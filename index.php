<?php
session_start();
require_once 'includes/navigation.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Voting System — Secure & Transparent</title>
    <meta name="description" content="Cast your vote securely from anywhere. A modern, transparent, and efficient voting platform for everyone.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
</head>
<body>
    <div class="container">
        <header>
            <?php echo function_exists('getElectionStatusNotification') ? getElectionStatusNotification() : ''; ?>
            <h1>🗳️ Online Voting System</h1>
            <p>Secure · Transparent · Democratic</p>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>

        <main>
            <div class="welcome-section">
                <h2>Your Vote. Your Voice.</h2>
                <p>Cast your vote securely from anywhere. Our platform ensures total transparency and integrity in every election.</p>

                <div class="cta-buttons">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'candidate') ? 'candidate_dashboard.php' : 'dashboard.php'; ?>" class="btn btn-large" id="go-dashboard-btn">
                            Go to Dashboard →
                        </a>
                    <?php else: ?>
                        <div class="user-buttons">
                            <h3>Get started — it only takes a minute</h3>
                            <a href="register.php" class="btn btn-large" id="register-btn">Register Now</a>
                            <a href="login.php" class="btn btn-secondary btn-large" id="login-btn">Login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="candidate-banner">
                <h2>🎯 Run for Office!</h2>
                <p>Are you interested in participating in this election as a candidate?</p>
                <p style="font-size: 0.92rem; opacity: 0.75; position: relative;">Create your candidacy profile, share your vision, and let voters choose you!</p>
                <a href="register_candidate.php" class="btn btn-secondary" id="register-candidate-btn" style="position:relative;">
                    Register as Candidate
                </a>
            </div>
            <?php endif; ?>

            <div class="features">
                <div class="feature">
                    <div style="font-size:2.2rem; margin-bottom:0.75rem;">🔒</div>
                    <h3>Secure Voting</h3>
                    <p>Your vote is protected with advanced encryption and security measures every step of the way.</p>
                </div>
                <div class="feature">
                    <div style="font-size:2.2rem; margin-bottom:0.75rem;">🗳️</div>
                    <h3>One Vote Per User</h3>
                    <p>Our system guarantees each registered citizen can only vote once, ensuring fairness for all.</p>
                </div>
                <div class="feature">
                    <div style="font-size:2.2rem; margin-bottom:0.75rem;">📊</div>
                    <h3>Real-time Results</h3>
                    <p>Watch election results update live as votes come in — complete transparency from start to finish.</p>
                </div>
                <div class="feature">
                    <div style="font-size:2.2rem; margin-bottom:0.75rem;">🎤</div>
                    <h3>Candidate Portal</h3>
                    <p>Candidates get their own dashboard to manage profiles, share their vision, and track votes.</p>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Online Voting System. Built for democratic participation.</p>
        </footer>
    </div>

    <script>
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, i * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.feature').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>