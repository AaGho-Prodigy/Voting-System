<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isAdmin && !isElectionOver($pdo)) {
    echo json_encode(['error' => 'Results are not yet available. Election is still ongoing.', 'restricted' => true]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE approved = 1 ORDER BY votes DESC");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_votes = 0;
    foreach ($candidates as $candidate) {
        $total_votes += (int)$candidate['votes'];
    }
    
    $winner = null;
    $max_votes = 0;
    foreach ($candidates as &$candidate) {
        $votes = (int)$candidate['votes'];
        $candidate['percentage'] = $total_votes > 0 ? round(($votes / $total_votes) * 100, 2) : 0;
        if ($votes > $max_votes) {
            $max_votes = $votes;
            $winner = $candidate;
        }
    }
    
    echo json_encode([
        'total_votes' => $total_votes,
        'candidates' => $candidates,
        'winner' => $winner
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>