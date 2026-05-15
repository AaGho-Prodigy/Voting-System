<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$email = $_GET['email'] ?? '';
$email = trim($email);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $exists = $stmt->rowCount() > 0;
    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Query error']);
}
