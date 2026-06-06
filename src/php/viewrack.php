<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

/* Spiros Ioannou 2009 , sivann _at_ gmail.com */
$id=$_GET['id'];

	// --- 第一步：查询机柜信息 (Racks) ---
	$sql_rack = "SELECT * FROM racks WHERE id = :id";
	$sth_rack = $dbh->prepare($sql_rack);
	$sth_rack->execute([':id' => $id]);
	$rack = $sth_rack->fetch(PDO::FETCH_ASSOC);
	
	// 检查机柜是否存在，如果不存在则初始化空数组
	if (!$rack) {
	    $rack = ['usize' => 0, 'revnums' => 0]; // 提供默认值防止后续报错
	}
	
	// --- 第二步：查询设备信息 (Items) ---
	// 注意：这里使用了 $rack['id'] 而不是原始的 $id，确保存在的机柜才查数据
	$items = []; // 初始化空数组
	if (!empty($rack['id'])) {
	    $sql_items = "SELECT items.*, agents.title as agtitle 
	                  FROM items 
	                  INNER JOIN agents ON agents.id = items.manufacturerid 
	                  WHERE items.rackid = :rackid
	                  AND items.rackmountable = 1";
	    
	    $sth_items = $dbh->prepare($sql_items);
	    $sth_items->execute([':rackid' => $rack['id']]);
	    
	    // 关键修复：只有查询成功才遍历
	    while ($r = $sth_items->fetch(PDO::FETCH_ASSOC)) {
	        $items[$r['id']] = $r;
	    }
	}


$err="";

//find item positions in rack for all racks
$mi=0;
if (isset($items) && $rack['revnums']) { //reverse rack row numbering
  foreach ($items as $it) {

    if (!is_numeric($it['rackposition']) || !is_numeric($it['usize'])) {
      $moreitems[$mi++]=$it;
      continue; //items with wrong position info
    }

    if (($it['rackposition']+$it['usize']-1)>$rack['usize']) {
		$err .= sprintf(
		    t("Item %s (ID:%s) exceeds rack boundaries!"),
		    htmlspecialchars($it['label']),
		    htmlspecialchars($it['id'])
		) . "<br>";
      continue;
    }


    for ($pos=$it['rackposition'];$pos<($it['rackposition']+$it['usize']) ;$pos++) {

      if (($it['rackposdepth']&4) && isset($rackrow[$pos]['F']) && $rackrow[$pos]['F'] && $rackrow[$pos]['F']!=$it['id']) {
		$err .= sprintf( 
		    t("Position conflict in row %s Front for items %s and %s"), 
		    $pos, 
		    "<a href='$scriptname?action=edititem&amp;id={$it['id']}'>".htmlspecialchars($it['label'])." (ID:{$it['id']})</a>", 
		    "<a href='$scriptname?action=edititem&amp;id={$rackrow[$pos]['F']}'>".htmlspecialchars($items[$rackrow[$pos]['F']]['label'])." (ID:{$rackrow[$pos]['F']})</a>" 
		) . "<br>";

      }
      if (($it['rackposdepth']&2) && isset($rackrow[$pos]['M']) && $rackrow[$pos]['M'] && $rackrow[$pos]['M']!=$it['id']) {
		$err .= sprintf( 
		    t("Position conflict in row %s Middle for items %s and %s"), 
		    $pos, 
		    "<a href='$scriptname?action=edititem&amp;id={$it['id']}'>".htmlspecialchars($it['label'])." (ID:{$it['id']})</a>", 
		    "<a href='$scriptname?action=edititem&amp;id={$rackrow[$pos]['M']}'>".htmlspecialchars($items[$rackrow[$pos]['M']]['label'])." (ID:{$rackrow[$pos]['M']})</a>" 
		) . "<br>";

      }
      if (($it['rackposdepth']&1) && isset($rackrow[$pos]['B']) && $rackrow[$pos]['B'] && $rackrow[$pos]['B']!=$it['id']) {
		$err .= sprintf( 
		    t("Position conflict in row %s Back for items %s and %s"), 
		    $pos, 
		    "<a href='$scriptname?action=edititem&amp;id={$it['id']}'>".htmlspecialchars($it['label'])." (ID:{$it['id']})</a>", 
		    "<a href='$scriptname?action=edititem&amp;id={$rackrow[$pos]['B']}'>".htmlspecialchars($items[$rackrow[$pos]['B']]['label'])." (ID:{$rackrow[$pos]['B']})</a>" 
		) . "<br>";

      }

      if ($pos==$it['rackposition']) $isitemtop=1; else $isitemtop=0;
      if ($it['rackposdepth']&4) {$rackrow[$pos]['F']=$it['id']; $rackrow[$pos]['FT']=$isitemtop;}
      if ($it['rackposdepth']&2) {$rackrow[$pos]['M']=$it['id']; $rackrow[$pos]['MT']=$isitemtop;}
      if ($it['rackposdepth']&1) {$rackrow[$pos]['B']=$it['id']; $rackrow[$pos]['BT']=$isitemtop;}

    }//for usize

  } //foreach

}
else if (isset($items)) { //normal row numbering (bottom==1)
  foreach ($items as $it) {

    if (!is_numeric($it['rackposition']) || !is_numeric($it['usize'])) {
      $moreitems[$mi++]=$it;
      continue; //items with wrong position info
    }

    if ($it['rackposition']-$it['usize']<0) {
		$err .= sprintf(
		    t("Item %s (ID:%s) exceeds rack boundaries!"),
		    htmlspecialchars($it['label']),
		    htmlspecialchars($it['id'])
		) . "<br>";
		
      continue;
    }

    for ($pos=$it['rackposition'];$pos>($it['rackposition']-$it['usize']) ;$pos--) {

      if (($it['rackposdepth']&4) && isset($rackrow[$pos]['F']) && $rackrow[$pos]['F'] && $rackrow[$pos]['F']!=$it['id']) {
		$err .= sprintf( 
		    t("Position conflict in row %s Front for items %s and %s"), 
		    $pos, 
		    "<a href='$scriptname?action=edititem&amp;id={$it['id']}'>".htmlspecialchars($it['label'])." (ID:{$it['id']})</a>", 
		    "<a href='$scriptname?action=edititem&amp;id={$rackrow[$pos]['F']}'>".htmlspecialchars($items[$rackrow[$pos]['F']]['label'])." (ID:{$rackrow[$pos]['F']})</a>" 
		) . "<br>";


      }
      if (($it['rackposdepth']&2) && isset($rackrow[$pos]['M']) && $rackrow[$pos]['M'] && $rackrow[$pos]['M']!=$it['id']) {
		$err .= sprintf( 
		    t("Position conflict in row %s Middle for items %s and %s"), 
		    $pos, 
		    "<a href='$scriptname?action=edititem&amp;id={$it['id']}'>".htmlspecialchars($it['label'])." (ID:{$it['id']})</a>", 
		    "<a href='$scriptname?action=edititem&amp;id={$rackrow[$pos]['M']}'>".htmlspecialchars($items[$rackrow[$pos]['M']]['label'])." (ID:{$rackrow[$pos]['M']})</a>" 
		) . "<br>";

      }
      if (($it['rackposdepth']&1) && isset($rackrow[$pos]['B']) && $rackrow[$pos]['B'] && $rackrow[$pos]['B']!=$it['id']) {
		$err .= sprintf( 
		    t("Position conflict in row %s Back for items %s and %s"), 
		    $pos, 
		    "<a href='$scriptname?action=edititem&amp;id={$it['id']}'>".htmlspecialchars($it['label'])." (ID:{$it['id']})</a>", 
		    "<a href='$scriptname?action=edititem&amp;id={$rackrow[$pos]['B']}'>".htmlspecialchars($items[$rackrow[$pos]['B']]['label'])." (ID:{$rackrow[$pos]['B']})</a>" 
		) . "<br>";

      }

      if ($pos==$it['rackposition']) $isitemtop=1; else $isitemtop=0;
      if ($it['rackposdepth']&4) {$rackrow[$pos]['F']=$it['id']; $rackrow[$pos]['FT']=$isitemtop;}
      if ($it['rackposdepth']&2) {$rackrow[$pos]['M']=$it['id']; $rackrow[$pos]['MT']=$isitemtop;}
      if ($it['rackposdepth']&1) {$rackrow[$pos]['B']=$it['id']; $rackrow[$pos]['BT']=$isitemtop;}

    }//for usize

  }
}

//echo "<pre>"; print_r($rackrow); echo "<p>";

	echo "<h1>" . sprintf(
	    t("Rack ID: %s - %s %s"),
	    htmlspecialchars($id),
	    htmlspecialchars($rack['model']),
	    htmlspecialchars($rack['label'])
	) . "</h1>";


?>
<div style='float:left;padding-left:10px; width:380px; max-width:380px; overflow:hidden;'>
<table class="rack" style="table-layout:fixed; width:360px; border-collapse:collapse;">
<caption><?php te("SIDE VIEW");?></caption>

<tr>
<th style="width:38px;" title='<?php te("Rack Unit<br>1 RU=44.45mm");?>'><?php te("RU");?></th>
<th style="width:107px;"><?php te("Front");?></th>
<th style="width:107px;"><?php te("Middle");?></th>
<th style="width:107px;"><?php te("Back");?></th>
</tr>


<?php
function printitemcell($rr,$depth) {
  global $rackrow,$items,$scriptname,$_GET;
  global $dbh;
  $item = $items[$rackrow[$rr][$depth]];
  $itemid     = isset($item['id']) ? $item['id'] : '';
  $internalid = isset($item['internalid']) ? trim($item['internalid']) : '';
  $ipv4       = isset($item['ipv4']) ? trim($item['ipv4']) : '';
  $label      = isset($item['label']) ? trim($item['label']) : '';
  $agtitle    = isset($item['agtitle']) ? $item['agtitle'] : '';
  $model      = isset($item['model']) ? $item['model'] : '';
  $usize      = isset($item['usize']) ? intval($item['usize']) : 1;
  $sid = getstatusidofitem($itemid, $dbh);
  $x   = attrofstatus($sid, $dbh);
  $attr = $x[0];
  $link = $scriptname.'?action=edititem&id='.$itemid;

  $full_title = trim(
    t('ID').':'.$itemid.' | '.
    t('Internal ID').':'.($internalid ? $internalid : t('none')).' | '.
    t('IPv4').':'.($ipv4 ? $ipv4 : t('none')).' | '.
    t('Label').':'.($label ? $label : $agtitle.' '.$model)
  );

  // 关键：状态小方块 行内显示、居中、不换行
  $status_css = "display:inline-block; width:6px; height:12px; vertical-align:middle; margin-right:3px; border:none;";
  $text_css = "white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:8px; line-height:12px; vertical-align:middle;";

  // 1U 单行
  if($usize == 1){
    $show_id    = $internalid ? $internalid : $itemid;
    $show_label = $label ? $label : $agtitle.' '.$model;
    $text = trim($show_id.' '.$ipv4.' '.$show_label);
    return "<span $attr style='$status_css'></span>".
           "<a href=\"$link\" target=\"_new\" title=\"".htmlspecialchars($full_title)."\" style='$text_css'>".
           "<span style='$text_css display:block;'>".htmlspecialchars($text)."</span>".
           "</a>";
  }
  // 2U+ 两行
  else {
    $line1 = t('ID').':'.$itemid.'  '.t('Internal ID').':'.$internalid.'  '.t('IPv4').':'.$ipv4;
    $line2 = trim($label.'  '.$agtitle.' '.$model);
    return "<span $attr style='$status_css'></span>".
           "<a href=\"$link\" target=\"_new\" title=\"".htmlspecialchars($full_title)."\">".
           "<span style='$text_css display:block;'>".htmlspecialchars($line1)."</span>".
           "<span style='$text_css display:block;'>".htmlspecialchars($line2)."</span>".
           "</a>";
  }
}

if (isset($_GET['highlightid'])) $hid=$_GET['highlightid'];

if ($rack['revnums']) {
  for ($rr=1;$rr<=$rack['usize'];$rr++) {
      echo "\n<tr>\n";
      echo "<td style='background-color:white;text-align:center;'>$rr</td>\n";
      $cell=1;
      $colspan=1;
      

      if ($rackrow[$rr]['FT']) { //is top row of this item?
        $rowspan=$items[$rackrow[$rr]['F']]['usize'];
	if ($rackrow[$rr]['F'] != $rackrow[$rr]['M'])  $colspan=1;
	elseif (($rackrow[$rr]['F'] == $rackrow[$rr]['M']) &&  ($rackrow[$rr]['M'] != $rackrow[$rr]['B']))  $colspan=2;
	elseif (($rackrow[$rr]['F'] == $rackrow[$rr]['M']) &&  ($rackrow[$rr]['M'] == $rackrow[$rr]['B']))  $colspan=3; //full row

	if ($hid==$rackrow[$rr]['F']) $c="highlight" ; else $c="occupied";
	echo " <td class='$c' colspan='$colspan' rowspan='$rowspan'>".printitemcell($rr,'F')."</td> ";
      } 
      elseif (!isset ($rackrow[$rr]['F']) || (!$rackrow[$rr]['F'])) { //empty cell
	echo " <td class='emptyrow'>&nbsp;</td> ";
	$colspan=1;
      }
      $cell+=$colspan;

      if ($cell==2) { //we have already printed one talbe cell in this row
	if ($rackrow[$rr]['MT']) { //is top row of this item?
	  $rowspan=$items[$rackrow[$rr]['M']]['usize'];
	  if ($rackrow[$rr]['M'] != $rackrow[$rr]['B'])  $colspan=1;
	  elseif ($rackrow[$rr]['M'] == $rackrow[$rr]['B'])  $colspan=2;
	  if ($hid==$rackrow[$rr]['M']) $c="highlight" ; else $c="occupied";
	  echo "<td class='$c' colspan='$colspan' rowspan='$rowspan'>".printitemcell($rr,'M');
	  $cell+=$colspan;
	}
	elseif (!isset ($rackrow[$rr]['M']) || (!$rackrow[$rr]['M'])) { //empty cell
	  echo " <td class='emptyrow'>&nbsp;</td> ";
	  $colspan=1;
	}
	$cell+=$colspan;
      }//cell==2

//echo "<br>$rr C:$cell B:".$rackrow[$rr]['B']. " BT:".$rackrow[$rr]['BT'];

// 替换这里 ↓↓↓
if ($cell==3 || !empty($rackrow[$rr]['B']) || !isset($rackrow[$rr]['B'])) {
	if ($rackrow[$rr]['BT']) { //is top row of this item?
	  $rowspan=$items[$rackrow[$rr]['B']]['usize'];
	  if ($hid==$rackrow[$rr]['B']) $c="highlight" ; else $c="occupied";
	  echo "<td class='$c' colspan='1' rowspan='$rowspan'>".printitemcell($rr,'B')."</td>";
	}
	elseif (!isset ($rackrow[$rr]['B']) || (!$rackrow[$rr]['B'])) { //empty cell
	  echo " <td class='emptyrow'>&nbsp;</td> ";
	  $colspan=1;
	}
}


    echo "\n</tr>\n";
 }//for

}

else {
  for ($rr=$rack['usize'];$rr>0;$rr--) {
      echo "\n<tr>\n";
      echo "<td style='background-color:white;text-align:center;'>$rr</td>\n";
      $cell=1;
      $colspan=1;
      

      if ($rackrow[$rr]['FT']) { //is top row of this item?
        $rowspan=$items[$rackrow[$rr]['F']]['usize'];
	if ($rackrow[$rr]['F'] != $rackrow[$rr]['M'])  $colspan=1;
	elseif (($rackrow[$rr]['F'] == $rackrow[$rr]['M']) &&  ($rackrow[$rr]['M'] != $rackrow[$rr]['B']))  $colspan=2;
	elseif (($rackrow[$rr]['F'] == $rackrow[$rr]['M']) &&  ($rackrow[$rr]['M'] == $rackrow[$rr]['B']))  $colspan=3; //full row

	if ($hid==$rackrow[$rr]['F']) $c="highlight" ; else $c="occupied";
	echo " <td class='$c' colspan='$colspan' rowspan='$rowspan'>".printitemcell($rr,'F')."</td> ";
      } 
      elseif (!isset ($rackrow[$rr]['F']) || (!$rackrow[$rr]['F'])) { //empty cell
	echo " <td class='emptyrow'>&nbsp;</td> ";
	$colspan=1;
      }
      $cell+=$colspan;

      if ($cell==2) { //we have already printed one talbe cell in this row
	if ($rackrow[$rr]['MT']) { //is top row of this item?
	  $rowspan=$items[$rackrow[$rr]['M']]['usize'];
	  if ($rackrow[$rr]['M'] != $rackrow[$rr]['B'])  $colspan=1;
	  elseif ($rackrow[$rr]['M'] == $rackrow[$rr]['B'])  $colspan=2;
	  if ($hid==$rackrow[$rr]['M']) $c="highlight" ; else $c="occupied";
	  echo "<td class='$c' colspan='$colspan' rowspan='$rowspan'>".printitemcell($rr,'M');
	  $cell+=$colspan;
	}
	elseif (!isset ($rackrow[$rr]['M']) || (!$rackrow[$rr]['M'])) { //empty cell
	  echo " <td class='emptyrow'>&nbsp;</td> ";
	  $colspan=1;
	}
	$cell+=$colspan;
      }//cell==2

      //echo "<br>$rr C:$cell B:".$rackrow[$rr]['B']. " BT:".$rackrow[$rr]['BT'];

// 替换这里 ↓↓↓
if ($cell==3 || !empty($rackrow[$rr]['B']) || !isset($rackrow[$rr]['B'])) {
	if ($rackrow[$rr]['BT']) { //is top row of this item?
	  $rowspan=$items[$rackrow[$rr]['B']]['usize'];
	  if ($hid==$rackrow[$rr]['B']) $c="highlight" ; else $c="occupied";
	  echo "<td class='$c' colspan='1' rowspan='$rowspan'>".printitemcell($rr,'B')."</td>";
	}
	elseif (!isset ($rackrow[$rr]['B']) || (!$rackrow[$rr]['B'])) { //empty cell
	  echo " <td class='emptyrow'>&nbsp;</td> ";
	  $colspan=1;
	}
}


    echo "\n</tr>\n";
  }

}


?>
<tr><td colspan=4 style='background-color:#666;border:1px solid #666;padding:0;'>&nbsp;</td></tr>
<tr>
<td style='border:0'><td style='padding:0;border:0;text-align:center'><img height=30 src='images/rackwheel.png'></td>
<td style='border:0'><td style='padding:0;border:0;text-align:center'><img height=30 src='images/rackwheel.png'></td>
</tr>
</table>

</div>

<div style='float:left;padding-left:20px;'>
<?php 
if ($mi) {
  echo "<h4>".t("More items assigned to this rack without position or u-size info").":</h4>";
  echo "<ul style='text-align:left;'>";
  for ($i=0;$i<$mi;$i++) {
	echo "<li><a href='$scriptname?action=edititem&amp;id={$moreitems[$i]['id']}'>" .
	     sprintf(t("Item %s: "), htmlspecialchars($moreitems[$i]['id'])) .
	     htmlspecialchars($moreitems[$i]['manufacturerid']) . " " .
	     htmlspecialchars($moreitems[$i]['model']) . " " .
	     htmlspecialchars($moreitems[$i]['label']) .
	     "</a></li>";
  }
  echo "</ul>";
}

echo "<p>";
echo $err;
?>
</div>

<?php
if ($action=="viewrack") {
?>
<script type="text/javascript">
$('a').attr("target", "_new");
</script>

<?php }?>