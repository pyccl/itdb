<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}
?>
<SCRIPT LANGUAGE="JavaScript"> 
$(function () {
  $('table#userslisttbl').dataTable({
	"sPaginationType": "full_numbers",
	"bJQueryUI": true,
	"iDisplayLength": 25,
	"bLengthChange": true,
	"bFilter": true,
	"bSort": true,
	"bInfo": true,
	"sDom": '<"H"Tlpf>rt<"F"ip>',
	"aaSorting": [],
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
</SCRIPT>
<?php 

// 全局预加载所有卡片，用于快速匹配
$all_cards = getDashboardCards($dbh);
$card_map = [];
foreach ($all_cards as $c) {
    $card_map[$c['id']] = $c;
}

$sql="SELECT * from users ORDER by username ASC";
$sth=db_execute($dbh,$sql);
?>
<h1><?php te("Users");?> <a title='<?php te("Add new User");?>' href='<?php echo $scriptname?>?action=edituser&amp;id=new'><img border=0 src='images/add.png' ></a>
</h1>

<table class='display' width="100%" id='userslisttbl'>
<thead>
<tr>
  <th width='2%'><?php te("ID");?></th>
  <th width='10%'><?php te("Username");?></th>
  <th><?php te("User Description");?></th>
  <th width='12%'><?php te("Type");?></th>
  <th><?php te("Dashboard Cards");?></th>
</tr>
</thead>
<tbody>
<?php 
$usertype[0]=t("Full Access");
$usertype[1]=t("Read Only");
$usertype[2]=t("copied from LDAP (read only)");

while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
    $card_ids = !empty($r['dashboard_cards']) ? explode(',', $r['dashboard_cards']) : [];
    $card_html = '';
    foreach ($card_ids as $cid) {
        $cid = trim($cid);
        if (isset($card_map[$cid])) {
            $c = $card_map[$cid];
            $icon  = htmlspecialchars($c['icon']);
            $title = htmlspecialchars($c['title']);
            $color = htmlspecialchars($c['color']);
            $card_html .= <<<HTML
<span style="display:inline-block; margin:0 8px 4px 0; white-space:nowrap;">
    <span style="color:$color; font-weight:bold;">$icon</span>
    <span style="color:$color;">$title</span>
</span>
HTML;
        }
    }
    if (empty($card_html)) {
        $card_html = '<span style="color:#999;">—</span>';
    }
?>
<tr>
  <td><a class='editid' href="<?php echo $scriptname?>?action=edituser&amp;id=<?php echo $r['id']?>"><?php echo $r['id']?></a></td>
  <td><?php echo $r['username']?></td>
  <td><?php echo $r['userdesc']?></td>
  <td><?php echo $usertype[$r['usertype']]?></td>
  <td style="line-height:1.5; padding:6px 8px;"><?php echo $card_html?></td>
</tr>
<?php } ?>
</tbody>
</table>

</form>
</body>
</html>
