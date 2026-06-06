<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

$disperr = '';
$warr = '';
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
/* Spiros Ioannou 2009 , sivann _at_ gmail.com */

// ===================== 机架式=否 → 强制清空位置字段（最顶部优先执行） =====================
if (isset($_POST['rackmountable']) && $_POST['rackmountable'] === '0') {
    $_POST['locationid']    = '';
    $_POST['locareaid']     = '';
    $_POST['rackid']        = '';
    $_POST['rackposition']  = '';
    $_POST['rackposdepth']  = '';
    $_POST['usize']         = ''; // 清空大小
}

$formvars=array(
    "itemtypeid","function","manufacturerid","label",
    "warrinfo","model","sn","sn2","sn3","locationid","locareaid",
    "origin","warrantymonths","purchasedate","purchprice","dnsname","userid","custom_user","custom_dept",
    "comments","maintenanceinfo","ispart","hd",
    "cpu","cpuno","corespercpu", "ram", "rackmountable", "rackid","rackposition","rackposdepth","usize","status",
    "macs","ipv4","ipv6","remadmip","panelport","switchid","switchport","ports","internalid"
);

/* delete item */
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    $f=itemid2files($delid,$dbh);
    $fids=array();
    for ($c=0;$c<count($f);$c++) {
        array_push($fids,$f[$c]['id']);
    }
    $sql="DELETE from item2file where itemid=$delid";
    $sth=db_exec($dbh,$sql);
    for ($c=0;$c<count($fids);$c++) {
        $nlinks=countfileidlinks($fids[$c],$dbh);
        if ($nlinks==0) delfile($fids[$c],$dbh);
    }
    $sql="DELETE from item2inv where itemid=$delid";
    $sth=db_exec($dbh,$sql);
    $sql="DELETE from item2soft where itemid=$delid";
    $sth=db_exec($dbh,$sql);
    $sql="DELETE from itemlink where itemid1=$delid or itemid2=$delid";
    $sth=db_exec($dbh,$sql);
    $sql="UPDATE tag2item set itemid=null where itemid=$delid";
    $sth=db_exec($dbh,$sql);
    $sql="DELETE from items where id=$delid";
    $sth=db_exec($dbh,$sql);
    addOperateLog(
        'item',
        'delete',
        'Deleted item ID %s',
        array($delid),
        'item',
        $delid,
        null,
        null,
        1,
        ''
    );
    $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
    $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                   VALUES ($delid, ".time().", 'Deleted by $user', '', 1, ".time().")";
    db_exec($dbh, $sql_action);
    echo "<script>document.location='$scriptname?action=listitems'</script>";
    echo "<a href='$scriptname?action=listitems'>".t("Go here")."</a></body></html>";
    exit;
}

/* delete associated file */
if (isset($_GET['delfid'])) {
    $fileid = intval($_GET['delfid']);
    $itemid = intval($id);
    $sql="DELETE from item2file where itemid=$id AND fileid=$fileid";
    $sth=db_exec($dbh,$sql);
    addOperateLog(
        'file',
        'delete',
        'Deleted file %s from item %s',
        array($fileid, $itemid),
        'item',
        $itemid,
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

if (!isset($_GET['id'])) {echo t("edititem:missing arguments");exit;}

/* update item data */
if (isset($_POST['itemtypeid']) && $_GET['id']!="new" && isvalidfrm()) {
    $myid = intval($_GET['id']);
    $item_id = $myid;
    $set="";
    $curr_user = isset($_COOKIE['itdbuser']) ? trim($_COOKIE['itdbuser']) : '';
		if ($curr_user) {
		    $sth = $dbh->prepare("SELECT id FROM users WHERE username = ?");
		    $sth->execute(array($curr_user));
		    $u = $sth->fetch(PDO::FETCH_ASSOC);
		    $uid = $u ? intval($u['id']) : 9999;
		} else {
		    $uid = 9999;
		}
		$_POST['userid'] = $uid;

    foreach ($formvars as $formvar) {
        if (isset($_POST[$formvar])) {
            $$formvar = trim($_POST[$formvar]);
        } else {
            $$formvar = '';
        }
		// 机架式=否，强制清空
		if ($rackmountable === '0') {
		    $locationid   = '';
		    $locareaid    = '';
		    $rackid       = '';
		    $rackposition = '';
		    $rackposdepth = '';
		    $usize        = '';
		}
        if ($formvar == 'purchasedate') {
            $$formvar = ymd2sec($$formvar);
        }
        if ($formvar == 'maintend') {
            $$formvar = ymd2sec($$formvar);
        }
        if ($formvar == 'warrantymonths') {
            $$formvar = empty($$formvar) ? 'NULL' : intval($$formvar);
            $set .= "$formvar=".$$formvar.", ";
        } else {
            $val = htmlspecialchars($$formvar, 3, 'UTF-8');
            $set .= "$formvar='$val', ";
        }
    }
    $set = rtrim($set, ", ");
    $itlnk    = isset($_POST['itlnk'])    ? $_POST['itlnk']    : array();
    $invlnk   = isset($_POST['invlnk'])   ? $_POST['invlnk']   : array();
    $softlnk  = isset($_POST['softlnk'])  ? $_POST['softlnk']  : array();
    $contrlnk = isset($_POST['contrlnk']) ? $_POST['contrlnk'] : array();
    // 旧数据
    $sth_old = $dbh->query("SELECT * FROM items WHERE id=$myid");
    $old_data_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
    $old_item_info = array();
    foreach ($formvars as $k) {
        $old_item_info[$k] = isset($old_data_raw[$k]) ? $old_data_raw[$k] : '';
    }
    $old_itlnk = array();
    $sth = $dbh->query("SELECT itemid2 FROM itemlink WHERE itemid1=$myid");
    if ($sth) $old_itlnk = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
    $old_invlnk = array();
    $sth = $dbh->query("SELECT invid FROM item2inv WHERE itemid=$myid");
    if ($sth) $old_invlnk = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
    $old_softlnk = array();
    $sth = $dbh->query("SELECT softid FROM item2soft WHERE itemid=$myid");
    if ($sth) $old_softlnk = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
    $old_contrlnk = array();
    $sth = $dbh->query("SELECT contractid FROM contract2item WHERE itemid=$myid");
    if ($sth) $old_contrlnk = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
    $old_relation = array(
        'itlnk'    => $old_itlnk,
        'invlnk'   => $old_invlnk,
        'softlnk'  => $old_softlnk,
        'contrlnk' => $old_contrlnk
    );
    // 执行更新
    $sql = "UPDATE items SET $set WHERE id=$myid";
    db_exec($dbh, $sql);
    // 新数据
    $sth_new = $dbh->query("SELECT * FROM items WHERE id=$myid");
    $new_data_raw = $sth_new->fetch(PDO::FETCH_ASSOC);
    $new_item_info = array();
    foreach ($formvars as $k) {
        $new_item_info[$k] = isset($new_data_raw[$k]) ? $new_data_raw[$k] : '';
    }
	$itemtypeid = intval($_POST['itemtypeid']);
	$sth = $dbh->query("SELECT hassoftware FROM itemtypes WHERE id=$itemtypeid");
	$r = $sth->fetch(PDO::FETCH_ASSOC);
	$real_softlnk = ($r && $r['hassoftware'] == 1) ? $softlnk : array();
	
	$new_relation = array(
	    'itlnk'    => $itlnk,
	    'invlnk'   => $invlnk,
	    'softlnk'  => $real_softlnk,
	    'contrlnk' => $contrlnk
	);
	
$diff_old_item = array();
$diff_new_item = array();
foreach ($old_item_info as $key => $old_val) {
    $new_val = isset($new_item_info[$key]) ? $new_item_info[$key] : '';
    if ((string)$old_val !== (string)$new_val) {
        $diff_old_item[$key] = $old_val;
        $diff_new_item[$key] = $new_val;
    }
}
$relation_keys = array('itlnk', 'invlnk', 'softlnk', 'contrlnk');
$diff_old_rel = array();
$diff_new_rel = array();
foreach ($relation_keys as $k) {
    $old = isset($old_relation[$k]) ? $old_relation[$k] : array();
    $new = isset($new_relation[$k]) ? $new_relation[$k] : array();
    
    if (json_encode($old) !== json_encode($new)) {
        $diff_old_rel[$k] = $old;
        $diff_new_rel[$k] = $new;
    }
}
// 不生成空节点：和 contract 完全一致
$old_diff = array();
$new_diff = array();
if (!empty($diff_old_item)) {
    $old_diff['item_info'] = $diff_old_item;
    $new_diff['item_info'] = $diff_new_item;
}
if (!empty($diff_old_rel)) {
    $old_diff['relation'] = $diff_old_rel;
    $new_diff['relation'] = $diff_new_rel;
}
// ===================== 写入日志（原函数不动） =====================
addOperateLog(
    'item',
    'update',
    'Updated item ID %s',
    array($myid),
    'item',
    $myid,
    $old_diff,
    $new_diff,
    1,
    ''
);
    // 操作记录
    $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
    $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                   VALUES ($myid, ".time().", 'Updated by $user', '', 1, ".time().")";
    db_exec($dbh, $sql_action);
    // 更新关联
    $sql = "DELETE FROM itemlink WHERE itemid1=$myid";
    db_exec($dbh, $sql);
    foreach ($itlnk as $lid) {
        $lid = intval($lid);
        db_exec($dbh, "INSERT INTO itemlink (itemid1,itemid2) VALUES ($myid,$lid)");
    }
    $sql = "DELETE FROM item2inv WHERE itemid=$myid";
    db_exec($dbh, $sql);
    foreach ($invlnk as $iid) {
        $iid = intval($iid);
        db_exec($dbh, "INSERT INTO item2inv (itemid,invid) VALUES ($myid,$iid)");
    }
	// 检查是否支持软件
	$itemtypeid = intval($_POST['itemtypeid']);
	$sth = $dbh->query("SELECT hassoftware FROM itemtypes WHERE id=$itemtypeid");
	$r = $sth->fetch(PDO::FETCH_ASSOC);
	if ($r && $r['hassoftware'] == 1) {
	    $sql = "DELETE FROM item2soft WHERE itemid=$myid";
	    db_exec($dbh, $sql);
	    foreach ($softlnk as $sid) {
	        $sid = intval($sid);
	        db_exec($dbh, "INSERT INTO item2soft (itemid,softid) VALUES ($myid,$sid)");
	    }
	}
	
    $sql = "DELETE FROM contract2item WHERE itemid=$myid";
    db_exec($dbh, $sql);
    foreach ($contrlnk as $cid) {
        $cid = intval($cid);
        db_exec($dbh, "INSERT INTO contract2item (itemid,contractid) VALUES ($myid,$cid)");
    }
}

/* add new item */
elseif (isset($_POST['itemtypeid']) && $_GET['id']=="new" && isvalidfrm()) {
	$curr_user = isset($_COOKIE['itdbuser']) ? trim($_COOKIE['itdbuser']) : '';
	if ($curr_user) {
	    $sth = $dbh->prepare("SELECT id FROM users WHERE username = ?");
	    $sth->execute(array($curr_user));
	    $u = $sth->fetch(PDO::FETCH_ASSOC);
	    $uid = $u ? intval($u['id']) : 9999;
	} else {
	    $uid = 9999;
	}
	$_POST['userid'] = $uid;
	
    foreach($_POST as $k => $v) {
        if (!is_array($v)) {
            ${$k} = trim($v);
        }
    }
	// 机架式=否，强制清空
	if (isset($rackmountable) && $rackmountable === '0') {
	    $locationid   = '';
	    $locareaid    = '';
	    $rackid       = '';
	    $rackposition = '';
	    $rackposdepth = '';
	    $usize        = '';
	}
    $purchasedate2 = ymd2sec($purchasedate);
    $switchid       = empty($switchid)       ? 'NULL' : intval($switchid);
    $usize          = empty($usize)          ? 'NULL' : intval($usize);
    $locationid     = empty($locationid)     ? 'NULL' : intval($locationid);
    $locareaid      = empty($locareaid)      ? 'NULL' : intval($locareaid);
    $rackid         = empty($rackid)         ? 'NULL' : intval($rackid);
    $rackposition   = empty($rackposition)   ? 'NULL' : intval($rackposition);
    $warrantymonths = empty($warrantymonths) ? 'NULL' : intval($warrantymonths);
    $itlnk    = isset($_POST['itlnk'])    ? $_POST['itlnk']    : array();
    $invlnk   = isset($_POST['invlnk'])   ? $_POST['invlnk']   : array();
    $softlnk  = isset($_POST['softlnk'])  ? $_POST['softlnk']  : array();
    $contrlnk = isset($_POST['contrlnk']) ? $_POST['contrlnk'] : array();
    $sql="INSERT INTO items (label,itemtypeid,function,manufacturerid,
    warrinfo,model,sn,sn2,sn3,origin,warrantymonths,purchasedate,purchprice,
    dnsname,userid,custom_user,custom_dept,locationid,locareaid,maintenanceinfo,
    comments,ispart,rackid,rackposition,rackposdepth,rackmountable,
    usize,status,macs,ipv4,ipv6,remadmip,
    hd,cpu,cpuno,corespercpu,ram,panelport,switchid,switchport,ports,internalid)
    VALUES ('$label','$itemtypeid','$function','$manufacturerid',
    '$warrinfo','$model','$sn','$sn2','$sn3','$origin',$warrantymonths,'$purchasedate2','$purchprice',
    '$dnsname',".intval($_POST['userid']).",'$custom_user','$custom_dept',$locationid,$locareaid,'$maintenanceinfo',
    '".htmlspecialchars($comments,ENT_QUOTES,'UTF-8')."','$ispart',
	".($rackid?$rackid:'NULL').",
	".($rackposition?$rackposition:'NULL').",
	".($rackposdepth?$rackposdepth:'NULL').",
	'$rackmountable',
    $usize,'$status','$macs','$ipv4','$ipv6','$remadmip',
    '$hd','$cpu','$cpuno','$corespercpu','$ram','$panelport',$switchid,'$switchport','$ports','$internalid')";
    db_exec($dbh,$sql);
    $lastid = $dbh->lastInsertId();
    $id = $lastid;
    // 新数据日志（含关联）
    $new_item_info = array();
    foreach ($formvars as $k) {
        $new_item_info[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : '';
    }
	// 不支持软件则清空 softlnk，日志与库一致
	$itemtypeid = intval($_POST['itemtypeid']);
	$sth = $dbh->query("SELECT hassoftware FROM itemtypes WHERE id=$itemtypeid");
	$r = $sth->fetch(PDO::FETCH_ASSOC);
	$real_softlnk = ($r && $r['hassoftware'] == 1) ? $softlnk : array();
	
	$new_relation = array(
	    'itlnk'    => $itlnk,
	    'invlnk'   => $invlnk,
	    'softlnk'  => $real_softlnk,
	    'contrlnk' => $contrlnk
	);
    $new_log_data = array(
        'item_info' => $new_item_info,
        'relation'  => $new_relation
    );
    addOperateLog(
        'item',
        'add',
        'Created new item ID %s',
        array($lastid),
        'item',
        $lastid,
        null,
        $new_log_data,
        1,
        ''
    );
    // 自动写入action表 ↓ 不记日志
    $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
    $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                   VALUES ($lastid, ".time().", 'Added by $user', '', 1, ".time().")";
    db_exec($dbh, $sql_action);
    // 新增关联
    foreach ($itlnk as $lid) {
        $lid = intval($lid);
        db_exec($dbh,"INSERT INTO itemlink (itemid1,itemid2) VALUES ($lastid,$lid)");
    }
    foreach ($invlnk as $iid) {
        $iid = intval($iid);
        db_exec($dbh,"INSERT INTO item2inv (itemid,invid) VALUES ($lastid,$iid)");
    }
	// 检查是否支持软件
	$itemtypeid = intval($_POST['itemtypeid']);
	$sth = $dbh->query("SELECT hassoftware FROM itemtypes WHERE id=$itemtypeid");
	$r = $sth->fetch(PDO::FETCH_ASSOC);
	if ($r && $r['hassoftware'] == 1) {
	    foreach ($softlnk as $sid) {
	        $sid = intval($sid);
	        db_exec($dbh,"INSERT INTO item2soft (itemid,softid) VALUES ($lastid,$sid)");
	    }
	}
    foreach ($contrlnk as $cid) {
        $cid = intval($cid);
        db_exec($dbh,"INSERT INTO contract2item (itemid,contractid) VALUES ($lastid,$cid)");
    }
	echo "<script>window.location='$scriptname?action=edititem&id=$lastid'</script>";

}

// ===================== 机架式=否 → 强制清空位置字段 =====================
if (isset($_POST['rackmountable']) && $_POST['rackmountable'] == '0') {
    $_POST['locationid']    = '';
    $_POST['locareaid']     = '';
    $_POST['rackid']        = '';
    $_POST['rackposition']  = '';
    $_POST['rackposdepth']  = '';
}

// ===================== 内部ID+序列号校验（最终完美版·全国际化）=====================
function isvalidfrm() {
    global $dbh, $disperr, $err, $_POST, $scriptname;
    $err = "";

    // 内部ID重复校验
    $internalid = trim($_POST['internalid']);
    if (!empty($internalid)) {
        $myid = $_GET['id'];
        $safe_iid = $dbh->quote($internalid);
        $sql = "SELECT id FROM items WHERE internalid = $safe_iid";
        if ($myid !== 'new' && is_numeric($myid)) {
            $sql .= " AND id != " . intval($myid);
        }
        $sth = db_execute($dbh, $sql);
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && !empty($row)) {
            $err .= t("Internal ID already exists") . "<br>";
        }
    }

    // 必填项
    if ($_POST['itemtypeid'] == "") $err .= t("Missing Item Type") . "<br>";
    if ($_POST['manufacturerid'] == "") $err .= t("Missing manufacturer") . "<br>";
    if (!isset($_POST['rackmountable'])) $err .= t("Missing Rackmountable") . "<br>";
    if (!isset($_POST['ispart'])) $err .= t("Missing Part") . "<br>";
    if (!isset($_POST['status'])) $err .= t("Missing Status") . "<br>";
    if ($_POST['model'] == "") $err .= t("Missing model") . "<br>";

    // SN 重复校验
    $myid    = $_GET['id'];
    $sn      = trim($_POST['sn']);
    $sn2     = trim($_POST['sn2']);
    $sn3     = trim($_POST['sn3']);
    $all_sn  = array_filter(array($sn, $sn2, $sn3));

    // 自身SN重复
    $check_self = array();
    if (!empty($sn)) $check_self[] = $sn;
    if (!empty($sn2)) $check_self[] = $sn2;
    if (!empty($sn3)) $check_self[] = $sn3;
    if (count($check_self) != count(array_unique($check_self))) {
        $err .= t("Duplicate serial numbers within the same item") . "<br>";
    }

    // 库内SN重复
    if (!empty($all_sn)) {
        $cond = array();
        foreach ($all_sn as $s) {
            $q = $dbh->quote($s);
            $cond[] = "sn = $q";
            $cond[] = "sn2 = $q";
            $cond[] = "sn3 = $q";
        }
        $sql = "SELECT id FROM items WHERE (" . implode(" OR ", $cond) . ")";
        if ($myid !== "new" && is_numeric($myid)) {
            $sql .= " AND id != " . intval($myid);
        }
        $sql .= " LIMIT 1";
        $sth = db_execute($dbh, $sql);
        $dup = $sth->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            $err .= t("Duplicate SN with item ID") . " " . $dup['id'] . "<br>";
        }
    }

    // 关键：有错误就赋值并返回0
    if ($err) {
        $disperr = "<div class='ui-state-error ui-corner-all' style='padding:8px; margin:10px 0;'>
            <p><span class='ui-icon ui-icon-alert' style='float:left; margin-right:5px;'></span>
            <strong>" . t("Error: Item not saved") . "</strong><br>$err</p></div>";
        return 0;
    }

    return 1;
}
echo $disperr; // 输出错误提示（关键修复）
require('itemform.php');
?>
