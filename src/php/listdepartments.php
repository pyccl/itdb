<SCRIPT LANGUAGE="JavaScript">
$(function () {
    $('table#contlisttbl').dataTable({
        "sPaginationType": "full_numbers",
        "bJQueryUI": true,
        "iDisplayLength": 40,
        "aLengthMenu": [[40, 50, 100, -1], [40, 50, 100, "All"]],
        "bLengthChange": true,
        "bFilter": true,
        "bSort": false,  // 关闭插件自带排序
        "bInfo": true,
        "bAutoWidth": true,
        "sDom": '<"H"Tlpf>rt<"F"ip>',
        "oTableTools": {
            "sSwfPath": "swf/copy_cvs_xls_pdf.swf"
        },
        "aoColumns": [
            null, // ID
            null, // 部门名称
            null, // 排序
            null, // 描述
            null  // 创建时间
        ],
        "oLanguage": {
            "oPaginate": {
                "sFirst": "<?php te('First'); ?>",
                "sPrevious": "<?php te('Previous'); ?>",
                "sNext": "<?php te('Next'); ?>",
                "sLast": "<?php te('Last'); ?>"
            },
            "sProcessing": "<?php te('Processing...'); ?>",
            "sLengthMenu": "<?php te('Show'); ?> _MENU_ <?php te('entries'); ?>",
            "sZeroRecords": "<?php te('No matching records found'); ?>",
            "sInfo": "<?php te('Showing'); ?> _START_ <?php te('to'); ?> _END_ <?php te('of'); ?> _TOTAL_ <?php te('entries'); ?>",
            "sInfoEmpty": "<?php te('Showing 0 to 0 of 0 entries'); ?>",
            "sInfoFiltered": "(<?php te('Filtered from'); ?> _MAX_ <?php te('total entries'); ?>)",
            "sInfoPostFix": "",
            "sSearch": "<?php te('Search'); ?>:",
            "sEmptyTable": "<?php te('No data available in table'); ?>"
        }
    });
});
</SCRIPT>

<?php
if (!isset($initok)) {
    echo t("do not run this script directly");
    exit;
}

/* 部门列表页面 */
// 1. 获取所有数据
$sql = " 
    SELECT 
        id, 
        name, 
        parent_id, 
        sort_order, 
        description, 
        created_time 
    FROM departments 
";
$sth = db_execute($dbh, $sql);
$all_data = $sth->fetchAll(PDO::FETCH_ASSOC);

// 2. 构建树并排序的核心函数
function buildDepartmentTree($elements, $parentId = 0) {
    $branch = array();
    
    // 找出当前层级的所有子节点
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            // 递归获取更深层的子节点
            $children = buildDepartmentTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    
    // --- 核心修正：严格判断 NULL，强制转换为整数 ---
    usort($branch, function($a, $b) {
        // 1. 强制转换为整数 (防止数据库返回字符串导致比较错误)
        $sortA = (int)$a['sort_order'];
        $sortB = (int)$b['sort_order'];
        
        // 2. 判断是否为“无排序号”
        // 注意：这里不再使用 empty()，因为它会把 0 也当成空。
        // 我们严格判断是否为 null。如果数据库里存的是空字符串，这里可能需要调整为 $a['sort_order'] === ''
        $isNullA = ($a['sort_order'] === null);
        $isNullB = ($b['sort_order'] === null);
        
        // 3. 加权处理：如果是 null，给一个极大的值 (排到最后)；否则使用原始数值 (支持 0, -1 等)
        $valA = $isNullA ? 999999 : $sortA;
        $valB = $isNullB ? 999999 : $sortB;
        
        // 4. 先比较处理后的数值
        if ($valA != $valB) {
            return $valA - $valB;
        }
        
        // 5. 如果数值相同，按 ID 升序
        return $a['id'] - $b['id'];
    });
    
    return $branch;
}

// 3. 扁平化并生成树形显示文本
$sorted_list = array();

function flattenTree($tree, $depth = 0) {
    global $sorted_list;
    
    foreach ($tree as $node) {
        // 顶级部门：直接显示名称
        if ($depth == 0) {
            $node['display_name'] = $node['name'];
        } else {
            // 子级部门逻辑
            $padding = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $depth - 1);
            $node['display_name'] = $padding . "┕┉ " . $node['name'];
        }
        
        $node['depth'] = $depth;
        $sorted_list[] = $node;

        // 递归处理子节点
        if (isset($node['children']) && !empty($node['children'])) {
            flattenTree($node['children'], $depth + 1);
        }
    }
}

// 执行构建
$tree = buildDepartmentTree($all_data);
flattenTree($tree);

?>

<h1>
    <?php te("Departments List");?>
    <a title='<?php te("Add new Department");?>' href='<?php echo $scriptname?>?action=editdepartments&amp;id=new'>
        <img border=0 src='images/add.png' >
    </a>
</h1>

<table class='display' width='100%' id='contlisttbl'>
<thead>
<tr>
    <th width='5%'><?php te("ID");?></th>
    <th width='30%'><?php te("Department Name");?></th>
    <th width='10%'><?php te("Sort Order");?></th>
    <th width='35%'><?php te("Description");?></th>
    <th width='15%'><?php te("Created Time");?></th>
</tr>
</thead>
<tbody>
<?php
$i=0; 
foreach ($sorted_list as $r) { 
    $i++;
    echo "\n<tr id='trid{$r['id']}'>";
    
    // ID
    echo "<td><a class='editid' href='$scriptname?action=editdepartments&amp;id=".$r['id']."'>".$r['id']."</a></td>\n";
    
    // 名称
    $safe_name = str_replace(array("&", "<", ">"), array("&amp;", "&lt;", "&gt;"), $r['name']);
    $display_html = "";
    
    if ($r['depth'] == 0) {
        $display_html = $safe_name;
    } else {
        $padding = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $r['depth'] - 1);
        $display_html = $padding . "┕┉ " . $safe_name;
    }
    
    echo "<td style='font-family: monospace; white-space: nowrap;'>".$display_html."</td>\n";
    
    // 排序序号
    echo "<td>".(isset($r['sort_order']) && $r['sort_order'] !== '' ? $r['sort_order'] : "")."</td>\n";
    
    // 描述
    echo "<td>".htmlspecialchars($r['description'])."</td>\n";
    
    // 时间
    if (empty($r['created_time'])) {
        $dateDisplay = t('N/A');
    } else {
        $timestamp = strtotime($r['created_time']);
        if ($timestamp === false || $timestamp < 1) {
            $dateDisplay = $r['created_time'];
        } else {
            $format = isset($dateparam) ? $dateparam : 'Y-m-d H:i';
            $dateDisplay = date($format, $timestamp);
        }
    }
    echo "<td><span title='".htmlspecialchars($r['created_time'])."'>".$dateDisplay."</span></td>\n";
    
    echo "</tr>\n";
} 
?>
</tbody>
</table>

</form>
</body>
</html>