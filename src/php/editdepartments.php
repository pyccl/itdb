<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

// 1. Handle Deletion (弹窗提示版)
if (isset($_GET['delid'])) {
    $delid = $_GET['delid'];
    if (!is_numeric($delid)) {
        echo "<script>alert('".t("Non numeric id")."');history.go(-1);</script>";
        exit;
    }
    $sql_check = "SELECT 
                    (SELECT count(*) FROM departments WHERE parent_id = $delid) as child_count,
                    (SELECT count(*) FROM employees WHERE department_id = $delid) as employee_count
                 ";
    $sth_check = db_execute($dbh, $sql_check);
    $check = $sth_check->fetch(PDO::FETCH_ASSOC);
    
    if ($check['child_count'] > 0) {
        echo "<script>alert('".t("Department not deleted: Sub-departments exist. Please handle them first.")."');history.go(-1);</script>";
        exit;
    }
    if ($check['employee_count'] > 0) {
        echo "<script>alert('".t("Department not deleted: Employees are assigned. Please transfer them first.")."');history.go(-1);</script>";
        exit;
    }

    // 删除前先查旧数据用于日志
    $sql_old = "SELECT * FROM departments WHERE id = " . intval($delid);
    $sth_old = db_execute($dbh, $sql_old);
    $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);

    // 执行删除
    $sql_del = "DELETE FROM departments WHERE id = $delid";
    db_exec($dbh, $sql_del);

    // ===================== 删除日志（同资产格式） =====================
    addOperateLog(
        'department',
        'delete',
        'Deleted department ID %s',
        array($delid),
        'department',
        $delid,
        $old_data,
        null,
        1,
        ''
    );

    echo "<script>document.location='$scriptname?action=listdepartments'</script>";
    echo "<a href='$scriptname?action=listdepartments'>".t("Click here if not redirected")."</a></body></html>";
    exit;
}

// 提交保存逻辑
$show_error = false;
$name_duplicate_error = false;
$name_duplicate_warning = false;

if (isset($_POST['id'])) {
    $id       = $_POST['id'];
    $name     = trim($_POST['name']);
    $parent_id= isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    
    // 排序号：不填则存NULL，而非0
    $sort_order = isset($_POST['sort_order']) ? trim($_POST['sort_order']) : '';
    $sort_order = $sort_order === '' ? 'NULL' : intval($sort_order);
    
    $description= isset($_POST['description']) ? $_POST['description'] : '';
    $force_save = isset($_POST['force_save']) ? intval($force_save) : 0;

    if (empty($name)) {
        $show_error = true;
    }
    if ($id != "new" && $parent_id == $id) {
        echo "<script>alert('".t("Error: Cannot set a department as its own parent.")."');history.go(-1);</script>";
        exit;
    }

    if (!$show_error) {
        $exclude_self = ($id != "new" && is_numeric($id)) ? " AND id != " . intval($id) : "";
        // 同级重名
        if ($parent_id > 0) {
            $sql_same = "SELECT id FROM departments 
                WHERE name = '" . addslashes($name) . "' 
                AND parent_id = $parent_id 
                $exclude_self 
                LIMIT 1";
        } else {
            $sql_same = "SELECT id FROM departments 
                WHERE name = '" . addslashes($name) . "' 
                AND (parent_id IS NULL OR parent_id = 0)
                $exclude_self 
                LIMIT 1";
        }
        $sth_same = db_execute($dbh, $sql_same);
        $dup_same = $sth_same->fetch(PDO::FETCH_ASSOC);
        if ($dup_same) {
            $name_duplicate_error = true;
        }
        // 跨级重名
        if (!$name_duplicate_error && !$force_save) {
            $sql_diff = "SELECT id FROM departments 
                WHERE name = '" . addslashes($name) . "' 
                $exclude_self 
                LIMIT 1";
            $sth_diff = db_execute($dbh, $sql_diff);
            $dup_diff = $sth_diff->fetch(PDO::FETCH_ASSOC);
            if ($dup_diff) {
                $name_duplicate_warning = true;
            }
        }

        // 保存
        if (!$name_duplicate_error && (!$name_duplicate_warning || $force_save)) {
            // 字段列表（用于日志对比）
            $dept_fields = array('id', 'name', 'parent_id', 'sort_order', 'description', 'created_time');

            if ($id == "new") {
                $current_time = date('Y-m-d H:i:s');
                $sql = "INSERT INTO departments (name, parent_id, sort_order, description, created_time)
                    VALUES (
                        '" . addslashes($name) . "',
                        " . ($parent_id > 0 ? $parent_id : 'NULL') . ",
                        $sort_order,
                        '" . addslashes($description) . "',
                        '$current_time'
                    )";
                db_exec($dbh, $sql, 0, 1, $lastid);
                $dept_id = $lastid;

                // 新增数据
                $new_data = array(
                    'id'            => $lastid,
                    'name'          => $name,
                    'parent_id'     => $parent_id,
                    'sort_order'    => $sort_order,
                    'description'   => $description,
                    'created_time'  => $current_time
                );

                // ===================== 新增日志 =====================
                addOperateLog(
                    'department',
                    'add',
                    'Created new department ID %s',
                    array($lastid),
                    'department',
                    $lastid,
                    null,
                    $new_data,
                    1,
                    ''
                );

                echo "<script>window.location='$scriptname?action=$action&id=$lastid'</script>";
                exit;
            } else {
                $dept_id = intval($id);
                // 旧数据
                $sql_old = "SELECT * FROM departments WHERE id = $dept_id";
                $sth_old = db_execute($dbh, $sql_old);
                $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);

                // 执行更新
                $sql = "UPDATE departments SET
                    name='" . addslashes($name) . "',
                    parent_id=" . ($parent_id > 0 ? $parent_id : 'NULL') . ",
                    sort_order=$sort_order,
                    description='" . addslashes($description) . "'
                    WHERE id=" . intval($id);
                db_exec($dbh, $sql);

                // 新数据
                $sql_new = "SELECT * FROM departments WHERE id = $dept_id";
                $sth_new = db_execute($dbh, $sql_new);
                $new_data = $sth_new->fetch(PDO::FETCH_ASSOC);

                // 对比差异（只记改动字段）
                $diff_old = array();
                $diff_new = array();
                foreach ($dept_fields as $k) {
                    $old_val = isset($old_data[$k]) ? $old_data[$k] : '';
                    $new_val = isset($new_data[$k]) ? $new_data[$k] : '';
                    if ((string)$old_val !== (string)$new_val) {
                        $diff_old[$k] = $old_val;
                        $diff_new[$k] = $new_val;
                    }
                }

                // ===================== 修改日志 =====================
                addOperateLog(
                    'department',
                    'update',
                    'Updated department ID %s',
                    array($dept_id),
                    'department',
                    $dept_id,
                    $diff_old,
                    $diff_new,
                    1,
                    ''
                );
            }
        }
    }
}

// 加载当前数据
if (!isset($_REQUEST['id'])) {
    $id = "new";
} else {
    $id = $_REQUEST['id'];
}
// 新增状态：不查询数据库
if ($id === "new") {
    $r = [];
} 
// 正常数字ID：查询
else {
    $id = (int)$id;
    $sql = "SELECT * FROM departments WHERE id = $id";
    $sth = db_execute($dbh, $sql);
    $r = $sth->fetch(PDO::FETCH_ASSOC);
    // 只有真的不存在才提示
    if (!$r && $id > 0) {
        echo "<script>alert('".t("ERROR: non-existent ID")."');history.go(-1);</script>";
        exit;
    }
}

// ===================== 新增：子部门查询 =====================
$child_depts = [];
if ($id != "new") {
    $sql_child = "SELECT id, name FROM departments WHERE parent_id = " . intval($id) . " ORDER BY sort_order ASC, id ASC";
    $sth_child = db_execute($dbh, $sql_child);
    $child_depts = $sth_child->fetchAll(PDO::FETCH_ASSOC);
}
// ===================== 新增：员工查询（工号+姓名） =====================
$employees = [];
if ($id != "new") {
    $sql_emp = "SELECT id, name, employee_code FROM employees WHERE department_id = " . intval($id) . " ORDER BY id ASC";
    $sth_emp = db_execute($dbh, $sql_emp);
    $employees = $sth_emp->fetchAll(PDO::FETCH_ASSOC);
}

// 构建部门树形下拉（排序：数字在前，NULL空值在后）
$sql_all = "SELECT id, name, parent_id FROM departments ORDER BY CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order ASC, id ASC";
$sth_all = db_execute($dbh, $sql_all);
$all_departments = $sth_all->fetchAll(PDO::FETCH_ASSOC);
$tree = buildTree($all_departments);

// 提交后保持选中的父部门
$current_parent = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : (isset($r['parent_id']) ? $r['parent_id'] : 0);
?>
<form id='mainform' method='post' action='<?php echo $scriptname . "?action=" . $action . "&id=" . $id; ?>'>
<?php
if ($id == "new") {
    echo "<h1>".t("Add new Department")."</h1>";
} else {
    echo "<h1>".sprintf(t("Edit Department (ID: %d)"), $id)."</h1>";
}
?>
<div class='errcontainer ui-state-error ui-corner-all' 
     style='padding: 0 .7em;width:700px;margin-bottom:3px; <?php echo ($show_error || $name_duplicate_error) ? '' : 'display:none;'; ?>'>
<p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span> 
<h4><?php echo t("Form submission error, please check the following fields:"); ?></h4>
<ol>
<?php
if ($show_error) {
    echo "<li><label class='error'>".t("Department Name is missing")."</label></li>";
}
if ($name_duplicate_error) {
    echo "<li><label class='error'>".t("Error: A department with the same name already exists under this parent.")."</label></li>";
}
?>
</ol>
</div>
<table style='width:100%' border='0'>
    <tr>
        <td class="tdtop" width="20%">
            <table class="tbl2" style='width:400px;'>
                <tr><td colspan=2><h3><?php echo t("Department Properties"); ?></h3></td></tr>
                <tr>
                    <td class="tdt"><?php echo t("ID"); ?>:</td>
                    <td>
                        <input style='display:none' type='text' name='id' value='<?php echo $id; ?>' readonly size='3'>
                        <?php if ($id == "new") echo t("New"); else echo $id; ?>
                    </td>
                </tr>
                <tr>
                    <td class="tdt"><?php echo t("Department Name"); ?><sup class='red'>*</sup>:</td>
                    <td>
                        <input class='input2 mandatory' id='name' type='text' name='name' size='30' 
                            value="<?php echo htmlspecialchars(isset($_POST['name']) ? $_POST['name'] : $r['name']); ?>">
                    </td>
                </tr>
                <tr>
                    <td class="tdt"><?php echo t("Sort Order"); ?>:</td>
                    <td>
                        <input class='input2' size='10' type='text' name='sort_order' 
                            value="<?php echo isset($r['sort_order']) ? $r['sort_order'] : ''; ?>">
                    </td>
                </tr>
                <tr>
                    <td class="tdt"><?php echo t("Parent Department"); ?>:</td>
                    <td>
						<select name='parent_id' id='parent_id' style='width:155px;'>
						    <option value='0'>--- <?php echo t("Top Level Department"); ?> ---</option>
						    <?php
						    foreach ($tree as $dept) {
						        if ($dept['id'] == $id) continue;
						        $depth = $dept['depth'];
						        
						        // 核心修正：一级不显示，二级及以下才显示 ┕┉
						        if ($depth == 0) {
						            // 一级部门：直接显示名称，无前缀
						            $prefix = '';
						        } else {
						            // 二级/三级/四级：前面空格 + 末尾一个 ┕┉
						            $prefix = str_repeat('　　', $depth - 1) . '┕┉';
						        }
						        
						        $selected = ($dept['id'] == $current_parent) ? 'selected' : '';
						        echo "<option value='{$dept['id']}' $selected>{$prefix}{$dept['name']}</option>";
						    }
						    ?>
						</select>
                    </td>
                </tr>
                <tr>
                    <td class="tdt"><?php echo t("Description"); ?>:</td>
                    <td>
                        <textarea class='input2' name='description' rows='4' cols='30'><?php echo htmlspecialchars($r['description']); ?></textarea>
                    </td>
                </tr>
				<?php if ($id != "new") { ?>
					<tr>
					    <td class="tdt"><?php echo t("Created Time"); ?>:</td>
					    <td>
					        <?php echo isset($r['created_time']) ? $r['created_time'] : t("Unknown"); ?>
					    </td>
					</tr>
	                <tr>
	                    <td class="tdt"><?php echo t("Associated Employees"); ?>:</td>
	                    <td>
	                        <?php 
	                        $emp_sql = "SELECT count(*) as cnt FROM employees WHERE department_id = " . intval($id);
	                        $emp_sth = db_execute($dbh, $emp_sql);
	                        $emp = $emp_sth->fetch(PDO::FETCH_ASSOC);
	                        echo sprintf(t("%d Employees"), $emp['cnt']);
	                        ?>
	                    </td>
	                </tr>
				<?php } ?>
            </table>
        </td>
        
<!-- ===================== 右侧：子部门 + 员工列表 ===================== -->
<td style='padding-left:10px; border-left:1px dashed #aaa; vertical-align:top; width:350px'>
<?php if ($id != "new") { ?>
    <!-- 子部门 -->
    <div style="margin-bottom:15px;">
        <h3 style="margin:0 0 5px 0; font-size:14px;"><?php echo t("Sub-departments"); ?></h3>
        <div class="tbl2" style="padding:5px; max-height:150px; overflow-y:auto;">
        <?php if (empty($child_depts)): ?>
            <?php echo t("No sub-departments"); ?>
        <?php else: ?>
            <?php foreach ($child_depts as $c): ?>
                <div style="padding:2px 0;">
                    <a href="?action=editdepartments&id=<?php echo $c['id']; ?>">
                        <?php echo htmlspecialchars($c['name']); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
    <!-- 员工列表（工号+姓名+可点击编辑） -->
    <div>
        <h3 style="margin:0 0 5px 0; font-size:14px;"><?php echo t("Employees"); ?></h3>
        <div class="tbl2" style="padding:5px; max-height:250px; overflow-y:auto;">
        <?php if (empty($employees)): ?>
            <?php echo t("No employees"); ?>
        <?php else: ?>
			<?php
			$emp_names = [];
			foreach ($employees as $e) {
			    $emp_names[] = '<a href="?action=editemployees&id='.$e['id'].'">['.htmlspecialchars($e['employee_code']).'] '.htmlspecialchars($e['name']).'</a>';
			}
			echo implode(' ｜ ', $emp_names);
			?>
        <?php endif; ?>
        </div>
    </div>
<?php } else { ?>
    <p><small><?php echo t("Tip: Please fill in the department name and select the parent department."); ?></small></p>
<?php } ?>
</td>
    </tr>
    <tr>
        <td colspan='2'>
            <button type="submit">
                <img src="images/save.png" alt='<?php echo t("Save"); ?>'> <?php echo t("Save"); ?>
            </button>
<?php if ($id != "new") { ?>
            <button type="button" onclick="javascript:if(confirm('<?php echo t("Are you sure you want to delete this department?"); ?>')){window.location='<?php echo $scriptname; ?>?action=<?php echo $action; ?>&delid=<?php echo $id; ?>';}">
                <img src="images/delete.png" border='0'> <?php echo t("Delete"); ?>
            </button>
<?php } ?>
        </td>
    </tr>
</table>
<input type='hidden' name='force_save' id='force_save' value='0'>
</form>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('mainform');
    var name = document.getElementById('name');
    var force_save = document.getElementById('force_save');
    form.onsubmit = function(){
        if (!name.value.trim()) {
            alert('<?php echo t("Department Name is missing"); ?>');
            name.focus();
            return false;
        }
        return true;
    };
<?php if ($name_duplicate_error) { ?>
        alert('<?php echo t("Error: A department with the same name already exists under this parent."); ?>');
<?php } ?>
<?php if ($name_duplicate_warning) { ?>
        if (confirm('<?php echo t("Department with the same name exists in another department. Are you sure to continue?"); ?>')) {
            force_save.value = '1';
            form.submit();
        }
<?php } ?>
});
</script>
</body></html>
