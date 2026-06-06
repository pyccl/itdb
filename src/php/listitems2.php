<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}
/* Spiros Ioannou 2009 , sivann _at_ gmail.com */

/// get item types
$sql="SELECT * from itemtypes order by typedesc";
$sth=db_execute($dbh,$sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $itypes[$r['id']]=$r;
$sth->closeCursor();

$sql="SELECT * from locations order by name,floor";
$sth=$dbh->query($sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $locations[$r['id']]=$r;
$sth->closeCursor();


$sql="SELECT * from racks";
$sth=$dbh->query($sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $racks[$r['id']]=$r;
$sth->closeCursor();

$sql="SELECT * from tags order by name";
$sth=$dbh->query($sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $tags[$r['id']]=$r;
$sth->closeCursor();

$sql="SELECT id,title FROM agents";
$sth=db_execute($dbh,$sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $agents[$r['id']]=$r;
$sth->closeCursor();

$sql="SELECT * FROM statustypes";
$sth=$dbh->query($sql);
$statustypes=$sth->fetchAll(PDO::FETCH_ASSOC);

//expand: show more columns
if (isset($_GET['expand']) && $_GET['expand']==1) 
  $expand=1;
else 
  $expand=0;

//export: export to excel (as html table readable by excel)
if (isset($_GET['export']) && $_GET['export']==1) {
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header("Expires: Thu, 01 Dec 1994 16:00:00 GMT");
  header("Cache-Control: Must-Revalidate");
  header('Content-Disposition: attachment; filename=itdb.xls');
  header('Connection: close');
  $export=1;
  $expand=1;//always export expanded view
}
else 
  $export=0;


if (!$export) 
  $perpage=18;
else 
  $perpage=10000;

if ($page=="all") {
  $perpage=10000;
}

if ($export)  {
  echo "<html>\n<head><meta http-equiv=\"Content-Type\"".
     " content=\"text/html; charset=UTF-8\" /></head>\n<body>\n";
}

/// display list
if ($export) 
  echo "\n<table border='1'>\n";
else {
  echo "<h1>".t("Items")." <sup>2</sup> <a title='".t("Add new Item")."' href='$scriptname?action=edititem&amp;id=new'>".
       "<img border=0 src='images/add.png'></a></h1>\n";
  echo "<form name='frm'>\n";
  echo "\n<table class='brdr'>\n";
}

if (!$export) {
  $get2=$_GET;
  unset($get2['orderby']);
  $url=http_build_query($get2);
}

if (!isset($orderby) && empty($orderby)) 
  $orderby="items.id desc";
elseif (isset($orderby)) {

  if (stristr($orderby,"FROM")||stristr($orderby,"WHERE")) {
    $orderby="id";
  }
  if (strstr($orderby,"DESC"))
    $ob="+ASC";
  else
    $ob="+DESC";
}

echo "<thead>\n";
$thead= "\n<tr><th title='".t("Search")."'>".
     "<button type='submit' style='padding:1px;'><img border=0  src='images/search.png'></button>".
     "<a href='$fscriptname?$url&amp;orderby=items.id$ob'>".t("ID")."</a></th>".
	 "<th><a href='$fscriptname?$url&amp;orderby=internalid$ob'>".t("Internal ID")."</a></th>".
     "<th><a href='$fscriptname?$url&amp;orderby=label$ob'>".t("Item Type")."</a></th>".
     "<th><a href='$fscriptname?$url&amp;orderby=itemtypeid$ob'>".t("Manufacturer")."</a></th>".
     "<th><a href='$fscriptname?$url&amp;orderby=manufacturerid$ob'>".t("Model")."</a></th>".
     "<th>".t("S/N")."</th>".
     "<th><a href='$fscriptname?$url&amp;orderby=sn$ob,sn2$ob,sn3$ob'>".t("Label")."</a></th>".
     "<th><a href='$fscriptname?$url&amp;orderby=purchasedate$ob'>".t("Purch. Date")."</a></th>".
     "<th title='".t("Warranty expiration in")."'><small><a href='$fscriptname?action=$action&amp;orderby=remdays$ob'>".t("Warr. Left")."</a></small></th>".
	"<th><a href='$fscriptname?$url&amp;orderby=user_name$ob'>".t("End User")."</a></th>".
	"<th><a href='$fscriptname?$url&amp;orderby=dept_name$ob'>".t("Department")."</a></th>".
     "<th><a href='$fscriptname?$url&amp;orderby=status$ob'>".t("Status")."</a></th>".
     "<th><a href='$fscriptname?$url&amp;orderby=locationid$ob'>".t("Location")."</a></th>";

if ($export) {
 //clean links from excel export
  $thead = preg_replace('@<a[^>]*>([^<]+)</a>@si', '\\1 ', $thead); 
  $thead = preg_replace('@<img[^>]*>@si', ' ', $thead); 
}

echo $thead;

if ($expand) {
  echo "\n <th>".t("Tags")."</th>";
  echo "\n <th>".t("DNS Name")."</th>";
  echo "\n <th>".t("Rack")."</th>";
  echo "<th>".t("Invoices")."</th><th>".t("S/W")."</th>";
  echo "\n <th>".t("PurchPrice")."</th>";
  echo "\n <th>".t("MACs")."</th>";
  echo "\n <th>".t("IPv4")."</th>";
  echo "\n <th>".t("IPv6")."</th>";
  echo "\n <th>".t("RemAdmIP")."</th>";
  echo "\n <th>".t("Ptch.PnlPrt")."</th>";
  echo "\n <th>".t("Switch")."</th>";
  echo "\n <th>".t("Switch Port")."</th>";
  echo "\n <th>".t("ports")."</th>";
}
else
  echo "<th>&nbsp;</th>";//more icon
echo "</tr>\n</thead>\n";

echo "\n<tbody>\n";
echo "\n<tr>";

//create pre-fill form box vars
$id=isset($_GET['id'])?($_GET['id']):"";
$internalid=isset($_GET['internalid'])?($_GET['internalid']):"";
$manufacturer=isset($_GET['manufacturer'])?($_GET['manufacturer']):"";
$dnsname=isset($_GET['dnsname'])?($_GET['dnsname']):"";
$model=isset($_GET['model'])?($_GET['model']):"";
$sn=isset($_GET['sn'])?($_GET['sn']):"";
$year=isset($_GET['year'])?($_GET['year']):"";
$page=isset($_GET['page'])?$_GET['page']:1;
$custom_user=isset($_GET['custom_user'])?$_GET['custom_user']:"";
$custom_dept=isset($_GET['custom_dept'])?$_GET['custom_dept']:"";
$status=isset($_GET['status'])?$_GET['status']:"";

///display search boxes
if (!$export) {

  echo "\n<td title='".t("ID")."'><input type=text size=3 style='width:6em;' value='$id' name='id'></td>";
  echo "\n<td title='".t("Internal ID")."'><input type=text size=3 style='width:6em;' value='$internalid' name='internalid'></td>";
  echo "\n<td>\n<select name='itemtypeid'>\n<option value=''>".t("All")."</option>\n";
  foreach ($itypes as $itype) {
    $dbid=$itype['id'];
    $itype=$itype['typedesc'];
    $s="";
    if (isset($_GET['itemtypeid']) && $_GET['itemtypeid']=="$dbid") $s=" SELECTED ";
    //echo "<option $s value='$dbid'>".sprintf("%02d",$dbid)."-$itype</option>\n";
    echo "<option $s value='$dbid' title='$dbid'>$itype</option>\n";
  }
  echo "</select>\n</td>\n";

  echo "<td title='".t("H/W Manufacturer")."'><input style='width:8em' type=text value='$manufacturer' name='manufacturer'></td>";
  echo "<td><input style='width:11em' type=text value='$model' name='model'></td>";
  echo "<td><input style='width:8em' type=text value='$sn' name='sn'></td>";
  echo "<td><input style='width:7em' type=text value='$label' name='label'></td>";
  echo "<td><input style='width:7em' type=text value='$year' name='year'></td>";
  echo "<td>-</td>";
  echo "<td><input style='width:7em' type=text value='$custom_user' name='custom_user'></td>";
  echo "<td><input style='width:7em' type=text value='$custom_dept' name='custom_dept'></td>";
  ?>
  <td>

  <select name='status'>
  <option $s value=''><?php echo te("All"); ?></option>

  <?php 
  for ($i=0;$i<count($statustypes);$i++) {
    $dbid=$statustypes[$i]['id']; $itype=$statustypes[$i]['statusdesc']; $s="";
    if ($status==$dbid) $s=" SELECTED ";
    echo "<option $s value='$dbid'>$itype</option>\n";
  }
  ?>
  </select>
  </td>

  <td><select name='locationid'>
      <option value=''><?php echo te("All"); ?></option>
<?php 
  foreach ($locations as $l) {
    $dbid=$l['id']; 
    $s="";

    $itype=$l['name']." Flr ".$l['floor'];
    if (isset($_GET['locationid']) && $_GET['locationid']=="$dbid") $s=" SELECTED ";
    echo "<option $s value='$dbid' title='$dbid'>$itype</option>\n";
  }
  echo "</select>\n";
  echo "</td>";

  if ($expand) {
    echo "<td>&nbsp;</td>";
    echo "<td>&nbsp;</td>";
    echo "<td colspan='10'>&nbsp;</td>\n";
  }
  else {
    $url=http_build_query($_GET);
    echo "<td style='vertical-align:top;' rowspan=".($perpage+1).">".
         "<a alt='".t("More")."' title='".t("Show more Columns")."' href='$fscriptname?$url&amp;expand=1'>".
         "<img src='images/more.png'></a></td>";
  }
  echo "</tr>\n\n";

}//if not export to excel: searchboxes

/// create WHERE clause
$where=" AND agents.title like '%$manufacturer%' and model like '%$model%' ".
       " AND (sn like '%$sn%' or sn2 like  '%$sn%'  or sn3 like  '%$sn%' )  AND dnsname like '%$dnsname%' ";
if (strlen($year)) {
  if (strstr($year,"/")) {
    $x=explode("/",$year);
    $y=$x[count($x)-1];

  if ($dateformat=="dmy") 
    $m=$x[count($x)-2];
  else
    $m=$x[count($x)-3];

    $where.=" and purchasedate >= ".mktime(0,0,0,$m,1,$y).
            " and purchasedate < ".mktime(0,0,0,($m+1),1,$y)." ";
  }
  else {
   $where.=" and purchasedate >= ".mktime(0,0,0,1,1,$year).
           " and purchasedate < ".mktime(0,0,0,1,1,($year+1))." ";
  }
}

if (strlen($id)) $where.=" AND items.id = '$id' ";
if (strlen($status)) $where.=" AND items.status = '$status' ";

//itemtypeid here:
if (isset($internalid) && strlen($internalid)) $where.=" AND internalid like '%$internalid%' ";
if (isset($itemtypeid) && strlen($itemtypeid)) $where.=" AND itemtypeid=$itemtypeid ";
if (isset($custom_user) && strlen($custom_user)) $where.=" AND employees.name LIKE '%$custom_user%' ";
if (isset($custom_dept) && strlen($custom_dept)) $where.=" AND departments.name LIKE '%$custom_dept%' ";
if (isset($locationid) && strlen($locationid)) $where.=" AND locationid='$locationid' ";
if (isset($rackid) && strlen($rackid)) $where.=" AND rackid=$rackid ";
if (isset($label) && strlen($label)) $where.=" AND label like '%$label%' ";

//calculate total returned rows
	$sth=db_execute($dbh,"
	    SELECT count(items.id) as totalrows 
	    FROM items
	    JOIN agents ON agents.id=items.manufacturerid
	    LEFT JOIN employees ON items.custom_user = employees.id
	    LEFT JOIN departments ON items.custom_dept = departments.id
	    WHERE 1=1 $where
	");

$totalrows=$sth->fetchColumn();

//page links
$get2=$_GET;
unset($get2['page']);
$url=http_build_query($get2);

for ($plinks="",$pc=1;$pc<=ceil($totalrows/$perpage);$pc++) {
 if ($pc==$page)
   $plinks.="<b><u><a href='$fscriptname?$url&amp;page=$pc'>$pc</a></u></b> ";
 else
   $plinks.="<a href='$fscriptname?$url&amp;page=$pc'>$pc</a> ";
}
$plinks.="<a href='$fscriptname?$url&amp;page=all'>[".t("show all")."]</a> ";

$t=time();
	$sql="SELECT 
	    items.*,
	    agents.title AS agtitle,
	    COALESCE(employees.name, '') AS user_name,
	    COALESCE(departments.name, '') AS dept_name,
	    (purchasedate+warrantymonths*30*24*60*60-$t)/(60*60*24) AS remdays 
	FROM items
	JOIN agents ON agents.id=items.manufacturerid
	LEFT JOIN employees ON items.custom_user = employees.id
	LEFT JOIN departments ON items.custom_dept = departments.id
	WHERE 1=1
	$where 
	order by $orderby LIMIT $perpage OFFSET ".($perpage*($page-1));
//echo $sql;
/// make db query
$sth=db_execute($dbh,$sql);

/// display results
$currow=0;
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
$currow++;

  $sqlinv="SELECT invoices.* from invoices,item2inv where item2inv.itemid={$r['id']} AND invoices.id=item2inv.invid";
  $sthinv=db_execute($dbh,$sqlinv);
  $invoices=$sthinv->fetchAll(PDO::FETCH_ASSOC);

  $sqlsoft="SELECT software.*, software.id as softwareid , invoices.* FROM software,item2soft,invoices WHERE ".
           " item2soft.itemid={$r['id']} ".
	   " AND software.id=item2soft.softid ".
	   " AND invoices.id=software.invoiceid ";
  $sthsoft=db_execute($dbh,$sqlsoft);
  $software=$sthsoft->fetchAll(PDO::FETCH_ASSOC);

  //2seconds
	// 读取设置
	$sth_setting = $dbh->query("SELECT dateformat,timeformat FROM settings LIMIT 1");
	$settings = $sth_setting->fetch(PDO::FETCH_ASSOC);
	// 格式化采购日期（只显示日期）
	$d = !empty($r['purchasedate']) ? format_date($r['purchasedate'], $settings, true, false) : "-";
	


  if (isset($locations[$r['locationid']])) {
    $i=$r['locationid'];
    $loc=$locations[$i]['name'].",Flr:".$locations[$i]['floor'];
  }
  else $loc="";

  if (isset($racks[$r['rackid']])) {
    $i=$r['rackid'];
    $rack=$racks[$i]['label']." ".$racks[$i]['model']." ".$racks[$i]['usize']."U";
  }
  else $rack="";

  //invoice links
  $invinfo="";
  if (isset($invoices[0]['id'])) {
    $cinv=count($invoices);
    for ($ninv=0;$ninv<$cinv;$ninv++) {
      $dinv=strlen($invoices[$ninv]['date'])?date($dateparam,$invoices[$ninv]['date']):"";
      $invinfo.="<b>&bull;</b>";
      $invinfo.="<a title='".t("View Invoice")."' href='$fscriptname?action=editinvoice&amp;id={$invoices[$ninv]['id']}'>".
                  "{$invoices[$ninv]['number']}</a>";
      //else $invinfo.="{$invoices[$ninv]['number']}";
      $invinfo.=" {$agents[$invoices[$ninv]['vendorid']]['title']}<span style='font-family:serif'>&rarr;</span>"; //sans-serif arrows suck for somereason
      $invinfo.="{$agents[$invoices[$ninv]['buyerid']]['title']} $dinv";
      if ($ninv<($cinv-1)) $invinfo.= "<br>";
    }
  }

	 //software links
	 $softinfo="<table border=1 class=brdr style='margin:0'>";
	  if (isset($software[0]['softwareid'])) {
	    $csof=count($software);
	    for ($nsof=0;$nsof<$csof;$nsof++) {
	      $softinfo.="\n<tr><td style='width:150px'><b>".($nsof+1)."</b>-";
	
	      $softinfo.="<a title='".t("Edit Software")."' href='$fscriptname?action=editsoftware&amp;id={$software[$nsof]['softwareid']}'>".
			"{$software[$nsof]['scompany']}&nbsp;{$software[$nsof]['stitle']}&nbsp;{$software[$nsof]['sversion']}</a></td>";
	      $invflink="<td style='width:20%'><a title='".t("Edit Invoice")."' href='$fscriptname?action=editinvoice&amp;id={$software[$nsof]['invoiceid']}'>".
	                "{$software[$nsof]['number']}</a></td>";
	      $dinv=strlen($software[$nsof]['date'])?date($dateparam,$software[$nsof]['date']):"";
	      $softinfo.="<td> $invflink</td>";
	      $softinfo.="<td>{$agents[$software[$nsof]['vendorid']]['title']}</td>";
	      $softinfo.="<td>{$agents[$software[$nsof]['buyerid']]['title']}</td><td>$dinv</td></tr>";
	      //if ($nsof<($csof-1)) $softinfo.= "<br>";
	    }
	  }
	  $softinfo.="</table>\n";
	  
  $remdays=$r['remdays'];
//  if (abs($remdays)>360) $remw=sprintf("%.1f",($remdays/360)).t("yr");
//  else if (abs($remdays)>70) $remw=sprintf("%.1f",($remdays/30)).t("mon");
//  else if (strlen($remdays)) $remw="$remdays".t("d");
//  else $remw="";
  

//  if ($remdays<0) $remw="<span style='color:#F90000'>$remw</span>";
//  if ($remdays>0) $remw="<span style='color:green'>$remw</span>";
//  if (abs($remdays)>360*20) $remw="";

  $remw=showremdays($remdays);

  $x=attrofstatus((int)$r['status'],$dbh);
  $attr=$x[0];
  $statustxt=$x[1];
	// PDO 获取状态背景色
	$sth_status = $dbh->prepare("SELECT color FROM statustypes WHERE id=?");
	$sth_status->execute([(int)$r['status']]);
	$status_color = $sth_status->fetchColumn();
	
	$text_color = getAutoTextColor($status_color);

//table row - 强制使用 CSS 控制颜色，移除 PHP 奇偶判断
echo "\n<tr class='item-row'>". "<td nowrap><span $attr>&nbsp;</span><a class='editid' title='".t("Edit")."' href='$fscriptname?action=edititem&amp;id=".$r['id']."'>". $r['id']."</a>";

  $sn="";
  if (strlen($r['sn'])) $sn.=$r['sn'];
  if (strlen($r['sn2'])) {if (strlen($sn)) $sn.=", ";} $sn.=$r['sn2'];
  if (strlen($r['sn3'])) {if (strlen($sn)) $sn.=", ";} $sn.=$r['sn3'];

  echo "</td>".
  	   "\n <td class='monospaced'>".$r['internalid']."</td>".
       "\n  <td>".$itypes[$r['itemtypeid']]['typedesc']."</td>".
       "\n  <td>".$r['agtitle']."&nbsp;</td>".
       "\n  <td>".$r['model']."</td>".
       "\n  <td class='monospaced'>$sn&nbsp;</td>".
       "\n  <td>{$r['label']}</td>".
       "\n  <td>$d&nbsp;</td>".
       "\n  <td>$remw</td>".
		"\n  <td>".$r['user_name']."</td>".
		"\n  <td>".$r['dept_name']."</td>".
       "\n  <td style=\"background-color:{$status_color}; color:{$text_color}; padding:2px 4px; font-weight:bold; border-radius:2px;\">$statustxt</td>".
       "\n  <td>$loc</td>";


  if ($expand) { //display more columns
    $ispart=$r['ispart']==1?"Y":"N";
    $rackmountable=$r['rackmountable']==1?"Y":"N";

    if (is_numeric($r['switchid'])) {
      $sqlsw="SELECT label,model, agents.title as agtitle from items,agents where agents.id=items.manufacturerid AND items.id={$r['switchid']}";
      $sthsw=db_execute($dbh,$sqlsw);
      $sw=$sthsw->fetchAll(PDO::FETCH_ASSOC);
      $sw=$sw[0];
      $switch=$r['switchid']."-".$sw['label'].", ".$sw['agtitle']." ".$sw['model'];
    }
    else 
      $switch="";
    $ports="";
    if ($r['ports']) $ports=$r['ports'];

    echo "\n<!-- expanded columns -->\n";
    echo "\n  <td><small>". showtags("item",$r['id'],0). "</small></td>";
    echo "\n  <td>".$r['dnsname']."</td>";
    echo "\n  <td>".$rack."</td>";
    echo "\n  <td><small>$invinfo</small></td>".
	 "\n  <td><small>$softinfo</small></td>";
    echo "\n  <td>".$r['purchprice']."</td>";
    echo "\n  <td>".$r['macs']."</td>";
    echo "\n  <td>".$r['ipv4']."</td>";
    echo "\n  <td>".$r['ipv6']."</td>";
    echo "\n  <td>".$r['remadmip']."</td>";
    echo "\n  <td>".$r['panelport']."</td>";
    echo "\n  <td>$switch</td>";
    echo "\n  <td>".$r['switchport']."</td>";
    echo "\n  <td>$ports</td>";
  }//expand

  echo "\n</tr>\n";
}
$sth->closeCursor();

if ($export) {
  echo "</tbody>\n</table>\n";
  exit;
}
else {
  if ($expand) 
    $cs=28;
  else 
    $cs=15;

?>
  <tr><td colspan='<?php echo $cs?>' class=tdc><button type=submit><img src='images/search.png'><?php echo te("Search"); ?></button></td></tr>
  </tbody></table>
  <input type='hidden' name='action' value='<?php echo $_GET['action']?>'>
  </form>

<div class='gray'>
  <b><?php echo $totalrows?> <?php echo te("Results"); ?></b><br>
  <b><?php echo te("Page"); ?>:</b> <?php echo $plinks?> <br>
  <a href='<?php echo "$fscriptname?action=$action&amp;export=1"?>'><img src='images/xcel2.jpg' height=25 border=0><?php echo te("Export to Excel"); ?></a>
</div>

<?php 
}

if ($export) {
  echo "\n</body>\n</html>\n";
  exit;
}

?>
