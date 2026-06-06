<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}
?>
<script type="text/javascript">
$(function () {
  $('table#histtbl').dataTable({
                "sPaginationType": "full_numbers",
                "bJQueryUI": true,
                "iDisplayLength": 25,
                "bLengthChange": true,
                "bFilter": true,
                "bSort": true,
                "bInfo": true,
                //"sDom": '<"H"lfr>t<"F"ip>',
                "sDom": '<"H"Tlpf>rt<"F"ip>',
                "oTableTools": {
                        "sSwfPath": "swf/copy_cvs_xls_pdf.swf"
                },
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
		    		"sUrl": "",
		    		"sEmptyTable": "<?php te('No data available in table'); ?>"
                }

  });
});

</script>

<?php 

/* Spiros Ioannou 2009 , sivann _at_ gmail.com */


if (isset($sqlsrch) && !empty($sqlsrch)) 
  $where = "where sql like '%$sqlsrch%'";
else { 
 $sqlsrch="";
 $where="";
}
$sth_setting = $dbh->query("SELECT dateformat,timeformat FROM settings LIMIT 1");
$settings = $sth_setting->fetch(PDO::FETCH_ASSOC);
?>
<h1><?php te("DB log");?></h1>
<table class='display' width='100%' id='histtbl'>
<thead>
<tr><th><?php te("ID");?></th>
     <th><?php te("Time");?></th>
     <th><?php te("SQL");?></th>
     <th><?php te("IP");?></th>
     <th><?php te("User");?></th>
     </tr>


</thead>
<tbody>


<?php


$sql="SELECT * FROM history  $where order by id desc ";

/// make db query
$sth=db_execute($dbh,$sql);

/// display results
// display results
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
    // 2seconds

	// 读取系统设置（已在init.php加载，直接用）
	$sth_setting = $dbh->query("SELECT dateformat,timeformat FROM settings LIMIT 1");
	$settings = $sth_setting->fetch(PDO::FETCH_ASSOC);
	
	// 使用你function.php里的统一格式化函数

	$d = !empty($r['date']) ? format_date($r['date'], $settings, true, true) : '-';

    // --- 核心修改部分：处理 SQL 字段 ---
    $full_sql = $r['sql']; // 先不转义，留给下面统一处理
    // 检查长度，如果超过 150 字符则截断并添加省略号
    if (strlen($full_sql) > 150) {
        $display_sql = substr($full_sql, 0, 150) . '...';
    } else {
        $display_sql = $full_sql;
    }
    // --- 修改结束 ---

    // table row (注意：这里对 display_sql 进行了转义，title 用了双引号)
    echo "\n<tr>".
         "\n <td>".$r['id']."</td>".
         "\n <td>$d&nbsp;</td>".
         "\n <td style='font-size:0.8em; max-width: 300px;' title=\"".htmlspecialchars($full_sql, ENT_QUOTES). "\">" . htmlspecialchars($display_sql) . "</td>". 
         "\n <td>".$r['ip']."</td>".
         "\n <td>".$r['authuser']."&nbsp;</td>".
         "\n</tr>";
}


echo "</tbody>\n";
echo "</table>\n";

?>
