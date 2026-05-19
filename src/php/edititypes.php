<?php 
if (!isset($initok)) {echo t("do not run this script directly");exit;}
/* Spiros Ioannou 2009 , sivann _at_ gmail.com */
$internaltypes=0;
// form submitted - 处理提交逻辑
if (isset($deltype)) {
    if ($deltype < $internaltypes) {
        echo htmlspecialchars(t("Type")) . " '$deltype' " . htmlspecialchars(t("cannot be deleted: internal type"));
    } else {
        $sql = "SELECT count(id) count from items WHERE itemtypeid=" . $_GET['deltype'];
        $sth = db_execute($dbh, $sql);
        $r = $sth->fetch(PDO::FETCH_ASSOC);
        $count = $r['count'];
        if ($count > 0) {
			$msg = sprintf(
			    t("Warning! There are %d item(s) of this type registered. Type not deleted!"),
			    $count
			);
            echo "<script>alert('" . addslashes(htmlspecialchars($msg)) . "'); history.back();</script>";
            exit;
        } else {
            // 获取旧数据
            $sql_old = "SELECT * FROM itemtypes WHERE id=" . $_GET['deltype'];
            $sth_old = db_execute($dbh, $sql_old);
            $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);
            
            // 原删除逻辑不动
            $sql = "DELETE from itemtypes where id=" . $_GET['deltype'];
            $sth = db_exec($dbh, $sql);
            // ===================== 仅修改此处 =====================
            $old_diff = array();
            $new_diff = null;
            $old_diff['itemtype_info'] = $old_data;
            addOperateLog(
                'itemtype',
                'delete',
                'Deleted item type ID %s',
                array($_GET['deltype']),
                'itemtype',
                $_GET['deltype'],
                $old_diff,
                $new_diff,
                1,
                ''
            );
            // ======================================================
            echo "<script>document.location='$scriptname?action=edititypes'</script>";
            exit;
        }
    }
}
// 处理新增和更新逻辑
if (isset($newtype)) {
    $newtype = htmlspecialchars($_POST['newtype']);
    $newhassoftware = $_POST['newhassoftware'];
    // 新增类型（原逻辑不动）
    if (strlen($newtype) > 1) {
        $checkSql = "SELECT id FROM itemtypes WHERE typedesc = '$newtype' LIMIT 1";
        $checkStmt = db_execute($dbh, $checkSql);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            $msg = t("Type already exists: ") . $newtype;
            echo "<script>alert('" . addslashes(htmlspecialchars($msg)) . "'); history.back();</script>";
            exit;
        } else {
            $sql = "INSERT INTO itemtypes (typedesc,hassoftware) values ('$newtype','$newhassoftware')";
            $sth = db_execute($dbh, $sql);
            $lastid = $dbh->lastInsertId();
            // ===================== 仅修改此处 =====================
            $old_diff = null;
            $new_diff = array();
            $new_diff['itemtype_info'] = array(
                'id'            => $lastid,
                'typedesc'      => $newtype,
                'hassoftware'   => $newhassoftware
            );
            addOperateLog(
                'itemtype',
                'add',
                'Created new item type ID %s',
                array($lastid),
                'itemtype',
                $lastid,
                $old_diff,
                $new_diff,
                1,
                ''
            );
            // ======================================================
        }
    }
    // 批量更新
    if (isset($_POST["ids"])) {
        // 旧数据
        $sth = $dbh->query("SELECT id, typedesc, hassoftware FROM itemtypes");
        $old_list = $sth->fetchAll(PDO::FETCH_ASSOC);
        $new_list = array();
        foreach ($_POST["ids"] as $i => $id) {
            $new_list[$id] = array(
                'id' => $id,
                'typedesc' => htmlspecialchars($_POST['descs'][$i]),
                'hassoftware' => $_POST['hassoftware'][$i]
            );
        }
        // 对比变动（只记改动项）
        $diff_old = array();
        $diff_new = array();
        foreach ($old_list as $old) {
            $id = $old['id'];
            if (!isset($new_list[$id])) continue;
            $new = $new_list[$id];
            if ((string)$old['typedesc'] !== (string)$new['typedesc'] || (string)$old['hassoftware'] !== (string)$new['hassoftware']) {
                $diff_old[$id] = $old;
                $diff_new[$id] = $new;
            }
        }
        // 格式：和 edititem 完全一样
        $old_diff = array();
        $new_diff = array();
        if (!empty($diff_old)) {
            $old_diff['itemtype_info'] = $diff_old;
            $new_diff['itemtype_info'] = $diff_new;
        }
        // 执行原更新
        for ($i = 0; $i < count($_POST["ids"]); $i++) {
            $descs[$i] = htmlspecialchars($_POST['descs'][$i]);
            $ids[$i] = $_POST['ids'][$i];
            $hassoftware[$i] = $_POST['hassoftware'][$i];
            $sql = "UPDATE itemtypes SET typedesc='" . $descs[$i] . "', hassoftware=" . $hassoftware[$i] . " WHERE id='" . $ids[$i] . "'";
            db_exec($dbh, $sql);
        }
        // ===================== 仅修改此处 =====================
        if (!empty($diff_old)) {
            addOperateLog(
                'itemtype',
                'update',
                'Updated item types ID %s',
                array_keys($diff_old),
                'itemtype',
                0,
                $old_diff,
                $new_diff,
                1,
                ''
            );
        }
        // ======================================================
    }
}
// ===================== 以下原代码完全不动 =====================
$sql = "SELECT * from itemtypes order by typedesc, id";
$sth = $dbh->query($sql);
$fixtypes = $sth->fetchAll(PDO::FETCH_ASSOC);
echo "<form method=post id='typeaddfrm' action='?action=edititypes&amp;dlg=$dlg' name='typeaddfrm'>";
echo "<input type=hidden name=action value='".$_POST["action"]."'>";
?>
<h1><?php te("Edit Item Types");?></h1>
<table id='itypetbl' class='brdr' border=0 >
<thead>
<tr><th>&nbsp;</th><th><?php te("ID");?></th><th><?php te("Description");?></th><th><?php te("Supports<br>Software");?><sup>1</sup></th></tr>
</thead>
<tbody>
<?php
for ($i = 0; $i < count($fixtypes); $i++) {
  $dbid = $fixtypes[$i]['id'];
  $itype = $fixtypes[$i]['typedesc'];
  if ($dbid >= "0") {
    echo "\n<tr><td title='".t("Delete ID").":$dbid'><a href='javascript:delconfirm(\"$itype\",\"$scriptname?action=edititypes&amp;deltype=$dbid\");'><img title='delete' src='images/delete.png' border=0></a></td><td>$dbid</td>";
  } else {
    echo "\n\n<tr><td>-</td>";
  }
  if ($fixtypes[$i]['hassoftware']) $s = "selected"; else $s = "";
  echo "<td><input type='text' name='descs[]' value=\"".htmlspecialchars($fixtypes[$i]['typedesc'])."\">\n".
       "<td><select name='hassoftware[]'>".
       "<option value='0'>".t("No")."</option>".
       "<option $s value='1'>".t("Yes")."</option></select>";
  echo "\n<input type=hidden name='ids[]' value='$dbid' >\n";
  echo "</td></tr>";
}
if (!isset($dbid)) $dbid = 0;
?>
<!-- 新增行 -->
<tr><th colspan=2>&nbsp;</th><th><?php te("Description");?></th><th><?php te("Supports<br>Software");?><sup>1</sup></th></tr>
<tr><td colspan=2><?php te("New");?>:</td><td>
     <input name='newtype' type='text'></td>
     <td><select name='newhassoftware'>
     <option value='0'><?php te("No");?></option>
     <option value='1'><?php te("Yes");?></option></select></td>
     </tr>
<tr><td style='text-align: right' colspan=4><button type="submit"><img src="images/save.png" alt='<?php te("Save");?>' > <?php te("Save");?></button></td></tr>
<tr><td style='text-align: left' colspan=4>
      <sup>1</sup><?php te("Select 'YES' if software can be installed <b>on</b> this item.<br> Only items supporting software are listed when <br>performing software - item associations");?>
    </td></tr>
</tbody>
</table>
</form>
