<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if (!isElectionOver($pdo)) {
    echo json_encode(['error' => 'Results are not yet available. Election is still ongoing.', 'restricted' => true]);
    exit;
}

if (!$pdo) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    $position = $_GET['position'] ?? '';
    $status = $_GET['status'] ?? '1';
    $sql = "SELECT * FROM candidates WHERE approved = 1";
    $params = [];
    
    if ($position) {
        $sql .= " AND position = ?";
        $params[] = $position;
    }
    
    if ($status !== 'all') {
        $sql .= " AND approved = ?";
        $params[] = (int)$status;
    }
    
    $sql .= " ORDER BY votes DESC, name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
        'success' => true,
        'total_votes' => $total_votes,
        'candidates' => $candidates,
        'winner' => $winner,
        'filters' => ['position' => $position, 'status' => $status]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>