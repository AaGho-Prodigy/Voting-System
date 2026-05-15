<?php 
require_once 'includes/config.php'; 
require_once 'includes/navigation.php'; 

if (!$pdo) {
    die("Database connection failed. Please check your database configuration.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin/results.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results — Online Voting System</title>
    <meta name="description" content="View real-time election results and vote distribution for the Online Voting System.">
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
            <p>Election Results</p>
            <?php echo function_exists('getElectionStatusNotification') ? getElectionStatusNotification() : ''; ?>
            <?php echo getNavMenu(); ?>
        </header>
        <main>
            <h2>Election Results</h2>
            
            <div class="results-summary">
                <div class="summary-card">
                    <h3>Total Votes</h3>
                    <p class="big-number">0</p>
                </div>
            </div>
            <div class="results-list">
                <h3>Candidates</h3>
                <div class="results-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th><th>Name</th><th>Position</th><th>Votes</th><th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="winner-announcement"></div>
                <div class="chart-container">
                    <h3>Vote Distribution</h3>
                    <div class="chart">Loading...</div>
                </div>
            </div>
        </main>
    </div>

    <script src="js/results.js"></script>
</body>
</html>