<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-cache");

$initok = true;
require_once dirname(__FILE__).'/../init.php';

$sth_setting = $dbh->query("SELECT dateformat, companytitle FROM settings LIMIT 1");
$setting = $sth_setting->fetch(PDO::FETCH_ASSOC);
$sys_datefmt = !empty($setting['dateformat']) ? trim($setting['dateformat']) : 'ymd';
$companytitle = html(!empty($setting['companytitle']) ? $setting['companytitle'] : t('IT ITems DataBase'));

switch (strtolower($sys_datefmt)) {
    case 'mdy': $dateformat = 'm/d/Y'; break;
    case 'dmy': $dateformat = 'd/m/Y'; break;
    case 'ymd': 
    default:    $dateformat = 'Y-m-d'; break;
}

$itemid = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemid <= 0) {
    die('<div style="padding:30px;text-align:center;">'.t("Invalid asset ID").'</div>');
}

try {
    $sth = $dbh->prepare("SELECT i.id, i.label, i.itemtypeid, i.manufacturerid, i.model, i.sn, i.sn2, i.sn3, i.internalid, i.status, i.cpu, i.ram, i.hd, i.ipv4, i.ipv6, i.macs, i.custom_dept, i.custom_user, i.locationid, i.locareaid, i.rackid, i.rackposition, i.purchasedate, i.warrantymonths FROM items i WHERE i.id = ?");
    $sth->execute(array($itemid));
    $item = $sth->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("DB Error: ".$e->getMessage());
}

if (!$item) {
    die('<div style="padding:30px;text-align:center;">'.t("Item not found").'</div>');
}

extract($item);

$status_color = '#666';
$status_name = t("Unknown Status");
$sth_st = $dbh->prepare("SELECT statusdesc, color FROM statustypes WHERE id = ?");
$sth_st->execute(array($status));
if ($st = $sth_st->fetch(PDO::FETCH_ASSOC)) {
    $status_name = html($st['statusdesc']);
    $status_color = html($st['color'] ? $st['color'] : '#666');
}

$item_type = '-';
if ($itemtypeid) {
    $sth_type = $dbh->prepare("SELECT typedesc FROM itemtypes WHERE id = ?");
    $sth_type->execute(array($itemtypeid));
    $item_type = html($sth_type->fetchColumn() ?: '-');
}

$manufacturer = '-';
if ($manufacturerid) {
    $sth_a = $dbh->prepare("SELECT title FROM agents WHERE id = ?");
    $sth_a->execute(array($manufacturerid));
    $manufacturer = html($sth_a->fetchColumn() ?: '-');
}

$dept_name = '-';
if ($custom_dept) {
    $sth_d = $dbh->prepare("SELECT name FROM departments WHERE id = ?");
    $sth_d->execute(array($custom_dept));
    $dept_name = html($sth_d->fetchColumn() ?: '-');
}

$user_name = '-';
if ($custom_user) {
    $sth_e = $dbh->prepare("SELECT name FROM employees WHERE id = ?");
    $sth_e->execute(array($custom_user));
    $user_name = html($sth_e->fetchColumn() ?: '-');
}

$location_str = '-';
if ($locationid) {
    $sth_l = $dbh->prepare("SELECT name, floor FROM locations WHERE id = ?");
    $sth_l->execute(array($locationid));
    $loc = $sth_l->fetch(PDO::FETCH_ASSOC);
    if ($loc) {
        $location_str = html($loc['name']);
        if ($loc['floor']) {
            $location_str .= " - " . t("Floor") . ":{$loc['floor']}";
        }
    }
}
if ($locareaid) {
    $sth_la = $dbh->prepare("SELECT areaname FROM locareas WHERE id = ?");
    $sth_la->execute(array($locareaid));
    $area = $sth_la->fetchColumn();
    if ($area) {
        $location_str .= " - " . html($area);
    }
}

if ($rackid) {
    $sth_r = $dbh->prepare("SELECT label FROM racks WHERE id = ?");
    $sth_r->execute(array($rackid));
    $rack_label = html($sth_r->fetchColumn() ?: '');
    if ($rack_label && $rackposition) {
        $location_str .= " - " . $rack_label . " (U" . $rackposition . ")";
    } elseif ($rack_label) {
        $location_str .= " - " . $rack_label;
    } elseif ($rackposition) {
        $location_str .= " - U" . $rackposition;
    }
}

$warranty_str = '-';
if ($purchasedate && $warrantymonths > 0) {
    $expire = strtotime("+$warrantymonths months", $purchasedate);
    $warranty_str = $expire < time() ? "<font color=red>".date($dateformat, $expire)."</font>" : date($dateformat, $expire);
}

$sn_all = '';
if (!empty($sn)) $sn_all .= html($sn);
if (!empty($sn2)) $sn_all .= (!empty($sn_all) ? '<br>' : '') . html($sn2);
if (!empty($sn3)) $sn_all .= (!empty($sn_all) ? '<br>' : '') . html($sn3);
if (empty($sn_all)) $sn_all = '-';

function html($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title><?php echo t("Item Data");?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}
body{background:#f5f7fa;padding:10px;color:#333}
.card{background:#fff;border-radius:12px;margin-bottom:10px;overflow:hidden}
.card-header{padding:12px 14px;border-bottom:1px solid #eee;font-weight:bold}
.card-body{padding:10px 14px}
.row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f8f8f8;align-items:flex-start}
.row:last-child{border-bottom:0}
.label{color:#666}
.value{text-align:right;max-width:60%;white-space:pre-line}
.status{padding:4px 8px;border-radius:10px;color:#fff;background:<?php echo $status_color?>}
.title{text-align:center;padding:10px 0}
.footer{text-align:center;color:#999;padding:15px;font-size:12px}
</style>
</head>
<body>

<div class="title">
<h2><?php echo html($label ?: $model ?: t("Item Data"))?></h2>
<div>ID: <?php echo $id?> &nbsp; <?php echo html($internalid ?: '')?></div>
<p style="margin-top:8px"><span class="status"><?php echo $status_name?></span></p>
</div>

<div class="card">
<div class="card-header"><?php echo t("Basic Info")?></div>
<div class="card-body">
<div class="row"><span class="label"><?php echo t("Internal ID")?></span><span class="value"><?php echo html($internalid ?: '-')?></span></div>
<div class="row"><span class="label"><?php echo t("Item Type")?></span><span class="value"><?php echo $item_type ?></span></div>
<div class="row"><span class="label"><?php echo t("Manufacturer")?></span><span class="value"><?php echo $manufacturer?></span></div>
<div class="row"><span class="label"><?php echo t("Model")?></span><span class="value"><?php echo html($model)?></span></div>
<div class="row"><span class="label"><?php echo t("S/N")?></span><span class="value"><?php echo $sn_all?></span></div>
</div>
</div>

<div class="card">
<div class="card-header"><?php echo t("Usage")?></div>
<div class="card-body">
<div class="row"><span class="label"><?php echo t("Department")?></span><span class="value"><?php echo $dept_name?></span></div>
<div class="row"><span class="label"><?php echo t("End User")?></span><span class="value"><?php echo $user_name?></span></div>
<div class="row"><span class="label"><?php echo t("Location")?></span><span class="value"><?php echo $location_str?></span></div>
</div>
</div>

<div class="card">
<div class="card-header"><?php echo t("Hardware")?></div>
<div class="card-body">
<div class="row"><span class="label"><?php echo t("CPU Model")?></span><span class="value"><?php echo html($cpu)?></span></div>
<div class="row"><span class="label"><?php echo t("RAM (GB)")?></span><span class="value"><?php echo html($ram)?> GB</span></div>
<div class="row"><span class="label"><?php echo t("HDs (TB)")?></span><span class="value"><?php echo html($hd)?> TB</span></div>
</div>
</div>

<div class="card">
<div class="card-header"><?php echo t("Network")?></div>
<div class="card-body">
<div class="row"><span class="label"><?php echo t("IPv4")?></span><span class="value"><?php echo html($ipv4)?></span></div>
<div class="row"><span class="label"><?php echo t("IPv6")?></span><span class="value"><?php echo html($ipv6)?></span></div>
<div class="row"><span class="label"><?php echo t("MACs")?></span><span class="value"><?php echo html($macs)?></span></div>
</div>
</div>

<div class="card">
<div class="card-header"><?php echo t("Warranty")?></div>
<div class="card-body">
<div class="row"><span class="label"><?php echo t("Date of Purchase")?></span><span class="value"><?php echo $purchasedate ? date($dateformat, $purchasedate) : '-'?></span></div>
<div class="row"><span class="label"><?php echo t("Warranty Expire")?></span><span class="value"><?php echo $warranty_str?></span></div>
</div>
</div>

<div class="footer">
<?php echo $companytitle ?> · <?php echo t("Scan to view"); ?>
</div>

</body>
</html>
