<?php
if (!isset($initok)) {
    echo t("do not run this script directly");
    exit;
}

// 状态切换置顶执行
$action = isset($_GET['switch_status']) ? $_GET['switch_status'] : '';
$card_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($action === 'toggle' && $card_id > 0) {
    $val = isset($_GET['val']) ? intval($_GET['val']) : 0;
    $new_status = ($val == 1) ? 1 : 0;
    $sql_update = "UPDATE dashboard_cards SET status = $new_status WHERE id = $card_id";
    $dbh->query($sql_update);
    echo '<script>location.href="'.$scriptname.'?action=listdashboardcards";</script>';
    exit;
}

// 货币配置
$sth_curr = $dbh->query("SELECT currency FROM settings LIMIT 1");
$setting_curr = $sth_curr->fetch(PDO::FETCH_ASSOC);
$currency = isset($setting_curr['currency']) ? $setting_curr['currency'] : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title><?php te("Dashboard Cards"); ?></title>
<style>
* {box-sizing:border-box; margin:0; padding:0;}
body {font-family:Arial; background:#f5f7fa; padding:20px;}
.page-header {
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:20px;
}
.page-title {font-size:22px; font-weight:bold;}
.btn-add {
  background:#009688; color:#fff; padding:8px 14px; border-radius:6px;
  text-decoration:none;
}
.stats-grid {
  display:flex; flex-wrap:wrap; gap:15px; padding:10px 0;
}
/* 🔥 卡片统一大小：宽度固定 + 高度固定 */
.stat-card {
  background:#fff; border-radius:8px; padding:20px;
  width: 180px;         /* 统一宽度 */
  height: 140px;        /* 统一高度 */
  min-width: 180px;
  max-width: 180px;
  min-height: 140px;
  max-height: 140px;
  text-align:center;
  box-shadow:0 2px 5px rgba(0,0,0,0.05);
  border-top:4px solid; cursor:pointer;
  position:relative; transition:all 0.2s;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.stat-card:hover {
  transform:scale(1.03); box-shadow:0 4px 12px rgba(0,0,0,0.1);
}
.stat-icon {
  font-size:28px;
  margin-bottom:8px;
  line-height: 1;
}
/* 日期、数字统一居中、自动换行、不撑大卡片 */
.stat-number {
  font-size:22px;
  font-weight:bold;
  line-height:1.3;
  min-height: auto;
  margin-bottom:4px;
  white-space: pre-wrap;
  word-break: break-word;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
}
.stat-label {
  font-size:12px;
  color:#777;
  text-transform:uppercase;
  line-height: 1.2;
}
/* 开关样式 禁用红色 启用绿色 */
.switch-box {
    position: absolute;
    top: 10px;
    right: 10px;
}
.switch {
  position:relative; display:inline-block; width:40px; height:22px;
}
.switch input {opacity:0; width:0; height:0;}
/* 默认禁用状态红色 */
.slider {
  position:absolute; top:0; left:0; right:0; bottom:0;
  background-color:#F56C6C;
  transition:.3s; border-radius:22px;
}
.slider:before {
  position:absolute; content:""; height:16px; width:16px;
  left:3px; bottom:3px; background-color:white;
  transition:.3s; border-radius:50%;
}
/* 选中启用状态绿色 */
input:checked + .slider {background-color:#009688;}
input:checked + .slider:before {transform:translateX(18px);}
.empty {padding:40px; text-align:center; color:#999;}
</style>

</head>
<body>

<div class="page-header">
  <div class="page-title"><?php te("Dashboard Cards"); ?></div>
  <a href="<?php echo $scriptname?>?action=editdashboardcard&id=new" class="btn-add">
    <?php te("Add"); ?>
  </a>
</div>

<div class="stats-grid">
<?php
$sql = "SELECT id, key_name, title, icon, color, sort, status, count_sql
        FROM dashboard_cards
        ORDER BY CASE WHEN sort IS NULL OR sort = '' THEN 999999 ELSE sort END ASC, id ASC";

$sth = db_execute($dbh, $sql);
$has = false;

while ($r = $sth->fetch(PDO::FETCH_ASSOC)) {
    $has = true;
    $id     = $r['id'];
    $title  = htmlspecialchars($r['title']);
    $icon   = htmlspecialchars($r['icon']);
    $color  = htmlspecialchars($r['color']);
    $status = $r['status'];
    $csql   = $r['count_sql'];
    $key    = $r['key_name'];

    $val = "0";
    if (!empty($csql)) {
        try {
            $s = $dbh->query($csql);
            $row = $s->fetch(PDO::FETCH_NUM);
            $val = $row ? $row[0] : "0";
        } catch (Exception $e) {
            $val = "ERR";
        }
    }

    $show = "";
    if (is_numeric($val)) {
        if (strpos($key, "amount_") === 0) {
            $show = $currency . number_format((float)$val, 2);
        } else {
            $show = number_format((int)$val);
        }
    } else {
        $show = htmlspecialchars($val);
    }
?>

<div class="stat-card" style="border-top-color:<?php echo $color; ?>;"
onclick="location.href='<?php echo $scriptname?>?action=editdashboardcard&id=<?php echo $id?>'">

    <div class="switch-box" onclick="event.stopPropagation();">
        <label class="switch">
            <input type="checkbox" <?php echo ($status==1) ? 'checked' : ''; ?>
            onchange="this.checked?location.href='<?php echo $scriptname?>?action=listdashboardcards&switch_status=toggle&id=<?php echo $id; ?>&val=1':location.href='<?php echo $scriptname?>?action=listdashboardcards&switch_status=toggle&id=<?php echo $id; ?>&val=0'">
            <span class="slider"></span>
        </label>
    </div>

    <div class="stat-icon"><?php echo $icon; ?></div>
    <div class="stat-number"><?php echo $show; ?></div>
    <div class="stat-label"><?php echo $title; ?></div>
</div>

<?php } ?>

<?php if (!$has) { ?>
<div class="empty"><?php te("No data available"); ?></div>
<?php } ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    function update(){
        var now = new Date();
        var y = now.getFullYear();
        var m = String(now.getMonth()+1).padStart(2,'0');
        var d = String(now.getDate()).padStart(2,'0');
        var hh = String(now.getHours()).padStart(2,'0');
        var mm = String(now.getMinutes()).padStart(2,'0');
        var ss = String(now.getSeconds()).padStart(2,'0');

        var date = y+'-'+m+'-'+d;
        var time = hh+':'+mm+':'+ss;
        var dt   = date+'<br>'+time;

        var nodes = document.querySelectorAll('.stat-number');
        for(var i=0; i<nodes.length; i++){
            var t = nodes[i].textContent.trim();
            if (t.indexOf('-')>-1 && t.indexOf(':')>-1) {
                nodes[i].innerHTML = dt;
            } else if (t.indexOf('-')>-1) {
                nodes[i].textContent = date;
            } else if (t.indexOf(':')>-1) {
                nodes[i].textContent = time;
            }
        }
    }
    update();
    setInterval(update, 1000);
});
</script>
</body>
</html>
