<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/navigation.php';

if (!$pdo) {
    die("Database connection failed. Please check your database configuration.");
}

if (!isAdminLoggedIn()) {
    redirect('login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results — Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/navigation.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🛡️ Admin Panel</h1>
            <?php echo getAdminNavMenu('results'); ?>
        </header>
        <main>
            <h2>Election Results - Live View</h2>
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

    <script>
    function updateAdminResults() {
        fetch('../api/get_admin_results.php')  
            .then(response => response.json())
            .then(data => {
                if (data.error && data.restricted) {
                    document.querySelector('.big-number').textContent = '-';
                    let tbody = document.querySelector('.results-table tbody');
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #999;"><strong>Election Results</strong><br><br>Results will be available after the election concludes.</td></tr>';
                    let chartDiv = document.querySelector('.chart');
                    chartDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #999;">Awaiting election completion...</div>';
                    return;
                }
                
                if (data.error) {
                    document.querySelector('.big-number').textContent = '-';
                    let tbody = document.querySelector('.results-table tbody');
                    tbody.innerHTML = `<tr><td colspan="5">Error: ${data.error}</td></tr>`;
                    return;
                }
                
                document.querySelector('.big-number').textContent = data.total_votes;
                let tbody = document.querySelector('.results-table tbody');
                let chartDiv = document.querySelector('.chart');
                let winnerDiv = document.querySelector('.winner-announcement');
                
                if (!data.candidates.length) {
                    tbody.innerHTML = '<tr><td colspan="5">No candidates available.</td></tr>';
                    chartDiv.innerHTML = '';
                    winnerDiv.innerHTML = '';
                    return;
                }
                
                // Display winner if exists
                if (data.winner) {
                    let winnerPct = data.total_votes ? (data.winner.votes / data.total_votes * 100).toFixed(2) : 0;
                    winnerDiv.innerHTML = `<div style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1)); padding: 1.5rem; border-radius: 8px; border-left: 4px solid #22c55e; margin: 1.5rem 0; text-align: center;">
                        <h3 style="color: #22c55e; margin: 0 0 0.5rem 0;">🏆 WINNER 🏆</h3>
                        <p style="font-size: 1.5rem; font-weight: bold; margin: 0.5rem 0;">${data.winner.name}</p>
                        <p style="font-size: 1rem; color: #666; margin: 0.5rem 0;">Position: ${data.winner.position}</p>
                        <p style="font-size: 1rem; margin: 0.5rem 0;"><strong>${data.winner.votes} votes (${winnerPct}%)</strong></p>
                    </div>`;
                } else {
                    winnerDiv.innerHTML = '';
                }
                
                let html = '';
                let chart = '';
                let i = 1;
                
               
                const approvedCandidates = data.candidates.filter(c => c.approved == 1);
                
                approvedCandidates.forEach(c => {
                    let pct = data.total_votes ? (c.votes / data.total_votes * 100).toFixed(2) : 0;
                    html += `<tr><td>${i++}</td><td>${c.name}</td><td>${c.position}</td><td>${c.votes}</td><td>${pct}%</td></tr>`;
                    chart += `<div class="chart-bar"><div class="bar-label">${c.name}</div><div class="bar-container"><div class="bar" style="width:${pct}%"></div><span class="bar-value">${c.votes} votes (${pct}%)</span></div></div>`;
                });
                
                tbody.innerHTML = html;
                chartDiv.innerHTML = chart;
            })
            .catch(error => console.error('Error:', error));
    }

    updateAdminResults();
    setInterval(updateAdminResults, 3000); 
    </script>
</body>
</html>