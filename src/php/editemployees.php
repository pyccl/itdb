<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}


// 先加载员工数据（必须放最前面！）
if (!isset($_REQUEST['id'])) {
    $id = "new";
} else {
    $id = $_REQUEST['id'];
}
$r = array();
if ($id != "new") {
    $sql = "SELECT * FROM employees WHERE id='$id'";
    $sth = db_execute($dbh, $sql);
    $r = $sth->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        echo "<script>alert('".t("ERROR: non-existent ID")."');history.go(-1);</script>";
        exit;
    }
}

// 解绑设备 → 员工+部门都清空 + 日志
if (isset($_GET['unbind_device']) && isset($id) && $id != "new" && is_numeric($id)) {
    $unbind_id = intval($_GET['unbind_device']);
    $emp_id = intval($id);
    
    // 查设备旧数据用于日志
    $sql_old_dev = "SELECT id,label,custom_user,custom_dept FROM items WHERE id=$unbind_id";
    $sth_old_dev = db_execute($dbh, $sql_old_dev);
    $old_dev = $sth_old_dev->fetch(PDO::FETCH_ASSOC);
    
    $sql_unbind = "UPDATE items SET custom_user = NULL, custom_dept = NULL WHERE id = $unbind_id AND custom_user = $emp_id";
    db_exec($dbh, $sql_unbind);
    
    // 解绑日志
    addOperateLog(
        'item',
        'unbind_item',
        'Unbound item ID %s from employee ID %s',
        array($unbind_id, $emp_id),
        'item',
        $emp_id,
        $old_dev,
        array('custom_user'=>null,'custom_dept'=>null),
        1,
        ''
    );
    
    echo "<script>window.location='$scriptname?action=$action&id=$id'</script>";
    exit;
}

// 分配设备 → 员工+部门同时正确写入 + 日志
if (isset($_POST['assign_device']) && isset($id) && $id != "new" && is_numeric($id)) {
    $device_id = intval($_POST['assign_device']);
    $emp_id = intval($id);
    $dept_id = intval($r['department_id']);
    if ($device_id > 0 && $dept_id > 0) {
        // 旧数据
        $sql_old_dev = "SELECT id,label,custom_user,custom_dept FROM items WHERE id=$device_id";
        $sth_old_dev = db_execute($dbh, $sql_old_dev);
        $old_dev = $sth_old_dev->fetch(PDO::FETCH_ASSOC);
        
        $sql_assign = "UPDATE items SET custom_user = $emp_id, custom_dept = $dept_id WHERE id = $device_id";
        db_exec($dbh, $sql_assign);
        
        // 分配日志
        addOperateLog(
            'item',
            'assign_item',
            'Assigned item ID %s to employee ID %s',
            array($device_id, $emp_id),
            'item',
            $emp_id,
            $old_dev,
            array('custom_user'=>$emp_id,'custom_dept'=>$dept_id),
            1,
            ''
        );
        
        echo "<script>window.location='$scriptname?action=$action&id=$id'</script>";
        exit;
    }
}

// 删除员工 + 日志
if (isset($_GET['delid'])) {
    $delid = $_GET['delid'];
    if (!is_numeric($delid)) {
        echo "<script>alert('".t("Non numeric id")."');history.go(-1);</script>";
        exit;
    }
    $sql_chk_item = "SELECT COUNT(*) AS cnt FROM items WHERE custom_user = $delid";
    $sth_chk = db_execute($dbh, $sql_chk_item);
    $row_chk = $sth_chk->fetch(PDO::FETCH_ASSOC);
    if ($row_chk['cnt'] > 0) {
        echo "<script>alert('".t("Cannot delete: This employee has assigned items, please unbind first.")."');history.go(-1);</script>";
        exit;
    }
    $sql_check_emp = "SELECT id FROM employees WHERE id = $delid";
    $sth_check = db_execute($dbh, $sql_check_emp);
    $check_row = $sth_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$check_row) {
        echo "<script>alert('".t("Employee not found")."');history.go(-1);</script>";
        exit;
    }
    
    // 删除前查旧数据
    $sql_old = "SELECT * FROM employees WHERE id = " . intval($delid);
    $sth_old = db_execute($dbh, $sql_old);
    $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);
    
    $sql_del = "DELETE FROM employees WHERE id = $delid";
    db_exec($dbh, $sql_del);
    
    // 删除日志
    addOperateLog(
        'employee',
        'delete',
        'Deleted employee ID %s',
        array($delid),
        'employee',
        $delid,
        $old_data,
        null,
        1,
        ''
    );
    
    echo "<script>document.location='$scriptname?action=listemployees'</script>";
    echo "<a href='$scriptname?action=listemployees'>".t("Click here if not redirected")."</a></body></html>";
    exit;
}

// 提交保存
$show_error = false;
$name_empty_error = false;
$code_empty_error = false;
$department_empty_error = false;
$code_duplicate_error = false;
$email_duplicate_error = false;

// 员工日志字段
$emp_fields = array('id','name','employee_code','department_id','position','email','phone','hire_date','status','created_time');

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $employee_code = trim($_POST['employee_code']);
    $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
    $position = trim($_POST['position']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $hire_date = isset($_POST['hire_date']) ? strtotime($_POST['hire_date']) : time();
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;

    if (empty($name)) {
        $name_empty_error = true;
        $show_error = true;
    }
    if (empty($employee_code)) {
        $code_empty_error = true;
        $show_error = true;
    }
    if ($department_id <= 0) {
        $department_empty_error = true;
        $show_error = true;
    }

    if (!$show_error) {
        $exclude_self = ($id != "new" && is_numeric($id)) ? " AND id != ".intval($id) : "";
        $sql_code = "SELECT id FROM employees WHERE employee_code = '".addslashes($employee_code)."' $exclude_self LIMIT 1";
        $sth_code = db_execute($dbh, $sql_code);
        $dup_code = $sth_code->fetch(PDO::FETCH_ASSOC);
        if ($dup_code) {
            $code_duplicate_error = true;
            $show_error = true;
        }
        if (!empty($email)) {
            $sql_email = "SELECT id FROM employees WHERE email = '".addslashes($email)."' $exclude_self LIMIT 1";
            $sth_email = db_execute($dbh, $sql_email);
            $dup_email = $sth_email->fetch(PDO::FETCH_ASSOC);
            if ($dup_email) {
                $email_duplicate_error = true;
                $show_error = true;
            }
        }
    }

    if (!$show_error) {
        if ($status == 0 && $id != "new") {
            $sql_chk = "SELECT COUNT(*) AS cnt FROM items WHERE custom_user = " . intval($id);
            $sth_chk = db_execute($dbh, $sql_chk);
            $row = $sth_chk->fetch(PDO::FETCH_ASSOC);
            if ($row['cnt'] > 0) {
                echo "<script>alert('" . t("Cannot resign: This employee still has assigned items, please unbind first!") . "');</script>";
                echo "<script>history.go(-1);</script>";
                exit;
            }
        }
        
        $current_time = time();
        if ($id == "new") {
            $sql = "INSERT INTO employees (name,employee_code,department_id,position,email,phone,hire_date,status,created_time)
                    VALUES (
                        '".addslashes($name)."',
                        '".addslashes($employee_code)."',
                        ".intval($department_id).",
                        '".addslashes($position)."',
                        '".addslashes($email)."',
                        '".addslashes($phone)."',
                        ".intval($hire_date).",
                        ".intval($status).",
                        $current_time
                    )";
            db_exec($dbh, $sql);
            $lastid = $dbh->lastInsertId();
            
            // 新增日志
            $new_data = array(
                'id' => $lastid,
                'name' => $name,
                'employee_code' => $employee_code,
                'department_id' => $department_id,
                'position' => $position,
                'email' => $email,
                'phone' => $phone,
                'hire_date' => $hire_date,
                'status' => $status,
                'created_time' => $current_time
            );
            addOperateLog(
                'employee',
                'add',
                'Created new employee ID %s',
                array($lastid),
                'employee',
                $lastid,
                null,
                $new_data,
                1,
                ''
            );
            
            echo "<script>window.location='$scriptname?action=$action&id=$lastid'</script>";
            exit;
        } else {
            // 修改前查旧数据
            $sql_old = "SELECT * FROM employees WHERE id = " . intval($id);
            $sth_old = db_execute($dbh, $sql_old);
            $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);
            
            $sql = "UPDATE employees SET
            name='".addslashes($name)."',
            employee_code='".addslashes($employee_code)."',
            department_id=".intval($department_id).",
            position='".addslashes($position)."',
            email='".addslashes($email)."',
            phone='".addslashes($phone)."',
            hire_date=".intval($hire_date).",
            status=".intval($status)."
            WHERE id=".intval($id);
            db_exec($dbh, $sql);
            
            // 同步设备部门
            $sync_sql = "UPDATE items SET custom_dept = ".intval($department_id)." WHERE custom_user = ".intval($id);
            db_exec($dbh, $sync_sql);
            
            // 新数据
            $sql_new = "SELECT * FROM employees WHERE id = " . intval($id);
            $sth_new = db_execute($dbh, $sql_new);
            $new_data = $sth_new->fetch(PDO::FETCH_ASSOC);
            
            // 差异对比
            $diff_old = array();
            $diff_new = array();
            foreach ($emp_fields as $k) {
                $old_val = isset($old_data[$k]) ? $old_data[$k] : '';
                $new_val = isset($new_data[$k]) ? $new_data[$k] : '';
                if ((string)$old_val !== (string)$new_val) {
                    $diff_old[$k] = $old_val;
                    $diff_new[$k] = $new_val;
                }
            }
            
            // 修改日志
            addOperateLog(
                'employee',
                'update',
                'Updated employee ID %s',
                array($id),
                'employee',
                $id,
                $diff_old,
                $diff_new,
                1,
                ''
            );
        }
    }
}

// 加载员工数据
if (!isset($_REQUEST['id'])) {
    $id = "new";
} else {
    $id = $_REQUEST['id'];
}
$r = array();
if ($id != "new") {
    $sql = "SELECT * FROM employees WHERE id='$id'";
    $sth = db_execute($dbh, $sql);
    $r = $sth->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        echo "<script>alert('".t("ERROR: non-existent ID")."');history.go(-1);</script>";
        exit;
    }
}

// 统计设备数量
$device_count = 0;
if ($id != "new" && is_numeric($id)) {
    $sql_cnt = "SELECT COUNT(*) AS cnt FROM items WHERE custom_user = " . intval($id);
    $sth_cnt = db_execute($dbh, $sql_cnt);
    $cnt_row = $sth_cnt->fetch(PDO::FETCH_ASSOC);
    $device_count = $cnt_row['cnt'];
}

// 加载该员工使用的设备
$device_list = [];
if ($id != "new" && is_numeric($id)) {
    $sql_dev = "SELECT 
        i.id, 
        i.internalid, 
        i.label, 
        i.model,
        i.manufacturerid,
        a.title as brand
        FROM items i
        LEFT JOIN agents a ON i.manufacturerid = a.id
        WHERE i.custom_user = " . intval($id) . "
        ORDER BY i.id DESC";
    $sth_dev = db_execute($dbh, $sql_dev);
    if ($sth_dev) {
        while ($row_dev = $sth_dev->fetch(PDO::FETCH_ASSOC)) {
            $device_list[] = $row_dev;
        }
    }
}

// 加载未分配设备
$available_devices = [];
$sql_avail = "SELECT 
    i.id,
    i.internalid,
    i.label,
    i.model,
    a.title as brand
    FROM items i
    LEFT JOIN agents a ON i.manufacturerid = a.id
    WHERE COALESCE(custom_user, '') = ''
    ORDER BY i.id DESC";
$sth_avail = db_execute($dbh, $sql_avail);
if ($sth_avail) {
    while ($d = $sth_avail->fetch(PDO::FETCH_ASSOC)) {
        $available_devices[] = $d;
    }
}

// 部门树形
$sql_all = "SELECT id,name,parent_id FROM departments ORDER BY CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order ASC, id ASC";
$sth_all = db_execute($dbh, $sql_all);
$all_departments = array();
while ($row = $sth_all->fetch(PDO::FETCH_ASSOC)) {
    $all_departments[] = $row;
}
$tree = buildTree($all_departments);
$current_dept = isset($_POST['department_id']) ? intval($_POST['department_id']) : (isset($r['department_id']) ? $r['department_id'] : 0);
?>
<form id='mainform' method='post' action='<?php echo $scriptname."?action=".$action."&id=".$id; ?>'>
<?php
if ($id == "new") {
    echo "<h1>".t("Add new Employee")."</h1>";
} else {
    echo "<h1>".sprintf(t("Edit Employee (ID: %d)"), $id)."</h1>";
}
?>
<div class='errcontainer ui-state-error ui-corner-all' style='padding:0 .7em;width:700px;margin-bottom:3px;<?php echo $show_error ? "" : "display:none";?>'>
<p><span class='ui-icon ui-icon-alert' style='float:left; margin-right:.3em;'></span>
<h4><?php echo t("Form submission error, please check the following fields:"); ?></h4>
<ol>
<?php
if ($name_empty_error) echo "<li><label class='error'>".t("Employee name cannot be empty")."</label></li>";
if ($code_empty_error) echo "<li><label class='error'>".t("Employee code cannot be empty")."</label></li>";
if ($department_empty_error) echo "<li><label class='error'>".t("Please select a department")."</label></li>";
if ($code_duplicate_error) echo "<li><label class='error'>".t("Employee code already exists")."</label></li>";
if ($email_duplicate_error) echo "<li><label class='error'>".t("Email already exists")."</label></li>";
?>
</ol>
</div>
<table style='width:100%' border='0'>
<tr>
<td class="tdtop" width="300px">
<table class="tbl2" style='width:290px;'>
<tr><td colspan=2><h3><?php echo t("Employee Information"); ?></h3></td></tr>
<tr>
<td class="tdt"><?php echo t("ID"); ?>:</td>
<td>
<input type='hidden' name='id' value='<?php echo $id; ?>'>
<?php if ($id == "new") echo t("New"); else echo $id; ?>
</td>
</tr>
<tr>
<td class="tdt"><?php echo t("Full Name"); ?><sup class='red'>*</sup>:</td>
<td>
<input class='input2 mandatory' id='name' type='text' name='name' size='30' value="<?php echo htmlspecialchars(isset($_POST['name']) ? $_POST['name'] : $r['name']); ?>">
</td>
</tr>
<tr>
<td class="tdt"><?php echo t("Employees Code"); ?><sup class='red'>*</sup>:</td>
<td>
<input class='input2 mandatory' id='employee_code' type='text' name='employee_code' size='20' value="<?php echo htmlspecialchars(isset($_POST['employee_code']) ? $_POST['employee_code'] : $r['employee_code']); ?>">
</td>
</tr>
<tr>
<td class="tdt"><?php echo t("Department"); ?><sup class='red'>*</sup>:</td>
<td>
<select name='department_id' id='department_id' style='width:155px;' class='mandatory'>
<option value="0"><?php echo t("Select"); ?></option>
<?php
foreach ($tree as $dept) {
    $depth = $dept['depth'];
    $prefix = $depth == 0 ? '' : str_repeat('　　', $depth - 1) . '┕┉';
    $selected = ($dept['id'] == $current_dept) ? 'selected' : '';
    echo "<option value='{$dept['id']}' $selected>{$prefix}{$dept['name']}</option>";
}
?>
</select>
</td>
</tr>
<tr>
<td class="tdt"><?php echo t("Position"); ?>:</td>
<td><input class='input2' size='30' type='text' name='position' value="<?php echo htmlspecialchars($r['position']); ?>"></td>
</tr>
<tr>
<td class="tdt"><?php echo t("Email"); ?>:</td>
<td><input class='input2' size='30' type='text' name='email' value="<?php echo htmlspecialchars($r['email']); ?>"></td>
</tr>
<tr>
<td class="tdt"><?php echo t("Phone"); ?>:</td>
<td><input class='input2' size='20' type='text' name='phone' value="<?php echo htmlspecialchars($r['phone']); ?>"></td>
</tr>
<tr>
<td class="tdt"><?php echo t("Hire Date"); ?>:</td>
<td><input class='dateinp' type='text' name='hire_date' value="<?php echo isset($r['hire_date']) ? date('Y-m-d', $r['hire_date']) : date('Y-m-d'); ?>"></td>
</tr>
<tr>
<td class="tdt"><?php echo t("Status"); ?>:</td>
<td>
<select name='status' class='input2'>
<option value='1' <?php echo ((isset($r['status']) ? $r['status'] : 1) == 1) ? 'selected' : ''; ?>><?php echo t("Active"); ?></option>
<option value='0' <?php echo ((isset($r['status']) ? $r['status'] : 1) == 0) ? 'selected' : ''; ?>><?php echo t("Inactive"); ?></option>
</select>
</td>
</tr>
<?php if ($id != "new"): ?>
<tr>
<td class="tdt"><?php echo t("Item Count"); ?>:</td>
<td><strong style="color:#c00000;"><?php echo $device_count; ?></strong></td>
</tr>
<tr>
<td class="tdt"><?php echo t("Created Time"); ?>:</td>
<td>
<?php
echo isset($r['created_time']) ? date('Y-m-d H:i:s', $r['created_time']) : t("Unknown");
?>
</td>
</tr>
<?php endif; ?>
</table>
</td>
<td style='padding-left:10px; border-left:1px dashed #aaa; vertical-align:top;'>
<div style="border:1px solid #ccc; padding:10px; background:#f9f9f9; border-radius:4px;">
<h3 style="margin:0 0 10px 0; font-size:14px;"><?php echo t("Item Management"); ?></h3>
<?php if ($id == "new"): ?>
    <p style="color:#666;"><?php echo t("Please save the employee first to manage devices."); ?></p>
<?php else: ?>
<div style="margin-bottom:12px;">
<h4 style="margin:0 0 5px 0; font-size:12px; color:#333;"><?php echo t("Assigned Items"); ?> (<?php echo $device_count; ?>)</h4>
<?php if (count($device_list) == 0): ?>
    <p style="font-size:12px; color:#666;">- <?php echo t("No items assigned"); ?> -</p>
<?php else: ?>
<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:12px; background:#fff;">
<tr style="background:#eee; text-align:left;">
<th style="width:5%; text-align:center; font-weight:bold;"><?php echo t("ID"); ?></th>
<th style="width:15%;"><?php echo t("Internal ID"); ?></th>
<th style="width:35%;"><?php echo t("Label"); ?></th>
<th style="width:35%;"><?php echo t("Manufacturer/Model"); ?></th>
<th style="width:10%; text-align:center;"><?php echo t("Action"); ?></th>
</tr>
<?php foreach ($device_list as $dev): ?>
<tr>
<td style="text-align:center;"><a class='editid' href="<?php echo $scriptname; ?>?action=edititem&id=<?php echo $dev['id']; ?>"><?php echo $dev['id']; ?></a></td>
<td><?php echo htmlspecialchars($dev['internalid']); ?></td>
<td><?php echo htmlspecialchars($dev['label']); ?></td>
<td>
<?php 
$brand = trim($dev['brand']);
$model = trim($dev['model']);
$parts = [];
if (!empty($brand)) $parts[] = $brand;
if (!empty($model)) $parts[] = $model;
echo htmlspecialchars(implode(' ', $parts));
?>
</td>
<td style="text-align:center;">
<a href="javascript:if(confirm('<?php echo t("Unbind?"); ?>')){window.location='<?php echo $scriptname."?action=$action&id=$id&unbind_device=".$dev['id']; ?>'}"
style="color:red; font-size:11px;"><?php echo t("Unbind"); ?></a>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
<div>
<h4 style="margin:0 0 5px 0; font-size:12px; color:#333;"><?php echo t("Assign Item"); ?> (<?php echo count($available_devices); ?>)</h4>
<form method="post" style="margin:0;" id="assignForm">
<div style="position:relative; width:380px;">
    <input 
        type="text" 
        id="deviceSearch" 
        placeholder="<?php echo t('Search ID / Internal ID / Label / Manufacturer...'); ?>"
        style="width:380px; padding:3px; font-size:12px; border:1px solid #ccc;"
    >
    <input type="hidden" name="assign_device" id="assign_device" value="0">
    <div id="searchResult" style="
        position:absolute; 
        top:24px; 
        left:0; 
        width:380px; 
        max-height:260px; 
        overflow-y:auto; 
        background:#fff; 
        border:1px solid #ccc; 
        border-top:none; 
        display:none;
        z-index:999;
    "></div>
</div>
<button type="submit" style="font-size:12px; padding:2px 6px; margin-top:4px;"><?php echo t("Assign"); ?></button>
</form>
</div>
<?php endif; ?>
</div>
</td>
</tr>
<tr>
<td colspan='2'>
<button type='submit'>
<img src='images/save.png' alt='<?php echo t("Save"); ?>'> <?php echo t("Save"); ?>
</button>
<?php if ($id != "new") { ?>
<button type='button' onclick="if(confirm('<?php echo t("Are you sure you want to delete this employee?"); ?>')){window.location='<?php echo $scriptname; ?>?action=<?php echo $action; ?>&delid=<?php echo $id; ?>';}">
<img src='images/delete.png' border='0'> <?php echo t("Delete"); ?></button>
<?php } ?>
</td>
</tr>
</table>
</form>
<script>
// 国际化文字（PHP输出到JS）
var i18n = {
    noMatch: "<?php echo t('No matching records found'); ?>",
    searchPlaceholder: "<?php echo t('Search ID / Internal ID / Label / Manufacturer...'); ?>"
};
document.addEventListener('DOMContentLoaded', function(){
    // ✅ 修复1：PHP5.6兼容中文不转义
    var devices = <?php
        $json = json_encode($available_devices);
        // PHP5.6兼容：手动替换unicode
        $json = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function($m){
            return mb_convert_encoding(pack('H*',$m[1]), 'UTF-8', 'UCS-2BE');
        }, $json);
        echo $json;
    ?>;

    var searchInput = document.getElementById('deviceSearch');
    var resultBox = document.getElementById('searchResult');
    var assignInput = document.getElementById('assign_device');

    function buildText(item) {
        let parts = ['ID:' + item.id];
        if (item.internalid) parts.push(item.internalid);
        if (item.label) parts.push(item.label);
        let spec = [];
        if (item.brand) spec.push(item.brand);
        if (item.model) spec.push(item.model); // ✅ 修复2：it.model
        let txt = parts.join(' · ');
        if (spec.length > 0) txt += ' (' + spec.join(' ') + ')';
        return txt;
    }

    function doSearch() {
        var kw = searchInput.value.toLowerCase().trim();
        resultBox.innerHTML = '';
        if (kw.length < 1) {
            resultBox.style.display = 'none';
            return;
        }
        var matched = devices.filter(it => {
            var t = buildText(it).toLowerCase();
            return t.indexOf(kw) !== -1;
        });
        if (matched.length === 0) {
            resultBox.innerHTML = '<div style="padding:4px; color:#666;">'+i18n.noMatch+'</div>';
            resultBox.style.display = 'block';
            return;
        }
        matched.forEach(it => {
            let div = document.createElement('div');
            div.style.padding = '4px 6px';
            div.style.cursor = 'pointer';
            div.style.fontSize = '12px';
            div.textContent = buildText(it);
            div.onclick = function() {
                searchInput.value = this.textContent;
                assignInput.value = it.id;
                resultBox.style.display = 'none';
            };
            div.onmouseover = function() { this.style.background = '#eef'; };
            div.onmouseout = function() { this.style.background = '#fff'; };
            resultBox.appendChild(div);
        });
        resultBox.style.display = 'block';
    }
    searchInput.addEventListener('input', doSearch);
    searchInput.addEventListener('focus', doSearch);
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultBox.contains(e.target)) {
            resultBox.style.display = 'none';
        }
    });
});
// 原有表单验证
var form = document.getElementById('mainform');
var name = document.getElementById('name');
var code = document.getElementById('employee_code');
var department = document.getElementById('department_id');
form.onsubmit = function(){
    if (!name.value.trim()) {
        alert('<?php echo t("Employee name cannot be empty"); ?>');
        name.focus();
        return false;
    }
    if (!code.value.trim()) {
        alert('<?php echo t("Employee code cannot be empty"); ?>');
        code.focus();
        return false;
    }
    if (department.value <= 0) {
        alert('<?php echo t("Please select a department"); ?>');
        department.focus();
        return false;
    }
    return true;
};
<?php if ($code_duplicate_error) echo "alert('".t("Employee code already exists")."');"; ?>
<?php if ($email_duplicate_error) echo "alert('".t("Email already exists")."');"; ?>
<?php
// 统一合并错误提示 - 带国际化 t() 函数完整版
if ($name_empty_error || $code_empty_error || $department_empty_error) {
    $msg = '';
    if ($name_empty_error) $msg .= t("Employee name cannot be empty") . '\n';
    if ($code_empty_error) $msg .= t("Employee code cannot be empty") . '\n';
    if ($department_empty_error) $msg .= t("Please select a department");
    echo "alert('" . trim($msg) . "');";
}
?>

</script>

</body></html>
