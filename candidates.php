<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates — Online Voting System</title>
    <meta name="description" content="Meet all the candidates running in the current election. View their profiles, platforms, and visions.">
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
            <p>Meet the Candidates</p>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>

        <main>
            <div style="margin-bottom: 2rem;">
                <h2 class="page-title">Candidates Running in This Election</h2>
                <p class="page-subtitle">Meet the candidates competing for your vote. Click on any candidate to learn more about their platform, goals, and vision.</p>
            </div>

            <?php
            if ($pdo) {
                try {
                    $stmt = $pdo->query("SELECT c.*, u.name as user_name, COUNT(v.id) as vote_count
                                         FROM candidates c
                                         JOIN users u ON c.user_id = u.id
                                         LEFT JOIN votes v ON c.id = v.candidate_id
                                         WHERE c.approved = 1
                                         GROUP BY c.id
                                         ORDER BY vote_count DESC, c.name ASC");
                    $candidates = $stmt->fetchAll();

                    if (empty($candidates)) {
                        echo '<div class="card text-center" style="padding: 3rem;">';
                        echo '<div style="font-size:3rem; margin-bottom:1rem;">👤</div>';
                        echo '<h3 style="color:var(--text-primary); margin-bottom:0.5rem;">No candidates available yet</h3>';
                        echo '<p style="color:var(--text-secondary);">Candidates will appear here once they register and are approved by administrators.</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="candidates-grid">';
                        foreach ($candidates as $candidate) {
                            echo '<div class="candidate-card">';
                            echo '<div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">';
                            echo '<div style="width:44px;height:44px;border-radius:50%;background:var(--grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">👤</div>';
                            echo '<div>';
                            echo '<h3 style="margin:0;">' . htmlspecialchars($candidate['name']) . '</h3>';
                            echo '<div class="badge badge-primary">' . htmlspecialchars($candidate['position']) . '</div>';
                            echo '</div></div>';

                            if ($candidate['bio']) {
                                echo '<p class="bio">' . htmlspecialchars(substr($candidate['bio'], 0, 120)) . '...</p>';
                            }

                            echo '<div class="vote-count">📊 ' . $candidate['vote_count'] . ' votes</div>';
                            echo '<a href="candidate_profile.php?id=' . $candidate['id'] . '" class="btn" style="margin-top:1rem;width:100%;">View Profile →</a>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="alert error">⚠️ Error loading candidates: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                echo '<div class="alert error">⚠️ Database connection error</div>';
            }
            ?>

            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="candidate-banner" style="margin-top: 3rem;">
                <h2>🎯 Want to Run?</h2>
                <p>Interested in becoming a candidate? Share your vision with voters and make a difference!</p>
                <a href="register_candidate.php" class="btn btn-secondary" id="register-candidate-main-btn" style="position:relative;">Register as Candidate</a>
            </div>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Online Voting System. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
