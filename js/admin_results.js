let refreshInterval = null;
let currentFilter = {
    position: '',
    status: '1'
};

document.addEventListener('DOMContentLoaded', function() {
    loadAdminResults();
    loadAdminStats();
    
    // Set default refresh rate
    setRefreshRate();
});

function loadAdminResults() {
    const params = new URLSearchParams();
    if (currentFilter.position) params.append('position', currentFilter.position);
    if (currentFilter.status) params.append('status', currentFilter.status);
    
    fetch(`../api/get_admin_results.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            // Check if results are restricted
            if (data.error && data.restricted) {
                document.getElementById('admin-total-votes').textContent = '-';
                document.getElementById('results-body').innerHTML = 
                    '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #999;"><strong>Election Results</strong><br><br>Results will be available after the election concludes.</td></tr>';
                document.getElementById('total-votes').textContent = '-';
                document.getElementById('total-candidates').textContent = '-';
                return;
            }
            
            if (data.error) {
                console.error('Error:', data.error);
                document.getElementById('results-body').innerHTML = 
                    `<tr><td colspan="7">Error: ${data.error}</td></tr>`;
                return;
            }
            
            // Update total votes
            document.getElementById('admin-total-votes').textContent = data.total_votes;
            document.getElementById('total-votes').textContent = data.total_votes;
            
            // Update table
            const tbody = document.getElementById('results-body');
            if (!data.candidates || data.candidates.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7">No candidates found</td></tr>';
                return;
            }
            
            let html = '';
            let rank = 1;
            let chartData = [];
            
            data.candidates.forEach(candidate => {
                const percentage = data.total_votes > 0 ? 
                    ((candidate.votes / data.total_votes) * 100).toFixed(2) : 0;
                
                // Status badge
                let statusBadge = candidate.approved == 1 ? 
                    '<span style="color: green;">✓ Approved</span>' : 
                    '<span style="color: orange;">⏳ Pending</span>';
                
                html += `<tr>
                    <td>${rank++}</td>
                    <td><strong>${escapeHtml(candidate.name)}</strong></td>
                    <td>${escapeHtml(candidate.position)}</td>
                    <td>${statusBadge}</td>
                    <td>${candidate.votes}</td>
                    <td>${percentage}%</td>
                    <td>
                        <button onclick="viewCandidate(${candidate.id})" class="btn-small">View</button>
                        ${candidate.approved == 0 ? 
                            `<button onclick="approveCandidate(${candidate.id})" class="btn-small btn-success">Approve</button>` : 
                            `<button onclick="rejectCandidate(${candidate.id})" class="btn-small btn-warning">Reject</button>`
                        }
                    </td>
                </tr>`;
                
                // Prepare chart data
                chartData.push({
                    name: candidate.name,
                    votes: candidate.votes,
                    percentage: percentage
                });
            });
            
            tbody.innerHTML = html;
            
            // Update chart
            updateAdminChart(chartData, data.total_votes);
            
            // Update candidate count
            document.getElementById('total-candidates').textContent = data.candidates.length;
            
        })
        .catch(error => {
            console.error('Error loading results:', error);
            document.getElementById('results-body').innerHTML = 
                '<tr><td colspan="7">Failed to load results. Please try again.</td></tr>';
        });
}

function loadAdminStats() {
    fetch('../api/get_admin_stats.php')
        .then(response => response.json())
        .then(data => {
            // Check if stats are restricted
            if (data.error && data.restricted) {
                document.getElementById('voted-users').textContent = '-';
                document.getElementById('voting-percentage').textContent = '-';
                return;
            }
            
            if (!data.error) {
                document.getElementById('voted-users').textContent = data.voted_users;
                document.getElementById('voting-percentage').textContent = 
                    data.voting_percentage ? data.voting_percentage + '%' : '0%';
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

function filterResults() {
    currentFilter.position = document.getElementById('position-filter').value;
    currentFilter.status = document.getElementById('status-filter').value;
    loadAdminResults();
}

function setRefreshRate() {
    const rate = parseInt(document.getElementById('refresh-rate').value);
    
    // Clear existing interval
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
    
    // Set new interval if rate > 0
    if (rate > 0) {
        refreshInterval = setInterval(() => {
            loadAdminResults();
            loadAdminStats();
        }, rate);
    }
}

function refreshResults() {
    loadAdminResults();
    loadAdminStats();
}

function updateAdminChart(candidates, totalVotes) {
    const chartContainer = document.getElementById('admin-chart');
    
    if (candidates.length === 0) {
        chartContainer.innerHTML = '<div class="alert info">No data for chart</div>';
        return;
    }
    
    // Simple HTML bar chart (you can replace with Chart.js for better visuals)
    let chartHTML = '<div class="chart-bars">';
    
    candidates.slice(0, 10).forEach(candidate => { // Show top 10
        const barWidth = Math.min((candidate.votes / totalVotes) * 100, 100);
        chartHTML += `
            <div class="chart-bar-row">
                <div class="candidate-name">${escapeHtml(candidate.name)}</div>
                <div class="bar-container">
                    <div class="bar" style="width: ${barWidth}%"></div>
                    <span class="bar-value">${candidate.votes} votes (${candidate.percentage}%)</span>
                </div>
            </div>
        `;
    });
    
    chartHTML += '</div>';
    chartContainer.innerHTML = chartHTML;
}

function updateChartType() {
    // If using Chart.js, update chart type here
    console.log('Chart type changed to:', document.getElementById('chart-type').value);
}

function viewCandidate(id) {
    window.location.href = `../candidate_profile.php?id=${id}`;
}

function approveCandidate(id) {
    if (confirm('Approve this candidate?')) {
        fetch('../api/approve_candidate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}&action=approve`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Candidate approved!');
                refreshResults();
            } else {
                alert('Error: ' + data.error);
            }
        });
    }
}

function rejectCandidate(id) {
    if (confirm('Reject this candidate?')) {
        fetch('../api/approve_candidate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}&action=reject`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Candidate rejected!');
                refreshResults();
            } else {
                alert('Error: ' + data.error);
            }
        });
    }
}

function exportResults(format) {
    alert(`Exporting results as ${format.toUpperCase()}...`);
    // Implement export functionality here
    window.location.href = `../api/export_results.php?format=${format}`;
}

function printResults() {
    window.print();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize with 5-second refresh
setRefreshRate();