<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/navigation.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!$pdo) {
    die('Database connection error.');
}

$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die('User not found.');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $existing_candidate = $stmt->fetch();
    
    if ($existing_candidate) {
        die('You already have a candidate profile (Status: ' . ($existing_candidate['approved'] ? 'Approved' : 'Pending') . ')');
    }
    
    if (isElectionOver($pdo)) {
        die('Candidate registration is closed. The election has ended.');
    }
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CHECK IF ELECTION HAS ENDED
    if (isElectionOver($pdo)) {
        $error = 'Candidate registration is closed. The election has ended.';
    } else {
        $position = sanitizeInput($_POST['position'] ?? '');
        $goals = sanitizeInput($_POST['goals'] ?? '');
        $targets = sanitizeInput($_POST['targets'] ?? '');
        $bio = sanitizeInput($_POST['bio'] ?? '');
        
        if (empty($position)) {
            $error = 'Position is required.';
        } elseif (empty($goals)) {
            $error = 'Goals and vision are required.';
        } else {
            try {
                // CREATE CANDIDATE PROFILE
                $stmt = $pdo->prepare("INSERT INTO candidates (user_id, name, position, bio, goals, targets, approved) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$_SESSION['user_id'], $user['name'], $position, $bio, $goals, $targets]);
                
                // UPDATE USER AS CANDIDATE
                $stmt = $pdo->prepare("UPDATE users SET is_candidate = 1 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                // UPDATE SESSION
                $_SESSION['user_type'] = 'candidate';
                $candidate_id = $pdo->lastInsertId();
                $_SESSION['candidate_id'] = $candidate_id;
                
                session_write_close();
                header('Location: candidate_dashboard.php?registered=1');
                exit;
                
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as Candidate</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
    <style>
        .user-info-box {
            background: linear-gradient(135deg, rgba(3, 105, 161, 0.05), rgba(6, 182, 212, 0.05));
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 2rem;
        }
        .user-info-box p {
            margin: 0.8rem 0;
            font-size: 0.95rem;
        }
        .user-info-box strong {
            color: var(--primary);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Online Voting System</h1>
            <p>Register as Candidate</p>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>

        <main class="form-container" style="max-width: 600px;">
            <?php if ($error): ?>
                <div class="alert error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="user-info-box">
                <h3 style="margin-top: 0; color: var(--primary);">Your Account Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($user['student_registration_number']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p style="font-size: 12px; color: var(--gray); margin-top: 15px;">ℹ️ Cannot change account details here. Use your profile settings if needed.</p>
            </div>

            <form method="POST">
                <h2>Candidate Information</h2>
                <p style="color: var(--gray); margin-bottom: 20px;">Tell voters about yourself and your vision.</p>

                <div class="form-group">
                    <label for="position"><strong>Position You're Running For:</strong> *</label>
                    <input type="text" id="position" name="position" placeholder="e.g., President, Vice President, Secretary" required>
                </div>

                <div class="form-group">
                    <label for="goals"><strong>Goals and Vision:</strong> *</label>
                    <textarea id="goals" name="goals" rows="5" placeholder="Share your vision, goals, and what you want to achieve if elected." required></textarea>
                    <small style="color: var(--gray);">Be clear and compelling. This helps voters understand your platform.</small>
                </div>

                <div class="form-group">
                    <label for="targets"><strong>Key Targets and Timeline:</strong></label>
                    <textarea id="targets" name="targets" rows="4" placeholder="What specific targets do you want to achieve? Include timeframes."></textarea>
                    <small style="color: var(--gray);">Optional but helpful for demonstrating concrete plans.</small>
                </div>

                <div class="form-group">
                    <label for="bio"><strong>Brief Biography:</strong></label>
                    <textarea id="bio" name="bio" rows="3" placeholder="A short introduction about yourself, your background, experience, etc."></textarea>
                    <small style="color: var(--gray);">Optional - helps voters get to know you better.</small>
                </div>

                <div style="background: linear-gradient(135deg, #fef3c7, #fde68a); padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid var(--warning); color: #78350f;">
                    <strong>⚠️ Important:</strong> Your candidacy will be submitted for admin approval. You'll be notified once a decision is made.
                </div>

                <button type="submit" class="btn btn-success" style="width: 100%; padding: 12px; font-size: 16px;">Submit Candidacy Application</button>
            </form>

            <p style="text-align: center; margin-top: 20px; color: var(--gray);">
                <a href="dashboard.php">← Back to Dashboard</a>
            </p>
        </main>
    </div>
</body>
</html>
