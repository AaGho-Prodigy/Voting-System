<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/navigation.php';

if (!isAdminLoggedIn()) {
    redirect('login.php');
}

$total_users      = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_candidates = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$total_votes      = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$voted_users      = $pdo->query("SELECT COUNT(*) FROM users WHERE has_voted = 1")->fetchColumn();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS allowed_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_registration_number VARCHAR(255) NOT NULL UNIQUE,
        used TINYINT(1) DEFAULT 0,
        used_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
}

$admin_message = '';
$admin_message_type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_allowed'])) {
            $srn = trim($_POST['student_registration_number'] ?? '');
            if ($srn !== '') {
                $checkDuplicate = $pdo->prepare("SELECT id FROM allowed_students WHERE student_registration_number = ?");
                $checkDuplicate->execute([$srn]);
                if ($checkDuplicate->fetchColumn() !== false) {
                    $admin_message = "Student registration number '{$srn}' already exists in the allowed list.";
                    $admin_message_type = 'error';
                } else {
                    $limit = $pdo->prepare("SELECT value FROM settings WHERE name = 'allowed_student_limit'");
                    $limit->execute();
                    $lim = $limit->fetchColumn();
                    $currentCount = $pdo->query("SELECT COUNT(*) FROM allowed_students")->fetchColumn();
                    if ($lim !== false && $lim !== null && is_numeric($lim) && $lim != '' && (int)$lim > 0 && $currentCount >= (int)$lim) {
                        $admin_message = 'Allowed list has reached the configured limit.';
                        $admin_message_type = 'error';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO allowed_students (student_registration_number) VALUES (?)");
                        $stmt->execute([$srn]);
                        $admin_message = "Student registration number '{$srn}' added to allowed list.";
                        $admin_message_type = 'success';
                    }
                }
            }
        } elseif (isset($_POST['bulk_add'])) {
            $bulk = trim($_POST['bulk_numbers'] ?? '');
            if ($bulk !== '') {
                $limit = $pdo->prepare("SELECT value FROM settings WHERE name = 'allowed_citizen_limit'");
                $limit->execute();
                $lim = $limit->fetchColumn();
$currentCount = $pdo->query("SELECT COUNT(*) FROM allowed_students")->fetchColumn();
                
                $lines = preg_split('/\r?\n/', $bulk);
                $added = 0;
                $duplicates = [];
                $skipped = 0;
                $available_slots = ((int)$lim) - $currentCount;
                $batch_seen = [];
                
                foreach ($lines as $line) {
                    $cn = trim($line);
                    if ($cn === '') continue;
                    
                    if (in_array($cn, $batch_seen)) {
                        $duplicates[] = $cn;
                        $skipped++;
                        continue;
                    }
                    
                    $checkDuplicate = $pdo->prepare("SELECT id FROM allowed_students WHERE student_registration_number = ?");
                    $checkDuplicate->execute([$cn]);
                    if ($checkDuplicate->fetchColumn() !== false) {
                        $duplicates[] = $cn;
                        $skipped++;
                        continue;
                    }
                    
                    if ($lim !== false && $lim !== null && is_numeric($lim) && $lim != '' && (int)$lim > 0 && $added >= $available_slots) {
                        $skipped++;
                        continue;
                    }
                    
                    try {
                        $pdo->prepare("INSERT INTO allowed_students (student_registration_number) VALUES (?)")->execute([$cn]);
                        $batch_seen[] = $cn;
                        $added++;
                    } catch (Exception $e) {
                        $skipped++;
                    }
                }
                
                $admin_message = "";
                if ($added > 0) {
                    $admin_message = "Bulk import complete. Added {$added} entries.";
                }
                if ($skipped > 0) {
                    if ($added > 0) {
                        $admin_message .= " Skipped {$skipped} entries.";
                    } else {
                        $admin_message = "Skipped {$skipped} entries.";
                    }
                    if (!empty($duplicates)) {
                        $admin_message .= " Duplicates: " . implode(", ", array_slice($duplicates, 0, 5));
                        if (count($duplicates) > 5) {
                            $admin_message .= " and " . (count($duplicates) - 5) . " more.";
                        }
                    }
                    $admin_message_type = 'warning';
                } else {
                    $admin_message_type = 'success';
                }
            }
        } elseif (isset($_POST['delete_allowed'])) {
            $id = (int)($_POST['allowed_id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("DELETE FROM allowed_students WHERE id = ?")->execute([$id]);
                $admin_message = 'Removed from allowed list.';
                $admin_message_type = 'success';
            }
        } elseif (isset($_POST['set_allowed_limit'])) {
            $limitVal = trim($_POST['allowed_limit'] ?? '');
            if ($limitVal === '') {
                $pdo->prepare("DELETE FROM settings WHERE name = 'allowed_student_limit'")->execute();
                $admin_message = 'Allowed limit cleared (no limit).';
                $admin_message_type = 'success';
            } else {
                if (!is_numeric($limitVal) || (int)$limitVal < 1) {
                    $admin_message = 'Invalid limit value.';
                    $admin_message_type = 'error';
                } else {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
                    $check->execute(['allowed_student_limit']);
                    if ($check->fetchColumn() > 0) {
                        $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'allowed_student_limit'")->execute([(int)$limitVal]);
                    } else {
                        $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?)")->execute(['allowed_student_limit', (int)$limitVal]);
                    }
                    $admin_message = 'Allowed limit updated.';
                    $admin_message_type = 'success';
                }
            }
        }
    } catch (Exception $e) {
        $admin_message = 'Action failed: ' . $e->getMessage();
        $admin_message_type = 'error';
    }
}

$allowed_list = $pdo->query("SELECT * FROM allowed_students ORDER BY created_at DESC")->fetchAll();
$allowed_limit = $pdo->prepare("SELECT value FROM settings WHERE name = 'allowed_student_limit'");
$allowed_limit->execute();
$allowed_limit_val = $allowed_limit->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Online Voting System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/navigation.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-card);
            transition: var(--transition);
        }
        .stat-card:hover {
            border-color: var(--border-accent);
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        .stat-card h3 {
            color: var(--text-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .admin-actions {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            margin-top: 1.5rem;
        }
        .admin-actions h3 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 1.25rem;
            font-size: 1.1rem;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🛡️ Admin Panel</h1>
            <p>Online Voting System Administration</p>
            <?php echo getAdminNavMenu('dashboard', '../'); ?>
        </header>

        <main>
            <div style="margin-bottom: 2rem;">
                <h2 class="page-title">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</h2>
                <p class="page-subtitle">Here's an overview of the current election status.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p class="big-number"><?php echo $total_users; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Candidates</h3>
                    <p class="big-number"><?php echo $total_candidates; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Votes</h3>
                    <p class="big-number"><?php echo $total_votes; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Voted Users</h3>
                    <p class="big-number"><?php echo $voted_users; ?></p>
                </div>
            </div>

            <div class="admin-actions">
                <h3>⚡ Quick Actions</h3>
                <div class="action-buttons">
                    <a href="manage_candidates.php" class="btn" id="manage-candidates-btn">Manage Candidates</a>
                    <a href="results.php" class="btn btn-secondary" id="view-results-admin-btn">View Results</a>
                </div>
            </div>

                <div class="admin-actions" style="margin-top:1rem;">
                    <h3>🔐 Allowed Student Registration Numbers</h3>
                    <?php if ($admin_message): ?>
                        <div class="alert <?php echo $admin_message_type; ?>" style="<?php 
                            if ($admin_message_type === 'error') echo 'background: rgba(239, 68, 68, 0.1); color: #dc2626; border-left: 4px solid #dc2626;';
                            elseif ($admin_message_type === 'success') echo 'background: rgba(16, 185, 129, 0.1); color: #059669; border-left: 4px solid #059669;';
                            elseif ($admin_message_type === 'warning') echo 'background: rgba(245, 158, 11, 0.1); color: #92400e; border-left: 4px solid #f59e0b;';
                            else echo 'background: rgba(59, 130, 246, 0.1); color: #1e40af; border-left: 4px solid #3b82f6;';
                        ?> padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;"><?php echo htmlspecialchars($admin_message); ?></div>
                    <?php endif; ?>

                    <p style="color:var(--text-muted);">Only student registration numbers present in this list can register. Admins remain in the `admin` table.</p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                        <form method="POST" class="stat-card" style="text-align: left;">
                            <div class="form-group">
                                <label style="font-size:0.8rem; margin-bottom:0.5rem; display:block;">Add Single Number</label>
                                <input type="text" name="student_registration_number" placeholder="Student Registration Number" required>
                            </div>
                            <button type="submit" name="add_allowed" class="btn" style="width:100%;">Add Number</button>
                        </form>

                        <form method="POST" class="stat-card" style="text-align: left;">
                            <div class="form-group">
                                <label style="font-size:0.8rem; margin-bottom:0.5rem; display:block;">Bulk Import (One per line)</label>
                                <textarea name="bulk_numbers" rows="3" placeholder="12345&#10;67890" style="min-height: 80px;"></textarea>
                            </div>
                            <button type="submit" name="bulk_add" class="btn btn-secondary" style="width:100%;">Import Numbers</button>
                        </form>

                        <form method="POST" class="stat-card" style="text-align: left;">
                            <div class="form-group">
                                <label style="font-size:0.8rem; margin-bottom:0.5rem; display:block;">Allowed List Limit</label>
                                <input type="number" name="allowed_limit" placeholder="Leave blank for no limit" value="<?php echo htmlspecialchars($allowed_limit_val ?? ''); ?>">
                            </div>
                            <button type="submit" name="set_allowed_limit" class="btn btn-outline" style="width:100%;">Set Limit</button>
                        </form>
                    </div>

                    <div style="margin-top: 2rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--text-primary); font-size: 1.1rem;">Current Allowed Numbers <span style="color:var(--text-muted); font-size: 0.9rem;">(<?php echo count($allowed_list); ?> total)</span></h4>
                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border); background: var(--bg-surface); border-radius: var(--radius); box-shadow: inset 0 2px 8px rgba(0,0,0,0.2);">
                            <?php if (empty($allowed_list)): ?>
                                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">No student registration numbers added yet.</div>
                            <?php else: ?>
                                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                                    <thead style="position: sticky; top: 0; background: var(--bg-card); z-index: 1;">
                                        <tr>
                                            <th style="padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase;">Number</th>
                                            <th style="padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase;">Used Status</th>
                                            <th style="padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; text-align: right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($allowed_list as $item): ?>
                                        <tr style="transition: var(--transition-fast);">
                                            <td style="padding: 1rem; border-bottom: 1px solid var(--border-subtle); color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($item['student_registration_number']); ?></td>
                                            <td style="padding: 1rem; border-bottom: 1px solid var(--border-subtle);">
                                                <?php if($item['used']): ?>
                                                    <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; padding: 0.25rem 0.75rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600;">Used</span>
                                                <?php else: ?>
                                                    <span style="background: rgba(148, 163, 184, 0.15); color: var(--text-muted); padding: 0.25rem 0.75rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600;">Unused</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-subtle); text-align: right;">
                                                <form method="POST" style="display: inline-block;">
                                                    <input type="hidden" name="allowed_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="delete_allowed" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; box-shadow: none;" onclick="return confirm('Remove this number from allowed list?')">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Online Voting System Administration. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>