<?php
function getElectionStatusNotification() {
    try {
        require_once __DIR__ . '/config.php';
        global $pdo; 
        
        if (!isset($pdo)) {
            return ''; 
        }
        
        $settings = [];
        $result = $pdo->query("SELECT * FROM settings");
        while ($row = $result->fetch()) {
            $settings[$row['name']] = $row['value'];
        }
        
        $status = $settings['election_status'] ?? 'inactive';
        $startDate = $settings['election_start'] ?? '';
        $endDate = $settings['election_end'] ?? '';
        
        $now = new DateTime();
        
        if ($status === 'paused') {
            return '<div class="election-notification election-paused">⚠️ Election is on hold for now. No one can vote.</div>';
        }
        
        if ($status === 'inactive' && $startDate) {
            $start = new DateTime($startDate);
            if ($now < $start) {
                $formattedDate = $start->format('F j, Y \a\t g:i A');
                return '<div class="election-notification election-scheduled">📅 Election starts on ' . $formattedDate . '</div>';
            }
        }
        
        if ($status === 'active' && $endDate) {
            $end = new DateTime($endDate);
            if ($now > $end) {
                return '<div class="election-notification election-ended">🏁 Election has ended. Results are now available.</div>';
            }
        }
        
        return '';
    } catch (Exception $e) {
        return '';
    }
}

function getNavMenu() {

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // More reliable path detection
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $basePath = (strpos($currentScript, '/admin/') !== false) ? '../' : '';

    $menu  = '<nav class="main-nav">';

    if (isset($_SESSION['user_id'])) {
        $userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'voter';
        if ($userType === 'candidate') {
            $menu .= '<a href="' . $basePath . 'index.php">Home</a>';
            $menu .= '<a href="' . $basePath . 'candidates.php">Candidates</a>';
            $menu .= '<a href="' . $basePath . 'candidate_dashboard.php">My Dashboard</a>';
            $menu .= '<a href="' . $basePath . 'vote.php">Cast Vote</a>';
            $menu .= '<a href="' . $basePath . 'candidate_edit.php">Edit Profile</a>';
            $menu .= '<a href="' . $basePath . 'results.php">Results</a>';
            $menu .= '<a href="' . $basePath . 'logout.php">Logout</a>';
        } else {
            $menu .= '<a href="' . $basePath . 'index.php">Home</a>';
            $menu .= '<a href="' . $basePath . 'candidates.php">Candidates</a>';
            $menu .= '<a href="' . $basePath . 'dashboard.php">My Dashboard</a>';
            $menu .= '<a href="' . $basePath . 'vote.php">Cast Vote</a>';
            $menu .= '<a href="' . $basePath . 'results.php">Results</a>';
            $menu .= '<a href="' . $basePath . 'logout.php">Logout</a>';
        }
    } else {

        $menu .= '<a href="' . $basePath . 'index.php">Home</a>';
        $menu .= '<a href="' . $basePath . 'candidates.php">Candidates</a>';
        $menu .= '<a href="' . $basePath . 'results.php">Results</a>';
    }

    $menu .= '</nav>';
    return $menu;
}


function getAdminNavMenu($currentPage = '', $basePath = '') {
    $menu = '<nav class="main-nav">';
    
    // Use relative paths without duplicating "admin"
    $menu .= '<a href="dashboard.php"' . ($currentPage === 'dashboard' ? ' class="active"' : '') . '>Dashboard</a>';
    $menu .= '<a href="manage_candidates.php"' . ($currentPage === 'manage_candidates' ? ' class="active"' : '') . '>Manage Candidates</a>';
    $menu .= '<a href="results.php"' . ($currentPage === 'results' ? ' class="active"' : '') . '>Results</a>';
    $menu .= '<a href="../logout.php">Logout</a>';
    
    $menu .= '</nav>';
    return $menu;
}