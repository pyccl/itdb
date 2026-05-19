<?php
require_once('../init.php');
$itemtypeid = intval($_POST['itemtypeid']);
$sth = $dbh->query("SELECT hassoftware FROM itemtypes WHERE id = $itemtypeid");
$r = $sth->fetch(PDO::FETCH_ASSOC);
echo json_encode(array(
    'hassoftware' => $r ? $r['hassoftware'] : 0
));
?>
