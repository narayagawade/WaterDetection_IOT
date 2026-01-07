<?php
header("Content-Type: application/json");
file_put_contents("log.txt", file_get_contents("php://input") . "\n", FILE_APPEND);

// Allow CORS for ESP32 (adjust domain in production)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST allowed"]);
    exit;
}

require_once '../db.php';  // Your PDO connection

// Get JSON input from ESP32
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

// Required fields
$required = ['device_id', 'auth_token', 'timestamp', 'pH', 'tds', 'turbidity', 'temperature'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        echo json_encode(["status" => "error", "message" => "Missing $field"]);
        exit;
    }
}

$device_id     = $input['device_id'];
$auth_token    = $input['auth_token'];
$timestamp     = $input['timestamp'];  // ISO format: 2025-11-13T10:00:00Z
$ph            = floatval($input['pH']);
$tds           = intval($input['tds']);
$turbidity     = floatval($input['turbidity']);
$temperature   = floatval($input['temperature']);

// Verify device and auth_token
$stmt = $pdo->prepare("SELECT d.*, m.id AS municipality_id, m.name AS muni_name, m.email AS muni_email 
                       FROM devices d 
                       JOIN municipalities m ON d.municipality_id = m.id 
                       WHERE d.device_id = ? AND d.auth_token = ? AND d.status = 'active'");
$stmt->execute([$device_id, $auth_token]);
$device = $stmt->fetch();

if (!$device) {
    echo json_encode(["status" => "error", "message" => "Invalid device_id or auth_token"]);
    exit;
}

$municipality_id = $device['municipality_id'];
$muni_name       = $device['muni_name'];
$muni_email      = $device['muni_email'];

// Save reading
$stmt = $pdo->prepare("INSERT INTO readings 
    (device_id, timestamp, ph, tds, turbidity, temperature) 
    VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$device_id, $timestamp, $ph, $tds, $turbidity, $temperature]);

// Update last_seen
$pdo->prepare("UPDATE devices SET last_seen = NOW() WHERE id = ?")->execute([$device['id']]);

// Threshold checks & create alerts
$alerts_created = [];
$alert_message = "";

if ($ph < 6.5 || $ph > 8.5) {
    $type = "Unsafe pH";
    $alert_message .= "pH: $ph (unsafe range)\n";
    $alerts_created[] = [$type, $ph];
}

if ($tds > 500) {
    $type = "High TDS";
    $alert_message .= "TDS: $tds ppm (high)\n";
    $alerts_created[] = [$type, $tds];
}

if ($turbidity > 5) {
    $type = "High Turbidity";
    $alert_message .= "Turbidity: $turbidity NTU (high)\n";
    $alerts_created[] = [$type, $turbidity];
}

// Insert alerts
foreach ($alerts_created as $al) {
    $stmt = $pdo->prepare("INSERT INTO alerts 
        (device_id, alert_type, value, message, status, timestamp) 
        VALUES (?, ?, ?, ?, 'new', NOW())");
    $stmt->execute([$device_id, $al[0], $al[1], "Alert: " . $al[0] . " - Value: " . $al[1]]);
}

// Prepare email content
$subject = "New Water Quality Reading - $muni_name";
$reading_time = date('d M Y, h:i A', strtotime($timestamp));
$body = "New sensor reading received at $reading_time\n\n";
$body .= "Location: " . $device['location_name'] . "\n\n";
$body .= "pH: $ph\n";
$body .= "TDS: $tds ppm\n";
$body .= "Turbidity: $turbidity NTU\n";
$body .= "Temperature: $temperature °C\n\n";

if ($alert_message) {
    $body .= "⚠️ ALERTS DETECTED:\n$alert_message\n";
    $body .= "Please take immediate action.\n";
} else {
    $body .= "✅ All parameters within safe range.\n";
}

$headers = "From: Water Monitor System <no-reply@yourdomain.com>";

// Send IMMEDIATE email to Municipality
mail($muni_email, $subject, $body, $headers);

// Delay 60 seconds, then send to Citizens
sleep(60);  // 1 minute delay

// Fetch all citizens linked to this municipality
$stmt = $pdo->prepare("SELECT email, name FROM users WHERE municipality_id = ?");
$stmt->execute([$municipality_id]);
$citizens = $stmt->fetchAll();

if ($citizens) {
    $citizen_subject = "Water Quality Update - $muni_name";
    $citizen_body = "Hello,\n\n";
    $citizen_body .= "Your local water quality was checked at $reading_time.\n\n";
    $citizen_body .= $body;  // Reuse same details
    $citizen_body .= "\nThank you for staying informed.\n";
    $citizen_body .= "— $muni_name Water Monitoring Team";

    foreach ($citizens as $citizen) {
        mail($citizen['email'], $citizen_subject, $citizen_body, $headers);
    }
}

// Final success response to ESP32
echo json_encode([
    "status" => "success",
    "message" => "Reading saved and notifications sent",
    "alerts" => count($alerts_created) > 0
]);
?>