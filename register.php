<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/navigation.php';
session_start();

$error = '';
$success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == 1) {
        $student_registration_number = sanitizeInput($_POST['student_registration_number']);
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($student_registration_number) || empty($name) || empty($email) || empty($password)) {
            $error = 'All required fields must be filled out.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE student_registration_number = ?");
            $stmt->execute([$student_registration_number]);

            if ($stmt->rowCount() > 0) {
                $error = 'Student registration number already registered.';
            } else {
                try {
                    $checkAllowed = $pdo->prepare("SELECT id, used FROM allowed_students WHERE student_registration_number = ?");
                    $checkAllowed->execute([$student_registration_number]);
                    $allowedRow = $checkAllowed->fetch();
                    if (!$allowedRow) {
                        $error = 'This student registration number is not permitted to register.';
                    } elseif ($allowedRow['used']) {
                        $error = 'This student registration number has already been used to register.';
                    }
                } catch (Exception $e) {
                    $error = 'Registration temporarily unavailable.';
                }

                if (!empty($error)) {
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
                    $stmt->execute([$email]);
                    if ($stmt->rowCount() > 0) {
                        $error = 'Email already registered.';
                    } else {
                        $otp = generateOTP();
                        $subject = "Your OTP for Registration";
                        $message = "Your OTP is: <strong>$otp</strong>. It expires in 10 minutes.";
                        if (sendMail($email, $subject, $message)) {
                            $_SESSION['reg_data'] = [
                                'student_registration_number' => $student_registration_number,
                                'name' => $name,
                                'email' => $email,
                                'password' => password_hash($password, PASSWORD_DEFAULT),
                                'otp' => $otp,
                                'otp_time' => time()
                            ];
                            $step = 2;
                            $success = 'OTP sent to your email. Please enter it below.';
                        } else {
                            $error = 'Failed to send OTP. Please try again.';
                        }
                    }
                }
            }
        }
    }

    if (isset($_POST['step']) && $_POST['step'] == 2) {
        $entered_otp = sanitizeInput($_POST['otp']);
        if (!isset($_SESSION['reg_data'])) {
            $error = 'Session expired. Please start registration again.';
        } elseif (time() - $_SESSION['reg_data']['otp_time'] > 600) {
            $error = 'OTP expired. Please try again.';
            unset($_SESSION['reg_data']);
        } elseif ($entered_otp != $_SESSION['reg_data']['otp']) {
            $error = 'Invalid OTP.';
        } else {
            $data = $_SESSION['reg_data'];
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE student_registration_number = ? OR LOWER(email) = LOWER(?)");
                $stmt->execute([$data['student_registration_number'], $data['email']]);
                if ($stmt->rowCount() > 0) {
                    $error = 'Student registration number or email already registered.';
                } else {
                    $pdo->beginTransaction();
                    $ins = $pdo->prepare("INSERT INTO users (citizenship_number, student_registration_number, name, email, password, is_candidate) VALUES (?, ?, ?, ?, ?, 0)");
                    $ins->execute([$data['student_registration_number'], $data['student_registration_number'], $data['name'], $data['email'], $data['password']]);
                    $newUserId = $pdo->lastInsertId();
                    $upd = $pdo->prepare("UPDATE allowed_students SET used = 1, used_by = ? WHERE student_registration_number = ? AND used = 0");
                    $upd->execute([$newUserId, $data['student_registration_number']]);
                    $pdo->commit();
                    unset($_SESSION['reg_data']);
                    redirect('login.php?registered=1');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Online Voting System</title>
    <meta name="description" content="Create your account and join the Online Voting System today.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🗳️ Online Voting System</h1>
            <p>Create Your Account</p>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>

        <main>
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                <form method="POST" action="register.php">
                    <input type="hidden" name="step" value="1">
                    <h2>Create Your Account</h2>
                    <p style="text-align: center; color: var(--gray); margin-bottom: 1rem;">Sign up to vote</p>

                    <div class="form-group">
                        <label for="student_registration_number">Student Registration Number: *</label>
                        <input type="text" id="student_registration_number" name="student_registration_number" placeholder="Enter your student registration number" required>
                    </div>

                    <div class="form-group">
                        <label for="name">Full Name: *</label>
                        <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email: *</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                        <div id="email_status" class="small" style="color: var(--danger); display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password: *</label>
                        <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password: *</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                    </div>

                    <button type="submit" class="btn" style="width: 100%;">Send OTP</button>
                </form>
                <?php elseif ($step == 2): ?>
                <form method="POST" action="register.php">
                    <input type="hidden" name="step" value="2">
                    <h2>Enter OTP</h2>

                    <div class="form-group">
                        <label for="otp">Enter OTP:</label>
                        <input type="text" id="otp" name="otp" required maxlength="6">
                    </div>

                    <button type="submit" class="btn">Verify & Register</button>
                </form>
                <?php endif; ?>

                <p class="form-footer">
                    Already registered? <a href="login.php">Login here</a>
                </p>
            </div>
        </main>
    </div>
    <script src="js/validation.js"></script>
</body>
</html>