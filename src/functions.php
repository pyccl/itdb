<?php

function format_date($datetime, $settings, $show_date = true, $show_time = true) {
    $fmt     = isset($settings['dateformat']) ? $settings['dateformat'] : 'ymd';
    $timefmt = isset($settings['timeformat']) ? $settings['timeformat'] : '';

    $ts = is_numeric($datetime) ? intval($datetime) : strtotime($datetime);
    if ($ts === false || $ts <= 0) {
        return '-';
    }

    // 日期部分
    $date = '';
    if ($show_date) {
        switch ($fmt) {
            case 'dmy':       $date = date('d/m/Y', $ts); break;
            case 'mdy':       $date = date('m/d/Y', $ts); break;
            case 'ymd':       $date = date('Y-m-d', $ts); break;
            case 'ymd_no':    $date = date('Ymd', $ts); break;
            case 'dmy_dot':   $date = date('d.m.Y', $ts); break;
            case 'mdy_dot':   $date = date('m.d.Y', $ts); break;
            case 'cn_date':   $date = date('Y年m月d日', $ts); break;
            case 'ymd_short': $date = date('y-m-d', $ts); break;
            case 'dmy_short': $date = date('d/m/y', $ts); break;
            default:          $date = date('Y-m-d', $ts);
        }
    }

    // 时间部分：系统设置不为空 且 页面允许显示 才输出
    $time = '';
    if ($show_time && $timefmt !== '') {
        switch ($timefmt) {
            case 'H:i:s':     $time = date('H:i:s', $ts); break;
            case 'H:i':       $time = date('H:i', $ts); break;
            case 'h:i:s A':   $time = date('h:i:s A', $ts); break;
            case 'h:i A':     $time = date('h:i A', $ts); break;
            default:          $time = '';
        }
    }

    $ret = trim($date . ' ' . $time);
    return $ret === '' ? '-' : $ret;
}


// 获取所有可用卡片
function getDashboardCards($dbh){
    if(!$dbh) return array();
    // 空sort排最后，同sort按id排序（SQLite兼容）
    $sql = "SELECT * FROM dashboard_cards 
            WHERE status=1 
            ORDER BY 
                CASE WHEN sort IS NULL OR sort = '' THEN 999999 ELSE sort END ASC, 
                id ASC";
    $sth = @db_execute($dbh, $sql);
    if(!$sth) return array();
    return $sth->fetchAll(PDO::FETCH_ASSOC);
}


// 获取用户已选卡片ID
function getUserSelectedCardIds($dbh, $userid){
    if(!$dbh || !is_numeric($userid)) return array();
    $sql = "SELECT dashboard_cards FROM users WHERE id=?";
    $sth = @db_execute($dbh, $sql, array($userid));
    if(!$sth) return array();
    $r = $sth->fetch(PDO::FETCH_ASSOC);
    $ids = trim(isset($r['dashboard_cards']) ? $r['dashboard_cards'] : '');
    return $ids ? explode(',', $ids) : array();
}

// 保存用户选择的卡片
function updateUserDashboardCards($dbh, $userid, $card_ids)
{
    if (!$dbh || !is_numeric($userid)) return false;
    $card_ids = is_array($card_ids) ? $card_ids : array();
    $cards_str = implode(',', $card_ids);
    $userid = intval($userid);

    // 老式拼接，100%兼容你的系统
    $sql = "UPDATE users SET dashboard_cards = '$cards_str' WHERE id = $userid";
    db_exec($dbh, $sql);

    return true;
}

// 根据背景颜色自动判断文字黑白
function getAutoTextColor($bgColor) {
    $bgColor = ltrim($bgColor, '#');
    $r = hexdec(substr($bgColor,0,2));
    $g = hexdec(substr($bgColor,2,2));
    $b = hexdec(substr($bgColor,4,2));
    $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
    return $brightness > 125 ? '#000000' : '#ffffff';
}

//保存操作日志
function addOperateLog(
    $module,
    $operate_type,
    $lang_str,
    $params = array(),
    $target_type = '',
    $target_id = 0,
    $old_data = null,
    $new_data = null,
    $status = 1,
    $fail_reason = ''
) {
    global $dbh;

    // 用户名从Cookie取（ITDB标准）
    $operate_user = 'unknown';
    if (isset($_COOKIE['itdbuser'])) {
        $operate_user = trim($_COOKIE['itdbuser']);
    }

    $user_id = 0;
    $ip = $_SERVER['REMOTE_ADDR'];
    $request_url = $_SERVER['REQUEST_URI'];
    $user_agent = '';
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'], 0, 500);
    }

    // JSON 数据原样保留，不转义 HTML
    $old_value   = $old_data !== null ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : '';
    $new_value   = $new_data !== null ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : '';
    $params_json = !empty($params)      ? json_encode($params, JSON_UNESCAPED_UNICODE) : '';

    // ========== 新增修复代码 开始 ==========
    // SQLite SQL语句转义：单引号转双单引号，百分号转双百分号
    $old_value = str_replace(array("'", "%"), array("''", "%%"), $old_value);
    $new_value = str_replace(array("'", "%"), array("''", "%%"), $new_value);
    $params_json = str_replace(array("'", "%"), array("''", "%%"), $params_json);
    // ========== 新增修复代码 结束 ==========

    // 👇 核心修复：仅对 JSON 之外的字段做 strenc，JSON 原样写入
    $module_sql       = strenc($module);
    $operate_type_sql  = strenc($operate_type);
    $operate_user_sql  = strenc($operate_user);
    $ip_sql            = strenc($ip);
    $target_type_sql   = strenc($target_type);
    $lang_str_sql      = strenc($lang_str);
    $fail_reason_sql   = strenc($fail_reason);
    $request_url_sql    = strenc($request_url);
    $user_agent_sql    = strenc($user_agent);

    // 👇 重点：old_value / new_value / params_json 不做 strenc
    $sql = "INSERT INTO operate_log (
        module, operate_type, operate_user, user_id,
        ip, operate_time,
        target_type, target_id,
        old_value, new_value,
        content, params,
        status, fail_reason,
        request_url, user_agent
    ) VALUES (
        '$module_sql',
        '$operate_type_sql',
        '$operate_user_sql',
        ".intval($user_id).",
        '$ip_sql',
        datetime('now','localtime'),
        '$target_type_sql',
        ".intval($target_id).",
        '$old_value',
        '$new_value',
        '$lang_str_sql',
        '$params_json',
        ".intval($status).",
        '$fail_reason_sql',
        '$request_url_sql',
        '$user_agent_sql'
    )";

    // 用系统自带 db_exec 写入
    db_exec($dbh, $sql, 1, 1);
}

/**
 * 构建部门树形结构（全局通用，所有页面共用）
 * 与系统 editdepartments.php 完全一致
 */
function buildTree($elements, $parentId = 0, $depth = 0) {
    $branch = array();
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $element['depth'] = $depth;
            $children = buildTree($elements, $element['id'], $depth + 1);
            $branch[] = $element;
            $branch = array_merge($branch, $children);
        }
    }
    return $branch;
}

function sec2ymd($secs)
{
  if (strlen($secs))
    return date("Ymd",$secs);
  else 
    return "";
}

//convert Y/M/D dates to unix timestamp
function ymd2sec($d)
{
  global $settings;

  if (!strlen($d))
    $purchasedate2="NULL";
  elseif ($settings['dateformat']=="ymd"){
    $x=explode("-",$d);
    if ((count($x)==1) && strlen(trim($d))==4) { //only year
      $d2=  mktime(0, 0, 0, 1, 1, $d);
    }
    else {
      $d2=  mktime(0, 0, 0, $x[1], $x[2], $x[0]);
    }
    return $d2;
  }
  elseif ($settings['dateformat']=="dmy"){
    $x=explode("/",$d);
    if ((count($x)==1) && strlen(trim($d))==4) { //only year
      $d2=  mktime(0, 0, 0, 1, 1, $d);
    }
    else {
      $d2=  mktime(0, 0, 0, $x[1], $x[0], $x[2]);
    }
//echo "$d -> $d2<br>";
    return $d2;
  }
  elseif ($settings['dateformat']=="mdy"){
    $x=explode("/",$d);
    if ((count($x)==1) && strlen(trim($d))==4) { //only year
      $d2=  mktime(0, 0, 0, 1, 1, $d);
    }
    else {
      $d2=  mktime(0, 0, 0, $x[0], $x[1], $x[2]);
    }
    return $d2;
  }
  return "";
}

//remove invalid filename characters
function validfn($s) {
  $f =preg_split('//u', 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΣΤΥΦΧΨΩΪΫΌΎΏΆΈΰαβγδεζηθικλμνξοπρςστυφχψωίϊΐϋόύώάέΰ');
  $t =preg_split('//u', 'ABGDEZHUIKLMNJOPRSSTYFXCVIUOUVAEUabgdezhuiklmnjoprsstyfxcviiiuouvaeu');
  $s=str_replace($f,$t,$s);
  $reserved = preg_quote('\/:*?"<>|', '/');
	  $s = preg_replace_callback("/([-\\x00-\\x20\\x7f-\\xff{$reserved}])/u", function() {
	    return '';
	  }, $s); 
  $s=strtolower($s);
  return $s;
}


//encode string for sql/html
function strenc($s)
{
  $s=htmlspecialchars($s,ENT_QUOTES,"UTF-8");
  return $s;
}
//////////////////// Database functions /////
// check permissions, log errors and transactions
// 
//encode string for sql/html

//for insert, update, delete
function db_exec($dbh,$sql,$skipauth=0,$skiphist=0,&$wantlastid=0)
{
global $authstatus,$userdata, $remaddr, $dblogsize,$errorstr,$errorbt;

  if (!$skipauth && !$authstatus) {echo "<big><b>Not logged in</b></big><br>";return 0;}
  if (stristr($sql,"insert ")) $skiphist=1; //for lastid function to work.

  //find user access
  $usr=$userdata[0]['username'];
  $sqlt="SELECT usertype FROM users where username='$usr'";
  $sth=$dbh->prepare($sqlt);
  $sth->execute();
  $ut=$sth->fetch(PDO::FETCH_ASSOC);
  $usertype=($ut['usertype']);
  $sth->closeCursor();

  if (!$skipauth && $usertype && (stristr($sql,"DELETE") || stristr($sql,"UPDATE") || stristr($sql,"INSERT")) 
      && !stristr($sql," tt ")) { /*tt:temporary table used for complex queries*/
    echo "<big><b>Access Denied, user '$usr' is read-only</b></big><br>";
    return 0;
  }

  $r=$dbh->exec($sql);
  $error = $dbh->errorInfo();
  if($error[0] && isset($error[2])) {
    $errorstr= "<br><b>db_exec:DB Error: ($sql): ".$error[2]."<br></b>";
    $errorbt = debug_backtrace();
    echo "</table></table></div>\n<pre>".$errorstr;
    print_r ($errorbt);
    return 0;
  }
  $wantlastid=$dbh->lastInsertId();

  if (!$skiphist) {
    $hist="";
    $t=time();
    $escsql=str_replace("'","''",$sql);
    $histsql="INSERT into history (date,sql,ip,authuser) VALUES ($t,'$escsql','$remaddr','".$_COOKIE["itdbuser"]."')";
    //update history table
    $rh=$dbh->exec($histsql);
    $lasthistid=$dbh->lastInsertId();

    $error = $dbh->errorInfo();
    if($error[0] && isset($error[2])) {
      $errorstr= "<br><b>HIST DB Error: ($histsql): ".$error[2]."<br></b>";
      $errorbt = debug_backtrace();
      echo $errorstr;
      print_r ($errorbt);
      return 0;
    }
    else { /* remove old history entries */
	$lastkeep=(int)($lasthistid)-$dblogsize;
	$sql="DELETE from history where id<$lastkeep";
	$sth=$dbh->exec($sql);
    }

  }
  return $r;
} //db_exec

//for select
function db_execute($dbh,$sql,$skipauth=0)
{
  global $authstatus,$errorstr,$errorbt;
  if (!$skipauth && !$authstatus) {echo "<big><b>Not logged in</b></big><br>";return 0;}
  $sth = $dbh->prepare($sql);
  $error = $dbh->errorInfo();
  if($error[0] && isset($error[2])) {
    $errorstr= "\n<br><b>db_execute:DB Error: ($sql): ".$error[2]."<br></b>\n";
    $errorbt= debug_backtrace();

    echo "</table></table></div>\n<pre>".$errorstr;
    print_r ($errorbt);
    echo "</pre>";

    return 0;
  }
  $sth->execute();
  return $sth;
}

function opendb($dbfile) {
    global $dbh;
    //open db
    try {
      $dbh = new PDO("sqlite:$dbfile");
    } 
    catch (PDOException $e) {
      print "Open database Error!: " . $e->getMessage() . "<br>";
      die();
    }
    return $dbh;
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);


    //$ret = $dbh->exec("PRAGMA case_sensitive_like = 0;");

}

function ckdberr($resource) {
    global $errorstr;
    $error = $resource->errorInfo();
    if($error[0] && isset($error[2])) {
        $errorstr= $error[2];
        $errorbt = debug_backtrace();
        logerr($errorstr."   BACKTRACE: ".$errorbt);
        return 1;
    }
    return 0;
}


/*execute with prepared statements
Example:
        $sql="SELECT * from tablename where id=:id order by date";
        $stmt=db_execute($dbh,$sql,array('id'=>$items['id']));
        $res=$stmt->fetch(PDO::FETCH_ASSOC);
*/

function db_execute2($dbh,$sql,$params=NULL) {
    global $errorstr,$errorbt,$errorno;

    $sth = $dbh->prepare($sql);
    $error = $dbh->errorInfo();

    if(((int)$error[0]||(int)$error[1]) && isset($error[2])) {
        $errorstr= "DB Error: ($sql): <br>\n".
            $error[2]."<br>\nParameters:"."params\n";
            //implode(",",$params);
        $errorbt= debug_backtrace();
        $errorno=$error[1]+$error[0];
        logerr("$errorstr BACKTRACE:".$errorbt);
        return 0;
    }

    if (is_array($params))
        $sth->execute($params);
    else
        $sth->execute();

    $error = $sth->errorInfo();
    if(((int)$error[0]||(int)$error[1]) && isset($error[2])) {
        $errorstr= "DB Error: ($sql): <br>\n".$error[2]."<br>\nParameters:".implode(",",$params);
        $errorbt= debug_backtrace();
        $errorno=$error[1]+$error[0];
        logerr("$errorstr BACKTRACE:".$errorbt);
    }

    return $sth;
}



function connect_to_ldap_server($ldap_server,$username,$passwd,$ldap_dn) {
    global $gen_error,$gen_errorstr;

    $ds=ldap_connect($ldap_server);  // must be a valid LDAP server!
    //echo "connect result is " . $ds . "<br />\n";
    if($ds){
        $dn="uid=".$username.",".$ldap_dn;
        echo $dn;
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        $r=ldap_bind($ds,$dn, $passwd);
        if(!$r){
            $gen_errorstr="ldap_bind: ".ldap_error($ds);
            $gen_error=100;
            ldap_close($ds);
            return FALSE;
        }
        return $ds;
    }
    else {
        return FALSE;
    }
}


?>
