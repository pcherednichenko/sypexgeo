<?php
require_once 'GeoTwo.php';

ini_set('memory_limit', '10G');
$Geo = new GeoTwo();
$Geo->needTest = false;
//$Geo->clearTable(""); info
$Geo->ipSort();
$Geo->writeInBd();
?>
