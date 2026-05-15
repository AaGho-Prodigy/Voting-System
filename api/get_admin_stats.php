<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if election is over - hide stats from everyone including admin until election ends
if (!isElectionOver($pdo)) {
    echo json_encode(['error' => 'Statistics are not yet available. Election is still ongoing.', 'restricted' => true]);
    exit;
}

if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    // Get total users
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Get voted users
    $voted_users = $pdo->query("SELECT COUNT(*) FROM users WHERE has_voted = 1")->fetchColumn();
    
    // Calculate voting percentage
    $voting_percentage = $total_users > 0 ? round(($voted_users / $total_users) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'total_users' => (int)$total_users,
        'voted_users' => (int)$voted_users,
        'voting_percentage' => $voting_percentage
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>