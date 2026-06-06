<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
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
	$('.delrow').click(function(){
		var answer = confirm('<?php te("Are you sure you want to delete this row ?");?>');
		if (answer) 
			$(this).parent().parent().remove();
	});
	$("#caddrow").click(function($e){
		var row = $('#contactstable tr:last').clone(true);
		$e.preventDefault();
		row.find("input:text").val("");
		row.find("img").css("display","inline");
		row.insertAfter('#contactstable tr:last');
	});
	$("#uaddrow").click(function($e){
		var row = $('#urlstable tr:last').clone(true);
		$e.preventDefault();
		row.find("input:text").val("");
		row.find("img").css("display","inline");
		row.insertAfter('#urlstable tr:last');
	});
});
</SCRIPT>
<?php 
/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */
$agent_formvars = array(
	'type','title','contactinfo','contacts','urls'
);
$disperr = "";
$title_style = "";
$type_style = "";

//delete agent
if (isset($_GET['delid'])) {
	$delid = intval($_GET['delid']);
	$sql="DELETE from agents where id=$delid";
	$sth=db_exec($dbh,$sql);
	
	$sql="UPDATE items SET manufacturerid='' where manufacturerid=$delid";
	db_exec($dbh,$sql);
	$sql="UPDATE invoices SET vendorid='' WHERE vendorid=$delid";
	db_exec($dbh,$sql);
	$sql="UPDATE invoices SET buyerid='' where buyerid=$delid";
	db_exec($dbh,$sql);
	$sql="UPDATE software SET manufacturerid='' where manufacturerid=$delid";
	db_exec($dbh,$sql);

	addOperateLog(
		'agent',
		'delete',
		'Deleted agent ID %s',
		array($delid),
		'agent',
		$delid,
		null,
		null,
		1,
		''
	);

	$user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
	$sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
								 VALUES ($delid, ".time().", 'Deleted agent by $user', '', 1, ".time().")";
	db_exec($dbh, $sql_action);
	echo "<script>document.location='$scriptname?action=listagents'</script>";
	echo "<a href='$scriptname?action=listagents'>".t("Go here")."</a></body></html>"; 
	exit;
}

if (isset($_POST['id'])) {
	$id=$_POST['id'];
	$type = 0;
	if (!empty($_POST['types'])){
		foreach ($_POST['types'] as $t)
			$type+=(int)$t;
	}

	$crows=count($_POST['cont_name']);
	$row=array();
	for ($i=0;$i<$crows;$i++) {
		$cont_name = preg_replace('/[\|#]/', ' ', $_POST['cont_name'][$i]);
		$cont_phones = preg_replace('/[\|#]/', ' ', $_POST['cont_phones'][$i]);
		$cont_email = preg_replace('/[\|#]/', ' ', $_POST['cont_email'][$i]);
		$cont_role = preg_replace('/[\|#]/', ' ', $_POST['cont_role'][$i]);
		$cont_comments = preg_replace('/[\|#]/', ' ', $_POST['cont_comments'][$i]);
		$row[$i]=implode("#",array($cont_name,$cont_phones,$cont_email,$cont_role,$cont_comments));
	}
	$contacts=implode("|",$row);

	$urows=count($_POST['url_url']);
	$row=array();
	for ($i=0;$i<$urows;$i++) {
		$url_description = preg_replace('/[\|#]/', ' ', $_POST['url_description'][$i]);
		$url_url = preg_replace('/[\|#]/', ' ', $_POST['url_url'][$i]);
		$row[$i]=implode("#",array($url_description,$url_url));
	}
	$urls=implode("|",$row);

	$title=trim($_POST['title']);
	$contactinfo=$_POST['contactinfo'];

	$err_list = array();
	if ($title == '') {
		$err_list[] = t("Agent name is missing");
		$title_style = 'style="border:1px solid #ed2633 !important;background:#fff3f3 !important;"';
	}
	if (empty($type)) {
		$err_list[] = t("Agent type(s) are missing");
		$type_style = 'style="border:1px solid #ed2633 !important;background:#fff3f3 !important;"';
	}

	if (!empty($err_list)) {
		$err_html = "";
		foreach ($err_list as $k => $v) {
			$err_html .= "<li>".$v."</li>";
		}
		$disperr = "
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px;display:block;'>
    <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>
    <h4>".t("There are <strong>error</strong>s in your form submission, please see below for details.")."</h4>
    <ol>
        $err_html
    </ol>
</div>";
	}
	else {
		if ($_POST['id']=="new") {
			$sql="INSERT into agents (type,title,contactinfo,contacts,urls)
						VALUES ('$type','$title','$contactinfo','$contacts','$urls')";
			db_exec($dbh,$sql);
			$lastid=$dbh->lastInsertId();
			$id=$lastid;

			// 新增日志：只保留agent自身信息，无关联
			$new_log_data = array(
				'type'          => $type,
				'title'         => $title,
				'contactinfo'   => $contactinfo,
				'contacts'      => $contacts,
				'urls'          => $urls
			);

			addOperateLog(
				'agent',
				'add',
				'Created new agent ID %s',
				array($lastid),
				'agent',
				$lastid,
				null,
				$new_log_data,
				1,
				''
			);

			$user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
			$sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
										 VALUES ($lastid, ".time().", 'Added agent by $user', '', 1, ".time().")";
			db_exec($dbh, $sql_action);
		}
		else {
			$sth_old = $dbh->query("SELECT * FROM agents WHERE id=$id");
			$old_data_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
			$old_agent_info = array();
			foreach ($agent_formvars as $k) {
				$old_agent_info[$k] = isset($old_data_raw[$k]) ? $old_data_raw[$k] : '';
			}

			$sql="UPDATE agents SET type='$type', title='$title', 
						contactinfo='$contactinfo', contacts='$contacts', urls='$urls' WHERE id=$id";
			db_exec($dbh,$sql);

			$sth_new = $dbh->query("SELECT * FROM agents WHERE id=$id");
			$new_data_raw = $sth_new->fetch(PDO::FETCH_ASSOC);
			$new_agent_info = array();
			foreach ($agent_formvars as $k) {
				$new_agent_info[$k] = isset($new_data_raw[$k]) ? $new_data_raw[$k] : '';
			}

			$diff_old = array();
			$diff_new = array();
			foreach ($old_agent_info as $k => $v) {
				$nv = isset($new_agent_info[$k]) ? $new_agent_info[$k] : '';
				if ((string)$v !== (string)$nv) {
					$diff_old[$k] = $v;
					$diff_new[$k] = $nv;
				}
			}

			// 修改日志：只保留agent自身信息，无关联
			addOperateLog(
				'agent',
				'update',
				'Updated agent ID %s',
				array($id),
				'agent',
				$id,
				$diff_old,
				$diff_new,
				1,
				''
			);

			$user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
			$sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
										 VALUES ($id, ".time().", 'Updated agent by $user', '', 1, ".time().")";
			db_exec($dbh, $sql_action);
		}

		echo "<script>window.location='$scriptname?action=$action&id=$id'</script> ";
		exit;
	}
}

/////////////////////////////
//// display data now
if (!isset($_REQUEST['id'])) {echo t("ERROR:ID not defined");exit;}
$id=$_REQUEST['id'];
$sql="SELECT * FROM agents WHERE id='$id'";
$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);
if (($id !="new") && (count($r)<5)) {echo t("ERROR: non-existent ID");exit;}

$type=$r['type'];
$title=$r['title'];
$contactinfo=$r['contactinfo'];
$contacts=$r['contacts'];
$urls=$r['urls'];

echo "\n<form method=post  action='$scriptname?action=$action&amp;id=$id' enctype='multipart/form-data'  name='addfrm'>\n";
if ($id=="new")
  echo "\n<h1>".t("Add Agent")."</h1>\n";
else
  echo "\n<h1>".t("Edit Agent")."</h1>\n";

echo $disperr;
?>

<table>
<tr>
<td class="tdtop">
	<h3><?php te("Agent Properties");?></h3>
	<table border=0 class="tbl2" width='100%'>
	<tr>
		<td class="tdt"><?php te("ID");?>:</td>
		<td>
			<input class='input1' type=text name='id' value='<?php echo $id?>' readonly size=3>
		</td>
	</tr>
	<tr>
		<td class="tdt"><?php te("Name");?><sup class='red'>*</sup>:</td>
		<td><input class='input1 mandatory' <?php echo $title_style; ?> size=20 type=text name='title' value="<?php echo $title?>"></td>
	</tr>
	<tr>
		<td class="tdt"><?php te("Type(s)");?><sup class='red'>*</sup>:</td> 
		<td title='<?php te("Cntrl+Click to select multiple roles for an agent ".
                   "<br><br><u>Vendor &amp; Buyer</u>: will be listed in invoices &amp; Contracts ".
                   "<br><br><u>H/W Manuf.</u>: will be listed in items editing ".
                   "<br><br><u>S/W Manuf.</u>: will be listed in software editing ".
                   "<br><br><u>Contractor</u>: will be listed in contracts");?>'>
			<select class='mandatory' <?php echo $type_style; ?> multiple size=5 name='types[]'>
				<?php 
				$s1 =($type&1)?"SELECTED":"";
				$s2 =($type&2)?"SELECTED":"";
				$s4 =($type&4)?"SELECTED":"";
				$s8 =($type&8)?"SELECTED":"";
				$s16=($type&16)?"SELECTED":"";
				?>
				<option <?php echo $s4?> value='4'><?php te("Vendor");?></option>
				<option <?php echo $s2?> value='2'><?php te("S/W Manufacturer");?></option>
				<option <?php echo $s8?> value='8'><?php te("H/W Manufacturer");?></option>
				<option <?php echo $s1?> value='1'><?php te("Buyer");?></option>
				<option <?php echo $s16?> value='16'><?php te("Contractor");?></option>
			</select>
		</td>
	</tr>
	</table>
</td>
<td class="tdtop" title='<?php te("Address, Phone number, other info, etc");?>' >
	<h3><?php te("Contact Info");?></h3>
	<textarea name='contactinfo' style='height: 90px;width:550px;' wrap='soft'><?php echo $contactinfo?></textarea> 
</td>
</tr>

<tr> 
<td  style='vertical-align:top;' rowspan=2>
	<h3> <?php te("Related");?>: </h3>
	<div>
		<span class="tita" onclick='showid("items");'><?php te("Items");?></span>  ,
		<span class="tita" onclick='showid("software");'><?php te("Software");?></span>  ,
		<span class="tita" onclick='showid("invoices1");'><?php te("Invoices (vendors)");?></span>  ,
		<span class="tita" onclick='showid("invoices2");'><?php te("Invoices (buyers)");?></span> 
	</div>
	<div class="scrltblcontainer4">
	<div  id='items' class='relatedlist'><?php te("ITEMS");?></div>
	<?php 
	if (is_numeric($id)) {
		$sql="SELECT 
	items.id, 
	items.internalid,
	items.label,
	agents.title as manuf_name,
	items.model,
	departments.name AS dept_name,
	employees.name AS emp_name
	FROM items 
	LEFT JOIN agents ON agents.id = items.manufacturerid
	LEFT JOIN departments ON departments.id = items.custom_dept
	LEFT JOIN employees ON employees.id = items.custom_user
	WHERE items.manufacturerid='$id'";
	$sthi=db_execute($dbh,$sql);
	$ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
	$nitems=count($ri);
	$institems="";
	for ($i=0;$i<$nitems;$i++) {
	    $tip = t("Label").": {$ri[$i]['label']}\n"
	         . t("Manufacturer").": {$ri[$i]['manuf_name']}\n"
	         . t("Model").": {$ri[$i]['model']}\n"
	         . t("Department").": {$ri[$i]['dept_name']}\n"
	         . t("End User").": {$ri[$i]['emp_name']}";
	    $tip = htmlspecialchars($tip, ENT_QUOTES);
	
	    $x = ($i+1).": ({$ri[$i]['id']}) {$ri[$i]['internalid']}";
	    
	    $bcolor = $i%2 ? "#D9E3F6" : "#ffffff";
	    $institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
	                <a href='$scriptname?action=edititem&amp;id={$ri[$i]['id']}' title=\"$tip\">$x</a></div>\n";
	}
	echo $institems;
	}
	?>
	<div  id='software' class='relatedlist'><?php te("SOFTWARE");?></div>
	<?php 
	if (is_numeric($id)) {
		$sql="SELECT software.id,software.stitle  FROM software WHERE manufacturerid='$id'";
		$sthi=db_execute($dbh,$sql);
		$ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
		$nitems=count($ri);
		$institems="";
		for ($i=0;$i<$nitems;$i++) {
			$x=($i+1).": ({$ri[$i]['id']}) ".$ri[$i]['stitle']." ".$ri[$i]['dnsname'];
			if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
			$institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
						<a href='$scriptname?action=editsoftware&amp;id={$ri[$i]['id']}'>$x</a></div>\n";
		}
		echo $institems;
	}
	?>
	<div id='invoices1' class='relatedlist'><?php te("INVOICES (vendor)");?></div>
	<?php 
	if (is_numeric($id)) {
		$sql="SELECT invoices.id, invoices.number, invoices.date FROM invoices WHERE vendorid='$id'";
		$sthi=db_execute($dbh,$sql);
		$ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
		$nitems=count($ri);
		$institems="";
		for ($i=0;$i<$nitems;$i++) {
			$d=strlen($ri[$i]['date'])?date($dateparam,$ri[$i]['date']):"";
			$x=($i+1).": ({$ri[$i]['id']})  ({$ri[$i]['number']}) - $d";
			if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
			$institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
						<a href='$scriptname?action=editinvoice&amp;id={$ri[$i]['id']}'>$x</a></div>\n";
		}
		echo $institems;
	}
	?>
	<div id='invoices2' class='relatedlist'><?php te("INVOICES (buyer)");?></div>
	<?php 
	if (is_numeric($id)) {
		$sql="SELECT invoices.id, invoices.number, invoices.date FROM invoices WHERE buyerid='$id'";
		$sthi=db_execute($dbh,$sql);
		$ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
		$nitems=count($ri);
		$institems="";
		for ($i=0;$i<$nitems;$i++) {
			$d=strlen($ri[$i]['date'])?date($dateparam,$ri[$i]['date']):"";
			$x=($i+1).": ({$ri[$i]['id']})  ({$ri[$i]['number']}) - $d ";
			if ($i%2) $bcolor="#D9E3F6"; else $bcolor="#ffffff";
			$institems.="\t<div style='margin:0;padding:0;background-color:$bcolor'>
						<a href='$scriptname?action=editinvoice&amp;id={$ri[$i]['id']}'>$x</a></div>\n";
		}
		echo $institems;
	}
	?>
	</div><!-- scrlbcontainer -->
</td> 

<td><h3> <?php te("Contacts");?> <img id='caddrow' src='images/add.png' title="<?php te("Add Row");?>"> </h3>
	<div class="scrltblcontainer3">
		<table class=tbl2 id="contactstable">
			<tr> 
				<th>-</th> 
				<th><?php te("Name");?></th> 
				<th><?php te("Phone numbers");?></th> 
				<th><?php te("Email");?></th> 
				<th><?php te("Role");?></th> 
				<th><?php te("Comments");?></th> 
			</tr> 
			<?php 
			$allcontacts=explode("|",$contacts);
			for ($i=0;$i<count($allcontacts);$i++) {
				$row=explode("#",$allcontacts[$i]);
				$name=isset($row[0])?$row[0]:"";
				$phones=isset($row[1])?$row[1]:"";
				$email=isset($row[2])?$row[2]:"";
				$role=isset($row[3])?$row[3]:"";
				$comments=isset($row[4])?$row[4]:"";
				?>
				<tr> 
					<td><img <?php  if (!$i) echo "style='display:none'";?> class='delrow' src='images/delete.png'></td>
					<td><input type="text" name="cont_name[]" size="15" value='<?php echo $name?>' ></td> 
					<td><input type="text" name="cont_phones[]" size="15"  value='<?php echo $phones?>'></td> 
					<td><input type="text" name="cont_email[]" size="15" value='<?php echo $email?>'></td> 
					<td><input type="text" name="cont_role[]" size="15"  value='<?php echo $role?>'></td> 
					<td><textarea name="cont_comments[]" size="20" ><?php echo $comments?></textarea></td> 
				</tr> 
				<?php 
			}
			?>
		</table><br>
	</div><!-- scrlbcontainer -->
</td>
</tr>

<tr>
<td class="tdtop">
	<h3><?php te("URLs");?> <img src='images/add.png'  id='uaddrow' title="<?php te("Add Row");?>"> </h3>
	<div class="scrltblcontainer3">
		<table class=tbl2 id="urlstable">
			<tr> 
				<th>-</th> 
				<th><?php te("Description");?></th> 
				<th>URL</th> 
				<th><?php te("LINK");?></th> 
			</tr> 
			<?php 
			$allurls=explode("|",$urls);
			for ($i=0;$i<count($allurls);$i++) {
				$row=explode("#",$allurls[$i]);
				$description=isset($row[0])?$row[0]:"";
				$url=isset($row[1])?urldecode($row[1]):"";
				?>
				<tr> 
					<td><img <?php  if (!$i) echo "style='display:none'";?> class='delrow' src='images/delete.png'></td>
					<td><input type="text" name="url_description[]" size="25" value='<?php echo $description?>' ></td> 
					<td><input type="text" name="url_url[]" size="60"  value='<?php echo $url?>'></td> 
					<td><a target="_blank" href='<?php echo $url?>'><?php te("GO");?></a></td> 
				</tr> 
				<?php 
			}
			?>
		</table><br>
		<sup>*</sup>
		<?php te("Use the string 'service' on the description to display this url on the item edit page.");?>
</td>
</tr>

<tr>
<td ><button type="submit"><img src="images/save.png" alt='<?php te("Save");?>' > <?php te("Save");?></button></td>
	<?php 
	if ($id != "new") {
		echo "\n<td><button type='button' onclick='javascript:delconfirm2(\"{$r['id']}\",\"$scriptname?action=$action&amp;delid={$r['id']}\");'>".
			"<img title='".t("Delete")."' src='images/delete.png' border=0>".t("Delete")."</button></td>\n</tr>\n";
	}
	echo "\n</table>\n";
	echo "\n<input type=hidden name='action' value='$action'>";
	echo "\n<input type=hidden name='id' value='$id'>";
	?>
</form>
</body>
</html>
