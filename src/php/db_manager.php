<?php
// if (!isset($initok)) {
    // require_once __DIR__ . '/../init.php';
    // exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
// }
// 设置时区
date_default_timezone_set('Asia/Shanghai');

// --- 配置区 ---
$db_path = '../data/itdb.db'; 
$backup_dir = '../databackups/';

// 确保备份目录存在
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$message = "";
$executed_sql = ""; // <<< 新增：用于存储最后执行的SQL语句
$mode = "readonly"; // 默认模式

// --- 模式切换逻辑 ---
session_start();
if (isset($_POST['set_mode']) && $_POST['set_mode'] == 'edit') {
    $_SESSION['db_mode'] = 'edit';
    $mode = "edit";
} elseif (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
} else {
    $mode = isset($_SESSION['db_mode']) ? $_SESSION['db_mode'] : 'readonly';
}

// --- 数据库连接 (使用 PDO) ---
try {
    if (!file_exists($db_path)) {
        $fp = fopen($db_path, 'w');
        fclose($fp);
    }
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA foreign_keys = ON"); // ✅ 开启外键约束
	// ✅ 强化锁处理
	$pdo->exec("PRAGMA locking_mode = EXCLUSIVE"); // 尝试使用独占锁模式，减少冲突
	$pdo->exec("PRAGMA busy_timeout = 10000"); // 增加到 10秒
	
// -----------------------------------------

} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// --- 权限验证逻辑 (修改版：含具体过期时间) ---
if (isset($_POST['set_mode']) && $_POST['set_mode'] == 'edit') {
    $input_username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $input_password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // 默认验证失败
    $auth_success = false;
    
    if (!empty($input_username) && !empty($input_password)) {
        try {
            $stmt = $pdo->prepare("SELECT dbpass, dbpasstime FROM users WHERE username = ?");
            $stmt->execute([$input_username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 验证密码 (假设明文存储)
            if ($user && $user['dbpass'] === $input_password) {
                // 验证时间
                if (empty($user['dbpasstime']) || strtotime($user['dbpasstime']) >= time()) {
                    $auth_success = true;
                } else {
                    // ✅ ✅ ✅ 核心修改：这里构建包含具体时间的错误信息
                    $expire_time = $user['dbpasstime']; // 例如：2024-01-01 00:00:00
                    // 格式化时间显示（例如：2024年01月01日 00:00）
                    // $formatted_time = date('Y年m月d日 H:i', strtotime($expire_time));
                    
                    // 设置红色的错误提示信息
                    $_SESSION['db_login_error'] = "密码已于 <strong>{$expire_time}</strong> 过期，请重新生成！";
                }
            } else {
                $_SESSION['db_login_error'] = "用户名或密码错误。";
            }
        } catch (PDOException $e) {
            $_SESSION['db_login_error'] = "系统错误: " . $e->getMessage();
        }
    } else {
        $_SESSION['db_login_error'] = "请输入用户名和密码。";
    }
    
    // ... 后续的跳转逻辑保持不变 (无需修改) ...


    // 处理跳转
    if ($auth_success) {
        $_SESSION['db_mode'] = 'edit';
        $_SESSION['db_admin_user'] = $input_username;
        // 清除错误信息
        unset($_SESSION['db_login_error']);
    } else {
        // 确保是未登录状态
        unset($_SESSION['db_mode']);
    }

    // 关键：无论成功失败，都跳转回原页面（清除 POST 数据）
    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_GET['table'])) {
        $redirect_url .= '?table=' . urlencode($_GET['table']);
    }
    header("Location: " . $redirect_url);
    exit;
}


// --- 原有的模式切换逻辑 (修改) ---
// 修改原有的直接设置 Session 的逻辑，增加 Session 存在时的二次检查
if (!isset($_SESSION['db_mode']) || $_SESSION['db_mode'] != 'edit') {
    $mode = 'readonly';
} else {
    // 即使 Session 存在，也最好检查一下数据库里的权限是否过期（可选增强）
    // 这里为了性能先不做实时检查，仅依赖登录时的检查。如果需要实时检查，需查询 users 表。
    $mode = 'edit';
}


// --- 工具函数 ---
// --- 新增函数：解析表结构获取外键约束 ---
function get_table_foreign_keys($pdo, $table_name) {
	$fk_info = array();
	try {
	    // ✅ 使用 SQLite 专用的 PRAGMA 命令查询外键
	    // 这是 100% 准确的方法，不需要解析 SQL 文本
	    $stmt = $pdo->query("PRAGMA foreign_key_list({$table_name})");
	    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	    
	    // 如果查询到了外键记录
	    if ($rows) {
	        foreach ($rows as $row) {
	            // $row 包含以下关键信息：
	            // 'from' => 当前表的字段名
	            // 'table' => 关联的外部表名
	            // 'to' => 关联的外部字段名
	            
	            $from_col = $row['from']; // 例如：user_id
	            $to_table = $row['table']; // 例如：users
	            $to_col = $row['to'];      // 例如：id
	            
	            // ✅ 构造显示格式：users(id)
	            // 使用 $from_col 作为数组的键，确保只有对应的字段才显示
	            $fk_info[$from_col] = "{$to_table}({$to_col})";
	        }
	    }
	    // 如果没查到，$fk_info 就是空数组，显示为 -
	} catch (Exception $e) {
	    // 如果 PRAGMA 命令出错（极少见），则返回空
	    // 注意：这里千万不要写默认值，否则又会变成所有字段都是 users(id)
	}
	return $fk_info;
	
}

function backup_db($src, $dir) {
    if (!file_exists($src)) return false;
    $timestamp = date('Y-m-d_H-i-s');
    $dest = $dir . 'backup_' . $timestamp . '.db';
    return copy($src, $dest) ? $dest : false;
}
// --- 手动备份与管理逻辑 (修复版：保留Table和分页状态) ---
// 1. 手动备份处理
if (isset($_POST['manual_backup'])) {
    if ($mode == 'edit') {
        $backup_dir = '../databackups/';
        $backup_file = $backup_dir . 'manual_' . date('Y-m-d_H-i-s') . '.db';
        // 使用 manual_ 前缀
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        if (copy($db_path, $backup_file)) {
            $_SESSION['db_operation_message'] = "手动备份成功！文件: " . basename($backup_file);
        } else {
            $_SESSION['db_operation_error'] = "手动备份失败！";
        }
        
        // ✅ ✅ ✅ 修改：构建保留状态的跳转 URL
        $redirect_url = $_SERVER['PHP_SELF'] . '?';
        $params = array();
        if (isset($_GET['table'])) $params[] = 'table=' . urlencode($_GET['table']);
        if (isset($_GET['dpage'])) $params[] = 'dpage=' . urlencode($_GET['dpage']);
        if (isset($_GET['cpage'])) $params[] = 'cpage=' . urlencode($_GET['cpage']);
        if (isset($_GET['fpage'])) $params[] = 'fpage=' . urlencode($_GET['fpage']); // 保留当前就在备份页的页码
        $redirect_url .= implode('&', $params);
        
        header("Location: " . $redirect_url);
        exit;
    }
}

// --- 处理删除单个备份文件 (修复版) ---
if (isset($_POST['delete_single_backup'])) {
    if ($mode == 'edit') {
        $backup_dir = '../databackups/';
        $file_to_delete = basename($_POST['file_path']);
        $full_path = $backup_dir . $file_to_delete;

        // 验证文件是否存在且在指定目录内
        if (file_exists($full_path) && is_file($full_path)) {
            // 尝试删除
            if (unlink($full_path)) {
                $_SESSION['db_operation_message'] = "🗑️ 成功删除备份文件: $file_to_delete";
            } else {
                $_SESSION['db_operation_error'] = "❌ 删除失败，可能是文件权限问题: $file_to_delete";
            }
        } else {
            $_SESSION['db_operation_error'] = "❌ 文件不存在: $file_to_delete";
        }

        // ✅ ✅ ✅ 修改：构建保留状态的跳转 URL
        // 注意：这里我们希望停留在备份列表页，所以强制带上 fpage 参数相关的状态
        $redirect_url = $_SERVER['PHP_SELF'] . '?';
        $params = array();
        
        // 保留当前查看的表
        if (isset($_GET['table'])) $params[] = 'table=' . urlencode($_GET['table']);
        
        // 保留数据列表页码
        if (isset($_GET['dpage'])) $params[] = 'dpage=' . urlencode($_GET['dpage']);
        
        // 保留结构列表页码
        if (isset($_GET['cpage'])) $params[] = 'cpage=' . urlencode($_GET['cpage']);
        
        // ⭐ 核心：保留备份列表的当前页码 (fpage)
        // 如果没有指定，通常保持在当前页，或者如果删除后当前页数据不足，逻辑会自动处理
        $current_fpage = isset($_GET['fpage']) ? $_GET['fpage'] : 1;
        // 如果删除的是当前页的最后一个文件，我们需要回退到上一页
        // 简单逻辑：如果当前页码大于1，且删除后该页没数据了，就 $current_fpage - 1，这里为了简化，直接保留原 fpage，前端分页逻辑会自动处理越界显示为空的情况
        $params[] = 'fpage=' . urlencode($current_fpage);

        $redirect_url .= implode('&', $params);
        
        header("Location: " . $redirect_url);
        exit;
    }
}

// 2. 批量删除备份文件
if (isset($_POST['delete_backups'])) {
    if ($mode == 'edit') {
        $files_to_delete = $_POST['backup_files']; // 这是一个数组
        $deleted_count = 0;
        
        foreach ($files_to_delete as $file) {
            // 安全检查：确保文件在备份目录内
            if (file_exists($file) && strpos(os_path($file), os_path($backup_dir)) === 0) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        $_SESSION['db_operation_message'] = "成功删除 $deleted_count 个备份文件。";

        // ✅ ✅ ✅ 修改：构建保留状态的跳转 URL
        $redirect_url = $_SERVER['PHP_SELF'] . '?';
        $params = array();
        if (isset($_GET['table'])) $params[] = 'table=' . urlencode($_GET['table']);
        if (isset($_GET['dpage'])) $params[] = 'dpage=' . urlencode($_GET['dpage']);
        if (isset($_GET['cpage'])) $params[] = 'cpage=' . urlencode($_GET['cpage']);
        if (isset($_GET['fpage'])) $params[] = 'fpage=' . urlencode($_GET['fpage']);
        $redirect_url .= implode('&', $params);
        
        header("Location: " . $redirect_url);
        exit;
    }
}


// 3. 下载单个文件 (直接输出)
if (isset($_GET['download_file'])) {
    if ($mode == 'edit') {
        $file = $_GET['download_file'];
        if (file_exists($file) && strpos(os_path($file), os_path($backup_dir)) === 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.basename($file));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } else {
            $_SESSION['db_operation_error'] = "文件不存在或无权访问。";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// 4. 批量下载为 ZIP (新增功能)
if (isset($_POST['download_zip'])) {
    if ($mode == 'edit') {
        $files_to_zip = $_POST['backup_files'];
        if (count($files_to_zip) == 0) {
            $_SESSION['db_operation_error'] = "未选择任何文件。";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }

        // 创建临时 ZIP 文件
        $tmp_zip = sys_get_temp_dir() . '/db_backups_' . time() . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($tmp_zip, ZipArchive::CREATE) !== TRUE) {
            $_SESSION['db_operation_error'] = "无法创建临时压缩文件。";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }

        foreach ($files_to_zip as $file) {
            if (file_exists($file)) {
                $zip->addFile($file, basename($file)); // 只添加文件名，不包含路径
            }
        }
        $zip->close();

        // 如果压缩成功，读取并输出
        if (file_exists($tmp_zip)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=Database_Backups_' . date('Y-m-d') . '.zip');
            header('Content-Length: ' . filesize($tmp_zip));
            readfile($tmp_zip);
            
            // 删除临时文件
            unlink($tmp_zip);
            exit;
        }
    }
}

// 辅助函数：路径标准化 (兼容 PHP 5.2)
function os_path($path) {
    return str_replace('\\', '/', realpath($path));
}

// --- 获取备份文件列表 (修改原有逻辑) ---
// 我们需要解析文件名来判断是手动还是自动，并获取时间
$backup_files = array();
$backup_dir = '../databackups/';

if (file_exists($backup_dir)) {
    $files = glob($backup_dir . "*.*");
    if ($files) {
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'db') {
                $filename = basename($file);
                $filesize = filesize($file);
                $filetime = filemtime($file);
                
                // 判断备份类型 (根据文件名前缀)
                if (strpos($filename, 'manual_') === 0) {
                    $type = '手动';
                } elseif (strpos($filename, 'backup_') === 0) {
                    $type = '自动';
                } else {
                    $type = '未知';
                }
                
                $backup_files[] = array(
                    'path' => $file,
                    'name' => $filename,
                    'type' => $type,
                    'time' => $filetime,
                    'size' => $filesize
                );
            }
        }
        // 按时间倒序排列 (最新的在前)
        // PHP 5.2 不支持匿名函数排序，需使用自定义函数
        usort($backup_files, 'sort_by_time_desc');
    }
}

// 自定义排序函数
function sort_by_time_desc($a, $b) {
    if ($a['time'] == $b['time']) return 0;
    return ($a['time'] < $b['time']) ? 1 : -1; // 降序
}

// --- 写入操作保护 ---
if ($mode == 'edit') {

//1. --- 处理新建表 (修复版：增加备份、提示及SQL显示) ---
if (isset($_POST['create_table'])) {
    $new_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['new_table_name']);
    
    if (empty($new_table_name)) {
        $_SESSION['db_operation_error'] = "错误：表名不能为空！";
    } else {
        try {
            // --- ✅ 新增：执行创建表前先备份 (关键步骤) ---
            backup_db($db_path, $backup_dir);
            
            // 检查表是否已存在
            $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$new_table_name'");
            if ($check->fetch()) {
                $_SESSION['db_operation_error'] = "错误：表名 '$new_table_name' 已存在！";
            } else {
                // --- 1. 构建 SQL 语句 ---
                $sql = "CREATE TABLE $new_table_name (id INTEGER PRIMARY KEY AUTOINCREMENT)";
                
                // --- 2. 执行创建表 ---
                $pdo->exec($sql);
                
                // --- 3. 记录日志到 history 表 ---
                try {
                    // 获取当前操作用户
                    $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
                    $auth_str = $current_user . " (datebase)";
                    $client_ip = $_SERVER['REMOTE_ADDR'];
                    
                    // 准备插入日志的 SQL
                    $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
                    // 执行插入
                    $log_stmt->execute(array(
                        time(),
                        $sql,
                        $auth_str,
                        $client_ip
                    ));
                } catch (Exception $e) {
                    error_log("History Log Failed: " . $e->getMessage());
                }
                
                // --- 4. 设置成功提示 (包含 SQL) ---
                // htmlspecialchars 用于防止 SQL 中的特殊字符破坏 HTML 标签
                $_SESSION['db_operation_message'] = "🎉 表 '$new_table_name' 创建成功！执行SQL: <code>" . htmlspecialchars($sql) . "</code>";
                
                // 刷新表列表
                $tables[] = $new_table_name;
                $current_table = $new_table_name;
            }
        } catch (PDOException $e) {
            $_SESSION['db_operation_error'] = "创建失败: " . $e->getMessage();
        }
    }
    // --- 5. 跳转刷新 ---
    header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($current_table));
    exit;
}

    // 2. 处理数据删除
    if (isset($_GET['delete'])) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table']);
        $id = intval($_GET['delete']);
        
        backup_db($db_path, $backup_dir);
        
		// 构建可读的SQL语句（用于显示）
		$debug_sql = "DELETE FROM $table WHERE rowid = $id";
		
		$stmt = $pdo->prepare("DELETE FROM $table WHERE rowid = ?");
		$stmt->execute(array($id));
		
		// --- 日志记录：数据删除 ---
		try {
		    $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
		    $auth_str = $current_user . " (datebase)";
		    $client_ip = $_SERVER['REMOTE_ADDR'];
		    
		    $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
		    $log_stmt->execute([
		        time(),
		        $debug_sql,
		        $auth_str,
		        $client_ip
		    ]);
		} catch (Exception $e) {
		    error_log("Log failed: " . $e->getMessage());
		}
		
        // ✅ ✅ ✅ 修改开始：改为存入 Session 并跳转 ✅ ✅ ✅
        $_SESSION['db_operation_message'] = "删除成功！";
        $_SESSION['db_executed_sql'] = $debug_sql; // <<< 记录SQL
        
        // 跳转，防止表单重复提交
        header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($table));
        exit;
        // ✅ ✅ ✅ 修改结束
    }

    // 3. 处理数据保存 (更新或插入) - 修复版 (含SQL提示和日志)
    if (isset($_POST['save_data'])) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table']);
        $id = intval($_POST['id']);
        
        $fields = array();
        foreach ($_POST['field'] as $key => $value) {
            $clean_key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            $fields[$clean_key] = $value;
        }
        
        // --- 新增：自动备份 ---
        backup_db($db_path, $backup_dir); 

        try {
            // --- 构建通用的SQL日志内容 ---
            // 我们需要在 try 内部定义 $sql 变量，以便后续日志使用
            $log_sql = ""; 

				if ($id == 0) {
			    // --- 兼容 PHP 5.2：手动构建字段 ---
			    $field_names = array();
			    $execute_values = array();
			    $sql_parts = array(); // 用于存储 ? 或 NULL
			
			    $stmt_info = $pdo->query("PRAGMA table_info($table)");
			    $table_structure = $stmt_info->fetchAll(PDO::FETCH_ASSOC);
			
				foreach ($table_structure as $col_info) {
				    $col_name = $col_info['name'];
				
				    // 跳过自增主键
				    if ($col_info['pk'] == 1 && strtoupper($col_info['type']) == 'INTEGER') {
				        continue;
				    }
				
				    // 只处理用户提交过的字段（没提交的完全跳过，交给数据库DEFAULT）
				    if (!array_key_exists($col_name, $fields)) {
				        continue;
				    }
				
				    $input_value = $fields[$col_name];
				
				    // 空值也跳过，交给数据库默认值
				    if ($input_value === null || $input_value === '') {
				        continue;
				    }
				
				    // 只有用户填了值，才写入SQL
				    $field_names[] = "`$col_name`";
				    $sql_parts[] = '?';
				    $execute_values[] = $input_value;
				}

			
			    // 构建 SQL
			    $columns_str = implode(', ', $field_names);
			    $values_str = implode(', ', $sql_parts);
			    
			    $log_sql = "INSERT INTO $table ($columns_str) VALUES ($values_str)";
			    
			    // 执行
			    $stmt = $pdo->prepare("INSERT INTO $table ($columns_str) VALUES ($values_str)");
			    $stmt->execute($execute_values);
            } else {
                // --- 更新现有记录 ---
                $set_parts = array();
                foreach (array_keys($fields) as $col) {
                    $set_parts[] = "$col = ?";
                }
                $set_clause = implode(', ', $set_parts);
                
                // 🔽 构建实际执行的 SQL
                $set_debug = array();
                foreach ($fields as $k => $v) {
                    $set_debug[] = "$k = " . $pdo->quote($v);
                }
                $log_sql = "UPDATE $table SET " . implode(', ', $set_debug) . " WHERE id = $id";

                $stmt = $pdo->prepare("UPDATE $table SET $set_clause WHERE id = ?");
                $values = array_values($fields);
                $values[] = $id;
                $stmt->execute($values);
            }

            // --- ✅ 核心修复：记录日志 (必须放在 try 内部) ---
            try {
                $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
                $auth_str = $current_user . " (datebase)";
                $client_ip = $_SERVER['REMOTE_ADDR'];
                
                $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
                $log_stmt->execute(array(
                    time(),
                    $log_sql, // <<< 写入刚才构建的 SQL
                    $auth_str,
                    $client_ip
                ));
            } catch (Exception $e) {
                error_log("Log failed: " . $e->getMessage());
            }
            // --- ✅ 日志记录结束 ---

            // --- 设置提示信息 (包含 SQL) ---
            // 这里将 SQL 存入 Session，前端会自动显示
            $_SESSION['db_operation_message'] = "操作成功！执行SQL: " . htmlspecialchars($log_sql);
            
            // 跳转
            header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($table));
            exit;

        }    catch (PDOException $e) {
	        $raw_error = $e->getMessage();
	        $error_code = $e->errorInfo[1];
	
	        // ✅ 专门处理错误码 19 (约束错误)
	        if ($error_code == 19) {
	            // --- 1. 获取外键定义 ---
	            $defined_fks = get_table_foreign_keys($pdo, $table);
	            
	            if (empty($defined_fks)) {
	                $display_msg = "🔴 <b>系统错误</b><br>无法读取外键定义。";
	            } else {
	                $error_details = array();
	                
	                // --- 2. 遍历检查 ---
	                foreach ($defined_fks as $fk_field => $ref_target) {
	                    
	                    // A. 解析目标表 (放弃正则，使用更 robust 的字符串处理)
	                    $parent_table = "未知表";
	                    $parent_column = "未知字段";
	                    
	                    // 尝试去除空格和引号，然后按 ( 分割
	                    // 假设格式为: table(column) 或 `table`(`column`)
	                    $clean_target = trim($ref_target);
	                    
	                    // 尝试分割字符串
	                    if (strpos($clean_target, '(') !== false && substr($clean_target, -1) === ')') {
	                        // 找到第一个左括号的位置
	                        $pos = strpos($clean_target, '(');
	                        // 括号前的是表名，括号里的是字段名
	                        $raw_table = substr($clean_target, 0, $pos);
	                        $raw_col = substr($clean_target, $pos + 1, -1); // -1 去掉最后的 )
	                        
	                        // 去除引号和空格
	                        $parent_table = trim($raw_table, " '`\"");
	                        $parent_column = trim($raw_col, " '`\"");
	                    }
	
	                    // B. 寻找用户提交的值
	                    $submitted_value = null;
	                    if (isset($_POST[$fk_field])) $submitted_value = $_POST[$fk_field];
	                    elseif (isset($_POST['field'][$fk_field])) $submitted_value = $_POST['field'][$fk_field];
	
	                    // C. 构造错误信息
	                    if ($submitted_value !== null && $submitted_value !== '') {
	                        // 如果解析失败，我们在错误信息里显示原始字符串，方便调试
	                        $ref_info = "{$parent_table}.{$parent_column}";
	                        if ($parent_table === "未知表") {
	                            $ref_info .= " (原始数据: '{$ref_target}')";
	                        }
	
	                        $error_details[] = 
	                            "❌ 关联失败：字段 <b>{$fk_field}</b> (值: <b>{$submitted_value}</b>) " ."：在表 <b>{$parent_table}</b> 的字段 <b>{$parent_column}</b> 中找不到该值。";
	                    }
	                }
	
	                // --- 3. 输出结果 ---
	                if (!empty($error_details)) {
	                    $display_msg = "🔴 <b>外键约束违规 (表: {$table})</b><br><br>" . implode('<hr>', $error_details);
	                } else {
	                    $display_msg = "🔴 <b>数据完整性冲突</b><br>操作违反了表 <b>{$table}</b> 的外键约束规则。";
	                }
	            }
	        } 
	        // 其他错误...
	        elseif (strpos($raw_error, 'UNIQUE') !== false) {
	            $display_msg = "🔴 <b>唯一性冲突</b><br>数据重复。";
	        } 
	        else {
	            $display_msg = "🔴 <b>数据库错误</b><br>" . htmlspecialchars($raw_error);
	        }
	
	        // --- 4. 跳转 ---
	        $_SESSION['db_operation_error'] = "操作失败: " . $display_msg;
	        header("Location: " . $_SERVER['PHP_SELF'] . "?table=" . urlencode($table));
	        exit;
	    }


    }

	// 4. 处理添加字段 (最终修复版：解决外键丢失、自增丢失、双重引号问题)
	if (isset($_POST['add_column'])) {
	    // --- 1. 获取并清洗输入数据 ---
	    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name']);
	    $col_name = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['col_name']);
	    $col_type = $_POST['col_type'];
	    // 获取约束参数
	    $col_default = isset($_POST['col_default']) ? trim($_POST['col_default']) : null;
	    $col_notnull = isset($_POST['col_notnull']) ? true : false;
	    $col_pk = isset($_POST['col_pk']) ? true : false;
	    $col_auto = isset($_POST['col_auto']) ? true : false;
	    $col_fk_table = isset($_POST['col_fk_table']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['col_fk_table']) : '';
	    $col_fk_col = isset($_POST['col_fk_col']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['col_fk_col']) : '';
	    
	    if (empty($table) || empty($col_name)) {
	        $_SESSION['db_operation_error'] = "表名或字段名无效。";
	        header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($table));
	        exit;
	    }
	
	    // 备份数据库
	    backup_db($db_path, $backup_dir);
	
	    try {
	        // --- 2. 获取旧表结构 ---
	        $stmt = $pdo->query("PRAGMA table_info($table)");
	        $old_cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
	        
	        // 检查字段是否已存在
	        foreach ($old_cols as $c) {
	            if ($c['name'] == $col_name) {
	                throw new Exception("字段 '$col_name' 已存在");
	            }
	        }
	
	        // --- ✅ ✅ ✅ 新增：获取现有的外键约束 ---
	        $existing_fk_constraints = array();
	        $fk_stmt = $pdo->query("PRAGMA foreign_key_list($table)");
	        $fk_rows = $fk_stmt->fetchAll(PDO::FETCH_ASSOC);
	        
	        // 将现有的外键转换为 SQL 字符串
	        foreach ($fk_rows as $fk) {
	            // 注意：$fk['from'] 是源字段，$fk['table'] 是目标表，$fk['to'] 是目标字段
	            $existing_fk_constraints[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
	        }
	        // --- ✅ ✅ ✅ 获取外键结束 ---
	
	        // --- 3. 构建新字段定义 ---
	        $field_sql = "`$col_name` $col_type";
	        $is_auto_pk = false;
	
	        // 3.1 处理新字段的约束
	        if ($col_pk && $col_auto) {
	            $field_sql = "`$col_name` INTEGER PRIMARY KEY AUTOINCREMENT";
	            $is_auto_pk = true;
	        } elseif ($col_pk) {
	            $field_sql .= " PRIMARY KEY";
	        }
	        
			// 处理默认值（终极修复：时间函数、空字符串、普通字符串）
			if ($col_default !== null && $col_default !== '' && !$is_auto_pk) {
			    $raw_val = trim($col_default);
			    if (preg_match('/^\(.*\)$/', $raw_val)) {
			        $field_sql .= " DEFAULT $raw_val";
			    } elseif ($raw_val === '' || $raw_val === '\'\'' || $raw_val === '""') {
			        $field_sql .= " DEFAULT ''";
			    } elseif (is_numeric($raw_val)) {
			        $field_sql .= " DEFAULT $raw_val";
			    } else {
			        if (preg_match('/^[\'"].*[\'"]$/', $raw_val)) {
			            $field_sql .= " DEFAULT $raw_val";
			        } else {
			            $safe_val = str_replace("'", "''", $raw_val);
			            $field_sql .= " DEFAULT '$safe_val'";
			        }
			    }
			}

	        // 处理非空
	        if ($col_notnull && !$col_pk) {
	            $field_sql .= " NOT NULL";
	        }
	
	        // --- 4. 重建表 ---
	        $temp_table = $table . '_temp_upgrade';
	        
	        // ✅ 修复：先删除可能残留的临时表
	        $pdo->exec("DROP TABLE IF EXISTS `$temp_table`");
	
	        // 4.1 构建旧字段定义 (只包含列定义)
	        $old_field_defs = array();
	        foreach ($old_cols as $c) {
	            $def = "`".$c['name']."` ".$c['type'];
	            
	            // 处理非空 (排除主键，因为主键会在后面单独处理)
	            if ($c['notnull'] == 1 && $c['pk'] == 0) {
	                $def .= " NOT NULL";
	            }
	            
	            // 处理默认值
				if ($c['dflt_value'] !== null) {
				    $raw_val = $c['dflt_value'];
				    if (preg_match('/^\(.*\)$/', $raw_val) || preg_match('/^[\'"].*[\'"]$/', $raw_val)) {
				        $def .= " DEFAULT $raw_val";
				    } elseif (is_numeric($raw_val)) {
				        $def .= " DEFAULT $raw_val";
				    } else {
				        $def .= " DEFAULT '" . str_replace("'", "''", $raw_val) . "'";
				    }
				}
				
	            // 处理主键自增
	            if ($c['pk'] == 1) {
	                $def .= " PRIMARY KEY";
	                if (strtoupper($c['type']) == 'INTEGER') {
	                    $def .= " AUTOINCREMENT";
	                }
	            }
	            $old_field_defs[] = $def;
	        }
	
	        // ✅ ✅ ✅ 核心修复：构建表级约束 ---
	        $table_constraints = array();
	        
	        // 1. 添加用户新设置的外键
	        if (!empty($col_fk_table) && !empty($col_fk_col)) {
	            $table_constraints[] = "FOREIGN KEY ($col_name) REFERENCES $col_fk_table($col_fk_col)";
	        }
	        
	        // 2. 添加从旧表继承的所有外键
	        $table_constraints = array_merge($table_constraints, $existing_fk_constraints);
	        
	        // 3. 将表级约束合并到字段定义末尾
	        // 注意：表级约束（如 FOREIGN KEY）在 CREATE TABLE 语句中通常作为单独的“字段”添加
	        $final_fields = array_merge($old_field_defs, array($field_sql));
	        
	        // 只有存在表级约束时，才把它们加进去
	        if (!empty($table_constraints)) {
	            $final_fields[] = implode(', ', $table_constraints);
	        }
	
	        // 4.2 生成建表 SQL
	        $create_sql = "CREATE TABLE `$temp_table` (" . implode(', ', $final_fields) . ")";
	
	        // --- 5. 执行 SQL ---
	        $pdo->exec($create_sql); // 创建临时表
	
	        // 5.1 复制数据
	        $old_col_names = array();
	        foreach ($old_cols as $c) {
	            $old_col_names[] = $c['name'];
	        }
	        if (!empty($old_col_names)) {
	            $pdo->exec("INSERT INTO `$temp_table` (".implode(', ', $old_col_names).") SELECT ".implode(', ', $old_col_names)." FROM `$table`");
	        }
	
	        // 5.2 重命名表 (交换)
	        $pdo->exec("DROP TABLE `$table`");
	        $pdo->exec("ALTER TABLE `$temp_table` RENAME TO `$table`");
	
	        // 5.3 处理自增计数器
	        if ($col_pk && $col_auto) {
	            $pdo->exec("INSERT OR REPLACE INTO sqlite_sequence (name, seq) SELECT '$table', COALESCE(MAX(id), 0) FROM $table");
	        }
	
	        // 5.4 记录日志
	        $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
	        $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
	        $log_stmt->execute([time(), $create_sql, $current_user, $_SERVER['REMOTE_ADDR']]);
	
	        $_SESSION['db_operation_message'] = "字段 '$col_name' 添加成功！";
	        header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($table));
	        exit;
	
	    } catch (Exception $e) {
	        $_SESSION['db_operation_error'] = "操作失败: " . $e->getMessage();
	        header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($table));
	        exit;
	    }
	}

	// 5. 处理恢复备份 (修复版：恢复后重新连接并写入日志)
	if (isset($_POST['restore_backup'])) {
	    $backup_file = $_POST['backup_file'];
	    if (file_exists($backup_file)) {
	        // 1. 关闭连接以释放文件
	        $pdo = null; 
	        
	        if (copy($backup_file, $db_path)) {
	            // 2. 重新连接数据库
	            $pdo = new PDO('sqlite:' . $db_path);
	            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	            
	            // 3. 准备日志数据 (注意：这里记录的是恢复动作，不是SQL执行)
	            $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
	            $auth_str = $current_user . " (datebase)";
	            $client_ip = $_SERVER['REMOTE_ADDR'];
	            $restore_sql = "RESTORE FROM FILE: " . basename($backup_file); // 自定义的SQL记录内容
	            
	            // 4. 写入日志
	            $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
	            $log_stmt->execute([
	                time(),
	                $restore_sql, // 写入恢复记录
	                $auth_str,
	                $client_ip
	            ]);
	            
	            // 5. 设置提示信息 (包含SQL语句)
	            $_SESSION['db_operation_message'] = "恢复成功！执行SQL: {$restore_sql}";
	        } else {
	            $_SESSION['db_operation_error'] = "恢复失败：文件复制错误";
	        }
	        
	        header("Location: ".$_SERVER['PHP_SELF']);
	        exit;
	    }
	}

    // ==================================================================================
    // 🔴 修复版：处理删除字段 (强制跳转模式)
    // ==================================================================================
    if (isset($_GET['delete_column'])) {
        $target_table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table']);
        $target_col = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['column']);
        
        // 简单校验
        if (!empty($target_table) && !empty($target_col)) {
            try {
                // 1. 执行删除字段
                $sql = "ALTER TABLE $target_table DROP COLUMN $target_col";
                $pdo->exec($sql);
                
                // 2. 记录日志
                try {
                    $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
                    $auth_str = $current_user . " (datebase)";
                    $client_ip = $_SERVER['REMOTE_ADDR'];
                    
                    $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
                    $log_stmt->execute([
                        time(),
                        $sql,
                        $auth_str,
                        $client_ip
                    ]);
                } catch (Exception $e) { 
                    error_log("Log failed: " . $e->getMessage()); 
                }
                
                // 3. 成功跳转 (使用 URL 传参)
                $success_msg = urlencode("字段 '$target_col' 删除成功！");
                header("Location: " . $_SERVER['PHP_SELF'] . "?table=" . urlencode($target_table) . "&message=" . $success_msg);
                exit;

	        } catch (PDOException $e) {
	            // 💥 失败处理：将错误信息和 SQL 都存入 Session
	            $version_info = $pdo->query("SELECT sqlite_version()")->fetchColumn();
	            $_SESSION['db_operation_error'] = "删除字段失败: " . $e->getMessage() . "<br> (提示：SQLite在 <b>3.35.0</b> 以上版本才支持删除字段，当前 SQLite 版本为 <b>$version_info</b> 。)";
	            
	            // <<< 新增：将尝试执行的 SQL 存入 Session >>>
	            $_SESSION['db_executed_sql'] = $sql; 
	            
	            // 跳转回当前表
	            header("Location: " . $_SERVER['PHP_SELF'] . "?table=" . urlencode($target_table));
	            exit;
	        }

        }
    }

    // ==================================================================================
    // 🔴 新增功能 2：处理删除表
    // ==================================================================================
    if (isset($_POST['drop_table'])) {
        $drop_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['drop_table_name']);
        
	    try {
	    	backup_db($db_path, $backup_dir);
	        $sql = "DROP TABLE $drop_table_name";
	        $pdo->exec($sql);
	        
	        $message = "表 '$drop_table_name' 已被彻底删除！";
	        
	        // --- 记录日志 (保持不变) ---
	        try {
	            $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
	            $auth_str = $current_user . " (datebase)";
	            $client_ip = $_SERVER['REMOTE_ADDR'];
	            
	            $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
	            $log_stmt->execute([
	                time(),
	                $sql,
	                $auth_str,
	                $client_ip
	            ]);
	        } catch (Exception $e) {
	            error_log("Log failed: " . $e->getMessage());
	        }
	        
	        // --- 关键修复：删除后立即跳转 ---
	        // 删除当前表后，跳转回首页（或者表列表页），防止继续读取已删除的表导致报错
	        header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message));
	        exit; // 确保跳转后代码停止执行
	
	    } catch (PDOException $e) {
	        $message = "删除表失败: " . $e->getMessage();
	    }

    }
    
	// --- ✅ 终极修复版：处理 SQL 执行请求 (SELECT 结果直接暂存) ---
	if (isset($_POST['run_sql'])) {
	    $sql_query = trim($_POST['sql_query']);
	    
	    // 1. 检查输入
	    if (empty($sql_query)) {
	        $_SESSION['sql_message'] = "错误：SQL 语句不能为空。";
	        $_SESSION['sql_message_type'] = "alert-error";
	        // 清除旧的查询结果
	        unset($_SESSION['sql_result_data']);
	    } else {
	        // 2. 判断类型
	        $sql_upper = strtoupper($sql_query);
	        $is_select = (strpos($sql_upper, 'SELECT') === 0);
	        $is_explain = (strpos($sql_upper, 'EXPLAIN') === 0);
	        $is_pragma = (strpos($sql_upper, 'PRAGMA') === 0);
	        $is_read_operation = ($is_select || $is_explain || $is_pragma);
	
	        if ($is_read_operation) {
	            // --- 🟢 读操作：执行并存储结果到 Session ---
	            try {
	                $stmt = $pdo->query($sql_query);
	                if ($stmt) {
	                    // 获取数据和列信息
	                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
	                    $column_count = $stmt->columnCount();
	                    
	                    // ✅ ✅ ✅ 核心修复：将结果序列化存入 Session (或者使用全局变量，这里用 Session 最稳)
	                    $_SESSION['sql_result_data'] = $results; 
	                    $_SESSION['sql_result_columns'] = $column_count;
	                    
	                    $row_count = count($results);
	                    $_SESSION['sql_message'] = "✅ 查询成功，共 {$row_count} 条记录。";
	                    $_SESSION['sql_message_type'] = "alert-success";
	                } else {
	                    $_SESSION['sql_message'] = "⚠️ 查询语句执行成功，但没有返回结果集。";
	                    $_SESSION['sql_message_type'] = "alert-error";
	                    unset($_SESSION['sql_result_data']);
	                }
	            } catch (PDOException $e) {
	                $_SESSION['sql_message'] = "❌ 查询错误: " . $e->getMessage();
	                $_SESSION['sql_message_type'] = "alert-error";
	                unset($_SESSION['sql_result_data']);
	            }
	        } else {
	            // --- 🔴 写操作：保持不变 ---
	            if ($mode != 'edit') {
	                $_SESSION['sql_message'] = "🔒 错误：执行写操作需要登录并进入编辑模式。";
	                $_SESSION['sql_message_type'] = "alert-error";
	                unset($_SESSION['sql_result_data']);
	            } else {
	                try {
	                    backup_db($db_path, $backup_dir);
	                    $stmt = $pdo->prepare($sql_query);
	                    $stmt->execute();
	                    $rows = $stmt->rowCount();
	                    $_SESSION['sql_message'] = "🚀 写操作执行成功！影响行数: {$rows}。";
	                    $_SESSION['sql_message_type'] = "alert-success";
	                    // 写操作无结果集
	                    unset($_SESSION['sql_result_data']);
	                } catch (PDOException $e) {
	                    $_SESSION['sql_message'] = "❌ 执行失败: " . $e->getMessage();
	                    $_SESSION['sql_message_type'] = "alert-error";
	                    unset($_SESSION['sql_result_data']);
	                }
	            }
	        }
	    }
	    // 无论成功失败，都留在当前页面显示结果（不重定向，防止 POST 数据丢失）
	    // 注意：这里不要 header 跳转，否则 $_POST 数据和 Message 会消失
	}



}

// --- 获取所有表名 (通常在代码靠前的位置) ---
$tables = array();
try {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['name'];
    }
} catch (Exception $e) {
    $tables = array();
}


$current_table = isset($_GET['table']) ? $_GET['table'] : (count($tables) > 0 ? $tables[0] : '');

	// ---  处理修改表名 (新增功能) ---
	if (isset($_POST['rename_table_submit'])) {
	    if ($mode == 'edit') {
	        $old_table_name = $current_table;
	        $new_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['new_table_name_input']);
	        
	        // 验证
	        if (empty($new_table_name)) {
	            $_SESSION['db_operation_error'] = "错误：新表名不能为空！";
	        } elseif ($new_table_name == $old_table_name) {
	            $_SESSION['db_operation_message'] = "提示：新表名与旧表名相同，无需修改。";
	        } else {
	            try {
	                // 1. 检查新表名是否已存在
	                $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$new_table_name'");
	                if ($check->fetch()) {
	                    $_SESSION['db_operation_error'] = "错误：表名 '$new_table_name' 已存在！";
	                } else {
	                    // 2. 执行备份 (关键步骤)
	                    backup_db($db_path, $backup_dir);
	                    
	                    // 3. 构建并执行 SQL
	                    $sql = "ALTER TABLE `$old_table_name` RENAME TO `$new_table_name`";

	                    $pdo->exec($sql);
	                    
	                    // 4. 记录日志
	                    try {
	                        $current_user = isset($_SESSION['db_admin_user']) ? $_SESSION['db_admin_user'] : 'unknown';
	                        $auth_str = $current_user . " (datebase)";
	                        $client_ip = $_SERVER['REMOTE_ADDR'];
	                        $log_stmt = $pdo->prepare("INSERT INTO history (date, sql, authuser, ip) VALUES (?, ?, ?, ?)");
	                        $log_stmt->execute(array(
	                            time(),
	                            $sql,
	                            $auth_str,
	                            $client_ip
	                        ));
	                    } catch (Exception $e) {
	                        error_log("Log failed: " . $e->getMessage());
	                    }
	                    
	                    // 5. 设置成功消息并跳转
	                    // 注意：跳转时需要带上新的表名，否则页面会因为找不到旧表名而报错
	                    $_SESSION['db_operation_message'] = "🎉 表名修改成功！从 '$old_table_name' 改为 '$new_table_name'。执行SQL: <code>" . htmlspecialchars($sql) . "</code>";
	                    header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($new_table_name));
	                    exit;
	                }
	            } catch (PDOException $e) {
	                $_SESSION['db_operation_error'] = "修改表名失败: " . $e->getMessage();
	            }
	        }
	        // 如果有错误或消息，通过 header 跳转回原页面显示
	        header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($current_table));
	        exit;
	    } else {
	        $_SESSION['db_operation_error'] = "权限不足，请进入编辑模式。";
	        header("Location: ".$_SERVER['PHP_SELF']."?table=".urlencode($current_table));
	        exit;
	    }
	}


	// 分页逻辑
	$data_page = isset($_GET['dpage']) ? intval($_GET['dpage']) : 1; // ✅ 修改参数名：page -> dpage
	$per_page = 10;
	$offset = ($data_page - 1) * $per_page; // ✅ 使用新变量
	$data = array();
	$columns = array();
	if ($current_table) {
	    // 获取列信息
	    $stmt = $pdo->query("PRAGMA table_info($current_table)");
	    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
	    // 获取总行数
	    $stmt = $pdo->query("SELECT COUNT(*) as c FROM $current_table");
	    $total_rows = $stmt->fetch(PDO::FETCH_ASSOC)['c'];
	    $total_pages = ceil($total_rows / $per_page);
	    // 获取数据
	    // 先查询表结构，判断是否有 'id' 字段
	    $has_id_field = false;
	    $stmt_info = $pdo->query("PRAGMA table_info($current_table)");
	    $columns = $stmt_info->fetchAll(PDO::FETCH_ASSOC);
	    foreach ($columns as $col) {
	        if ($col['name'] == 'id' && $col['pk'] == 1) { // 如果存在 id 且是主键
	            $has_id_field = true;
	            break;
	        }
	    }
	    // 根据是否有 id 字段决定查询方式
	    if ($has_id_field) {
	        $stmt = $pdo->query("SELECT * FROM $current_table LIMIT $per_page OFFSET $offset");
	    } else {
	        $stmt = $pdo->query("SELECT rowid, * FROM $current_table LIMIT $per_page OFFSET $offset");
	    }
	    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>数据库管理 - <?php echo $mode; ?></title>
    <style>
		.pagination {
		    margin: 20px 0;
		    text-align: center;
		}
		.pagination a, .pagination span {
		    display: inline-block;
		    padding: 5px 10px;
		    margin: 0 2px;
		    border: 1px solid #ddd;
		    text-decoration: none;
		    color: #007bff;
		}
		.pagination strong {
		    padding: 5px 10px;
		    margin: 0 2px;
		    background: #007bff;
		    color: #fff;
		    border: 1px solid #007bff;
		}
		.pagination span {
		    color: #999;
		    border-color: #eee;
		    cursor: not-allowed;
		}

        body { font-family: sans-serif; margin: 20px; background: #f4f4f4; }
        .container { background: #fff; padding: 20px; border-radius: 8px; max-width: 1000px; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
        input[type="text"] { width: 100%; box-sizing: border-box; padding: 5px; }
		/* 1. 统一所有下拉框(select)的高度，使其与文本框(input)一致 */
		.login-container input,
		/* 用户名密码框 */
		div[style*="margin-bottom"] select,
		/* 表选择下拉框 */
		table + div select,
		/* 添加字段下拉框 */
		select {
		    /* 统一输入框和下拉框的外观 */
		    padding: 5px !important;
		    height: auto !important; /* 取消固定高度，由 padding 决定 */
		    margin: 8px 0 !important;
		    border: 1px solid #ced4da !important;
		    border-radius: 4px !important;
		    box-sizing: border-box !important;
		    font-size: 14px !important;
		    font-family: inherit; /* 继承字体，看起来更统一 */
		}
		
		/* --- 表格布局优化 (恢复横向滚动模式) --- */
		
		/* 1. 移除 fixed 布局，恢复自动布局，让列宽由内容决定 */
		.data-table {
		    table-layout: auto; /* 改回 auto，列宽自适应内容 */
		    width: 100%;
		    border-collapse: collapse;
		    /* 移除了 min-width，让表格可以收缩 */
		}
		
		/* 2. 移除操作列的固定宽度，让其由按钮内容撑开 */
		.data-table td:last-child,
		.data-table th:last-child {
		    /* width: 150px; --- 删除这行 --- */
		    white-space: nowrap; /* 保持按钮不换行 */
		    /* 移除背景色和 position，恢复默认 */
		}
		
		/* 3. 优化单元格内边距，让表格不要太臃肿 */
		.data-table td {
		    padding: 5px 8px; /* 减少内边距，让单元格更紧凑 */
		    overflow: visible; /* 允许内容溢出显示（配合外层滚动） */
		    text-overflow: clip; /* 取消省略号，显示完整文本（由滚动条保证可见性） */
		    min-width: 50px; 
		}
		
		/* 4. 关键：确保外层容器显示横向滚动条 */
		.table-container {
		    width: 100%;
		    overflow-x: auto; /* 强制显示横向滚动条 */
		    border: 1px solid #ddd;
		    border-radius: 4px;
		    margin-bottom: 10px;
		    /* 增加一个最小高度，防止空表格时看不到边框 */
		    min-height: 50px; 
		}
		
		/* 5. 恢复按钮为横向排列，并缩小宽度 */
		.data-table td button,
		.data-table td a {
		    padding: 3px 8px !important; /* 大量减少内边距，让按钮变短 */
		    font-size: 12px !important; /* 字体变小 */
		    margin: 0 2px; /* 减小按钮间距 */
		}
		

		/* 2. 修正备份列表中的按钮高度 */
		/* 确保下载(a标签)和恢复(按钮)高度一致 */
		td a.btn-primary,
		td button.btn-danger {
		    /* 统一按钮内边距 */
		    padding: 5px 10px !important;
		    font-size: 12px !important;
		    margin: 0 !important;
		    display: inline-block !important;
		    text-align: center;
		    box-sizing: border-box;
		    line-height: 1.2;
		}
		
		/* === 新增/修改的样式结束 === */
        .btn { padding: 5px 10px; text-decoration: none; color: #fff; border-radius: 3px; border: none; cursor: pointer; }
        .btn-primary { background: #007bff; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #000; }
		.alert { padding: 10px; margin-bottom: 10px; border-radius: 4px; border: 1px solid #c3e6cb; }
		.alert-success { background: #d4edda; color: #155724; } /* 绿色 */
		.alert-error { background: #f8d7da; color: #721c24; }   /* 红色 */
        .pagination a { margin: 0 2px; padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; }

		/* --- 修改版：左侧文字固定，右侧登录框自适应占满 --- */
		.mode-bar {
		    padding: 15px;
		    background: #f8f9fa;
		    margin-bottom: 20px;
		    border-radius: 8px;
		    border: 1px solid #e9ecef;
		    display: flex;
		    align-items: center;
		    gap: 15px;
		    
		    /* ✅ 新增：关键属性，允许内部元素换行（防止极端窄屏溢出） */
		    flex-wrap: nowrap;
		    
		    /* ✅ 新增：背景色延伸，看起来更像一个整体的卡片 */
		    overflow: hidden; /* 隐藏内部绝对定位元素的溢出阴影（可选） */
		}
		
		/* ✅ 新增：左侧状态文字容器 */
		.mode-bar .status-text {
		    flex: 0 0 auto; /* 不伸缩，不增长，宽度由内容决定 */
		    color: #333;
		    font-weight: bold;
		    font-size: 14px;
		    white-space: nowrap; /* 防止文字换行 */
		}
		
		/* ✅ 修改：登录容器 - 关键变化 */
		.login-container {
		    position: relative;
		    display: flex;
		    align-items: center;
		    gap: 8px;
		    background: #fff;
		    padding: 8px 12px; /* 增加一点左右内边距 */
		    border-radius: 6px;
		    border: 1px solid #dee2e6;
		    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		    
		    /* ✅ 关键：占据所有剩余空间 */
		    flex: 1; 
		    
		    /* 保持内部紧凑 */
		    min-width: 150px; /* 设置一个最小宽度，防止被压缩得太窄 */
		}
		
		/* 输入框样式：恢复为合理的宽度比例 */
		.login-container input[type="text"],
		.login-container input[type="password"] {
		    width: auto !important;
		    /* ✅ 修改：使用 flex 代替固定宽度，让它们按比例分配空间 */
		    flex: 1; /* 平分可用空间 */
		    min-width: 100px !important; /* 保证最小输入空间 */
		    padding: 9px 10px !important;
		    margin: 0 !important;
		    border: 1px solid #ced4da !important;
		    border-radius: 4px !important;
		    font-size: 13px !important;
		    box-sizing: border-box;
		}
		
		/* 按钮样式：保持紧凑 */
		.login-container button {
		    width: auto !important;
		    padding: 9px 14px !important;
		    background: #007bff !important;
		    border: none !important;
		    color: white !important;
		    font-weight: bold !important;
		    border-radius: 4px !important;
		    cursor: pointer !important;
		    font-size: 14px !important;
		    white-space: nowrap;
		    /* 移除 min-width，让按钮根据文字收缩 */
		}
		
		/* --- 修改版：红色错误提示（保持原有布局，仅改颜色） --- */
		.db-error-msg {
		    /* ✅ 保持原有布局属性，不改变位置和尺寸 */
		    /* 注意：这里不要加 position: absolute，保持它在文档流中的位置 */
		    background-color: #f8d7da; /* 浅红色背景 */
		    color: #721c24;            /* 深红色文字 */
		    border: 1px solid #f5c6cb; /* 红色边框 */
		    padding: 8px 12px;         /* 保持内边距，与之前一致 */
		    border-radius: 4px;        /* 圆角 */
		    font-size: 13px;           /* 字体大小 */
		    margin: 0;                 /* 紧贴周围元素 */
		    display: block;            /* 确保它是块级元素，占据一行或挤压旁边元素 */
		}
	

    </style>
<script type="text/javascript">
function toggleAll(source) {
    checkboxes = document.getElementsByName('backup_files[]');
    for(var i=0; i<checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>

</head>
<body>
<!-- 悬浮返回主页按钮 -->
<a href="../index.php" class="btn btn-warning" id="back-home-btn" style="
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    padding: 10px 15px;
    font-weight: bold;
    text-decoration: none;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
">🏠 返回主页</a>

<div class="container">
    <h1>🛠️数据库管理</h1>
	<div class="mode-bar">
	    <strong>当前模式:</strong> 
		<?php if ($mode == 'edit'): ?>
		    <!-- 左侧状态文字 -->
		    <div class="status-text">✅ 编辑模式 (可读写)</div>
		    <!-- 右侧退出按钮（如果有需要） -->
		    <a href="?action=logout" class="btn btn-danger" style="margin-left: auto;">🚪 退出编辑</a>
		<?php else: ?>
		    <!-- 左侧状态文字 -->
		    <div class="status-text">🔴 只读模式</div>
		    
		    <!-- 右侧登录容器（会自动占满剩余空间） -->
		    <div class="login-container">
		        <!-- 错误提示：悬浮层 -->
		        <?php if (isset($_SESSION['db_login_error'])): ?>
		            <div class="db-error-msg" id="login-error">
		                ⚠️ <?php echo $_SESSION['db_login_error']; ?>
		            </div>
		            <?php unset($_SESSION['db_login_error']); ?>
		        <?php endif; ?>
		        
		        <!-- 登录表单 -->
		        <form method="post" autocomplete="off" style="display: contents; z-index: 1; position: relative;">
		            <input type="hidden" name="set_mode" value="edit">
		            <!-- 用户名和密码框将平分 .login-container 的宽度 -->
		            <input type="text" name="username" placeholder="用户名" required>
		            <input type="password" name="password" placeholder="密码" required>
		            <button type="submit">🔓 登录</button>
		        </form>
		    </div>
		<?php endif; ?>

	</div>

    <?php 
    $display_message = '';
    $display_sql = ''; // <<< 新增：用于存储要显示的 SQL
    
    // 1. 检查是否有 Session 中的 SQL (先读取，再销毁，防止影响后续逻辑)
    if (isset($_SESSION['db_executed_sql'])) {
        $display_sql = $_SESSION['db_executed_sql'];
        // 读取后立即销毁，保证只显示一次
        unset($_SESSION['db_executed_sql']); 
    }
    
    // 2. 最高优先级：检查是否有 Session 中的操作错误
    if (isset($_SESSION['db_operation_error'])) {
        $display_message = $_SESSION['db_operation_error'];
        unset($_SESSION['db_operation_error']); 
    }
    // 3. 其次：检查是否有 Session 中的成功消息
    elseif (isset($_SESSION['db_operation_message'])) {
        $display_message = $_SESSION['db_operation_message'];
        unset($_SESSION['db_operation_message']); 
    }
    // 4. 最后：如果有 URL 传参的消息 (保留兼容旧的跳转方式)
    elseif (isset($_GET['message'])) {
        $display_message = $_GET['message'];
    }
    
    // --- 颜色判断逻辑 ---
    if ($display_message): 
        $is_error = false;
        $error_keywords = ['失败', '错误', 'Error', 'Warning', '警告', '不存在', 'Invalid', 'syntax', 'constraint'];
        foreach ($error_keywords as $keyword) {
            if (strpos($display_message, $keyword) !== false) {
                $is_error = true;
                break;
            }
        }
        $alert_class = $is_error ? 'alert-error' : 'alert-success';
    ?>
        <div class="alert <?php echo $alert_class; ?>">
            <?php echo $display_message; ?>
            <!-- <<< 新增：如果存在 SQL，显示 SQL >>> -->
            <?php if (!empty($display_sql)): ?>
                <br><small><strong>执行SQL:</strong> <code><?php echo htmlspecialchars($display_sql); ?></code></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>


		<!-- 表选择 (下拉列表) -->
		<div style="margin-bottom: 20px;">
		<strong>选择表:</strong>
		<select onchange="if(this.value) window.location.href='?table='+this.value">
		<option value="">-- 请选择表 --</option>
		<?php 
		// --- ✅ 新增：对表名进行自然排序 (修复 Bug 1) ---
		natsort($tables); 
		foreach ($tables as $tbl):
		    // 🔽 新增：查询该表的记录数
		    $count = 0;
		    try {
		        $stmt_count = $pdo->query("SELECT COUNT(*) as c FROM `$tbl`");
		        $row_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
		        $count = $row_count['c'];
		    } catch (Exception $e) {
		        $count = '错误';
		    }
		?>
		<option value="<?php echo urlencode($tbl); ?>" <?php echo ($tbl == $current_table) ? 'selected' : ''; ?>>
		    <?php echo htmlspecialchars($tbl); ?> (<?php echo $count; ?>)
		</option>
		<?php endforeach; ?>
		</select>
		</div>


    <?php if ($current_table): ?>
        <h2>表: <?php echo htmlspecialchars($current_table); ?></h2>
		<!-- ✅ 最终修复版：SQL 执行器界面 (独立于其他表单，移除危险样式) -->
		<hr> <!-- 确保与上方内容隔离 -->
		<!-- ✅ 极简版：强制只读 SELECT 执行器 -->
		<h3>⚡ 数据查询 (仅支持 SELECT)</h3>
		
		<?php
		// --- ✅ 核心逻辑：强制执行 SELECT (无需登录，无视模式) ---
		if (isset($_POST['run_sql_simple'])) {
		    $sql_query = trim($_POST['sql_query']);
		    
		    // 1. 强制检查：必须以 SELECT 开头 (忽略大小写)
		    if (empty($sql_query)) {
		        $simple_result_msg = "⚠️ 请输入 SQL 语句。";
		    } elseif (stripos($sql_query, 'SELECT') !== 0) {
		        // 如果不是 SELECT 开头，直接拒绝，不执行任何其他语句
		        $simple_result_msg = "🔒 安全限制：此表单仅允许执行 SELECT 查询。";
		    } else {
		        try {
		            // 直接执行 (PDO 会自动处理)
		            $stmt = $pdo->query($sql_query);
		            if ($stmt) {
		                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
		                if ($results) {
		                    // 成功：存储数据用于显示
		                    $simple_result_data = $results;
		                    $simple_result_msg = "✅ 查询成功，共 " . count($results) . " 行。";
		                } else {
		                    $simple_result_msg = "ℹ️ 查询已执行，但没有返回数据（可能是空表或条件不匹配）。";
		                }
		            } else {
		                $simple_result_msg = "❌ 查询执行失败，无结果集。";
		            }
		        } catch (PDOException $e) {
		            $simple_result_msg = "❌ 查询错误: " . $e->getMessage();
		        }
		    }
		}
		?>
		
		<!-- 显示消息 -->
		<?php if (isset($simple_result_msg)): ?>
		    <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
		        <?php echo $simple_result_msg; ?>
		    </div>
		<?php endif; ?>
		
		<!-- 表单：移除所有 JS 验证和危险样式 -->
		<form method="post">
		    <!-- 强制标记这个表单是简单的 -->
		    <input type="hidden" name="run_sql_simple" value="1">
		    
		    <textarea 
		        name="sql_query" 
		        rows="4" 
		        style="width: 100%; font-family: monospace; padding: 8px; box-sizing: border-box;"
		        placeholder="请输入 SELECT 语句，例如：SELECT * FROM users LIMIT 5;"><?php 
		            // 保留用户输入的内容，方便调试
		            echo isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : ''; 
		        ?></textarea>
		    <br><br>
		    <!-- 移除所有 JS confirm，直接提交 -->
		    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
		        🔍 执行查询 (SELECT Only)
		    </button>
		</form>
		
		<!-- ✅ 结果显示：直接在变量中读取 -->
		<?php if (isset($simple_result_data)): ?>
		    <h4>📊 查询结果:</h4>
		    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
		        <table style="width: 100%; border-collapse: collapse; font-family: sans-serif;">
		            <thead>
		                <tr style="background: #f8f9fa;">
		                    <?php foreach ($simple_result_data[0] as $key => $value): ?>
		                        <th style="border: 1px solid #ddd; padding: 10px; text-align: left; font-weight: bold;">
		                            <?php echo htmlspecialchars($key); ?>
		                        </th>
		                    <?php endforeach; ?>
		                </tr>
		            </thead>
		            <tbody>
		                <?php foreach ($simple_result_data as $row): ?>
		                    <tr>
		                        <?php foreach ($row as $cell): ?>
		                            <td style="border: 1px solid #ddd; padding: 8px; text-align: left;">
		                                <?php echo htmlspecialchars($cell); ?>
		                            </td>
		                        <?php endforeach; ?>
		                    </tr>
		                <?php endforeach; ?>
		            </tbody>
		        </table>
		    </div>
		<?php endif; ?>
		<hr>

        <!-- 数据列表 -->
        <h3>📋数据管理</h3>
        
        <!-- 表格开始 -->
		<div class="table-container" style="width: 100%; overflow-x: auto; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
		    <table class="data-table"> <!-- 移除了内联 min-width，交给 CSS 处理 -->
	            <thead>
	                <tr>
	                    <?php foreach ($columns as $col): ?>
	                        <th style="text-align: center;"><?php echo htmlspecialchars($col['name']); ?></th>
	                    <?php endforeach; ?>
						<?php if ($mode == 'edit'): ?>
						    <th style="text-align: center;">操作</th>
						<?php endif; ?>
	                </tr>
	            </thead>
	            
	            <!-- 隔离新增行表单 -->
	            <?php if ($mode == 'edit'): ?>
		            <tbody>
		                <tr>
		                    <form method="post" style="display: table-row;">
		                        <input type="hidden" name="table" value="<?php echo $current_table; ?>">
		                        <input type="hidden" name="id" value="0"> <!-- ID为0表示新增 -->
		                        <?php foreach ($columns as $col): ?>
		                            <td>
		                                <input type="text" name="field[<?php echo $col['name']; ?>]" value="" <?php echo ($mode != 'edit') ? 'disabled' : ''; ?>>
		                            </td>
		                        <?php endforeach; ?>
		                        <td style="text-align: center; vertical-align: middle; width: 140px;">
		                            <button type="submit" name="save_data" class="btn btn-success" <?php echo ($mode != 'edit') ? 'disabled' : ''; ?>>➕新增</button>
		                        </td>
		                    </form>
		                </tr>
		            </tbody>
				<?php endif; ?>
	            <!-- 隔离数据行表单 -->
				<tbody>
				<?php foreach ($data as $row): ?>
				<tr>
				    <form method="post" style="display: table-row;">
				    <input type="hidden" name="table" value="<?php echo $current_table; ?>">
				    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
				    
				    <?php foreach ($columns as $col): ?>
				    <td>
				        <input type="text" 
				               name="field[<?php echo $col['name']; ?>]" 
				               value="<?php echo htmlspecialchars($row[$col['name']]); ?>" 
				               <?php echo ($mode != 'edit') ? 'disabled' : ''; ?>>
				    </td>
				    <?php endforeach; ?>
				    
				    <?php if ($mode == 'edit'): ?>
				    <td style="text-align: center; white-space: nowrap;">
				        <!-- 恢复为简单的横向按钮 -->
				        <button type="submit" name="save_data" class="btn btn-primary" style="padding: 3px 8px; font-size: 12px;">💾保存</button>
				        <?php
							// 智能获取 ID 逻辑
							$delete_id = 0;
							// 优先使用 rowid (适用于无 id 字段的表)
							if (isset($row['rowid'])) {
							    $delete_id = $row['rowid'];
							}
							// 如果有 id 字段，直接用 id (PDO 会将其映射为 rowid，但为了保险直接取 id)
							elseif (isset($row['id'])) {
							    $delete_id = $row['id'];
							}
							// 万不得已，尝试用数组键 (不推荐，仅作保底)
							else {
							    $delete_id = key($data); 
							}
						?>
						<a href="?table=<?php echo $current_table; ?>&delete=<?php echo $delete_id; ?>" class="btn btn-danger" style="padding: 3px 8px; font-size: 12px;" onclick="return confirm('确定删除？')">🗑删除</a>
				    </td>
				    <?php endif; ?>
				    </form>
				</tr>
				<?php endforeach; ?>
				</tbody>

	        </table>
	    </div>
        <!-- 表格结束 -->

		<!-- 分页 -->
		<div class="pagination">
		<?php
		// 总页数
		$total_pages = ceil($total_rows / $per_page);
		// 只有一页时不显示分页
		if ($total_pages <= 1) {
		    // 什么都不输出
		} else {
		    // 获取当前的 cpage 和 fpage (如果存在)
		    $current_cpage = isset($_GET['cpage']) ? $_GET['cpage'] : 1;
		    $current_fpage = isset($_GET['fpage']) ? $_GET['fpage'] : 1;
		    
		    // 1. 首页按钮
		    if ($data_page > 1) {
		        // ✅ 显式带上 table, cpage, fpage
		        echo '<a href="?table=' . urlencode($current_table) . '&dpage=1&cpage=' . $current_cpage . '&fpage=' . $current_fpage . '">&laquo; 首页</a> ';
		    } else {
		        echo '<span>&laquo; 首页</span> ';
		    }
		    
		    // 2. 中间页码逻辑
		    $show_num = 2;
		    $start = max(2, $data_page - $show_num);
		    $end = min($total_pages - 1, $data_page + $show_num);
		    
		    if ($total_pages <= 7) {
		        for ($i = 2; $i < $total_pages; $i++) {
		            if ($i == $data_page) {
		                echo '<strong>[' . $i . ']</strong> ';
		            } else {
		                // ✅ 显式带上 table, cpage, fpage
		                echo '<a href="?table=' . urlencode($current_table) . '&dpage=' . $i . '&cpage=' . $current_cpage . '&fpage=' . $current_fpage . '">' . $i . '</a> ';
		            }
		        }
		    } else {
		        if ($start > 2) {
		            echo '... ';
		        }
		        for ($i = $start; $i <= $end; $i++) {
		            if ($i == $data_page) {
		                echo '<strong>[' . $i . ']</strong> ';
		            } else {
		                // ✅ 显式带上 table, cpage, fpage
		                echo '<a href="?table=' . urlencode($current_table) . '&dpage=' . $i . '&cpage=' . $current_cpage . '&fpage=' . $current_fpage . '">' . $i . '</a> ';
		            }
		        }
		        if ($end < $total_pages - 1) {
		            echo '... ';
		        }
		    }
		    
		    // 3. 末页按钮
		    if ($data_page < $total_pages) {
		        // ✅ 显式带上 table, cpage, fpage
		        echo '<a href="?table=' . urlencode($current_table) . '&dpage=' . $total_pages . '&cpage=' . $current_cpage . '&fpage=' . $current_fpage . '">末页 &raquo;</a>';
		    } else {
		        echo '<span>末页 &raquo;</span>';
		    }
		    echo "<span style='margin-left: 20px;'>第 $data_page 页，共 $total_pages 页</span>";
		}
		?>
		</div>

        <hr>
        <?php if ($mode == 'edit'): ?>
			<!-- 新建表功能 -->
			<h3>🆕新建表</h3>
			<form method="post" onsubmit="return confirm('确定要创建新表吗？')">
				<input type="text" name="new_table_name" placeholder="输入新表名" required <?php echo ($mode != 'edit') ? 'disabled' : ''; ?> style="width: 150px;">
			    <button type="submit" name="create_table" class="btn btn-success" <?php echo ($mode != 'edit') ? 'disabled' : ''; ?>>➕创建表</button>
			</form>
			<hr> <!-- 加个分割线 -->
		<?php endif; ?>
		<?php if ($mode == 'edit' && $current_table): ?>
		<h3>📝 修改表名</h3>
		<form method="post" onsubmit="return confirm('确定要修改表名吗？\n注意：新表名不能已存在。')">
		    <input type="hidden" name="rename_table" value="1">
		    <input type="text" name="new_table_name_input" value="<?php echo htmlspecialchars($current_table); ?>" required style="width: 150px;">
		    <button type="submit" name="rename_table_submit" class="btn btn-warning" <?php echo ($mode != 'edit') ? 'disabled' : ''; ?>>🔄 重命名表</button>
		</form>
		<hr>
		<?php endif; ?>
		<!-- 结构管理 -->
		<h3>表结构管理</h3>
		
		<!-- 🔴 新增：删除表按钮 (保持不变) -->
		<?php if ($mode == 'edit' && $current_table): ?>
		    <form method="post" style="display:inline;" onsubmit="return confirm('警告：确定要彻底删除表 [<?php echo $current_table; ?>] 吗？此操作不可恢复！')">
		        <input type="hidden" name="drop_table" value="1">
		        <input type="hidden" name="drop_table_name" value="<?php echo $current_table; ?>">
		        <button type="submit" class="btn btn-danger">🗑️ 删除当前表 (<?php echo $current_table; ?>)</button>
		    </form>
		<?php endif; ?>
		<!-- ✅ 升级版：添加字段表单 (支持约束) -->
		<?php if ($mode == 'edit'): ?>
		<form method="post" style="margin-bottom: 10px; margin-top: 10px; display: flex; flex-wrap: wrap; gap: 5px; align-items: center;">
		    <input type="hidden" name="table_name" value="<?php echo $current_table; ?>">
		    
		    <input type="text" name="col_name" placeholder="字段名" required style="width: 100px;">
		    
		    <select name="col_type" required style="width: 100px;">
		            <option value="TEXT">TEXT (文本)</option>
		            <option value="INTEGER">INTEGER (整数)</option>
		            <option value="REAL">REAL (浮点数)</option>
		            <option value="NUMERIC">NUMERIC (数字，如金额)</option>
		            <option value="BLOB">BLOB (二进制对象)</option>
		    </select>
		
		    <!-- 默认值 -->
		    <input type="text" name="col_default" placeholder="默认值 (可选)" style="width: 100px;">
		
		    <!-- 约束选项 -->
		    <label><input type="checkbox" name="col_notnull" value="1"> 非空</label>
		    <label><input type="checkbox" name="col_pk" value="1"> 主键</label>
		    <label><input type="checkbox" name="col_auto" value="1"> 自增</label>
		
		    <!-- 外键约束 (简单实现：输入表名和字段名) -->
		    <input type="text" name="col_fk_table" placeholder="外键表" style="width: 100px;">
		    <input type="text" name="col_fk_col" placeholder="外键字段" style="width: 100px;">
		    
		    <button type="submit" name="add_column" class="btn btn-warning">➕ 添加字段</button>
		</form>
		<?php endif; ?>
		
		<!-- ✅ 修复版：字段列表表格 (支持外键显示和完整表头) -->
		<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
		    <thead>
		        <tr style="background: #f8f9fa;">
		            <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 50px;">序号</th>
		            <th style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 200px;">字段名</th>
		            <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 150px;">类型</th>
		            <!-- 🔴 修复：补回缺失的“约束”列，用于显示 PK/AI/NOT NULL -->
		            <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 100px;">约束</th>
		            <!-- ✅ 新增：添加“外键”列 -->
		            <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 150px;">外键</th>
		            <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 150px;">默认值</th>
		            <?php if ($mode == 'edit'): ?>
		                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 100px;">操作</th>
		            <?php endif; ?>
		        </tr>
		    </thead>
		    <tbody>
		        <?php 
		        // --- ✅ 新增：获取外键信息 ---
		        // 注意：这个函数需要 $pdo 和 $current_table
		        $foreign_keys_map = get_table_foreign_keys($pdo, $current_table);
				// --- ✅ 新增：字段列表分页逻辑 ---
		        $fields_per_page = 10; // 每页显示数量
		        
		        // 获取当前页码 (使用 cpage 避免与数据表 page 和备份 fpage 冲突)
		        $fields_page = isset($_GET['cpage']) ? intval($_GET['cpage']) : 1;
		        
		        // 计算总数和总页数
		        $total_fields = count($columns);
		        $total_fields_pages = ceil($total_fields / $fields_per_page);
		        
		        // 修正页码范围
		        $fields_page = max(1, min($fields_page, $total_fields_pages ?: 1));
		        
		        // 截取当前页数据
		        $offset = ($fields_page - 1) * $fields_per_page;
		        $paginated_columns = array_slice($columns, $offset, $fields_per_page);
		        
		        // 如果没有字段
		        if (empty($paginated_columns)): 
		        ?>
		        <tr>
		            <td colspan="<?php echo $mode == 'edit' ? 6 : 5; ?>" style="border: 1px solid #ddd; padding: 8px; text-align: center;">
		                该表暂无字段
		            </td>
		        </tr>
		        <?php else: 
				// ✅ 强制调用外键解析函数
				$foreign_keys_map = get_table_foreign_keys($pdo, $current_table); 
				$col_index = ($fields_page - 1) * $fields_per_page + 1; // 保持序号逻辑
				?>
				
				<?php foreach ($paginated_columns as $col): ?>
				<tr>
				    <td style="text-align: center; vertical-align: middle;"><?php echo $col_index++; ?></td>
				    <td style="text-align: left;"><?php echo htmlspecialchars($col['name']); ?></td>
				    <td style="text-align: center;"><?php echo htmlspecialchars($col['type']); ?></td>
				    
				    <!-- ✅ 修复 2：约束显示 (PK/AI 显示中文) -->
				    <td style="text-align: center;">
				        <?php 
				        $notes = array();
						// ✅ 修复：约束显示 (强制显示非空，无论是否为主键)
						$notes = array();
						// 显示非空 (只要 notnull=1 就显示)
						if ($col['notnull'] == 1) { 
						    $notes[] = '非空'; 
						}
						// 显示主键和自增
						if ($col['pk'] == 1) {
						    $notes[] = '主键'; 
						    if (strtoupper($col['type']) == 'INTEGER') {
						        $notes[] = '自增';
						    }
						}
				        echo !empty($notes) ? implode('<br>', $notes) : '';
				        ?>
				    </td>
				    
					<!-- ✅ 修复 1：显示外键 (精准匹配) -->
					<td style="text-align: center; font-family: monospace; font-size: 12px; color: #0066cc;">
					    <?php 
					    $col_name = $col['name']; // 获取当前这一列的字段名，例如：user_id
					    
					    // ✅ 核心逻辑：只有当 $foreign_keys_map 数组里有 
					    // 键名为 $col_name 的时候，才显示
					    if (isset($foreign_keys_map[$col_name])) { 
					        echo htmlspecialchars($foreign_keys_map[$col_name]); 
					    } else { 
					        echo "<span style='color: #999;'>-</span>";
					    }
					    ?>
					</td>
				    <td style="text-align: center;"> 
				        <?php 
				        // ✅ 修复 3：清理默认值显示 (去除多余的引号转义)
				        $default = $col['dflt_value'];
				        if ($default !== null) {
				            $display_val = $default;
				            // 如果值被单引号包围，去掉引号并还原内部的转义
				            if (preg_match("/^'(.*)'$/", $display_val, $matches)) {
				                $display_val = str_replace("''", "'", $matches[1]);
				            }
				            echo htmlspecialchars($display_val);
				        } else {
				            echo '-';
				        }
				        ?> 
				    </td>
				    
				    <!-- 删除字段列 -->
				    <?php if ($mode == 'edit'): ?>
				    <td style="text-align: center; vertical-align: middle;">
				        <a href="?table=<?php echo urlencode($current_table); ?>&delete_column=1&column=<?php echo urlencode($col['name']); ?>" class="btn btn-danger" onclick="return confirm('确定删除字段 <?php echo $col['name']; ?> 吗？\n注意：旧版SQLite不支持此操作！')"> 🗑️ 删除 </a>
				    </td>
				    <?php endif; ?>
				</tr>
		        <?php endforeach; ?>
		        <?php endif; ?>
		    </tbody>
		</table>
		<!-- ✅ 新增：字段列表分页导航 (精简版：首页...中间...末页) -->
		<?php if ($total_fields_pages > 1): // 只有页数大于1时才显示分页栏 ?>
		<div class="pagination" style="margin-top: 10px;">
		<?php
		    // 获取当前的 dpage 和 fpage (如果存在)
		    $current_dpage = isset($_GET['dpage']) ? $_GET['dpage'] : 1;
		    $current_fpage = isset($_GET['fpage']) ? $_GET['fpage'] : 1;
		    
		    // 1. 首页按钮
		    if ($fields_page > 1) {
		        // ✅ 显式带上 table, dpage, fpage
		        echo '<a href="?table=' . urlencode($current_table) . '&cpage=1&dpage=' . $current_dpage . '&fpage=' . $current_fpage . '">&laquo; 首页</a> ';
		    } else {
		        echo '<span>&laquo; 首页</span> ';
		    }
		    
		    // 2. 中间页码逻辑
		    $show_num = 2;
		    $start = max(2, $fields_page - $show_num);
		    $end = min($total_fields_pages - 1, $fields_page + $show_num);
		    
		    if ($total_fields_pages <= 7) {
		        for ($i = 2; $i < $total_fields_pages; $i++) {
		            if ($i == $fields_page) {
		                echo '<strong>[' . $i . ']</strong> ';
		            } else {
		                // ✅ 显式带上 table, dpage, fpage
		                echo '<a href="?table=' . urlencode($current_table) . '&cpage=' . $i . '&dpage=' . $current_dpage . '&fpage=' . $current_fpage . '">' . $i . '</a> ';
		            }
		        }
		    } else {
		        if ($start > 2) {
		            echo '... ';
		        }
		        for ($i = $start; $i <= $end; $i++) {
		            if ($i == $fields_page) {
		                echo '<strong>[' . $i . ']</strong> ';
		            } else {
		                // ✅ 显式带上 table, dpage, fpage
		                echo '<a href="?table=' . urlencode($current_table) . '&cpage=' . $i . '&dpage=' . $current_dpage . '&fpage=' . $current_fpage . '">' . $i . '</a> ';
		            }
		        }
		        if ($end < $total_fields_pages - 1) {
		            echo '... ';
		        }
		    }
		    
		    // 3. 末页按钮
		    if ($fields_page < $total_fields_pages) {
		        // ✅ 显式带上 table, dpage, fpage
		        echo '<a href="?table=' . urlencode($current_table) . '&cpage=' . $total_fields_pages . '&dpage=' . $current_dpage . '&fpage=' . $current_fpage . '">末页 &raquo;</a>';
		    } else {
		        echo '<span>末页 &raquo;</span>';
		    }
		?>
		</div>
		<?php endif; ?>

    <?php endif; ?>
    <hr>
    
<h3>💾 数据库备份与管理</h3>

<!-- 1. 手动备份按钮 -->
<?php if ($mode == 'edit'): ?>
	<div style="margin-bottom: 20px;">
	    <form method="post" style="display: inline;">
	        <button type="submit" name="manual_backup" class="btn btn-success" <?php echo ($mode != 'edit') ? 'disabled' : ''; ?>>➕ 创建手动备份</button>
	    </form>
	</div>
<?php endif; ?>

<!-- 2. 备份文件列表与批量操作 -->
<h4>💾 备份文件列表</h4>
<?php 
// --- 文件列表分页逻辑 (新增) ---
$files_per_page = 10; // 每页显示数量 (与上面的 $per_page 保持一致)
$total_files = count($backup_files);
$total_files_pages = ceil($total_files / $files_per_page);

// 获取当前页码 (使用 fpage 避免与数据表的 page 冲突)
$files_page = isset($_GET['fpage']) ? intval($_GET['fpage']) : 1;
$files_page = max(1, min($files_page, $total_files_pages ?: 1)); // 修正页码范围

// 截取当前页数据
$paginated_files = array();
if ($total_files > 0) {
    $offset = ($files_page - 1) * $files_per_page;
    $paginated_files = array_slice($backup_files, $offset, $files_per_page);
}
?>
<?php if (count($paginated_files) > 0): ?>
<form method="post" onsubmit="return confirm('确定删除选中的文件吗？')">
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 50px;">序号</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">文件名</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 100px;">备份方式</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 150px;">备份时间</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 100px;">文件大小</th>
                <?php if ($mode == 'edit'): ?>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 185px;">操作</th>
                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 60px;">
                    <input type="checkbox" id="select_all" onclick="toggleAll(this)"> 全选
                </th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $index = ($files_page - 1) * $files_per_page + 1; ?>
            <?php foreach ($paginated_files as $bf): ?>
            <tr>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?php echo $index++; ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; font-family: monospace;"><?php echo htmlspecialchars($bf['name']); ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?php echo $bf['type']; ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?php echo date('Y-m-d H:i:s', $bf['time']); ?></td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><?php echo number_format($bf['size'] / 1024, 2); ?> KB</td>
                <?php if ($mode == 'edit'): ?>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">
                    <!-- 下载单个 -->
                    <a href="?download_file=<?php echo urlencode($bf['path']); ?>" class="btn btn-primary" style="padding: 3px 8px; font-size: 12px;">📥下载</a>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="backup_file" value="<?php echo $bf['path']; ?>">
                        <button type="submit" name="restore_backup" class="btn btn-danger" style="padding: 3px 8px; font-size: 12px;" onclick="return confirm('覆盖当前数据？')">🔁恢复</button>
                    </form>
                        <!-- 3. 删除表单 (新增) -->
				    <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除这个备份文件吗？')">
				        <input type="hidden" name="delete_single_backup" value="1">
				        <input type="hidden" name="file_path" value="<?php echo htmlspecialchars(basename($bf['path'])); ?>">
				        <button type="submit" name="delete_single" class="btn btn-danger" style="padding: 3px 8px; font-size: 12px;">🗑️删除</button>
				    </form>
                </td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">
                    <input type="checkbox" name="backup_files[]" value="<?php echo $bf['path']; ?>">
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 批量操作按钮 -->
    <?php if ($mode == 'edit'): ?>
    <div style="margin-top: 10px;">
        <!-- 批量下载 ZIP -->
        <button type="submit" name="download_zip" class="btn btn-success" style="padding: 5px 10px;">📦 批量下载为 ZIP</button>
        <!-- 批量删除 -->
        <button type="submit" name="delete_backups" class="btn btn-danger" style="padding: 5px 10px;" onclick="return confirm('确定删除？')">🗑️ 批量删除</button>
    </div>
    <?php endif; ?>

		<!-- 文件列表分页导航 (精简版：首页...中间...末页) -->
		<?php if ($total_files_pages > 1): // 只有页数大于1时才显示分页栏 ?>
		<div class="pagination" style="margin-top: 20px;">
		<?php
		    // 获取当前的 dpage 和 cpage (如果存在)
		    $current_dpage = isset($_GET['dpage']) ? $_GET['dpage'] : 1;
		    $current_cpage = isset($_GET['cpage']) ? $_GET['cpage'] : 1;
		    
		    // 1. 首页按钮
		    if ($files_page > 1) {
		        // ✅ 显式带上 table, dpage, cpage
		        echo '<a href="?table=' . urlencode($current_table) . '&fpage=1&dpage=' . $current_dpage . '&cpage=' . $current_cpage . '">&laquo; 首页</a> ';
		    } else {
		        echo '<span>&laquo; 首页</span> ';
		    }
		    
		    // 2. 中间页码逻辑
		    $show_num = 2;
		    $start = max(2, $files_page - $show_num);
		    $end = min($total_files_pages - 1, $files_page + $show_num);
		    
		    if ($total_files_pages <= 7) {
		        for ($i = 2; $i < $total_files_pages; $i++) {
		            if ($i == $files_page) {
		                echo '<strong>[' . $i . ']</strong> ';
		            } else {
		                // ✅ 显式带上 table, dpage, cpage
		                echo '<a href="?table=' . urlencode($current_table) . '&fpage=' . $i . '&dpage=' . $current_dpage . '&cpage=' . $current_cpage . '">' . $i . '</a> ';
		            }
		        }
		    } else {
		        if ($start > 2) {
		            echo '... ';
		        }
		        for ($i = $start; $i <= $end; $i++) {
		            if ($i == $files_page) {
		                echo '<strong>[' . $i . ']</strong> ';
		            } else {
		                // ✅ 显式带上 table, dpage, cpage
		                echo '<a href="?table=' . urlencode($current_table) . '&fpage=' . $i . '&dpage=' . $current_dpage . '&cpage=' . $current_cpage . '">' . $i . '</a> ';
		            }
		        }
		        if ($end < $total_files_pages - 1) {
		            echo '... ';
		        }
		    }
		    
		    // 3. 末页按钮
		    if ($files_page < $total_files_pages) {
		        // ✅ 显式带上 table, dpage, cpage
		        echo '<a href="?table=' . urlencode($current_table) . '&fpage=' . $total_files_pages . '&dpage=' . $current_dpage . '&cpage=' . $current_cpage . '">末页 &raquo;</a>';
		    } else {
		        echo '<span>末页 &raquo;</span>';
		    }
		    echo "<span style='margin-left: 20px;'>第 $files_page 页，共 $total_files_pages 页</span>";
		?>
		</div>
		<?php endif; ?>

</form>
<?php else: ?>
<p>暂无备份文件。</p>
<?php endif; ?>

</div>
	<!-- ✅ 新增：错误提示自动隐藏功能 -->
	<script>
	// 页面加载完成后执行
	document.addEventListener('DOMContentLoaded', function() {
	    var errorDiv = document.getElementById('login-error');
	    if (errorDiv) {
	        // 设置 8 秒后自动淡出并隐藏
	        setTimeout(function() {
	            // 简单的隐藏效果
	            errorDiv.style.opacity = '0';
	            errorDiv.style.transition = 'opacity 0.5s';
	            setTimeout(function() {
	                errorDiv.style.display = 'none';
	            }, 500);
	        }, 8000); // 8000 毫秒 = 8 秒
	    }
	});
	</script>
	
</body>
</html>