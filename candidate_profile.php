<?php
require_once 'includes/config.php';
require_once 'includes/navigation.php';

$candidate_id = (int)$_GET['id'] ?? 0;

if (!$candidate_id) {
    header('Location: vote.php');
    exit;
}

$stmt = $pdo->prepare("SELECT c.*, u.email FROM candidates c JOIN users u ON c.user_id = u.id WHERE c.id = ? AND c.approved = 1");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    header('Location: vote.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($candidate['name']); ?> - Candidate Profile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Online Voting System</h1>
            <?php echo function_exists('getElectionStatusNotification') ? getElectionStatusNotification() : ''; ?>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>

        <main>
            <a href="vote.php" class="back-btn">← Back to Voting</a>

            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-photo">
                        <?php echo strtoupper(substr($candidate['name'], 0, 1)); ?>
                    </div>
                    <h1><?php echo htmlspecialchars($candidate['name']); ?></h1>
                    <h2><?php echo htmlspecialchars($candidate['position']); ?></h2>
                </div>

                <div class="profile-section">
                    <h3>About</h3>
                    <p><?php echo nl2br(htmlspecialchars($candidate['bio'])); ?></p>
                </div>

                <div class="profile-section">
                    <h3>Goals</h3>
                    <p><?php echo nl2br(htmlspecialchars($candidate['goals'])); ?></p>
                </div>

                <div class="profile-section">
                    <h3>Targets</h3>
                    <p><?php echo nl2br(htmlspecialchars($candidate['targets'])); ?></p>
                </div>

                <div class="profile-section">
                    <h3>Contact Information</h3>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>