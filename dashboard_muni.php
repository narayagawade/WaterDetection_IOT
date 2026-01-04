<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'municipality') {
    header("Location: login.php");
    exit;
}

$muni_id = $_SESSION['municipality_id'];
$muni_name = $_SESSION['muni_name'];

// === Handle Trigger Sensors Button ===
$trigger_success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['trigger_sensors'])) {
    // Change this URL to your actual domain and secure key
    $trigger_url = "http://localhost/water-project/api/trigger.php?muni_id=" . $muni_id . "&key=your_strong_secret_key_here";

    // Fire and forget request (with timeout)
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]);
    $result = @file_get_contents($trigger_url, false, $context);

    $trigger_success = "Sensor reading triggered successfully! Fresh data will arrive shortly.";
}

// Fetch municipality data
$stmt = $pdo->prepare("SELECT name, registration_token, last_cleaning_date FROM municipalities WHERE id = ?");
$stmt->execute([$muni_id]);
$muni = $stmt->fetch();

// Update Cleaning Date
$clean_success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_cleaning'])) {
    $clean_date = $_POST['cleaning_date'];
    if (!empty($clean_date)) {
        // Ensure column exists
        $pdo->exec("ALTER TABLE municipalities ADD COLUMN IF NOT EXISTS last_cleaning_date DATE NULL");
        
        $stmt = $pdo->prepare("UPDATE municipalities SET last_cleaning_date = ? WHERE id = ?");
        if ($stmt->execute([$clean_date, $muni_id])) {
            $clean_success = "Tank cleaning date updated successfully!";
            $muni['last_cleaning_date'] = $clean_date;
        }
    }
}

// Fetch recent alerts
$stmt = $pdo->prepare("
    SELECT alert_type, value, message, timestamp 
    FROM alerts 
    WHERE device_id IN (SELECT device_id FROM devices WHERE municipality_id = ?) 
    ORDER BY timestamp DESC LIMIT 10
");
$stmt->execute([$muni_id]);
$alerts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($muni['name']); ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            width: 300px;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 100px;
            box-shadow: 8px 0 20px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        .sidebar .card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .sidebar .card-header {
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar .btn-light {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: white;
        }
        
        .sidebar .btn-light:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .token-text {
            font-family: 'Courier New', monospace;
            font-size: 1.4rem;
            letter-spacing: 5px;
            color: #a5d8ff;
            font-weight: bold;
        }
        
        .main-content {
            margin-left: 300px;
            padding: 30px;
            min-height: 100vh;
        }
        
        @media (max-width: 992px) {
            .sidebar { width: 100%; height: auto; position: relative; padding-top: 20px; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Desktop Sidebar -->
    <div class="d-none d-lg-block sidebar">
        <?php include 'muni_sidebar.php'; ?>
    </div>

    <!-- Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas">
        <div class="offcanvas-header" style="background: #2c3e50; color: white;">
            <h5 class="offcanvas-title"><i class="fas fa-city me-2"></i><?php echo htmlspecialchars($muni['name']); ?></h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0" style="background: #1a252f;">
            <?php include 'muni_sidebar.php'; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Mobile Navbar -->
        <nav class="navbar navbar-dark mb-4 d-lg-none rounded shadow" style="background: #2c3e50;">
            <div class="container-fluid">
                <button class="btn btn-light" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="navbar-brand mb-0 h5"><?php echo htmlspecialchars($muni['name']); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </nav>

        <!-- Desktop Header -->
        <div class="d-none d-lg-flex justify-content-between align-items-center bg-white shadow p-4 rounded mb-4">
            <h3 class="mb-0"><i class="fas fa-tachometer-alt text-primary me-3"></i> Dashboard</h3>
            <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>

        <!-- Success Messages -->
        <?php if ($trigger_success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <?php echo $trigger_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($clean_success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <?php echo $clean_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Trigger Sensor Reading Card -->
        <div class="card shadow-lg mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-satellite-dish me-2"></i> Manual Sensor Reading</h5>
            </div>
            <div class="card-body text-center py-5">
                <p class="lead text-muted mb-4">
                    Click below to immediately activate your sensors and get fresh water quality data.
                </p>
                <form method="POST">
                    <button type="submit" name="trigger_sensors" class="btn btn-info btn-lg px-5 shadow">
                        <i class="fas fa-bolt me-3"></i> Start Reading Now
                    </button>
                </form>
                <small class="text-muted d-block mt-4">
                    Data will be recorded and sent to citizens after 1 minute delay.
                </small>
            </div>
        </div>

        <!-- Alerts Card -->
        <div class="card shadow-lg border-0 mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Recent Water Quality Alerts</h5>
            </div>
            <div class="card-body bg-white">
                <?php if (empty($alerts)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                        <h5 class="text-success">All Clear!</h5>
                        <p class="text-muted">No recent alerts. Water quality is safe and monitored.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($alerts as $alert): ?>
                            <div class="list-group-item border-start border-danger border-5 shadow-sm mb-3 rounded">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 text-danger fw-bold"><?php echo htmlspecialchars($alert['alert_type']); ?></h6>
                                        <p class="mb-1"><?php echo htmlspecialchars($alert['message']); ?></p>
                                        <small class="text-muted">Value: <?php echo htmlspecialchars($alert['value']); ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo date('d M, h:i A', strtotime($alert['timestamp'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Future Charts Placeholder -->
        <div class="card shadow-lg border-0 text-center">
            <div class="card-body py-5">
                <i class="fas fa-chart-line fa-4x text-primary mb-4 opacity-75"></i>
                <h5 class="text-primary">Live Sensor Charts</h5>
                <p class="text-muted">Real-time pH, TDS, Turbidity & Temperature graphs coming soon...</p>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToken() {
            const token = "<?php echo addslashes($muni['registration_token'] ?? ''); ?>";
            if (token) {
                navigator.clipboard.writeText(token).then(() => {
                    alert("Token copied to clipboard!");
                }).catch(() => {
                    prompt("Copy this token:", token);
                });
            } else {
                alert("No registration token assigned yet.");
            }
        }
    </script>
</body>
</html>