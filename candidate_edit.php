<?php
require_once 'includes/auth.php';
require_once 'includes/navigation.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidate') {
    redirect('login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = sanitizeInput($_POST['bio']);
    $goals = sanitizeInput($_POST['goals']);
    $targets = sanitizeInput($_POST['targets']);

    if (empty($goals) || empty($targets)) {
        $error = 'Goals and targets are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE candidates SET bio = ?, goals = ?, targets = ? WHERE user_id = ?");
            $stmt->execute([$bio, $goals, $targets, $_SESSION['user_id']]);
            $success = 'Profile updated successfully!';
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT c.* FROM candidates c WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$candidate = $stmt->fetch();

if (!$candidate) {
    die('Candidate profile not found.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Candidate Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Edit Candidate Profile</h1>
            <?php echo function_exists('getNavMenu') ? getNavMenu() : ''; ?>
        </header>

        <main class="form-container">
            <form method="POST" action="candidate_edit.php">
                <h2>Edit Your Profile</h2>

                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="bio">Biography:</label>
                    <textarea id="bio" name="bio" rows="4" placeholder="Tell voters about yourself..."><?php echo htmlspecialchars($candidate['bio']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="goals">Campaign Goals:</label>
                    <textarea id="goals" name="goals" rows="4" required placeholder="What are your main goals?"><?php echo htmlspecialchars($candidate['goals']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="targets">Key Targets:</label>
                    <textarea id="targets" name="targets" rows="4" required placeholder="What specific targets do you aim to achieve?"><?php echo htmlspecialchars($candidate['targets']); ?></textarea>
                </div>

                <button type="submit" class="btn btn-large">Update Profile</button>

                <p class="form-footer">
                    <a href="candidate_dashboard.php">← Back to Dashboard</a>
                </p>
            </form>
        </main>
    </div>
</body>
</html>