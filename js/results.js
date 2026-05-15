function updateResults() {
    fetch('api/get_results.php')
        .then(response => response.json())
        .then(data => {
            if (data.error && data.restricted) {
                document.querySelector('.big-number').textContent = '-';
                let tbody = document.querySelector('.results-table tbody');
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #999;"><strong>Election Results</strong><br><br>Results will be available after the election concludes.</td></tr>';
                document.querySelector('.chart').innerHTML = '<div style="text-align: center; padding: 2rem; color: #999;">Awaiting election completion...</div>';
                let winnerDiv = document.querySelector('.winner-announcement');
                if (winnerDiv) winnerDiv.innerHTML = '';
                return;
            }
            
            if (data.error) {
                document.querySelector('.big-number').textContent = '-';
                let tbody = document.querySelector('.results-table tbody');
                tbody.innerHTML = '<tr><td colspan="5">Error loading results.</td></tr>';
                return;
            }
            
            document.querySelector('.big-number').textContent = data.total_votes;
            let tbody = document.querySelector('.results-table tbody');
            let chartDiv = document.querySelector('.chart');
            let winnerDiv = document.querySelector('.winner-announcement');
            
            if (!data.candidates.length) {
                tbody.innerHTML = '<tr><td colspan="5">No candidates available.</td></tr>';
                chartDiv.innerHTML = '';
                if (winnerDiv) winnerDiv.innerHTML = '';
                return;
            }
            
            let html = '';
            let chart = '';
            let i = 1;
            
            data.candidates.forEach(c => {
                let pct = c.percentage || (data.total_votes ? (c.votes / data.total_votes * 100).toFixed(2) : 0);
                html += `<tr><td>${i++}</td><td>${c.name}</td><td>${c.position}</td><td>${c.votes}</td><td>${pct}%</td></tr>`;
                chart += `<div class="chart-bar"><div class="bar-label">${c.name}</div><div class="bar-container"><div class="bar" style="width:${pct}%"></div><span class="bar-value">${c.votes} votes (${pct}%)</span></div></div>`;
            });
            
            tbody.innerHTML = html;
            chartDiv.innerHTML = chart;
            
            if (winnerDiv && data.winner) {
                let pct = data.winner.percentage || (data.total_votes ? (data.winner.votes / data.total_votes * 100).toFixed(2) : 0);
                winnerDiv.innerHTML = `<div style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1)); padding: 1.5rem; border-radius: 8px; border-left: 4px solid #22c55e; margin: 1.5rem 0; text-align: center;">
                    <h3 style="color: #22c55e; margin: 0 0 0.5rem 0;">🏆 WINNER 🏆</h3>
                    <p style="font-size: 1.5rem; font-weight: bold; margin: 0.5rem 0;">${data.winner.name}</p>
                    <p style="font-size: 1rem; color: #666; margin: 0.5rem 0;">Position: ${data.winner.position}</p>
                    <p style="font-size: 1rem; margin: 0.5rem 0;"><strong>${data.winner.votes} votes (${pct}%)</strong></p>
                </div>`;
            }
        })
        .catch(error => console.error('Error:', error));
}

updateResults();
setInterval(updateResults, 5000);