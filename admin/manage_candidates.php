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

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['candidate_id'];
        $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM votes WHERE candidate_id = ?")->execute([$id]);
        $message = 'Candidate deleted';
    } elseif (isset($_POST['approve'])) {
        $id = (int)$_POST['candidate_id'];
        $pdo->prepare("UPDATE candidates SET approved = 1 WHERE id = ?")->execute([$id]);
        $message = 'Candidate approved';
    } elseif (isset($_POST['reject'])) {
        $id = (int)$_POST['candidate_id'];
        $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM votes WHERE candidate_id = ?")->execute([$id]);
        $message = 'Candidate rejected and removed';
    } elseif (isset($_POST['set_dates'])) {
        $start_iso = $_POST['start_iso'] ?? null;
        $end_iso = $_POST['end_iso'] ?? null;
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;

        $useStart = $start_iso ?: $start_date;
        $useEnd = $end_iso ?: $end_date;
        $start_local_in = $_POST['start_local'] ?? null;
        $end_local_in = $_POST['end_local'] ?? null;

        try {
            $now = new DateTime();
            $startDt = new DateTime($useStart);
            $endDt = new DateTime($useEnd);

            if ($startDt <= $now) {
                $message = 'Start date must be in the future.';
            } elseif ($endDt <= $startDt) {
                $message = 'End date must be after the start date.';
            } else {
                $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'election_start'")->execute([$startDt->format(DateTime::ATOM)]);
                $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'election_end'")->execute([$endDt->format(DateTime::ATOM)]);
                if ($start_local_in) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
                    $check->execute(['election_start_local']);
                    if ($check->fetchColumn() > 0) {
                        $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'election_start_local'")->execute([$start_local_in]);
                    } else {
                        $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?)")->execute(['election_start_local', $start_local_in]);
                    }
                }
                if ($end_local_in) {
                    $check2 = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
                    $check2->execute(['election_end_local']);
                    if ($check2->fetchColumn() > 0) {
                        $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'election_end_local'")->execute([$end_local_in]);
                    } else {
                        $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?)")->execute(['election_end_local', $end_local_in]);
                    }
                }
                $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'election_status'")->execute(['inactive']);
                $message = 'Election dates updated';
            }
        } catch (Exception $e) {
            $message = 'Invalid date(s) provided.';
        }
    } elseif (isset($_POST['reset_election'])) {
        try {
            $pdo->beginTransaction();

            $pdo->exec("DELETE FROM votes");
            $pdo->exec("DELETE FROM candidates");

            $pdo->exec("DELETE FROM users");

            try {
                $pdo->exec("UPDATE allowed_students SET used = 0, used_by = NULL");
            } catch (Exception $e) {
            }

            $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'election_status'")->execute(['inactive']);
            $pdo->prepare("UPDATE settings SET value = NULL WHERE name = 'election_start'")->execute();
            $pdo->prepare("UPDATE settings SET value = NULL WHERE name = 'election_end'")->execute();

            $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
            $check->execute(['election_start_local']);
            if ($check->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET value = '' WHERE name = 'election_start_local'")->execute();
            } else {
                $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?)")->execute(['election_start_local', '']);
            }
            $check->execute(['election_end_local']);
            if ($check->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET value = '' WHERE name = 'election_end_local'")->execute();
            } else {
                $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?)")->execute(['election_end_local', '']);
            }

            $pdo->commit();
            $message = 'Election has been reset. Candidates removed; users can register and vote again.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Failed to reset election: ' . $e->getMessage();
        }
    }
}

$candidates = $pdo->query("SELECT c.*, u.email FROM candidates c JOIN users u ON c.user_id = u.id ORDER BY c.approved DESC, c.votes DESC")->fetchAll();

$settings = [];
$result = $pdo->query("SELECT * FROM settings");
while ($row = $result->fetch()) {
    $settings[$row['name']] = $row['value'];
}
$election_status = $settings['election_status'] ?? 'inactive';
$election_start = $settings['election_start'] ?? '';
$election_end = $settings['election_end'] ?? '';

$dates_locked = (!empty($election_start) && !empty($election_end));

try {
    $now = new DateTime();
    $startDT = $election_start ? new DateTime($election_start) : null;
    $endDT = $election_end ? new DateTime($election_end) : null;
    $hasStartTimePassed = $startDT ? ($now >= $startDT) : false;
    $hasEndTimePassed = $endDT ? ($now > $endDT) : false;

    if ($election_status === 'paused') {
        $effective_status = 'paused';
    } elseif (!$hasEndTimePassed && ($election_status === 'active' || $hasStartTimePassed)) {
        $effective_status = 'active';
    } else {
        $effective_status = 'inactive';
    }

    $showPause = ($effective_status === 'active');
    $showResume = ($election_status === 'paused' && $hasStartTimePassed && !$hasEndTimePassed);

} catch (Exception $e) {
    $hasStartTimePassed = false;
    $hasEndTimePassed = false;
    $effective_status = $election_status === 'paused' ? 'paused' : $election_status;
    $showPause = ($effective_status === 'active');
    $showResume = ($election_status === 'paused');
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/navigation.css">
    <style>
        /* ---- Candidate Cards ---- */
        .candidate-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.4rem 1.6rem;
            margin: 1rem 0;
            background: var(--bg-card);
            box-shadow: var(--shadow-card);
            transition: var(--transition);
        }
        .candidate-card:hover {
            border-color: rgba(255,255,255,0.12);
        }
        .candidate-card.pending {
            border-left: 4px solid var(--warning);
            background: rgba(245,158,11,0.04);
        }
        .candidate-card.approved {
            border-left: 4px solid var(--success);
            background: rgba(16,185,129,0.04);
        }
        .candidate-card h4 {
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.6rem;
        }
        .candidate-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0.35rem 0;
        }
        .candidate-card p strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* ---- Status Badges ---- */
        .status-pending {
            color: #fcd34d;
            background: rgba(245,158,11,0.15);
            border: 1px solid rgba(245,158,11,0.3);
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .status-approved {
            color: #6ee7b7;
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.3);
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-full);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .approved-status {
            color: #6ee7b7;
            font-weight: 600;
            font-size: 0.88rem;
            margin-right: 1rem;
        }

        /* ---- Action Buttons ---- */
        .action-buttons {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .inline-form {
            display: inline-block;
        }
        .btn-approve {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: inherit;
            transition: var(--transition-fast);
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }
        .btn-approve:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .btn-reject {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: inherit;
            transition: var(--transition-fast);
            box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        }
        .btn-reject:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .btn-delete {
            background: rgba(100,116,139,0.2);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: inherit;
            transition: var(--transition-fast);
        }
        .btn-delete:hover {
            background: rgba(239,68,68,0.15);
            border-color: rgba(239,68,68,0.3);
            color: #fca5a5;
        }

        /* ---- Section titles ---- */
        .candidates-section { margin-top: 2rem; }
        .section-title {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 0.7rem 1.2rem;
            border-radius: var(--radius);
            margin: 1.5rem 0 0.75rem;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .pending-title  { border-left: 4px solid var(--warning); }
        .approved-title { border-left: 4px solid var(--success); }

        /* ---- Election Controls ---- */
        .election-controls {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .election-controls h3 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.05rem;
        }
        .election-controls p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            font-size: 0.92rem;
        }
        .election-controls label {
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 0.3rem;
            margin-top: 0.75rem;
        }
        .election-controls input[type="datetime-local"] {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 0.6rem 0.9rem;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 0.9rem;
            margin-right: 0.5rem;
            transition: var(--transition-fast);
        }
        .election-controls input[type="datetime-local"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
        }
        .dates-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }
        .dates-row .date-field { flex: 1; min-width: 200px; }
        .control-buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🛡️ Admin Panel</h1>
            <?php echo getAdminNavMenu('manage_candidates'); ?>
        </header>
        
        <main>
            <h2>Manage Candidates</h2>
            
            <?php if ($message): ?>
                <div class="alert success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="election-controls">
                <h3>⚡ Election Control</h3>

                <?php
                $statusLabel = match($effective_status) {
                    'active'   => '🟢 Active',
                    'paused'   => '🟡 Paused',
                    default    => '⚫ Inactive',
                };
                ?>
                <p>Current Status: <strong><?php echo $statusLabel; ?></strong></p>

                <!-- Date Picker Form -->
                <form method="POST" id="dates-form" onsubmit="return prepareDatesForSubmit()">
                    <div class="dates-row">
                        <div class="date-field">
                            <label for="start_date">Start Date &amp; Time</label>
                            <?php if ($dates_locked): ?>
                                <?php $display_start = $settings['election_start_local'] ?? $election_start; ?>
                                <div style="padding:0.6rem 0.9rem; border-radius:6px; background:var(--bg-input); border:1px solid var(--border); color:var(--text-primary);">
                                    <?php echo htmlspecialchars($display_start); ?>
                                </div>
                            <?php else: ?>
                                <input type="datetime-local" name="start_date" id="start_date"
                                    value="<?php echo htmlspecialchars($election_start); ?>" data-stored-iso="<?php echo htmlspecialchars($election_start); ?>" data-stored-local="<?php echo htmlspecialchars($settings['election_start_local'] ?? ''); ?>" required>
                            <?php endif; ?>
                        </div>
                        <div class="date-field">
                            <label for="end_date">End Date &amp; Time</label>
                            <?php if ($dates_locked): ?>
                                <?php $display_end = $settings['election_end_local'] ?? $election_end; ?>
                                <div style="padding:0.6rem 0.9rem; border-radius:6px; background:var(--bg-input); border:1px solid var(--border); color:var(--text-primary);">
                                    <?php echo htmlspecialchars($display_end); ?>
                                </div>
                            <?php else: ?>
                                <input type="datetime-local" name="end_date" id="end_date"
                                    value="<?php echo htmlspecialchars($election_end); ?>" data-stored-iso="<?php echo htmlspecialchars($election_end); ?>" data-stored-local="<?php echo htmlspecialchars($settings['election_end_local'] ?? ''); ?>"
                                       required>
                                <small id="end_date_hint" style="color:var(--text-muted);font-size:0.78rem;">
                                    Select a start date first
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Hidden ISO fields: client-side will fill these with timezone-aware values -->
                    <input type="hidden" name="start_iso" id="start_iso" value="">
                    <input type="hidden" name="end_iso" id="end_iso" value="">
                    <!-- Hidden local wall-clock values so reload shows exact values admin entered -->
                    <input type="hidden" name="start_local" id="start_local" value="">
                    <input type="hidden" name="end_local" id="end_local" value="">
                    <div class="control-buttons">
                        <?php if ($dates_locked): ?>
                            <div style="color:var(--text-muted);">Dates locked. To change them, update the `settings` table directly.</div>
                        <?php else: ?>
                            <button type="submit" name="set_dates" class="btn">💾 Save Dates</button>
                        <?php endif; ?>
                    </div>
                </form>

                <div style="margin-top:1rem;">
                    <form method="POST" onsubmit="return confirm('Reset the election? This will DELETE all candidates and votes and reset users so the election starts fresh. This cannot be undone.')">
                        <button type="submit" name="reset_election" class="btn btn-danger">Reset Election</button>
                    </form>
                </div>

                <!-- Pause/Resume controls removed — scheduling is handled via Start/End dates -->
            </div>
            
            <div class="candidates-section">
                <!-- Pending Candidates Section -->
                <?php 
                $pending_candidates = array_filter($candidates, function($c) { return !$c['approved']; });
                if (!empty($pending_candidates)): ?>
                    <div class="section-title pending-title">
                        Pending Approval (<?php echo count($pending_candidates); ?>)
                    </div>
                    <?php foreach ($pending_candidates as $candidate): ?>
                        <div class="candidate-card pending">
                            <h4><?php echo htmlspecialchars($candidate['name']); ?> - <?php echo htmlspecialchars($candidate['position']); ?></h4>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="status-pending">Pending Approval</span>
                            </p>
                            <p><strong>Bio:</strong> <?php echo htmlspecialchars(substr($candidate['bio'], 0, 100)) . (strlen($candidate['bio']) > 100 ? '...' : ''); ?></p>
                            <p><strong>Goals:</strong> <?php echo nl2br(htmlspecialchars(substr($candidate['goals'], 0, 100))) . (strlen($candidate['goals']) > 100 ? '...' : ''); ?></p>
                            <p><strong>Targets:</strong> <?php echo nl2br(htmlspecialchars(substr($candidate['targets'], 0, 100))) . (strlen($candidate['targets']) > 100 ? '...' : ''); ?></p>
                            
                            <div class="action-buttons">
                                <!-- Only 2 buttons for unapproved candidates: Approve and Reject (Delete) -->
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                    <button type="submit" name="approve" class="btn-approve">✓ Approve</button>
                                </form>
                                <form method="POST" class="inline-form" onsubmit="return confirmReject()">
                                    <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                    <button type="submit" name="reject" class="btn-reject">✗ Reject & Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Approved Candidates Section -->
                <?php 
                $approved_candidates = array_filter($candidates, function($c) { return $c['approved']; });
                if (!empty($approved_candidates)): ?>
                    <div class="section-title approved-title">
                        Approved Candidates (<?php echo count($approved_candidates); ?>)
                    </div>
                    <?php foreach ($approved_candidates as $candidate): ?>
                        <div class="candidate-card approved">
                            <h4><?php echo htmlspecialchars($candidate['name']); ?> - <?php echo htmlspecialchars($candidate['position']); ?></h4>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
                            <p><strong>Votes:</strong> <?php echo $candidate['votes']; ?></p>
                            <p><strong>Status:</strong> 
                                <span class="status-approved">✓ Approved</span>
                            </p>
                            <p><strong>Bio:</strong> <?php echo htmlspecialchars(substr($candidate['bio'], 0, 100)) . (strlen($candidate['bio']) > 100 ? '...' : ''); ?></p>
                            <p><strong>Goals:</strong> <?php echo nl2br(htmlspecialchars(substr($candidate['goals'], 0, 100))) . (strlen($candidate['goals']) > 100 ? '...' : ''); ?></p>
                            <p><strong>Targets:</strong> <?php echo nl2br(htmlspecialchars(substr($candidate['targets'], 0, 100))) . (strlen($candidate['targets']) > 100 ? '...' : ''); ?></p>
                            
                            <div class="action-buttons">
                                <span class="approved-status">✓ Approved for election</span>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                    <button type="submit" name="delete" class="btn-delete" onclick="return confirmDelete()">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (empty($candidates)): ?>
                    <div class="alert info">No candidates registered.</div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function toLocalISOString(date) {
            const pad = n => String(n).padStart(2, '0');
            return date.getFullYear() + '-' +
                   pad(date.getMonth() + 1) + '-' +
                   pad(date.getDate()) + 'T' +
                   pad(date.getHours()) + ':' +
                   pad(date.getMinutes());
        }

        const startInput = document.getElementById('start_date');
        const endInput   = document.getElementById('end_date');
        const endHint    = document.getElementById('end_date_hint');
        const startIso   = document.getElementById('start_iso');
        const endIso     = document.getElementById('end_iso');
        const start_local = document.getElementById('start_local');
        const end_local = document.getElementById('end_local');
        const datesLocked = <?php echo $dates_locked ? 'true' : 'false'; ?>;

        function refreshStartMin() {
            startInput.min = toLocalISOString(new Date());
        }

        function onStartChange() {
            const startVal = startInput.value;

            if (!startVal) {
                endInput.disabled = true;
                endInput.value    = '';
                endInput.min      = '';
                endHint.textContent = 'Select a start date first';
                return;
            }

            const startDate = new Date(startVal);
            const now       = new Date();

            if (startDate <= now) {
                startInput.setCustomValidity('Start date must be in the future.');
                startInput.reportValidity();
                endInput.disabled = true;
                endInput.value    = '';
                endHint.textContent = 'Fix the start date first';
                return;
            }

            startInput.setCustomValidity('');

            const minEnd = new Date(startDate.getTime() + 60000);
            endInput.min      = toLocalISOString(minEnd);
            endInput.disabled = false;
            endHint.textContent = 'Must be after the start date';

            if (endInput.value && new Date(endInput.value) <= startDate) {
                endInput.value = '';
            }

            if (endInput.value) onEndChange();
        }

        function onEndChange() {
            if (!startInput.value || !endInput.value) return;
            const startDate = new Date(startInput.value);
            const endDate   = new Date(endInput.value);

            if (endDate <= startDate) {
                endInput.setCustomValidity('End date must be after the start date.');
            } else {
                endInput.setCustomValidity('');
            }
        }

        function validateDates() {
            const now = new Date();
            const startDate = startInput.value ? new Date(startInput.value) : null;
            const endDate   = endInput.value   ? new Date(endInput.value)   : null;

            if (!startDate) {
                showError(startInput, 'Please select a start date.');
                return false;
            }
            if (startDate <= now) {
                showError(startInput, 'Start date must be in the future.');
                return false;
            }
            if (!endDate) {
                showError(endInput, 'Please select an end date.');
                return false;
            }
            if (endDate <= startDate) {
                showError(endInput, 'End date must be after the start date.');
                return false;
            }
            return true;
        }

        function prepareDatesForSubmit() {
            if (datesLocked) {
                alert('Dates are locked and cannot be changed from the UI. Modify the settings table to update them.');
                return false;
            }
            if (!validateDates()) return false;

            try {
                if (startInput.value) {
                    const startDt = new Date(startInput.value);
                    startIso.value = toISOWithOffset(startDt);
                    start_local.value = startInput.value;
                }
                if (endInput.value) {
                    const endDt = new Date(endInput.value);
                    endIso.value = toISOWithOffset(endDt);
                    end_local.value = endInput.value;
                }
            } catch (e) {
                alert('Failed to convert dates to timezone-aware format. Please try re-entering the dates.');
                return false;
            }

            return true;
        }

        function toISOWithOffset(d) {
            const pad = n => String(n).padStart(2, '0');
            const yyyy = d.getFullYear();
            const MM = pad(d.getMonth() + 1);
            const dd = pad(d.getDate());
            const hh = pad(d.getHours());
            const mm = pad(d.getMinutes());
            const ss = pad(d.getSeconds());

            const tzMin = -d.getTimezoneOffset();
            const sign = tzMin >= 0 ? '+' : '-';
            const tzH = pad(Math.floor(Math.abs(tzMin) / 60));
            const tzM = pad(Math.abs(tzMin) % 60);

            return `${yyyy}-${MM}-${dd}T${hh}:${mm}:${ss}${sign}${tzH}:${tzM}`;
        }

        function showError(input, msg) {
            input.setCustomValidity(msg);
            input.reportValidity();
            setTimeout(() => input.setCustomValidity(''), 3000);
        }

        document.addEventListener('DOMContentLoaded', function () {
            refreshStartMin();
            setInterval(refreshStartMin, 30000);

            // If a start date was already saved, unlock the end field
            // If server stored a timezone-aware ISO, convert it to local datetime-local for display
            const storedStart = startInput.dataset.storedIso;
            const storedEnd = endInput.dataset.storedIso;
                // Prefer stored local wall-clock if available (preserves admin-entered display)
                const storedLocalStart = startInput.dataset.storedLocal;
                const storedLocalEnd = endInput.dataset.storedLocal;

                if (storedLocalStart) {
                    startInput.value = storedLocalStart;
                } else if (storedStart) {
                    try {
                        const d = new Date(storedStart);
                        startInput.value = toLocalISOString(d);
                    } catch (e) {
                        // ignore parse errors
                    }
                }

                if (storedLocalEnd) {
                    endInput.value = storedLocalEnd;
                    if (!datesLocked) endInput.disabled = false;
                } else if (storedEnd) {
                    try {
                        const d2 = new Date(storedEnd);
                        endInput.value = toLocalISOString(d2);
                        if (!datesLocked) endInput.disabled = false;
                    } catch (e) {
                        // ignore
                    }
                }

                if (startInput.value) onStartChange();

            startInput.addEventListener('change', onStartChange);
            startInput.addEventListener('input',  onStartChange);
            endInput.addEventListener('change', onEndChange);
            endInput.addEventListener('input',  onEndChange);
        });

        // ─── Confirmation helpers ─────────────────────────────────────
        function confirmReject() {
            return confirm('Are you sure you want to REJECT and DELETE this candidate?\n\nThis will permanently remove their candidacy.');
        }
        function confirmDelete() {
            return confirm('Are you sure you want to DELETE this candidate?\n\nThis will also delete all votes cast for them.');
        }
    </script>
</body>
</html>