<?php
/********************************* DB QUERIES IN FUNCTION FORM ****************************************/
function t($s) {
  global $trans_tbl,$settings,$trans_showmissing,$scriptdir;
  $lang=$settings['lang'];

  if ($lang=="en") return $s;
 
  if (isset($trans_tbl[$lang][$s]) && strlen(trim($trans_tbl[$lang][$s]))) {
    return $trans_tbl[$lang][$s];
  }
  else {
    if ($trans_showmissing) {
      $fn="$scriptdir/translations/{$lang}.missing.txt";
      file_put_contents($fn,$s."\n",FILE_APPEND);
    }
    return $s;
  }

}

function te($s) {
  echo t($s);
}

function read_trans($lang) {
  global $trans_tbl,$scriptdir;
  $row = 0;
  if ($lang=="en") return;
  $fn="$scriptdir/translations/$lang.txt";
  if (is_readable($fn) && (($handle = fopen($fn, "r")) !== FALSE)) {
      while ($line=trim(fgets($handle))) {
        if($pos=strpos($line,'#')){
          $key=substr($line,0,$pos);
          $value=substr($line, $pos+1);
          $trans_tbl[$lang][$key]=$value;
        }
      }
      fclose($handle);
  }
  else {
    echo "<p style='display:inline;background-color:white;color:red'> Error openning $fn <br /></p>\n";
  }
}

// --- START: Random Password Generator for DB (PHP 5.2 Compatible) ---
function generateRandomDBPassword() {
    $length = 20;
    // 定义字符池：数字 + 大写字母 + 小写字母 + 特殊字符
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!@#$%^&*()_+-=[]{}|;:,.<>?';
    $charLength = strlen($chars);
    $password = '';
    
    // PHP 5.2 使用 mt_rand 作为最佳随机数生成方案
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[mt_rand(0, $charLength - 1)];
    }
    
    return $password;
}
// --- END: Random Password Generator ---


function getstatusidofitem($itemid,$dbh)
{
  $sql="SELECT status FROM items WHERE items.id='$itemid'";
  $sth=db_execute($dbh,$sql);
  $statusid=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $statusid=$statusid['status'];
  return $statusid;

}

/* return css and title based on item status */
function attrofstatus($statusid,$dbh)
{
	// 查询数据库获取颜色和描述
	$sql = "SELECT statusdesc, color FROM statustypes WHERE id = '$statusid'";
	$sth = db_execute($dbh, $sql);
	$r = $sth->fetch(PDO::FETCH_ASSOC);
	$sth->closeCursor();
	
	// 设置默认值（防止数据库为空）
	$statusdesc = $r['statusdesc'];
	$color = $r['color'];
	if (empty($color)) $color = "#CCCCCC"; // 默认灰色
	
	// 生成内联样式属性（固定宽度高度，显示为方块）
	$attr = " style='background-color: $color !important; forced-color-adjust: none; -webkit-appearance: none; float: left; width: 6px; height: 12px; border: 1px solid white; display: inline-block; vertical-align: middle;' title='".t("Status").": $statusdesc'";

	$statustxt = $statusdesc;

  return array($attr,$statustxt);

}


function ftype2str($typeid,$dbh) {

  $sql="SELECT typedesc from filetypes WHERE ".
        " id='$typeid'";
  $sth=db_execute($dbh,$sql);
  $typestr=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $typestr=$typestr['typedesc'];

  return ucfirst($typestr);

}
//returns files array from invoice id
function invid2files($invid,$dbh) {
  $sql="SELECT files.* from files,invoice2file WHERE ".
        " invoice2file.invoiceid='$invid' AND ".
        " invoice2file.fileid=files.id";
  $sth=db_execute($dbh,$sql);
  $fn=$sth->fetchAll(PDO::FETCH_ASSOC);
  return $fn;
}

//returns files array from software id
function softid2files($softid,$dbh) {
  $sql="SELECT files.* from files,software2file WHERE ".
        " software2file.softwareid='$softid' AND ".
        " software2file.fileid=files.id";
  $sth=db_execute($dbh,$sql);
  $fn=$sth->fetchAll(PDO::FETCH_ASSOC);
  return $fn;
}

function softid2invoicefiles($softid,$dbh) {
  $sql="SELECT files.* from files,invoice2file,soft2inv WHERE ".
      " soft2inv.softid='$softid' AND ".
      " invoice2file.invoiceid=soft2inv.invid AND ".
      " invoice2file.fileid=files.id";
  $sthi=db_execute($dbh,$sql);
  $f=$sthi->fetchAll(PDO::FETCH_ASSOC);
  return $f;
}


function softid2contractfiles($softid,$dbh) {
    $sql="SELECT files.* from files,contract2file,contract2soft WHERE ".
        " contract2soft.softid='$softid' AND ".
        " contract2file.contractid=contract2soft.contractid AND ".
        " contract2file.fileid=files.id";
    $sthi=db_execute($dbh,$sql);
    $f=$sthi->fetchAll(PDO::FETCH_ASSOC);
    return $f;
}




//returns files array from item id
function itemid2files($itemid,$dbh) {
  $sql="SELECT files.* from files,item2file WHERE ".
        " item2file.itemid='$itemid' AND ".
        " item2file.fileid=files.id";
  $sth=db_execute($dbh,$sql);
  $fn=$sth->fetchAll(PDO::FETCH_ASSOC);
  return $fn;
}

function itemid2invoicefiles($itemid,$dbh) {
    $sql="SELECT files.* from files,invoice2file,item2inv WHERE ".
        " item2inv.itemid='$itemid' AND ".
        " invoice2file.invoiceid=item2inv.invid AND ".
        " invoice2file.fileid=files.id";
    $sthi=db_execute($dbh,$sql);
    $f=$sthi->fetchAll(PDO::FETCH_ASSOC);
    return $f;
}

function itemid2contractfiles($itemid,$dbh) {
    $sql="SELECT files.* from files,contract2file,contract2item WHERE ".
        " contract2item.itemid='$itemid' AND ".
        " contract2file.contractid=contract2item.contractid AND ".
        " contract2file.fileid=files.id";
    $sthi=db_execute($dbh,$sql);
    $f=$sthi->fetchAll(PDO::FETCH_ASSOC);
    return $f;
}



//returns files array from contract id
function contractid2files($contractid,$dbh) {
  $sql="SELECT files.* from files,contract2file WHERE ".
        " contract2file.contractid='$contractid' AND ".
        " contract2file.fileid=files.id";
  $sth=db_execute($dbh,$sql);
  $fn=$sth->fetchAll(PDO::FETCH_ASSOC);
  return $fn;
}



//returns number of connected items/racks with a locationid
function countloclinks($locid,$dbh) {
  $sql="SELECT count(id) count from items where locationid=$locid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  $sql="SELECT count(id) count from racks where locationid=$locid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  return $count;
}

//returns number of connected items/racks with a location areaid
function countlocarealinks($locareaid,$dbh) {
  $sql="SELECT count(id) count from items where locareaid=$locareaid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  $sql="SELECT count(id) count from racks where locareaid=$locareaid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  return $count;
}

function countfileidlinks($fileid,$dbh) {
  $count=0;

  $sql="SELECT count(softwareid) count from software2file where fileid=$fileid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  $sql="SELECT count(*) count from invoice2file where fileid=$fileid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  $sql="SELECT count(*) count from item2file where fileid=$fileid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  $sql="SELECT count(*) count from contract2file where fileid=$fileid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $count+=$r['count'];

  return $count;
}


function delfile($fileid,$dbh) {
global $uploaddir;
  //delete inter-item links
  $sql="SELECT fname from files where id=$fileid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $fname=$r['fname'];


  $sql="DELETE from files where id=$fileid";
  $sth=db_exec($dbh,$sql);

  $sql="DELETE from invoice2file where fileid=$fileid";
  $sth=db_exec($dbh,$sql);

  $sql="DELETE from software2file where fileid=$fileid";
  $sth=db_exec($dbh,$sql);

  $sql="DELETE from item2file where fileid=$fileid";
  $sth=db_exec($dbh,$sql);

  $sql="DELETE from contract2file where fileid=$fileid";
  $sth=db_exec($dbh,$sql);

  if (strlen($fname))
    unlink($uploaddir.$fname);
}

function countitemtags($tagid) {
  global $dbh;

  $sql="SELECT count(itemid) count from tag2item where tagid=$tagid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  return $r['count'];
}

function countsoftwaretags($tagid) {
  global $dbh;

  $sql="SELECT count(softwareid) count from tag2software where tagid=$tagid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  return $r['count'];
}



function tagid2name($id) {
  global $dbh;
  $sql="SELECT name from tags where id = '$id'";
  $sth=$dbh->query($sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $name=$r['name'];
  return $name;
}


function tagname2id($name) {
  global $dbh;

  $id="";
  $sql="SELECT id from tags where name = '$name'";
  $sth=$dbh->query($sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $id=$r['id'];

  return $id;

}

function showtags($type="item",$id,$lnk=1) {
  // id: item id
  global $dbh;

  $sql="SELECT name FROM tags,tag2$type WHERE tags.id=tag2$type.tagid AND tag2$type.{$type}id='$id' ORDER BY name";
  $sth = $dbh->query($sql);
  $tags=array();
  while ($r=$sth->fetch(PDO::FETCH_ASSOC)) array_push($tags,$r['name']);
  $sth->closeCursor();


  $ret= "\n<!-- showtags -->\n";
  $ret.= "\n  <ul class='tags'>\n";
  if (count($tags)) {
    for ($i=0;$i<count($tags)-1 ; $i++) {

      if ($lnk)
	$ret.= "    <li><a href=''>{$tags[$i]}</a></li>\n";
      else
	$ret.= "    <li>{$tags[$i]}</li>\n";
    }
    if ($lnk)
      $ret.= "    <li class='last'><a href=''>{$tags[$i]}</a></li>\n";
    else
      $ret.= "    <li class='last'>{$tags[$i]}</li>\n";
  }
  $ret.= "  </ul>\n  ";
  return $ret;
}


function countitemsinrack($rackid) {
  global $dbh;

  $sql="SELECT count(id) count from items where rackid=$rackid";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  return $r['count'];
}

function delrack($rackid,$dbh) {
  $sql="UPDATE items set rackid='' where rackid='$rackid'";
  $sth=db_exec($dbh,$sql);

  $sql="DELETE from racks where id='$rackid'";
  $sth=db_exec($dbh,$sql);

}

function deluser($userid,$dbh) {
  if ($userid==1) {
    echo "Cannot remove user with ID=1";
    return;
  }
  $sql="UPDATE items set userid=1 where userid='$userid'";
  $sth=db_exec($dbh,$sql);

  $sql="DELETE from users where id='$userid'";
  $sth=db_exec($dbh,$sql);

}

function showfiles($f,$class="fileslist",$wantdel=1,$divtitle='') {
// f: array with data from "files" table (from fetchall)
global $dateparam,$scriptname,$action,$id,$uploaddirwww,$dbh,$settings; //新增$settings

  $flnk="";
  for ($lnk="",$c=0;$c<count($f);$c++) {
   $fname=$f[$c]['fname'];
   $ftitle=$f[$c]['title'];
   $fid=$f[$c]['id'];
   $ftype=$f[$c]['type'];
   //原：$fdate=empty($f[$c]['date'])?"":date($dateparam,$f[$c]['date']);
   $fdate = (!empty($f[$c]['date']) && $f[$c]['date']>0) ? format_date($f[$c]['date'],$settings,true,false) : '';
   $ftypestr=ftype2str($ftype,$dbh);
   if (strlen($ftitle)) $t="<br>".t("Title").":$ftitle"; else $t="";

   $flnk.="<div title='$divtitle' class='$class' >";
   if ($wantdel) 
     $flnk.="<a title='".t("Remove association. If file is orphaned (nothing links to it), it gets deleted.")."' ".
	   "href='javascript:delconfirm2(\"[$fid] $fname\",\"$scriptname?action=$action&amp;id=$id&amp;delfid=$fid\");'>".
	 "<img src='images/delete.png'></a> ";
   $flnk.= "<a target=_blank title='".t("Edit File")." $fid' href='$scriptname?action=editfile&amp;id=$fid'><img  src='images/edit.png'></a>".
	 " <a target=_blank title='".t("Download")." $fname' href='".$uploaddirwww.$fname."'><img src='images/down.png'></a>".
	 "<br>".t("Type").":<b>$ftypestr</b>".
	 "<br>".t("Date").":<b>$fdate</b>".
	 "<br>".t("Title").":$ftitle\n".
	 "</div>\n ";
  }

  return $flnk;
}

function calcremdays($purchdate_ts,$warrantymonths) {
	if (!strlen($warrantymonths))
		return array('string'=>'','days'=>'');

	if (!is_numeric($purchdate_ts))
		return array('string'=>'','days'=>'');

	$nowdate = new DateTime();
	$pdate = new DateTime();
	$pdate->setTimestamp(intval($purchdate_ts));

	$d_interval=new DateInterval("P{$warrantymonths}M");
	$d_interval->invert=0;
	$enddate=$pdate->add($d_interval);
	
	
	$exp_interval = $nowdate->diff($enddate);

	// *** 关键修改：先获取翻译后的单位字符串 ***
	$unit_yr  = t('yr');  // 获取 "yr" 或 "年"
	$unit_mon = t('mon'); // 获取 "mon" 或 "月"
	$unit_d   = t('d');   // 获取 "d" 或 "天"
	
	if ($exp_interval->format('%y')) {
	    // 年、月、日都显示
	    $exp_interval_str = $exp_interval->format('%r %y ') . $unit_yr . $exp_interval->format(' %m ') . $unit_mon . $exp_interval->format(', %d ') . $unit_d;
	} else {
	    // 只显示月、日
	    $exp_interval_str = $exp_interval->format('%r %m ') . $unit_mon . $exp_interval->format(', %d ') . $unit_d;
	}
	
	$exp_interval_sign = $exp_interval->format('%r');
	$exp_interval_days = "$exp_interval_sign" . $exp_interval->days;
	
	if ($exp_interval_sign == "-") {
	    $exp_interval_str = "<span style='color:#F90000'>$exp_interval_str</span>";
	} else {
	    $exp_interval_str = "<span style='color:green'>$exp_interval_str</span>";
	}
	
	return array('string' => $exp_interval_str, 'days' => $exp_interval_days);
	


	//$exp_interval = $nowdate->diff($enddate);
	//if ($exp_interval->format('%y'))
	//	$exp_interval_str=$exp_interval->format('%r %y .t('yr'). %m .t('mon')., %d .t('d')');
	//else
	//	$exp_interval_str=$exp_interval->format('%r %m .t('mon')., %d .t('d')');

	//$exp_interval_sign=$exp_interval->format('%r');
	//$exp_interval_days="$exp_interval_sign".$exp_interval->days;

	//if ($exp_interval_sign=="-") 
	//	$exp_interval_str="<span style='color:#F90000'>$exp_interval_str</span>";
	//else
	//	$exp_interval_str="<span style='color:green'>$exp_interval_str</span>";

	//return array('string'=>$exp_interval_str,'days'=>$exp_interval_days);

}


//function showremdays($remdays) {
//  if (abs($remdays)>360) $remw=sprintf("%.1f",($remdays/360)).t("yr");
//  else if (abs($remdays)>70) $remw=sprintf("%.1f",($remdays/30)).t("mon");
//  else if (strlen($remdays)) $remw="$remdays".t("d");
//  else $remw="";


//  if ($remdays<0) $remw="<span style='color:#F90000'>$remw</span>";
//  if ($remdays>0) $remw="<span style='color:green'>$remw</span>";
//  if (abs($remdays)>360*20) $remw="";
  
//  return $remw;
//}

function showremdays($remdays) {
  // *** 关键修改：先获取翻译后的单位字符串 ***
  $unit_yr  = t('yr');  // 获取 "yr" 或 "年"
  $unit_mon = t('mon'); // 获取 "mon" 或 "月"
  $unit_d   = t('d');   // 获取 "d" 或 "天"
  
  if (abs($remdays) > 360) {
    $remw = sprintf("%.1f", ($remdays / 360)) . $unit_yr;
  } 
  else if (abs($remdays) > 70) {
    $remw = sprintf("%.1f", ($remdays / 30)) . $unit_mon;
  } 
  else if (strlen($remdays)) {
    $remw = "$remdays" . $unit_d; // 注意这里原来是 .("d")，缺少了 t 函数
  } 
  else {
    $remw = "";
  }

  if ($remdays < 0) {
    $remw = "<span style='color:#F90000'>$remw</span>";
  } 
  if ($remdays > 0) {
    $remw = "<span style='color:green'>$remw</span>";
  } 
  if (abs($remdays) > 360 * 20) {
    $remw = "";
  }
  
  return $remw;
}


/* return h/w manufacturers agent */
function getagenthwmanufacturers() {
  global $dbh;

  $sql="SELECT * from agents where type&4";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetchAll(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $agents=$r;

  return $r;
}


function gethwmanufacturerbyname ($name) {
  global $dbh;

	$name=trim(strtolower($name));
	if (!strlen($name))
		return -1;
	$sql="SELECT * from agents where type&8 AND lower(title) = '$name'";
	$sth=db_execute($dbh,$sql);
	$r=$sth->fetchAll(PDO::FETCH_ASSOC);
	$sth->closeCursor();

	if (!count($r[0]['id'])) 
		return -1;
	else 
		return $r;
}


function getuseridbyname ($name) {
  global $dbh;

	$name=trim(strtolower($name));
	if (!strlen($name))
		return -1;
	$sql="SELECT id from users where LOWER(username) ='$name' ";
	$sth=db_execute($dbh,$sql);
	$r=$sth->fetch(PDO::FETCH_ASSOC);
	$sth->closeCursor();

	if (!count($r['id'])) 
		return -1;
	else 
		return $r['id'];
}

function getagentidbyname ($name) {
  global $dbh;

	$name=trim(strtolower($name));
	if (!strlen($name))
		return -1;
	$sql="SELECT id from agents where LOWER(title) ='$name' ";
	$sth=db_execute($dbh,$sql);
	$r=$sth->fetch(PDO::FETCH_ASSOC);
	$sth->closeCursor();

	if (!count($r['id'])) 
		return -1;
	else 
        return $r['id'];
}

function getitemtypeidbyname ($name) {
  global $dbh;

	$name=trim(strtolower($name));
	if (!strlen($name))
		return -1;
	$sql="SELECT id from itemtypes where LOWER(typedesc) ='$name' ";
	$sth=db_execute($dbh,$sql);
	$r=$sth->fetch(PDO::FETCH_ASSOC);
	$sth->closeCursor();

	if (!count($r['id'])) 
		return -1;
	else 
		return $r['id'];
}


function getstatustypeidbyname ($name) {
  global $dbh;


	$name=trim(strtolower($name));
	if (!strlen($name))
		return -1;
	$sql="SELECT id from statustypes where LOWER(statusdesc) ='$name' ";
	$sth=db_execute($dbh,$sql);
	$r=$sth->fetch(PDO::FETCH_ASSOC);
	$sth->closeCursor();

	if (!count($r['id'])) 
		return -1;
	else 
		return $r['id'];
}

function getlocidsbynames ($locname,$areaname) {
  global $dbh;


	$locname=trim(strtolower($locname));
	$areaname=trim(strtolower($areaname));
	if (!strlen($locname))
		return array(-1,-1);

	//if (!strlen($locname) || (!strlen($areaname))) return array(-1,-1);

	if (strlen($areaname)) {
        $sql="SELECT locations.id as locid, locareas.id as locareaid from locations,locareas ".
        " WHERE locareas.locationid=locations.id AND ".
        " LOWER(locareas.areaname) =:areaname AND ".
        " LOWER(locations.name) =:locname";
        $sth=db_execute2($dbh,$sql,array('areaname'=>$areaname,'locname'=>$locname));
        $r=$sth->fetch(PDO::FETCH_BOTH);
        $sth->closeCursor();
        if (!count($r['locid'])) return array(-1,-1);
        else return $r;
    }
    else {
        $sql="SELECT locations.id as locid, locareas.id as locareaid from locations,locareas ".
        " WHERE LOWER(locations.name) =:locname";
        $sth=db_execute2($dbh,$sql,array('locname'=>$locname));
        $r=$sth->fetch(PDO::FETCH_BOTH);
        $sth->closeCursor();
        if (!count($r['locid'])) return array(-1,-1);
        else return array($r['locid'],-1);
    }

}


function getuserbyname ($name) {
  global $dbh;

	$name=trim(strtolower($name));
	$sql="SELECT * from users where lower(username) = :name ";
	$sth=db_execute2($dbh,$sql,array('name'=>$name));
	$r=$sth->fetchAll(PDO::FETCH_ASSOC);
	$sth->closeCursor();

	if (!count($r[0]['id']))  {
		return -1;
    }
	else  {
		return $r;
    }
}



/* return URL of an agent which has a specific $tag description. E.g. "Service Tag" */
function getagenturlbytag($agenturl,$tagstr) {
  global $dbh;

  $sql="SELECT urls from agents where id='$agenturl'";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $urls = isset($r['urls']) ? $r['urls'] : '';

  $allurls=explode("|",$urls);
  for ($i=0;$i<count($allurls);$i++) {
    $row=explode("#",$allurls[$i]);
    // 加 isset 判断，解决 Undefined offset: 1
    $description = isset($row[0]) ? $row[0] : '';
    $url         = isset($row[1]) ? urldecode($row[1]) : '';

    if (stristr($description, $tagstr)) {
        return $url;
    }
  }
  return "";
}



function dbversion() {
  global $dbh;

  $sql="SELECT * from settings";
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $sth->closeCursor();
  $ver=$r['dbversion'];
  return $ver;
}


?>
