<?php
session_start();
require_once 'db.php';  // Your PDO database connection

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($action == 'login') {
        // ====== LOGIN ======
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Email and password are required.";
        } else {
            if ($role == 'admin') {
                $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['role'] = 'admin';
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['username'];
                    header("Location: dashboard_admin.php");
                    exit;
                }
            } elseif ($role == 'municipality') {
                $stmt = $pdo->prepare("SELECT * FROM municipalities WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['role'] = 'municipality';
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['muni_name'] = $user['name'];
                    $_SESSION['municipality_id'] = $user['id'];
                    header("Location: dashboard_muni.php");
                    exit;
                }
            } elseif ($role == 'citizen') {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['role'] = 'citizen';
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['location'] = $user['location_name'];
                    $_SESSION['municipality_id'] = $user['municipality_id'] ?? null;
                    header("Location: index.php");
                    exit;
                }
            }
            $error = "Invalid email or password.";
        }

    } elseif ($action == 'signup') {
        // ====== SIGNUP ======
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($role == 'municipality') {
            $muni_name = trim($_POST['muni_name'] ?? '');

            if (empty($muni_name) || empty($email) || empty($password)) {
                $error = "All fields are required for municipality signup.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM municipalities WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "This email is already registered.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO municipalities (name, email, password_hash, registration_token, token_used) 
                                          VALUES (?, ?, ?, NULL, 0)");
                    if ($stmt->execute([$muni_name, $email, $hash])) {
                        $success = "Municipality account created successfully!<br>
                                    <strong>You can now log in.</strong><br>
                                    <small class='text-muted'>Your citizen registration token will be assigned by the admin soon.</small>";
                    } else {
                        $error = "Signup failed. Please try again.";
                    }
                }
            }

        } elseif ($role == 'citizen') {
            $name = trim($_POST['name'] ?? '');
            $reg_token = trim($_POST['reg_token'] ?? '');
            $location = trim($_POST['location'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                $error = "Name, email, and password are required.";
            } elseif (empty($reg_token) && empty($location)) {
                $error = "Please enter either a registration token or your location.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email already registered.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $municipality_id = null;
                    $final_location = $location;
                    $linked_by_token = 0;

                    if (!empty($reg_token)) {
                        $stmt = $pdo->prepare("SELECT id, name FROM municipalities WHERE registration_token = ? AND token_used = 0");
                        $stmt->execute([$reg_token]);
                        $muni = $stmt->fetch();

                        if ($muni) {
                            $municipality_id = $muni['id'];
                            $final_location = $muni['name'] . " Area";
                            $linked_by_token = 1;
                            $pdo->prepare("UPDATE municipalities SET token_used = 1 WHERE id = ?")->execute([$muni['id']]);
                        } else {
                            $error = "Invalid or already used registration token.";
                        }
                    }

                    if (!$error) {
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, location_name, municipality_id, linked_by_token) 
                                              VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt->execute([$name, $email, $hash, $final_location, $municipality_id, $linked_by_token])) {
                            $success = "Citizen account created successfully! You can now log in.";
                        } else {
                            $error = "Signup failed.";
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Water Quality Monitoring - Login / Signup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4 fw-bold">Water Quality Monitor</h3>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success text-center"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <ul class="nav nav-pills nav-justified mb-4" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#login">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#signup">Signup</a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- LOGIN TAB -->
                            <div id="login" class="tab-pane fade show active">
                                <form method="POST">
                                    <input type="hidden" name="action" value="login">
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <select name="role" class="form-select" required>
                                            <option value="citizen">Citizen</option>
                                            <option value="municipality">Municipality Official</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email / Username</label>
                                        <input type="text" name="email" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Login</button>
                                </form>
                            </div>

                            <!-- SIGNUP TAB -->
                            <div id="signup" class="tab-pane fade">
                                <form method="POST" novalidate>
                                    <input type="hidden" name="action" value="signup">

                                    <div class="mb-3">
                                        <label class="form-label">I am a</label>
                                        <select name="role" id="signup_role" class="form-select" required onchange="toggleFields()">
                                            <option value="citizen">Citizen</option>
                                            <option value="municipality">Municipality Official</option>
                                        </select>
                                    </div>

                                    <!-- Common Email & Password -->
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>

                                    <!-- Municipality Specific -->
                                    <div id="muni_fields" class="d-none">
                                        <div class="mb-3">
                                            <label class="form-label">Municipality Name</label>
                                            <input type="text" name="muni_name" id="muni_name" class="form-control" placeholder="e.g., Vengurla Municipal Council">
                                        </div>
                                    </div>

                                    <!-- Citizen Specific -->
                                    <div id="citizen_fields">
                                        <div class="mb-3">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" name="name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Municipality Registration Token <span class="text-muted">(Optional)</span></label>
                                            <input type="text" name="reg_token" id="reg_token" class="form-control" placeholder="e.g., MUNC-ABCD1234">
                                            <small class="text-muted">Get this from your municipality official for correct local data.</small>
                                        </div>
                                        <div id="manual_location" class="mb-3">
                                            <label class="form-label">Your Area / Location <span class="text-danger">(Required if no token)</span></label>
                                            <input type="text" name="location" id="location_input" class="form-control" placeholder="e.g., Vengurla Ward 1">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success w-100 mt-3">Create Account</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFields() {
            const role = document.getElementById('signup_role').value;
            const muniFields = document.getElementById('muni_fields');
            const citizenFields = document.getElementById('citizen_fields');
            const muniName = document.getElementById('muni_name');
            const locationInput = document.getElementById('location_input');
            const tokenInput = document.getElementById('reg_token');

            if (role === 'municipality') {
                muniFields.classList.remove('d-none');
                citizenFields.classList.add('d-none');
                muniName.required = true;
            } else {
                muniFields.classList.add('d-none');
                citizenFields.classList.remove('d-none');
                if (muniName) muniName.required = false;
            }
            toggleLocation();
        }

        function toggleLocation() {
            const tokenValue = document.getElementById('reg_token')?.value.trim() || '';
            const manualLoc = document.getElementById('manual_location');
            const locationInput = document.getElementById('location_input');

            if (tokenValue.length > 0) {
                manualLoc.style.display = 'none';
                locationInput.required = false;
            } else {
                manualLoc.style.display = 'block';
                locationInput.required = true;
            }
        }

        // Initial setup
        toggleFields();
        document.getElementById('reg_token')?.addEventListener('input', toggleLocation);
    </script>
</body>
</html>