<?php
require_once '../db.php';

$device_id = $_GET['device_id'];
$auth_token = $_GET['auth_token'];

// Verify and reset
$pdo->prepare("UPDATE devices SET trigger_flag = 0 WHERE device_id = ? AND auth_token = ?")->execute([$device_id, $auth_token]);
echo "reset";