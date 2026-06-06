<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}
?>
<SCRIPT LANGUAGE="JavaScript">
$(document).ready(function() {
  $("#tabs").tabs();
  $("#tabs").show();
  $("#locationid").change(function() {
    var locationid=$(this).val();
    var dataString = 'locationid='+ locationid;
    $.ajax ({
        type: "POST",
        url: "php/locarea_options_ajax.php",
        data: dataString,
        cache: false,
        success: function(html) {
          $("#locareaid").html(html);
        }
    });
  });
});
</SCRIPT>

<?php 
/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */

// 机柜表单字段（用于日志对比）
$formvars_rack = array(
    "locationid","locareaid","usize","depth","comments","model","label","revnums"
);

$sql="SELECT * FROM locations order by name";
$sth=$dbh->query($sql);
$locations=$sth->fetchAll(PDO::FETCH_ASSOC);

// -------------------------- 删除机柜 --------------------------
if (isset($_GET['delid'])) { 
  $delid=intval($_GET['delid']);
  if (!is_numeric($delid)) {
    echo t("Non numeric id")." delid=($delid)";
    exit;
  }
  $nitems=countitemsinrack($delid);
  if ($nitems>0) {
    echo t("<b>Rack not deleted: Please remove items first from this rack<br></b>\n");
    echo "<br><a href='javascript:history.go(-1);'>".t("Go back")."</a>\n</body></html>";
    exit;
  }
  else {
    delrack($delid,$dbh);

    // 日志：删除机柜（同edititem格式）
    addOperateLog(
        'rack',
        'delete',
        'Deleted rack ID %s',
        array($delid),
        'rack',
        $delid,
        null,
        null,
        1,
        ''
    );

    echo "<script>document.location='$scriptname?action=listracks'</script>\n";
    echo "<a href='$scriptname?action=listracks'>".t("Go here")."</a>\n</body></html>"; 
    exit;
  }
}

// -------------------------- 保存（新增/更新）机柜 --------------------------
if (isset($_POST['id'])) {
  $id=$_POST['id'];

  if ((empty($_POST['usize']))||(empty($_POST['depth'])))  {
    echo "<br><b>".t("Some <span class='mandatory'> mandatory</span> fields are missing").".</b><br>".
         "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
    exit;
  }

  // -------------------------- 新增机柜 --------------------------
  if ($_POST['id']=="new") {
    $sql="INSERT into racks (locationid, usize, depth, comments, model, label, revnums, locareaid) ".
     " VALUES ('".$_POST['locationid']."','".$_POST['usize']."','".$_POST['depth']."','".$_POST['comments']."','".$_POST['model']."','".$_POST['label']."','".$_POST['revnums']."','".$_POST['locareaid']."')";
    db_exec($dbh,$sql,0,0,$lastid);
    $lastid=$dbh->lastInsertId();

    // 新机柜数据
    $new_rack_info = array();
    foreach ($formvars_rack as $k) {
        $new_rack_info[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : '';
    }
    $new_log_data = array(
        'rack_info' => $new_rack_info
    );

    // 日志：新增机柜
    addOperateLog(
        'rack',
        'add',
        'Created new rack ID %s',
        array($lastid),
        'rack',
        $lastid,
        null,
        $new_log_data,
        1,
        ''
    );

    print "<br><b>".t("Added Rack")." <a href='$scriptname?action=$action&amp;id=$lastid'>$lastid</a></b><br>";
    echo "<script>window.location='$scriptname?action=$action&id=$lastid'</script> ";
    $id=$lastid;
    exit;
  }
  // -------------------------- 更新机柜 --------------------------
  else {
    $myid = intval($id);

    // 旧数据
    $sth_old = $dbh->query("SELECT * FROM racks WHERE id=$myid");
    $old_data_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
    $old_rack_info = array();
    foreach ($formvars_rack as $k) {
        $old_rack_info[$k] = isset($old_data_raw[$k]) ? $old_data_raw[$k] : '';
    }

    // 执行更新
    $sql="UPDATE racks set ".
      " locationid='".$_POST['locationid']."', ".
      " locareaid='".$_POST['locareaid']."', ".
      " usize='".$_POST['usize']."', ".
      " revnums='".$_POST['revnums']."', ".
      " depth='".$_POST['depth']."', ".
      " model='".($_POST['model'])."', ".
      " comments='".($_POST['comments'])."' , ".
      " label='".($_POST['label'])."' ".
      " WHERE id=$myid";
    db_exec($dbh,$sql);

    // 新数据
    $sth_new = $dbh->query("SELECT * FROM racks WHERE id=$myid");
    $new_data_raw = $sth_new->fetch(PDO::FETCH_ASSOC);
    $new_rack_info = array();
    foreach ($formvars_rack as $k) {
        $new_rack_info[$k] = isset($new_data_raw[$k]) ? $new_data_raw[$k] : '';
    }

    // 对比差异（同edititem）
    $diff_old = array();
    $diff_new = array();
    foreach ($old_rack_info as $key => $old_val) {
        $new_val = isset($new_rack_info[$key]) ? $new_rack_info[$key] : '';
        if ((string)$old_val !== (string)$new_val) {
            $diff_old[$key] = $old_val;
            $diff_new[$key] = $new_val;
        }
    }

    $old_diff = array();
    $new_diff = array();
    if (!empty($diff_old)) {
        $old_diff['rack_info'] = $diff_old;
        $new_diff['rack_info'] = $diff_new;
    }

    // 日志：更新机柜
    addOperateLog(
        'rack',
        'update',
        'Updated rack ID %s',
        array($myid),
        'rack',
        $myid,
        $old_diff,
        $new_diff,
        1,
        ''
    );
  }

  // 更新机柜内设备位置
  $sql="UPDATE items set locationid='".$_POST['locationid']."', locareaid='".$_POST['locareaid']."' WHERE items.rackid=$id";
  db_exec($dbh,$sql);
  te("Location of items in this rack was updated to match rack location");
}

// -------------------------- 页面展示 --------------------------
if (!isset($_REQUEST['id'])) {echo "ERROR:ID not defined";exit;}
$id=$_REQUEST['id'];

$sql="SELECT count(items.id) AS population, racks.* FROM racks LEFT JOIN items ON items.rackid=racks.id WHERE racks.id='$id' GROUP BY racks.id";
// 真实占用U位：同1U无论前/中/后，只算1次
$real_used = [];
$sth_items = $dbh->prepare("SELECT rackposition, usize FROM items WHERE rackid = ? AND rackposition > 0 AND usize > 0 AND rackmountable = 1");
$sth_items->execute([$id]);
$devices = $sth_items->fetchAll(PDO::FETCH_ASSOC);

foreach ($devices as $dev) {
    $start = intval($dev['rackposition']);
    $size  = intval($dev['usize']);
    for ($i = 0; $i < $size; $i++) {
        $u = $start + $i;
        $real_used[$u] = 1;
    }
}
$real_occupation = count($real_used);

$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);

if (($id !="new") && (count($r)<2)) {echo t("ERROR: non-existent ID")."<br>($sql)";exit;}

echo "\n<form id='mainform' method=post action='$scriptname?action=$action&amp;id=$id' enctype='multipart/form-data' name='addfrm'>\n";
?>

<?php 
if ($id=="new")
  echo "\n<h1>".t("Add Rack")."</h1>\n";
else
  echo "\n<h1>".t("Edit Rack")."  ($id)"."</h1>\n";
?>

<!-- 错误提示 -->
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px;display:none;'>
  <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>
  <h4><?php te("There are <strong>error</strong>s in your form submission, please see below for details.");?></h4>
  <ol>
    <li><label for="usize" class="error"><?php te("Rack height is missing");?></label></li>
    <li><label for="depth" class="error"><?php te("Rack depth is missing");?></label></li>
    <li><label for="label" class="error"><?php te("Rack label is missing");?></label></li>
    <li><label for="locationid" class="error"><?php te("Rack location is missing");?></label></li>
  </ol>
</div>

<table style='width:100%' border=0>
<tr>
<td class="tdtop" width=20%>
  <table class="tbl2" style='width:300px;'>
  <tr><td colspan=2><h3><?php te("Rack Properties");?></h3></td></tr>
  <tr><td class="tdt"><?php te("ID")?>:</td> <td><input style='display:none' type=text name='id' value='<?php echo $id?>' readonly size=3><?php echo $id?></td></tr>
  <tr><td class="tdt"><?php te("Height (U)")?><sup class='red'>*</sup>:</td>
  <td>
  <select class='mandatory' validate='required:true' name='usize'>
<?php 
  echo "\n<option value=''>".t("Select")."</option>";
  for ($s=48;$s>=6;$s--) {
    if ($s==$r['usize']) $sel="selected"; else $sel="";
    echo "<option $sel value='$s'>".$s."U</option>\n";
  }
?>
  </select>
  </td></tr>
  <tr><td class="tdt"><?php te("Numbering")?>:</td>
  <td>
  <select name='revnums'>
<?php
  if ($r['revnums']==1) { $s0="";$s1="selected"; } else { $s0="selected"; $s1=""; }
  echo "<option $s0 value='0'>".t("1=Bottom")."</option>\n";
  echo "<option $s1 value='1'>".t("1=Top")."</option>\n";
?>
  </select>
  </td></tr>
  <tr><td class="tdt"><?php te("Label");?><sup class='red'>*</sup>:</td> 
      <td><input class='input2 mandatory' validate='required:true' size=20 type=text name='label' value="<?php echo $r['label']?>"></td></tr>
  <tr><td class="tdt"><?php te("Depth");?>(mm)<sup class='red'>*</sup>:</td> 
      <td><input class='input2 mandatory' validate='required:true' size=20 type=text name='depth' value="<?php echo $r['depth']?>"></td></tr>
  <tr><td class="tdt"><?php te("Model");?>:</td> 
      <td><input class='input2' size=20 type=text name='model' value="<?php echo $r['model']?>"></td></tr>
  <tr><td class="tdt"><?php te("Comments");?>:</td> 
      <td><textarea class='tarea1' wrap=soft name=comments><?php echo $r['comments']?></textarea></td></tr>
	<tr><td class="tdt"><?php te("Location");?><sup class='red'>*</sup>:</td> 
	<td>
	  <select id='locationid' name='locationid' validate='required:true' class='mandatory'>
	  <option value=''><?php te("Select");?></option>
	  <?php
	  $locationid=$r['locationid'];
	  foreach ($locations  as $key=>$location ) {
	    $dbid=$location['id'];
	    if (is_numeric($location['floor']))
	            $itype=$location['name'].", ".t("Floor").":".$location['floor'];
	    else
	            $itype=$location['name'];
	    $s="";
	    if (($locationid=="$dbid")) $s=" SELECTED ";
	    echo "    <option $s value='$dbid'>$itype</option>\n";
	  }
	  ?>
	  </select>
	</td>
	</tr>
  <tr><td class="tdt"><?php te("Area");?>:</td>
  <td>
<?php
  if (is_numeric($locationid)) {
    $sql="SELECT id,areaname FROM locareas WHERE locationid=$locationid order by areaname";
    $stha=$dbh->query($sql);
    $locareas=$stha->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $locareas=array();
  }
?>
    <select id='locareaid' name='locareaid'>
      <option value=''><?php te("Select");?></option>
      <?php
      $locareaid=$r['locareaid'];
      foreach ($locareas as $locarea) {
        $dbid=$locarea['id'];
        $name=$locarea['areaname'];
        $s=($locareaid=="$dbid") ? " SELECTED " : "";
        echo "<option $s value='$dbid'>$name</option>\n";
      }
      ?>
    </select>
  </td></tr>
	<?php if ($id != "new") { ?>
	<tr><td class="tdt"><?php te("Item Count");?>:</td> <td><?php echo $r['population']?></td></tr>
	<?php } ?>

	<?php 
	$occupation = $real_occupation; 
	$total_u = (int)$r['usize'];
	$percent = 0;
	$u_text = '';
	
	if ($id != "new" && $total_u > 0) {
	    $width = (int)($occupation / $total_u * 150);
	    $percent = round(($occupation / $total_u) * 100);
	    $u_text = $occupation . 'U / ' . $percent . '%';
	} else {
	    $width = 0;
	    $u_text = '0U / 0%';
	}
	?>
	<?php if ($id != "new") { ?>
	<tr>
	   <td class='tdt'><?php te("Occupation");?>:</td>
	   <td title='<?php echo sprintf(t("%s U occupied"),$occupation)?>'>
	     <div style='width:150px; border:1px solid #888; padding:0; position:relative;'>
	       <div style='background-color:#8ECE03; width:<?php echo $width?>px; height:16px;'></div>
	       <div style='position:absolute; top:0; left:0; width:100%; text-align:center; font-size:11px; color:#000; line-height:16px;'>
	         <?php echo $u_text ?>
	       </div>
	     </div>
	   </td>
	</tr>
	<?php } ?>
	

  </table>
</td>
<td class='smallrack' style='padding-left:10px; border-left:1px dashed #aaa; width:380px; max-width:380px; vertical-align:top;'>
  <?php if ($id!="new") include('viewrack.php'); ?>
</td>
</tr>
<tr>
<td colspan=2>
<button type="submit"><img src="images/save.png" alt='<?php te("Save");?>'> <?php te("Save");?></button>
<?php 
if ($id != "new") {
  echo "\n<button type='button' onclick='javascript:delconfirm2(\"{$r['id']}\",\"$scriptname?action=$action&amp;delid=$id\");'>".
  "<img title='".t("delete")."' src='images/delete.png' border=0>".t("Delete"). "</button>\n";
}
?>
</td>
</tr>
</table>

<input type=hidden name='id' value='<?php echo $id ?>'>
<input type=hidden name='action' value='<?php echo $action ?>'>
</form>
</body>
</html>
