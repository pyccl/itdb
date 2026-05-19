<SCRIPT LANGUAGE="JavaScript"> 
$(document).ready(function() {
  $("#tabs").tabs();
  $("#tabs").show();
  $(function () {
   $('input#itemsfilter').quicksearch('table#itemslisttbl tbody tr');
   $('input#softwarefilter').quicksearch('table#softwarelisttbl tbody tr');
   $('input#contractsfilter').quicksearch('table#contractslisttbl tbody tr');
   $('input#invoicesfilter').quicksearch('table#invoiceslisttbl tbody tr');
   });
});
</SCRIPT>
<?php 
if (!isset($initok)) {echo t("do not run this script directly");exit;}
/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */
$sql="SELECT * FROM filetypes order by typedesc";
$sth=db_execute($dbh,$sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $filetypes[$r['id']]=$r;
//delete file
if (isset($_GET['delid'])) { //if we came from a post (save) the update file 
  $delid=$_GET['delid'];
  if (!is_numeric($delid)) {
    echo t("Non numeric id")." delid=($delid)";
    exit;
  }
  //first handle file associations
  $nlinks=countfileidlinks($delid,$dbh);
  if ($nlinks>0) {
    echo t("<b>File not deleted: Please remove associations first for this file<br></b>");
    echo "<br><a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
    echo "\n</body>\n</html>";
    exit;
  }
  else {
    delfile($delid,$dbh);
    // ========== 删除文件日志（同edititem格式） ==========
    addOperateLog(
        'file',
        'delete',
        'Deleted file ID %s',
        array($delid),
        'file',
        $delid,
        null,
        null,
        1,
        ''
    );
    echo "<script>document.location='$scriptname?action=listfiles'</script>";
    echo "<a href='$scriptname?action=listfiles'>".t("Go here")."</a></body></html>"; 
    exit;
  }
}
if (isset($_POST['id'])) { //if we came from a post (save), update the file 
  $id=$_POST['id'];
  $title=$_POST['title'];
  $type=$_POST['type'];
  $date=ymd2sec($_POST['date']);
  //don't accept empty fields
  if ((empty($_POST['title']))|| ($_POST['type']<1) || (empty($_POST['date'])))  {
    echo "<br><b>".t("Some <span class='mandatory'> mandatory</span> fields are missing").".</b><br>".
         "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
    exit;
  }
  if ($_POST['id']=="new")  {//if we came from a post (save) the add software 
    if (strlen($_FILES['file']['name'])>2) { //insert file
      $path_parts = pathinfo($_FILES['file']["name"]);
      $fileext=$path_parts['extension'];
      $ftypestr=ftype2str($_POST['type'],$dbh);
      $unique=substr(uniqid(),-4,4);
      $filefn=strtolower("$ftypestr-".validfn($title)."-$unique.$fileext");
      $uploadfile = $uploaddir.$filefn;
      $result = '';
      //Move the file from the stored location to the new location
      if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
	  $result = t("Cannot upload the file")." '".$_FILES['file']['name']."'"; 
	  if(!file_exists($uploaddir)) {
	      $result .= t(" : Folder doesn't exist.");
	  } elseif(!is_writable($uploaddir)) {
	      $result .= t(" : Folder not writable.");
	  } elseif(!is_writable($uploadfile)) {
	      $result .= t(" : File not writable.");
	  }
	  $filefn = '';
	  echo "<br><b>".t("ERROR").": $result</b><br>";
      }
      else {
	  $sql="INSERT into files (title,type,fname,uploader,uploaddate,date)".
	       " VALUES ('$title','$type','$filefn','{$userdata[0]['username']}','".time()."','$date')";
	  db_exec($dbh,$sql);
	  $lastid=$dbh->lastInsertId();
	  // ========== 新增文件日志（同edititem格式，无关联） ==========
	  $new_file_info = array(
	      'title' => $_POST['title'],
	      'type'  => $_POST['type'],
	      'date'  => $_POST['date'],
	      'fname' => $filefn
	  );
	  $new_log_data = array(
	      'file_info' => $new_file_info
	  );
	  addOperateLog(
	      'file',
	      'add',
	      'Created new file ID %s',
	      array($lastid),
	      'file',
	      $lastid,
	      null,
	      $new_log_data,
	      1,
	      ''
	  );
	  print "<br><b>".t("Added File")." <a href='$scriptname?action=$action&amp;id=$lastid'>$lastid</a></b><br>";
	  echo "<script>window.location='$scriptname?action=$action&id=$lastid'</script> "; //go to the new item
	  echo "\n</body></html>";
	  $id=$lastid;
	  exit;
	}
    }//insert file
    else {
      echo "<br><b>".t("No file uploaded").".</b><br>".
	   "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
      exit;
    }
  }//new file
  else {
    // ========== 编辑文件日志（同edititem格式，文件+关联） ==========
    $sth_old = db_execute($dbh,"SELECT * FROM files WHERE id=$id");
    $old_data_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
    $sql="UPDATE files set title='$title', type='$type', uploader='{$userdata[0]['username']}', uploaddate='".time()."', ".
       " date='$date' WHERE id=$id";
    db_exec($dbh,$sql);
    if (strlen($_FILES['file']['name'])>2) { //update file
      $oldfname=$old_data_raw['fname'];
      $path_parts = pathinfo($_FILES['file']["name"]);
      $fileext=$path_parts['extension'];
      $ftypestr=ftype2str($_POST['type'],$dbh);
      $unique=substr(uniqid(),-4,4);
      $filefn=strtolower("$ftypestr-".validfn($title)."-$unique.$fileext");
      $uploadfile = $uploaddir.$filefn;
      $result = '';
      //Move the file from the stored location to the new location
      if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
	  $result = t("Cannot upload the file")." '".$_FILES['file']['name']."'"; 
	  if(!file_exists($uploaddir)) {
	      $result .= t(" : Folder doesn't exist.");
	  } elseif(!is_writable($uploaddir)) {
	      $result .= t(" : Folder not writable.");
	  } elseif(!is_writable($uploadfile)) {
	      $result .= t(" : File not writable.");
	  }
	  $filefn = '';
	  echo "<br><b>".t("ERROR").": $result</b><br>";
      }
      else {
	$sql="UPDATE files set fname='$filefn' WHERE id=$id";
	db_exec($dbh,$sql);
	if (strlen($oldfname))
	  unlink($uploaddir.$oldfname);
      }
    }//update file
    else{
      $filefn = $old_data_raw['fname'];
    }
    $old_file_info = array(
        'title' => $old_data_raw['title'],
        'type'  => $old_data_raw['type'],
        'date'  => intval($old_data_raw['date']),
        'fname' => $old_data_raw['fname']
    );
    $q1 = db_execute($dbh,"SELECT itemid FROM item2file WHERE fileid=$id");
    $old_itlnk = $q1->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $q2 = db_execute($dbh,"SELECT softwareid FROM software2file WHERE fileid=$id");
    $old_softlnk = $q2->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $q3 = db_execute($dbh,"SELECT contractid FROM contract2file WHERE fileid=$id");
    $old_contrlnk = $q3->fetchAll(PDO::FETCH_COLUMN, 0);

    $q4 = db_execute($dbh,"SELECT invoiceid FROM invoice2file WHERE fileid=$id");
    $old_invlnk = $q4->fetchAll(PDO::FETCH_COLUMN, 0);

    $new_file_info = array(
        'title' => $_POST['title'],
        'type'  => $_POST['type'],
        'date'  => intval(ymd2sec($_POST['date'])),
        'fname' => $filefn
    );
    $new_itlnk    = isset($_POST['itlnk']) ? $_POST['itlnk'] : array();
    $new_softlnk  = isset($_POST['softlnk']) ? $_POST['softlnk'] : array();
    $new_contrlnk = isset($_POST['contrlnk']) ? $_POST['contrlnk'] : array();
    $new_invlnk   = isset($_POST['invlnk']) ? $_POST['invlnk'] : array();
// ===================== 终极修复：绝对不生成空数组 =====================
$has_change = false;
$old_diff = array();
$new_diff = array();
// 文件信息变化才加
foreach ($old_file_info as $k => $old_val) {
    $new_val = $new_file_info[$k];
    if ((string)$old_val !== (string)$new_val) {
        $old_diff['file_info'][$k] = $old_val;
        $new_diff['file_info'][$k] = $new_val;
        $has_change = true;
    }
}
// 关联变化才加
if (json_encode($old_itlnk) !== json_encode($new_itlnk)) {
    $old_diff['relation']['itlnk'] = $old_itlnk;
    $new_diff['relation']['itlnk'] = $new_itlnk;
    $has_change = true;
}
if (json_encode($old_softlnk) !== json_encode($new_softlnk)) {
    $old_diff['relation']['softlnk'] = $old_softlnk;
    $new_diff['relation']['softlnk'] = $new_softlnk;
    $has_change = true;
}
if (json_encode($old_contrlnk) !== json_encode($new_contrlnk)) {
    $old_diff['relation']['contrlnk'] = $old_contrlnk;
    $new_diff['relation']['contrlnk'] = $new_contrlnk;
    $has_change = true;
}
if (json_encode($old_invlnk) !== json_encode($new_invlnk)) {
    $old_diff['relation']['invlnk'] = $old_invlnk;
    $new_diff['relation']['invlnk'] = $new_invlnk;
    $has_change = true;
}
// 只有真正修改才写日志，无修改完全不输出
if ($has_change) {
    addOperateLog(
        'file',
        'update',
        'Updated file ID %s',
        array($id),
        'file',
        $id,
        $old_diff,
        $new_diff,
        1,
        ''
    );
}
  }//not new
  /* Redefine associations here */
  //update item - file links 
  $sql="delete from item2file where fileid=$id";
  db_exec($dbh,$sql);
  $itlnk = isset($_POST['itlnk']) ? $_POST['itlnk'] : array();
  for ($i=0;$i<count($itlnk);$i++) {
    $sql="INSERT into item2file (fileid,itemid) values ($id,".$itlnk[$i].")";
    db_exec($dbh,$sql);
  }
  //update software - file links 
  $sql="delete from software2file where fileid=$id";
  db_exec($dbh,$sql);
  $softlnk = isset($_POST['softlnk']) ? $_POST['softlnk'] : array();
  for ($i=0;$i<count($softlnk);$i++) {
    $sql="INSERT into software2file (fileid,softwareid) values ($id,".$softlnk[$i].")";
    db_exec($dbh,$sql);
  }
  //update contract - file links 
  $sql="delete from contract2file where fileid=$id";
  db_exec($dbh,$sql);
  $contrlnk = isset($_POST['contrlnk']) ? $_POST['contrlnk'] : array();
  for ($i=0;$i<count($contrlnk);$i++) {
    $sql="INSERT into contract2file (fileid,contractid) values ($id,".$contrlnk[$i].")";
    db_exec($dbh,$sql);
  }
  //update invoice - file links 
  $sql="delete from invoice2file where fileid=$id";
  db_exec($dbh,$sql);
  $invlnk = isset($_POST['invlnk']) ? $_POST['invlnk'] : array();
  for ($i=0;$i<count($invlnk);$i++) {
    $sql="INSERT into invoice2file (fileid,invoiceid) values ($id,".$invlnk[$i].")";
    db_exec($dbh,$sql);
  }
}//save pressed
/////////////////////////////
//// display data now
if (!isset($_REQUEST['id'])) {echo t("ERROR:ID not defined");exit;}
$id=$_REQUEST['id'];
$sql="SELECT * FROM files,filetypes where files.type=filetypes.id AND files.id='$id'";
$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);
if ($id!="new") 
  $mytype=$r['typedesc'];
else 
  $mytype="";
if (($id !="new") && (count($r)<3)) {echo t("ERROR: non-existent ID")."<br>($sql)";exit;}
echo "\n<form id='mainform' method=post  action='$scriptname?action=$action&amp;id=$id' enctype='multipart/form-data'  name='addfrm'>\n";
?>
<?php 
if ($id=="new")
  echo "\n<h1>".t("Add File")."</h1>\n";
else
  echo "\n<h1>".t("Edit File")."</h1>\n";
?>
<!-- error errcontainer -->
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px;'>
        <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right:.3em;'></span>
        <h4><?php te("There are <strong>error</strong>s in your form submission, please see below for details.");?></h4>
        <ol>
                <li><label for="type" class="error"><?php te("File Type is missing");?></label></li>
                <li><label for="title" class="error"><?php te("File Title is missing");?></label></li>
                <li><label for="date" class="error"><?php te("Issue Date is missing");?></label></li>
                <li><label for="file" class="error"><?php te("You forgot to select the file?");?></label></li>
        </ol>
</div>
<table style='width:100%' border=0>
<tr>
<td class="tdtop">
    <table class="tbl2" style='width:300px;'>
    <tr><td colspan=2><h3><?php te("File Properties");?></h3></td></tr>
    <tr><td class="tdt"><?php te("ID");?>:</td> 
    <td><input class='input2' type=text name='id' value='<?php echo ($id=="new"||$id==0)?"":$id; ?>' readonly size=3></td></tr>
    <tr><td class="tdt"><?php te("Type");?><sup class='red'>*</sup>:</td><td>
    <select class='mandatory' validate='required:true' name='type'>
<?php 
     if ($mytype=="invoice")
       echo "<option  value='3'>".t("Invoice")."</option>";
     else {
       echo "\n<option  value=''>--- ".t("Select")." ---</option>";
       foreach ($filetypes as $ftype) {
	 $dbid=$ftype['id'];
	 $ftypedesc=ucfirst($ftype['typedesc']);
	 if ($ftype['typedesc']=="invoice") continue;
	 if ($r['type']==$dbid) $s=" SELECTED "; else $s="";
	 echo "\n<option $s value='$dbid'>$ftypedesc</option>";
}
     }
?>
    </select>
    </td></tr>
    <tr><td class="tdt"><?php te("Title");?><sup class='red'>*</sup>:</td> <td><input  class='input2 mandatory' validate='required:true' size=20 type=text name='title' value="<?php echo $r['title']?>"></td></tr>
    <tr><td class="tdt"><?php te("Issue Date");?><sup class='red'>*</sup>:</td> <td><input  class='input2 dateinp mandatory' validate='required:true' id='date' size=20 type=text name='date' 
        value="<?php  if (!empty($r['date'])) echo date($dateparam,$r['date'])?>"></td></tr>
    <?php if($id != "new"): ?>
    <!-- ========== 添加时隐藏以下3行，编辑时显示 ========== -->
    <tr><td class="tdt"><?php te("Filename");?>:</td><td><a target=_blank href="<?php  echo $uploaddirwww.$r['fname'] ?>"><?php echo $r['fname']?></a></td></tr>
    <tr><td title='<?php te("Number of items/software/invoices/etc which reference this file");?>'
            class="tdt"><?php te("Associations");?>:</td> <td><b><?php  echo countfileidlinks($_GET['id'],$dbh);?></b></td></tr>
    <tr><td class="tdt"><?php te("Uploaded by");?>:</td> <td><?php echo $r['uploader']?> <?php te("on");?> <?php  if (!empty($r['uploaddate'])) echo date($dateparam." H:i",$r['uploaddate'])?></td></tr>
    <?php endif; ?>
    </table>
</td>
<td class="tdtop" >
    <div style='float:left;width:70%;'>
      <h3><?php te("Associations Overview");?></h3>
      <div style='text-align:center'>
        <span class="tita" onclick='showid("items");'><?php te("Items");?></span> |
        <span class="tita" onclick='showid("software");'><?php te("Software");?></span> |
        <span class="tita" onclick='showid("invoices1");'><?php te("Invoices");?></span> |
        <span class="tita" onclick='showid("contracts");'><?php te("Contracts");?></span>
      </div>
      <div class="scrltblcontainer4" style='height:13em'>
      <div  id='items' class='relatedlist'><?php te("ITEMS");?></div>
      <?php 
      if (is_numeric($id)) {
        $sql="SELECT items.id, agents.title || ' ' || items.model || ' [ ' || itemtypes.typedesc || ', ID:' || items.id || ' ]' as txt ".
             "FROM agents,items,itemtypes,item2file WHERE ".
             " agents.id=items.manufacturerid AND items.itemtypeid=itemtypes.id AND ".
             " item2file.itemid=items.id AND item2file.fileid='$id'";
        $sthi=db_execute($dbh,$sql);
        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
        $nitems=count($ri);
        $institems="";
        for ($i=0;$i<$nitems;$i++) {
          $x=($i+1).": ".$ri[$i]['txt'];
          if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
          $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
                      <a href='$scriptname?action=edititem&id={$ri[$i]['id']}'>$x</a></div>\n";
        }
        echo $institems;
      }
      ?>
      <div  id='software' class='relatedlist'><?php te("SOFTWARE");?></div>
      <?php 
      if (is_numeric($id)) {
        $sql="SELECT software.id, agents.title || ' ' || software.stitle || ' '|| software.sversion || ' [ID:' || software.id || ']' as txt ".
             "FROM agents,software,software2file WHERE ".
             " agents.id=software.manufacturerid AND software2file.softwareid=software.id AND software2file.fileid='$id'";
        $sthi=db_execute($dbh,$sql);
        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
        $nitems=count($ri);
        $institems="";
        for ($i=0;$i<$nitems;$i++) {
          $x=($i+1).": ".$ri[$i]['txt'];
          if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
          $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
                      <a href='$scriptname?action=editsoftware&id={$ri[$i]['id']}'>$x</a></div>\n";
        }
        echo $institems;
      }
      ?>
      <div id='invoices1' class='relatedlist'><?php te("INVOICES");?></div>
      <?php 
      if (is_numeric($id)) {
        $sql="SELECT invoices.id, invoices.number, invoices.date FROM invoices,invoice2file ".
             " WHERE invoice2file.invoiceid=invoices.id AND invoice2file.fileid=$id";
        $sthi=db_execute($dbh,$sql);
        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
        $nitems=count($ri);
        $institems="";
        for ($i=0;$i<$nitems;$i++) {
          $d=strlen($ri[$i]['date'])?date($dateparam,$ri[$i]['date']):"";
          $x=($i+1).":  ({$ri[$i]['number']}) - $d [ID:{$ri[$i]['id']}]";
          if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
          $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
                      <a href='$scriptname?action=editinvoice&id={$ri[$i]['id']}'>$x</a></div>\n";
        }
        echo $institems;
      }
      ?>
     <div id='contracts' class='relatedlist'><?php te("CONTRACTS");?></div>
      <?php 
      if (is_numeric($id)) {
        $sql="SELECT contracts.id, type,title,number,startdate,currentenddate FROM contracts,contract2file ".
             " WHERE contract2file.contractid=contracts.id AND contract2file.fileid=$id";
        $sthi=db_execute($dbh,$sql);
        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
        $nitems=count($ri);
        $institems="";
        for ($i=0;$i<$nitems;$i++) {
          $d=date($dateparam,$ri[$i]['startdate'])."-".date($dateparam,$ri[$i]['currentenddate']);
          $x=($i+1).":  (".$ri[$i]['title']." ".$ri[$i]['number'].") - $d [ID:{$ri[$i]['id']}]";
          if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
          $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
                      <a href='$scriptname?action=editcontract&id={$ri[$i]['id']}'>$x</a></div>\n";
        }
        echo $institems;
      }
      ?>
    </div>
  </td>
</td>
<td class="tdtop">
    <table class="tbl2" width='90%'>
    <tr><td colspan=2 colspan=2><h3>
      <?php 
      if ($id=="new") {
	$tip="";
	echo t("Upload a File");
  $file_validate_required='true';
      }
      else{
	$tip=t("If you select a new file, it will replace the current one, <br>while keeping its associations.");
	echo t("Replace File");
  $file_validate_required='false';
      }
      ?>
    </h3></td></tr>
    <!-- file upload -->
    <tr> 
      <td class="tdt"><?php te("File");?><sup class='red'>*</sup>:</td> <td><input validate='required:<?php echo $file_validate_required;?>' name="file" id="file" size="25" type="file"></td>
    </tr>
    </table>
<?php echo $tip?>
</td>
<!-- upload -->
</tr>
<tr><td colspan=3>
<h3><?php te("Associations");?></h3>
<div id="tabs">
<ul >
<li><a href="#tab1"><?php te("Items");?></a></li>
<li><a href="#tab2"><?php te("Software");?></a></li>
<li><a href="#tab3"><?php te("Contracts");?></a></li>
<li><a href="#tab4"><?php te("Invoices");?></a></li>
</ul>
<div id="tab1" class="tab_content"><!-- item associations -->
<?php   if (($id!="new") && ($mytype!="invoice")) { ?>
      <table border='0' class=tbl2 style='width:100%;border-bottom:1px solid #cecece'><!-- connect to other items -->
	<tr><td colspan=2 ><?php te("Associate file with the following items");?>:
           <input class='filter' style='color:#909090' id="itemsfilter" 
               name="itemsfilter" value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20"><br>
	</td></tr>
	<tr><td colspan=2>
	  <div class='scrltblcontainer' style='height:30em'>
	  <table width='100%' class='sortable brdr' id='itemslisttbl'>
	  <thead><tr><th><?php te("Rel");?></th><th><?php te("ID");?></th><th><?php te("Type");?></th><th><?php te("Manuf.-Model");?></th>
                     <th><?php te("Label");?></th><th>DNS</th><th><?php te("Users");?></th><th><?php te("S/N");?></th></tr></thead>
	  <tbody>
	  <?php 
	  // 设备专用临时表，不与其他模块混用
	  $dbh->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tt_items (id INTEGER PRIMARY KEY)");
	  $dbh->exec("DELETE FROM tt_items");
	  if (is_numeric($id)) {
	      $dbh->exec("INSERT INTO tt_items SELECT itemid FROM item2file WHERE fileid = $id");
	  }
	  // 已关联
	  $sql="SELECT items.id,manufacturerid,model,itemtypeid,sn||' '||sn2||' '||sn3 as sn,label,dnsname,users.username AS username,
	             typedesc, agents.title AS agtitle
	        FROM items
	        JOIN users ON items.userid = users.id
	        JOIN itemtypes ON items.itemtypeid = itemtypes.id
	        JOIN agents ON items.manufacturerid = agents.id
	        WHERE items.id IN (SELECT id FROM tt_items)
	        ORDER BY itemtypeid, items.id DESC, manufacturerid, model, dnsname";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='itlnk[]' value='".$r['id']."' checked type='checkbox' /></td>".
	     "<td class='bld' style='white-space:nowrap'><a target=_blank href='$scriptname?action=edititem&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td class='bld'>".$r['typedesc']."</td>".
	     "<td class='bld'>".$r['agtitle']."&nbsp;".$r['model']."</td>".
	     "<td class='bld'>".$r['label']."</td>".
	     "<td class='bld'>".$r['dnsname']."</td>".
	     "<td class='bld'>".$r['username']."</td>".
	     "<td class='bld'>".$r['sn']."</td></tr>";
	  }
	  // 未关联
	  $sql="SELECT items.id,manufacturerid,model,itemtypeid,sn||' '||sn2||' '||sn3 as sn,label,dnsname,users.username AS username,
	             typedesc, agents.title AS agtitle
	        FROM items
	        JOIN users ON items.userid = users.id
	        JOIN itemtypes ON items.itemtypeid = itemtypes.id
	        JOIN agents ON items.manufacturerid = agents.id
	        WHERE items.id NOT IN (SELECT id FROM tt_items)
	        ORDER BY itemtypeid, items.id DESC, manufacturerid, model, dnsname";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='itlnk[]' value='".$r['id']."' type='checkbox' /></td>".
	     "<td style='white-space:nowrap'><a target=_blank href='$scriptname?action=edititem&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td>".$r['typedesc']."</td>".
	     "<td>".$r['agtitle']."&nbsp;".$r['model']."</td>".
	     "<td>".$r['label']."</td>".
	     "<td>".$r['dnsname']."</td>".
	     "<td>".$r['username']."</td>".
	     "<td>".$r['sn']."</td></tr>";
	  }
	?>
	</tbody>
	</table>
	</div>
	</td>
	</tr>
      </table>
<?php  } 
  elseif (($id!="new") && ($mytype=="invoice")) { 
     echo t("<br>-Files of type 'invoice' can be associated only with invoices and only using the 'invoice' menu ");
  }
?>
</div><!-- item associations -->

<div id="tab2" class="tab_content"><!-- software associations -->
<?php   if (($id!="new") && ($mytype!="invoice")) { ?>
      <table border='0' class=tbl2 style='width:100%;border-bottom:1px solid #cecece'><!-- connect to software -->
	<tr><td colspan=2 ><?php te("Associate file with the following software");?>:
	<input class='filter' style='color:#909090' id="softwarefilter" 
               name="softwarefilter" value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20"><br>
	</td></tr>
	<tr><td colspan=2>
	  <div class='scrltblcontainer' style='height:35em'>
	  <table width='100%' class='sortable brdr' id='softwarelisttbl'>
	  <thead><tr><th><?php te("Rel");?></th><th><?php te("ID");?></th><th><?php te("Manufacturer");?></th>
                     <th><?php te("Title-Version");?></th></tr></thead>
	  <tbody>
	  <?php 
	  // 软件专用临时表
	  $dbh->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tt_soft (id INTEGER PRIMARY KEY)");
	  $dbh->exec("DELETE FROM tt_soft");
	  if (is_numeric($id)) {
	      $dbh->exec("INSERT INTO tt_soft SELECT softwareid FROM software2file WHERE fileid = $id");
	  }
	  // 已关联
	  $sql="SELECT software.id,manufacturerid,stitle,sversion, agents.title AS agtitle
	        FROM software
	        JOIN agents ON software.manufacturerid = agents.id
	        WHERE software.id IN (SELECT id FROM tt_soft)
	        ORDER BY agtitle, software.id DESC, stitle";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='softlnk[]' value='".$r['id']."' checked type='checkbox' /></td>".
	     "<td class='bld' style='white-space:nowrap'><a target=_blank href='$scriptname?action=editsoftware&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td class='bld'>".$r['agtitle']."</td>".
	     "<td class='bld'>".$r['stitle']."&nbsp;".$r['sversion']."</td></tr>";
	  }
	  // 未关联
	  $sql="SELECT software.id,manufacturerid,stitle,sversion, agents.title AS agtitle
	        FROM software
	        JOIN agents ON software.manufacturerid = agents.id
	        WHERE software.id NOT IN (SELECT id FROM tt_soft)
	        ORDER BY agtitle, software.id DESC, stitle";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='softlnk[]' value='".$r['id']."' type='checkbox' /></td>".
	     "<td style='white-space:nowrap'><a target=_blank href='$scriptname?action=editsoftware&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td>".$r['agtitle']."</td>".
	     "<td>".$r['stitle']."&nbsp;".$r['sversion']."</td></tr>";
	  }
	?>
	</tbody>
	</table>
	</div>
	</td>
	</tr>
      </table>
<?php   }
  elseif (($id!="new") && ($mytype=="invoice")) { 
     echo t("<br>-Files of type 'invoice' can be associated only with invoices and only using the 'invoice' menu ");
  }
?>
</div><!-- tab2 software associations -->

<div id="tab3" class="tab_content"><!-- contracts associations -->
<?php   if (($id!="new") && ($mytype!="invoice")) { ?>
      <table border='0' class=tbl2 style='width:100%;border-bottom:1px solid #cecece'><!-- connect to contracts -->
	<tr><td colspan=2><?php te("Associate file with the following contracts");?>:
             <input class='filter' style='color:#909090' id="contractsfilter" 
               name="contractsfilter" value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20"><br>
	</td></tr>
	<tr><td colspan=2>
	  <div class='scrltblcontainer' style='height:35em'>
	  <table width='100%' class='sortable brdr' id='contractslisttbl'>
	  <thead><tr><th><?php te("Rel");?></th><th><?php te("ID");?></th><th><?php te("Contractor");?></th><th><?php te("Title");?></th>
                     </tr></thead>
	  <tbody>
	  <?php 
	  // 合同专用临时表
	  $dbh->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tt_contr (id INTEGER PRIMARY KEY)");
	  $dbh->exec("DELETE FROM tt_contr");
	  if (is_numeric($id)) {
	      $dbh->exec("INSERT INTO tt_contr SELECT contractid FROM contract2file WHERE fileid = $id");
	  }
	  // 已关联
	  $sql="SELECT contracts.id,contractorid,contracts.title as ctitle, agents.title AS agtitle
	        FROM contracts
	        JOIN agents ON contracts.contractorid = agents.id
	        WHERE contracts.id IN (SELECT id FROM tt_contr)
	        ORDER BY agtitle, contracts.id DESC, ctitle";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='contrlnk[]' value='".$r['id']."' checked type='checkbox' /></td>".
	     "<td class='bld' style='white-space:nowrap'><a target=_blank href='$scriptname?action=editcontract&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td class='bld'>".$r['agtitle']."</td>".
	     "<td class='bld'>".$r['ctitle']."</td></tr>";
	  }
	  // 未关联
	  $sql="SELECT contracts.id,contractorid,contracts.title AS ctitle, agents.title AS agtitle
	        FROM contracts
	        JOIN agents ON contracts.contractorid = agents.id
	        WHERE contracts.id NOT IN (SELECT id FROM tt_contr)
	        ORDER BY agtitle, contracts.id DESC, ctitle";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='contrlnk[]' value='".$r['id']."' type='checkbox' /></td>".
	     "<td style='white-space:nowrap'><a target=_blank href='$scriptname?action=editcontract&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td>".$r['agtitle']."</td>".
	     "<td>".$r['ctitle']."</td></tr>";
	  }
	?>
	</tbody>
	</table>
	</div>
	</td>
	</tr>
      </table>
<?php   }
  elseif (($id!="new") && ($mytype=="invoice")) { 
     echo t("<br>-Files of type 'invoice' can be associated only with invoices and only using the 'invoice' menu ");
  }
?>
</div><!-- tab3 contracts associations -->

<div id="tab4" class="tab_content"><!-- invoice associations -->
<?php   if (($id!="new")) { ?>
      <table border='0' class=tbl2 style='width:100%;border-bottom:1px solid #cecece'><!-- connect to invoices -->
	<tr><td colspan=2 ><?php te("Associate file with the following invoices");?>:
             <input class='filter' style='color:#909090' id="invoicesfilter" 
               name="invoicesfilter" value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20"><br>
	</td></tr>
	<tr><td colspan=2>
	  <div class='scrltblcontainer' style='height:35em'>
	  <table width='100%' class='sortable brdr' id='invoiceslisttbl'>
	  <thead><tr><th><?php te("Rel");?></th><th><?php te("ID");?></th><th><?php te("Order Num");?></th><th><?php te("Vendor");?></th>
                     </tr></thead>
	  <tbody>
	  <?php 
	  // 发票专用临时表
	  $dbh->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tt_inv (id INTEGER PRIMARY KEY)");
	  $dbh->exec("DELETE FROM tt_inv");
	  if (is_numeric($id)) {
	      $dbh->exec("INSERT INTO tt_inv SELECT invoiceid FROM invoice2file WHERE fileid = $id");
	  }
	  // 已关联
	  $sql="SELECT invoices.id,number,agents.title AS vendortitle
	        FROM invoices
	        JOIN agents ON invoices.vendorid = agents.id
	        WHERE invoices.id IN (SELECT id FROM tt_inv)
	        ORDER BY invoices.id DESC";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='invlnk[]' value='".$r['id']."' checked type='checkbox' /></td>".
	     "<td class='bld' style='white-space:nowrap'><a target=_blank href='$scriptname?action=editinvoice&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td class='bld'>".$r['number']."</td>".
	     "<td class='bld'>".$r['vendortitle']."</td></tr>";
	  }
	  // 未关联
	  $sql="SELECT invoices.id,number,agents.title AS vendortitle
	        FROM invoices
	        JOIN agents ON invoices.vendorid = agents.id
	        WHERE invoices.id NOT IN (SELECT id FROM tt_inv)
	        ORDER BY invoices.id DESC";
	  $sth=db_execute($dbh,$sql);
	  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	    echo "\n <tr><td><input name='invlnk[]' value='".$r['id']."' type='checkbox' /></td>".
	     "<td style='white-space:nowrap'><a target=_blank href='$scriptname?action=editinvoice&id=".$r['id']."'><img src='images/edit.png'>".$r['id']."</a></td>".
	     "<td>".$r['number']."</td>".
	     "<td>".$r['vendortitle']."</td></tr>";
	  }
	?>
	</tbody>
	</table>
	</div>
	</td>
	</tr>
      </table>
<?php   } ?>
</div><!-- tab4 invoice associations -->

</div><!-- tab container -->
</td></tr>
<tr><td colspan=1><button type="submit"><img src="images/save.png" alt='<?php te("Save");?>'> <?php te("Save");?></button></td>
<?php 
if ($id != "new") {
	echo "\n<td style='text-align:right'><button type='button' onclick='javascript:delconfirm2(\"{$r['id']}\",\"$scriptname?action=$action&amp;delid=$id\");'>".
     	"<img title='delete' src='images/delete.png' border=0>".t("Delete"). "</button></td>\n</tr>\n";
}
// end of item links
//////////////////////////////////////////////
echo "\n</table>\n";
echo "\n<input type=hidden name='action' value='$action'>";
echo "\n<input type=hidden name='id' value='$id'>";
?>
</form>
</body>
</html>
