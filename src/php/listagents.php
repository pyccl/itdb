<SCRIPT LANGUAGE="JavaScript"> 
$(function () {
 //$('input#agentlistfilter').quicksearch('table#agentlisttbl tbody tr');

  $('table#agentlisttbl').dataTable({
                "sPaginationType": "full_numbers",
                "bJQueryUI": true,
                "iDisplayLength": 18,
                "aLengthMenu": [[10, 18, 25, 50, 100, -1], [10, 18, 25, 50, 100, "All"]],
                "bLengthChange": true,
                "bFilter": true,
                "bSort": true,
                "bInfo": true,
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
            "sEmptyTable": "<?php te('No data available in table'); ?>"
        }

  });
});

</SCRIPT>
<?php 

if (!isset($initok)) {echo t("do not run this script directly");exit;}

/* Spiros Ioannou 2009 , sivann _at_ gmail.com */

$sql="SELECT * from agents order by title,type";
$sth=db_execute($dbh,$sql);
?>

<h1><?php te("Agents");?> <a title='<?php te("Add new Agent");?>' href='<?php echo $scriptname?>?action=editagent&amp;id=new'><img border=0 src='images/add.png' ></a>
</h1>

<table  class='display' width='100%' border=0 id='agentlisttbl'>

<thead>
<tr>
  <th style='width:70px'><?php te("ID");?></th>
  <th><?php te("Type");?></th>
  <th style='width:160px'><?php te("Title");?></th>
  <th style='width:150px'><?php te("Contact Info");?></th>
  <th><?php te("Contacts");?></th>
</tr>
</thead>
<tbody>
<?php 

$i=0;
/// print actions list
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
  $i1++;
  $type="";

  if ($r['type']&1) $type.="<span style='color:#b00;'>".t("Buyer")."</span>";
  if ($r['type']&2) $type.=" <span style='color:#a0b;'>".t("S/W Manufacturer")."</span>";
  if ($r['type']&8) $type.=" <span style='color:#600;'>".t("H/W Manufacturer")."</span>";
  if ($r['type']&4) $type.=" <span style='color:#0a0;'>".t("Vendor")."</span>";
  if ($r['type']&16) $type.=" <span style='color:#44e;'>".t("Contractor")."</span>";

  $allcontacts=explode("|",trim($r['contacts']));
  if ((count($allcontacts)==1) && (trim($allcontacts[0]==""))) $allcontacts=array();
  if ((count($allcontacts)==1) && (trim($allcontacts[0]=="####"))) $allcontacts=array();

  echo "\n<tr id='trid{$r['id']}'>";
  echo "<td><a class='editid' href='$scriptname?action=editagent&amp;id=".$r['id']."'>{$r['id']}</a></td>\n";
  echo "<td style='padding-left:2px;padding-right:2px;'>$type</td>\n";
  echo "<td style='padding-left:2px;padding-right:2px;'>{$r['title']}</td>\n";
  echo "<td style='padding-left:2px;padding-right:2px;'>{$r['contactinfo']}</td>\n";

  echo "<td>\n";

  echo "<table cellspacing=0 style='width:100%;border:0'>";
  for ($i=0;$i<count($allcontacts);$i++) {
    $row=explode("#",$allcontacts[$i]);
    $name=$row[0];
    $phones=$row[1];
    $email=$row[2];
    $role=$row[3];
    $comments=$row[4];

    ?>
    <tr>
	<td style='border:0;border-bottom:1px dotted #aaa;width:100px'><?php echo $name?> </td>
	<td style='border:0;border-bottom:1px dotted #aaa;width:100px'><?php echo $phones?></td>
	<td style='border:0;border-bottom:1px dotted #aaa;width:100px'><?php echo $email?></td>
	<td style='border:0;border-bottom:1px dotted #aaa;width:100px'><?php echo $role?></td>
	<td style='border:0;border-bottom:1px dotted #aaa;width:100px'><?php echo $comments?></td>
    </tr>
    <?php 
  }
  echo "</table>\n";
  echo "</td>";
  echo "</tr>\n\n";
}

?>

</tbody>
</table>

</form>
</body>
</html>
