<?php
require_once 'includes/auth.php';
require_once 'includes/navigation.php';

if (!$pdo) {
    die("Database connection failed. Please check your database configuration.");
}

$checkVote = $pdo->prepare("SELECT id FROM votes WHERE voter_id = ?");
$checkVote->execute([$_SESSION['user_id']]);

if ($checkVote->fetch()) {
    $_SESSION['has_voted'] = true;
    $pdo->prepare("UPDATE users SET has_voted = 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
    redirect('dashboard.php');
}

if (isset($_SESSION['has_voted']) && $_SESSION['has_voted']) {
    redirect('dashboard.php');
}

try {
    $stmt = $pdo->prepare("SELECT name, value FROM settings WHERE name IN ('election_status', 'election_start', 'election_end')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings = [];
}

$election_status = $settings['election_status'] ?? 'inactive';
$election_start  = $settings['election_start']  ?? null;
$election_end    = $settings['election_end']    ?? null;

$now = new DateTime();
$can_vote = true;
$error_message = '';

$scheduledStarted = false;
if ($election_start && !empty($election_start) && $election_start !== 'NULL') {
    try {
        $start_date_check = new DateTime($election_start);
        if ($now >= $start_date_check) {
            $scheduledStarted = true;
        }
    } catch (Exception $e) {
        $scheduledStarted = false;
    }
}

if ($election_status === 'paused') {
    $can_vote = false;
    $error_message = "Election is currently on hold. Voting is temporarily suspended.";

} elseif ($election_status === 'active' || $scheduledStarted) {
    $can_vote = true;

    if ($election_start && !$scheduledStarted) {
        $can_vote = false;
        try {
            $start_date = new DateTime($election_start);
            $error_message = "Voting has not opened yet. The election starts on " . $start_date->format('F j, Y \\a\\t g:i A') . ".";
        } catch (Exception $e) {
            $error_message = "Voting has not opened yet.";
        }
    }

    if ($can_vote && $election_end && !empty($election_end) && $election_end !== 'NULL') {
        try {
            $end_date = new DateTime($election_end);
            if ($now > $end_date) {
                $can_vote = false;
                $error_message = "Election has ended. Results are now available.";
            }
        } catch (Exception $e) {
        }
    }

} else {
    $can_vote = false;
    $error_message = "Election is not active. Voting is not allowed at this time.";
}

$stmt = $pdo->query("SELECT * FROM candidates WHERE approved = 1");
$candidates = $stmt->fetchAll();

shuffle($candidates);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    if (!$can_vote) {
        $error = "Cannot vote at this time: " . $error_message;
    } else {
        $candidate_id = (int)$_POST['candidate_id'];
        
        $candidateCheck = $pdo->prepare("SELECT id FROM candidates WHERE id = ? AND approved = 1");
        $candidateCheck->execute([$candidate_id]);
        
        if (!$candidateCheck->fetch()) {
            $error = "Invalid candidate selected.";
        } else {
            try {
                $pdo->beginTransaction();
                
                $checkVoteAgain = $pdo->prepare("SELECT id FROM votes WHERE voter_id = ?");
                $checkVoteAgain->execute([$_SESSION['user_id']]);
                
                if ($checkVoteAgain->fetch()) {
                    throw new Exception("You have already voted!");
                }
                
                $stmt = $pdo->prepare("INSERT INTO votes (voter_id, candidate_id) VALUES (?, ?)");
                $result = $stmt->execute([$_SESSION['user_id'], $candidate_id]);
                
                if (!$result) {
                    throw new Exception("Failed to insert vote record");
                }
                
                $stmt = $pdo->prepare("UPDATE candidates SET votes = votes + 1 WHERE id = ?");
                $result = $stmt->execute([$candidate_id]);
                
                if (!$result) {
                    throw new Exception("Failed to update candidate votes");
                }
                
                $stmt = $pdo->prepare("UPDATE users SET has_voted = 1 WHERE id = ?");
                $result = $stmt->execute([$_SESSION['user_id']]);
                
                if (!$result) {
                    throw new Exception("Failed to update user voting status");
                }
                
                $pdo->commit();
                
                $_SESSION['has_voted'] = true;
                
                $success = "Your vote has been cast successfully! Thank you for participating.";
                $candidates = [];
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "An error occurred while casting your vote: " . $e->getMessage();
            }
        }
    }
}

if (!$can_vote && !isset($error)) {
    $error = $error_message;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote — Online Voting System</title>
    <meta name="description" content="Cast your secure vote in the Online Voting System election.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
    <style>
        .vote-instructions {
            background: rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.2);
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius-lg);
            margin: 1.5rem 0;
        }
        .vote-instructions h3 {
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
        }
        .vote-instructions p {
            color: var(--text-secondary);
            margin: 0.3rem 0;
            font-size: 0.9rem;
        }
        .election-status-box {
            background: rgba(16,185,129,0.08);
            border: 1px solid rgba(16,185,129,0.25);
            padding: 1.1rem 1.4rem;
            border-radius: var(--radius);
            margin: 1rem 0;
        }
        .election-status-box p {
            color: var(--text-secondary);
            margin: 0.3rem 0;
            font-size: 0.9rem;
        }
        .vote-submit-area {
            text-align: center;
            margin: 2rem 0;
        }
        .candidate-card.selected {
            border-color: var(--primary) !important;
            background: rgba(99,102,241,0.06) !important;
            box-shadow: 0 0 0 2px rgba(99,102,241,0.25), var(--shadow-lg) !important;
        }
        /* Ensure label text remains readable when a card is selected */
        .candidate-card.selected label,
        .candidate-card.selected h3,
        .candidate-card.selected p {
            color: var(--text-primary) !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🗳️ Online Voting System</h1>
            <?php echo function_exists('getElectionStatusNotification') ? getElectionStatusNotification() : ''; ?>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>
        
        <main>
            <h2>Cast Your Vote</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert success">
                    <p><?php echo $success; ?></p>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="dashboard.php" class="btn">Go to Dashboard</a>
                        <a href="results.php" class="btn">View Results</a>
                    </div>
                </div>
            <?php elseif (isset($error)): ?>
                <div class="alert error">
                    <p><strong>Error:</strong> <?php echo $error; ?></p>
                    <?php if (strpos($error, 'already voted') !== false): ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <p>You have already voted. You cannot vote again.</p>
                            <a href="dashboard.php" class="btn">Go to Dashboard</a>
                            <a href="results.php" class="btn">View Results</a>
                        </div>
                    <?php elseif (strpos($error, 'ended') !== false): ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="results.php" class="btn">View Results</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="election-status-box">
                    <p><strong style="color:var(--success);">✅ Voting is currently OPEN</strong></p>
                    <p>You are voting as: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?> (ID: <?php echo $_SESSION['user_id']; ?>)</strong></p>
                    <p>Your voting status: <strong style="color: var(--success);">✓ Not Voted Yet</strong></p>
                    <?php if ($election_end && !empty($election_end) && $election_end !== 'NULL'): ?>
                        <?php 
                        try {
                            $end_date = new DateTime($election_end);
                            $time_left = $now->diff($end_date);
                            if ($time_left->invert == 0) { // Future date
                                echo "<p>Voting ends on: " . $end_date->format('F j, Y \a\t g:i A') . "</p>";
                            }
                        } catch (Exception $e) {
                            // Ignore
                        }
                        ?>
                    <?php endif; ?>
                </div>
                
                <div class="vote-instructions">
                    <h3>📋 Voting Instructions</h3>
                    <p>1. Review all candidates carefully</p>
                    <p>2. Click on a candidate card to select</p>
                    <p>3. Click "View Profile" to see more details</p>
                    <p>4. Click "Submit Vote" when you've made your choice</p>
                    <p><strong>Note:</strong> You can only vote once. Your vote is final and cannot be changed.</p>
                </div>
                
                <div class="candidates-list">
                    <?php if (empty($candidates)): ?>
                        <div class="alert info">
                            <p>No candidates available for voting at this time.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="vote.php" id="voteForm">
                            <div class="candidates-grid">
                                <?php foreach ($candidates as $candidate): ?>
                                    <div class="candidate-card">
                                        <input type="radio" id="candidate_<?php echo $candidate['id']; ?>" name="candidate_id" value="<?php echo $candidate['id']; ?>" required>
                                        <label for="candidate_<?php echo $candidate['id']; ?>">
                                            <h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
                                            <p><strong>Position:</strong> <?php echo htmlspecialchars($candidate['position']); ?></p>
                                            <?php if (!empty($candidate['bio'])): ?>
                                                <p><?php echo htmlspecialchars(substr($candidate['bio'], 0, 150)) . (strlen($candidate['bio']) > 150 ? '...' : ''); ?></p>
                                            <?php endif; ?>
                                        </label>
                                        <a href="candidate_profile.php?id=<?php echo $candidate['id']; ?>" class="btn-secondary" target="_blank">View Full Profile</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <button type="submit" class="btn btn-large" onclick="return confirmVote()">
                                    ✅ Submit Vote
                                </button>
                            </div>
                        </form>
                        
                        <script>
                            function confirmVote() {
                                const selected = document.querySelector('input[name="candidate_id"]:checked');
                                if (!selected) {
                                    alert('Please select a candidate before submitting your vote.');
                                    return false;
                                }
                                
                                const candidateName = document.querySelector('label[for="' + selected.id + '"] h3').textContent;
                                return confirm(`Are you sure you want to vote for "${candidateName}"?\n\nThis action cannot be undone.`);
                            }
                            
                            // Auto-select candidate when clicking anywhere on the card
                            document.querySelectorAll('.candidate-card').forEach(card => {
                                card.addEventListener('click', function(e) {
                                    if (!e.target.closest('a')) { // Don't trigger on profile links
                                        const radio = this.querySelector('input[type="radio"]');
                                        if (radio) {
                                            radio.checked = true;
                                            // Remove selected class from all cards then add to this one
                                            document.querySelectorAll('.candidate-card').forEach(c => c.classList.remove('selected'));
                                            this.classList.add('selected');
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>