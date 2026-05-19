<SCRIPT LANGUAGE="JavaScript"> 
$(document).ready(function() {
  $("#tabs").tabs();
  $("#tabs").show();
  $('input#itemsfilter').quicksearch('table#itemslisttbl tbody tr');
  $('input#softfilter').quicksearch('table#softlisttbl tbody tr');
  $('input#contrfilter').quicksearch('table#contrlisttbl tbody tr');
});
</script>
<?php 
if (!isset($initok)) {echo t("do not run this script directly");exit;}

/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */

// 发票字段列表（日志对比用，同资产 formvars 结构）
$inv_formvars = array(
    'vendorid','buyerid','number','description','date'
);

//delete invoice
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    
    // 文件关联处理
    $f = invid2files($delid,$dbh);
    $fids = array();
    for ($c=0;$c<count($f);$c++) {
        array_push($fids, $f[$c]['id']);
    }

    $sql = "DELETE from invoice2file where invoiceid=$delid";
    $sth = db_exec($dbh,$sql);

    for ($c=0;$c<count($fids);$c++) {
        $nlinks = countfileidlinks($fids[$c],$dbh);
        if ($nlinks == 0) delfile($fids[$c],$dbh);
    }

    // 删除主表与关联
    $sql = "DELETE from invoices where id=$delid";
    db_exec($dbh,$sql);

    $sql = "DELETE from item2inv where invid=$delid";
    db_exec($dbh,$sql);

    $sql = "DELETE from soft2inv where invid=$delid";
    db_exec($dbh,$sql);

    $sql = "DELETE from contract2inv where invid=$delid";
    db_exec($dbh,$sql);

    $sql = "UPDATE software SET invoiceid='' where invoiceid=$delid";
    db_exec($dbh,$sql);

    // ====================== 发票删除日志 ======================
    addOperateLog(
        'invoice',
        'delete',
        'Deleted invoice ID %s',
        array($delid),
        'invoice',
        $delid,
        null,
        null,
        1,
        ''
    );

    // 写入 actions 表
    $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
    $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                   VALUES ($delid, ".time().", 'Deleted invoice by $user', '', 1, ".time().")";
    db_exec($dbh, $sql_action);

    echo "<script>document.location='$scriptname?action=listinvoices'</script>";
    echo "<a href='$scriptname?action=listinvoices'>".t("Go here")."</a></body></html>"; 
    exit;
}

//remove association and delete file
if (isset($_GET['delfid'])) {
    $fileid = intval($_GET['delfid']);
    $itemid = intval($id);

    $sql = "DELETE from invoice2file where fileid=$fileid";
    $sth = db_exec($dbh,$sql);

    // 文件删除日志
    addOperateLog(
        'file',
        'delete',
        'Deleted file %s from invoice %s',
        array($fileid, $id),
        'invoice',
        $id,
        null,
        null,
        1,
        ''
    );

    $nlinks = countfileidlinks($fileid,$dbh);
    if ($nlinks == 0) delfile($fileid,$dbh);

    echo "<script>window.location='$scriptname?action=$action&id=$id'</script> ";
    echo "<br><a href='$scriptname?action=$action&id=$id'>".t("Go here")."</a></body></html>"; 
    exit;
}

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $inv_id = $id;

    // 接收所有 POST
    foreach($_POST as $k => $v) {
        if (!is_array($v)) ${$k} = trim($v);
    }

    // 必填校验
    if (empty($vendorid) || empty($buyerid) || !strlen($number) || !strlen($date)) {
        echo "<br><b>".t("Some <span class='mandatory'> mandatory</span> fields are missing").".</b><br>
        <a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
        exit;
    }

    $d = ymd2sec($date);

    // 关联数据
    $itlnk    = isset($_POST['itlnk'])    ? $_POST['itlnk']    : array();
    $softlnk  = isset($_POST['softlnk'])  ? $_POST['softlnk']  : array();
    $contrlnk = isset($_POST['contrlnk']) ? $_POST['contrlnk'] : array();

    // ====================== 新增发票 ======================
    if ($_POST['id'] == "new") {
        $sql = "INSERT into invoices (vendorid,buyerid,number,description,date)
                VALUES ('$vendorid','$buyerid','$number','$description','$d')";
        db_exec($dbh,$sql);
        $lastid = $dbh->lastInsertId();
        $id = $lastid;

        // 组装日志：基础信息 + 关联关系合并（同资产）
        $new_inv_info = array();
        foreach ($inv_formvars as $k) {
            $new_inv_info[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : '';
        }
        $new_inv_info['date'] = $d;

        $new_relation = array(
            'itlnk'    => $itlnk,
            'softlnk'  => $softlnk,
            'contrlnk' => $contrlnk
        );

        $new_log_data = array(
            'inv_info'  => $new_inv_info,
            'relation'  => $new_relation
        );

        // 新增日志
        addOperateLog(
            'invoice',
            'add',
            'Created new invoice ID %s',
            array($lastid),
            'invoice',
            $lastid,
            null,
            $new_log_data,
            1,
            ''
        );

        // 写入 actions
        $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
        $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                       VALUES ($lastid, ".time().", 'Added invoice by $user', '', 1, ".time().")";
        db_exec($dbh, $sql_action);
    }
    // ====================== 修改发票 ======================
    else {
        // 旧数据
        $sth_old = $dbh->query("SELECT * FROM invoices WHERE id=$id");
        $old_data_raw = $sth_old->fetch(PDO::FETCH_ASSOC);
        $old_inv_info = array();
        foreach ($inv_formvars as $k) {
            $old_inv_info[$k] = isset($old_data_raw[$k]) ? $old_data_raw[$k] : '';
        }

        // 旧关联
        $old_itlnk = array();
        $s = $dbh->query("SELECT itemid FROM item2inv WHERE invid=$id");
        if ($s) $old_itlnk = $s->fetchAll(PDO::FETCH_COLUMN, 0);

        $old_softlnk = array();
        $s = $dbh->query("SELECT softid FROM soft2inv WHERE invid=$id");
        if ($s) $old_softlnk = $s->fetchAll(PDO::FETCH_COLUMN, 0);

        $old_contrlnk = array();
        $s = $dbh->query("SELECT contractid FROM contract2inv WHERE invid=$id");
        if ($s) $old_contrlnk = $s->fetchAll(PDO::FETCH_COLUMN, 0);

        $old_relation = array(
            'itlnk'    => $old_itlnk,
            'softlnk'  => $old_softlnk,
            'contrlnk' => $old_contrlnk
        );

        // 执行更新
        $sql = "UPDATE invoices SET 
                vendorid='$vendorid', 
                buyerid='$buyerid', 
                number='$number', 
                description='$description', 
                date='$d' 
                WHERE id=$id";
        db_exec($dbh,$sql);

        // 新数据
        $sth_new = $dbh->query("SELECT * FROM invoices WHERE id=$id");
        $new_data_raw = $sth_new->fetch(PDO::FETCH_ASSOC);
        $new_inv_info = array();
        foreach ($inv_formvars as $k) {
            $new_inv_info[$k] = isset($new_data_raw[$k]) ? $new_data_raw[$k] : '';
        }

        $new_relation = array(
            'itlnk'    => $itlnk,
            'softlnk'  => $softlnk,
            'contrlnk' => $contrlnk
        );

        // 对比基础字段差异
        $diff_old_inv = array();
        $diff_new_inv = array();
        foreach ($old_inv_info as $key => $old_val) {
            $new_val = isset($new_inv_info[$key]) ? $new_inv_info[$key] : '';
            if ((string)$old_val !== (string)$new_val) {
                $diff_old_inv[$key] = $old_val;
                $diff_new_inv[$key] = $new_val;
            }
        }

        // 对比关联差异
        $relation_keys = array('itlnk','softlnk','contrlnk');
        $diff_old_rel = array();
        $diff_new_rel = array();
        foreach ($relation_keys as $k) {
            $old = isset($old_relation[$k]) ? $old_relation[$k] : array();
            $new = isset($new_relation[$k]) ? $new_relation[$k] : array();
            if (json_encode($old) != json_encode($new)) {
                $diff_old_rel[$k] = $old;
                $diff_new_rel[$k] = $new;
            }
        }
        
		// 对比基础字段差异 + 时间戳统一转int
		$diff_old_inv = array();
		$diff_new_inv = array();
		$time_fields = ['date'];
		
		foreach ($old_inv_info as $key => $old_val) {
		    $new_val = isset($new_inv_info[$key]) ? $new_inv_info[$key] : '';
		
		    // 时间字段强制转整数，统一类型
		    if (in_array($key, $time_fields)) {
		        $old_val = (int)$old_val;
		        $new_val = (int)$new_val;
		    }
		
		    if ((string)$old_val !== (string)$new_val) {
		        $diff_old_inv[$key] = $old_val;
		        $diff_new_inv[$key] = $new_val;
		    }
		}
		
		// 对比关联差异
		$relation_keys = array('itlnk','softlnk','contrlnk');
		$diff_old_rel = array();
		$diff_new_rel = array();
		foreach ($relation_keys as $k) {
		    $old = isset($old_relation[$k]) ? $old_relation[$k] : array();
		    $new = isset($new_relation[$k]) ? $new_relation[$k] : array();
		    if (json_encode($old) != json_encode($new)) {
		        $diff_old_rel[$k] = $old;
		        $diff_new_rel[$k] = $new;
		    }
		}
		
		// 不生成空节点：无修改不显示，和item/contract/software完全一致
		$old_diff = array();
		$new_diff = array();
		
		if (!empty($diff_old_inv)) {
		    $old_diff['inv_info'] = $diff_old_inv;
		    $new_diff['inv_info'] = $diff_new_inv;
		}
		if (!empty($diff_old_rel)) {
		    $old_diff['relation'] = $diff_old_rel;
		    $new_diff['relation'] = $diff_new_rel;
		}
		
		// 修改日志
		addOperateLog(
		    'invoice',
		    'update',
		    'Updated invoice ID %s',
		    array($id),
		    'invoice',
		    $id,
		    $old_diff,
		    $new_diff,
		    1,
		    ''
		);

        // 写入 actions
        $user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'system';
        $sql_action = "INSERT INTO actions (itemid, actiondate, description, invoiceinfo, isauto, entrydate)
                       VALUES ($id, ".time().", 'Updated invoice by $user', '', 1, ".time().")";
        db_exec($dbh, $sql_action);
    }

    // ====================== 更新关联（不记独立日志） ======================
    // item 关联
    $sql = "delete from item2inv where invid=$id";
    db_exec($dbh,$sql);
    foreach ($itlnk as $iid) {
        $iid = intval($iid);
        db_exec($dbh, "INSERT into item2inv (invid,itemid) values ($id,$iid)");
    }

    // software 关联
    $sql = "delete from soft2inv where invid=$id";
    db_exec($dbh,$sql);
    foreach ($softlnk as $sid) {
        $sid = intval($sid);
        db_exec($dbh, "INSERT into soft2inv (invid,softid) values ($id,$sid)");
    }

    // contract 关联
    $sql = "delete from contract2inv where invid=$id";
    db_exec($dbh,$sql);
    foreach ($contrlnk as $cid) {
        $cid = intval($cid);
        db_exec($dbh, "INSERT into contract2inv (invid,contractid) values ($id,$cid)");
    }

    if ($_POST['id'] == "new") {
        echo "<script>window.location='$scriptname?action=$action&id=$id'</script> ";
    }
}

/////////////////////////////
//// display data now
$sql="SELECT id,title,type FROM agents order by title";
$sth=db_execute($dbh,$sql);
while ($r=$sth->fetch(PDO::FETCH_ASSOC)) $agents[$r['id']]=$r;

if (!isset($_REQUEST['id'])) {echo t("ERROR:ID not defined");exit;}
$id=$_REQUEST['id'];

$sql="SELECT * FROM invoices WHERE id='$id'";
$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);

if (($id !="new") && (count($r)<5)) {echo t("ERROR: non-existent ID");exit;}

$number=$r['number'];
$date=$r['date'];
$vendorid=$r['vendorid'];
$buyerid=$r['buyerid'];
$description=$r['description'];

echo "\n<form id='mainform' method=post  action='$scriptname?action=$action&amp;id=$id' enctype='multipart/form-data'  name='addfrm'>\n";

if ($id=="new")
  echo "\n<h1>".t("Add Invoice")."</h1>\n";
else
  echo "\n<h1>".t("Edit Invoice")."</h1>\n";
?>

<!-- error errcontainer -->
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px;display:none;'>
    <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>
    <h4><?php te("There are <strong>error</strong>s in your form submission, please see below for details.");?>.</h4>
    <ol>
        <li><label for="vendorid" class="error"><?php te("Vendor is missing");?></label></li>
        <li><label for="buyerid" class="error"><?php te("Buyer is missing");?></label></li>
        <li><label for="number" class="error"><?php te("Order Num is missing");?></label></li>
        <li><label for="date" class="error"><?php te("Date is missing");?></label></li>
    </ol>
</div>

<div id="tabs">
    <ul>
        <li><a href="#tab1"><?php te("Invoice Data");?></a></li>
        <li><a href="#tab2"><?php te("Item Associations");?></a></li>
        <li><a href="#tab3"><?php te("Software Associations");?></a></li>
        <li><a href="#tab4"><?php te("Contract Associations");?></a></li>
        <li><a href="#tab5"><?php te("Upload Files");?></a></li>
    </ul>

    <div id="tab1" class="tab_content">
        <table class=tbl1 border=0>
        <?php 
        $d=strlen($date)?date($dateparam,$date):"";

        $f=invid2files($id,$dbh);
        $flnk = '';
        for ($c=0;$c<count($f);$c++) {
            $fname=$f[$c]['fname'];
            $ftitle=$f[$c]['title'];
            $fid=$f[$c]['id'];
            $ftype=$f[$c]['type'];
            $ftypestr=ftype2str($ftype,$dbh);
            $fdate=empty($f[$c]['date'])?"":date($dateparam,$f[$c]['date']);
            $t = strlen($ftitle) ? "<br>".t("Title").":$ftitle" : "";

            $flnk .= "<div class='fileslist'>
                <a href='javascript:delconfirm2(\"[$fid] $fname\",\"$scriptname?action=$action&amp;id=$id&amp;delfid=$fid\");'>
                <img src='images/delete.png'></a>
                <a target='_blank' href='$scriptname?action=editfile&amp;id=$fid'><img src='images/edit.png'></a>
                <a target='_blank' href='".$uploaddirwww.$fname."'><img src='images/down.png'></a>
                <br>Type: <b>$ftypestr</b>
                <br>Date: <b>$fdate</b>
                <br>Title: $ftitle
                </div>";
        }
        ?>
        <tr>
            <td class="tdtop">
                <table class="tbl2" width='100%'>
                    <tr><td colspan=2><h3><?php te("Invoice Properties");?></h3></td></tr>
                    <tr>
                        <td class="tdt"><?php te("ID");?>:</td>
                        <td><input class='input2' type=text name='id' value='<?php echo $id?>' readonly size=3></td>
                    </tr>
                    <tr>
                        <td class="tdt">
                        <?php if (is_numeric($vendorid)) {
                            echo "<a href='$scriptname?action=editagent&amp;id=$vendorid'><img src='images/edit.png'></a> ";
                        } ?>
                        <?php te("Vendor");?><sup class='red'>*</sup>:</td>
                        <td>
                            <select class='mandatory' validate='required:true' name='vendorid'>
                                <option value=''><?php te("Select");?></option>
                                <?php 
                                foreach ($agents as $a) {
                                    if (!($a['type']&4)) continue;
                                    $s = ($vendorid == $a['id']) ? 'SELECTED' : '';
                                    echo "<option $s value='{$a['id']}'>{$a['title']}</option>";
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdt">
                        <?php if (is_numeric($buyerid)) {
                            echo "<a href='$scriptname?action=editagent&amp;id=$buyerid'><img src='images/edit.png'></a> ";
                        } ?>
                        <?php te("Buyer");?><sup class='red'>*</sup>:</td>
                        <td>
                            <select class='mandatory' validate='required:true' name='buyerid'>
                                <option value=''><?php te("Select");?></option>
                                <?php 
                                foreach ($agents as $a) {
                                    if (!($a['type']&1)) continue;
                                    $s = ($buyerid == $a['id']) ? 'SELECTED' : '';
                                    echo "<option $s value='{$a['id']}'>{$a['title']}</option>";
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdt"><?php te("Order Num");?><sup class='red'>*</sup>:</td>
                        <td><input class='input2 mandatory' validate='required:true' size=20 type=text name='number' value="<?php echo $number?>"></td>
                    </tr>
                    <tr>
                        <td class="tdt"><?php te("Date");?><sup class='red'>*</sup>:</td>
                        <td><input class='dateinp mandatory' validate='required:true' size=10 id='date' type=text name='date' value='<?php echo $d?>'></td>
                    </tr>
                    <tr>
                        <td class="tdt"><?php te("Description");?>:</td>
                        <td colspan=2><textarea name='description' class='tarea2' wrap='soft'><?php echo $description?></textarea></td>
                    </tr>
                </table>
            </td>
            <td style='vertical-align:top;'>
                <h3><?php te("Associations Overview");?></h3>
                <div style='text-align:center;'>
                    <span class="tita" onclick='showid("items");'><?php te("Items");?></span> |
                    <span class="tita" onclick='showid("software");'><?php te("Software");?></span> |
                    <span class="tita" onclick='showid("contracts");'><?php te("Contracts");?></span>
                </div>
                <div class="scrltblcontainer4">
                    <div id='items' class='relatedlist'><?php te("ITEMS");?></div>
                    <?php 
                    if (is_numeric($id)) {
                        $sql="SELECT items.id, agents.title || ' ' || items.model || ' [' || itemtypes.typedesc || ', ID:' || items.id || ']' as txt 
                             FROM agents,items,itemtypes, item2inv 
                             WHERE agents.id=items.manufacturerid 
                             AND items.itemtypeid=itemtypes.id 
                             AND item2inv.itemid=items.id 
                             AND item2inv.invid='$id'";
                        $sthi=db_execute($dbh,$sql);
                        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
                        for ($i=0;$i<count($ri);$i++) {
                            $bcolor = $i%2 ? "#D9E3F6" : "#fff";
                            echo "<div style='background:$bcolor'><a href='$scriptname?action=edititem&id={$ri[$i]['id']}'>".($i+1).": {$ri[$i]['txt']}</a></div>";
                        }
                    }
                    ?>
                    <div id='software' class='relatedlist'><?php te("SOFTWARE");?></div>
                    <?php 
                    if (is_numeric($id)) {
                        $sql="SELECT software.id, agents.title || ' ' || software.stitle ||' '|| software.sversion || ' [ID:' || software.id || ']' as txt 
                             FROM agents,software,soft2inv 
                             WHERE agents.id=software.manufacturerid 
                             AND soft2inv.softid=software.id 
                             AND soft2inv.invid='$id'";
                        $sthi=db_execute($dbh,$sql);
                        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
                        for ($i=0;$i<count($ri);$i++) {
                            $bcolor = $i%2 ? "#D9E3F6" : "#fff";
                            echo "<div style='background:$bcolor'><a href='$scriptname?action=editsoftware&id={$ri[$i]['id']}'>".($i+1).": {$ri[$i]['txt']}</a></div>";
                        }
                    }
                    ?>
                    <div id='contracts' class='relatedlist'><?php te("CONTRACTS");?></div>
                    <?php 
                    if (is_numeric($id)) {
                        $sql="SELECT contracts.id, type,title,number,startdate,currentenddate 
                             FROM contracts,contract2inv 
                             WHERE contract2inv.contractid=contracts.id 
                             AND contract2inv.invid=$id";
                        $sthi=db_execute($dbh,$sql);
                        $ri=$sthi->fetchAll(PDO::FETCH_ASSOC);
                        for ($i=0;$i<count($ri);$i++) {
                            $d=date($dateparam,$ri[$i]['startdate'])." - ".date($dateparam,$ri[$i]['currentenddate']);
                            $bcolor = $i%2 ? "#D9E3F6" : "#fff";
                            echo "<div style='background:$bcolor'><a href='$scriptname?action=editcontract&id={$ri[$i]['id']}'>".($i+1).": {$ri[$i]['title']} {$ri[$i]['number']} ($d) [ID:{$ri[$i]['id']}]</a></div>";
                        }
                    }
                    ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan=2 class="tdtop">
                <table class="tbl2" width='100%'>
                    <tr><td><h3><?php te("Associated Files");?><img onclick='window.location.href=window.location.href;' src='images/refresh.png'></h3></td></tr>
                    <tr><td><?php echo $flnk?></td></tr>
                </table>
            </td>
        </tr>
        </table>
    </div>

    <div id="tab2" class="tab_content">
        <h2>
            <input id="itemsfilter" name="itemsfilter" class='filter' value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20">
        </h2>
        <?php 
        $sql=" SELECT COALESCE((SELECT itemid FROM item2inv WHERE invid='$id' AND itemid=items.id ),0) islinked , 
             items.id,status,manufacturerid,model,itemtypeid,typedesc,sn || ' '||sn2 ||' ' || sn3 as sn,dnsname,users.username ,label 
             FROM items,itemtypes,users  
             WHERE items.itemtypeid=itemtypes.id 
             AND users.id=userid 
             order by islinked desc,itemtypeid,items.id desc, manufacturerid,model, dnsname ";
        $sth=db_execute($dbh,$sql);
        ?>
        <div class='scrltblcontainer2'>
            <table width='100%' class='brdr sortable' id='itemslisttbl'>
                <thead>
                    <tr>
                        <th width='5%'><?php te("Associate");?></th>
                        <th style="width:65px"><?php te("ID");?></th>
                        <th><?php te("Type");?></th>
                        <th><?php te("Manufacturer");?></th>
                        <th><?php te("Model");?></th>
                        <th><?php te("Label");?></th>
                        <th><?php te("DNS");?></th>
                        <th><?php te("User");?></th>
                        <th><?php te("S/N");?></th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
                    $cls = $ir['islinked'] ? "class='bld'" : "";
                    $x = attrofstatus((int)$ir['status'],$dbh);
                    $attr = $x[0];

                    echo "<tr>
                    <td><input name='itlnk[]' value='{$ir['id']}' ".($ir['islinked'] ? 'checked' : '')." type='checkbox'></td>
                    <td nowrap $cls><span $attr>&nbsp;</span><a target='_blank' href='$scriptname?action=edititem&id={$ir['id']}'><div class='editid'>{$ir['id']}</div></a></td>
                    <td $cls>{$ir['typedesc']}</td>
                    <td $cls>{$agents[$ir['manufacturerid']]['title']}</td>
                    <td $cls>{$ir['model']}</td>
                    <td $cls>{$ir['label']}</td>
                    <td $cls>{$ir['dnsname']}</td>
                    <td $cls>{$ir['username']}</td>
                    <td $cls>{$ir['sn']}</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="tab3" class="tab_content">
        <h2>
            <input id="softfilter" name="softfilter" class='filter' value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20">
        </h2>
        <?php 
        $sql=" SELECT COALESCE((SELECT softid from soft2inv WHERE invid='$id' AND softid=software.id ),0) islinked , 
             software.id, stitle || ' ' || sversion as titver, agents.title AS agtitle  
             FROM software,agents 
             WHERE agents.id=software.manufacturerid  
             ORDER BY islinked desc,manufacturerid,stitle,sversion ";
        $sth=db_execute($dbh,$sql);
        ?>
        <div class='scrltblcontainer2'>
            <table width='100%' class='tbl2 brdr sortable' id='softlisttbl'>
                <thead>
                    <tr>
                        <th width='5%'><?php te("Associated");?></th>
                        <th><?php te("ID");?></th>
                        <th><?php te("Manufacturer");?></th>
                        <th><?php te("Title/Ver.");?></th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
                    $cls = $ir['islinked'] ? "class='bld'" : "";
                    echo "<tr>
                    <td><input name='softlnk[]' value='{$ir['id']}' ".($ir['islinked'] ? 'checked' : '')." type='checkbox'></td>
                    <td $cls><a target='_blank' href='$scriptname?action=editsoftware&id={$ir['id']}'><div class='editid'>{$ir['id']}</div></a></td>
                    <td $cls>{$ir['agtitle']}</td>
                    <td $cls>{$ir['titver']}</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="tab4" class="tab_content">
        <h2>
            <input id="contrfilter" name="contrfilter" class='filter' value='<?php te("Filter");?>' onclick='this.style.color="#000"; this.value=""' size="20">
        </h2>
        <?php 
        $sql=" SELECT COALESCE((SELECT contractid FROM contract2inv WHERE invid='$id' AND contractid=contracts.id ),0) islinked , 
             contracts.id, contracts.title AS ctitle, agents.title AS agtitle  
             FROM contracts,agents 
             WHERE agents.id=contracts.contractorid  
             ORDER BY islinked desc,contractorid,ctitle";
        $sth=db_execute($dbh,$sql);
        ?>
        <div class='scrltblcontainer2'>
            <table width='100%' class='tbl2 brdr sortable' id='contrlisttbl'>
                <thead>
                    <tr>
                        <th width='5%'><?php te("Associated");?></th>
                        <th><?php te("ID");?></th>
                        <th><?php te("Contractor");?></th>
                        <th><?php te("Title");?></th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                while ($ir=$sth->fetch(PDO::FETCH_ASSOC)) {
                    $cls = $ir['islinked'] ? "class='bld'" : "";
                    echo "<tr>
                    <td><input name='contrlnk[]' value='{$ir['id']}' ".($ir['islinked'] ? 'checked' : '')." type='checkbox'></td>
                    <td $cls><a target='_blank' href='$scriptname?action=editcontract&id={$ir['id']}'><div class='editid'>{$ir['id']}</div></a></td>
                    <td $cls>{$ir['agtitle']}</td>
                    <td $cls>{$ir['ctitle']}</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="tab5" class="tab_content">
        <table class="tbl2" width='100%'>
            <tr><td colspan=2><h2><?php te("Upload a File");?></h2></td></tr>
            <tr><td class="tdc">
                <iframe class="upload_frame" name="upload_frame" 
                src="php/uploadframe.php?id=<?php echo $id?>&amp;type=invoice&amp;assoctable=invoice2file&amp;colname=invoiceid&amp;defdate=<?php echo urlencode($d)?>"  
                frameborder="0" allowtransparency="true"></iframe>
            </td></tr>
        </table>
    </div>
</div>

<table>
    <tr>
        <td><button type="submit"><img src="images/save.png"> <?php te("Save");?></button></td>
        <?php if ($id != "new") { ?>
        <td>
            <button type='button' onclick='javascript:delconfirm2("<?php echo $r['id']?>","$scriptname?action=$action&amp;delid=<?php echo $r['id']?>");'>
                <img src='images/delete.png'> <?php te("Delete");?>
            </button>
        </td>
        <?php } ?>
    </tr>
</table>

<input type=hidden name='action' value='<?php echo $action?>'>
<input type=hidden name='id' value='<?php echo $id?>'>
</form>
</body>
</html>
