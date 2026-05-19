<?php
if (!isset($initok)) {echo t("do not run this script directly");exit;}

// 👇 只在这里安全增加重名检测，其余代码完全原版不动
if (isset($_POST['labelaction']) && $_POST['labelaction']=="savepreset") {
    $name = trim($_POST['name']);
    $save_ok = true;
    $msg = '';

    if (!$name) {
        $msg = t("<b><big>Not saved: specify preset name!</big></b>");
        $save_ok = false;
    } else {
        // 重名检测
        $sql_check = "SELECT * FROM labelpapers WHERE name = '".addslashes($name)."'";
        $res_check = $dbh->query($sql_check);
        if ($res_check && $res_check->fetch()) {
            $msg = t("<b><big>ERROR：Preset name already exists！</big></b>");
            $save_ok = false;
        }
    }

    // 检测通过才执行入库
    if ($save_ok) {
        if (!isset($wantbarcode)) $wantbarcode=0;
        if (!isset($wantheadertext)) $wantheadertext=0;
        if (!isset($wantheaderimage)) $wantheaderimage=0;
        if (!isset($wantnotext)) $wantnotext=0;
        if (!isset($wantraligntext)) $wantraligntext=0;
        foreach($_POST as $k => $v) {
            ${$k} = $v;
            if (strstr($k,"want") && $v=="on") $$k=1;
        }
        $print_fields = isset($_POST['print_fields']) ? $_POST['print_fields'] : array();
        if (empty($print_fields)) {
            $print_fields = array('id','manu_model','status','dept_user');
        }
        $print_fields_str = implode(',', $print_fields);
        $custom_w = isset($_POST['custom_w']) ? intval($_POST['custom_w']) : 40;
        $custom_h = isset($_POST['custom_h']) ? intval($_POST['custom_h']) : 30;
        
        $sql="INSERT INTO labelpapers ".
        "(rows,cols,lwidth,lheight,vpitch,hpitch,tmargin,bmargin,lmargin,rmargin,name,
        border,padding,fontsize,headerfontsize,barcodesize,idfontsize,wantbarcode,wantheadertext,wantheaderimage,
        headertext,image,imagewidth,imageheight,papersize,qrtext,wantnotext,wantraligntext,print_fields,custom_w,custom_h) ".
        "values ($rows,$cols,$lwidth,$lheight,$vpitch,$hpitch,$tmargin,$bmargin,$lmargin,$rmargin,'".addslashes($name)."',
        $border,$padding,$fontsize,$headerfontsize,$barcodesize,'".addslashes($idfontsize)."',$wantbarcode,$wantheadertext,$wantheaderimage,
        '".addslashes($headertext)."','".addslashes($image)."',$imagewidth,$imageheight,'".addslashes($papersize)."','".addslashes($qrtext)."',$wantnotext,$wantraligntext,'".addslashes($print_fields_str)."',$custom_w,$custom_h)";
        
        db_execute($dbh,$sql);
        $msg = t("<b><big>Preset saved!</big></b>");
    }

    // 输出提示，不exit，继续渲染下面整个页面
    if (!empty($msg)) {
        echo $msg;
    }
}

?>
<script>
	function toggleCustomSize() {
	  var v = document.getElementById('papersize').value;
	  document.getElementById('custom_size_div').style.display = (v == 'Custom') ? 'block' : 'none';
	}
	window.onload = function() { toggleCustomSize(); };
function ldata(rows,cols,lwidth,lheight, vpitch, hpitch, tmargin, bmargin, lmargin, rmargin,name,
               border,padding,fontsize, headerfontsize,barcodesize, idfontsize,wantbarcode,wantheadertext,wantheaderimage,
               headertext,image,imageheight,imagewidth,papersize,qrtext,wantnotext,wantraligntext,print_fields,custom_w,custom_h)
{
  document.selitemsfrm.lwidth.value=lwidth;
  document.selitemsfrm.lheight.value=lheight;
  document.selitemsfrm.vpitch.value=vpitch;
  document.selitemsfrm.hpitch.value=hpitch;
  document.selitemsfrm.tmargin.value=tmargin;
  document.selitemsfrm.bmargin.value=bmargin;
  document.selitemsfrm.lmargin.value=lmargin;
  document.selitemsfrm.rmargin.value=rmargin;
  document.selitemsfrm.name.value=name;
  document.selitemsfrm.oldname.value=name;
  document.selitemsfrm.border.value=border;
  document.selitemsfrm.padding.value=padding;
  document.selitemsfrm.headerfontsize.value=headerfontsize;
  document.selitemsfrm.barcodesize.value=barcodesize;
  document.selitemsfrm.idfontsize.value = idfontsize || '#000000';
  document.selitemsfrm.fontsize.value=fontsize;
  document.selitemsfrm.image.value=image;
  document.selitemsfrm.imagewidth.value=imagewidth;
  document.selitemsfrm.imageheight.value=imageheight;
  document.selitemsfrm.qrtext.value=qrtext;
  $("#wantbarcode").prop("checked", wantbarcode);
  $("#wantheadertext").prop("checked", wantheadertext);
  $("#wantheaderimage").prop("checked", wantheaderimage);
  $("#wantnotext").prop("checked", 1*wantnotext);
  $("#wantraligntext").prop("checked", 1*wantraligntext);
  document.selitemsfrm.headertext.value=headertext;
  document.selitemsfrm.rows.selectedIndex = rows-1;
  document.selitemsfrm.cols.selectedIndex = cols-1;
  $("#pn_"+papersize).attr("selected", "selected");
  $('#theimage').attr('src',$('#iimage').val());
  $("input[name='print_fields[]']").prop('checked', false);
  if(print_fields){
    var arr = print_fields.split(',');
    for(var i=0; i<arr.length; i++){
      $("input[name='print_fields[]'][value='"+arr[i]+"']").prop('checked', true);
    }
  }
  document.selitemsfrm.custom_w.value = custom_w;
  document.selitemsfrm.custom_h.value = custom_h;
  toggleCustomSize(); 
}

	// 👇 完全删除所有禁用代码，原版原样
	function refreshLabelUI() {}
	
	$(document).ready(function() {
	    $("#tabs").tabs();
	    $("#tabs").show();
	    $("#selitems option").clone().appendTo('#selitems2');
	$("#filter").keyup(function () {
	    var filter = $(this).val().trim();
	    var count = 0;
	    $("#selitems option").remove();
	
	    if (filter === '') {
	        $("#selitems2 option").clone().appendTo('#selitems');
	        $("#filter-count").text('0 <?php te("items");?>');
	        return;
	    }
	
	    var reg = new RegExp(filter, "i");
	    var lastGroupAdded = null;
	
	    $("#selitems2 option").each(function () {
	        var $opt = $(this);
	        var isGroup = $opt.is(':disabled');
	
	        if (isGroup) {
	            lastGroupAdded = $opt;
	        } else {
	            if ($opt.text().search(reg) >= 0) {
	                // 先加分组（只加一次）
	                if (lastGroupAdded !== null) {
	                    $("#selitems").append(lastGroupAdded.clone());
	                    lastGroupAdded = null;
	                }
	                $("#selitems").append($opt.clone());
	                count++;
	            }
	        }
	    });
	
	    $("#filter-count").text(count + ' <?php te("items");?>');
	});


$('#getitemspdf').click(function(e) {
    e.preventDefault();
    if (!$("#selitems :selected").length) {
        alert(langSelectItems);
        return;
    }
    $("#selitemsfrm").attr("action", "php/printitemlabels_pdf.php");
    $('#selitemsfrm').submit();
});
    $('#savepreset').click(function(e) {
      e.preventDefault();
      $("#selitemsfrm").attr("action", "?action=printlabels");
      $("#frmlabelaction").val("savepreset");
      $('#selitemsfrm').submit();
    });
    $('#iimage').keyup(function() {
      $('#theimage').attr('src',$('#iimage').val());
    });
    $( "#tabs" ).tabs();
});
</script>
<select id='selitems2' name='selitems2[]' multiple size='1' style='display:none'> </select>
<?php
if (isset($_GET['delpaperid'])) {
  $sql="DELETE from labelpapers where id=".$_GET['delpaperid'];
  $sth=db_execute($dbh,$sql);
  echo "<script>document.location='$scriptname?action=$action'</script>\n";
  echo "<a href='$scriptdir?action=$action'>".t("Go here")."</a>\n</body></html>";
  exit;
}
if (!isset($wantbarcode)) $wantbarcode=0;
if (!isset($wantheadertext)) $wantheadertext=0;
if (!isset($wantheaderimage)) $wantheaderimage=0;
if (isset($_POST['name']))  {
  foreach($_POST as $k => $v) {
    ${$k} = $v;
    if (strstr($k,"want") && $v=="on")
      $$k=1;
  }
}
$print_fields = isset($_POST['print_fields']) ? $_POST['print_fields'] : array();
if (empty($print_fields)) {
    $print_fields = array('id','manu_model','status','dept_user');
}
$sql="SELECT * from itemtypes";
$sth=db_execute($dbh,$sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $itypes[$r['id']]=$r;
$sql="SELECT id,title,type FROM agents";
$sth=db_execute($dbh,$sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $agents[$r['id']]=$r;
$status_map = array();
$res = $dbh->query("SELECT id, statusdesc, color FROM statustypes");
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    $status_map[$row['id']] = $row;
}
$dept_map = array();
$res = $dbh->query("SELECT id, name FROM departments");
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    $dept_map[$row['id']] = $row['name'];
}
$emp_map = array();
$res = $dbh->query("SELECT id, name FROM employees");
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    $emp_map[$row['id']] = $row['name'];
}
$sql="SELECT * from labelpapers";
$sth=$dbh->query($sql);
$alllabels=$sth->fetchAll(PDO::FETCH_ASSOC);
for ($i=0;$i<count($alllabels);$i++) {
  $labelpapers[$alllabels[$i]['id']]=$alllabels[$i];
}

// 默认加载第一个预设（原版不动）
if (!isset($_POST['name']) && !empty($alllabels)) {
    foreach(array_keys($alllabels[0]) as $key) {
        $$key=$alllabels[0][$key];
    }
    if (isset($alllabels[0]['print_fields']) && $alllabels[0]['print_fields'] != '') {
        $print_fields = explode(',', $alllabels[0]['print_fields']);
    } else {
        $print_fields = array('id','manu_model','status','dept_user');
    }
}

if (isset($_GET['orderby']))
  $orderby=$_GET['orderby'];
else
  $orderby='status';
  // 统计每个状态数量
	$status_count = array();
	$sth_count = $dbh->query("SELECT status, COUNT(*) AS cnt FROM items GROUP BY status");
	while ($row = $sth_count->fetch(PDO::FETCH_ASSOC)) {
	    $status_count[$row['status']] = $row['cnt'];
}

?>

<h1><?php te("Print Labels");?></h1>
<script>var langSelectItems = "<?php echo addslashes(t("Select items from the list first")); ?>";</script>

<div id='labelcontainer'>
<form method=post id='selitemsfrm' name='selitemsfrm'>
<input type="hidden" name="oldname" id="oldname" value="">
<input type="hidden" name="labelaction" id="frmlabelaction" value="">

  <div class='labellist' style='float:left;'>
    <div id='tabs'>
	<ul>
		<li><a href="#tabs-1"><?php te("Items");?></a></li>
	</ul>
      <div id="tabs-1">
	<div style='float:left;text-align:left'>
	  <b><?php te("Order By");?>:
	  <a title='<?php te("order: status, item type, manufacturer,id");?>' href='<?php echo "$fscriptname?action=$action"?>'>[<?php te("Type");?>]</a>
	  <a title='<?php te("order: status, id, item type, manufacturer");?>' href='<?php echo "$fscriptname?action=$action&amp;orderby=items.id"?>'>[<?php te("ID");?>↑]</a>
	  <a title='<?php te("order: status, id descending, item type, manufacturer");?>' href='<?php echo "$fscriptname?action=$action&amp;orderby=items.id+desc"?>'>[<?php te("ID");?>↓]</a>
	  <a title='<?php te("order: status, internalid, item type, manufacturer");?>' href='<?php echo "$fscriptname?action=$action&amp;orderby=internalid"?>'>[<?php te("Internal ID");?>↑]</a>
	  <a title='<?php te("order: status, internalid descending, item type, manufacturer");?>' href='<?php echo "$fscriptname?action=$action&amp;orderby=internalid+desc"?>'>[<?php te("Internal ID");?>↓]</a>
	  <a title='<?php te("order: status, department, item type, manufacturer");?>' href='<?php echo "$fscriptname?action=$action&amp;orderby=custom_dept"?>'>[<?php te("Department");?>]</a>
	  </b>
	</div>
	<div style='float:right;text-align:left'>
	<b><?php te("Filter");?></b>:<input title='<?php te("enter text to filter listed items");?>' id="filter" name="filter" size="20">
	<span id='filter-count'></span>
	</div>
      <br>
      <div id='selcontainer'>
      <?php
      $sth=db_execute($dbh,"SELECT count(id) as count from items");
      $r=$sth->fetch(PDO::FETCH_ASSOC);
      $sth->closeCursor();
      $nitems=$r['count'];
      echo "<select id='selitems' class='monospaced' name='selitems[]' multiple='multiple' style='width:100%;height:300px;overflow-y:auto;'>\n";
      $sql="SELECT items.id,manufacturerid,model,status,sn,sn3,itemtypeid,internalid,custom_user,custom_dept,label,purchprice,purchasedate,macs ".
	   " FROM items,itemtypes ".
	   " WHERE items.itemtypeid=itemtypes.id ".
	   " ORDER BY status,$orderby,itemtypes.typedesc, manufacturerid,items.id,internalid,custom_dept,custom_user, sn, sn2, sn3";
      $sth=db_execute($dbh,$sql);
      $pstatus = -999;
      while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
	  $idesc=$itypes[$r['itemtypeid']]['typedesc'];
	  $idesc=sprintf("%-10s",$idesc);
	  $idesc=str_replace(" ","&nbsp;",$idesc);
	  $id=sprintf("%04d",$r['id']);
	  $internalid=sprintf("%-15s",$r['internalid']);
	  $internalid=str_replace(" ","&nbsp;",$internalid);
	  $dept_id = $r['custom_dept'];
	  $dept_name = isset($dept_map[$dept_id]) ? $dept_map[$dept_id] : $dept_id;
	  $user_id = $r['custom_user'];
	  $user_name = isset($emp_map[$user_id]) ? $emp_map[$user_id] : $user_id;
	  $custom_user = sprintf("%-8s", $user_name);
	  $custom_user = str_replace(" ", "&nbsp;", $custom_user);
	  $custom_dept = sprintf("%-10s", $dept_name);
	  $custom_dept = str_replace(" ", "&nbsp;", $custom_dept);
	  $sid = $r['status'];
	  $sname = "Status_$sid";
	  $scolor = "#eee";
	  if (isset($status_map[$sid])) {
	      $sname = $status_map[$sid]['statusdesc'];
	      $scolor = $status_map[$sid]['color'];
	  }
	  if ($pstatus != $sid) {
	      $cnt = isset($status_count[$sid]) ? $status_count[$sid] : 0;
	      $txtColor = getAutoTextColor($scolor);
			echo "<option disabled style='background-color:$scolor;color:$txtColor;font-weight:bold;text-align:center;'>".htmlspecialchars($sname)."（{$cnt}）</option>";
	      $pstatus = $sid;
	  }
	  $sn=strlen($r['sn'])>0?$r['sn']:$r['sn3'];
	  if (isset($_POST['selitems']) && in_array($id, $_POST['selitems'])) $s="selected";
	  else $s="";
	  $label = strlen($r['label']) ? "-".$r['label'] : "";
	  echo "<option class='monospaced' $s value='{$r['id']}'>$id-$idesc|$internalid,$custom_user,$custom_dept|$sname {$agents[$r['manufacturerid']]['title']}-{$r['model']}-$sn$label</option>\n";
      }
	$sth->closeCursor();
      ?>
      </select>
      </div>
      <br><input class='prepbtn' id='getitemspdf' type=submit value='<?php te("Make Item Labels");?>'>
      <br>
      <ol style='text-align:left'>
      <li><?php te("Select items from the list above");?></li>
      <li><?php te("Select Label properties (manual or preset)");?></li>
      <li><?php te('Click "Make Item Labels"');?></li>
      <li><?php te("Download &amp; print the resulting PDF");?></li>
      </ol>
      <?php echo t("<br>In the PDF printing dialog,");?>
      <ul>
	  <li><?php te('set <b>"Page Scaling"</b> to <b>"None"</b>');?></li>
	  <li><?php te('<b>uncheck</b> "auto-rotate &amp; center"</b>');?></li>
      </ul>
    </div>
  </div>
</div>

<div class='blue' style='float:left;margin-left:10px;'>
<table class='propstable' border=0>
<caption><?php te("Label properties");?>:</caption>
<tr><th><?php te("Property");?></th><th><?php te("Value");?></th><th><?php te("Presets");?></th></tr>
<tr>
<td class='tdt'><label for=name><?php te("Preset Name");?>:</label></td>
<td><input size=8 value='<?php echo $name?>' name=name><input type="hidden" name="oldname" value="<?php echo $name; ?>"></td>
<td style='vertical-align:top;' rowspan=13 align=left>
<?php
if (isset($labelpapers))
foreach ($labelpapers as $lp) {
  $pf_esc = htmlspecialchars($lp['print_fields']);
  echo "\n<a href='javascript:ldata({$lp['rows']}, {$lp['cols']}, ".
       "{$lp['lwidth']},{$lp['lheight']}, {$lp['vpitch']}, {$lp['hpitch']}, ".
       "{$lp['tmargin']}, {$lp['bmargin']}, {$lp['lmargin']}, ".
       "{$lp['rmargin']},\"{$lp['name']}\",{$lp['border']},{$lp['padding']},{$lp['fontsize']},{$lp['headerfontsize']},{$lp['barcodesize']},\"{$lp['idfontsize']}\",{$lp['wantbarcode']},{$lp['wantheadertext']},{$lp['wantheaderimage']},\"{$lp['headertext']}\",\"{$lp['image']}\",\"{$lp['imageheight']}\",\"{$lp['imagewidth']}\",\"{$lp['papersize']}\",\"{$lp['qrtext']}\",{$lp['wantnotext']},{$lp['wantraligntext']},\"{$pf_esc}\",{$lp['custom_w']},{$lp['custom_h']})'>{$lp['name']}</a>";
  echo " <a href='javascript:delconfirm(\"{$lp['id']}\",\"$scriptname?action=$action&amp;delpaperid={$lp['id']}\");'><img src='images/delete.png'></a><br>\n";
}
?>
</td>
</tr>
<?php
	echo "<tr><td class='tdt'>".t("Paper Size").":</td><td>";
	$papernames=file("php/papernames.txt");
	echo "<select id='papersize' name='papersize' onchange='toggleCustomSize()'>\n";
	foreach ($papernames as $papername) {
	  $papername=trim($papername);
	  $s = ($papername == $papersize) ? 'SELECTED' : '';
	  echo "<option $s id='pn_$papername' value='$papername'>$papername</option>\n";
	}
	$s_custom = ($papersize == 'Custom') ? 'SELECTED' : '';
	echo "<option $s_custom id='pn_Custom' value='Custom'>".t("Custom Size")."</option>\n";
	echo "</select>";
	
	echo '
	<div id="custom_size_div" style="margin-top:6px;display:none;">
	  '.t("Width").': <input size="4" name="custom_w" id="custom_w" value="40"> mm
	  &nbsp;&nbsp;
	  '.t("Height").': <input size="4" name="custom_h" id="custom_h" value="30"> mm
	</div>
	';
	echo "</td></tr>";
echo "<tr><td class='tdt'>".t("Rows").":</td><td><select name=rows>";
for ($i=1;$i<40;$i++) {
  $s = ($i==$rows) ? 'SELECTED' : '';
  echo "<option $s value=$i>$i</option>";
}
echo "</select></td></tr>";
echo "<tr><td class='tdt'>".t('Columns').":</td><td><select name=cols>";
for ($i=1;$i<10;$i++) {
  $s = ($i==$cols) ? 'SELECTED' : '';
  echo "<option $s value=$i>$i</option>";
}
echo "</select></td></tr>";
?>
<tr><td class='tdt'><?php te("Width");?>:</td><td><input size=4 value='<?php echo $lwidth?>' name=lwidth>mm</td></tr>
<tr><td class='tdt'><?php te("Height");?>:</td><td><input size=4 value='<?php echo $lheight?>' name=lheight>mm</td></tr>
<tr><td class='tdt'><?php te("Vert. Pitch");?>:</td><td><input size=4 value='<?php echo $vpitch?>' name=vpitch>mm</td></tr>
<tr><td class='tdt'><?php te("Horz. Pitch");?>:</td><td><input size=4 value='<?php echo $hpitch?>' name=hpitch>mm</td></tr>
<tr><td class='tdt'><?php te("Top Margin");?>:</td><td><input size=4 value='<?php echo $tmargin?>' name=tmargin>mm</td></tr>
<tr><td class='tdt'><?php te("Bottom Margin");?>:</td><td><input size=4 value='<?php echo $bmargin?>' name=bmargin>mm</td></tr>
<tr><td class='tdt'><?php te("Left Margin");?>:</td><td><input size=4 value='<?php echo $lmargin?>' name=lmargin>mm</td></tr>
<tr><td class='tdt'><?php te("Right Margin");?>:</td><td><input size=4 value='<?php echo $rmargin?>' name=rmargin>mm</td></tr>
<tr><td class='tdt'><?php te("Border Color");?>:</td><td title='<?php te("0:black, 255:white")?>'><input size=4 value='<?php echo $border?>' name=border></td></tr>
<tr><td class='tdt'><?php te("Text Padding");?>:</td><td><input size=4 value='<?php echo $padding?>' name=padding>mm</td>
	<td rowspan=14>
		<small><?php te("Print Fields");?>:</small><br>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="id" <?php if(in_array('id',$print_fields))echo'checked'?>><?php te("ID");?></label>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="internalid" <?php if(in_array('internalid',$print_fields))echo'checked'?>><?php te("Internal ID");?></label><br>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="itemtype" <?php if(in_array('itemtype',$print_fields))echo'checked'?>><?php te("Item Type");?></label>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="manu_model" <?php if(in_array('manu_model',$print_fields))echo'checked'?>><?php te("Manufacturer/Model");?></label><br>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="sn" <?php if(in_array('sn',$print_fields))echo'checked'?>><?php te("SN");?></label>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="label" <?php if(in_array('label',$print_fields))echo'checked'?>><?php te("Label");?></label><br>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="status" <?php if(in_array('status',$print_fields))echo'checked'?>><?php te("Status");?></label>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="dept_user" <?php if(in_array('dept_user',$print_fields))echo'checked'?>><?php te("Department/User");?></label><br>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="purchprice" <?php if(in_array('purchprice',$print_fields))echo'checked'?>><?php te("Purchase Price");?></label>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="purchasedate" <?php if(in_array('purchasedate',$print_fields))echo'checked'?>><?php te("Purchase Date");?></label><br>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="macs" <?php if(in_array('macs',$print_fields))echo'checked'?>><?php te("MAC");?></label>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="ipv4" <?php if(in_array('ipv4',$print_fields))echo'checked'?>><?php te("IPv4");?></label>
		<label style="white-space:nowrap; margin-right:4px;"><input type="checkbox" name="print_fields[]" value="ipv6" <?php if(in_array('ipv6',$print_fields))echo'checked'?>><?php te("IPv6");?></label>
		<div style="text-align:center; margin:8px 0;">
		    <input id="savepreset" type="submit" value="<?php te("Save as new Preset");?>">
		</div>
		
		<div style="text-align:center; margin:8px 0;">
		<img width="180" src="images/labelinfo.jpg">
		</div>
	</td>
</tr>
<tr><td class='tdt'><?php te("FontSize");?>:</td><td><input size=4 value='<?php echo $fontsize?>' name=fontsize>pt</td></tr>
<tr><td class='tdt'><?php te("Header Color");?>:</td><td><input size=10 type="color" value='<?php echo $idfontsize?>' name=idfontsize> <small><?php te("Format: "); ?>#000000</small></td></tr>
<tr><td class='tdt'><?php te("Header FontSize");?>:</td><td><input size=4 value='<?php echo $headerfontsize?>' name=headerfontsize>pt</td></tr>
<tr><td class='tdt'><?php te("Barcode Size");?>:</td><td><input size=4 value='<?php echo $barcodesize?>' name=barcodesize>mm</td></tr>
<tr><td class='tdt'><?php te("Header Image");?>:</td><td><input size=9 id='iimage' style='width:12em' value='<?php echo $image?>' name='image'> <img id='theimage' width="25" height="25" src='<?php echo $image; ?>'></td></tr>
<tr><td class='tdt'><?php te("Image Size (WxH)");?>:</td><td><input size=2 value='<?php echo $imagewidth?>' name='imagewidth'> X <input size=2 value='<?php echo $imageheight?>' name='imageheight'>mm</td></tr>
<tr><td class='tdt'><?php te("Header");?>:</td><td title='<?php te("_NL_ = newline")?>'><textarea wrap=soft rows=2 name='headertext' cols=20><?php echo $headertext?></textarea></td></tr>
<tr><td class='tdt'><?php te("QR Barcode");?>:</td><td title='<?php te("Text to prepend in QR barcode ID. <br>e.g. http://www.example.com/itdb/ ?action=edititem&id=")?>'><input id='wantbarcode' type=checkbox <?php if($wantbarcode) echo "CHECKED"; ?> name=wantbarcode> <input style='width:140px' value='<?php echo $qrtext?>' name=qrtext></td></tr>
<tr><td class='tdt'><?php te("Header Text");?>:</td><td><input id='wantheadertext' type=checkbox <?php if($wantheadertext) echo "CHECKED"; ?> name=wantheadertext></td></tr>
<tr><td class='tdt'><?php te("Header Image");?>:</td><td><input id='wantheaderimage' type=checkbox <?php if($wantheaderimage) echo "CHECKED"; ?> name=wantheaderimage></td></tr>
<tr><td class='tdt'><?php te("No Text");?>:</td><td><input id='wantnotext' type=checkbox <?php if($wantnotext) echo "CHECKED"; ?> name=wantnotext></td></tr>
<tr><td class='tdt'><?php te("Text to the right of barcode");?>:</td><td><input id='wantraligntext' type=checkbox <?php if($wantraligntext) echo "CHECKED"; ?> name=wantraligntext></td></tr>
<tr><td class='tdt'><?php te("Skip");?>:</td><td><input size=4 value='<?php echo $labelskip?>' name=labelskip> <?php te("labels");?></td></tr>
</table>
</div>
</form>
</div>
</body></html>
