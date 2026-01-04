<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch temperature
$temperature = "N/A";
$weather_api = @file_get_contents("https://api.open-meteo.com/v1/forecast?latitude=16.35&longitude=73.67&current_weather=true");
if ($weather_api) {
    $weather = json_decode($weather_api, true);
    if (isset($weather['current_weather']['temperature'])) {
        $temperature = $weather['current_weather']['temperature'] . "°C";
    }
}

$success = '';

// Handle Send Token
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_token'])) {
    $muni_id = $_POST['muni_id'];
    $custom_token = trim($_POST['final_token']);

    if (empty($custom_token)) {
        $success = "Please generate a token first.";
    } else {
        $stmt = $pdo->prepare("UPDATE municipalities SET registration_token = ?, token_used = 0 WHERE id = ?");
        if ($stmt->execute([$custom_token, $muni_id])) {
            $success = "Token <strong>$custom_token</strong> successfully sent to the municipality!";
        } else {
            $success = "Error saving token.";
        }
    }
}

// Fetch all municipalities
$stmt = $pdo->query("SELECT id, name, email, registration_token FROM municipalities ORDER BY name");
$municipalities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Water Quality Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .token-card { border: 3px dashed #0d6efd; background: #f8f9fa; }
        .big-token { font-size: 2.5rem; letter-spacing: 4px; }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary shadow-lg">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold fs-4">
                <i class="fas fa-tint me-2"></i> Water Quality Admin Panel
            </a>
            <div class="d-flex align-items-center text-white">
                <span class="me-4"><i class="fas fa-user-shield me-2"></i> Welcome, <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></span>
                <span class="me-4"><i class="fas fa-calendar-alt me-2"></i> <span id="live-time"></span></span>
                <span class="me-4"><i class="fas fa-thermometer-half me-2"></i> <?php echo $temperature; ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">

                <?php if ($success): ?>
                    <div class="alert <?php echo strpos($success, 'successfully') ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Big Token Generation Card -->
                <div class="card shadow-lg mb-5 token-card">
                    <div class="card-header bg-primary text-white text-center">
                        <h3 class="mb-0"><i class="fas fa-key me-2"></i> Generate & Send Registration Token</h3>
                    </div>
                    <div class="card-body text-center py-5">
                        <p class="lead text-muted">Select a municipality, generate a token, then send it.</p>

                        <div class="row align-items-center justify-content-center">
                            <div class="col-md-4">
                                <select id="muni_select" class="form-select form-select-lg">
                                    <option value="">-- Select Municipality --</option>
                                    <?php foreach ($municipalities as $muni): ?>
                                        <option value="<?php echo $muni['id']; ?>">
                                            <?php echo htmlspecialchars($muni['name']); ?> (<?php echo htmlspecialchars($muni['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4 mt-3 mt-md-0">
                                <div class="input-group input-group-lg">
                                    <input type="text" id="generated_token" class="form-control text-center big-token" placeholder="Click Generate" readonly>
                                    <button class="btn btn-warning" type="button" onclick="generateToken()">
                                        <i class="fas fa-sync-alt me-2"></i> Generate Token
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-3 mt-3 mt-md-0">
                                <form method="POST" id="send_form">
                                    <input type="hidden" name="muni_id" id="send_muni_id">
                                    <input type="hidden" name="final_token" id="send_final_token">
                                    <button type="submit" name="send_token" class="btn btn-success btn-lg w-100" disabled>
                                        <i class="fas fa-paper-plane me-2"></i> Send Token
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-4">
                            <small class="text-muted">Token will be visible to the municipality after sending.</small>
                        </div>
                    </div>
                </div>

                <!-- Municipalities Table -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-city me-2"></i> All Registered Municipalities</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($municipalities)): ?>
                            <p class="text-center text-muted py-5">No municipalities registered yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Municipality Name</th>
                                            <th>Email</th>
                                            <th>Current Token</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($municipalities as $index => $muni): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><strong><?php echo htmlspecialchars($muni['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($muni['email']); ?></td>
                                                <td>
                                                    <?php if ($muni['registration_token']): ?>
                                                        <span class="badge bg-success fs-6 p-2">
                                                            <?php echo htmlspecialchars($muni['registration_token']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not sent yet</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($muni['registration_token']): ?>
                                                        <span class="badge bg-primary">Sent</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live Time
        function updateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            document.getElementById('live-time').textContent = now.toLocaleDateString('en-IN', options);
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Generate Random Token
        function generateToken() {
            const token = 'MUNC-' + Math.random().toString(36).substring(2, 10).toUpperCase();
            document.getElementById('generated_token').value = token;
            document.getElementById('send_final_token').value = token;

            const muniId = document.getElementById('muni_select').value;
            document.getElementById('send_muni_id').value = muniId;

            const sendBtn = document.querySelector('button[name="send_token"]');
            sendBtn.disabled = !muniId;
        }

        // Enable Send button only when municipality selected
        document.getElementById('muni_select').addEventListener('change', function() {
            const sendBtn = document.querySelector('button[name="send_token"]');
            sendBtn.disabled = this.value === '';
            if (this.value === '') {
                document.getElementById('generated_token').value = '';
                document.getElementById('send_final_token').value = '';
            }
        });
    </script>
</body>
</html>