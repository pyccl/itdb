<?php 
// === 配置区 === 
$db_path = '../data/itdb.db'; 
// === 连接数据库 === 
if (!file_exists($db_path)) { 
    die("❌ 错误：找不到数据库文件 '$db_path'，请检查路径！"); 
} 
try { 
    $pdo = new PDO("sqlite:" . $db_path); 
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    echo "✅ 成功连接到数据库。<br>"; 
} catch (Exception $e) { 
    die("❌ 连接数据库失败: " . $e->getMessage()); 
} 
// === 核心修复逻辑：Users 表 (原有逻辑) === 
echo "<h3>1. 正在检查 Users 表...</h3>";
$columns = []; 
$stmt = $pdo->query("PRAGMA table_info(users)"); 
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
    $columns[] = $row['name']; 
} 
echo "🔍 当前 users 表包含的字段：<b>" . implode(", ", $columns) . "</b><br><br>"; 
$target_fields = [ 
    'dbpass' => 'TEXT DEFAULT NULL', 
    'dbpasstime' => 'TEXT DEFAULT NULL',
    'dashboard_cards' => 'TEXT DEFAULT NULL'
]; 
$modified = false; 
foreach ($target_fields as $fname => $ftype) { 
    if (!in_array($fname, $columns)) { 
        $sql = "ALTER TABLE users ADD COLUMN $fname $ftype"; 
        try { 
            $pdo->exec($sql); 
            echo "✅ 成功添加字段：<b>$fname</b><br>"; 
            $modified = true; 
        } catch (Exception $e) { 
            echo "❌ 添加字段 $fname 失败: " . $e->getMessage() . "<br>"; 
        } 
    } else { 
        echo "⚠️ 字段 <b>$fname</b> 已经存在，跳过。<br>"; 
    } 
} 
if ($modified) { 
    echo "<br><strong style='color:green;'>🎉 Users 表修复完成！字段已补全。</strong><br><br>";
} else { 
    echo "<br><strong style='color:orange;'>✅ Users 表无需修复。</strong><br><br>";
} 
// =========================================================================
// === 新增逻辑：Statustypes 表修复 ===
// =========================================================================
echo "<h3>2. 正在检查 Statustypes 表...</h3>";
// 1. 检查 statustypes 表是否存在
$table_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='statustypes'");
if ($table_check->fetch()) {
    // 表存在，检查字段
    $stype_columns = [];
    $stmt2 = $pdo->query("PRAGMA table_info(statustypes)");
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $stype_columns[] = $row['name'];
    }
    echo "🔍 当前 statustypes 表包含的字段：<b>" . implode(", ", $stype_columns) . "</b><br><br>";
    // 2. 定义需要添加的字段
    $target_stype_field = 'color';
    $field_type = 'TEXT DEFAULT NULL'; // 颜色字段定义
    if (!in_array($target_stype_field, $stype_columns)) {
        $sql2 = "ALTER TABLE statustypes ADD COLUMN $target_stype_field $field_type";
        try {
            $pdo->exec($sql2);
            echo "✅ 成功添加字段：<b>$target_stype_field</b><br>";
            echo "<strong style='color:green;'>🎉 Statustypes 表修复完成！已添加 color 字段。</strong>";
        } catch (Exception $e) {
            echo "❌ 添加字段 $target_stype_field 失败: " . $e->getMessage() . "<br>";
            echo "<strong style='color:red;'>⚠️ Statustypes 表修复失败。</strong>";
        }
    } else {
        echo "⚠️ 字段 <b>$target_stype_field</b> 已经存在，跳过。<br>";
        echo "<strong style='color:orange;'>✅ Statustypes 表无需修复。</strong>";
    }
} else {
    echo "❌ 错误：找不到 statustypes 表，请确认数据库是否正确！<br>";
    echo "<strong style='color:red;'>⚠️ 请检查数据库文件是否包含 statustypes 表。</strong>";
}
// =========================================================================
// === 新增逻辑：Items 表修复 (合并 internalid, custom_user, custom_dept) ===
// =========================================================================
echo "<h3>3. 正在检查 Items 表...</h3>";
// 1. 检查 items 表是否存在
$table_check_items = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='items'");
if ($table_check_items->fetch()) {
    // 表存在，检查字段
    $items_columns = [];
    $stmt3 = $pdo->query("PRAGMA table_info(items)");
    while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
        $items_columns[] = $row['name'];
    }
    echo "🔍 当前 items 表包含的字段：<b>" . implode(", ", $items_columns) . "</b><br><br>";
    // 2. 定义所有需要确保存在的字段
    // 格式: '字段名' => '字段类型定义'
    $target_items_fields = [
        'internalid'  => 'TEXT', // 原有字段：内部ID
        'custom_user' => 'INTEGER', // 新增字段：使用人
        'custom_dept' => 'INTEGER' // 新增字段：部门
    ];
    
    $modified_items = false;
    foreach ($target_items_fields as $fname => $ftype) {
        if (!in_array($fname, $items_columns)) {
            $sql3 = "ALTER TABLE items ADD COLUMN $fname $ftype";
            try {
                $pdo->exec($sql3);
                echo "✅ 成功添加字段：<b>$fname</b><br>";
                $modified_items = true;
            } catch (Exception $e) {
                echo "❌ 添加字段 $fname 失败: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "⚠️ 字段 <b>$fname</b> 已经存在，跳过。<br>";
        }
    }
    
    if ($modified_items) {
        echo "<br><strong style='color:green;'>🎉 Items 表修复完成！缺失字段已自动补全。</strong>";
    } else {
        echo "<br><strong style='color:orange;'>✅ Items 表结构完整，无需修复。</strong>";
    }
} else {
    echo "❌ 错误：找不到 items 表，请确认数据库是否正确！<br>";
    echo "<strong style='color:red;'>⚠️ 请检查数据库文件是否包含 items 表。</strong>";
}
// =========================================================================
// === 新增逻辑：Settings 表修复 (检查并添加 timeformat 字段) ===
// =========================================================================
echo "<h3>4. 正在检查 Settings 表...</h3>"; 
// 1. 检查 settings 表是否存在
$table_check_settings = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
if ($table_check_settings->fetch()) {
    // 表存在，检查字段
    $settings_columns = [];
    $stmt4 = $pdo->query("PRAGMA table_info(settings)");
    while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
        $settings_columns[] = $row['name'];
    }
    echo "🔍 当前 settings 表包含的字段：<b>" . implode(", ", $settings_columns) . "</b><br><br>";
    // 2. 定义需要检查/添加的字段
    $target_settings_field = 'timeformat';
    $field_type = "TEXT DEFAULT 'H:i:s'"; // 默认时间格式为 时:分:秒
    if (!in_array($target_settings_field, $settings_columns)) {
        $sql4 = "ALTER TABLE settings ADD COLUMN $target_settings_field $field_type";
        try {
            $pdo->exec($sql4);
            // 尝试插入一个默认值（可选）
            $pdo->exec("UPDATE settings SET timeformat='H:i:s' WHERE timeformat IS NULL OR timeformat=''");
            echo "✅ 成功添加字段：<b>$target_settings_field</b>，并设置默认值 'H:i:s'<br>";
            echo "<strong style='color:green;'>🎉 Settings 表修复完成！已添加 timeformat 字段。</strong>";
        } catch (Exception $e) {
            echo "❌ 添加字段 $target_settings_field 失败: " . $e->getMessage() . "<br>";
            echo "<strong style='color:red;'>⚠️ Settings 表修复失败。</strong>";
        }
    } else {
        echo "⚠️ 字段 <b>$target_settings_field</b> 已经存在，跳过。<br>";
        // 检查是否有值，如果没有则补充
        $checkVal = $pdo->query("SELECT timeformat FROM settings WHERE timeformat IS NOT NULL AND timeformat != '' LIMIT 1");
        if (!$checkVal->fetch()) {
            $pdo->exec("UPDATE settings SET timeformat='H:i:s' WHERE timeformat IS NULL OR timeformat=''");
            echo "💡 提示：检测到 timeformat 字段无值，已尝试填充默认值 'H:i:s'。<br>";
        }
        echo "<strong style='color:orange;'>✅ Settings 表无需修复。</strong>";
    }
} else {
    echo "❌ 错误：找不到 settings 表，请确认数据库是否正确！<br>";
    echo "<strong style='color:red;'>⚠️ 请检查数据库文件是否包含 settings 表。</strong>";
}
// =========================================================================
// === 新增逻辑：创建 Departments (部门) 和 Employees (员工) 表 ===
// =========================================================================
echo "<h3>5. 正在检查并创建 Departments 和 Employees 表...</h3>";
// --- 1. 创建部门表 (Departments) ---
// 检查 departments 表是否存在
$table_check_dept = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='departments'");
if (!$table_check_dept->fetch()) {
    $sql_dept = "
    CREATE TABLE departments (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        name            TEXT,
        parent_id       INTEGER,
        sort_order      INTEGER,
        description     TEXT,
        created_time    INTEGER
    );";
    try {
        $pdo->exec($sql_dept);
        echo "✅ 成功创建表：<b>departments</b><br>";
    } catch (Exception $e) {
        echo "❌ 创建 departments 表失败: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ 表 <b>departments</b> 已经存在，跳过创建。<br>";
}
// --- 2. 创建员工表 (Employees) ---
// 检查 employees 表是否存在
$table_check_emp = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='employees'");
if (!$table_check_emp->fetch()) {
    $sql_emp = "
    CREATE TABLE employees (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        name              TEXT,
        employee_code     TEXT,
        department_id     INTEGER,
        position          TEXT,
        email             TEXT,
        phone             TEXT,
        hire_date         INTEGER,
        status            INTEGER,
        created_time      INTEGER,
        -- 🔑 定义外键约束：链接到 departments 表的 id 字段
        FOREIGN KEY (department_id) REFERENCES departments(id)
    );";
    try {
        $pdo->exec($sql_emp);
        echo "✅ 成功创建表：<b>employees</b>，并建立了外键约束。<br>";
    } catch (Exception $e) {
        echo "❌ 创建 employees 表失败: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ 表 <b>employees</b> 已经存在，跳过创建。<br>";
    
    // --- 2.1 表已存在时的修复逻辑：检查并添加外键约束 ---
    // 注意：SQLite 默认不支持直接 ALTER TABLE 添加外键，需要重建表
    // 这里我们做一个简单的检查，提示用户需要重建表
    $stmt_fk = $pdo->query("PRAGMA foreign_key_list(employees)");
    $fks = $stmt_fk->fetchAll(PDO::FETCH_ASSOC);
    $has_fk = false;
    foreach ($fks as $fk) {
        if ($fk['table'] == 'departments' && $fk['to'] == 'id') {
            $has_fk = true;
            break;
        }
    }
    if (!$has_fk) {
        echo "💡 提示：检测到 employees 表存在，但缺少外键约束。<br>";
        echo "    ⚠️ 修复建议：由于 SQLite 限制，建议导出数据后删除 employees 表，重新运行此脚本以建立完整的外键约束。<br>";
    }
}
if (!isset($has_fk) || $has_fk) {
    echo "<strong style='color:green;'>🎉 表结构检查完成。</strong>";
}
// =========================================================================
// === 新增逻辑：创建 Operate Log 操作日志表 ===
// =========================================================================
echo "<h3>6. 正在检查并创建 Operate Log 操作日志表...</h3>";
// 检查 operate_log 表是否存在
$table_check_log = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='operate_log'");
if (!$table_check_log->fetch()) {
    $sql_log = "
    CREATE TABLE operate_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module VARCHAR(30) NOT NULL,
        operate_type VARCHAR(30) NOT NULL,
        operate_user VARCHAR(50),
        user_id INTEGER DEFAULT 0,
        operate_time DATETIME DEFAULT (datetime('now','localtime')),
        ip VARCHAR(50),
        target_type VARCHAR(30) DEFAULT '',
        target_id INTEGER DEFAULT 0,
        old_value TEXT DEFAULT '',
        new_value TEXT DEFAULT '',
        content VARCHAR(255) NOT NULL,
        params TEXT DEFAULT '',
        status TINYINT DEFAULT 1,
        fail_reason TEXT DEFAULT '',
        request_url TEXT DEFAULT '',
        user_agent TEXT DEFAULT ''
    );";
    try {
        $pdo->exec($sql_log);
        echo "✅ 成功创建表：<b>operate_log</b><br>";
        echo "<strong style='color:green;'>🎉 操作日志表创建完成！</strong>";
    } catch (Exception $e) {
        echo "❌ 创建 operate_log 表失败: " . $e->getMessage() . "<br>";
        echo "<strong style='color:red;'>⚠️ operate_log 表创建失败。</strong>";
    }
} else {
    echo "⚠️ 表 <b>operate_log</b> 已经存在，跳过创建。<br>";
    echo "<strong style='color:orange;'>✅ operate_log 表无需创建。</strong>";
}
// =========================================================================
// === 新增逻辑：labelpapers 表添加字段 print_fields, custom_w, custom_h
// =========================================================================
echo "<h3>7. 正在检查 labelpapers 表...</h3>";
// 检查 labelpapers 表是否存在
$table_check_label = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='labelpapers'");
if ($table_check_label->fetch()) {
    // 表存在，检查现有字段
    $label_columns = [];
    $stmt7 = $pdo->query("PRAGMA table_info(labelpapers)");
    while ($row = $stmt7->fetch(PDO::FETCH_ASSOC)) {
        $label_columns[] = $row['name'];
    }
    echo "🔍 当前 labelpapers 表包含的字段：<b>" . implode(", ", $label_columns) . "</b><br><br>";
    // 需要添加的3个字段（严格按你要求）
    $target_label_fields = [
        'print_fields' => 'TEXT DEFAULT NULL',
        'custom_w'     => 'INTEGER DEFAULT NULL',
        'custom_h'     => 'INTEGER DEFAULT NULL',
        'labelskip'    => 'INTEGER DEFAULT 0'
    ];
    $modified_label = false;
    foreach ($target_label_fields as $fname => $ftype) {
        if (!in_array($fname, $label_columns)) {
            $sql7 = "ALTER TABLE labelpapers ADD COLUMN $fname $ftype";
            try {
                $pdo->exec($sql7);
                echo "✅ 成功添加字段：<b>$fname</b><br>";
                $modified_label = true;
            } catch (Exception $e) {
                echo "❌ 添加字段 $fname 失败: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "⚠️ 字段 <b>$fname</b> 已经存在，跳过。<br>";
        }
    }
    if ($modified_label) {
        echo "<br><strong style='color:green;'>🎉 labelpapers 表修复完成！缺失字段已自动补全。</strong>";
    } else {
        echo "<br><strong style='color:orange;'>✅ labelpapers 表结构完整，无需修复。</strong>";
    }
} else {
    echo "❌ 错误：找不到 labelpapers 表，请确认数据库是否正确！<br>";
    echo "<strong style='color:red;'>⚠️ 请检查数据库文件是否包含 labelpapers 表。</strong>";
}
// =========================================================================
// === 新增逻辑：创建 dashboard_cards 表 ===
// =========================================================================
echo "<h3>8. 正在检查并创建 dashboard_cards 表...</h3>";
// 检查 dashboard_cards 表是否存在
$table_check_dashboard = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='dashboard_cards'");
if (!$table_check_dashboard->fetch()) {
    $sql_dashboard = "
    CREATE TABLE dashboard_cards (
        id          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
        key_name    TEXT NOT NULL,
        title       TEXT NOT NULL,
        icon        TEXT NOT NULL,
        color       TEXT NOT NULL,
        count_sql   TEXT,
        link_url    TEXT,
        sort        INTEGER DEFAULT 0,
        status      INTEGER DEFAULT 1
    );";
    try {
        $pdo->exec($sql_dashboard);
        echo "✅ 成功创建表：<b>dashboard_cards</b><br>";
        echo "<strong style='color:green;'>🎉 dashboard_cards 表创建完成！</strong>";
    } catch (Exception $e) {
        echo "❌ 创建 dashboard_cards 表失败: " . $e->getMessage() . "<br>";
        echo "<strong style='color:red;'>⚠️ dashboard_cards 表创建失败。</strong>";
    }
} else {
    echo "⚠️ 表 <b>dashboard_cards</b> 已经存在，跳过创建。<br>";
    echo "<strong style='color:orange;'>✅ dashboard_cards 表无需创建。</strong>";
}

echo "<br><br><small>✅ 脚本执行完毕。请刷新你的管理页面，或者删除此文件以防安全隐患。</small>";
?>
