<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'citizen') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$location = $_SESSION['location'];
$municipality_id = $_SESSION['municipality_id'];  // May be null if not linked by token

// Fetch latest reading (most recent for this user's area)
$query = "
    SELECT r.*, d.location_name 
    FROM readings r
    JOIN devices d ON r.device_id = d.device_id
    WHERE d.municipality_id = ? 
    ORDER BY r.timestamp DESC LIMIT 1
";
if (!$municipality_id) {
    // Fallback: match by location_name if no municipality link
    $query = "
        SELECT r.*, d.location_name 
        FROM readings r
        JOIN devices d ON r.device_id = d.device_id
        WHERE d.location_name LIKE ? 
        ORDER BY r.timestamp DESC LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(["%$location%"]);
} else {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$municipality_id]);
}
$latest = $stmt->fetch();

// Fetch last cleaning date
$stmt = $pdo->prepare("SELECT last_cleaning_date FROM municipalities WHERE id = ?");
$stmt->execute([$municipality_id ?? 0]);
$clean_date = $stmt->fetchColumn();

// Fetch historical data for charts (last 30 readings)
$history_query = "
    SELECT timestamp, ph, tds, turbidity, temperature 
    FROM readings 
    WHERE device_id = (SELECT device_id FROM devices WHERE municipality_id = ? ORDER BY id LIMIT 1)
    ORDER BY timestamp DESC LIMIT 30
";
if (!$municipality_id) {
    $history_query = str_replace("municipality_id = ?", "location_name LIKE ?", $history_query);
    $stmt_hist = $pdo->prepare($history_query);
    $stmt_hist->execute(["%$location%"]);
} else {
    $stmt_hist = $pdo->prepare($history_query);
    $stmt_hist->execute([$municipality_id]);
}
$history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
$history = array_reverse($history);  // Oldest first for chart

// Handle Report Submission
$report_success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    $report_text = trim($_POST['report_text']);
    if (!empty($report_text)) {
        $stmt = $pdo->prepare("INSERT INTO citizen_reports (user_id, municipality_id, report_text, timestamp) 
                               VALUES (?, ?, ?, NOW())");
        if ($stmt->execute([$user_id, $municipality_id, $report_text])) {
            $report_success = "Your report has been submitted to the municipality. Thank you!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Water Quality Dashboard - <?php echo htmlspecialchars($location); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: linear-gradient(135deg, #e0f7fa, #ffffff); min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,123,255,0.1); }
        .gauge-card { background: linear-gradient(145deg, #ffffff, #f0f8ff); }
        .status-good { color: #28a745; }
        .status-fair { color: #ffc107; }
        .status-poor { color: #dc3545; }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-primary shadow-lg">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold"><i class="fas fa-tint me-2"></i> My Water Quality Dashboard</a>
            <div class="d-flex align-items-center text-white">
                <span class="me-3"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($location); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">

        <?php if ($report_success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $report_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Readings Gauges -->
        <?php if ($latest): ?>
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card gauge-card text-center p-4">
                    <h5 class="text-primary"><i class="fas fa-flask"></i> pH Level</h5>
                    <h2 class="<?php echo ($latest['ph'] >= 6.5 && $latest['ph'] <= 8.5) ? 'status-good' : 'status-poor'; ?>">
                        <?php echo number_format($latest['ph'], 2); ?>
                    </h2>
                    <p>Safe range: 6.5 - 8.5</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card gauge-card text-center p-4">
                    <h5 class="text-primary"><i class="fas fa-tint"></i> TDS (ppm)</h5>
                    <h2 class="<?php echo $latest['tds'] <= 500 ? 'status-good' : 'status-poor'; ?>">
                        <?php echo $latest['tds']; ?>
                    </h2>
                    <p>Safe: ≤500 ppm</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card gauge-card text-center p-4">
                    <h5 class="text-primary"><i class="fas fa-eye"></i> Turbidity (NTU)</h5>
                    <h2 class="<?php echo $latest['turbidity'] <= 5 ? 'status-good' : 'status-poor'; ?>">
                        <?php echo number_format($latest['turbidity'], 1); ?>
                    </h2>
                    <p>Safe: ≤5 NTU</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card gauge-card text-center p-4">
                    <h5 class="text-primary"><i class="fas fa-thermometer-half"></i> Temperature (°C)</h5>
                    <h2 class="text-info"><?php echo number_format($latest['temperature'], 1); ?></h2>
                    <p>Last updated: <?php echo date('d M Y, h:i A', strtotime($latest['timestamp'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Historical Charts -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-lg mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Water Quality Trends (Last 30 Readings)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="waterChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center">
            <h4>No data available yet for your area.</h4>
            <p>Readings will appear here once sensors are activated.</p>
        </div>
        <?php endif; ?>

        <!-- Tank Cleaning Date -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow-lg border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-broom me-2"></i> Water Tank Cleaning</h5>
                    </div>
                    <div class="card-body text-center">
                        <h3 class="text-success">
                            <?php echo $clean_date ? date('d F Y', strtotime($clean_date)) : 'Not updated yet'; ?>
                        </h3>
                        <p>Last cleaned by your municipality</p>
                    </div>
                </div>
            </div>

            <!-- Report Issue -->
            <div class="col-md-6">
                <div class="card shadow-lg border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Report Water Issue</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <textarea name="report_text" class="form-control" rows="4" placeholder="Describe any water quality concern (e.g., bad taste, color, smell)..." required></textarea>
                            </div>
                            <button type="submit" name="submit_report" class="btn btn-warning w-100">
                                <i class="fas fa-paper-plane me-2"></i> Send Report to Municipality
                            </button>
                        </form>
                        <small class="text-muted d-block mt-3">Your report will be reviewed by local officials.</small>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        <?php if ($latest && count($history) > 0): ?>
        const ctx = document.getElementById('waterChart').getContext('2d');
        const waterChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($r) { return date('d M h:i A', strtotime($r['timestamp'])); }, $history)); ?>,
                datasets: [
                    {
                        label: 'pH',
                        data: <?php echo json_encode(array_column($history, 'ph')); ?>,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0,123,255,0.1)',
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'TDS (ppm)',
                        data: <?php echo json_encode(array_column($history, 'tds')); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40,167,69,0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Turbidity (NTU)',
                        data: <?php echo json_encode(array_column($history, 'turbidity')); ?>,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255,193,7,0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Temperature (°C)',
                        data: <?php echo json_encode(array_column($history, 'temperature')); ?>,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220,53,69,0.1)',
                        tension: 0.4,
                        yAxisID: 'y2'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { position: 'left', title: { display: true, text: 'pH' } },
                    y1: { position: 'right', title: { display: true, text: 'TDS / Turbidity' }, grid: { drawOnChartArea: false } },
                    y2: { position: 'right', title: { display: true, text: 'Temperature (°C)' }, grid: { drawOnChartArea: false } }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index' }
                }
            }
        });
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>