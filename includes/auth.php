<?php
session_start();
require_once __DIR__ . '/config.php';
require_once 'functions.php';

$public_pages = [
    'index.php',
    'login.php',
    'register.php',
    'candidates.php',
    'candidate_profile.php',
    'results.php',
    'contact.php'
];

$admin_pages = [
    'login.php',
    'dashboard.php',
    'manage_candidates.php'
];

$voter_pages = [
    'dashboard.php',
    'vote.php'
];

$candidate_pages = [
    'candidate_dashboard.php',
    'candidate_profile.php',
    'candidate_edit.php',
    'vote.php'
];

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

if (!in_array($current_page, $public_pages) && 
    !($current_dir === 'admin' && in_array($current_page, $admin_pages)) && 
    !isLoggedIn()) {
    redirect('login.php');
}

// Role-based access
if (isLoggedIn()) {
    $user_type = $_SESSION['user_type'] ?? '';
    // Vote page can be accessed by both voters and candidates
    if ($current_page === 'vote.php' && !in_array($user_type, ['voter', 'candidate'])) {
        error_log("AUTH REDIRECT: Access denied to vote.php for user_type=" . $user_type);
        redirect('login.php');
    }
    // Dashboard can be accessed by voters only
    if ($current_page === 'dashboard.php' && $user_type !== 'voter') {
        error_log("AUTH REDIRECT: Access denied to dashboard.php for user_type=" . $user_type);
        redirect('login.php');
    }
    if (in_array($current_page, $candidate_pages) && $current_page !== 'vote.php' && $user_type !== 'candidate') {
        error_log("AUTH REDIRECT: Access denied to candidate page (" . $current_page . ") for user_type=" . $user_type);
        redirect('login.php');
    }
    if ($current_dir === 'admin' && in_array($current_page, $admin_pages) && !isAdminLoggedIn()) {
        // Redirect to unified login page
        error_log("AUTH REDIRECT: Admin area access denied; not admin logged in. Redirecting to login.php");
        redirect('login.php');
    }
}
?>