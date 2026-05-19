<?php

//ITDB:IT-items database
//sivann at gmail.com 2008-2014

$version=file_get_contents("VERSION");
$fordbversion=6;

/*********************************************************************** 
 *********************************************************************** 
 ***********************************************************************/

$itdb_start=getmicrotime();
function getmicrotime() {
    $a = explode (' ',microtime());
    return(double) $a[0] + $a[1];
} 

$initok=1;
require("init.php");


$head="";

if (!isset($_GET['action']))
  $_GET['action']="";
else {
  $_GET['action']=str_replace("/","",$_GET['action']);
  $_GET['action']=str_replace("%","",$_GET['action']);
  $_GET['action']=str_replace(";","",$_GET['action']);

}

if ((isset($_GET['export']) && ($_GET['export']==1))) {
  $action = "listitems2"; 
  require ("php/listitems2.php");
  exit;
}

$req="php/{$_GET['action']}.php";
$stitle="";

if ((isset($_GET['dlg']) && ($_GET['dlg']==1))) {
  $dlg=1;
}
else  {
  $dlg=0;
}


switch ($_GET['action']) {
  case "listitems2": 
    $title=t("Find Item2");
    break;
  case "listitems": 
    $title=t("Find Item");
    $head.="<link rel='stylesheet' type='text/css' href='css/jquery.tag.list.css' />\n";
    break;
  case "listagents": 
    $title=t("List Agents");
    break;
  case "editagent": 
    $title=t("Edit Agent");
    break;
  case "edititem": 
    $title=t("Edit Item");
    $stitle=t("Item");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.tag.js'></script>\n".
	   "<link rel='stylesheet' type='text/css' href='css/jquery.tag.css' />\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "editsoftware": 
    $title=t("Edit Software");
    $stitle=t("Software");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.tag.js'></script>\n".
	   "<link rel='stylesheet' type='text/css' href='css/jquery.tag.css' />\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "listsoftware": 
    $title=t("List Software");
    $head.="<link rel='stylesheet' type='text/css' href='css/jquery.tag.list.css' />\n";
    break;
  case "listcontracts": 
    $title=t("List Contracts");
    break;
  case "listemployees": 
    $title=t("List Employees");
    break;
  case "listdepartments": 
    $title=t("List Departments");
    break;
  case "listinvoices": 
    $title=t("List Invoices");
    break;
  case "editinvoice": 
    $title=t("Edit Invoice");
    $stitle=t("Invoice");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "listfiles": 
    $title=t("List Files");
    break;
  case "editfile": 
    $title=t("Edit File");
    $stitle=t("File");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "listusers": 
    $title=t("List Users");
    break;
  case "listdashboardcards":
    $title=t("Dashboard Cards");
    break;
  case "editdashboardcard":
    $stitle=t("Dashboard Card");
    $title=t("Edit Dashboard Card");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
           "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
           "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "listracks": 
    $title=t("List Racks");
    break;
  case "translations": 
    $title=t("Translations");
    break;
  case "settings": 
    $title=t("Settings");
    break;

  case "import": 
    $title=t("Import");
    break;
  case "edituser": 
    $stitle=t("User");
    $title=t("Edit User");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;

  case "editrack": 
    $stitle=t("Rack");
    $title=t("Edit Rack");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "editemployees": 
  	$stitle=t("Employees");
    $title=t("Edit Employees");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "editdepartments": 
  	$stitle=t("Departments");
    $title=t("Edit Departments");
    break;
  case "edititypes": 
    $title=t("Edit Item Types");
    break;
  case "editcontract": 
    $title=t("Edit Contract");
    $stitle=t("Contract");
    $head.="<script language='javascript' type='text/javascript' src='js/jquery.metadata.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.js'></script>\n".
	   "<script language='javascript' type='text/javascript' src='js/jquery.validate.front.js'></script>\n";
    break;
  case "editcontracttypes": 
    $title=t("Edit Contract Types");
    break;
  case "edittags": 
    $title=t("Edit Tags");
    break;
  case "editusers": 
    $title=t("Edit Users");
    break;
  case "listlocations": 
    $title=t("List Locations");
    break;
  case "editlocation": 
    $title=t("Edit Location");
    break;
  case "editstatustypes": 
    $title=t("Edit Item Status Types");
    break;
  case "editfiletypes": 
    $title=t("Edit File Types");
    break;
  case "printlabels": 
    $title=t("Print Labels");
    break;
  case "reports": 
    $title=t("Reports");
    $head.="<script language='javascript' type='text/javascript' src='js/jqplot/jquery.jqplot.js'></script>\n".
	   "<script type='text/javascript' src='js/jqplot/plugins/jqplot.pieRenderer.js'></script>\n".
	   "<script type='text/javascript' src='js/jqplot/plugins/jqplot.barRenderer.js'></script>\n".
	   "<script type='text/javascript' src='js/jqplot/plugins/jqplot.barRenderer.min.js'></script>\n".
	   "<script type='text/javascript' src='js/jqplot/plugins/jqplot.categoryAxisRenderer.min.js'></script>\n".
	   "<script type='text/javascript' src='js/jqplot/plugins/jqplot.pointLabels.min.js'></script>\n".
	   "<!--[if lt IE 9]><script language='javascript' type='text/javascript' src='js/jqplot/excanvas.js'></script><![endif]-->\n".
	   "<link rel='stylesheet' type='text/css' href='css/jquery.jqplot.css' />";
    break;
  case "showhist": 
    $title=t("History");
    break;
  case "browse": 
    $title=t("Browse Data");
    $head.="<script type='text/javascript' src='js/jstree/jquery.jstree.js'></script>";
    break;
  case "viewrack": 
    $title=t("Rack");
    $stitle=t("Rack");
    break;
  case "about":
    $title=t("About");
    $stitle=t("About");
    $req="php/about.php";
    break;
  default: 
    $title="";
    $stitle="";
    $req="php/home.php";
    break;
}
if (isset($_GET['id'])) 
  $id=$_GET['id']; 
else 
  $id="";

if (strlen($stitle)) $stitle.=":".$id;

$x="style_".$_GET['action'];
$$x="color:#BAFF04 ";


require('php/header.php');

if ($authstatus && (dbversion() != $fordbversion)) {
  echo "<body>";
  require ("php/itdbupdate.php");
  echo "</body>\n</html>\n";
  exit;
}

if ($dlg && $authstatus) {
  echo "<body>";
  require($req);
  echo "</body>\n</html>\n";
  exit;
}

?>

<body onload='BodyLoad()' class='mainbody'>


<!--div id='mainheader'> <?php echo $settings['companytitle']?> </div-->
<div id='leftcolumn' >
<div onclick='self.location.href="<?php echo $scriptname?>"' id='leftlogo' >
<span style='padding-top:5px;'> <a href='<?php echo $scriptname?>'> ITDB </a></span>
</div>

<span id=logo>
<?php te("IT ITems DataBase")?>
</span>

<hr class='green1'>
<?php 
if ($authstatus) {
?>

<table class='thdr' width='90%' border=0>

<tr><td><a style='<?php echo $style_?>' class='ahdr' href="<?php echo $scriptname?>" ><?php echo t("Home") ?></a></td> <td></td> </tr>
<tr><td><a style='<?php echo $style_about?>' class='ahdr' href="<?php echo $scriptname?>?action=about" ><?php te("About")?></a></td> <td></td> </tr>

<tr><td colspan=2><hr class='light1'> </td></tr>

<tr>
<td><a style="<?php echo $style_listitems.$style_edititem; ?>" class='ahdr' title='<?php te("List Items");?>' href="<?php echo $scriptname?>?action=listitems" ><?php te("Items")?></a> </td>
<td><a title='<?php te("Add new Item");?>' class='ahdr' href="<?php echo $scriptname?>?action=edititem&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listsoftware.$style_editsoftware; ?>" title='<?php te("List Software");?>' class='ahdr' href="<?php echo $scriptname?>?action=listsoftware" ><?php te("Software");?></a> </td>
<td><a title='<?php te("Add new Software");?>' class='ahdr' href="<?php echo $scriptname?>?action=editsoftware&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listinvoices.$style_editinvoice; ?>" title='<?php te("List Invoices");?>' class='ahdr' href="<?php echo $scriptname?>?action=listinvoices" ><?php te("Invoices");?></a> </td>
<td><a title='<?php te("Add new Invoice");?>' class='ahdr' href="<?php echo $scriptname?>?action=editinvoice&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>


<tr>
<td><a style="<?php echo $style_listagents.$style_editagent; ?>" title='<?php te("Vendors/Buyers/ Manufacturers");?>' class='ahdr' href="<?php echo $scriptname?>?action=listagents" ><?php te("Agents");?></a> </td>
<td><a title='<?php te("Add new Agent");?>' class='ahdr' href="<?php echo $scriptname?>?action=editagent&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listfiles.$style_editfile; ?>" title='<?php te("Documents, Manuals, Offers, Licenses, ...");?>' class='ahdr' href="<?php echo $scriptname?>?action=listfiles" ><?php te("Files");?></a> </td>
<td><a title='<?php te("Add new File");?>' class='ahdr' href="<?php echo $scriptname?>?action=editfile&amp;id=new" ><img  alt="+" src='images/add.png'></a></td> 
</tr>


<tr>
<td><a style="<?php echo $style_listcontracts.$style_editcontract; ?>" title='<?php te("Support and Maintanance, Leases, ...");?>' class='ahdr' href="<?php echo $scriptname?>?action=listcontracts" ><?php te("Contracts");?></a> </td>
<td><a title='<?php te("Add new Contract");?>' class='ahdr' href="<?php echo $scriptname?>?action=editcontract&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listlocations; ?>" class='ahdr' href="<?php echo $scriptname?>?action=listlocations" ><?php te("Locations");?></a></td>
<td><a style="<?php echo $style_editlocation; ?>" class='ahdr' href="<?php echo $scriptname?>?action=editlocation&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listusers; ?>" class='ahdr' href="<?php echo $scriptname?>?action=listusers" ><?php te("Users");?></a></td>
<td><a style="<?php echo $style_edituser; ?>" class='ahdr' href="<?php echo $scriptname?>?action=edituser&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>


<tr>
<td><a style="<?php echo $style_listracks; ?>" class='ahdr' href="<?php echo $scriptname?>?action=listracks" ><?php te("Racks");?></a></td>
<td><a style="<?php echo $style_editrack; ?>" class='ahdr' href="<?php echo $scriptname?>?action=editrack&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listdepartments; ?>" class='ahdr' href="<?php echo $scriptname?>?action=listdepartments" ><?php te("Departments");?></a></td>
<td><a style="<?php echo $style_editdepartments; ?>" class='ahdr' href="<?php echo $scriptname?>?action=editdepartments&amp;id=new"><img alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listemployees; ?>" class='ahdr' href="<?php echo $scriptname?>?action=listemployees" ><?php te("Employees");?></a></td>
<td><a style="<?php echo $style_editemployees; ?>" class='ahdr' href="<?php echo $scriptname?>?action=editemployees&amp;id=new"><img alt="+" src='images/add.png'></a></td>
</tr>

<tr>
<td><a style="<?php echo $style_listdashboardcards.$style_editdashboardcard; ?>" class='ahdr' title='<?php te("Dashboard Cards");?>' href="<?php echo $scriptname?>?action=listdashboardcards" ><?php te("Dashboard Cards")?></a> </td>
<td><a title='<?php te("Add new Dashboard Card");?>' class='ahdr' href="<?php echo $scriptname?>?action=editdashboardcard&amp;id=new" ><img  alt="+" src='images/add.png'></a></td>
</tr>


<tr><td colspan=2><hr class='light1'> </td></tr>

<tr><td colspan=2><a style="<?php echo $style_edititypes; ?>" class='ahdr' href="<?php echo $scriptname?>?action=edititypes" ><?php te("Item Types");?></a></td></tr>
<tr><td colspan=2><a style="<?php echo $style_editcontracttypes; ?>" class='ahdr' href="<?php echo $scriptname?>?action=editcontracttypes" ><?php te("Contr. Types")?></a></td></tr>
<tr><td colspan=2><a style="<?php echo $style_editstatustypes; ?>" class='ahdr' href="<?php echo $scriptname?>?action=editstatustypes" ><?php te("Status Types");?></a></td></tr>
<tr><td colspan=2><a style="<?php echo $style_editfiletypes; ?>" class='ahdr' href="<?php echo $scriptname?>?action=editfiletypes" ><?php te("File Types");?></a></td></tr>

<tr><td colspan=2><a style="<?php echo $style_edittags; ?>" class='ahdr' href="<?php echo $scriptname?>?action=edittags" ><?php te("Tags")?></a></td></tr>

<tr><td colspan=2><hr class='light1'> </td></tr>

<tr><td colspan=2><a style="<?php echo $style_printlabels; ?>" class='ahdr' href="<?php echo $scriptname?>?action=printlabels" ><?php te("Print Labels")?></a></td></tr>
<tr><td colspan=2><a style="<?php echo $style_reports; ?>" class='ahdr' href="<?php echo $scriptname?>?action=reports" ><?php te("Reports")?></a></td></tr>
<tr><td colspan=2><a style="<?php echo $style_browse; ?>" class='ahdr' href="<?php echo $scriptname?>?action=browse" ><?php te("Browse Data")?></a></td></tr>
<tr><td colspan=2><hr class='light1'></td></tr>

<tr><td colspan=2><a style="<?php echo $style_settings; ?>" class='ahdr' href="<?php echo $scriptname?>?action=settings" ><?php te("Settings");?></a></td></tr>

<tr><td colspan=2><a style="<?php echo $style_import; ?>" class='ahdr' href="<?php echo $scriptname?>?action=import" ><?php te("Import");?></a></td></tr>
<tr><td colspan=2><a style="<?php echo $style_translations; ?>" class='ahdr' href="<?php echo $scriptname?>?action=translations" ><?php te("Translations");?></a></td></tr>
<tr><td colspan=2><a style="<?php echo $style_showhist; ?>" class='ahdr' href="<?php echo $scriptname?>?action=showhist" ><?php te("DB log");?></a></td></tr>
</table>
<?php 

}
else {
  if (isset($_COOKIE["itdbuser"])) $itdbuser=$_COOKIE["itdbuser"]; 
  else $itdbuser=t("username");

	// 登录失败日志
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['authusername']) && !$authstatus) {
	    $username = trim($_POST['authusername']);
	    addOperateLog(
	        'auth',
	        'login_fail',
	        'User %s login failed',
	        array($username),
	        'user',
	        0,
	        null,
	        array('username' => $username),
	        0,
	        'Invalid username or password'
	    );
	}


  echo "\n<form name=itdbloginfrm method=post>".
   "<input name=authusername size=10 onfocus=\"this.value='';\" ".
   "value='$itdbuser'>\n<br>".
   "<input name=authpassword size=10  type=password onfocus=\"this.value='';\" ".
   "value=''>\n".
   "<br><br><button type=submit><img src='images/key.png'>  ".t("Login")."</button>";
   "\n";
}

// 注销日志
if (isset($_POST['logout']) && $_POST['logout'] == '1') {
    $logout_user = isset($_COOKIE['itdbuser']) ? $_COOKIE['itdbuser'] : 'unknown';
    addOperateLog(
        'auth',
        'logout',
        'User %s logout success',
        array($logout_user),
        'user',
        0,
        array('username' => $logout_user),
        null,
        1,
        ''
    );
}


if ($authstatus) {
  echo "\n<div style='height:5px'></div>".
       "<form method=post><button type='submit'><img width=20 src='images/logout_red.png'> ".t("Logout")."</button>".
       "\n<input type=hidden name=logout value='1'></form>";


  if (strlen($stitle)) {
    $url="$fscriptname?action=$action&id=$id";

    $sql="SELECT * FROM viewhist order by id DESC limit 1";
    $sth=db_execute($dbh,$sql);
    $viewhist=$sth->fetchAll(PDO::FETCH_ASSOC);
    if (!$demomode) {
      if ($viewhist[0]['url']!=$url) {
	$sql="INSERT into viewhist (url,description)".
	     " VALUEs ('$url','$stitle')";
	db_exec($dbh,$sql,1,1,$lastid);

	$lastkeep=(int)($lastid)-40;
	$sql="DELETE from viewhist where id<$lastkeep";
	db_exec($dbh,$sql,1,1);
	$sth=$dbh->exec($sql);
      }
    }
  }

  $sql="SELECT * FROM viewhist order by id DESC";
  $sth=db_execute($dbh,$sql);
  $viewhist=$sth->fetchAll(PDO::FETCH_ASSOC);

  ?>
  <div title='<?php te("Recent History");?>' style='font-size:7pt;height:75px;width:100%;overflow:auto;margin-top:5px ;margin-bottom:5px;text-align:left;color:white;border-bottom:1px solid #8FAFE4;'>
  <?php 
  for ($i=0;$i<count($viewhist);$i++){
    if (!($i%2)) $bgc="";else$bgc="background-color:#295BAD";
    echo "<div style='border-bottom:1px solid #8FAFE4;width:100%;clear:both;$bgc'><a style='color:white' href='".$viewhist[$i]['url']."'>".$viewhist[$i]['description']."</a></div>\n";
  }

  ?>
  </div>

<?php 
}

// 登录成功日志 - 仅POST登录时记录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['authusername']) && $authstatus) {
    $username = trim($_POST['authusername']);
    if ($username !== '') {
        addOperateLog(
            'auth',
            'login',
            'User %s login success',
            array($username),
            'user',
            0,
            null,
            array('username' => $username),
            1,
            ''
        );
    }
}


if (strstr($authmsg,"elcome") || strstr($authmsg,"thenticated")) {
    echo "<div class=info>$authmsg</div><br>";
}
elseif (!strstr($authmsg,"elcome")) 
  echo "<br><div class=warning>$authmsg</div>";


if ($authstatus) {
?>
  <a title='<?php te("Download DataBase file. Contains all data except uploaded files/documents");?>' class='ahdr' href='getdb.php'><img src='images/database_save.png'><?php te("DB (SQLite)");?></a><br>
  <a title='<?php te("Download a complete installation backup (much larger)");?>' class='ahdr' href='gettar.php'><img src='images/backup.gif' width=20><?php te("Full Backup");?></a><br>
  <a title='<?php te("Download a complete installation backup (much larger)");?>' class='ahdr' href='php/db_manager.php'><img src='images/database_save.png'><?php te("Database Manager");?></a><br>
<?php 
}

echo "<br> <small>".
     "<a href='CHANGELOG.txt' class='ahdr'>".sprintf(t("Version : %s"),$version)."</a><br><a style='color:white' href='http://www.sivann.gr/software/itdb/'>sivann</a></small>\n";
?>
<br>
<a title='<?php te("phpinfo");?>' href='phpinfo.php'><img src='images/infosmall.png'></a>
</div>
<!-- END OF #leftcolumn -->


<div id='mainpage'>
<?php 

if ($authstatus) 
  require($req);
else {
  echo "<b>".t("Please Login!")."</b>";
  require("php/about.php");
}

$itdb_end=getmicrotime();

echo "</div>";// <!-- end of #mainpage -->

echo "<span style='color:#aaa'>" . sprintf(t("server time = %s secs"), number_format(($itdb_end - $itdb_start), 3)) . "</span>";


?>
</body>
</html>