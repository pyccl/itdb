<?php 
/* Spiros Ioannou 2009 , sivann _at_ gmail.com */
require("../init.php");
if ($itemid=="new") {
  te("Cannot add log entries to unsaved items.");
  exit;
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" >
<script>
function filltoday()
{
var mydate= new Date()
var theyear=mydate.getFullYear()
var themonth=mydate.getMonth()+1
var thetoday=mydate.getDate()
var x=document.getElementById('newdate').value;
  if (x.length==0) {
<?php if ($settings['dateformat']=="ymd") {?>
    document.getElementById('newdate').value=theyear+"-"+themonth+"-"+thetoday;
<?php  } elseif ($settings['dateformat']=="dmy") {?>
    document.getElementById('newdate').value=thetoday+"/"+themonth+"/"+theyear;
<?php  } else {?>
    document.getElementById('newdate').value=themonth+"/"+thetoday+"/"+theyear;
<?php  }?>
  }
}
</script>
</head>
<body bgcolor="#ffffff">
<link rel="stylesheet" href="/css/itdb.css" type="text/css">

<?php 
if (!$authstatus) {
  echo "<b>$authmsg</b> <br>";
  echo "AuthStatus=$authstatus";
  exit;
}

$formvars=array("id", "actiondate","description","invoiceinfo");

if (isset($_POST['description'])) {
  $nrows=count($_POST['id']);
  for ($rn=0;$rn<$nrows;$rn++) {
    $id          = $_POST['id'][$rn];
    $desc        = trim($_POST['description'][$rn]);
    $inv         = trim($_POST['invoiceinfo'][$rn]);
    $actd        = trim($_POST['actiondate'][$rn]);

    // ===================== 新增：有内容才插入、才记日志 =====================
    if (($id == "new" || $id == t("new")) && strlen($desc) > 1) {
      $adate = empty($actd) ? time() : ymd2sec($actd);
      $sql="INSERT INTO actions (itemid,actiondate,description,invoiceinfo,isauto,entrydate) 
            VALUES ($itemid,$adate,'".addslashes($desc)."','".addslashes($inv)."',0,".time().")";
      $r=db_exec($dbh,$sql);
      $action_id = $dbh->lastInsertId();

      $new_data = [
        'action_id'   => $action_id,
        'description' => $desc,
        'invoiceinfo' => $inv,
        'actiondate'  => $actd
      ];
      
      addOperateLog(
          'item',
          'add action',
          'Added action log ID %s for item %s',
          array($action_id, $itemid),
          'item',
          $itemid,
          null,
          $new_data,
          1,
          ''
      );
    }
    // ===================== 修改：只有内容变了才更新、才记日志 =====================
    elseif ($id != "new" && $id != t("new") && is_numeric($id)) {
      $action_id = intval($id);
      // 取旧数据
      $old = $dbh->query("SELECT description,invoiceinfo,actiondate FROM actions WHERE id=$action_id")->fetch(PDO::FETCH_ASSOC);
      $old_desc = trim($old['description']);
      $old_inv  = trim($old['invoiceinfo']);
      $old_actd = format_date($old['actiondate'],$settings,true,false);

      // 判断：是否真的修改
      $changed = false;
      if ($old_desc != $desc) $changed = true;
      if ($old_inv != $inv) $changed = true;
      if ($old_actd != $actd) $changed = true;

      if (!$changed) {
        continue; // 没修改，跳过，不写日志
      }

      // 执行更新
      $sql="UPDATE actions SET 
            actiondate=".ymd2sec($actd).",
            description='".addslashes($desc)."',
            invoiceinfo='".addslashes($inv)."',
            isauto=0 WHERE id=$action_id";
      $r=db_exec($dbh,$sql);

      $old_data = [
          'action_id'   => $action_id,
          'description' => $old_desc,
          'invoiceinfo' => $old_inv,
          'actiondate'  => $old_actd
      ];
      $new_data = [
          'action_id'   => $action_id,
          'description' => $desc,
          'invoiceinfo' => $inv,
          'actiondate'  => $actd
      ];
      
      addOperateLog(
          'item',
          'update action',
          'Updated action log ID %s for item %s',
          array($action_id, $itemid),
          'item',
          $itemid,
          $old_data,
          $new_data,
          1,
          ''
      );
    }
  }
}

if (!isset($_GET['itemid']) || !strlen($_GET['itemid'])) {
  echo "$scriptname: ".t("wrong arguments");
  exit;
}
$itemid=$_GET['itemid'];
$sql="SELECT * from actions where itemid=$itemid order by actiondate";
$sth=db_execute($dbh,$sql);

if (!isset($_GET['detached']))
  $det="<a target=_blank href='$scriptname?itemid=$itemid&amp;detached=1'>
  <img src='/images/detach.gif' title='".t("Show in new window")."' border=0 align=absmiddle></a></caption>\n";
else 
  $det="";

echo "\n<form method=post name='actionaddfrm'>\n";
echo "<table align=center class=brdr border=0>\n";
echo "\n<caption><h2>".sprintf(t("Item Log (Item %d)"),$itemid)."</h2>$det</caption>\n";
echo "\n<tr><th>&nbsp;</th><th>".t("Action Date")."</th><th>".t("Description")."</th><th>".t("Invoice info")."</th><th>".t("Entry Date")."</th></tr>\n";

$i=0;
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
$i++;
    $d=!empty($r['actiondate'])?format_date($r['actiondate'],$settings,true,false):"-";
    $ed=!empty($r['entrydate'])?format_date($r['entrydate'],$settings,true,false):"-";

  if ($r['isauto']) {
    echo "\n<tr>\n";
    echo "<td>{$r['id']}</td>\n";
    echo "<td>$d</td>\n";
    echo "<td>{$r['description']}</td>\n";
    echo "<td>{$r['invoiceinfo']}</td>\n";
    echo "<td>$ed</td>\n";
    echo "</tr>\n\n";
  }
  else {
    echo "\n<tr>\n";
    echo "<td><input type=hidden name='id[]' value='".$r['id']."' readonly size=3>{$r['id']}</td>\n";
    echo "<td><input title='".t("d/m/y or yyyy")."' size=9 type=text name='actiondate[]' value=\"".$d."\"></td>\n";
    echo "<td><textarea wrap='soft' class=tarea3  name='description[]'>".$r['description']."</textarea></td>\n";
    echo "<td><input size=10 type=text name='invoiceinfo[]' value=\"".$r['invoiceinfo']."\"></td>\n";
    echo "<td>$ed</td>\n";
    echo "</tr>\n\n";
  }
}

echo "<tr><td><input type=text name='id[]' value='".t("new")."' readonly size=3></td>\n";
echo "<td><input title='".t("d/m/y or yyyy")."' size=9 type=text id='newdate' onclick='filltoday();' name='actiondate[]'></td>\n";
echo "<td><textarea wrap='soft' class=tarea3 name='description[]'></textarea></td>\n";
echo "<td><input size=10 type=text name='invoiceinfo[]' ></td>\n";
echo "<td></td>\n";
echo "<tr><td colspan=4><input value='".t("Save Action Log")."' type=submit></td></tr>\n";
?>
</table>
</form>
</body>
</html>
