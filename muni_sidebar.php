<?php
// This file is included in dashboard_muni.php
// Fetch current muni data again if needed
$stmt = $pdo->prepare("SELECT registration_token, last_cleaning_date FROM municipalities WHERE id = ?");
$stmt->execute([$_SESSION['municipality_id']]);
$side_muni = $stmt->fetch();
?>

<div class="p-4">

    <!-- Token Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white text-center">
            <h6 class="mb-0"><i class="fas fa-key me-2"></i> Registration Token</h6>
        </div>
        <div class="card-body text-center">
            <?php if ($side_muni['registration_token']): ?>
                <div class="token-text text-primary fw-bold mb-3">
                    <?php echo htmlspecialchars($side_muni['registration_token']); ?>
                </div>
                <button class="btn btn-outline-primary btn-sm" onclick="copyToken()">
                    <i class="fas fa-copy me-2"></i> Copy
                </button>
            <?php else: ?>
                <p class="text-warning small">Not assigned yet</p>
                <p class="text-muted small">Contact admin</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cleaning Date Card -->
    <div class="card">
        <div class="card-header bg-success text-white text-center">
            <h6 class="mb-0"><i class="fas fa-broom me-2"></i> Tank Cleaning</h6>
        </div>
        <div class="card-body">
            <p class="text-center fw-bold fs-4 text-success">
                <?php echo $side_muni['last_cleaning_date'] ? date('d M', strtotime($side_muni['last_cleaning_date'])) : '--'; ?>
            </p>
            <p class="text-center text-white small">Last cleaned</p>

            <form method="POST" action="dashboard_muni.php">
                <div class="mb-3">
                    <input type="date" name="cleaning_date" class="form-control form-control-sm" required 
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <button type="submit" name="update_cleaning" class="btn btn-light btn-sm w-100">
                    <i class="fas fa-check me-2"></i> Update
                </button>
            </form>
        </div>
    </div>

</div>