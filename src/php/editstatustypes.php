<?php
if (!isset($initok)) {echo t("do not run this script directly");exit;}
/* Spiros Ioannou 2009 , sivann _at_ gmail.com */
//echo "<pre>"; print_r($_GET); print_r($_POST);
$internaltypes="4";
$formvars=array("id", "statusdesc");

// 删除逻辑
if (isset($_GET['deltype']) && $_GET['deltype']<=$internaltypes) {
    echo t("Type")." '{$_GET['deltype']}' ".t("cannot be deleted: internal type");
}
elseif (isset($_GET['deltype'])) {
    $deltype=$_GET['deltype'];
    $sql="SELECT count(id) count from items WHERE status=$deltype";
    $sth=db_execute($dbh,$sql);
    $r=$sth->fetch(PDO::FETCH_ASSOC);
    $count=$r['count'];
    if ($count>0) {
        echo "<b>" . sprintf(t("Warning! There are %d item(s) of this status registered. Type not deleted!"), $count) . "</b>";
    } else {
        $sth_old = $dbh->query("SELECT * FROM statustypes WHERE id=$deltype");
        $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);
        addOperateLog(
            'statustype',
            'delete',
            'Deleted status type ID %s',
            array($deltype),
            'statustype',
            $deltype,
            $old_data,
            null,
            1,
            ''
        );
        $sql="DELETE from statustypes where id=".$_GET['deltype'];
        $sth=db_exec($dbh,$sql);
        echo "<script>location.href='$scriptname?action={$_GET['action']}';</script>";
        exit;
    }
}

// 初始化提示信息数组
$inserted = array();
$duplicates = array();

// 批量更新收集
$batch_update_ids = [];
$batch_old = [];
$batch_new = [];

if (isset($_POST['statusdesc'])) {
    $nrows=count($_POST['id']);
    for ($rn=0;$rn<$nrows;$rn++) {
        $id = $_POST['id'][$rn];
        $desc = trim($_POST['statusdesc'][$rn]);
        if (empty($desc)) continue;

        // 重复检查
        $check_sql = "SELECT COUNT(*) FROM statustypes WHERE statusdesc = ? COLLATE NOCASE";
        if ($id != "new") {
            $check_sql .= " AND id != ?";
        }
        $check_sth = $dbh->prepare($check_sql);
        $params = array($desc);
        if ($id != "new") $params[] = $id;
        $check_sth->execute($params);
        $count = $check_sth->fetchColumn();

        if ($count == 0) {
            $quoted_desc = $dbh->quote($desc);
            $color_val = isset($_POST['color'][$rn]) ? $_POST['color'][$rn] : '#000000';

            // ====================== 新增：保持原来单条日志 ======================
            if (($id == "new") && (strlen($desc)>1)) {
                $sql = "INSERT INTO statustypes (statusdesc, color) VALUES ($quoted_desc, '$color_val')";
                db_exec($dbh, $sql);
                $new_id = $dbh->lastInsertId();

                $new_data = [
                    'statusdesc' => $desc,
                    'color' => $color_val
                ];
                addOperateLog(
                    'statustype',
                    'add',
                    'Created status type ID %s',
                    [$new_id],
                    'statustype',
                    $new_id,
                    null,
                    $new_data,
                    1,
                    ''
                );
                $inserted[] = $desc;
            }
            // ====================== 更新：合并批量日志 ======================
            elseif ($id != "new"){
                $id_int = intval($id);
                $old = $dbh->query("SELECT * FROM statustypes WHERE id=$id_int")->fetch(PDO::FETCH_ASSOC);

                $sql = "UPDATE statustypes SET statusdesc=$quoted_desc, color='$color_val' WHERE id=" . $id_int;
                db_exec($dbh, $sql);

                $new = $dbh->query("SELECT * FROM statustypes WHERE id=$id_int")->fetch(PDO::FETCH_ASSOC);

                $diff_old = [];
                $diff_new = [];
                $fields = ['id','statusdesc','color'];
                foreach($fields as $k){
                    $ov = isset($old[$k]) ? (string)$old[$k] : '';
                    $nv = isset($new[$k]) ? (string)$new[$k] : '';
                    if($ov !== $nv){
                        $diff_old[$k] = $ov;
                        $diff_new[$k] = $nv;
                    }
                }
                if (!empty($diff_old)) {
                    $batch_update_ids[] = $id_int;
                    $batch_old[$id_int] = $diff_old;
                    $batch_new[$id_int] = $diff_new;
                }
                $inserted[] = $desc;
            }
        } else {
            $duplicates[] = $desc;
        }
    }

    // ====================== 批量更新：合并1条，多个占位符 ======================
    if (!empty($batch_update_ids)) {
        $placeholders = implode(', ', array_fill(0, count($batch_update_ids), '%d'));
        $content = "Updated status type ID {$placeholders}";
        addOperateLog(
            'statustype',
            'update',
            $content,
            $batch_update_ids,
            'statustype',
            0,
            $batch_old,
            $batch_new,
            1,
            ''
        );
    }

    // 提示信息
    $messages = array();
    if (!empty($duplicates)) {
        $messages[] = sprintf(t("Warning: %s - already exists"), implode(", ", $duplicates));
    }
    if (empty($duplicates) && !empty($inserted)) {
        $messages[] = t("Saved successfully");
    }
    if (!empty($messages)) {
        $alert_text = implode("\\n", $messages);
        echo "<script type='text/javascript'>alert('$alert_text');</script>";
    }
}

$sql="SELECT * from statustypes order by id";
$sth=db_execute($dbh,$sql);
?>
<form method=post name='actionaddfrm'>
<h1><?php te("Edit Status Types");?></h1>
<table class=brdr>
<tr><th>&nbsp;</th><th><?php te("ID");?></th><th><?php te("Description");?></th><th><?php te("Color");?></th>
<?php
$i=0;
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
$i++;
    $dbid=$r['id'];
    if ($dbid>$internaltypes)
        echo "\n<tr><td title='".t("Delete")."'><a href='javascript:delconfirm(\"$itype\",\"$scriptname?action=$action&amp;deltype=$dbid\");'>".
             "<img src='images/delete.png' border=0></a></td>";
    else
        echo "\n\n<tr><td title='".t("Internal Type")." ($dbid), ".t("cannot delete")."'></td>";
    $x=attrofstatus($dbid,$dbh);
    $attr=$x[0];
    $statustxt=$x[1];

    $id_display = ($r['id'] == 'new') ? 'Auto' : $r['id'];
    echo "<td nowrap><input type='text' name='id_display[]' value='$id_display' readonly style='background:#eee; cursor:not-allowed;' size='3'></td>";
    echo "<td><input type='hidden' name='id[]' value='".$r['id']."'>";
    echo "<span $attr>&nbsp;</span><input size='15' maxlength='20' type='text' name='statusdesc[]' value=\"".$r['statusdesc']."\"></td>\n";
    echo "<td><input type='color' name='color[]' value='".htmlspecialchars($r['color'] ? $r['color'] : '#cccccc')."' style='width: 50px;'></td>";
    echo "</tr>\n\n";
}
echo "<tr><td><input type=hidden name='id[]' value='new' readonly size=3>".t("New").":</td>\n";
echo "<td>&nbsp;</td><td><input size=15 maxlen=20 type=text name='statusdesc[]' ></td>\n";
$rand_color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
echo "<td><input type='color' name='color[]' value='$rand_color' style='width: 50px;'></td>\n";
?>
<tr><td colspan=2><button type="submit"><img src="images/save.png" alt="<?php te("Save");?>" > <?php te("Save");?></button></td></tr>
</table>
</form>
</body>
</html>
