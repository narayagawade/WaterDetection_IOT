<?php
require_once '../db.php';

$muni_id = $_GET['muni_id'];
$key = $_GET['key'];
if ($key !== 'secretkey') die("Unauthorized");  // Change to strong key

// Set trigger flag in DB (add column to devices: trigger_flag TINYINT DEFAULT 0)
$pdo->prepare("UPDATE devices SET trigger_flag = 1 WHERE municipality_id = ?")->execute([$muni_id]);

// Optional: Send immediate SMS/email to municipality "Trigger sent!"
echo "Trigger activated";