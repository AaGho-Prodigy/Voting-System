<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_type'] === 'candidate') {
    header('Location: candidate_dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if (!$pdo) {
    die('Database error.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE user_id = ? AND approved = 1");
    $stmt->execute([$user_id]);
    $candidate = $stmt->fetch();

    if (!$candidate) {
        die('You do not have an approved candidate profile yet.');
    }

    $stmt = $pdo->prepare("UPDATE users SET is_candidate = 1 WHERE id = ?");
    $stmt->execute([$user_id]);

    $_SESSION['user_type'] = 'candidate';
    $_SESSION['candidate_id'] = $candidate['id'];
    session_write_close();

    header('Location: candidate_dashboard.php');
    exit;
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
