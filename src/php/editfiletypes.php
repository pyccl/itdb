<?php 
if (!isset($initok)) {echo t("do not run this script directly");exit;}
/* Spiros Ioannou 2009 , sivann _at_ gmail.com */
//echo "<pre>"; print_r($_GET); print_r($_POST);
$internaltypes="10";
$formvars=array("id", "typedesc");
//form submitted
if  (isset($_GET['deltype']) && $_GET['deltype']<=$internaltypes) { //delete an entry
	// 1. 获取并清理变量 (安全做法)
	$type_name = isset($_GET['deltype']) ? htmlspecialchars($_GET['deltype']) : 'Unknown';
	
	// 2. 使用 sprintf 组合句子
	echo sprintf(t("Type '%s' cannot be deleted: internal type"), $type_name);
	
}
elseif (isset($_GET['deltype'])) {
    // 获取类型 ID
    $deltype = intval($_GET['deltype']);
    
    // 1. 检查关联文件数量
    $sql = "SELECT count(id) count from files WHERE type='$deltype'";
    $sth = db_execute($dbh, $sql);
    $r = $sth->fetch(PDO::FETCH_ASSOC);
    $count = $r['count'];
    if ($count > 0) {
        // --- 有文件：弹窗提示并返回 ---
        echo "<script>";
        // 构建国际化提示语
		$alertMsg = sprintf(
		    t("Cannot delete type '%s': There are %d existing files associated with this type"), 
		    $deltype, 
		    $count
		);
        // 输出弹窗代码
        echo "alert('" . addslashes($alertMsg) . "');";
        echo "history.go(-1);"; // 返回上一页
        echo "</script>";
        exit; // 立即终止，防止执行下面的删除代码
    } else {
        // --- 无文件：执行删除操作 ---
        $sth_old = $dbh->query("SELECT * FROM filetypes WHERE id=$deltype");
        $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);
        addOperateLog(
            'filetype',
            'delete',
            'Deleted file type ID %s',
            array($deltype),
            'filetype',
            $deltype,
            $old_data,
            null,
            1,
            ''
        );

        $deleteSql = "DELETE FROM filetypes WHERE id = $deltype";
        $deleteSth = db_execute($dbh, $deleteSql);
        
        if ($deleteSth) {
            // --- 删除成功：弹窗提示并跳转 ---
            echo "<script>";
            echo "alert('" . t("Delete successful") . "');";
            echo "location.href = '?action=editfiletypes';"; // 刷新当前页面或跳转到列表页
            echo "</script>";
        } else {
            // --- 删除失败：弹窗提示 ---
            echo "<script>";
            echo "alert('" . t("Delete failed") . "');";
            echo "history.go(-1);";
            echo "</script>";
        }
        exit;
    }
}
//if came here from a form post, update db with new values
if (isset($_POST['typedesc'])) {
  $nrows = count($_POST['typedesc']);
  $errors = array();
  $valid_data = array(); // 用于存储验证通过的数据
  
  // 【仅新增】批量更新日志收集变量
  $batch_update_ids = [];
  $batch_old_data = [];
  $batch_new_data = [];

  // --- 第一阶段：验证数据 (Validation Phase) ---
  for ($rn = 0; $rn < $nrows; $rn++) {
    $id = trim($_POST['id'][$rn]);
    $desc = trim($_POST['typedesc'][$rn]);
    // 1. 跳过空行
    if (strlen($desc) <= 1) {
      continue;
    }
    // 2. 检查是否已存在于数据库中 (排除当前编辑的ID)
    $exclude_sql = ($id != "new") ? " AND id != " . intval($id) : "";
    $sql_check = "SELECT id FROM filetypes WHERE typedesc = '$desc' $exclude_sql LIMIT 1";
    $sth_check = db_execute($dbh, $sql_check);
    $exists = $sth_check->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
		$errors[] = sprintf(t("File Type '%s' already exists. Please use a different name."), $desc);
    } else {
      // 如果验证通过，将数据存入数组，等待执行
      $valid_data[] = array('id' => $id, 'desc' => $desc);
    }
  }
  // --- 第二阶段：处理结果 (Action Phase) ---
  // 如果有错误，弹窗并阻止后续操作
  if (!empty($errors)) {
    echo "<script>alert('" . addslashes(implode("\n", $errors)) . "'); history.back();</script>";
    exit; // 退出脚本，防止执行下方的HTML
  }
  // 如果没有错误，执行数据库写入
  if (!empty($valid_data)) {
    foreach ($valid_data as $row) {
      $id = $row['id'];
      $desc = $row['desc'];
      
      if ($id == "new") {
        // 新增
        $sql = "INSERT INTO filetypes (typedesc) VALUES ('$desc')";
        db_exec($dbh, $sql);
        $new_id = $dbh->lastInsertId();
        $new_data = ['typedesc' => $desc];
        addOperateLog(
            'filetype',
            'add',
            'Created file type ID %s',
            [$new_id],
            'filetype',
            $new_id,
            null,
            $new_data,
            1,
            ''
        );
      } else {
        // 更新
        $id_int = intval($id);
        $sth_old = $dbh->query("SELECT * FROM filetypes WHERE id=$id_int");
        $old = $sth_old->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE filetypes SET typedesc='$desc' WHERE id=" . intval($id);
        db_exec($dbh, $sql);

        $sth_new = $dbh->query("SELECT * FROM filetypes WHERE id=$id_int");
        $new = $sth_new->fetch(PDO::FETCH_ASSOC);
        
        $diff_old = [];
        $diff_new = [];
        $fields = ['id','typedesc'];
        foreach($fields as $k){
            $ov = isset($old[$k]) ? (string)$old[$k] : '';
            $nv = isset($new[$k]) ? (string)$new[$k] : '';
            if($ov !== $nv){
                $diff_old[$k] = $ov;
                $diff_new[$k] = $nv;
            }
        }
        // 【仅修改】不写日志，改为收集
        if(!empty($diff_old)){
            $batch_update_ids[] = $id_int;
            $batch_old_data[$id_int] = $diff_old;
            $batch_new_data[$id_int] = $diff_new;
        }
      }
    }

    // 【仅新增】循环结束后统一写 1 条合并更新日志
    if (!empty($batch_update_ids)) {
        $placeholders = implode(', ', array_fill(0, count($batch_update_ids), '%s'));
        addOperateLog(
            'filetype',
            'update',
            "Updated file type ID {$placeholders}",
            $batch_update_ids,
            'filetype',
            0,
            $batch_old_data,
            $batch_new_data,
            1,
            ''
        );
    }
  }
  // 操作成功，跳转页面
  echo "<script>document.location='$scriptname?action={$_GET['action']}';</script>";
  echo "<a href='$scriptname?action={$_GET['action']}'>" . t("Go here") . "</a>";
  exit;
} // if (POST)

$sql="SELECT * from filetypes order by id";
$sth=db_execute($dbh,$sql);
?>
<form method=post name='actionaddfrm'>
<h1><?php te("Edit File Types");?></h1>
<table class=brdr>
<tr><th>&nbsp;</th><th><?php te("ID");?></th><th><?php te("Description");?></th></tr>
<?php 
$i=0;
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
$i++;
  $dbid=$r['id'];
  $dbtypedesc=$r['typedesc'];
  if ($dbid>$internaltypes) 
    echo "\n<tr><td title='".t("Delete")." [".t("ID").": ".$dbid."]'><a href='javascript:delconfirm(\"[ID: {$dbid}] $dbtypedesc\",\"$scriptname?action=$action&amp;deltype=$dbid\");'>".
         "<img src='images/delete.png' border=0></a></td><td>$dbid</td>";
  else
    echo "\n\n<tr><td title='".t("Internal Type")." ($dbid), ".t("cannot be deleted or changed").".'></td><td>$dbid</td>";
  echo "<td nowrap><input type=hidden name='id[]' value='".$r['id']."' readonly size=3>\n";
  if ($dbid<=$internaltypes) echo "<input size=15 maxlen=20 type=text name='typedesc[]' readonly value=\"".$r['typedesc']."\"></td>\n";
  if ($dbid>$internaltypes) echo "<input size=15 maxlen=20 type=text name='typedesc[]' value=\"".$r['typedesc']."\"></td>\n";
  echo "</tr>\n\n";
}
//empty line to add new items at bottom
echo "<tr><td><input type=hidden name='id[]' value='new' readonly size=3>".t("New").":</td>\n";
echo "<td>new</td><td><input size=15 maxlen=20 type=text name='typedesc[]' ></td>\n";
?>
<tr><td colspan=2><button type="submit"><img src="images/save.png" alt='<?php te("Save");?>'> <?php te("Save");?></button></td></tr>
</table>
</form>
</body>
</html>
