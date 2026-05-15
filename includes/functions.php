<?php
function sanitizeInput($data) {
    if (empty($data)) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

function hasUserVoted($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT has_voted FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result && $result['has_voted'] == 1;
    } catch (Exception $e) {
        return false;
    }
}

function getUserInfo($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

function sendMail($to, $subject, $message) {
    require_once __DIR__ . '/email_config.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: " . $mail->ErrorInfo);
        return false;
    }
}

function generateOTP() {
    return (string)rand(100000, 999999);
}

function isElectionOver($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'election_end'");
        $stmt->execute();
        $election_end = $stmt->fetchColumn();
        if (!$election_end) {
            return false;
        }
        $endTime = new DateTime($election_end);
        $now = new DateTime();
        return $now >= $endTime;
    } catch (Exception $e) {
        return false;
    }
}
?>