<?php
require_once '../db.php';

$device_id = $_GET['device_id'];
$auth_token = $_GET['auth_token'];

// Verify auth
$stmt = $pdo->prepare("SELECT id FROM devices WHERE device_id = ? AND auth_token = ? AND trigger_flag = 1");
$stmt->execute([$device_id, $auth_token]);
if ($stmt->fetch()) {
    echo "trigger";
} else {
    echo "no";
}