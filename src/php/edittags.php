<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

/* Spiros Ioannou 2011 , sivann _at_ gmail.com */

$internaltags=0;

//form submitted
if  (isset($delid) && $delid<$internaltags) { //delete an item entry
  echo sprintf(t("Type '%s' cannot be deleted: internal tag"),$delid);
}
elseif  (isset($delid)) { //delete an item entry

  $sql="SELECT count(tagid) count from tag2item WHERE tagid=".$_GET['delid'];
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $count_i=(int)$r['count'];

  $sql="SELECT count(tagid) count from tag2software WHERE tagid=".$_GET['delid'];
  $sth=db_execute($dbh,$sql);
  $r=$sth->fetch(PDO::FETCH_ASSOC);
  $count_s=(int)$r['count'];

  if ($count_i>0 || $count_s>0) {
        $msg = sprintf(t("Warning! There are %s item(s) and %s software associated to this tag. Tag %d not deleted!"), $count_i, $count_s, $delid);
        echo "<script>alert('".$msg."'); history.back();</script>";
        exit; // 阻止后续代码执行
  }
  else {
    $sql="DELETE from tags where id=".$_GET['delid'];
    $sth=db_exec($dbh,$sql);
    $successMsg = t("Tag deleted successfully!");
    $refreshUrl = "$scriptname?action=edittags";
        
    // 合并在一个 Script 中：先弹窗，然后跳转
    echo "<script>alert('$successMsg'); document.location='$refreshUrl';</script>";
        
    // 因为页面会跳转，下面的 HTML 链接通常不会显示，但为了防止 JS 失效，保留一个链接
    echo "<a href='$refreshUrl'>".t("Go back")."</a>";
    exit; 
  }
}
	if (isset($newtag)) {
	    $newtag = trim($newtag);
	    
	    // 1. 检查标签长度
	    if (strlen($newtag) > 1) {
	        
	        // 2. 新增：查询数据库，检查是否已存在同名标签
	        // 使用 PDO 的 quote 方法防止 SQL 注入（虽然 trim 过，但为了安全）
	        $safe_newtag = $dbh->quote($newtag);
	        $sql_check = "SELECT id FROM tags WHERE name = $safe_newtag LIMIT 1";
	        $sth_check = $dbh->query($sql_check);
	        $existing_tag = $sth_check->fetch(PDO::FETCH_ASSOC);
	
	        if ($existing_tag) {
	            // 3. 如果存在，设置错误信息（注意：这里需要确保 $errors 数组已定义，或者使用 $msg）
	            $errors[] = sprintf(t("Tag '%s' already exists. Please use a different name."), $newtag);
	        } else {
	            // 4. 如果不存在，执行插入（原逻辑）
	            $sql = "INSERT INTO tags (name) VALUES ('$newtag')";
	            $sth = db_execute($dbh, $sql);
	            // 插入成功，可以设置成功信息（可选）
	            // $successMsg = t("New tag added successfully!");
	        }
	    } else {
	        // 原有的长度检查
	        $errors[] = t("Tag name is too short");
	    }
	
	    // --- 保留原有的更新旧标签逻辑（Update all old tags）---
	    if (isset($_GET["ids"])) {
	        for ($i = 0; $i < count($_GET["ids"]); $i++) {
	            $names = $_GET['names'];
	            $ids = $_GET['ids'];
	            $sql = "UPDATE tags SET name='" . $names[$i] . "' WHERE id='" . $ids[$i] . "'";
	            db_exec($dbh, $sql);
	        }
	    }
	}

//echo "<pre>"; print_r($_GET); echo "</pre>";

$sql="SELECT * from tags order by name";
$sth = $dbh->query($sql);
$tags=$sth->fetchAll(PDO::FETCH_ASSOC);

	// 显示错误信息（放在 Form 上方）
	if (!empty($errors)) {
	    echo "<div style='color: red; background: #ffeeee; border: 1px solid red; padding: 10px; margin-bottom: 10px;'>";
	    foreach ($errors as $error) {
	        echo htmlspecialchars($error) . "<br>";
	    }
	    echo "</div>";
	}
	
	// 显示成功信息（可选）
	if (!empty($successMsg)) {
	    echo "<div style='color: green; background: #eeffee; border: 1px solid green; padding: 10px; margin-bottom: 10px;'>";
	    echo htmlspecialchars($successMsg);
	    echo "</div>";
	}

echo "<form method=get name='tagaddfrm'>";
echo "<input type=hidden name=action value='".$_GET["action"]."'>";
?>

	<h1><?php te("Edit Tags");?></h1>
	<div style='float:left'>
<table border=0 class='brdr' >

<tr><th>&nbsp;</th><th><?php te("ID");?></th><th><?php te("TAG");?></th><th><?php te("Associated Items");?></th><th><?php te("Associated Software");?></th></tr>

<?php 
//print tag list
for ($i=0;$i<count($tags);$i++) {
  $dbid=$tags[$i]['id'];
  $name=$tags[$i]['name'];

if ($dbid>=$internaltags) //change this to remove X from internal tags
  echo "\n\n<tr><td><a href='javascript:delconfirm(\"$name\",\"$scriptname?action=edittags&amp;delid=$dbid\");'><img title='delete' src='images/delete.png' border=0></a></td>";
else echo "\n\n<tr><td>--</td>\n";

  echo "<td>$dbid</td>";
  
  echo "<td><input size='30' type='text' name='names[]' ".
    "value=\"".$tags[$i]['name']."\">\n";
  echo "\n<input type=hidden name='ids[]' value='$dbid' >\n</td>\n";

  echo "<td>";
  $cnt=countitemtags($dbid);
  echo "<a href='$dbid' class='showitems'>$cnt</a>";
  echo "</td>\n";

  echo "<td>";
  $cnt=countsoftwaretags($dbid);
  echo "<a href='$dbid' class='showsoftware'>$cnt</a>";
  echo "</td>\n";
  echo "</tr>\n";
}

if (!isset($dbid)) $dbid=0;
?>

    <tr><td colspan=2><?php te("New");?>:</td>
      <td><input size='30' name='newtag' type='text'></td>
      <td colspan=2></td>
    </tr>

<tr><td style='text-align: right' colspan=5><button type="submit"><img src="images/save.png" alt="Save" > <?php te("Save");?></button></td></tr>
<tr><td style='text-align: left' colspan=5> </td></tr>
</table>
	</div>
</form>

<div style='text-align:left;float:left;margin-left:50px;min-width:350px;max-width:500px;min-height:300px;border:1px solid #fff;' 
     id='itemresults'><?php te("Click on Item count column on the left to display associated items");?></div>

<div style='text-align:left;float:left;margin-left:50px;min-width:350px;max-width:500px;min-height:300px;border:1px solid #fff;' 
     id='softwareresults'><?php te("Click on Software count column on the left to display associated software");?></div>

<script>
  $(document).ready(function(){    

    $(".showitems" ).click(function() {
      $("#itemresults").html('<center><img src="images/ajaxload.gif"></center>').load('php/tag2item_ajaxlist.php?tagid='+ $(this).attr('href'));
      return false;
    });

    $(".showsoftware" ).click(function() {
      $("#softwareresults").html('<center><img src="images/ajaxload.gif"></center>').load('php/tag2software_ajaxlist.php?tagid='+ $(this).attr('href'));
      return false;
    });
 }); 
</script>
