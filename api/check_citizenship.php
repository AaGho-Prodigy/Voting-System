<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$student_registration_number = $_GET['student_registration_number'] ?? '';

if (empty($student_registration_number)) {
    echo json_encode(['exists' => false, 'reason' => null]);
    exit;
}

try {
    // Check if already registered
    $stmt = $pdo->prepare("SELECT id FROM users WHERE student_registration_number = ?");
    $stmt->execute([$student_registration_number]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['exists' => true, 'reason' => 'already_registered']);
        exit;
    }

    // Check if in allowed students list
    $checkAllowed = $pdo->prepare("SELECT id, used FROM allowed_students WHERE student_registration_number = ?");
    $checkAllowed->execute([$student_registration_number]);
    $allowedRow = $checkAllowed->fetch();

    if (!$allowedRow) {
        echo json_encode(['exists' => false, 'reason' => 'not_allowed']);
        exit;
    }

    if ($allowedRow['used']) {
        echo json_encode(['exists' => true, 'reason' => 'already_used']);
        exit;
    }

    // All checks passed - student registration number is available
    echo json_encode(['exists' => false, 'reason' => null]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'reason' => 'error']);
}
?>
