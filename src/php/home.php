<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

// 从卡片商城读取用户可用卡片
$my_cards = array();
$selected_ids = array();
if(isset($r['id'])){
    $selected_ids = getUserSelectedCardIds($dbh, $r['id']);
}
$all_cards = getDashboardCards($dbh);
// 加载货币符号
$sth_curr = $dbh->query("SELECT currency FROM settings LIMIT 1");
$setting_curr = $sth_curr->fetch(PDO::FETCH_ASSOC);
$currency = isset($setting_curr['currency']) ? $setting_curr['currency'] : '';
// 执行统计SQL

$counts = array();
foreach($all_cards as $c){
    $key = $c['key_name'];
    $sql = trim($c['count_sql']);
    $counts[$key] = '';
    if($sql){
        try{
            $sth = $dbh->query($sql);
            if($sth){
                $row = $sth->fetch(PDO::FETCH_NUM);
                if (isset($row[0])) {
                    // ↓↓↓ 只保留原始结果，不做任何自动格式化
                    $counts[$key] = $row[0];
                    // ↑↑↑ 就这一句，完完全全和编辑预览一致
                } else {
                    $counts[$key] = 0;
                }
            }
        }catch(Exception $e){}
    }
}

?>

<style>
.stats-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    padding: 10px 0 20px 0;
    justify-content: flex-start;
    margin-bottom: 20px;
    max-width: 940px;
    margin-left: 20px;
}
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    min-width: 130px;
    text-align: center;
    text-decoration: none;
    color: #333;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: all 0.3s;
    border-top: 4px solid;
    display: block;
}
.stat-card:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.stat-icon {
    font-size: 28px;
    margin-bottom: 10px;
    display: block;
}
.stat-number {
    font-size: 22px; /* 统一缩小一点，两行也不会撑大卡片 */
    font-weight: bold;
    margin: 5px 0;
    display: block;
    line-height: 1.2;
    min-height: 64px; /* 所有卡片高度统一 */
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.stat-label {
    font-size: 12px;
    color: #777;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.divider-title {
    font-size: 12px !important;
    font-weight: 500 !important;
    color: #999 !important;
    display: flex !important;
    align-items: center !important;
    max-width: 940px !important;
    margin: 20px 0 10px 20px !important;
    background: none !important;
    padding: 0 !important;
    border: none !important;
    text-decoration: none !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.divider-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background-color: rgba(0, 0, 0, 0.06) !important;
    margin-left: 10px;
    display: block;
}
</style>

<?php
global $authstatus, $dbh;
$selected_ids = array();
$show_cards = array();

if ($authstatus) {
    $username = isset($_COOKIE['itdbuser']) ? trim($_COOKIE['itdbuser']) : '';
    if ($username) {
        $safe_user = addslashes($username);
        $sql = "SELECT dashboard_cards FROM users WHERE username = '$safe_user' LIMIT 1";
        $sth = $dbh->query($sql);
        if ($sth) {
            $u = $sth->fetch(PDO::FETCH_ASSOC);
            $str = is_array($u) && isset($u['dashboard_cards']) ? trim($u['dashboard_cards']) : '';
            if (!empty($str)) {
                $selected_ids = array_map('intval', explode(',', $str));
            }
        }
    }
}

$all_cards = getDashboardCards($dbh);
foreach ($all_cards as $c) {
    if (in_array((int)$c['id'], $selected_ids)) {
        $show_cards[] = $c;
    }
}

if (!empty($show_cards)): ?>
<span class="divider-title"><b>📊 <?php te("Statistics Overview"); ?></b></span>
<div class="stats-grid">
<?php
foreach ($show_cards as $c):
    $num   = isset($counts[$c['key_name']]) ? $counts[$c['key_name']] : 0;
    $link  = trim($c['link_url']);
    $color = htmlspecialchars($c['color']);
    $icon  = htmlspecialchars($c['icon']);
    $title = htmlspecialchars($c['title']);
?>
    <?php if (!empty($link)): ?>
    <a href="<?php echo htmlspecialchars($link); ?>" class="stat-card" style="border-top-color:<?php echo $color; ?>;">
    <?php else: ?>
    <div class="stat-card" style="border-top-color:<?php echo $color; ?>;cursor:default;">
    <?php endif; ?>
        <span class="stat-icon"><?php echo $icon; ?></span>
        <span class="stat-number">
        <?php
        $key = $c['key_name'];
        if (is_numeric($num)) {
            $decimal = strpos($num, '.') !== false ? strlen(substr(strrchr($num, '.'), 1)) : 0;
            $formatted = number_format($num, $decimal);
            echo substr($key, 0, 7) === 'amount_' ? $currency.$formatted : $formatted;
        } else {
            echo $num;
        }
        ?>
        </span>
        <span class="stat-label"><?php echo $title; ?></span>
    <?php if (!empty($link)): ?>
    </a>
    <?php else: ?>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
</div>
<?php endif; ?>


<span class="divider-title"><b>📦 <?php te("Asset Management"); ?></b></span>
<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listitems'>
        <img class='bigblock' src='images/big/hardware.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listitems'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=edititem&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class="lnktxt"><?php te("Add");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=edititypes'><img class='bigblocklnk' src='images/big/wheel24.png'> <span class='lnktxt'><?php te("Item Types");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editstatustypes'><img class='bigblocklnk' src='images/big/wheel24.png'> <span class='lnktxt'><?php te("Status Types");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Hardware");?></div>
      <?php te("Manage your H/W items <br> Servers, PCs, Switches, etc");?>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listsoftware'>
        <img class='bigblock' src='images/big/software.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listsoftware'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editsoftware&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class="lnktxt"><?php te("Add");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Software");?></div>
      <?php te("Manage your software");?><br>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listinvoices'>
        <img class='bigblock' src='images/big/document.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listinvoices'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editinvoice&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class='lnktxt'><?php te("Add");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Invoices");?></div>
      <?php te("Manage your Invoices");?>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listcontracts'>
        <img class='bigblock' src='images/big/contract.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listcontracts'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editcontract&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class="lnktxt"><?php te("Add");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editcontracttypes'><img class='bigblocklnk' src='images/big/wheel24.png'> <span class='lnktxt'><?php te("Contract Types");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Contracts");?></div>
      <?php te("Manage your contracts <br> Support, Licenses, Leases, etc");?>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listagents'>
        <img class='bigblock' src='images/big/company.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listagents'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editagent&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class="lnktxt"><?php te("Add");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Agents");?></div>
      <?php te("Manage Agents");?><br>
      <?php te("H/W & S/W Manufacturers & Vendors, Buyers, Contractors");?></div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listfiles'>
        <img class='bigblock' src='images/big/files128.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listfiles'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editfile&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class='lnktxt'><?php te("Add");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editfiletypes'><img class='bigblocklnk' src='images/big/wheel24.png'> <span class='lnktxt'><?php te("File Types");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Files");?></div>
      <?php te("File Maintenance");?>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listracks'>
        <img class='bigblock' src='images/big/rack1.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listracks'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editrack&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class='lnktxt'><?php te("Add");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Racks");?></div>
      <?php te("Add/View");?>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=listlocations'>
        <img class='bigblock' src='images/big/location.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=listlocations'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Find");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=editlocation&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <span class='lnktxt'><?php te("Add");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Locations");?></div>
      <?php te("Manage item locations");?>
    </div>
</div>
<div style='clear:both'></div>

<!-- ================================================== -->
<span class="divider-title"><b>🏢 <?php te("Organization & Users"); ?></b></span>
<div class='bigblock'>
  <div style='float:left'>
    <a href='<?php echo $scriptname?>?action=listdepartments'>
    <img class='bigblock' src='images/big/group.png'>
    </a>
  </div>
  <div class='bigblocklinks'>
    <div><a href='<?php echo $scriptname?>?action=listdepartments'><img class='bigblocklnk' src='images/big/search24.png'> <?php te("Find");?></a></div>
    <div><a href='<?php echo $scriptname?>?action=editdepartments&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <?php te("Add");?></a></div>
  </div>
  <div class='bigblockdesc'>
    <div class='bigblocktitle'><?php te("Departments");?></div>
    <?php te("Organizational Structure of Management Departments and Sub-departments");?>
  </div>
</div>

<div class='bigblock'>
  <div style='float:left'>
    <a href='<?php echo $scriptname?>?action=listemployees'>
    <img class='bigblock' src='images/big/users.png'>
    </a>
  </div>
  <div class='bigblocklinks'>
    <div><a href='<?php echo $scriptname?>?action=listemployees'><img class='bigblocklnk' src='images/big/search24.png'> <?php te("Find");?></a></div>
    <div><a href='<?php echo $scriptname?>?action=editemployees&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <?php te("Add");?></a></div>
  </div>
  <div class='bigblockdesc'>
    <div class='bigblocktitle'><?php te("Employees");?></div>
    <?php te("Manage employee information");?>
  </div>
</div>

<div class='bigblock'>
  <div style='float:left'>
    <a href='<?php echo $scriptname?>?action=listusers'>
    <img class='bigblock' src='images/big/user.png'>
    </a>
  </div>
  <div class='bigblocklinks'>
    <div><a href='<?php echo $scriptname?>?action=listusers'><img class='bigblocklnk' src='images/big/search24.png'> <?php te("Find");?></a></div>
    <div><a href='<?php echo $scriptname?>?action=edituser&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <?php te("Add");?></a></div>
  </div>
  <div class='bigblockdesc'>
    <div class='bigblocktitle'><?php te("Users");?></div>
    <?php te("Management system users, database management password, homepage card display, etc.");?>
  </div>
</div>

<div class='bigblock'>
  <div style='float:left'>
    <a href='<?php echo $scriptname?>?action=listdashboardcards'>
    <img class='bigblock' src='images/big/data.png'>
    </a>
  </div>
  <div class='bigblocklinks'>
    <div><a href='<?php echo $scriptname?>?action=listdashboardcards'><img class='bigblocklnk' src='images/big/search24.png'> <?php te("List");?></a></div>
    <div><a href='<?php echo $scriptname?>?action=editdashboardcard&amp;id=new'><img class='bigblocklnk' src='images/big/plus.png'> <?php te("Add");?></a></div>
  </div>
  <div class='bigblockdesc'>
    <div class='bigblocktitle'><?php te("Dashboard Cards");?></div>
    <?php te("Manage statistics cards");?>
  </div>
</div>
<div style='clear:both'></div>

<!-- ================================================== -->
<span class="divider-title"><b>📋 <?php te("Tools & Reports"); ?></b></span>
<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=reports'>
        <img class='bigblock' src='images/big/pie.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=reports'><img class='bigblocklnk' src='images/big/spreadsheet24.png'> <span class='lnktxt'><?php te("Reports");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Reports");?></div>
      <?php te("View Reports");?>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=browse'>
        <img class='bigblock' src='images/big/view_tree.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=browse'><img class='bigblocklnk' src='images/big/search24.png'> <span class='lnktxt'><?php te("Browse");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Tree Explorer");?></div>
      <?php te("Tree Explorer");?><br>
      <?php te("View items by type, by user, by agent");?>
    </div>
</div>

<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=printlabels'>
        <img class='bigblock' src='images/big/labels.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=printlabels'><span class='linktxt'><?php te("Labels");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Print Labels");?></div>
      <?php te("select and print labels for your items");?>
    </div>
</div>
<div style='clear:both'></div>

<!-- ================================================== -->
<span class="divider-title"><b>⚙️ <?php te("System Settings"); ?></b></span>
<div class='bigblock'>
    <div style='float:left'>
        <a href='<?php echo $scriptname?>?action=settings'>
        <img class='bigblock' src='images/big/settings.png'>
        </a>
    </div>
    <div class='bigblocklinks'>
	    <div><a href='<?php echo $scriptname?>?action=settings'><img class='bigblocklnk' src='images/big/wheel24.png'> <span class='lnktxt'><?php te("ITDB Settings");?></span></a></div>
	    <div><a href='<?php echo $scriptname?>?action=edittags'><img class='bigblocklnk' src='images/big/tag.png'> <span class='lnktxt'><?php te("Tags");?></span></a></div>
    </div>
    <div class='bigblockdesc'>
      <div class='bigblocktitle'><?php te("Settings");?></div>
      <?php te("Manage various parameters");?><br>
      <?php te("Tags, Dates, Currency,...");?>
    </div>
</div>

<div style='clear:both'></div>
<script>
// 首页时间卡片 实时秒刷（完美区分：日期 / 时间 / 日期时间）
document.addEventListener('DOMContentLoaded', function() {
    function updateClock() {
        let now = new Date();
        let y = now.getFullYear();
        let m = String(now.getMonth() + 1).padStart(2, '0');
        let d = String(now.getDate()).padStart(2, '0');
        let hh = String(now.getHours()).padStart(2, '0');
        let mm = String(now.getMinutes()).padStart(2, '0');
        let ss = String(now.getSeconds()).padStart(2, '0');
        
        let date_str = y + '-' + m + '-' + d;
        let time_str = hh + ':' + mm + ':' + ss;
        let datetime_str = date_str + '<br>' + time_str;

        let numbers = document.querySelectorAll('.stat-number');
        numbers.forEach(el => {
            let text = el.innerText.trim();

            // 1. 纯时间卡片（只含 : 不含 -）→ 只刷新时间
            if (text.includes(':') && !text.includes('-')) {
                el.innerText = time_str;
            }
            // 2. 纯日期卡片（只含 - 不含 :）→ 不动，保持日期
            else if (text.includes('-') && !text.includes(':')) {
                // 不修改，避免覆盖
            }
            // 3. 日期+时间 → 正常刷新
            else if (text.includes('-') && text.includes(':')) {
                el.innerHTML = datetime_str;
            }
        });
    }
    updateClock();
    setInterval(updateClock, 1000);
});

</script>
