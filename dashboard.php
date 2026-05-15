<?php
require_once 'includes/auth.php';
require_once 'includes/navigation.php';


$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$candidate = null;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $candidate = $stmt->fetch();
    } catch (Exception $e) {
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Online Voting System</title>
    <meta name="description" content="Your personal voter dashboard for the Online Voting System.">
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
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>
        
        <main>
            <div class="welcome-section">
                <h2>Welcome, <?php echo $_SESSION['user_name']; ?>!</h2>
                
                <?php if ($user['has_voted']): ?>
                    <div class="alert success">
                        <p>You have already cast your vote. Thank you for participating!</p>
                    </div>
                    <a href="results.php" class="btn">View Results</a>
                <?php else: ?>
                    <div class="alert info">
                        <p>You haven't voted yet. Click the button below to cast your vote.</p>
                    </div>
                    <a href="vote.php" class="btn">Cast Your Vote</a>
                <?php endif; ?>
            </div>
            
            <div class="user-info">
                <h3>Your Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Registration Number:</strong>
                        <span><?php echo $user['student_registration_number']; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Name:</strong>
                        <span><?php echo $user['name']; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Email:</strong>
                        <span><?php echo $user['email']; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Voting Status:</strong>
                        <span><?php echo $user['has_voted'] ? 'Voted' : 'Not Voted'; ?></span>
                    </div>
                </div>
            </div>

            <div class="participation-section">
                <h3>🎯 Participate as a Candidate</h3>
                
                <?php if ($candidate): ?>
                    <?php if ($candidate['approved'] == 1): ?>
                        <p class="status approved"><strong>✓ Status:</strong> You have an approved candidate profile!</p>
                        <p style="margin: 15px 0;">
                            Would you like to activate your candidacy and participate in the election?
                        </p>
                        <a href="become_candidate.php" class="btn btn-success" style="display: inline-block;">
                            ✓ Activate My Candidacy
                        </a>
                        <a href="candidate_profile.php?id=<?php echo $candidate['id']; ?>" class="btn btn-secondary" style="display: inline-block; margin-left: 10px;">
                            View My Profile
                        </a>
                    <?php else: ?>
                        <p class="status pending"><strong>⏳ Status:</strong> Your candidate application is pending approval</p>
                        <p>Our administrators are reviewing your application. You'll be notified when a decision is made.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Want to run for office and contest in this election?</p>
                    <p>Register as a candidate to create your candidacy profile and let voters learn about you.</p>
                    <a href="register_candidate.php" class="btn" style="background-color: #007bff; display: inline-block;">
                        + Register as Candidate
                    </a>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>