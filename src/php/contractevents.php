<?php
if (file_exists('init.php'))
    require_once("init.php");
else
    require_once("../init.php");

$err_msg = '';

if (isset($_POST['eventid'])) {
    $eventid = $_POST['eventid'];
    foreach ($_POST as $k => $v) {
        $$k = trim($v);
    }

    function safe_ymd2sec($d) {
        if (trim($d) === '') return 0;
        $t = ymd2sec($d);
        return is_numeric($t) ? $t : 0;
    }

    $start_ts = safe_ymd2sec($ev_startdate);
    $end_ts   = safe_ymd2sec($ev_enddate);

    // 校验
    if (trim($ev_startdate) === '') {
        $err_msg = t("Event start date cannot be empty");
    } elseif (trim($ev_enddate) === '') {
        $err_msg = t("Event end date cannot be empty");
    } elseif (trim($ev_description) === '') {
        $err_msg = t("Description cannot be empty");
    } elseif ($start_ts > $end_ts) {
        $err_msg = t("Start date cannot be later than end date");
    }

    // —————————— 核心修复：AJAX错误不破坏列表 ——————————
    if ($err_msg) {
        echo "<div class='ajax-error' style='color:red; padding:8px; font-weight:bold;'>$err_msg</div>";
        echo "<script>setTimeout(function(){jQuery('.ajax-error').fadeOut()}, 2500);</script>";
        include_event_list($contractid);
        exit;
    }

    if ($eventid === "new") {
        $sql = "INSERT INTO contractevents (contractid, siblingid, startdate, enddate, description)
                VALUES ('$contractid', '$ev_siblingid', '$start_ts', '$end_ts', '$ev_description')";
        db_exec($dbh, $sql, 0, 0, $lastid);

        $log_new = array(
            'event_info' => array(
                'contractid'   => $contractid,
                'siblingid'    => $ev_siblingid,
                'startdate'    => $ev_startdate,
                'enddate'      => $ev_enddate,
                'description'  => $ev_description
            )
        );

        addOperateLog(
            'contractevents',
            'add',
            'Added contract event ID %s',
            array($lastid),
            'contract',
            $contractid,
            null,
            $log_new
        );
    }
    elseif (is_numeric($eventid)) {
        $sth = $dbh->query("SELECT * FROM contractevents WHERE id='$eventid'");
        $old = $sth->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE contractevents
                SET siblingid='$ev_siblingid', startdate='$start_ts', enddate='$end_ts', description='$ev_description'
                WHERE id='$eventid'";
        db_exec($dbh, $sql);

        $log_old = array(
            'event_info' => array(
                'contractid'   => $old['contractid'],
                'siblingid'    => $old['siblingid'],
                'startdate'    => $old['startdate'] ? date('Y-m-d', $old['startdate']) : '',
                'enddate'      => $old['enddate'] ? date('Y-m-d', $old['enddate']) : '',
                'description'  => $old['description']
            )
        );
        $log_new = array(
            'event_info' => array(
                'contractid'   => $contractid,
                'siblingid'    => $ev_siblingid,
                'startdate'    => $ev_startdate,
                'enddate'      => $ev_enddate,
                'description'  => $ev_description
            )
        );

        addOperateLog(
            'contractevents',
            'update',
            'Updated contract event ID %s',
            array($eventid),
            'contract',
            $contractid,
            $log_old,
            $log_new
        );
    }
}
elseif (isset($_POST['deleventid'])) {
    $del_id = $_POST['deleventid'];
    $sth = $dbh->query("SELECT * FROM contractevents WHERE id='$del_id'");
    $old = $sth->fetch(PDO::FETCH_ASSOC);

    $sql = "DELETE FROM contractevents WHERE id='$del_id'";
    db_exec($dbh, $sql);

    $log_old = array(
        'event_info' => array(
            'contractid'   => $old['contractid'],
            'siblingid'    => $old['siblingid'],
            'startdate'    => $old['startdate'] ? date('Y-m-d', $old['startdate']) : '',
            'enddate'      => $old['enddate'] ? date('Y-m-d', $old['enddate']) : '',
            'description'  => $old['description']
        )
    );

    addOperateLog(
        'contractevents',
        'delete',
        'Deleted contract event ID %s',
        array($del_id),
        'contract',
        $old['contractid'],
        $log_old,
        null
    );
}

// 输出列表（单独封装，保证AJAX永远正常显示）
function include_event_list($contractid) {
    global $dbh;
    $sql = "SELECT * FROM contractevents WHERE contractid = $contractid ORDER BY id DESC";
    $sth = db_execute($dbh, $sql);
    $events = $sth->fetchAll(PDO::FETCH_ASSOC);
    ?>
<style>
.evtbl{width:100%;border-collapse:collapse;margin-top:5px;font-size:12px;}
.evtbl th,.evtbl td{border:1px solid #ccc;padding:4px;text-align:left;}
.evtbl th{background:#f5f5f5;}
.evrow:hover{background:#f8f8f8;}
</style>
<table class="evtbl">
<tr>
    <th><?php te("ID");?></th>
    <th><?php te("Event Start");?></th>
    <th><?php te("Event End");?></th>
    <th><?php te("Description");?></th>
    <th><?php te("Action");?></th>
</tr>
<?php if(count($events)==0):?>
<tr><td colspan="5" align="center"><?php te("No events");?></td></tr>
<?php endif;?>
<?php foreach($events as $ev):?>
<tr class="evrow">
    <td><?=$ev['id']?></td>
    <td><?=$ev['startdate']?date('Y-m-d',$ev['startdate']):''?></td>
    <td><?=$ev['enddate']?date('Y-m-d',$ev['enddate']):''?></td>
    <td><?=htmlspecialchars($ev['description'])?></td>
    <td>
        <button type="button" onclick="$('#ev_dialog').data('rowid','<?=$ev['id']?>').dialog('open')"><?php te("Edit");?></button>
        <button type="button" onclick="$('#ev_deldialog').data('rowid','<?=$ev['id']?>').dialog('open')"><?php te("Delete");?></button>
        <span id="eventid_<?=$ev['id']?>" style="display:none"><?=$ev['id']?></span>
        <span id="ev_siblingid_<?=$ev['id']?>" style="display:none"><?=$ev['siblingid']?></span>
        <span id="ev_startdate_<?=$ev['id']?>" style="display:none"><?=$ev['startdate']?date('Y-m-d',$ev['startdate']):''?></span>
        <span id="ev_enddate_<?=$ev['id']?>" style="display:none"><?=$ev['enddate']?date('Y-m-d',$ev['enddate']):''?></span>
        <span id="ev_description_<?=$ev['id']?>" style="display:none"><?=htmlspecialchars($ev['description'])?></span>
    </td>
</tr>
<?php endforeach;?>
</table>
<?php
}

// 正常页面加载时直接输出列表
$contractid = isset($_GET['id']) ? intval($_GET['id']) : intval($_POST['contractid']);
include_event_list($contractid);
?>
