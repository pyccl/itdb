<?php
require_once __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');

$itemtypeid = (int)$_POST['itemtypeid'];
$sth = $dbh->prepare("SELECT hassoftware FROM itemtypes WHERE id = ?");
$sth->execute([$itemtypeid]);
$row = $sth->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'hassoftware' => $row ? (int)$row['hassoftware'] : 0
]);
