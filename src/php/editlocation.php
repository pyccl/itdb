<script>
  function ajaxify(response, status, xhr, form){
    //alert('ajaxifying');
     $('#areafrm').ajaxForm({
	'success': ajaxify,
        target: '#locareas'
     });
  }
  $(document).ready(function() {
    ajaxify(null,null,null,null);
  });
</script>
<?php 
if (!isset($initok)) {echo t("do not run this script directly");exit;}
/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */
// 日志字段定义
$loc_formvars = array('name','floor','floorplanfn');
$disperr = "";
$name_style = "";
$floor_style = "";

if (isset($_GET['delid'])) {
  $delid=intval($_GET['delid']);

  $sth_old = $dbh->query("SELECT * FROM locations WHERE id=$delid");
  $old_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
  $loc_formvars = array('name','floor','floorplanfn');
  $old_location_info = array();
  foreach ($loc_formvars as $k) {
    $old_location_info[$k] = isset($old_raw[$k]) ? $old_raw[$k] : '';
  }

  // 取区域名称，不是ID，格式同软件日志
  $area_names = array();
  $res = $dbh->query("SELECT areaname FROM locareas WHERE locationid=$delid");
  while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    $area_names[] = $row['areaname'];
  }

  // 格式和软件完全一致
  $old_diff = array(
    "location_info" => $old_location_info,
    "areas" => $area_names
  );

  @unlink($uploaddir . $old_raw['floorplanfn']);
  db_exec($dbh, "DELETE FROM locations WHERE id=$delid");
  db_exec($dbh, "UPDATE items SET locationid=0 WHERE locationid=$delid");
  db_exec($dbh, "DELETE FROM locareas WHERE locationid=$delid");

  addOperateLog(
    'location',
    'delete',
    'Deleted location ID %s',
    array($delid),
    'location',
    $delid,
    $old_diff,
    null,
    1,
    ''
  );

  $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
  db_exec($dbh, "INSERT INTO actions(itemid,actiondate,description,invoiceinfo,isauto,entrydate) VALUES($delid, ".time().", 'Deleted location by $user', '', 1, ".time().")");

  echo "<script>document.location='$scriptname?action=listlocations'</script>";
  exit;
}

if (isset($_POST['id'])) { //save
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $floor = trim($_POST['floor']);

    $err_list = array();
    if ($name == '') {
        $err_list[] = t("Location name is missing");
        $name_style = 'style="border:1px solid #ed2633 !important;background:#fff3f3 !important;"';
    }
    if ($floor == '') {
        $err_list[] = t("Floor is missing");
        $floor_style = 'style="border:1px solid #ed2633 !important;background:#fff3f3 !important;"';
    }

    // 顶部红色错误提示
    if (!empty($err_list)) {
        $err_html = "";
        foreach ($err_list as $v) {
            $err_html .= "<li>$v</li>";
        }
        $disperr = "
<div class='errcontainer ui-state-error ui-corner-all' style='padding:0 .7em;width:700px;margin-bottom:3px;display:block;'>
    <p><span class='ui-icon ui-icon-alert' style='float:left;margin-right:.3em;'></span>
    <h4>".t("There are <strong>error</strong>s in your form submission, please see below for details.")."</h4>
    <ol>$err_html</ol>
</div>";
    } else {
        // 重复检测
        $duplicate_check_sql = "SELECT id FROM locations WHERE name='".addslashes($name)."'";
        if ($id != "new") {
            $duplicate_check_sql .= " AND id!='$id'";
        }
        $dup_res = db_execute($dbh, $duplicate_check_sql);
        if ($dup_res->fetch()) {
            echo "<br><b>".sprintf(t("Duplicate location name '%s'"), htmlspecialchars($name))."</b><br>";
            echo "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
            exit;
        }

        if ($_POST['id'] == "new") {
            $filefn = '';
            if (strlen($_FILES['file']['name'])>2) {
                $path_parts = pathinfo($_FILES['file']['name']);
                $fileext = $path_parts['extension'];
                $unique = substr(uniqid(), -4,4);
                $filefn = strtolower("floorplan-".validfn($name)."-$unique.$fileext");
                $uploadfile = $uploaddir.$filefn;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
                    // ok
                } else {
                    $filefn = '';
                }
            }

            $sql = "INSERT INTO locations (name,floor,floorplanfn) VALUES ('$name','$floor','$filefn')";
            db_exec($dbh,$sql);
            $lastid = $dbh->lastInsertId();
            $id = $lastid;

            // 新增日志
            $new_data = array(
                'name' => $name,
                'floor' => $floor,
                'floorplanfn' => $filefn
            );
            addOperateLog(
                'location',
                'add',
                'Created new location ID %s',
                array($lastid),
                'location',
                $lastid,
                null,
                $new_data,
                1,
                ''
            );
            $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
            $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                           VALUES ($lastid, ".time().", 'Added location by $user', '', 1, ".time().")";
            db_exec($dbh, $sql_action);

            echo "<script>window.location='$scriptname?action=$action&id=$lastid'</script>";
            exit;
        } else {
            // 修改：先取旧数据
            $sth_old = $dbh->query("SELECT * FROM locations WHERE id=$id");
            $old_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
            $old_loc = array();
            foreach ($loc_formvars as $k) {
                $old_loc[$k] = isset($old_raw[$k]) ? $old_raw[$k] : '';
            }

            // 上传新图
            $filefn = $old_loc['floorplanfn'];
            if (strlen($_FILES['file']['name'])>2) {
                $path_parts = pathinfo($_FILES['file']['name']);
                $fileext = $path_parts['extension'];
                $unique = substr(uniqid(), -4,4);
                $filefn = strtolower("floorplan-".validfn($name)."-$unique.$fileext");
                $uploadfile = $uploaddir.$filefn;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
                    @unlink($uploaddir.$old_loc['floorplanfn']);
                } else {
                    $filefn = $old_loc['floorplanfn'];
                }
            }

            // 更新
            $sql = "UPDATE locations SET name='$name', floor='$floor', floorplanfn='$filefn' WHERE id=$id";
            db_exec($dbh,$sql);

            // 新数据
            $sth_new = $dbh->query("SELECT * FROM locations WHERE id=$id");
            $new_raw = $sth_new->fetch(PDO::FETCH_ASSOC);
            $new_loc = array();
            foreach ($loc_formvars as $k) {
                $new_loc[$k] = isset($new_raw[$k]) ? $new_raw[$k] : '';
            }

            // 差异日志
            $diff_old = array();
            $diff_new = array();
            foreach ($old_loc as $k => $ov) {
                $nv = isset($new_loc[$k]) ? $new_loc[$k] : '';
                if ((string)$ov !== (string)$nv) {
                    $diff_old[$k] = $ov;
                    $diff_new[$k] = $nv;
                }
            }
            if (!empty($diff_old)) {
                addOperateLog(
                    'location',
                    'update',
                    'Updated location ID %s',
                    array($id),
                    'location',
                    $id,
                    $diff_old,
                    $diff_new,
                    1,
                    ''
                );
                $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
                $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                               VALUES ($id, ".time().", 'Updated location by $user', '', 1, ".time().")";
                db_exec($dbh, $sql_action);
            }

            echo "<script>window.location='$scriptname?action=$action&id=$id'</script>";
            exit;
        }
    }
}//save

/////////////////////////////
//// display data now
if (!isset($_REQUEST['id'])) {echo t("ERROR:ID not defined");exit;}
$id=$_REQUEST['id'];
$sql="SELECT * FROM locations where id='$id'";
$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);
if (($id!="new") && count($r)<3) {echo t("ERROR: non-existent ID")."<br>($sql)";exit;}

echo "\n<form method=post action='$scriptname?action=$action&amp;id=$id' enctype='multipart/form-data' name='addfrm'>\n";

if ($id=="new")
  echo "\n<h1>".t("Add Location")."</h1>\n";
else
  echo "\n<h1>".t("Edit Location")."</h1>\n";

echo $disperr; // 顶部错误
?>

<table style='width:100%' border=0>
<tr>
<td class="tdtop" style='width:35em;'>
    <table class="tbl2" style='width:300px;'>
    <tr><td colspan=2><h3><?php te("Location Properties");?></h3></td></tr>
    <tr><td class="tdt"><?php te("ID");?>:</td>
        <td><input class='input2' type=text name='id' value='<?php echo $id?>' readonly size=3></td></tr>

    <tr>
        <td class="tdt"><?php te("Building Name");?><sup class='red'>*</sup>:</td>
        <td><input class='input2 mandatory' <?php echo $name_style?> size=20 type=text name='name' value="<?php echo $r['name']?>"></td>
    </tr>
    <tr>
        <td class="tdt"><?php te("Floor");?><sup class='red'>*</sup>:</td>
        <td><input class='input2 mandatory' <?php echo $floor_style?> size=20 type=text name='floor' value="<?php echo $r['floor']?>"></td>
    </tr>

    <?php if ($id!="new") { ?>
    <tr>
        <td class="tdt"><?php te("Filename");?>:</td>
        <td><a target=_blank href="<?php echo $uploaddirwww.$r['floorplanfn']; ?>"><?php echo $r['floorplanfn']?></a></td>
    </tr>
    <tr>
        <td class="tdt"><?php te("Associations (items/racks)");?>:</td>
        <td><b><?php echo countloclinks($id,$dbh);?></b></td>
    </tr>
    <?php } ?>
    </table>

    <table class="tbl2" width='90%'>
    <tr><td colspan=2><h3>
      <?php if ($id=="new") {
          echo t("Upload a Floor Plan");
        } else {
          echo t("Replace Floor Plan");
        }?>
    </h3></td></tr>
    <tr> 
      <td class="tdt"><?php te("Floor Plan");?>:</td>
      <td><input name="file" id="file" size="25" type="file"></td>
    </tr>
    </table>

    <h3><?php te("Associations Overview");?></h3>
    <div style='text-align:center'>
      <span class="tita" onclick='showid("items");'><?php te("Items");?></span>
    </div>
    <div class="scrltblcontainer4" style='height:40ex' >
      <div id='items' class='relatedlist'><?php te("ITEMS");?></div>
      <?php if (is_numeric($id)) {
        $sql="SELECT items.id, agents.title||' '||items.model||' ['||itemtypes.typedesc||', ID:'||items.id||']' as txt
              FROM agents,items,itemtypes
              WHERE agents.id=items.manufacturerid AND items.itemtypeid=itemtypes.id AND locationid=$id";
        $sthi=db_execute($dbh,$sql);
        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
        foreach($ri as $i=>$item) {
          $bcolor = $i%2 ? "#D9E3F6":"#fff";
          echo "<div style='background:$bcolor'><a href='$scriptname?action=edititem&id={$item['id']}'>".($i+1).": {$item['txt']}</a></div>";
        }
      } ?>
    </div>

  <br>
  <button type="submit"><img src="images/save.png"> <?php te("Save");?></button>
  
  <?php if ($id!="new") { ?>
  <button type='button' onclick='delconfirm2("<?=$r['id']?>","<?=$scriptname?>?action=editlocation&delid=<?=$r['id']?>");'>
  <img src="images/delete.png"> <?php te("Delete");?></button>
  <?php } ?>

  <input type=hidden name='action' value='<?php echo $action?>'>
  <input type=hidden name='id' value='<?php echo $id?>'>
  </form>
</td>

<td class="tdtop">
<h3><?php te("Areas: rooms, offices");?></h3>
  <div class='scrltblcontainer4' id='locareas'>
  <?php if ($id!="new") {
       require('php/locareas.php');
    } else {
      echo t("Save new location first to define areas onto it");
    } ?>
  </div>
</td>

<td>
<?php if ($id!="new" && strlen($r['floorplanfn'])) {?>
<img width=600 src='<?php echo $uploaddirwww.$r['floorplanfn']; ?>'>
<?php }?>
</td>

</tr>
</table>
</body>
</html>
