<SCRIPT LANGUAGE="JavaScript">
$(function () {
    $('table#contlisttbl').dataTable({
        "sPaginationType": "full_numbers",
        "bJQueryUI": true,
        "iDisplayLength": 25,
        "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "bLengthChange": true,
        "bFilter": true,
        "bSort": true,
        "bInfo": true,
        "bAutoWidth": true,
        "sDom": '<"H"Tlpf>rt<"F"ip>',
        "oTableTools": {
            "sSwfPath": "swf/copy_cvs_xls_pdf.swf"
        },
        "aoColumns": [
            null, // ID
            null, // 姓名
            null, // 工号
            null, // 部门
            null, // 职位
            null, // 邮箱
            null, // 电话
            { "sType": "title-numeric" }, // 入职日期 (需要特殊排序)
            null  // 状态
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

    // 用于日期排序的辅助函数 (如果日期是通过 title 属性存储的)
    jQuery.fn.dataTableExt.oSort['title-numeric-asc'] = function(a,b) {
        var x = a.match(/title="*(-?[0-9]+)/)[1];
        var y = b.match(/title="*(-?[0-9]+)/)[1];
        x = parseFloat( x );
        y = parseFloat( y );
        return ((x < y) ? -1 : ((x > y) ? 1 : 0));
    };

    jQuery.fn.dataTableExt.oSort['title-numeric-desc'] = function(a,b) {
        var x = a.match(/title="*(-?[0-9]+)/)[1];
        var y = b.match(/title="*(-?[0-9]+)/)[1];
        x = parseFloat( x );
        y = parseFloat( y );
        return ((x < y) ? 1 : ((x > y) ? -1 : 0));
    };
});
</SCRIPT>

<?php
if (!isset($initok)) {
    echo t("do not run this script directly");
    exit;
}

/* 
    员工列表页面
    作者: [你的名字]
*/

// 1. SQL 查询：连接 employees 和 departments 表，获取部门名称
// 如果部门被删除，LEFT JOIN 可以确保员工记录依然显示 (显示为 "无部门" 或 NULL)
$sql = "
    SELECT 
        e.id,
        e.name,
        e.employee_code,
        e.position,
        e.email,
        e.phone,
        e.hire_date,
        e.status,
        e.created_time,
        d.name as department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY e.id DESC
";

$sth = db_execute($dbh, $sql);
?>

<h1>
    <?php te("Employees List");?> 
    <a title='<?php te("Add new Employee");?>' href='<?php echo $scriptname?>?action=editemployees&amp;id=new'>
        <img border=0 src='images/add.png' >
    </a>
</h1>

<table class='display' width='100%' id='contlisttbl'>
    <thead>
        <tr>
            <th width='5%'><?php te("ID");?></th>
            <th width='10%'><?php te("Full Name");?></th>
            <th width='10%'><?php te("Employees Code");?></th>
            <th width='10%'><?php te("Department");?></th>
            <th width='10%'><?php te("Position");?></th>
            <th width='15%'><?php te("Email");?></th>
            <th width='10%'><?php te("Phone");?></th>
            <th width='10%'><?php te("Hire Date");?></th>
            <th width='5%'><?php te("Status");?></th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $i=0; 
        while ($r=$sth->fetch(PDO::FETCH_ASSOC)) { 
            $i++;
            echo "\n<tr id='trid{$r['id']}'>";
            
            // ID 列：带编辑链接
            echo "<td><a class='editid' href='$scriptname?action=editemployees&amp;id=".$r['id']."'>".$r['id']."</a></td>\n";
            
            // 姓名
            echo "<td>".$r['name']."</td>\n";
            
            // 工号
            echo "<td>".$r['employee_code']."</td>\n";
            
            // 部门 (如果员工没有部门，显示 "None")
            echo "<td>".($r['department_name'] ?: 'None')."</td>\n";
            
            // 职位
            echo "<td>".$r['position']."</td>\n";
            
            // 邮箱
            echo "<td>".$r['email']."</td>\n";
            
            // 电话
            echo "<td>".$r['phone']."</td>\n";
            
            // 入职日期 (使用时间戳格式化，假设 $dateparam 是 "Y-m-d" 格式)
            // 使用 <span title> 存储时间戳，以便 DataTables 正确排序
            $dateDisplay = $r['hire_date'] ? date($dateparam, $r['hire_date']) : 'N/A';
            echo "<td><span title='{$r['hire_date']}'>".$dateDisplay."</span></td>\n";
            
            // 状态 (简单显示 0 或 1，或者你可以定义 0=离职, 1=在职)
            // 这里简单显示数值，你可以根据需要修改为图标或文字
            $statusText = $r['status'] ? t('Active') : t('Inactive');
            echo "<td>".$statusText."</td>\n";
            
            echo "</tr>\n";
        } 
        ?>
    </tbody>
</table>
</form>
</body>
</html>
