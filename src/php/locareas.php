<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}
// 后台数据处理，完全静默
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 保存区域
    if ((!isset($_POST['deleteareaid'])) && isset($_POST['areaids'])) {
        $nrows = count($_POST['areaids']);

        $original_areas = array();
        $s = $dbh->query("SELECT id, areaname FROM locareas WHERE locationid='$id'");
        while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
            $original_areas[$r['id']] = $r['areaname'];
        }

        $old_log = array();
        $new_log = array();

        for ($rn=0; $rn<$nrows; $rn++) {
            $current_area_id   = $_POST['areaids'][$rn];
            $current_area_name = trim($_POST['areanames'][$rn]);

            if (empty($current_area_name)) continue;

            $is_new = ($current_area_id == "new");

            $duplicate_check_sql = "SELECT id FROM locareas WHERE locationid='$id' AND areaname='" . addslashes($current_area_name) . "'";
            if (!$is_new) {
                $duplicate_check_sql .= " AND id != '$current_area_id'";
            }
            $duplicate_result = db_execute($dbh, $duplicate_check_sql);
            if ($duplicate_result->fetch(PDO::FETCH_ASSOC)) continue;

            if ($is_new && strlen($current_area_name) > 1) {
                db_exec($dbh, "INSERT INTO locareas (locationid, areaname) VALUES ('$id', '$current_area_name')");
                $new_id = $dbh->lastInsertId();
                $new_log[$new_id] = $current_area_name;
            } elseif (!$is_new) {
                $old_name = $original_areas[$current_area_id];
                if ($old_name != $current_area_name) {
                    db_exec($dbh, "UPDATE locareas SET areaname='$current_area_name' WHERE id='$current_area_id'");
                    $old_log[$current_area_id] = $old_name;
                    $new_log[$current_area_id] = $current_area_name;
                }
            }
        }

        if (!empty($old_log) || !empty($new_log)) {
            addOperateLog(
                'locarea',
                'update',
                'Updated areas for location ID %s',
                array($id),
                'location',
                $id,
                array('areas' => $old_log),
                array('areas' => $new_log),
                1,
                ''
            );
        }
    }

    // 删除区域
    if (isset($_POST['deleteareaid'])) {
        $delid = $_POST['deleteareaid'];
        $old_area = $dbh->query("SELECT * FROM locareas WHERE id='$delid'")->fetch(PDO::FETCH_ASSOC);
        $nareas = countlocarealinks($delid, $dbh);

        if (!$nareas) {
            db_exec($dbh, "DELETE FROM locareas WHERE id='$delid'");
            addOperateLog(
                'locarea',
                'delete',
                'Deleted area ID %s',
                array($delid),
                'location',
                $id,
                array('area' => $old_area),
                null,
                1,
                ''
            );
        }
    }

    // 只刷新父页，不输出任何东西，绝对不闪
    header("Content-Type: text/html; charset=utf-8");
    die("<script>if(window.parent)window.parent.location.reload();</script>");
}

// 界面渲染
$sql = "SELECT * FROM locareas WHERE locationid=$id";
$sthi = db_execute($dbh, $sql);
$ri = $sthi->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- 核心：提交到隐藏iframe，永远不闪父页面 -->
<form method="post" target="hidden_submit_frame">
<input type="hidden" name="id" value="<?php echo htmlspecialchars($id) ?>">

<table>
<?php foreach ($ri as $row) { ?>
<tr>
<td>
<input type="image" 
       onclick="return confirm('<?php te("Are you sure you want to delete this area?");?>');" 
       src="images/delete.png" 
       value="<?php echo $row['id'] ?>" 
       name="deleteareaid">
</td>
<td>
<input name="areaids[]" value="<?php echo $row['id'] ?>" type="hidden">
<input style="width:8em" name="areanames[]" value="<?php echo htmlspecialchars($row['areaname']) ?>">
</td>
</tr>
<?php } ?>

<tr>
<td></td>
<td>
<input name="areaids[]" value="new" type="hidden">
<input style="width:8em" name="areanames[]" value="">
</td>
</tr>
</table>

<input type="submit" value="<?php te("Save areas"); ?>">
</form>

<!-- 隐藏提交iframe，绝对不闪屏核心！！！ -->
<iframe name="hidden_submit_frame" style="display:none;"></iframe>
