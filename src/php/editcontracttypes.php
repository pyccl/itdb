<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

/* Spiros Ioannou 2009 , sivann _at_ gmail.com */
$internaltypes=1;
// --- 处理删除主类型 ---
if (isset($deltype)) {
  if ($deltype <= $internaltypes) {
    $msg = sprintf(t("Type '%s' cannot be deleted: internal type"), $deltype);
    echo "<script>alert(" . json_encode($msg) . "); history.back();</script>";
    exit;
  } else {

    // ====================== 新增：检查是否有子类型 ======================
    $sql_check_sub = "SELECT COUNT(id) AS cnt FROM contractsubtypes WHERE contypeid = :contypeid";
    $stmt_check = $dbh->prepare($sql_check_sub);
    $stmt_check->execute(array(':contypeid' => $deltype));
    $sub_count = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($sub_count['cnt'] > 0) {
      $msg = t("Cannot delete type: it has existing subtypes");
      echo "<script>alert('".addslashes($msg)."'); history.back();</script>";
      exit;
    }
    // ====================================================================

    $sql = "SELECT count(id) count from contracts WHERE type=$deltype";
    $sth = db_execute($dbh, $sql);
    $r = $sth->fetch(PDO::FETCH_ASSOC);
    $count = $r['count'];
    if ($count > 0) {
      $msg = sprintf(t("Warning! There are %d contract(s) of this type registered. Type not deleted!"), $count);
      echo "<script>alert('".addslashes($msg)."'); history.back();</script>";
      exit;
    } else {
      $sql_old = "SELECT * FROM contracttypes WHERE id=" . $_GET['deltype'];
      $sth_old = db_execute($dbh, $sql_old);
      $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);
      $sql = "DELETE from contracttypes where id=".$_GET['deltype'];
      $sth = db_exec($dbh, $sql);
      $sql = "DELETE from contractsubtypes where contypeid=".$_GET['deltype'];
      $sth = db_exec($dbh, $sql);
      $old_diff = array();
      $new_diff = null;
      $old_diff['contracttype_info'] = $old_data;
      addOperateLog(
          'contracttype',
          'delete',
          'Deleted contract type ID %s',
          array($_GET['deltype']),
          'contracttype',
          $_GET['deltype'],
          $old_diff,
          $new_diff,
          1,
          ''
      );
      echo "<script>document.location='$scriptname?action={$_GET['action']}'</script>";
      exit;
    }
  }
}
// --- 处理删除子类型 ---
if (isset($delsubtype)) {
  $sql_old = "SELECT * FROM contractsubtypes WHERE id=" . $_GET['delsubtype'];
  $sth_old = db_execute($dbh, $sql_old);
  $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);
  $sql = "DELETE from contractsubtypes where id=".$_GET['delsubtype'];
  $sth = db_exec($dbh, $sql);
  $old_diff = array();
  $new_diff = null;
  $old_diff['contractsubtype_info'] = $old_data;
  addOperateLog(
      'contractsubtype',
      'delete',
      'Deleted contract subtype ID %s',
      array($_GET['delsubtype']),
      'contractsubtype',
      $_GET['delsubtype'],
      $old_diff,
      $new_diff,
      1,
      ''
  );
  echo "<script>document.location='$scriptname?action={$_GET['action']}'</script>";
  exit;
}
// --- 处理保存主类型 ---
if (isset($savetype)) {
  if (isset($_POST['newtype'])) {
    $name = trim($_POST['newtype']);
    if (strlen($name) > 1) {
      $checkSql = "SELECT id FROM contracttypes WHERE name = :name";
      $checkStmt = $dbh->prepare($checkSql);
      $checkStmt->execute(array(':name' => $name));
      $exists = $checkStmt->fetch();
      if ($exists) {
        $msg = sprintf('%s %s', t("Contract Type already exists:"), htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
        echo "<script>alert('".addslashes($msg)."'); history.back();</script>";
        exit;
      } else {
        $sql = "INSERT into contracttypes (name) values (:name)";
        $sth = $dbh->prepare($sql);
        $sth->execute(array(':name' => $name));
        $lastid = $dbh->lastInsertId();
        $old_diff = null;
        $new_diff = array();
        $new_diff['contracttype_info'] = array(
            'id'   => $lastid,
            'name' => $name
        );
        addOperateLog(
            'contracttype',
            'add',
            'Created new contract type ID %s',
            array($lastid),
            'contracttype',
            $lastid,
            $old_diff,
            $new_diff,
            1,
            ''
        );
      }
    }
  }
  if (isset($_POST["ids"])) {
    $sth = $dbh->query("SELECT id, name FROM contracttypes");
    $old_list = $sth->fetchAll(PDO::FETCH_ASSOC);
    $new_list = array();
    foreach ($_POST["ids"] as $i => $id) {
      $new_list[$id] = array(
          'id'   => $id,
          'name' => trim($_POST['descs'][$i])
      );
    }
    $diff_old = array();
    $diff_new = array();
    foreach ($old_list as $old) {
      $id = $old['id'];
      if (!isset($new_list[$id])) continue;
      $new = $new_list[$id];
      if ((string)$old['name'] !== (string)$new['name']) {
        $diff_old[$id] = $old;
        $diff_new[$id] = $new;
      }
    }
    for ($i = 0; $i < count($_POST["ids"]); $i++) {
      $descs[$i] = trim($_POST['descs'][$i]);
      $ids[$i] = $_POST['ids'][$i];
      $sql = "UPDATE contracttypes SET name=:name WHERE id=:id";
      $sth = $dbh->prepare($sql);
      $sth->execute(array(':name' => $descs[$i], ':id' => $ids[$i]));
    }
    if (!empty($diff_old)) {
      $old_diff = array();
      $new_diff = array();
      $old_diff['contracttype_info'] = $diff_old;
      $new_diff['contracttype_info'] = $diff_new;
      addOperateLog(
          'contracttype',
          'update',
          'Updated contract types ID %s',
          array_keys($diff_old),
          'contracttype',
          0,
          $old_diff,
          $new_diff,
          1,
          ''
      );
    }
  }
}
elseif (isset($savesubtype)) {
  if (isset($_POST['newsubtype'])) {
    $name = trim($_POST['newsubtype']);
    $subtypesof = $_POST['subtypesof'];
    if (strlen($name) > 1) {
      $checkSql = "SELECT id FROM contractsubtypes WHERE name = :name AND contypeid = :contypeid";
      $checkStmt = $dbh->prepare($checkSql);
      $checkStmt->execute(array(':name' => $name, ':contypeid' => $subtypesof));
      $exists = $checkStmt->fetch();
      if ($exists) {
        $mainTypeName = t("Unknown");
        $mainTypeSql = "SELECT name FROM contracttypes WHERE id = :id";
        $mainStmt = $dbh->prepare($mainTypeSql);
        $mainStmt->execute(array(':id' => $subtypesof));
        $mainRow = $mainStmt->fetch();
        if ($mainRow) {
          $mainTypeName = $mainRow['name'];
        }
        $msg = sprintf(t("Subtype '%s' already exists under type '%s'"), htmlspecialchars($name), $mainTypeName);
        echo "<script>alert('".addslashes($msg)."'); history.back();</script>";
        exit;
      } else {
        $sql = "INSERT into contractsubtypes (name, contypeid) values (:name, :contypeid)";
        $sth = $dbh->prepare($sql);
        $sth->execute(array(':name' => $name, ':contypeid' => $subtypesof));
        $lastid = $dbh->lastInsertId();
        $old_diff = null;
        $new_diff = array();
        $new_diff['contractsubtype_info'] = array(
            'id'         => $lastid,
            'name'       => $name,
            'contypeid'  => $subtypesof
        );
        addOperateLog(
            'contractsubtype',
            'add',
            'Created new contract subtype ID %s',
            array($lastid),
            'contractsubtype',
            $lastid,
            $old_diff,
            $new_diff,
            1,
            ''
        );
      }
    }
  }
  if (isset($_POST["subids"])) {
    $sth = $dbh->query("SELECT id, name, contypeid FROM contractsubtypes");
    $old_list = $sth->fetchAll(PDO::FETCH_ASSOC);
    $new_list = array();
    foreach ($_POST["subids"] as $i => $id) {
      $new_list[$id] = array(
          'id'         => $id,
          'name'       => trim($_POST['subdescs'][$i]),
          'contypeid'  => $_POST['subtypesof']
      );
    }
    $diff_old = array();
    $diff_new = array();
    foreach ($old_list as $old) {
      $id = $old['id'];
      if (!isset($new_list[$id])) continue;
      $new = $new_list[$id];
      if ((string)$old['name'] !== (string)$new['name']) {
        $diff_old[$id] = $old;
        $diff_new[$id] = $new;
      }
    }
    for ($i = 0; $i < count($_POST["subids"]); $i++) {
      $subdescs[$i] = trim($_POST['subdescs'][$i]);
      $subids[$i] = $_POST['subids'][$i];
      $sql = "UPDATE contractsubtypes SET name=:name WHERE id=:id";
      $sth = $dbh->prepare($sql);
      $sth->execute(array(':name' => $subdescs[$i], ':id' => $subids[$i]));
    }
    if (!empty($diff_old)) {
      $old_diff = array();
      $new_diff = array();
      $old_diff['contractsubtype_info'] = $diff_old;
      $new_diff['contractsubtype_info'] = $diff_new;
      addOperateLog(
          'contractsubtype',
          'update',
          'Updated contract subtypes ID %s',
          array_keys($diff_old),
          'contractsubtype',
          0,
          $old_diff,
          $new_diff,
          1,
          ''
      );
    }
  }
}
$sql = "select * from contracttypes where id <=$internaltypes UNION all select * from (select * from contracttypes where id>1 order by name)";
$sth = $dbh->query($sql);
$contracttypes = $sth->fetchAll(PDO::FETCH_ASSOC);
?>
<form method=post name='typeaddfrm'>
<input type=hidden name=action value="<?php echo htmlspecialchars($_GET["action"]); ?>">
<h1><?php te("Edit Contract Types");?></h1>
<div style='width:80%;border:0px solid red;margin-left:auto;margin-right:auto;'>
  <div style='float:left;margin-right:15px;'>
  <table border=0 class='brdr' >
  <tr><th>&nbsp;</th><th><?php te("ID");?></th><th><?php te("Type Names");?></th><th></th></tr>
  <?php
  for ($i=0;$i<count($contracttypes);$i++) {
    $dbid = $contracttypes[$i]['id'];
    $itype = $contracttypes[$i]['name'];
    if ($dbid > $internaltypes) {
      echo "\n<tr><td><a href='javascript:delconfirm(\"$itype\",\"$scriptname?action=$action&amp;deltype=$dbid\");'><img title='".t("Delete ID").":$dbid' src='images/delete.png' border=0></a></td>";
    } else {
      echo "\n\n<tr><td title='".t("ID").":$dbid'>-</td>";
    }
    echo "<td>$dbid</td>";
    echo "<td><input size=30 type='text' name='descs[]' value=\"".htmlspecialchars($contracttypes[$i]['name'])."\">
<input type=hidden name='ids[]' value='$dbid' >\n";
    echo "</td>";
    echo "<td><button type=submit name='subtypesof' value='$dbid'>".t("View/Edit Subtypes")."</button></td>";
    echo "</tr>";
  }
  ?>
  <tr><td colspan=2><?php te("New");?>:</td><td><input size='30' name='newtype' type='text'></td></tr>
  <tr><td style='text-align: right' colspan=4>
    <button name='savetype' type="submit">
      <img src="images/save.png" alt='<?php te("Save");?>' > <?php te("Save");?>
    </button>
  </td></tr>
  </form>
  </table>
  </div>
  <?php
  if (isset($_POST['subtypesof'])) {
    $subtypesof = $_POST['subtypesof'];
  ?>
  <div style='float:left;'>
  <form method=post name='subtypeaddfrm'>
  <table class='brdr'>
  <tr><th>&nbsp;</th><th><?php te("ID");?></th><th><?php te("Subtypes of type ");?> <?php echo htmlspecialchars($subtypesof)?> <?php te("Names");?></th></tr>
  <?php
  $sql = "SELECT * from contractsubtypes WHERE contypeid=$subtypesof order by name";
  $sth = $dbh->query($sql);
  $contractsubtypes = $sth->fetchAll(PDO::FETCH_ASSOC);
  echo "<input type=hidden name='subtypesof' value='".htmlspecialchars($subtypesof)."' >\n";
  for ($i=0;$i<count($contractsubtypes);$i++) {
    $dbid = $contractsubtypes[$i]['id'];
    $itype = $contractsubtypes[$i]['name'];
    echo "\n<tr><td><a href='javascript:delconfirm(\"$itype\",\"$scriptname?action=$action&amp;delsubtype=$dbid\");'><img title='".t("Delete")."' src='images/delete.png' border=0></a></td>";
    echo "\n<td>$dbid</td>".
         "<td><input size=30 type='text' name='subdescs[]' value=\"".htmlspecialchars($contractsubtypes[$i]['name'])."\">
<input type=hidden name='subids[]' value='$dbid' >\n";
    echo "</td></tr>";
  }
  ?>
  <tr><td colspan=2><?php te("New");?>:</td><td><input size='30' name='newsubtype' type='text'></td></tr>
  <tr><td style='text-align: right' colspan=4>
    <button type='submit' name='savesubtype'>
      <img src="images/save.png" alt='<?php te("Save");?>' > <?php te("Save");?>
    </button>
  </td></tr>
  <?php
  }
  ?>
  </table>
  </div>
  </form>
</div>
