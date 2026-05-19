<?php 
if (!isset($initok)) {echo t("do not run this script directly");exit;}
/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */

// 用户字段（用于对比）
$user_formvars = array('username', 'userdesc', 'pass', 'usertype', 'dbpass', 'dbpasstime');

//delete user
if (isset($_GET['delid'])) {
  $delid=$_GET['delid'];
  if (!is_numeric($delid)) {
    echo t("Non numeric id")." delid=($delid)";
    exit;
  }

  deluser($delid,$dbh);

  // 日志同item格式
  addOperateLog(
    'user',
    'delete',
    'Deleted user ID %s',
    array($delid),
    'user',
    $delid,
    null,
    null,
    1,
    ''
  );

  echo "<script>document.location='$scriptname?action=listusers'</script>\n";
  echo "<a href='$scriptname?action=listusers'>".t("Go here")."</a>\n</body></html>"; 
  exit;
}

if (isset($_POST['id'])) {
  $id = $_POST['id'];
  $username = $_POST['username'];
  $usertype = $_POST['usertype'];
  $userdesc = isset($_POST['userdesc']) ? $_POST['userdesc'] : '';
  $pass = $_POST['pass'];
  $dbpass_val = isset($_POST['dbpass']) ? $_POST['dbpass'] : '';
  $dbpasstime_val = isset($_POST['dbpasstime']) ? $_POST['dbpasstime'] : '';
  $regen_flag = false;

  // 重置数据库密码
  if (isset($_POST['regen_dbpass']) && $usertype == '0') {
    $new_pass = generateRandomDBPassword();
    $expire_time = date('Y-m-d') . ' 23:59:59';
    $dbpass_val = $new_pass;
    $dbpasstime_val = $expire_time;
    $regen_flag = true;
  }

  // 非空校验
  if (empty($_POST['username'])) {
    echo "<br><b><span class='mandatory'>".t("Username")."</span> ".t("cannot be empty.")."</b><br>".
         "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
    exit;
  }
  if (empty($_POST['pass'])) {
    echo "<br><b><span class='mandatory'>".t("Password")."</span> ".t("cannot be empty.")."</b><br>".
         "<a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
    exit;
  }

  // 新增用户
  if ($_POST['id']=="new") {
    $sql="SELECT count(id) AS count from users where username='{$_POST['username']}'";
    $sth1=db_execute($dbh,$sql);
    $r1=$sth1->fetch(PDO::FETCH_ASSOC);
    $sth1->closeCursor();
    $c=$r1['count'];

    if (!empty($dbpass_val)) {
      $dbpasstime_val = date('Y-m-d').' 23:59:59';
    } else {
      $dbpasstime_val = '';
    }

    if ($c > 0) {
      echo t("<b>Not saved -- Username already exists</b>");
      echo "<br><a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
      exit;
    }

    $sql="INSERT into users (username, userdesc, pass, usertype, dbpass, dbpasstime) ".
         "VALUES ('$username','$userdesc','$pass','$usertype','$dbpass_val','$dbpasstime_val')";
	db_exec($dbh,$sql);
	$lastid = $dbh->lastInsertId(); // 先获取ID
	
	// ========== 保存卡片商城选择 ==========
	$card_ids = isset($_POST['dashboard_card_ids']) ? $_POST['dashboard_card_ids'] : array();
	updateUserDashboardCards($dbh, $lastid, $card_ids);

    // 新增日志：同item结构
    $new_data = array(
      'user_info' => array(
        'username'   => $username,
        'userdesc'   => $userdesc,
        'pass'       => '******',
        'usertype'   => $usertype,
        'dbpass'     => '******',
        'dbpasstime' => $dbpasstime_val
      )
    );

    addOperateLog(
      'user',
      'add',
      'Created new user ID %s',
      array($lastid),
      'user',
      $lastid,
      null,
      $new_data,
      1,
      ''
    );

    echo "<script>window.location='$scriptname?action=$action&id=$lastid'</script>";
    echo "\n</body></html>";
    exit;
  }
  // 编辑用户
  else {
    $uid = intval($id);
    $sql="SELECT count(id) AS count from users where username='{$_POST['username']}' AND id<>$uid";
    $sth1=db_execute($dbh,$sql);
    $r1=$sth1->fetch(PDO::FETCH_ASSOC);
    $sth1->closeCursor();
    $c=$r1['count'];

    if ($c) {
      echo t("<b>Not saved -- Username already exists</b>");
      echo "<br><a href='javascript:history.go(-1);'>".t("Go back")."</a></body></html>";
      exit;
    }

    if ($username=='admin' && $usertype) {
      $usertype=0;
    }

    // ====================== 取旧数据 ======================
    $sql_old = "SELECT * FROM users WHERE id=$uid";
    $sth_old = db_execute($dbh, $sql_old);
    $old_data = $sth_old->fetch(PDO::FETCH_ASSOC);

    // ====================== 执行更新 ======================
    $sql="UPDATE users SET 
      username='$_POST[username]',
      userdesc='$_POST[userdesc]',
      pass='$_POST[pass]',
      usertype='$usertype',
      dbpass='$dbpass_val',
      dbpasstime='$dbpasstime_val'
      WHERE id=$uid";
    db_exec($dbh,$sql);
	// ========== 保存卡片商城选择 ==========
	$card_ids = isset($_POST['dashboard_card_ids']) ? $_POST['dashboard_card_ids'] : array();
	updateUserDashboardCards($dbh, $uid, $card_ids);

    // ====================== 取新数据 ======================
    $sql_new = "SELECT * FROM users WHERE id=$uid";
    $sth_new = db_execute($dbh, $sql_new);
    $new_data = $sth_new->fetch(PDO::FETCH_ASSOC);

    // ====================== 仅对比变动项（同edititem） ======================
    $diff_old = array();
    $diff_new = array();

    foreach ($user_formvars as $k) {
      $old_val = isset($old_data[$k]) ? (string)$old_data[$k] : '';
      $new_val = isset($new_data[$k]) ? (string)$new_data[$k] : '';

      // 登录密码：新旧都隐藏
      if ($k === 'pass') {
          $old_val = '******';
          $new_val = '******';
      }
      // 数据库密码：旧值显示真实值，新值隐藏
      elseif ($k === 'dbpass') {
          $new_val = '******';
      }

      if ($old_val !== $new_val) {
          $diff_old[$k] = $old_val;
          $diff_new[$k] = $new_val;
      }
    }

    // 只保留变动项，同item格式
    $old_log = array();
    $new_log = array();
    if (!empty($diff_old)) {
      $old_log['user_info'] = $diff_old;
      $new_log['user_info'] = $diff_new;
    }

    // ====================== 写入日志 ======================
    if ($regen_flag) {
      addOperateLog(
        'user',
        'reset_dbpass',
        'Reset database password for user ID %s',
        array($uid),
        'user',
        $uid,
        $old_log,
        $new_log,
        1,
        ''
      );
    } else {
      addOperateLog(
        'user',
        'update',
        'Updated user ID %s',
        array($uid),
        'user',
        $uid,
        $old_log,
        $new_log,
        1,
        ''
      );
    }
  }
}

/////////////////////////////
//// display data 
if (!isset($_REQUEST['id'])) {echo t("ERROR:ID not defined");exit;}
$id=$_REQUEST['id'];
$sql="SELECT * from users where users.id='$id'";
$sth=db_execute($dbh,$sql);
$r=$sth->fetch(PDO::FETCH_ASSOC);
if (($id !="new") && (count($r)<2)) {echo t("ERROR: non-existent ID")."<br>($sql)";exit;}
echo "\n<form id='mainform' method=post  action='$scriptname?action=$action&amp;id=$id' enctype='multipart/form-data'  name='addfrm'>\n";
if ($id=="new")
  echo "\n<h1>".t("Add User")."</h1>\n";
else
  echo "\n<h1>".t("Edit User")."  ($id)"."</h1>\n";
?>
<!-- error errcontainer -->
<div class='errcontainer ui-state-error ui-corner-all' style='padding: 0 .7em;width:700px;margin-bottom:3px;'>
        <p><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'></span>
        <h4><?php te("There are <strong>error</strong>s in your form submission, please see below for details.");?></h4>
        <ol>
                <li><label for="username" class="error"><?php te("Username is missing");?></label></li>
                <li><label for="pass" class="error"><?php te("Password is missing");?></label></li>
        </ol>
</div>
<table style='width:100%' border=0>
<tr>
<td class="tdtop" width=20%>
    <table class="tbl2" style='width:300px;'>
    <tr><td colspan=2><h3><?php te("User Properties")?></h3></td></tr>
    <tr><td class="tdt"><?php te("ID")?>:</td> 
        <td><input  style='display:none' type=text name='id' 
     value='<?php echo $id?>' readonly size=3><?php echo $id?></td></tr>
    <tr><td class="tdt"><?php te("Username");?><sup class='red'>*</sup>:</td> <td><input  class='input2 mandatory' validate='required:true' size=20 type=text name='username' value="<?php echo $r['username']?>"></td></tr>
    <tr><td class="tdt"><?php te("Type")?></td>
        <td>
    <select class='mandatory' validate='required:true' name='usertype'>
    <?php
    if ($r['usertype']==1 || empty($r['username'])) {$s1="selected"; $s0="";} else {$s0="selected"; $s1="";} 
    echo " <option value=1 $s1>".t("Read Only")."</option>\n".
         " <option value=0 $s0>".t("Full Access")."</option>\n".
         "</select></td>";
    ?>
    </select>
    </td></tr>
    <tr><td class="tdt"><?php te("User Description");?>:</td> 
        <td><input autocomplete="off" class='input2' size=20 
     type=text name='userdesc' value="<?php echo $r['userdesc']?>">
        </td></tr>
    <tr><td class="tdt"><?php te("Password");?><sup class='red'>*</sup>:</td> 
        <td><input autocomplete="off" class='input2 mandatory' validate='required:true' size=20 type="password"
     name='pass' value="<?php echo $r['pass']?>">
         </td></tr>
    <?php if (isset($r['usertype']) && $r['usertype'] == 0 && $r['id'] == 1 && $r['username'] == 'admin'): ?>
    <tr>
        <td class="tdt"><?php te("Database Manager Password");?>:</td> 
        <td>
			<input type="text" 
			       value="<?php echo htmlspecialchars(isset($r['dbpass']) ? $r['dbpass'] : t('Not Generated')); ?>" 
			       readonly size="30" 
			       style="background:#eee; font-family:monospace; margin-bottom: 5px;">
			<br>
			<input type="hidden" name="dbpass" value="<?php echo htmlspecialchars($r['dbpass']); ?>">
			<input type="hidden" name="dbpasstime" value="<?php echo htmlspecialchars($r['dbpasstime']); ?>">
			<small style="color:#666;">
			    <?php te("Valid until");?>: 
			    <b><?php echo htmlspecialchars(isset($r['dbpasstime']) ? $r['dbpasstime'] : t('N/A')); ?></b>
			</small>
        </td>
    </tr>
<tr>
    <td class="tdt"><?php te("Action");?>:</td>
    <td>
        <?php 
        $confirmText = t("Are you sure you want to regenerate the database password? This will expire the old password.");
        ?>
        <button type="submit" name="regen_dbpass" class="btn" 
                onclick="return confirm('<?php echo $confirmText; ?>')">
            <?php te("Regenerate Password");?> 
        </button>
    </td>
</tr>
    <?php endif; ?>
    </table>
    <ul>
      <li><b><?php te("Users are used for both web login and as item assignees");?></b></li>
      <li><?php te("Blank passwords prohibit login");?></li>
	    <?php if (isset($r['usertype']) && $r['usertype'] == 0 && $r['id'] == 1 && $r['username'] == 'admin'): ?>
	        <li>
            <?php te("Attention: The database management password is valid only for today (until 23:59:59) and will expire immediately thereafter.<br>This password grants direct access to the system database. <font color=red><b>Do not change it unless absolutely necessary</b></font>, as doing so may cause system failure!");?>
	        </li>
	    <?php endif; ?>
    </ul>
</td>
		<td class="tdtop" style="padding-left:10px; border-left:1px dashed #aaa; vertical-align:top;">
	<style>
	/* 多列平铺，填满右侧空白 */
	.dashboard-card-selector {
	  display: flex;
	  flex-wrap: wrap;
	  gap: 12px;
	  padding: 12px 0;
	  width: 100%;
	  box-sizing: border-box;
	}
	.card-select-item {
	  position: relative;
	  background: #fff;
	  border-radius: 8px;
	  padding: 16px 12px;
	  width: 130px;
	  text-align: center;
	  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
	  border-top: 4px solid #ccc;
	  cursor: pointer;
	  transition: all 0.25s ease;
	  box-sizing: border-box;
	}
	.card-select-item.checked {
	  background: #e3f2ff;
	  border-color: #409eff;
	  box-shadow: 0 3px 10px rgba(64, 158, 255, 0.15);
	}
	.card-select-item:hover {
	  transform: translateY(-2px);
	  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
	}
	.card-select-checkbox {
	  position: absolute;
	  top: 8px;
	  right: 8px;
	  width: 16px;
	  height: 16px;
	  cursor: pointer;
	  margin: 0;
	}
	.card-select-icon {
	  font-size: 26px;
	  margin-bottom: 8px;
	  display: block;
	}
	.card-select-title {
	  font-size: 12px;
	  color: #333;
	  font-weight: 500;
	  text-transform: uppercase;
	  letter-spacing: 0.4px;
	}
	</style>
	
	<div style="width: 100%;">
	  <h3 style="margin:0 0 10px 0; font-size:16px;"><?php te("Dashboard Card Selection");?></h3>
	  <div class="dashboard-card-selector">
	    <?php
	    $card_list = getDashboardCards($dbh);
	    $selected_ids = [];
	    if (!empty($r['dashboard_cards'])) {
	        $selected_ids = explode(',', $r['dashboard_cards']);
	    }
	    foreach($card_list as $card):
	        $c_id    = $card['id'];
	        $checked = in_array($c_id, $selected_ids);
	        $color   = htmlspecialchars($card['color']);
	        $icon    = htmlspecialchars($card['icon']);
	        $title   = htmlspecialchars($card['title']);
	    ?>
	    <label class="card-select-item <?php echo $checked ? 'checked' : ''; ?>" style="border-top-color:<?php echo $color; ?>;">
	        <input type="checkbox" class="card-select-checkbox" formnovalidate name="dashboard_card_ids[]" value="<?php echo $c_id; ?>" <?php echo $checked ? 'checked' : ''; ?>>
	        <span class="card-select-icon" style="color:<?php echo $color; ?>"><?php echo $icon; ?></span>
	        <span class="card-select-title"><?php echo $title; ?></span>
	    </label>
	    <?php endforeach; ?>
	  </div>
	</div>
	</td>
</tr>
<tr>
<td colspan=2>
<button type="submit" form="mainform"><img src="images/save.png" alt='Save'> <?php te("Save");?></button>
<?php 
if (is_numeric($id) && $id != 1) {
    echo "\n<button type='button' onclick='delconfirm2(\"{$r['id']}\",\"$scriptname?action=$action&delid=$id\",\"All items assigned to admin.\");'>". 
         "<img src='images/delete.png' border=0>".t("Delete")."</button>\n";
} 
?>
</td>
</tr>
</table>
<input type=hidden name='id' value='<?php echo $id ?>'>
<input type=hidden name='action' value='<?php echo $action ?>'>
</form>
</body>
</html>
