<SCRIPT LANGUAGE="JavaScript"> 

$(document).ready(function() {



<?php
if (isset($_POST['dateformat']) ) { //if we came from a post (save), refresh to show new language
	echo "window.location=window.location;";
}
?>

});
</SCRIPT>
<?php 

if (!isset($initok)) {echo t("do not run this script directly");exit;}
if(!isset($userdata) || $userdata[0]['usertype'] == 1) { echo "You must have Admin (Full Access) to access this page";exit;}

/* Spiros Ioannou 2009-2010 , sivann _at_ gmail.com */

if (isset($_POST['dateformat']) ) { //if we came from a post (save), update the rack 
  $sql="UPDATE settings set companytitle='".trim($_POST['companytitle']).
  "', dateformat='".$_POST['dateformat'].
  "', timeformat='".$_POST['timeformat'].
  "', currency='".$_POST['currency'].
  "', useldap='".$_POST['useldap'].
  "', ldap_server='".trim($_POST['ldap_server']).
  "', ldap_dn='".trim($_POST['ldap_dn']).
  "', ldap_getusers='".trim($_POST['ldap_getusers']).
  "', ldap_getusers_filter='".trim($_POST['ldap_getusers_filter']).
  "',".
       " lang='".$_POST['lang']."', ".
       //" switchmapenable='".$_POST['switchmapenable']."', switchmapdir='".$_POST['switchmapdir']."',".
       " timezone='".$_POST['timezone']."' ";
  db_exec($dbh,$sql);

}//save pressed

/////////////////////////////
//// display data 

$sql="SELECT * FROM settings";
$sth=$dbh->query($sql);
$settings=$sth->fetchAll(PDO::FETCH_ASSOC);
$settings=$settings[0];

echo "\n<form id='mainform' method=post  action='$scriptname?action=$action' enctype='multipart/form-data'  name='settingsfrm'>\n";

echo "\n<h1>".t("Settings")."</h1>\n";
?>

    <table class="tbl2" >
    <tr><td colspan=2><h3><?php te("Settings"); ?></h3></td></tr>
    <tr><td class="tdt"><?php te("Company Title");?>:</td> 
        <td><input  class='input2 ' size=20 type=text name='companytitle' value="<?php echo $settings['companytitle']?>"></td></tr>
    <tr><td class="tdt"><?php te("Date Format")?></td><td>
    <select  name='dateformat'>
      <?php if ($settings['dateformat']=="dmy") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> value='dmy'><?php te("Day/Month/Year");?></option>
      <?php if ($settings['dateformat']=="mdy") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> value='mdy'><?php te("Month/Day/Year");?></option>
      <?php if ($settings['dateformat']=="ymd") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> value='ymd'><?php te("Year-Month-Day");?></option>
    </select>
    </td>
    </tr>
	<tr><td class="tdt"><?php te("Time Format")?></td><td> 
	    <select name='timeformat'> 
	        <?php 
	        // 检查是否为 24小时制
	        if ($settings['timeformat'] == "H:i:s") { 
	            $s1 = "SELECTED"; 
	            $s2 = ""; 
	        } else { 
	            $s1 = ""; 
	            $s2 = "SELECTED"; // 默认如果数据库不是 H:i:s，就选中 "无时间"
	        } 
	        ?> 
	        <option <?php echo $s1?> value='H:i:s'><?php te("Hour:Minute:Second"); ?> (15:30:45)</option> 
	        <option <?php echo $s2?> value=''><?php te("None (Timeless)"); ?></option> 
	    </select> 
	</td></tr>



    <tr><td class="tdt"><?php te("Currency")?></td><td>

    <select name='currency'>
      <?php if ($settings['currency']=="&euro;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Euro");?>' value='<?php echo htmlentities("&euro;");?>'><?php te("Euro");?>-&euro;</option>

      <?php if ($settings['currency']=="$") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Dollar");?>' value='<?php echo htmlentities("$");?>'><?php te("Dollar");?>-$</option>

      <?php if ($settings['currency']=="&pound;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Pound");?>' value='<?php echo htmlentities("&pound;");?>'><?php te("Pound");?>-&pound;</option>

      <?php if ($settings['currency']=="&yen;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Yen");?>' value='<?php echo htmlentities("&yen;");?>'><?php te("Yen");?>-&yen;</option>

      <?php if ($settings['currency']=="&#8361;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Won");?>' value='<?php echo htmlentities("&#8361;");?>'><?php te("Won");?>-&#8361;</option>

      <?php if ($settings['currency']=="&#8360;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Rupee");?>' value='<?php echo htmlentities("&#8360;");?>'><?php te("Rupee");?>-&#8360;</option>

      <?php if ($settings['currency']=="&#8377;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Indian Rupee");?>' value='<?php echo htmlentities("&#8377;");?>'><?php te("Indian Rupee");?>-&#8377;</option>

	  <?php if ($settings['currency']=="&yen;") $s="SELECTED"; else $s="" ?>
	  <option <?php echo $s?> title='<?php te("Yuan");?>' value='<?php echo htmlentities("&yen;");?>'><?php te("Yuan");?>-&yen;</option>

      <?php if ($settings['currency']=="&#65020;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Rial");?>' value='<?php echo htmlentities("&#65020;");?>'><?php te("Rial");?>-&#65020;</option>

      <?php if ($settings['currency']=="Ft") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Forint");?>' value='<?php echo htmlentities("&#65020;");?>'><?php te("Forint");?>-Ft</option>
      
      <?php if ($settings['currency']=="&#8381;") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("Rubel");?>' value='<?php echo htmlentities("&#8381;");?>'><?php te("Rubel");?>-&#8381;</option>

      <?php if ($settings['currency']=="kr") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> title='<?php te("NOK");?>' value='<?php echo htmlentities("kr");?>'><?php te("NOK");?>-NOK</option>

    </select></td></tr>
    <tr><td class="tdt"><?php te("Interface Language")?></td><td>
    <select  name='lang'>
      <?php if ($settings['lang']=="en") $s="SELECTED"; else $s="" ?>
      <option <?php echo $s?> value='en'>en</option>
      <?php
      $tfiles=scandir("translations/");
      foreach ($tfiles as $f) {
		  $f=strtolower($f);
		  if (strstr($f,"txt") && (!strstr($f,"new")) && (!strstr($f,"missing"))) {
			  $bf=basename($f,".txt");
			  if ($settings['lang']=="$bf") $s="SELECTED"; else $s="" ;
			  echo "<option $s value='$bf'>$bf</option>\n";
		  }
      }
      ?>
    </select>
    </td>

    </tr>
    <tr><td class="tdt" title='<?php te("Timezone based on 3 alpha abbreviation. (e.g. MST, EST, UTC, etc)");?>'><?php te("Timezone (Abbreviation)");?>:</td><td>
    <select name='timezone'>
<?php
      $tz_array=file("php/timezones.txt");
      foreach ($tz_array as $tz) {
	$tz=trim($tz);
	if ($tz==$settings['timezone']) $s="SELECTED"; else $s="";
	echo "<option $s>$tz</option>\n";
      }
?>
</select>

</td></tr>

<!--
    <tr><td colspan=2><h3><?php te("Integration"); ?></h3></td></tr>

    <tr>
    <?php
      //SwitchMap Enabled (switchmapenable)
      $y="";$n="";
      if ($settings['switchmapenable']=="1") {$y="checked";$n="";}
      if ($settings['switchmapenable']=="0") {$n="checked";$y="";}
    ?>
      <td class='tdt' title='<?php te("Select yes if switchmap is installed on this server.");?>'><?php te("SwitchMap Integration");?>:</td>
      <td>
        <div >
          <input  validate='required:true' <?php echo $y?> class='radio' type=radio name='switchmapenable' value='1'><?php te("Yes");?>
          <input  class='radio' type=radio <?php echo $n?> name='switchmapenable' value='0'><?php te("No");?>
        </div>
      </td>
    </tr>
    <tr><td class="tdt" title='<?php te("Provide the full path to the switches directory within the SwitchMap directory.");?>'><?php te("Path To Switchmap");?>:</td><td><input  class='input2 ' size=20 type=text name='switchmapdir' value="<?php echo $settings['switchmapdir']?>"></td></tr>

-->
    <tr><td class="tdt"><?php te("Use LDAP");?>:</td> 
        <td><select  name='useldap'>
        <?php
        if ($settings['useldap']==1) $s1='SELECTED';
        else $s1='';
        ?>
        <option value=0><?php echo t('No')?></option>
        <option <?php echo $s1?> value=1><?php echo t('Yes')?></option>
        </select>
        <?php te("(for authentication only, except user admin which is local)");?></td></tr>

    <tr><td class="tdt"><?php te("LDAP Server");?>:</td> 
        <td><input  class='input2 ' size=20 type=text name='ldap_server' value="<?php echo $settings['ldap_server']?>"> <?php te("e.g.: ldap.mydomain.com");?></td></tr>
    <tr><td class="tdt"><?php te("LDAP DN");?>:</td> 
        <td><input  class='input2 ' size=20 type=text name='ldap_dn' value="<?php echo $settings['ldap_dn']?>"> <?php te("For user authentication.e.g.: ou=People,dc=mydomain,dc=com");?></td></tr>
    <tr><td class="tdt"><?php te("LDAP Search for users");?>:</td> 
        <td><input  class='input2 ' size=20 type=text name='ldap_getusers' value="<?php echo $settings['ldap_getusers']?>"> <?php te("e.g.: ou=People,dc=mydomain,dc=com");?></td></tr>
    <tr><td class="tdt"><?php te("LDAP User filter");?>:</td> 
        <td><input  class='input2 ' size=20 type=text name='ldap_getusers_filter' value="<?php echo $settings['ldap_getusers_filter']?>"> <?php te("e.g.: (&amp; (uid=*) (IsActive=TRUE))");?></td></tr>


<tr>
<td colspan=2>
<br>
<button type="submit"><img src="images/save.png" alt='<?php te("Save");?>'> <?php te("Save");?></button>
</td>
</tr>
</table>
<input type=hidden name='action' value='<?php echo $action ?>'>
</form>

</body>
</html>
