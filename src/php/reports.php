<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

$chart_type = 'pie'; 
// 只保留 pie, bar, horbar
if (isset($_GET['chart']) && in_array(strtolower($_GET['chart']), array('pie', 'bar', 'horbar'))) { 
    $chart_type = strtolower($_GET['chart']); 
}


// --- PHP 5.2 兼容性修复：如果没有 json_encode 函数，则定义它 ---
if (!function_exists('json_encode')) {
    function json_encode($data) {
        if (is_array($data)) {
            $is_list = array_keys($data) === range(0, count($data) - 1);
            $result = array();
            foreach ($data as $key => $value) {
                if (is_string($value)) $value = '"' . addslashes($value) . '"';
                if (is_array($value)) $value = json_encode($value);
                if ($is_list) {
                    $result[] = $value;
                } else {
                    $result[] = '"' . $key . '":' . $value;
                }
            }
            if ($is_list) return '[' . implode(',', $result) . ']';
            return '{' . implode(',', $result) . '}';
        }
        return $data;
    }
}

if (isset($sqlsrch) && !empty($sqlsrch)) $where = "where sql like '%$sqlsrch%'";
else {
    $sqlsrch = "";
    $where = "";
}

$reports = array(
    'itemperagent' => 📊.t('Number of items per Manufacturer (Agent)'),
    'softwareperagent' => 📊.t('Number of installed Software per Manufacturer (Agent)'),
    'invoicesperagent' => 📊.t('Number of invoices per Vendor (Agent)'),
    'itemsperstatus' => 📊.t('Number of items per Status'),
    'percsupitems' => 📊.t('Number of Items under support'),
    'itemlistperlocation' => 📊.t('Item list per location'),
    'itemsendwarranty' => t('Items with warranty end date close to (before or after) today'),
    'allips' => t('List items with defined IPv4 numbers'),
    'noinvoice' => t('Items without invoices'),
    'nolocation' => t('Items without location'),
    'depreciation3' => t('Item depreciation value 3 years'),
    'depreciation5' => t('Item depreciation value 5 years')
);
?>
<h1><?php te("Reports"); ?></h1>
<div style='width:100%;clear:both; max-width:1180px; margin:0 auto; overflow:hidden;'>
    <div style='float:left;text-align:left;padding:5px;height:350px;overflow-y:auto;width:300px;border:1px solid #cecece;'>
        <h2><?php te("Select Report"); ?></h2>
        <ul>
            <?php
            $curdesc = "";
            foreach ($reports as $q => $desc) {
                if ($q == $query) {
                    echo "<li><b><a href='$scriptname?action=$action&amp;query=$q'>$desc</a></b></li>";
                    $curdesc = $desc;
                } else
                    echo "<li><a href='$scriptname?action=$action&amp;query=$q'>$desc</a></li>";
            }
            ?>
        </ul>
    </div>
    <!-- 图表专门用一个 div -->
    <div id="chart-container" style="padding:5px;float:left;height:350px;width:720px;border:1px solid #cecece; "></div>
    <!-- 表格数据将显示在下面的 div 中 -->
    <div id="table-container" style="width:100%;clear:both; padding:10px;">
        <!-- 表格内容将被插入到这里 -->
    </div>
</div>
<div style='width:100%;clear:both;'>
    <?php
    switch ($query) {
        case "depreciation5":
            $sql = "select items.id as ID,typedesc as type, agents.title as manufacturer ,model, strftime('%Y-%m-%d', purchasedate,'unixepoch') AS PurchaseDate, purchprice as PurchasePrice, cast( ((strftime('%s','now') - purchasedate)/(60*60*24*30.4)*(purchasedate AND 1)) AS INTEGER) as Months , (purchprice-purchprice/60*cast( ((strftime('%s','now') - purchasedate)/(60*60*24*30.4)*(purchasedate AND 1)) AS INTEGER)) as CurrentValue FROM items,itemtypes,agents WHERE agents.id=manufacturerid AND itemtypes.id=items.itemtypeid ";
            $editlnk = "$scriptname?action=edititem&id";
            break;
        case "depreciation3":
            $sql = "select items.id as ID,typedesc as type, agents.title as manufacturer ,model, strftime('%Y-%m-%d', purchasedate,'unixepoch') AS PurchaseDate, purchprice as PurchasePrice, cast( ((strftime('%s','now') - purchasedate)/(60*60*24*30.4)*(purchasedate AND 1)) AS INTEGER) as Months , (purchprice-purchprice/36*cast( ((strftime('%s','now') - purchasedate)/(60*60*24*30.4)*(purchasedate AND 1)) AS INTEGER)) as CurrentValue FROM items,itemtypes,agents WHERE agents.id=manufacturerid AND itemtypes.id=items.itemtypeid ";
            $editlnk = "$scriptname?action=edititem&id";
            break;
        case "noinvoice":
            $sql = "select items.id as ID,typedesc as type, agents.title as manufacturer ,model, strftime('%Y-%m-%d', purchasedate,'unixepoch') AS PurchaseDate FROM items,itemtypes,agents WHERE agents.id=manufacturerid AND itemtypes.id=items.itemtypeid AND items.ID not in (select itemid from item2inv)";
            $editlnk = "$scriptname?action=edititem&id";
            break;
        case "nolocation":
            $sql = "select items.id as ID,typedesc as type, agents.title as manufacturer ,model FROM items,itemtypes,agents WHERE agents.id=manufacturerid AND itemtypes.id=items.itemtypeid AND (locationid='' OR locationid is null)";
            $editlnk = "$scriptname?action=edititem&id";
            break;
        case "allips":
            $sql = "select items.id as ID,ipv4,ipv6, typedesc as type, agents.title as manufacturer, model, dnsname, label FROM items,itemtypes,agents WHERE agents.id=manufacturerid AND itemtypes.id=items.itemtypeid AND ipv4 <> '' order by ipv4";
            $editlnk = "$scriptname?action=edititem&id";
            break;
		case "itemlistperlocation":
		    // 1. 先获取国际化的字符串
		    $noLocText = t("No Location");
		    
		    $sql = "SELECT 
		                COUNT(*) as totalcount, 
		                -- 2. 在 SQL 中使用刚才获取的变量
		                COALESCE(locations.name || ' Floor:' || locations.floor, '$noLocText') as Location 
		            FROM items 
		            -- 3. 关键：使用 LEFT JOIN 保留无地点记录
		            LEFT JOIN locations on items.locationid=locations.id 
		            -- 4. 关键：按 location.id 分组，而不是按 name/floor 分组。
		            -- 这是因为如果两个地点同名，GROUP BY name 会把它们合并，导致无地点数据被吞掉。
		            GROUP BY items.locationid 
		            ORDER BY totalcount desc";
		    
		    $graph['type'] = $chart_type;
		    $graph['colx'] = "Location";
		    $graph['coly'] = "totalcount";
		    $graph['limit'] = 15;
		    break;
		
        case "itemperagent":
            $sql = "select count(*) as totalcount,agents.title as Agent, agents.id as ID from items,agents WHERE agents.id=items.manufacturerid group by manufacturerid order by totalcount desc;";
            $editlnk = "$scriptname?action=editagent&id";
            $graph['type'] = $chart_type;
            $graph['colx'] = "Agent";
            $graph['coly'] = "totalcount";
            $graph['limit'] = 15;
            break;
        case "softwareperagent":
            $sql = "select count(*) as totalcount,agents.title as Agent, agents.id as ID from software,agents WHERE agents.id=software.manufacturerid group by manufacturerid order by totalcount desc;";
            $editlnk = "$scriptname?action=editagent&id";
            $graph['type'] = $chart_type;
            $graph['colx'] = "Agent";
            $graph['coly'] = "totalcount";
            $graph['limit'] = 15;
            break;
        case "invoicesperagent":
            $sql = "select count(*) as totalcount,agents.title as Agent, agents.id as ID from invoices,agents WHERE agents.id=invoices.vendorid group by vendorid order by totalcount desc;";
            $editlnk = "$scriptname?action=editagent&id";
            $graph['type'] = $chart_type;
            $graph['colx'] = "Agent";
            $graph['coly'] = "totalcount";
            $graph['limit'] = 15;
            break;
        case "itemsendwarranty":
            $t = time();
            $sql = "select items.id as ID,ipv4, typedesc as type, agents.title as manufacturer, model, dnsname, label, (strftime('%s',purchasedate,'unixepoch','+'||warrantymonths||' months')-$t)/(60*60*24) RemainingDays FROM items,itemtypes,agents WHERE agents.id=manufacturerid AND itemtypes.id=items.itemtypeid AND RemainingDays>-360 AND RemainingDays<360 order by RemainingDays ";
            $editlnk = "$scriptname?action=edititem&id";
            break;
		case "percsupitems":
		    // 1. 定义翻译映射表（关键：这里定义了图表显示的文字）
		    $NotExpired=t('Not Expired');
		    $Expired=t('Expired');
		    $Undefined=t('Undefined');
		
		    // 2. 定义 SQL (保持原样，仅用于查询数量)
		    $sql = "select '$NotExpired' as TypeCode, 
		                   (select count(id) from items where ((purchasedate+warrantymonths*30*24*60*60-strftime(\"%s\"))/(60*60*24)) >1 AND purchasedate>0 AND warrantymonths>0) as Items 
		            UNION 
		            SELECT '$Expired' as TypeCode, 
		                   (select count(id) from items where ((purchasedate+warrantymonths*30*24*60*60-strftime(\"%s\"))/(60*60*24)) <=1 AND purchasedate>0 AND warrantymonths>0) as Items 
		            UNION 
		            SELECT '$Undefined' as TypeCode, 
		                   (select count(id) from items where purchasedate=0 OR purchasedate is null OR warrantymonths=0 OR warrantymonths is null) as Items 
		            ";
		    
		    // 3. 手动构建图表数据 (关键：在这里把翻译好的数据存入 $plot_raw)
		    // 先清空
		    $plot_raw = array();
		    
		    // 手动执行查询
		    $sth_temp = db_execute($dbh, $sql);
		    while ($r_temp = $sth_temp->fetch(PDO::FETCH_ASSOC)) {
		        $code = $r_temp['TypeCode'];
		        $value = (int)$r_temp['Items'];
		        
		        // 获取翻译后的名称 (这才是图表显示的内容)
		        $displayName = isset($statusLabels[$code]) ? $statusLabels[$code] : $code;
		        
		        // 构建图表数据
		        $plot_raw[] = array($displayName, $value);
		        
		        // 额外：为了防止下面的通用循环重复读取，我们把结果存起来
		        $tempData[] = $r_temp;
		    }
		
		    // 4. 设置图表配置
		    $graph['type'] = $chart_type;
		    $graph['colx'] = "TypeCode"; // 这个字段名必须和 SQL 里的别名一致
		    $graph['coly'] = "Items";
		    $graph['limit'] = 15;		    
		    break;

        case "itemsperstatus":
            $sql = " SELECT st.id, st.statusdesc as status, COUNT(i.id) AS totalcount, st.color AS color FROM statustypes st LEFT JOIN items i ON st.id = i.status GROUP BY st.id, st.statusdesc, st.color ORDER BY totalcount DESC ";
            $graph['type'] = $chart_type;
			$graph['colx'] = "status";
			$graph['coly'] = "totalcount";
			$graph['limit'] = 20;
			break;
        default:
            exit;
    }
    
		// --- 定义不需要显示图表和下拉框的报表列表 ---
		// 这些报表通常是明细列表，只显示表格
		$noGraphReports = array('noinvoice', 'nolocation', 'allips', 'itemsendwarranty', 'depreciation3', 'depreciation5');
		// 检查当前报表是否在列表中
		$showChartElements = !in_array($query, $noGraphReports);
		
    
    ?>
	<div style='padding-top:15px;clear:both'>
	    <h2><?php echo $curdesc ?></h2>
	    
	    <!-- 只有当报表需要图表时，才显示下拉框 -->
	    <?php if ($showChartElements): ?>
	    <div style="margin-bottom: 10px;">
	        <label><?php te("Chart Type"); ?>:</label>
		<!-- 图表类型切换按钮 -->
			<div class="chart-toggle" style="margin-bottom: 15px;">
			    <button type="button" data-type="pie" class="btn <?php echo ($chart_type == 'pie') ? 'active' : ''; ?>">
			        <?php echo t("Pie Chart"); ?>
			    </button>
			    <button type="button" data-type="bar" class="btn <?php echo ($chart_type == 'bar') ? 'active' : ''; ?>">
			        <?php echo t("Vertical Bar"); ?>
			    </button>
			    <button type="button" data-type="horbar" class="btn <?php echo ($chart_type == 'horbar') ? 'active' : ''; ?>">
			        <?php echo t("Horizontal Bar"); ?>
			    </button>
			</div>
	    <?php endif; ?>
        <input style='color:#909090' id="repfilter" name="repfilter" class='filter' value='<?php te("Filter"); ?>' onclick='this.style.color="#000"; this.value=""' size="20">
        <table id='reptbl' class='sortable'>
            <?php

            $sth = db_execute($dbh, $sql);
            $row = 0;
			// --- 特殊处理：如果报表已经提供了 $plot_raw (如 percsupitems)，则跳过数据库查询 ---
			if (isset($plot_raw) && !empty($plot_raw)) {
			    // 如果 $plot_raw 已经有数据了，说明图表数据已经手动构建好了
			    // 我们需要从临时变量中获取表格数据（或者重新查询，这里为了简单重新用 SQL 查）
			    $sth = db_execute($dbh, $sql); 
			} else {
			    // --- 普通报表：执行 SQL 查询 ---
			    $sth = db_execute($dbh, $sql);
			    // --- 数据容器 ---
			    $plot_raw = array();
			}
			
			$row = 0;

            // --- 数据容器 (关键修改) ---
            // 1. $plot_raw: 用于 Pie 图 (格式: [['Label', 10], ...])
            // 2. $plot_values: 用于 Bar/Line (格式: [10, 20, 30])
            // 3. $plot_labels: 用于 Bar/Line 的 X 轴 (格式: ['A', 'B', 'C'])
            $plot_raw = array();
            $plot_values = array();
            $plot_labels = array();

            while ($r = $sth->fetch(PDO::FETCH_ASSOC)) {
                echo "\n<tr>";
                if (!$row) {
                    echo "\n\t<th>#</th>";
                    foreach ($r as $k => $v) {
                        $displayText = $k;
                        $map = array();
                        switch ($query) {
                            case "depreciation5":
                            case "depreciation3":
                                $map = array('ID' => t('ID'), 'type' => t('Type'), 'manufacturer' => t('Manufacturer'), 'model' => t('Model'), 'PurchaseDate' => t('Purchase Date'), 'PurchasePrice' => t('Purchase Price'), 'Months' => t('Months'), 'CurrentValue' => t('Current Value'));
                                break;
                            case "noinvoice":
                                $map = array('ID' => t('ID'), 'type' => t('Type'), 'manufacturer' => t('Manufacturer'), 'model' => t('Model'), 'PurchaseDate' => t('Purchase Date'));
                                break;
                            case "nolocation":
                                $map = array('ID' => t('ID'), 'type' => t('Type'), 'manufacturer' => t('Manufacturer'), 'model' => t('Model'));
                                break;
                            case "allips":
                                $map = array('ID' => t('ID'), 'ipv4' => t('IPv4'), 'ipv6' => t('IPv6'), 'type' => t('Type'), 'manufacturer' => t('Manufacturer'), 'model' => t('Model'), 'dnsname' => t('DNS Name'), 'label' => t('Label'));
                                break;
                            case "itemlistperlocation":
                                $map = array('totalcount' => t('Total Count'), 'Location' => t('Location'), 'ID' => t('ID'), 'dnsname' => t('DNS Name'), 'ipv4' => t('IPv4'), 'ipv6' => t('IPv6'), 'label' => t('Label'), 'type' => t('Type'), 'manufacturer' => t('Manufacturer'), 'model' => t('Model'));
                                break;
                            case "itemperagent":
                            case "softwareperagent":
                            case "invoicesperagent":
                                $map = array('totalcount' => t('Total Count'), 'Agent' => t('Agent'), 'ID' => t('ID'));
                                break;
                            case "itemsendwarranty":
                                $map = array('ID' => t('ID'), 'ipv4' => t('IPv4'), 'type' => t('Type'), 'manufacturer' => t('Manufacturer'), 'model' => t('Model'), 'dnsname' => t('DNS Name'), 'label' => t('Label'), 'RemainingDays' => t('Remaining Days'));
                                break;
                            case "percsupitems":
		    					$map = array('TypeCode' => t('Status'), 'Items' => t('Total Count'));
                                break;
                            case "itemsperstatus":
                                $map = array('id' => t("ID"), 'status' => t('Status'), 'totalcount' => t('Total Count'), 'color' => t("Color"));
                                break;
                            default:
                                $map = array();
                        }
                        if (isset($map[$k])) {
                            $displayText = $map[$k];
                        }
                        echo "\n\t<th>$displayText</th>";
                    }
                    echo "\n</tr>\n<tr>";
                }

                // --- 数据分离逻辑 (关键修改) ---
                if (isset($graph['colx']) && isset($graph['coly'])) {
					// 如果上面的 SQL 失效了，这里作为最后防线
					$label = isset($r[$graph['colx']]) ? $r[$graph['colx']] : t("No Location");
					
                    $value = isset($r[$graph['coly']]) && is_numeric($r[$graph['coly']]) ? (float)$r[$graph['coly']] : 0;

                    // 1. 构建 Pie 图数据
                    $plot_raw[] = array($label, $value);

                    // 2. 构建 Bar/Line 图数据
                    $plot_labels[] = $label;
                    $plot_values[] = $value;
                }

                echo "\n\t<td>" . ($row + 1) . "</td>";
				foreach ($r as $k => $v) {
				    if ($k == "ID") {
				        echo "\n\t<td><a class=\"editid\" href=\"$editlnk=$v\">$v</a></td>";
				    } 
				    // --- 新增：针对 percsupitems 的状态翻译 ---
				    elseif ($query == "percsupitems" && $k == "TypeCode") {
				        // 定义相同的映射表逻辑
				        $tableLabels = array(
				            'NotExpired' => t('Not Expired'),
				            'Expired'    => t('Expired'),
				            'Undefined'  => t('Undefined')
				        );
				        $translatedStatus = isset($tableLabels[$v]) ? $tableLabels[$v] : $v;
				        echo "\n\t<td>$translatedStatus</td>";
				    } 
				    else {
				        echo "\n\t<td>$v</td>";
				    }
				}


                echo "</tr>\n";
                $row++;
        }
        echo "</tr>\n";
        $row++;
	    // --- 新增代码：如果是 percsupitems 报表，手动查询并添加 Total 行 ---
	    if ($query == "percsupitems") {
	        // 执行单独的 Total 查询
	        $sth_total = db_execute($dbh, $sql_total);
	        $r_total = $sth_total->fetch(PDO::FETCH_ASSOC);
	        $total_count = $r_total['Items'];
	        
	        // 输出 Total 行，并强制放在最底部
	        echo "<tr style='font-weight:bold; background-color: #f5f5f5;'>";
	        echo "<td></td>"; // 序号列留空
	        echo "<td>" . t('Total') . "</td>"; // 显示“总计”
	        echo "<td>$total_count</td>"; // 显示总数
	        // 如果有更多列，根据 $map 补齐空单元格，或者确保表格列数匹配
	        echo "</tr>";
	    }
	    ?>
	    </table>

    </div>
</div>
<script>
// --- 1. 数据准备 ---
var PHP_CONFIG = { 
    chartType: '<?php echo $chart_type; ?>', 
    pieData: <?php echo json_encode($plot_raw); ?>,
    barLineData: <?php echo json_encode($plot_values); ?>,
    barLineTicks: <?php echo json_encode($plot_labels); ?>,
    horBarData: <?php 
        $data = array();
        for ($i = count($plot_raw) - 1; $i >= 0; $i--) {
            $item = $plot_raw[$i];
            $data[] = array((float)$item[1], $item[0]);
        }
        echo json_encode($data);
    ?>
};

// --- 2. 绘图函数 ---
$(document).ready(function () {
    $('#chart-container').empty().removeClass('jqplot-target');

    function drawChart() {
        var plotOptions = { 
            grid: { background: '#ffffff', borderWidth: 0, shadow: false },
            legend: { show: false }
        };

        if (PHP_CONFIG.chartType === 'pie') {
            plotOptions.series = [{
                renderer: $.jqplot.PieRenderer, 
                rendererOptions: { 
                    sliceMargin: 3, showDataLabels: true, dataLabels: 'percent', 
                    dataLabelThreshold: 0, dataLabelFormatString: '%.2f%%', padding: 10
                }
            }];
            delete plotOptions.axes;
            plotOptions.legend.show = true;
            $.jqplot('chart-container', [PHP_CONFIG.pieData], plotOptions);
        }
        else if (PHP_CONFIG.chartType === 'bar') {
            plotOptions.series = [{
                renderer: $.jqplot.BarRenderer,
                rendererOptions: { varyBarColor: true },
                pointLabels: { show: true, formatString: '%d' }
            }];
            plotOptions.axes = { 
                xaxis: { 
                    renderer: $.jqplot.CategoryAxisRenderer, 
                    ticks: PHP_CONFIG.barLineTicks,
                    tickRenderer: $.jqplot.CanvasAxisTickRenderer,
                    tickOptions: {angle: -30, fontSize: '10pt'}
                }, 
                yaxis: { tickOptions: { formatString: '%d' } } 
            };
            $.jqplot('chart-container', [PHP_CONFIG.barLineData], plotOptions);
        }
        else if (PHP_CONFIG.chartType === 'horbar') {
            plotOptions.series = [{
                renderer: $.jqplot.BarRenderer,
                rendererOptions: { barDirection: 'horizontal', varyBarColor: true },
                pointLabels: { show: true, formatString: '%d' }
            }];
            plotOptions.axes = { 
                yaxis: { renderer: $.jqplot.CategoryAxisRenderer },
                xaxis: { pad: 1.1, tickOptions: { formatString: '%d' } } 
            };
            $.jqplot('chart-container', [PHP_CONFIG.horBarData], plotOptions);
        }
    }
    drawChart();

    $('.chart-toggle .btn').click(function () {
        $('.chart-toggle .btn').removeClass('active');
        $(this).addClass('active');
        var url = new URL(window.location);
        url.searchParams.set('chart', $(this).data('type'));
        window.location.href = url.href;
    });
});

// ====================== 原生筛选 不影响图表 ======================
window.onload = function(){
    var f = document.getElementById("repfilter");
    var t = document.getElementById("reptbl");
    if(!f || !t)return;
    f.onclick = function(){
        this.value = "";
        this.style.color = "#000";
    };
    f.oninput = function(){
        var k = this.value.toLowerCase();
        var r = t.getElementsByTagName("tr");
        for(var i=0;i<r.length;i++){
            if(r[i].getElementsByTagName("th").length>0)continue;
            var s = r[i].textContent.toLowerCase();
            r[i].style.display = s.indexOf(k)>-1 ? "" : "none";
        }
    };
};
</script>
