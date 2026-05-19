<SCRIPT LANGUAGE="JavaScript"> 
$(document).ready(function() {
    $('input#itemsfilter').quicksearch('table#itemslisttbl tbody tr');
    $('input#invfilter').quicksearch('table#invlisttbl tbody tr');
    $('input#contrfilter').quicksearch('table#contrlisttbl tbody tr');
    $("#tabs").tabs();
    $("#tabs").show();
});
</SCRIPT>
<?php 
if (!isset($initok)) {echo t("do not run this script directly");exit;}

/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */
$sql="SELECT id,title,type FROM agents";
$sth=db_execute($dbh,$sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $agents[$r['id']]=$r;

// 软件字段定义（和资产formvars同结构）
$soft_formvars = array(
    'invoiceid','slicenseinfo','manufacturerid','stitle','sversion',
    'sinfo','purchdate','licqty','lictype'
);

//delete software
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    
    //文件关联处理
    $f=softid2files($delid,$dbh);
    $fids=array();
    for ($c=0;$c<count($f);$c++) {
        array_push($fids,$f[$c]['id']);
    }
    $sql="DELETE from software2file where softwareid=$delid";
    $sth=db_exec($dbh,$sql);
    for ($c=0;$c<count($fids);$c++) {
        $nlinks=countfileidlinks($fids[$c],$dbh);
        if ($nlinks==0) delfile($fids[$c],$dbh);
    }

    //删除主表与关联表
    $sql="DELETE from software where id=$delid";
    db_exec($dbh,$sql);
    $sql="DELETE from item2soft where softid=$delid";
    db_exec($dbh,$sql);
    $sql="DELETE FROM soft2inv WHERE softid=$delid";
    db_exec($dbh,$sql);
    $sql="DELETE FROM contract2soft WHERE softid=$delid";
    db_exec($dbh,$sql);

    // 删除日志（同资产格式）
    addOperateLog(
        'software',
        'delete',
        'Deleted software ID %s',
        array($delid),
        'software',
        $delid,
        null,
        null,
        1,
        ''
    );

    // 写入actions表
    $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
    $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                   VALUES ($delid, ".time().", 'Deleted software by $user', '', 1, ".time().")";
    db_exec($dbh, $sql_action);

    echo "<script>document.location='$scriptname?action=listsoftware'</script>";
    echo "<a href='$scriptname?action=listsoftware'>".t("Go here")."</a></body></html>"; 
    exit;
}

//删除关联文件
if (isset($_GET['delfid'])) {
    $fileid = intval($_GET['delfid']);
    $itemid = intval($id);
    $sql="DELETE from software2file where softwareid=$id AND fileid=$fileid";
    $sth=db_exec($dbh,$sql);

    addOperateLog(
        'file',
        'delete',
        'Deleted file %s from software %s',
        array($fileid, $id),
        'software',
        $id,
        null,
        null,
        1,
        ''
    );

    $nlinks=countfileidlinks($fileid,$dbh);
    if ($nlinks==0) delfile($fileid,$dbh);

    echo "<script>window.location='$scriptname?action=$action&id=$id'</script> ";
    echo "<br><a href='$scriptname?action=$action&id=$id'>".t("Go here")."</a></body></html>"; 
    exit;
}

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $soft_id = $id;

    //必填项校验
    if ((empty($_POST['stitle']))|| (empty($_POST['sversion']))|| 
        (!strlen($_POST['manufacturerid'])) || (!strlen($_POST['purchdate'])) ) {
        echo "<br><b>".t("Some <span class='mandatory'> mandatory</span> fields are missing").".</b><br>
        <a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }

    //接收POST
    foreach($_POST as $k => $v) {
        if (!is_array($v)) ${$k} = trim($v);
    }

    $pd = ymd2sec($purchdate);
    $mend = ymd2sec($maintend);

    //关联数据
    $softlnk  = isset($_POST['softlnk'])  ? $_POST['softlnk']  : array();
    $invlnk   = isset($_POST['invlnk'])   ? $_POST['invlnk']   : array();
    $contrlnk = isset($_POST['contrlnk']) ? $_POST['contrlnk'] : array();

    // ====================== 新增软件 ======================
    if ($_POST['id'] == "new") {
        $sql="INSERT into software (invoiceid,slicenseinfo,manufacturerid,stitle,sversion,sinfo,purchdate,licqty,lictype)
              VALUES ('$invoiceid','$slicenseinfo','$manufacturerid','$stitle','$sversion','$sinfo','$pd','$licqty','$lictype')";
        db_exec($dbh,$sql);
        $lastid = $dbh->lastInsertId();
        $id = $lastid;

        // 组装日志：基础信息 + 关联关系 合并在一起（完全同资产）
        $new_soft_info = array();
        foreach ($soft_formvars as $k) {
            $new_soft_info[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : '';
        }
        $new_soft_info['purchdate'] = $pd;

        $new_relation = array(
            'softlnk'   => $softlnk,
            'invlnk'    => $invlnk,
            'contrlnk'  => $contrlnk
        );

        $new_log_data = array(
            'soft_info' => $new_soft_info,
            'relation'  => $new_relation
        );

        // 一条日志搞定：新增+关联全部包含
        addOperateLog(
            'software',
            'add',
            'Created new software ID %s',
            array($lastid),
            'software',
            $lastid,
            null,
            $new_log_data,
            1,
            ''
        );

        // actions记录
        $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
        $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                       VALUES ($lastid, ".time().", 'Added software by $user', '', 1, ".time().")";
        db_exec($dbh, $sql_action);
    } 
    // ====================== 修改软件 ======================
    else {
        // 旧数据
        $sth_old = $dbh->query("SELECT * FROM software WHERE id=$id");
        $old_data_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
        $old_soft_info = array();
        foreach ($soft_formvars as $k) {
            $old_soft_info[$k] = isset($old_data_raw[$k]) ? $old_data_raw[$k] : '';
        }

        // 旧关联
        $old_softlnk = [];
        $s = $dbh->query("SELECT itemid FROM item2soft WHERE softid=$id");
        if ($s) $old_softlnk = $s->fetchAll(PDO::FETCH_COLUMN, 0);

        $old_invlnk = [];
        $s = $dbh->query("SELECT invid FROM soft2inv WHERE softid=$id");
        if ($s) $old_invlnk = $s->fetchAll(PDO::FETCH_COLUMN, 0);

        $old_contrlnk = [];
        $s = $dbh->query("SELECT contractid FROM contract2soft WHERE softid=$id");
        if ($s) $old_contrlnk = $s->fetchAll(PDO::FETCH_COLUMN, 0);

        $old_relation = [
            'softlnk'  => $old_softlnk,
            'invlnk'   => $old_invlnk,
            'contrlnk' => $old_contrlnk
        ];

        // 执行更新
        $sql="UPDATE software set invoiceid='$invoiceid', slicenseinfo='$slicenseinfo', 
              manufacturerid='$manufacturerid', stitle='$stitle', sversion='$sversion', 
              sinfo='$sinfo', purchdate='$pd', licqty='$licqty', lictype='$lictype' 
              WHERE id=$id";
        db_exec($dbh,$sql);

        // 新数据
        $sth_new = $dbh->query("SELECT * FROM software WHERE id=$id");
        $new_data_raw = $sth_new->fetch(PDO::FETCH_ASSOC);
        $new_soft_info = array();
        foreach ($soft_formvars as $k) {
            $new_soft_info[$k] = isset($new_data_raw[$k]) ? $new_data_raw[$k] : '';
        }

        $new_relation = [
            'softlnk'  => $softlnk,
            'invlnk'   => $invlnk,
            'contrlnk' => $contrlnk
        ];

		// 对比差异：只记改动字段（同edititem，时间戳统一int）
		$diff_old_soft = [];
		$diff_new_soft = [];
		
		// 时间字段：数据库存整型时间戳
		$time_fields = ['purchdate'];
		
		foreach ($old_soft_info as $key => $old_val) {
		    $new_val = isset($new_soft_info[$key]) ? $new_soft_info[$key] : '';
		
		    // 时间字段强制转整数，统一类型
		    if (in_array($key, $time_fields)) {
		        $old_val = (int)$old_val;
		        $new_val = (int)$new_val;
		    }
		
		    // 只有不一样才记录
		    if ((string)$old_val !== (string)$new_val) {
		        $diff_old_soft[$key] = $old_val;
		        $diff_new_soft[$key] = $new_val;
		    }
		}


        // 关联差异
        $relation_keys = ['softlnk','invlnk','contrlnk'];
        $diff_old_rel = [];
        $diff_new_rel = [];
        foreach ($relation_keys as $k) {
            $old = isset($old_relation[$k]) ? $old_relation[$k] : [];
            $new = isset($new_relation[$k]) ? $new_relation[$k] : [];
            if (json_encode($old) !== json_encode($new)) {
                $diff_old_rel[$k] = $old;
                $diff_new_rel[$k] = $new;
            }
        }

		// 最终日志结构：无修改不显示空数组（同contract、item）
		$old_diff = [];
		$new_diff = [];
		
		// 基础信息有变化才加
		if (!empty($diff_old_soft)) {
		    $old_diff['soft_info'] = $diff_old_soft;
		    $new_diff['soft_info'] = $diff_new_soft;
		}
		
		// 关联有变化才加
		if (!empty($diff_old_rel)) {
		    $old_diff['relation'] = $diff_old_rel;
		    $new_diff['relation'] = $diff_new_rel;
		}

        // 一条日志：修改+关联变更全部包含
        addOperateLog(
            'software',
            'update',
            'Updated software ID %s',
            array($id),
            'software',
            $id,
            $old_diff,
            $new_diff,
            1,
            ''
        );

        // actions记录
        $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
        $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                       VALUES ($id, ".time().", 'Updated software by $user', '', 1, ".time().")";
        db_exec($dbh, $sql_action);
    }

    // ========== 下面是关联更新，不记独立日志，完全同资产 ==========
    // item 关联
    $sql="delete from item2soft where softid=$id";
    db_exec($dbh,$sql);
    foreach ($softlnk as $sid) {
        $sid = intval($sid);
        db_exec($dbh, "INSERT into item2soft (softid,itemid) values ($id,$sid)");
    }

    // invoice 关联
    $sql="DELETE FROM soft2inv WHERE softid=$id";
    db_exec($dbh,$sql);
    foreach ($invlnk as $iid) {
        $iid = intval($iid);
        db_exec($dbh, "INSERT INTO soft2inv (softid,invid) values ($id,$iid)");
    }

    // contract 关联
    $sql="DELETE FROM contract2soft WHERE softid=$id";
    db_exec($dbh,$sql);
    foreach ($contrlnk as $cid) {
        $cid = intval($cid);
        db_exec($dbh, "INSERT INTO contract2soft (softid,contractid) values ($id,$cid)");
    }

    if ($_POST['id'] == "new") {
        echo "<script>window.location='$scriptname?action=$action&id=$id'</script> ";
    }
}

/////////////////////////////
//// display data now
if (!isset($_REQUEST['id'])) {echo t("ERROR:ID not defined");exit;}
$id=$_REQUEST['id'];
$sql="SELECT * FROM software where id='$id'";
$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);
if (($id !="new") && (count($r)<5)) {echo t("ERROR: non-existent ID");exit;}

$manufacturerid=$r['manufacturerid'];
$stitle=$r['stitle'];
$sversion=$r['sversion'];
$purchdate=$r['purchdate'];
$slicensefile=$r['slicensefile'];
$slicenseinfo=$r['slicenseinfo'];
$sinfo=$r['sinfo'];
$invoiceid=$r['invoiceid'];
$licqty=$r['licqty'];
$lictype=$r['lictype'];

echo "\n<form id='mainform' method=post  action='$scriptname?action=$action&amp;id=$id' enctype='multipart/form-data'  name='addfrm'>\n";
?>

<?php 
if ($id=="new")
  echo "\n<h1>".t("Add Software")."</h1>\n";
else
  echo "\n<h1>".t("Edit Software")." ($id)</h1>\n";
?>

<!-- error errcontainer -->
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px;display:none;'>
        <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>
        <h4><?php te("There are <strong>error</strong>s in your form submission, please see below for details.");?></h4>
        <ol>
                <li><label for="manufacturerid" class="error"><?php te("S/W Manufacturer is missing");?></label></li>
                <li><label for="stitle" class="error"><?php te("Software title is missing");?></label></li>
                <li><label for="sversion" class="error"><?php te("Software Version is missing");?></label></li>
                <li><label for="purchdate" class="error"><?php te("Date of purhcase is missing");?></label></li>
        </ol>
</div>

<div id="tabs">
  <ul>
  <li><a href="#tab1"><?php te("Software Data");?></a></li>
  <li><a href="#tab2"><?php te("Item Associations");?></a></li>
  <li><a href="#tab3"><?php te("Invoice Associations");?></a></li>
  <li><a href="#tab4"><?php te("Contract Associations");?></a></li>
  <li><a href="#tab5"><?php te("Upload Files");?></a></li>
  </ul>

<div id="tab1" class="tab_content">
  <?php 
  echo "<table class=tbl1 border=0>";
  $sql="SELECT * FROM itemtypes";
  $sth=$dbh->query($sql);
  $fixtypes=$sth->fetchAll(PDO::FETCH_ASSOC);
  for ($i=0;$i<count($fixtypes);$i++) {
    $typeid2name[$fixtypes[$i]['id']]=$fixtypes[$i]['typedesc'];
  }
  $qtsel="<select name='licqty'>\n";
  for ($i=1;$i<=400;$i++) {
    if ($licqty==$i) $s="SELECTED";
    else $s="";
    $qtsel.= "<option $s value='$i'>$i</option>\n";
  }
  $qtsel.="</select>\n";

  $f=softid2files($id,$dbh);
  $flnk=showfiles($f);
  $f2=softid2invoicefiles($id,$dbh);
  $flnk.=showfiles($f2,'fileslist2',0,t('File of related invoice'));
  $f3=softid2contractfiles($id,$dbh);
  $flnk.=showfiles($f3,'fileslist3',0,t('File of related contract'));
  $d=strlen($purchdate)?date($dateparam,$purchdate):"";
  ?>
  <tr>
  <td class="tdtop">
      <table class="tbl2" style='width:300px;'>
      <tr><td colspan=2><h3><?php te("Software Properties");?></h3></td></tr>
      <tr><td class="tdt">
      <?php te("ID");?>:</td> <td><input  class='input2' type=text name='id' value='<?php echo $id?>' readonly size=3></td></tr>
      <tr><td class="tdt">
     <?php   if (is_numeric($manufacturerid))
       echo "<a title='".t("Edit Manufacturer (agent)")."' href='$scriptname?action=editagent&amp;id=$manufacturerid'><img src='images/edit.png'></a> "; ?>
      
      <?php te('Manufacturer');?><sup class='red'>*</sup>:</td> <td title=<?php te('Add more manufacturers at the "Agents" menu');?>>
	   <select validate='required:true' class='mandatory' name='manufacturerid'>
	   <option value=''><?php te('Select');?></option>
	  <?php 
	    foreach ($agents as $a) {
	      if (!($a['type']&2)) continue;
	      $dbid=$a['id'];
	      $atype=$a['title']; $s="";
	      if (isset($manufacturerid) && $manufacturerid==$a['id']) $s=" SELECTED ";
	      echo "<option $s value='$dbid' title='$dbid'>$atype</option>\n";
	    }
	    echo "</select>\n";
	  ?>
      </td></tr>
      <tr><td class="tdt"><?php te("Title");?><sup class='red'>*</sup>:</td> <td><input  validate='required:true' class='input2 mandatory' size=20 type=text name='stitle' value="<?php echo $stitle?>"></td></tr>
      <tr><td class="tdt"><?php te("Version");?><sup class='red'>*</sup>:</td> <td><input  validate='required:true' class='input2 mandatory' size=20 type=text name='sversion' value="<?php echo $sversion?>"></td></tr>
      <tr>
	  <td class="tdt"><?php te("Purchase Date");?><sup class='red'>*</sup>:</td> <td><input  validate='required:true' class='mandatory dateinp' size=10 title='<?php echo $datetitle?>' type=text name='purchdate' id='purchdate' value='<?php echo $d?>'>
	  </td>
      </tr>
      <tr><td class="tdt"><?php te("Quantity");?>:</td> <td><?php echo $qtsel?> </td></tr>
  <?php 
  $t0="";$t1="";$t2="";
  if (empty ($lictype) || $lictype=="0") {$t0="checked";$t1="";$t2="";}
  if ($lictype=="1") {$t1="checked";$t0="";$t2="";}
  if ($lictype=="2") {$t2="checked";$t0="";$t1="";}
  ?>
    <tr>
    <td class='tdt'><?php te('License Per');?>:</td>
    <td>
    <input style='width:10%' type=radio <?php echo $t0?> name='lictype' value='0'><?php te('Box');?>
    <input style='width:10%' type=radio <?php echo $t1?> name='lictype' value='1'>CPU
    <input style='width:10%' type=radio <?php echo $t2?> name='lictype' value='2'><?php te('Core');?>
    </td>
    </tr>
      <tr><td class="tdt"><?php te("Licencing Info");?>:</td> <td colspan=2>
	      <textarea name='slicenseinfo' class='tarea2' wrap='soft'><?php echo $slicenseinfo?></textarea></td></tr>
      <tr><td class="tdt"><?php te("Other Info");?>:</td> <td colspan=2> <textarea name='sinfo' class='tarea2' wrap='soft'><?php echo $sinfo?></textarea> </td></tr>
      </table>
  </td>
  <td rowspan=1 class="tdtop">
    <h3><?php te("Associations Overview");?></h3>
    <div style='text-align:center'>
      <span class="tita" onclick='showid("items");'><?php te("Items");?></span> |
      <span class="tita" onclick='showid("invoices1");'><?php te("Invoices");?></span> |
      <span class="tita" onclick='showid("contracts");'><?php te("Contracts");?></span>
    </div>
    <div class="scrltblcontainer4" >
    <div  id='items' class='relatedlist'><?php te('ITEMS');?></div>
    <?php 
    if (is_numeric($id)) {
      $sql="SELECT items.id, agents.title || ' ' || items.model || ' [' || itemtypes.typedesc || ', ID:' || items.id || ']' as txt 
           FROM agents,items,itemtypes,item2soft WHERE 
           agents.id=items.manufacturerid AND items.itemtypeid=itemtypes.id AND 
           item2soft.itemid=items.id AND item2soft.softid=$id";
      $sthi=db_execute($dbh,$sql);
      $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
      $nitems=count($ri);
      $institems="";
      for ($i=0;$i<$nitems;$i++) {
        $x=($i+1).": ".$ri[$i]['txt'];
        if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
        $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
                    <a href='$scriptname?action=edititem&amp;id={$ri[$i]['id']}'>$x</a></div>\n";
      }
      echo $institems;
    }
    ?>
   <div id='invoices1' class='relatedlist'><?php te('INVOICES');?></div>
    <?php 
    if (is_numeric($id)) {
      $sql="SELECT invoices.id, invoices.number, invoices.date FROM invoices,soft2inv 
           WHERE soft2inv.invid=invoices.id AND soft2inv.softid=$id";
      $sthi=db_execute($dbh,$sql);
      $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
      $nitems=count($ri);
      $institems="";
      for ($i=0;$i<$nitems;$i++) {
        $d=strlen($ri[$i]['date'])?date($dateparam,$ri[$i]['date']):"";
        $x=($i+1).":  ({$ri[$i]['number']}) - $d [ID:{$ri[$i]['id']}]";
        if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
        $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
                    <a href='$scriptname?action=editinvoice&amp;id={$ri[$i]['id']}'>$x</a></div>\n";
      }
      echo $institems;
    }
    ?>
   <div id='contracts' class='relatedlist'><?php te('CONTRACTS');?></div>
    <?php 
    if (is_numeric($id)) {
      $sql="SELECT contracts.id, type,title,number,startdate,currentenddate FROM contracts,contract2soft 
           WHERE contract2soft.contractid=contracts.id AND contract2soft.softid=$id";
      $sthi=db_execute($dbh,$sql);
      $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
      $nitems=count($ri);
      $institems="";
      for ($i=0;$i<$nitems;$i++) {
        $d=date($dateparam,$ri[$i]['startdate'])."-".date($dateparam,$ri[$i]['currentenddate']);
        $x=($i+1).":  (".$ri[$i]['title']." ".$ri[$i]['number'].") - $d [ID:{$ri[$i]['id']}]";
        if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
        $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
                    <a href='$scriptname?action=editcontract&amp;id={$ri[$i]['id']}'>$x</a></div>\n";
      }
      echo $institems;
    }
    ?>
  </td>
  <td rowspan=1 class="tdtop">
    <h3><?php te('Tags');?> <span title='<?php te("Changes are saved immediately.<br>Removing tags removes associations not Tags. Use the Tags menu for that.");?>' style='font-weight:normal;font-size:70%'>(<a class="edit-tags" href=""><?php te("Edit Tags");?></a>)</span></h3>
      <?php 
      echo showtags("software",$id);
      ?>
      <script>
        ajaxtagscript="php/tag2software_ajaxedit.php?id=<?php echo $id?>";
        <?php 
        require_once('js/jquery.tag.front.js');
        ?>
      </script>
      <br>
      <div style='clear:both;height:20px;'></div>
      <div style='font-style:italic' id='result'></div>
  </td>
  </tr>
  <tr><td colspan=3 class="tdtop">
    <h3><?php te("Associated Files");?><img onclick='window.location.href=window.location.href;' title='Refresh' src='images/refresh.png'></h3>
    <br>
	<?php echo $flnk?>
  </td>
  </tr>
  </table>
</div>

<div id='tab2' class='tab_content'>
  <table>
  <tr>
    <td colspan=3><h2> <?php te("Installed Into");?><sup>1</sup>
	<input style='color:#909090' id="itemsfilter" name="itemsfilter" class='filter' 
	       value='<?php te('Filter');?>' onclick='this.style.color="#000"; this.value=""' size="20">
	 <span style='font-weight:normal;' class='nres'></span>
    </h2>
    </td></tr>
    <tr><td colspan=3 class='tdc' >
    <?php 
    $sql=" SELECT COALESCE((SELECT itemid from item2soft where softid='$id'  AND itemid=items.id ),0) islinked , 
     items.id,status,manufacturerid,model,itemtypeid,sn || ' '||sn2 ||' ' || sn3 as sn,dnsname,users.username ,label 
     FROM items,itemtypes,users  WHERE items.itemtypeid=itemtypes.id AND itemtypes.hassoftware=1 AND users.id=userid 
     order by islinked desc,itemtypeid,items.id desc, manufacturerid,model, dnsname ";
    $sth=db_execute($dbh,$sql);
    ?>
    <div style='margin-left:auto;margin-right:auto;' class='scrltblcontainer2'>
       <table width='100%' class='sortable'  id='itemslisttbl'>
	 <thead>
	    <tr><th><?php te("Installed");?></th><th style='width:70px'><?php te("ID");?></th><th><?php te("Type");?></th>
                <th><?php te("Manufacturer");?></th><th><?php te("Model");?></th>
	        <th><?php te("Label");?></th><th><?php te("DNS");?></th><th><?php te("User");?></th><th><?php te("S/N");?></th>
	    </tr>
	  </thead>
	  <tbody>
    <?php 
    while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
      if ($ir['islinked']) {
	$cls="class='bld'";
      } else {
	$cls="";
      }
      $x=attrofstatus((int)$ir['status'],$dbh);
      $attr=$x[0];
      $statustxt=$x[1];
      echo "\n <tr><td><input name='softlnk[]' value='".$ir['id']."' ";
      if ($ir['islinked']) echo " checked ";
      echo  " type='checkbox' /></td>".
       "<td nowrap $cls style='white-space: nowrap;'><span $attr>&nbsp;</span>
       <a title='".sprintf(t("Edit item %s in new window"),$ir['id'])."' 
       target=_blank href='$scriptname?action=edititem&id=".$ir['id']."'><div class='editid'>".
       $ir['id'].
       "</div></a></td>";
       echo "<td $cls>".$typeid2name[$ir['itemtypeid']].
       "<td $cls>".$agents[$ir['manufacturerid']]['title']. "&nbsp;</td>".
       "<td $cls>".$ir['model'].  "&nbsp;</td>".
       "<td $cls>".$ir['label']."&nbsp;</td>".
       "<td $cls>".$ir['dnsname']."&nbsp;</td>".
       "<td $cls>".$ir['username']."&nbsp;</td>".
       "<td $cls>".$ir['sn']."&nbsp;</td></tr>\n";
    }
    echo "\n</tbody></table>\n";
    echo "</div>\n";
    ?>
    <sup>1</sup><?php te("Select systems where this software is currently installed. Only items with 'software support' in their item type are shown.");?>
    </td>
  </tr>
  </table>
</div>

<div id='tab3' class='tab_content'>
  <h2>
      <input style='color:#909090' id="invfilter" name="invfilter" class='filter' 
             value='<?php te('Filter');?>' onclick='this.style.color="#000"; this.value=""' size="20">
	 <span style='font-weight:normal;' class='nres'></span>
  </h2>
  <?php 
  $sql=" SELECT COALESCE((SELECT invid from soft2inv WHERE softid='$id' AND invid=invoices.id ),0) islinked , 
       invoices.id, number,date,invoices.description as invdesc, agents.title AS agtitle  
       FROM invoices,agents WHERE agents.id=invoices.vendorid 
       ORDER BY islinked desc,date,agtitle";
  $sth=db_execute($dbh,$sql);
  ?>
  <div style='margin-left:auto;margin-right:auto;' class='scrltblcontainer2'>
     <table width='100%' class='tbl2 brdr sortable'  id='invlisttbl'>
       <thead>
          <tr><th width='5%'><?php te("Associated");?></th><th><?php te("ID");?></th><th><?php te("Vendor");?></th>
              <th><?php te("Number");?></th><th><?php te("Title");?></th><th><?php te("Date");?></th>
          </tr>
        </thead>
        <tbody>
  <?php 
  while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
    if ($ir['islinked']) {
      $cls="class='bld'";
    } else {
      $cls="";
    }
    echo "<tr><td><input name='invlnk[]' value='".$ir['id']."' ";
    if ($ir['islinked']) echo " checked ";
    echo  " type='checkbox' /></td>".
     "<td $cls><a title='".sprintf(t("Edit Invoice %s in new window"),$ir['id'])."' 
     target=_blank href='$scriptname?action=editinvoice&amp;id=".$ir['id']."'><div class='editid'>";
    echo $ir['id'];
    echo "</div></a></td>".
     "<td $cls>".$ir['agtitle'].  "&nbsp;</td>".
     "<td $cls>".$ir['number'].  "&nbsp;</td>".
     "<td $cls>".$ir['invdesc'].  "&nbsp;</td>".
     "<td $cls>". date("Y-m-d",$ir['date'])."&nbsp;</td></tr>\n";
  }
  ?>
  </tbody>
  </table>
  </div>
</div>

<div id='tab4' class='tab_content'>
  <h2><input style='color:#909090' id="contrfilter" name="contrfilter" class='filter' 
             value=<?php te('Filter');?> onclick='this.style.color="#000"; this.value=""' size="20">
	 <span style='font-weight:normal;' class='nres'></span>
  </h2>
  <?php 
  $sql=" SELECT COALESCE((SELECT contractid FROM contract2soft WHERE softid='$id' AND contractid=contracts.id ),0) islinked , 
       contracts.id, contracts.title AS ctitle, agents.title AS agtitle  
       FROM contracts,agents WHERE agents.id=contracts.contractorid 
       ORDER BY islinked desc,contractorid,ctitle";
  $sth=db_execute($dbh,$sql);
  ?>
  <div style='margin-left:auto;margin-right:auto;' class='scrltblcontainer2'>
     <table width='100%' class='tbl2 brdr sortable'  id='contrlisttbl'>
       <thead>
          <tr><th width='5%'><?php te("Associated");?></th><th><?php te("ID");?></th><th><?php te("Contractor");?></th><th><?php te("Title");?></th>
          </tr>
        </thead>
        <tbody>
  <?php 
  while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
    if ($ir['islinked']) {
      $cls="class='bld'";
    } else {
      $cls="";
    }
    echo "<tr><td><input name='contrlnk[]' value='".$ir['id']."' ";
    if ($ir['islinked']) echo " checked ";
    echo  " type='checkbox' /></td>".
     "<td $cls><a title='".sprintf(t("Edit contract %s in new window"),$ir['id'])."' 
     target=_blank href='$scriptname?action=editcontract&amp;id=".$ir['id']."'><div class='editid'>";
    echo $ir['id'];
    echo "</div></a></td>".
     "<td $cls>".$ir['agtitle'].  "&nbsp;</td>".
     "<td $cls>".$ir['ctitle']."&nbsp;</td></tr>\n";
  }
  ?>
  </tbody>
  </table>
  </div>
</div>

<div id='tab5' class='tab_content'>
      <table class="tbl2" width='100%'>
      <tr><td colspan=2><h2><?php te('Upload a File');?></h2></td></tr>
      <tr><td class="tdc">
      <iframe class="upload_frame" name="upload_frame" 
	    src="php/uploadframe.php?id=<?php echo $id?>&amp;assoctable=software2file&amp;colname=softwareid"  
	    frameborder="0" allowtransparency="true"></iframe>
      </td>
      </tr>
      </table>
</div>
</div>

<table>
<tr><td colspan=2><button type="submit"><img src="images/save.png" alt="Save"> <?php te("Save");?></button></td>
<?php
if ($id != "new") {
	echo "\n<td><button type='button' onclick='javascript:delconfirm2(\"{$r['id']}\",\"$scriptname?action=$action&amp;delid={$r['id']}\");'>
     	<img title='delete' src='images/delete.png' border=0> ".t("Delete")."</button></td>\n</tr>\n";
}
?>
</table>
<input type=hidden name='action' value='<?php echo $action?>'>
<input type=hidden name='id' value='<?php echo $id?>'>
</form>
</body>
</html>
