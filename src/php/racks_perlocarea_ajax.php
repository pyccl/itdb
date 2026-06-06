<?php
if (!isset($initok)) {
    require_once __DIR__ . '/../init.php';
    exit("<b><font color=red>".t("ERROR : Do not run this script directly.")."</font></b>");
}

if(is_numeric($_POST['locationid'])) {
  $id=$_POST['locationid'];

  $sql="SELECT * FROM racks WHERE locationid=$id order by label,id";
  $sth=$dbh->query($sql);
  $racks=$sth->fetchAll(PDO::FETCH_ASSOC);

  if (count($racks))
    echo "      <option value=''>".t("Select")."</option>\n";
  else
    echo "      <option value=''>".t("No racks in location")."</option>\n";

  foreach ($racks  as $key=>$rack ) {
    $dbid=$rack['id'];
    $name=$rack['label'].",".$rack['usize']."U ". $rack['model'];
    $s="";
    //if (($rackid=="$dbid")) $s=" SELECTED ";
    echo "    <option $s value='$dbid'>$name</option>\n";
  }
}
?>
