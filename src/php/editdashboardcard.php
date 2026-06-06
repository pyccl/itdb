<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

// 仅新增：加载货币符号 PHP5.6
$sth_curr = $dbh->query("SELECT currency FROM settings LIMIT 1");
$setting_curr = $sth_curr->fetch(PDO::FETCH_ASSOC);
$currency = isset($setting_curr['currency']) ? $setting_curr['currency'] : '';

// ====================== 【仅这里加一段：AJAX 预览拦截，防止输出整页源码】======================
if (isset($_POST['ajax_sql_preview'])) {
    $preview_num = 0;
    $sql_error = '';
    $sql = trim($_POST['sql']);
    if (!empty($sql)) {
        try {
            $sth_p = $dbh->query($sql);
            if ($sth_p) {
                $row = $sth_p->fetch(PDO::FETCH_NUM);
                $preview_num = $row && isset($row[0]) ? $row[0] : 0;
            }
        } catch (Exception $e) {
            $sql_error = t("SQL Error") . ": " . $e->getMessage();
        }
    }
    echo '<div id="pv_num">'.$preview_num.'</div>';
    if ($sql_error) {
        echo '<div class="sql-error">'.htmlspecialchars($sql_error).'</div>';
    }
    exit; // 关键：不输出后面任何HTML/JS
}
// ====================== 【以上是唯一新增的PHP代码】======================
$card_formvars = array('key_name','title','icon','color','count_sql','link_url','sort','status');
// ====================== SQL 预览（PHP 5.6 最终修复版）======================
$preview_num = 0;
$sql_error = '';
$current_count_sql = '';
if (isset($_POST['_only_preview_sql'])) {
    $sql = trim($_POST['_only_preview_sql']);
    $current_count_sql = $sql;
    if (!empty($sql)) {
        try {
            $sth_p = $dbh->query($sql);
            if ($sth_p) {
                $row = $sth_p->fetch(PDO::FETCH_NUM);
                if ($row && isset($row[0])) {
                    $preview_num = $row[0];
                    $sql_error = '';
                } else {
                    $preview_num = 0;
                    $sql_error = t("SQL returned no data");
                }
            } else {
                $preview_num = 0;
                $sql_error = t("SQL execute failed");
            }
        } catch (Exception $e) {
            $sql_error = t("SQL Error") . ": " . $e->getMessage();
            $preview_num = 0;
        }
    } else {
        $preview_num = 0;
        $sql_error = '';
    }
}
// ====================== 删除逻辑 ======================
if (isset($_GET['delid'])) {
    $delid = $_GET['delid'];
    if (!is_numeric($delid)) {
        echo t("Non numeric id")." delid=($delid)";
        exit;
    }
    db_exec($dbh, "DELETE FROM dashboard_cards WHERE id=$delid");
    addOperateLog(
        'dashboard_card','delete','Deleted dashboard card ID %s',array($delid),
        'dashboard_card',$delid,null,null,1,''
    );
    echo "<script>document.location='$scriptname?action=listdashboardcards'</script>";
    exit;
}
// ====================== 保存逻辑（预览时绝对不执行）======================
if (isset($_POST['id']) && !isset($_POST['_only_preview_sql'])) {
    $id         = $_POST['id'];
    $key_name   = trim($_POST['key_name']);
    $title      = $_POST['title'];
    $icon       = $_POST['icon'];
    $color      = $_POST['color'];
    $count_sql  = $_POST['count_sql'];
    $link_url   = $_POST['link_url'];
    $sort       = trim($_POST['sort']);
    $status     = intval($_POST['status']);
    // 空 → 存 NULL
    if ($sort === '') {
        $sort_val = null;
    } else {
        $sort_val = intval($sort);
    }
    if (empty($_POST['key_name'])) {
        echo "<br><b><span class='mandatory'>".t("Key Name")."</span> ".t("cannot be empty.")."</b><br>";
        echo "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }
    if (empty($_POST['title'])) {
        echo "<br><b><span class='mandatory'>".t("Title")."</span> ".t("cannot be empty.")."</b><br>";
        echo "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }
    if (empty($_POST['icon'])) {
        echo "<br><b><span class='mandatory'>".t("Icon")."</span> ".t("cannot be empty.")."</b><br>";
        echo "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }
    if (empty($_POST['color'])) {
        echo "<br><b><span class='mandatory'>".t("Color")."</span> ".t("cannot be empty.")."</b><br>";
        echo "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }
    $check_id = $id == 'new' ? 0 : intval($id);
    $sql_check = "SELECT id FROM dashboard_cards WHERE key_name = ? AND id != ?";
    $sth_check = $dbh->prepare($sql_check);
    $sth_check->execute(array($key_name, $check_id));
    $exists = $sth_check->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        echo "<br><b><span class='mandatory'>".t("Key Name")."</span> already exists, please use another one.</b><br>";
        echo "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }
    if ($_POST['id'] == "new") {
        $sql = "INSERT INTO dashboard_cards
                (key_name,title,icon,color,count_sql,link_url,sort,status)
                VALUES (?,?,?,?,?,?,?,?)";
        $sth = $dbh->prepare($sql);
        $sth->execute(array($key_name, $title, $icon, $color, $count_sql, $link_url, $sort_val, $status));
        $lastid = $dbh->lastInsertId();
        addOperateLog(
            'dashboard_card','add','Created new dashboard card ID %s',array($lastid),
            'dashboard_card',$lastid,null,array('card'=>$_POST),1,''
        );
        echo "<script>window.location='$scriptname?action=editdashboardcard&id=$lastid'</script>";
        echo "</body></html>";
        exit;
    } else {
		$cid = intval($id);
		
		// 🔥 关键：在 UPDATE 之前 先读取数据库旧数据（解决 old 全空）
		$sql_old = "SELECT key_name,title,icon,color,count_sql,link_url,sort,status FROM dashboard_cards WHERE id=?";
		$sth_old = $dbh->prepare($sql_old);
		$sth_old->execute(array($cid));
		$old_row = $sth_old->fetch(PDO::FETCH_ASSOC);
		
		// 执行更新
		$sql = "UPDATE dashboard_cards SET
		        key_name=?,title=?,icon=?,color=?,count_sql=?,link_url=?,sort=?,status=?
		        WHERE id=?";
		$sth = $dbh->prepare($sql);
		$sth->execute(array($key_name, $title, $icon, $color, $count_sql, $link_url, $sort_val, $status, $cid));
		
		// 只记录修改的字段
		$fields = array('key_name','title','icon','color','count_sql','link_url','sort','status');
		$old_data = array();
		$new_data = array();
		foreach ($fields as $f) {
		    $old_val = isset($old_row[$f]) ? $old_row[$f] : '';
		    $new_val = isset($_POST[$f]) ? trim($_POST[$f]) : '';
		
		    if ($f === 'sort') {
		        $old_val = $old_val === null ? '' : (string)$old_val;
		        $new_val = $new_val === '' ? '' : (string)$new_val;
		    }
		
		    if ((string)$old_val !== (string)$new_val) {
		        $old_data[$f] = $old_val;
		        $new_data[$f] = $new_val;
		    }
		}
		
		// 写入操作日志
		addOperateLog(
		    'dashboard_card','update','Updated dashboard card ID %s',array($cid),
		    'dashboard_card',$cid,
		    empty($old_data) ? null : array('id'=>$cid, 'old'=>$old_data),
		    empty($new_data) ? null : array('id'=>$cid, 'new'=>$new_data),
		    1,''
		);
    }
}
if (!isset($_REQUEST['id'])) { echo t("ERROR:ID not defined"); exit; }
$id = $_REQUEST['id'];
$sql = "SELECT * FROM dashboard_cards WHERE id=?";
$sth = $dbh->prepare($sql);
$sth->execute(array($id));
$r = $sth->fetch(PDO::FETCH_ASSOC);
if ($id != "new" && !$r) { echo t("ERROR: non-existent ID")."<br>($sql)"; exit; }
// NULL 转空显示
$r['sort'] = $r['sort'] === null ? '' : $r['sort'];
// 随机颜色（新增时自动生成）
if ($id == "new" || empty($r['color'])) {
    $r['color'] = sprintf('#%06x', mt_rand(0, 0xFFFFFF));
}
if (!empty($current_count_sql)) {
    $r['count_sql'] = $current_count_sql;
}
if (empty($preview_num) && !empty($r['count_sql'])) {
    try {
        $sth_p = $dbh->query($r['count_sql']);
        if ($sth_p) {
            $row = $sth_p->fetch(PDO::FETCH_NUM);
            if ($row && isset($row[0])) {
                $preview_num = $row[0];
                $sql_error = '';
            } else {
                $preview_num = 0;
                $sql_error = t("SQL returned no data");
            }
        } else {
            $preview_num = 0;
            $sql_error = t("SQL execute failed");
        }
    } catch (Exception $e) {
        $sql_error = t("SQL Error").": ".$e->getMessage();
        $preview_num = 0;
    }
}
?>
<!-- 独立预览表单 -->
<form id="pvForm" method="post" style="display:none;">
    <input type="hidden" name="_only_preview_sql" id="_only_preview_sql">
</form>
<form id='mainform' method=post action="<?php echo $scriptname?>?action=editdashboardcard&id=<?php echo $id?>" name="addfrm">
<input type=hidden name='id' value="<?php echo $id ?>">
<?php if ($id=="new"): ?>
<h1><?php te("Add Dashboard Card");?></h1>
<?php else: ?>
<h1><?php te("Edit Dashboard Card");?> (<?php echo $id?>)</h1>
<?php endif; ?>
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px; display:none;'>
    <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>
    <h4><?php te("There are <strong>error</strong>s in your form submission, please see below for details.");?></h4>
    <ol>
        <li><label for="key_name" class="error"><?php te("Key Name is missing");?></label></li>
        <li><label for="title" class="error"><?php te("Title is missing");?></label></li>
        <li><label for="icon" class="error"><?php te("Icon is missing");?></label></li>
        <li><label for="color" class="error"><?php te("Color is missing");?></label></li>
    </ol>
</div>
<table style='width:100%' border=0>
<tr>
<td class="tdtop" width=20% valign="top">
<table class="tbl2" style='width:380px;'>
<tr><td colspan=2><h3><?php te("Card Basic Info");?></h3></td></tr>
<tr>
    <td class="tdt"><?php te("ID");?>:</td>
    <td><input style='display:none' type=text name='id' value='<?php echo $id?>' readonly size=3><?php echo $id?></td>
</tr>
<tr>
    <td class="tdt"><?php te("Key Name");?><sup class='red'>*</sup>:</td>
    <td>
        <input class='input2 mandatory' validate='required:true' size=25 type="text" name="key_name" value="<?php echo htmlspecialchars($r['key_name']); ?>">
        <br><small style="color:#666"><?php te("For amount cards, start with <b>amount_</b>, e.g.: amount_total");?></small>
    </td>
</tr>
<tr>
    <td class="tdt"><?php te("Title");?><sup class='red'>*</sup>:</td>
    <td><input class='input2 mandatory pv_title' validate='required:true' size=25 type="text" name="title" value="<?php echo htmlspecialchars($r['title']); ?>" oninput="pv()"></td>
</tr>
<tr>
    <td class="tdt"><?php te("Icon");?><sup class='red'>*</sup>:</td>
    <td><input class='input2 mandatory pv_icon' validate='required:true' size=25 type="text" name="icon" value="<?php echo htmlspecialchars($r['icon']); ?>" oninput="pv()"></td>
</tr>
<tr>
    <td class="tdt"><?php te("Color");?><sup class='red'>*</sup>:</td>
    <td><input type="color" class="input2 mandatory pv_color" validate="required:true" style="width:155px; height:22px; padding:2px; border:1px solid #ccc;" name="color" value="<?php echo htmlspecialchars($r['color']); ?>" oninput="pv()"></td>
</tr>
<tr>
    <td class="tdt"><?php te("Sort");?>:</td>
    <td><input class='input2' type="number" name="sort" value="<?php echo htmlspecialchars($r['sort']); ?>" style="width:147px;" placeholder=""></td>
</tr>
<tr>
    <td class="tdt"><?php te("Status");?>:</td>
    <td>
        <select name="status">
            <option value="1" <?php echo $r['status']==1 ? 'selected' : '' ?>><?php te("Enabled");?></option>
            <option value="0" <?php echo $r['status']==0 ? 'selected' : '' ?>><?php te("Disabled");?></option>
        </select>
    </td>
</tr>
<tr><td colspan=2><h3><?php te("Card Data & Link");?></h3></td></tr>
<tr>
    <td class="tdt"><?php te("Count SQL");?>:</td>
    <td>
        <textarea class="input2" name="count_sql" rows="4" cols="25" id="psql" oninput="pv()" onblur="doPreview()"><?php echo htmlspecialchars($r['count_sql']); ?></textarea>
    </td>
</tr>
<tr>
    <td class="tdt"><?php te("Link URL");?>:</td>
    <td><input class="input2" size=25 type="text" name="link_url" value="<?php echo htmlspecialchars($r['link_url']); ?>"></td>
</tr>
</table>
<div style="margin-top:12px;">
<button type="submit" form="mainform"><img src="images/save.png" alt='Save'> <?php te("Save");?></button>
<?php if (is_numeric($id) && $id != 1): ?>
<button type="button" onclick="delconfirm2('<?php echo $r['id']?>','<?php echo $scriptname?>?action=editdashboardcard&delid=<?php echo $id?>','<?php te("Delete this card?")?>')">
<img src="images/delete.png" border=0> <?php te("Delete");?></button>
<?php endif; ?>
</div>
</td>
<td class="tdtop" style="padding-left:10px; border-left:1px dashed #aaa; vertical-align:top;">
<style>
.stat-card-preview {
    width: 180px; background: #fff; border-radius: 8px; padding:20px; text-align:center;
    box-shadow:0 2px 8px rgba(0,0,0,0.07); border-top:5px solid <?php echo htmlspecialchars($r['color']); ?>;
}
.stat-card-preview .p-icon { font-size:32px; margin-bottom:10px; color:<?php echo htmlspecialchars($r['color']); ?>; }
.stat-card-preview .p-num { font-size:32px; font-weight:bold; color:#333; }
.stat-card-preview .p-title { font-size:13px; color:#777; margin-top:4px; }
.sql-error { color:#F56C6C; background:#FEF0F0; padding:6px 10px; border-radius:4px; margin-top:8px; font-size:12px; }
</style>
<h3><?php te("Live Preview");?></h3>
<div id="preview_card" class="stat-card-preview">
    <div class="p-icon" id="pv_icon"><?php echo htmlspecialchars($r['icon']); ?></div>
	<div class="p-num" id="pv_num">
<?php
$key = isset($r['key_name']) ? $r['key_name'] : '';
if (is_numeric($preview_num)) {
    $decimals = strpos((string)$preview_num, '.') !== false ? strlen(substr(strrchr($preview_num, '.'), 1)) : 0;
    $formatted = number_format($preview_num, $decimals);
    if (substr($key, 0, 7) === 'amount_') {
        echo $currency . $formatted;
    } else {
        echo $formatted;
    }
} else {
    echo $preview_num;
}
?>
	</div>
    <div class="p-title" id="pv_title"><?php echo htmlspecialchars($r['title']); ?></div>
</div>
<?php if ($sql_error): ?>
<div class="sql-error"><?php echo $sql_error; ?></div>
<?php endif; ?>
</td>
</tr>
</table>
</form>
<script>
function pv() {
    var title = document.querySelector('.pv_title').value;
    var icon  = document.querySelector('.pv_icon').value;
    var color = document.querySelector('.pv_color').value;
    var box   = document.getElementById('preview_card');
    document.getElementById('pv_title').innerText = title;
    document.getElementById('pv_icon').innerText = icon;
    box.style.borderTopColor = color;
    document.getElementById('pv_icon').style.color = color;
}
function doPreview() {
    var sql = document.getElementById('psql').value;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', location.href, true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.onreadystatechange = function(){
        if(xhr.readyState==4 && xhr.status==200){
            var res = xhr.responseText;
            var num = res.match(/<div id="pv_num">([\s\S]*?)<\/div>/);
            var err = res.match(/<div class="sql-error">([\s\S]*?)<\/div>/);
            var key = '<?php echo isset($r['key_name']) ? addslashes($r['key_name']) : ''; ?>';
            var curr = '<?php echo addslashes($currency); ?>';
            var val = num ? num[1] : 0;
            
            if (!isNaN(val)) {
                val = val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            
            if (key.substring(0,7) === 'amount_' && !isNaN(val)) {
                document.getElementById('pv_num').innerText = curr + val;
            } else {
                document.getElementById('pv_num').innerText = val;
            }
            var errBox = document.querySelector('.sql-error');
            if(err){
                if(!errBox){
                    errBox = document.createElement('div');
                    errBox.className = 'sql-error';
                    preview_card.after(errBox);
                }
                errBox.innerText = err[1];
                errBox.style.display = 'block';
            }else{
                if(errBox) errBox.style.display = 'none';
            }
        }
    }
    xhr.send('ajax_sql_preview=1&sql='+encodeURIComponent(sql));
}
</script>
</body></html>
