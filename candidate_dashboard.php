<?php
require_once 'includes/auth.php';
require_once 'includes/navigation.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidate') {
    redirect('login.php');
}

$stmt = $pdo->prepare("SELECT c.* FROM candidates c WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$candidate = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Candidate Dashboard</h1>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>

        <main class="candidate-dashboard">
            <div class="candidate-welcome">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                <p>Manage your candidacy and track your progress.</p>
            </div>
            
            <?php if ($candidate): ?>
                <div class="candidate-info">
                    <h3 class="candidate-position"><?php echo htmlspecialchars($candidate['position']); ?></h3>
                    <p><strong>Status:</strong> 
                        <span class="status-badge <?php echo $candidate['approved'] == 1 ? 'status-approved' : 'status-pending'; ?>">
                            <?php echo $candidate['approved'] == 1 ? 'Approved' : 'Pending Approval'; ?>
                        </span>
                    </p>
                    
                    <div class="candidate-details">
                        <div class="detail-section">
                            <h4>About Me</h4>
                            <p><?php echo nl2br(htmlspecialchars($candidate['bio'] ?: 'No biography provided.')); ?></p>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Campaign Goals</h4>
                            <p><?php echo nl2br(htmlspecialchars($candidate['goals'])); ?></p>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Key Targets</h4>
                            <p><?php echo nl2br(htmlspecialchars($candidate['targets'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <a href="candidate_edit.php" class="btn">Edit Profile</a>
                    
                    <?php if ($user['has_voted']): ?>
                        <div class="alert success" style="margin: 15px 0;">
                            <p>You have already cast your vote. Thank you for participating!</p>
                        </div>
                    <?php else: ?>
                        <div class="alert info" style="margin: 15px 0;">
                            <p>You can also participate as a voter. Cast your vote now!</p>
                        </div>
                        <a href="vote.php" class="btn">Cast Your Vote</a>
                    <?php endif; ?>
                    
                    <a href="results.php" class="btn btn-secondary">View Election Results</a>
                </div>
            <?php else: ?>
                <div class="alert info">
                    <p>Your candidate profile was not found.</p>
                    <p>If you have already submitted an application but don't see it here, please contact an administrator.</p>
                    <p>If you have not yet created a profile, you can <a href="register_candidate.php">create your candidacy now</a>.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>