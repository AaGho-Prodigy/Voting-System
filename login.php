<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/navigation.php';

if (isAdminLoggedIn()) {
    header('Location: admin/dashboard.php', true, 302);
    exit;
}

if (isset($_SESSION['user_id'])) {
    $target = ($_SESSION['user_type'] === 'candidate') ? 'candidate_dashboard.php' : 'dashboard.php';
    header('Location: ' . $target, true, 302);
    exit;
}

$error = '';
$success = '';
$step = 1;
$mode = 'password';

if (isset($_GET['registered'])) {
    $success = '✅ Registration successful! Please login with your credentials.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['action'])) {
        $type = $_POST['user_type'] ?? 'citizen';
        $id = $_POST['identifier'] ?? '';
        $pass = $_POST['password'] ?? '';

        if (empty($id) || empty($pass)) {
            $error = 'All fields required.';
        } elseif (!$pdo) {
            $error = 'Database unavailable.';
        } elseif ($type === 'admin') {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
                $stmt->execute([$id]);
                $admin = $stmt->fetch();
                if ($admin && password_verify($pass, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_logged_in'] = true;
                    session_write_close();
                    header('Location: admin/dashboard.php', true, 302);
                    exit;
                } else {
                    $error = 'Invalid admin credentials.';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE student_registration_number = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $error = 'Registration number not found.';
                } elseif (!password_verify($pass, $user['password'])) {
                    $error = 'Invalid password.';
                } else {
                    $otp = generateOTP();
                    if (sendMail($user['email'], 'OTP', "Your OTP: <b>$otp</b>")) {
                        $_SESSION['login_otp'] = [
                            'user_id' => $user['id'],
                            'otp' => (string)$otp,
                            'time' => time()
                        ];
                        $step = 2;
                        $mode = 'otp';
                        $success = 'OTP sent.';
                    } else {
                        $error = 'Failed to send OTP.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $otp_entered = trim($_POST['otp'] ?? '');
        
        if (!isset($_SESSION['login_otp'])) {
            $error = 'Session expired.';
        } elseif (time() - $_SESSION['login_otp']['time'] > 600) {
            $error = 'OTP expired.';
            unset($_SESSION['login_otp']);
        } elseif ($otp_entered !== $_SESSION['login_otp']['otp']) {
            $error = 'Invalid OTP.';
        } else {
            try {
                $user_id = $_SESSION['login_otp']['user_id'];
                unset($_SESSION['login_otp']);
                
                $stmt = $pdo->prepare("SELECT u.*, c.id as candidate_id FROM users u LEFT JOIN candidates c ON u.id = c.user_id WHERE u.id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_type'] = ($user['is_candidate'] == 1) ? 'candidate' : 'voter';
                    $_SESSION['has_voted'] = $user['has_voted'];
                    if ($user['is_candidate']) {
                        $_SESSION['candidate_id'] = $user['candidate_id'];
                    }
                    
                    session_write_close();
                    $target = ($_SESSION['user_type'] === 'candidate') ? 'candidate_dashboard.php' : 'dashboard.php';
                    header('Location: ' . $target, true, 302);
                    exit;
                } else {
                    $error = 'User not found.';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Online Voting System</title>
    <meta name="description" content="Login to the Online Voting System to cast your vote or manage your candidate profile.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
    <style>
        .hidden { display: none !important; }
        .show   { display: block !important; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🗳️ Online Voting System</h1>
            <p>Secure Access Portal</p>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>
        <main>
            <div class="form-container" id="login-form-container">
            <?php if ($error): ?>
                <div class="alert error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div id="pwd-form" class="<?php echo ($mode === 'password') ? 'show' : 'hidden'; ?>">
                <form method="POST" id="login-password-form">
                    <h2>Welcome Back</h2>
                    <p>Sign in to your account to continue</p>
                    <div class="form-group">
                        <label>Account Type</label>
                        <div class="radio-group">
                            <label><input type="radio" name="user_type" value="citizen" checked> 🧑 Citizen</label>
                            <label><input type="radio" name="user_type" value="admin"> 🛡️ Admin</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="identifier">Student Registration No.</label>
                        <input type="text" id="identifier" name="identifier" placeholder="Enter your ID or username" required>
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn btn-block" id="login-submit-btn">Login →</button>
                </form>
            </div>

            <div id="otp-form" class="<?php echo ($mode === 'otp' && $step === 2) ? 'show' : 'hidden'; ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="verify_otp">
                    <h2>Verify Identity</h2>
                    <div class="form-group">
                        <label>Enter 6-digit OTP:</label>
                        <input type="text" name="otp" maxlength="6" required autofocus>
                    </div>
                    <button type="submit" class="btn">Verify & Login</button>
                </form>
            </div>

            <div class="form-footer">
                <p>Don't have an account? <a href="register.php" id="go-register-link">Register here</a></p>
            </div>
            </div><!-- /.form-container -->
        </main>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Online Voting System. All rights reserved.</p>
    </footer>
</body>
</html>
