<?php
if (!isset($initok)) {echo t("do not run this script directly");exit;}
$sql="SELECT * FROM contracttypes";
$sth=$dbh->query($sql);
$contracttypes=$sth->fetchAll(PDO::FETCH_ASSOC);
$sql="SELECT * FROM contractsubtypes";
$sth=$dbh->query($sql);
$contractsubtypes=$sth->fetchAll(PDO::FETCH_ASSOC);
$sql="SELECT id,title,type FROM agents";
$sth=db_execute($dbh,$sql);
$agents = array();
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $agents[$r['id']]=$r;

/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */

// ========================= 删除合同 =========================
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    $old_contract = $dbh->query("SELECT * FROM contracts WHERE id=$delid")->fetch(PDO::FETCH_ASSOC);
    $old_itlnk    = $dbh->query("SELECT itemid   FROM contract2item  WHERE contractid=$delid")->fetchAll(PDO::FETCH_COLUMN);
    $old_softlnk  = $dbh->query("SELECT softid   FROM contract2soft WHERE contractid=$delid")->fetchAll(PDO::FETCH_COLUMN);
    $old_invlnk   = $dbh->query("SELECT invid    FROM contract2inv  WHERE contractid=$delid")->fetchAll(PDO::FETCH_COLUMN);

    $old_data = array(
        'contract_info' => $old_contract,
        'relation' => array(
            'itlnk'    => $old_itlnk,
            'softlnk'  => $old_softlnk,
            'invlnk'   => $old_invlnk
        )
    );

    addOperateLog(
        'contract',
        'delete',
        'Deleted contract ID %s',
        array($delid),
        'contract',
        $delid,
        $old_data,
        null,
        1,
        ''
    );

    $sql="DELETE FROM contract2item WHERE contractid=$delid";
    db_exec($dbh,$sql);
    $sql="DELETE FROM contract2soft WHERE contractid=$delid";
    db_exec($dbh,$sql);
    $sql="DELETE FROM contract2inv WHERE contractid=$delid";
    db_exec($dbh,$sql);
    $sql="DELETE FROM contract2file WHERE contractid=$delid";
    db_exec($dbh,$sql);
    $sql="DELETE from contracts where id=".$_GET['delid'];
    $sth=db_exec($dbh,$sql);
    echo "<script>document.location='$scriptname?action=listcontracts'</script>";
    echo "<a href='$scriptname?action=listcontracts'>".t("Go here")."</a></body></html>";
    exit;
}

// ========================= 保存合同（新增/编辑） =========================
if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $is_new = ($id === 'new');

    $contract_fields = array(
        'type','subtype','parentid','title','number','description','comments',
        'totalcost','contractorid','startdate','currentenddate','renewals'
    );

    $old_contract = array();
    $old_itlnk = array();
    $old_softlnk = array();
    $old_invlnk = array();

    if (!$is_new) {
        $old_contract = $dbh->query("SELECT * FROM contracts WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
        $old_itlnk   = $dbh->query("SELECT itemid FROM contract2item WHERE contractid=$id")->fetchAll(PDO::FETCH_COLUMN);
        $old_softlnk = $dbh->query("SELECT softid FROM contract2soft WHERE contractid=$id")->fetchAll(PDO::FETCH_COLUMN);
        $old_invlnk  = $dbh->query("SELECT invid FROM contract2inv WHERE contractid=$id")->fetchAll(PDO::FETCH_COLUMN);
    }

    $rrows=count($_POST['ren_enddatebefore']);
    $row = array();
    for ($i=0;$i<$rrows;$i++) {
        $_POST['ren_enddatebefore'] = preg_replace('/[\|#]/', ' ', $_POST['ren_enddatebefore']);
        $_POST['ren_enddateafter'] = preg_replace('/[\|#]/', ' ', $_POST['ren_enddateafter']);
        $_POST['ren_effectivedate'] = preg_replace('/[\|#]/', ' ', $_POST['ren_effectivedate']);
        $_POST['ren_notes'] = preg_replace('/[\|#]/', ' ', $_POST['ren_notes']);
        $_POST['ren_dateentered'] = preg_replace('/[\|#]/', ' ', $_POST['ren_dateentered']);
        $_POST['ren_enteredby'] = preg_replace('/[\|#]/', ' ', $_POST['ren_enteredby']);
        $row[$i]=implode("#",array(
            $_POST['ren_enddatebefore'][$i],
            $_POST['ren_enddateafter'][$i],
            $_POST['ren_effectivedate'][$i],
            $_POST['ren_notes'][$i],
            $_POST['ren_dateentered'][$i],
            $_POST['ren_enteredby'][$i]
        ));
    }
    $renewals=implode("|",$row);

    $title=$_POST['title'];
    $number=$_POST['number'];
    $typex=$_POST['typex'];
    $description=$_POST['description'];
    $comments=$_POST['comments'];
    $parentid=$_POST['parentid'];
    $totalcost=$_POST['totalcost'];
    $contractorid=$_POST['contractorid'];
    $startdate=ymd2sec($_POST['startdate']);
    $currentenddate=ymd2sec($_POST['currentenddate']);

    $missing="";
    if ((!strlen($title))) $missing.= "<br><b>".t("Contract Title is missing").".</b><br>";
    if ((!strlen($number))) $missing.= "<br><b>".t("Contract number is missing").".</b><br>";
    if ((!strlen($typex))) $missing.= "<br><b>".t("Contract Type is missing").".</b><br>";
    if ((!strlen($contractorid))) $missing.= "<br><b>".t("Contractor is missing").".</b><br>";
    if ((!strlen($startdate))) $missing.= "<br><b>".t("Start Date of contract is missing").".</b><br>";
    if ((!strlen($currentenddate)))  $missing.= "<br><b>".t("Current End Date of contract is missing").".</b><br>";
    if ($missing) {
        echo "$missing\n<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }

    $x=explode(":",$typex); $type=$x[0]; $subtype=$x[1];

    if ($_POST['id']=="new") {
        $sql="INSERT into contracts (type , subtype, parentid , title, number, description, comments, totalcost, contractorid , startdate , currentenddate , renewals) VALUES ('$type','$subtype','$parentid','$title','$number', '$description','$comments', '$totalcost','$contractorid','$startdate','$currentenddate','$renewals') ";
        db_exec($dbh,$sql,0,0,$lastid);
        $lastid=$dbh->lastInsertId();
        $id=$lastid;
    } else {
        $sql="UPDATE contracts SET type='$type', subtype='$subtype', title='$title', number='$number', description='$description', comments='$comments', contractorid='$contractorid', startdate='$startdate', currentenddate='$currentenddate', renewals='$renewals', parentid='$parentid', totalcost='$totalcost' WHERE id=$id";
        db_exec($dbh,$sql);
    }

    if (!isset($_POST['itlnk'])) $itlnk=array(); else $itlnk=$_POST['itlnk'];
    $sql="DELETE FROM contract2item WHERE contractid=$id"; db_exec($dbh,$sql);
    for ($i=0;$i<count($itlnk);$i++) {
        $sql="INSERT INTO contract2item (contractid,itemid) values ($id,".$itlnk[$i].")"; db_exec($dbh,$sql);
    }

    if (!isset($_POST['softlnk'])) $softlnk=array(); else $softlnk=$_POST['softlnk'];
    $sql="DELETE FROM contract2soft WHERE contractid=$id"; db_exec($dbh,$sql);
    for ($i=0;$i<count($softlnk);$i++) {
        $sql="INSERT INTO contract2soft (contractid,softid) values ($id,".$softlnk[$i].")"; db_exec($dbh,$sql);
    }

    if (!isset($_POST['invlnk'])) $invlnk=array(); else $invlnk=$_POST['invlnk'];
    $sql="DELETE FROM contract2inv WHERE contractid=$id"; db_exec($dbh,$sql);
    for ($i=0;$i<count($invlnk);$i++) {
        $sql="INSERT INTO contract2inv (contractid,invid) values ($id,".$invlnk[$i].")"; db_exec($dbh,$sql);
    }

    $new_contract = $dbh->query("SELECT * FROM contracts WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    $new_itlnk   = $dbh->query("SELECT itemid FROM contract2item WHERE contractid=$id")->fetchAll(PDO::FETCH_COLUMN);
    $new_softlnk = $dbh->query("SELECT softid FROM contract2soft WHERE contractid=$id")->fetchAll(PDO::FETCH_COLUMN);
    $new_invlnk  = $dbh->query("SELECT invid FROM contract2inv WHERE contractid=$id")->fetchAll(PDO::FETCH_COLUMN);

    if ($is_new) {
        $log_old = null;
        $log_new = array(
            'contract_info' => $new_contract,
            'relation' => array(
                'itlnk'   => $new_itlnk,
                'softlnk' => $new_softlnk,
                'invlnk'  => $new_invlnk
            )
        );
    } else {
        $diff_old = array();
        $diff_new = array();
        foreach ($contract_fields as $k) {
            $ov = isset($old_contract[$k]) ? (string)$old_contract[$k] : '';
            $nv = isset($new_contract[$k]) ? (string)$new_contract[$k] : '';
            if ($ov !== $nv) {
                $diff_old['contract_info'][$k] = $old_contract[$k];
                $diff_new['contract_info'][$k] = $new_contract[$k];
            }
        }
        if (json_encode($old_itlnk) !== json_encode($new_itlnk)) {
            $diff_old['relation']['itlnk'] = $old_itlnk;
            $diff_new['relation']['itlnk'] = $new_itlnk;
        }
        if (json_encode($old_softlnk) !== json_encode($new_softlnk)) {
            $diff_old['relation']['softlnk'] = $old_softlnk;
            $diff_new['relation']['softlnk'] = $new_softlnk;
        }
        if (json_encode($old_invlnk) !== json_encode($new_invlnk)) {
            $diff_old['relation']['invlnk'] = $old_invlnk;
            $diff_new['relation']['invlnk'] = $new_invlnk;
        }
        $log_old = $diff_old;
        $log_new = $diff_new;
    }

    if ($is_new) {
        addOperateLog('contract','add','Created new contract ID %s',array($id),'contract',$id,$log_old,$log_new,1,'');
    } else {
        addOperateLog('contract','update','Updated contract ID %s',array($id),'contract',$id,$log_old,$log_new,1,'');
    }
}

// ========================= Event Logs (No Chinese, No array) =========================
if (isset($_POST['eventid']) && isset($_POST['contractid'])) {
    $contractid   = intval($_POST['contractid']);
    $eventid      = trim($_POST['eventid']);
    $is_event_new = ($eventid === 'new');
    $ev_siblingid   = intval(isset($_POST['ev_siblingid']) ? $_POST['ev_siblingid'] : 0);
    $ev_startdate   = isset($_POST['ev_startdate']) ? $_POST['ev_startdate'] : '';
    $ev_enddate     = isset($_POST['ev_enddate']) ? $_POST['ev_enddate'] : '';
    $ev_description = isset($_POST['ev_description']) ? $_POST['ev_description'] : '';
    $old_event = null;
    if (!$is_event_new) {
        $old_event = $dbh->query("SELECT * FROM contractevents WHERE id=" . intval($eventid))->fetch(PDO::FETCH_ASSOC);
    }
    $new_data = array(
        'event_info' => array(
            'contractid'   => $contractid,
            'siblingid'    => $ev_siblingid,
            'startdate'    => $ev_startdate,
            'enddate'      => $ev_enddate,
            'description'  => $ev_description
        ),
        'relation' => array()
    );
    if ($is_event_new) {
        addOperateLog(
            'contract_event',
            'add',
            'Added contract event ID %s',
            array($eventid),
            'contract',
            $contractid,
            null,
            $new_data,
            1,
            ''
        );
    } else {
        addOperateLog(
            'contract_event',
            'update',
            'Updated contract event ID %s',
            array($eventid),
            'contract',
            $contractid,
            $old_event,
            $new_data,
            1,
            ''
        );
    }
}


// ========================= Event Delete Log =========================
if (isset($_POST['deleventid']) && isset($_POST['contractid'])) {
    $deleventid = intval($_POST['deleventid']);
    $contractid = intval($_POST['contractid']);
    $old_event = $dbh->query("SELECT * FROM contractevents WHERE id=" . $deleventid)->fetch(PDO::FETCH_ASSOC);
    $old_data = array(
        'event_info' => $old_event,
        'relation' => array()
    );
    addOperateLog(
        'contract_event',
        'delete',
        'Deleted contract event ID %s',
        array($deleventid),
        'contract',
        $contractid,
        $old_data,  // 这里必须是数组，绝对不能json_encode
        null,
        1,
        ''
    );
}

?>
<SCRIPT LANGUAGE="JavaScript"> 
function confirm_filled($row)
{
    var filled = 0;
    $row.find('input,select').each(function() {
        if (jQuery(this).val()) filled++;
    });
    if (filled) return confirm('<?php te("Do you really want to remove this row?");?>');
    return true;
};
$(document).ready(function() {
    $('#renewalstable').delegate('.delrow', 'click', function(){
        var answer = confirm('<?php te("Are you sure you want to delete this row?");?>');
        if (answer) {
            $(this).closest('tr').remove();
        }
    });
    $("#caddrow").click(function($e) {
        var row = $('#renewalstable tr:last').clone(false);
        $e.preventDefault();
        row.find("input:text").val("");
        row.find("img").css("display","inline");
        row.find("input[name='enteredby[]']").val("<?php echo $userdata[0]['username']; ?>");
        row.find('input').each(function() {
            $(this).attr("id","");
        });
        row.insertAfter('#renewalstable tr:last');
        $(".dateinp").mask('<?php echo $maskdateparam?>',{placeholder:"_"});
    });
    $("#tabs").tabs();
    $("#tabs").show();
    $('input#itemsfilter').quicksearch('table#itemslisttbl tbody tr');
    $('input#softfilter').quicksearch('table#softlisttbl tbody tr');
    $('input#invfilter').quicksearch('table#invlisttbl tbody tr');
 
	$("#ev_dialog").dialog({ 
	  autoOpen: false, 
	  modal: true, 
	  width: 420,
	  position: { my: "center center", at: "center center", of: window }, // 居中
	  close: function() {
	    // 关闭时清空表单 → 修复残留BUG
	    $('[name=eventid]').val('new');
	    $('[name=ev_siblingid]').val('');
	    $('[name=ev_startdate]').val('');
	    $('[name=ev_enddate]').val('');
	    $('[name=ev_description]').val('');
	  },
	  open: function (event, ui) {
	    var ri=$(this).data('rowid');
	    if (ri!='new')  {
	      var selected=$('#ev_siblingid_'+ri).html();
	      $("[name=ev_siblingid] option[value="+selected+"]").attr('selected', 'selected');
	      $('[name=ev_siblingid]').val($('#ev_siblingid_'+ri).html());
	      $('[name=ev_startdate]').val($('#ev_startdate_'+ri).html());
	      $('[name=ev_enddate]').val($('#ev_enddate_'+ri).html());
	      $('[name=ev_description]').val($('#ev_description_'+ri).html());
	      $('[name=eventid]').val($('#eventid_'+ri).html());
	    } else {
	      // 添加模式：清空
	      $('[name=eventid]').val('new');
	      $('[name=ev_siblingid]').val('');
	      $('[name=ev_startdate]').val('');
	      $('[name=ev_enddate]').val('');
	      $('[name=ev_description]').val('');
	    }
	  }
	});

	$("#ev_deldialog").dialog({ 
	  autoOpen: false, 
	  modal: true, 
	  width: 320,
	  position: { my: "center center", at: "center center", of: window }, // 居中
	  close: function() {
	    $('[name=deleventid]').val('');
	  },
	  open: function (event, ui) {
	    var ri=$(this).data('rowid');
	    if (ri!='new')  {
	      $('[name=deleventid]').val($('#eventid_'+ri).html());
	    }
	  }
	});

    $('#evteditfrm').ajaxForm({
        target: '#eventsdiv',
        resetForm: true,
        success:function() {
          $('#ev_dialog').dialog('close'); 
        }
    });
    $('#evtdelfrm').ajaxForm({
        target: '#eventsdiv',
        resetForm: true,
        success:function() {
          $('#ev_deldialog').dialog('close'); 
        }
    });
});
function showid(n){
  document.getElementById(n).scrollIntoView(false);
}
</SCRIPT>
<?php
if (!isset($_REQUEST['id'])) {echo t("ERROR:ID not defined");exit;}
$id=$_REQUEST['id'];
$sql="SELECT * FROM contracts WHERE id='$id'";
$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);
if (($id !="new") && (count($r)<5)) {echo t("ERROR: non-existent ID");exit;}
echo "\n<form method=post  action='$scriptname?action=$action&id=$id' id='mainform' enctype='multipart/form-data'  name='contractfrm'>\n";
if ($id=="new")
  echo "\n<h1>".t("Add Contract")."</h1>\n";
else
  echo "\n<h1>".t("Edit Contract")."</h1>\n";
?>
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px;display:none;'>
        <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>
        <h4><?php te("There are <strong>error</strong>s in your form submission, please see below for details.");?></h4>
        <ol>
                <li><label for="title" class="error"><?php te("Contract Title is missing.");?></label></li>
                <li><label for="number" class="error"><?php te("Contract number is missing.");?></label></li>
                <li><label for="typex" class="error"><?php te("Contract Type is missing.");?></label></li>
                <li><label for="contractorid" class="error"><?php te("Contractor is missing.");?></label></li>
                <li><label for="startdate" class="error"><?php te("Start Date of contract is missing.");?></label></li>
                <li><label for="currentenddate" class="error"><?php te("Current End Date of contract is missing.");?></label></li>
        </ol>
</div>
<div id="tabs">
  <ul>
  <li><a href="#tab1"><?php te("Contract Data");?></a></li>
  <li><a href="#tab2"><?php te("Event History");?></a></li>
  <li><a href="#tab3"><?php te("Item Associations");?></a></li>
  <li><a href="#tab4"><?php te("Software Associations");?></a></li>
  <li><a href="#tab5"><?php te("Invoice Associations");?></a></li>
  <li><a href="#tab6"><?php te("Upload Files");?></a></li>
  </ul>
<div id="tab1" class="tab_content">
  <table class='tbl1' style='width:100%' >
  <tr>
  <td class="tdtop">
      <h3><?php te("Contract Properties");?></h3>
      <table border=0 class="tbl2" width='100%'>
      <tr><td class="tdt"><?php te("ID");?>:</td> <td><input  class='input1' type=text name='id' value='<?php echo $id?>' readonly size=3></td></tr>
      <tr><td class="tdt"><?php te("Title");?><sup class='red'>*</sup>:</td> <td><input  class='input1 mandatory' validate='required:true' size=20 type=text name='title' value="<?php echo $r["title"]?>"></td></tr>
      <tr><td class="tdt"><?php te("Number");?><sup class='red'>*</sup>:</td> <td><input  class='input1 mandatory' validate='required:true' size=20 type=text name='number' value="<?php echo $r["number"]?>"></td></tr>
      <tr><td class="tdt"><?php te("Type");?><sup class='red'>*</sup>:</td> <td>
      <select class='mandatory'  validate='required:true' name='typex'>
      <option  value=''><?php te("Select");?></option>
      <?php 
      for ($i=0;$i<count($contracttypes);$i++) {
	$dbid=$contracttypes[$i]['id'];
        $itype=($contracttypes[$i]['name']);
	if (($r['type']==$dbid) && (!is_numeric($r['subtype']))) $s=" SELECTED "; else $s="";
	echo "<option $s value='$dbid:'>$itype</option>\n";
	for ($i2=0;$i2<count($contractsubtypes);$i2++) {
	  if ($contractsubtypes[$i2]['contypeid']!=$dbid) continue;
	  $dbid2=$contractsubtypes[$i2]['id']; 
	  $itype2=($contractsubtypes[$i2]['name']); $s="";
	  if (($r['type']==$dbid) && ($r['subtype']==$dbid2)) $s=" SELECTED ";
	  echo "<option $s value='$dbid:$dbid2'>$itype:$itype2</option>\n";
	}
      }
      ?>
      </select>
      </td></tr>
      <tr><td class="tdt">
      <?php   
       if (is_numeric($r['contractorid']))
	 echo "<a title='".t("Edit Vendor (agent)")."' href='$scriptname?action=editagent&id={$r['contractorid']}'><img src='images/edit.png'></a> "; 
      ?>
      <?php te("Contractor");?><sup class='red'>*</sup>:</td> <td>
	   <select class='mandatory' validate='required:true' name='contractorid' title='<?php te("Agent of Type Contractor");?>'>
	  <option  value=''><?php te("Select");?></option>
	  <?php 
	    foreach ($agents as $a) {
	      if (!($a['type']&16)) continue;
	      $dbid=$a['id']; 
	      $atype=$a['title']; $s="";
	      if (isset($r['contractorid']) && $r['contractorid']==$a['id']) $s=" SELECTED ";
	      echo "<option $s value='$dbid' title='$dbid'>$atype</option>\n";
	    }
	    echo "</select>\n";
	  ?>
      </td></tr>
      <tr><td class="tdt">
      <?php   
       if (is_numeric($r['parentid']))
	 echo "<a title='".t("Edit parent")."' href='$scriptname?action=editcontract&id={$r['parentid']}'><img src='images/edit.png'></a> "; 
      ?>
      <?php te("Parent");?>:</td> <td>
      <select class=''  name='parentid'>
      <option value=''>-- <?php te("Select");?> --</option>
      <?php 
      $sql="SELECT id,title FROM contracts  WHERE id <> '$id'";
      $sth1=db_execute($dbh,$sql);
      $rac=$sth1->fetchAll(PDO::FETCH_ASSOC);
      $nitems=count($rac);
      $institems="";
      for ($i=0;$i<$nitems;$i++) {
	$dbid=$rac[$i]['id'];
	$title=$rac[$i]['title'];
	if ($r['parentid']==$dbid) $s=" SELECTED "; else $s="";
	echo "<option $s value='$dbid'>$dbid:$title</option>\n";
      }
      ?>
      </select>
      </td></tr>
      <tr><td class="tdt"><?php te("Total Cost");?> (<?php echo $settings['currency']?>):</td> <td><input  class='input1' size=20 type=text name='totalcost' value="<?php echo $r["totalcost"]?>"></td></tr>
      <tr>
      <?php  
      $sdate=strlen($r["startdate"])?date($dateparam,$r['startdate']):"";
      ?>
      <td class="tdt"><?php te("Start Date");?><sup class='red'>*</sup>:</td> <td><input  class='dateinp mandatory' id=startdate validate="required:true " size=10 title='<?php echo $datetitle?>' type=text id='startdate' name='startdate' value='<?php echo $sdate?>'>
      </td>
      </tr>
      <tr>
      <?php  
      $edate=strlen($r["currentenddate"])?date($dateparam,$r['currentenddate']):"";
      ?>
      <td class="tdt"><?php te("Current EndDate");?><sup class='red'>*</sup>:</td> <td><input  class='dateinp mandatory' validate='required:true' size=10 title='<?php echo $datetitle?>' type=text id='currentenddate' name='currentenddate' value='<?php echo $edate?>'>
      </td>
      </tr>
      </table>
  </td>
  <td class="tdtop" >
    <div style='float:left;width:70%;'>
      <h3><?php te("Contract Description");?></h3>
      <textarea name='description' style='height: 180px;width:99%;' wrap='soft'><?php echo $r['description']?></textarea> 
    </div>
    <div style='float:left;width:30%'>
      <h3><?php te("Comments");?></h3>
      <textarea name='comments' style='height: 180px;width:99%;' wrap='soft'><?php echo $r['comments']?></textarea> 
    </div>
  </td>
  </tr>
<?php 
  $f=contractid2files($id,$dbh);
  $flnk=showfiles($f);
  $sql="SELECT files.* from files,invoice2file,contract2inv WHERE ".
      " contract2inv.contractid='$id' AND ".
      " invoice2file.invoiceid=contract2inv.invid AND ".
      " invoice2file.fileid=files.id";
  $sthi=db_execute($dbh,$sql);
  $f=$sthi->fetchAll(PDO::FETCH_ASSOC);
  $flnk.=showfiles($f,'fileslist2',0);
?>
  <tr><td colspan=2>
    <table width='100%'>
    <tr><td><h3><?php te("Associated Files");?><img onclick='window.location.href=window.location.href;' 
						src='images/refresh.png'></h3></td></tr>
    <tr>
    <td>&nbsp;
	<?php echo $flnk?>
    </td>
    </tr>
    </table>
  </td> </tr>
  <tr> 
  <td  style='vertical-align:top;' rowspan=2>
    <h3><?php te("Associations Overview");?></h3>
    <div style='text-align:center'>
      <span class="tita" onclick='showid("items");'><?php te("Items");?></span> |
      <span class="tita" onclick='showid("software");'><?php te("Software");?></span> |
      <span class="tita" onclick='showid("invoices1");'><?php te("Invoices");?></span>
    </div>
    <div class="scrltblcontainer4" style='height:13em'>
    <div  id='items' class='relatedlist'><?php te("ITEMS");?></div>
    <?php 
    if (is_numeric($id)) {
      $sql="SELECT items.id, agents.title || ' ' || items.model || ' [ ' || itemtypes.typedesc || ', ID:' || items.id || ' ]' as txt ".
	   "FROM agents,items,contract2item,itemtypes WHERE ".
           " agents.id=items.manufacturerid AND items.itemtypeid=itemtypes.id AND ".
           " contract2item.itemid=items.id AND contract2item.contractid='$id'";
      $sthi=db_execute($dbh,$sql);
      $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
      $nitems=count($ri);
      $institems="";
      for ($i=0;$i<$nitems;$i++) {
	$x=($i+1).": ".$ri[$i]['txt'];
	if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
	$institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>".
		    "<a href='$scriptname?action=edititem&id={$ri[$i]['id']}'>$x</a></div>\n";
      }
      echo $institems;
    }
    ?>
    <div  id='software' class='relatedlist'><?php te("SOFTWARE");?></div>
    <?php 
    if (is_numeric($id)) {
      $sql="SELECT software.id, agents.title || ' ' || software.stitle || ' '||software.sversion || ' [ID:' || software.id || ']' as txt ".
	   "FROM agents,software,contract2soft WHERE ".
           " agents.id=software.manufacturerid AND contract2soft.softid=software.id AND contract2soft.contractid='$id'";
      $sthi=db_execute($dbh,$sql);
      $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
      $nitems=count($ri);
      $institems="";
      for ($i=0;$i<$nitems;$i++) {
	$x=($i+1).": ".$ri[$i]['txt'];
	if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
	$institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>".
		    "<a href='$scriptname?action=editsoftware&id={$ri[$i]['id']}'>$x</a></div>\n";
      }
      echo $institems;
    }
    ?>
    <div id='invoices1' class='relatedlist'><?php te("INVOICES");?></div>
    <?php 
    if (is_numeric($id)) {
      $sql="SELECT invoices.id, invoices.number, invoices.date FROM invoices,contract2inv ".
	   " WHERE contract2inv.invid=invoices.id AND contract2inv.contractid='$id'";
      $sthi=db_execute($dbh,$sql);
      $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
      $nitems=count($ri);
      $institems="";
      for ($i=0;$i<$nitems;$i++) {
	$d=strlen($ri[$i]['date'])?date($dateparam,$ri[$i]['date']):"";
	$x=($i+1).":  ({$ri[$i]['number']}) - $d [ID:{$ri[$i]['id']}]";
	if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
	$institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>".
		    "<a href='$scriptname?action=editinvoice&id={$ri[$i]['id']}'>$x</a></div>\n";
      }
      echo $institems;
    }
    ?>
    </div>
  </td> 
  <td class="tdtop">
    <h3><?php te("Renewals");?></h3> 
    <div class="scrltblcontainer3">
	  <table class=tbl2 id="renewalstable">
	  <tr> 
	      <th title='<?php te("Add Row");?>' ><button id='caddrow' style='margin:0;padding:0;font-weight:bold;font-size:1em;' >+</button></th>
	      <th><?php te("End Date before");?></th> 
	      <th><?php te("End Date after");?></th> 
	      <th><?php te("Effective date");?></th> 
	      <th><?php te("Notes");?></th> 
	      <th><?php te("Date Entered");?></th> 
	      <th><?php te("Entered By");?></th> 
	  </tr> 
	  <?php 
	  $allrenewals=explode("|",$r['renewals']);
	  for ($i=0;$i<count($allrenewals);$i++) {
	    $row=explode("#",$allrenewals[$i]);
	    $enddatebefore=($row[0]);
	    $enddateafter=($row[1]);
	    $effectivedate=($row[2]);
	    $notes=$row[3];
	    $dateentered=($row[4]);
	    $enteredby=strlen($row[5])?$row[5]:$userdata[0]['username'];
	  ?>
	    <tr> 
		<td><img <?php if (!$i) echo "style='display:none'";?> title='<?php te("Delete Row");?>' class='delrow' src='images/delete.png'></td>
		<td><input class='dateinp' style='width:7em'  type="text" name="ren_enddatebefore[]" size="8" value='<?php echo $enddatebefore?>' ></td>
		<td><input class='dateinp'  style='width:7em' type="text" name="ren_enddateafter[]" size="8" value='<?php echo $enddateafter?>' ></td>
		<td><input class='dateinp'  style='width:7em' type="text" name="ren_effectivedate[]" size="8" value='<?php echo $effectivedate?>' ></td>
		<td><input type=text  name='ren_notes[]'  style='width:10em;' value='<?php echo $notes?>'></td> 
		<td><input class='dateinp'  style='width:7em' type="text" name="ren_dateentered[]" size="8" value='<?php echo $dateentered?>'></td>
		<td><input style='width:5em' type="text" name="ren_enteredby[]" size="5"  value='<?php echo $enteredby?>'></td> 
	    </tr> 
	  <?php 
	  }
	  ?>
	  </table><br>
    </div>
  </td>
  </tr>
  <tr>
  <td>
  </td>
  </tr>
  </table>
</div>
<div id="tab2" class="tab_content">
<h2><?php te("Contract Event History");?></h2>
  <div id='eventsdiv' style='margin-left:auto;margin-right:auto;' class='scrltblcontainer2'>
  <?php include('contractevents.php');?>
  </div>
  <button type=button id='rownew' value=lala onclick="$('#ev_dialog').data('rowid','new').dialog('open')"><?php te("Add");?></button>
</div>
<div id="tab3" class="tab_content">
  <h2>
      <input style='color:#909090' id="itemsfilter" name="itemsfilter" class='filter' 
             value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20">
  </h2>
  <?php 
  $sql=" SELECT COALESCE((SELECT itemid FROM contract2item WHERE contractid='$id' AND itemid=items.id ),0) islinked , 
       items.id,status,manufacturerid,model,itemtypeid,typedesc,sn || ' '||sn2 ||' ' || sn3 as sn,dnsname,users.username ,label 
       FROM items,itemtypes,users  WHERE items.itemtypeid=itemtypes.id AND users.id=userid 
       order by islinked desc,itemtypeid,items.id desc, manufacturerid,model, dnsname ";
  $sth=db_execute($dbh,$sql);
  ?>
  <div style='margin-left:auto;margin-right:auto;' class='scrltblcontainer2'>
     <table width='100%' class='brdr sortable'  id='itemslisttbl'>
       <thead>
          <tr><th width='5%'><?php te("Associated");?></th><th style='width:65px;'><?php te("ID");?></th><th><?php te("Type");?></th>
                  <th><?php te("Manufacturer");?></th><th><?php te("Model");?></th>
                   <th><?php te("Label");?></th><th>DNS</th><th><?php te("User");?></th><th><?php te("S/N");?></th>
          </tr>
        </thead>
        <tbody>
  <?php 
  while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
    $cls = $ir['islinked'] ? "class='bld'" : "";
    $x=attrofstatus((int)$ir['status'],$dbh);
    $attr=$x[0];
    echo "<tr><td><input name='itlnk[]' value='".$ir['id']."' ".($ir['islinked']?" checked":"")." type='checkbox'></td>".
     "<td nowrap $cls><span $attr>&nbsp;</span><a title='Edit item {$ir['id']}' target=_blank href='$scriptname?action=edititem&id={$ir['id']}'><div class='editid'>{$ir['id']}</div></a></td>".
     "<td $cls>".$ir['typedesc']."</td>".
     "<td $cls>".$agents[$ir['manufacturerid']]['title']. "&nbsp;</td>".
     "<td $cls>".$ir['model'].  "&nbsp;</td>".
     "<td $cls>".$ir['label']."&nbsp;</td>".
     "<td $cls>".$ir['dnsname']."&nbsp;</td>".
     "<td $cls>".$ir['username']."&nbsp;</td>".
     "<td $cls>".$ir['sn']."&nbsp;</td></tr>\n";
  }
  ?>
  </tbody>
  </table>
  </div>
</div>
<div id="tab4" class="tab_content">
  <h2>
      <input style='color:#909090' id="softfilter" name="softfilter" class='filter' 
             value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20">
  </h2>
  <?php 
  $sql=" SELECT COALESCE((SELECT softid from contract2soft WHERE contractid='$id' AND softid=software.id ),0) islinked , 
       software.id, stitle || ' ' || sversion as titver, agents.title AS agtitle  
       FROM software,agents WHERE agents.id=software.manufacturerid  
       ORDER BY islinked desc,manufacturerid,stitle,sversion ";
  $sth=db_execute($dbh,$sql);
  ?>
  <div style='margin-left:auto;margin-right:auto;' class='scrltblcontainer2'>
     <table width='100%' class='tbl2 brdr sortable'  id='softlisttbl'>
       <thead>
          <tr><th width='5%'><?php te("Associated");?></th><th>ID</th><th><?php te("Manufacturer");?></th><th><?php te("Title/Ver.");?></th>
          </tr>
        </thead>
        <tbody>
  <?php 
$xx=0;
  while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
    $cls = $ir['islinked'] ? "class='bld'" : "";
    $xx++;
    echo "<tr><td><input name='softlnk[]' value='".$ir['id']."' ".($ir['islinked']?" checked":"")." type='checkbox' /></td>".
     "<td $cls><a class='editid' title='Edit software {$ir['id']}' target=_blank href='$scriptname?action=editsoftware&id={$ir['id']}'>{$ir['id']}</a></td>".
     "<td $cls>".$ir['agtitle'].  "&nbsp;</td>".
     "<td $cls>".$ir['titver']."&nbsp;</td></tr>\n";
  }
  ?>
  </tbody>
  </table>
  <?php echo sprintf(t("%s Software listed"),$xx);?>
  </div>
</div>
<div id="tab5" class="tab_content">
  <h2>
      <input style='color:#909090' id="invfilter" name="invfilter" class='filter' 
             value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20">
  </h2>
  <?php 
  $sql=" SELECT COALESCE((SELECT invid from contract2inv WHERE contractid='$id' AND invid=invoices.id ),0) islinked , 
       invoices.id, number,date, agents.title AS agtitle  
       FROM invoices,agents WHERE agents.id=invoices.vendorid  
       ORDER BY islinked desc,date,agtitle";
  $sth=db_execute($dbh,$sql);
  ?>
  <div style='margin-left:auto;margin-right:auto;' class='scrltblcontainer2'>
     <table width='100%' class='tbl2 brdr sortable'  id='invlisttbl'>
       <thead>
          <tr><th width='5%'><?php te("Associated");?></th><th><?php te("ID");?></th><th><?php te("Vendor");?></th>
              <th><?php te("Number");?></th><th><?php te("Date");?></th>
          </tr>
        </thead>
        <tbody>
  <?php 
  while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
    $cls = $ir['islinked'] ? "class='bld'" : "";
    echo "<tr><td><input name='invlnk[]' value='".$ir['id']."' ".($ir['islinked']?" checked":"")." type='checkbox' /></td>".
     "<td $cls><a title='Edit Invoice {$ir['id']}' target=_blank href='$scriptname?action=editinvoice&id={$ir['id']}'>{$ir['id']}</a></td>".
     "<td $cls>".$ir['agtitle'].  "&nbsp;</td>".
     "<td $cls>".$ir['number'].  "&nbsp;</td>".
     "<td $cls>". date("Y-m-d",$ir['date'])."&nbsp;</td></tr>\n";
  }
  ?>
  </tbody>
  </table>
  </div>
</div>
<div id='tab6' class='tab_content'>
    <table class="tbl2" width='100%'>
    <tr><td colspan=2><h2><?php te("Upload a File");?></h2></td></tr>
    <tr><td class="tdc">
    <iframe class="upload_frame" name="upload_frame" 
          src="php/uploadframe.php?id=<?php echo $id?>&assoctable=contract2file&colname=contractid"  
          frameborder="0" allowtransparency="true"></iframe>
    </td>
    </tr>
    </table>
</div>
</div>
<table>
<tr><td><button type="submit"><img src="images/save.png" alt='<?php te("Save");?>' > <?php te("Save");?></button></td>
<?php 
if ($id != "new") {
	echo "<td><button type='button' onclick='delconfirm2(\"{$r['id']}\",\"$scriptname?action=$action&delid={$r['id']}\");'><img title='delete' src='images/delete.png' border=0> ".t("Delete")."</button></td>";
}
?>
</tr>
</table>
<input type=hidden name='action' value='<?php echo $action?>'>
<input type=hidden name='id' value='<?php echo $id?>'>
</form>
<div id="ev_dialog" title='<?php te("Edit Event");?>' style='display:none'>
 <form name="evteditfrm" method="post" id="evteditfrm" action="php/contractevents.php">
  <table>
     <tr><td><?php te("ID");?>:</td><td><input type=text name='eventid' value='new' readonly></td></tr>
     <tr><td><?php te("Sibling");?></td><td>
       <select name='ev_siblingid'>
       <option value=''><?php te("Select");?></option>
       <?php 
       $sql="SELECT id,description FROM contractevents  WHERE contractid = '$id'";
       $sth1=db_execute($dbh,$sql);
       $rac=$sth1->fetchAll(PDO::FETCH_ASSOC);
       foreach ($rac as $row) {
 	 $dbid=$row['id'];
 	 $title=substr($row['description'],0,15)."...";
 	 echo "<option value='$dbid'>$dbid:$title</option>\n";
       }
       ?>
       </select>
     </td></tr>
     <tr><td><?php te("Event Start");?><sup class='red'>*</sup>:</td><td><input type=text class='dateinp' name='ev_startdate'></td></tr>
     <tr><td><?php te("Event End");?><sup class='red'>*</sup>:</td><td><input type=text class='dateinp' name='ev_enddate'></td></tr>
     <tr><td><?php te("Description");?><sup class='red'>*</sup>:</td><td><textarea name='ev_description' style='width:250px;height:80px;'></textarea></td>
  </table>
  <input type=hidden name="contractid" value="<?php echo $id?>">
  <input type=submit value=" <?php te('Save');?> ">
 </form>
</div>
<div id="ev_deldialog" title="<?php te("Delete Event");?>" style='display:none'>
 <form name="evtdelfrm" method="post" id="evtdelfrm" action="php/contractevents.php">
  <b><?php te("Delete Event?");?><br></b>
  <input type=text name='deleventid' value='' readonly>
  <input type=hidden name="contractid" value="<?php echo $id?>">
  <input type=submit value='<?php te("Delete");?>'>
 </form>
</div>
