<?php
require_once '../config/database.php';
require_once '../classes/Booking.php';
require_once '../classes/Notification.php';

$pageTitle = 'Booking Management';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$db         = Database::getInstance();
$bookingObj = new Booking();

$success = '';
$error   = '';

// ── Handle actions ────────────────────────────────────────────────────────────
$action    = $_GET['action']    ?? '';
$bookingId = (int)($_GET['id'] ?? 0);

if ($action && $bookingId) {
    // Fetch booking for notifications
    $bRow = $db->prepare("SELECT b.*, s.title as skill_title,
                                  l.name as learner_name, t.name as teacher_name
                           FROM bookings b
                           JOIN skills s ON s.id = b.skill_id
                           JOIN users  l ON l.id = b.learner_id
                           JOIN users  t ON t.id = b.teacher_id
                           WHERE b.id = ?");
    $bRow->execute([$bookingId]);
    $bRow = $bRow->fetch();

    switch ($action) {
        case 'cancel':
            if ($bRow && in_array($bRow['status'], ['pending','accepted'])) {
                $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")
                   ->execute([$bookingId]);
                // Notify both parties
                $notif = new Notification();
                $notif->create($bRow['learner_id'], 'Booking Cancelled by Admin',
                    "Your booking for '{$bRow['skill_title']}' has been cancelled by an administrator.", 'general');
                $notif->create($bRow['teacher_id'], 'Booking Cancelled by Admin',
                    "The booking for '{$bRow['skill_title']}' with {$bRow['learner_name']} has been cancelled by an administrator.", 'general');
                $success = 'Booking cancelled and both parties notified.';
            } else {
                $error = 'Booking cannot be cancelled (wrong status).';
            }
            break;

        case 'force_complete':
            if ($bRow && $bRow['status'] === 'accepted') {
                // Force-set both confirmed flags then complete
                try {
                    $db->prepare("UPDATE bookings SET teacher_confirmed=1, learner_confirmed=1 WHERE id=?")
                       ->execute([$bookingId]);
                } catch (PDOException $e) { /* columns may not exist yet */ }

                if ($bookingObj->complete($bookingId, $bRow['teacher_id'])) {
                    $success = 'Session force-completed and points transferred.';
                } else {
                    $error = 'Force-complete failed. Check the learner has enough points.';
                }
            } else {
                $error = 'Only accepted bookings can be force-completed.';
            }
            break;

        case 'delete':
            try {
                $db->prepare("DELETE FROM bookings WHERE id=?")->execute([$bookingId]);
                $success = 'Booking permanently deleted.';
                $bookingId = 0;
            } catch (PDOException $e) {
                $error = 'Cannot delete — booking has related records (reviews/transactions).';
            }
            break;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter = sanitize($_GET['filter'] ?? 'all');
$search = sanitize($_GET['search'] ?? '');

$where  = [];
$params = [];

if ($filter !== 'all') {
    $where[] = "b.status = ?";
    $params[] = $filter;
}

if ($search) {
    $where[] = "(s.title LIKE ? OR l.name LIKE ? OR t.name LIKE ? OR l.email LIKE ? OR t.email LIKE ?)";
    $params = array_merge($params, array_fill(0, 5, "%$search%"));
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Duration / confirmed columns may not exist yet
$durCol  = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='session_duration'")->fetchColumn()
    ? "IFNULL(b.session_duration,1)" : "1";
$tcCol   = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='teacher_confirmed'")->fetchColumn()
    ? "IFNULL(b.teacher_confirmed,0)" : "0";
$lcCol   = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='learner_confirmed'")->fetchColumn()
    ? "IFNULL(b.learner_confirmed,0)" : "0";

$stmt = $db->prepare("
    SELECT b.*,
           s.title AS skill_title, s.points_required,
           (s.points_required * $durCol) AS total_points,
           $durCol  AS session_duration,
           $tcCol   AS teacher_confirmed,
           $lcCol   AS learner_confirmed,
           l.name  AS learner_name,  l.email AS learner_email,
           t.name  AS teacher_name,  t.email AS teacher_email
    FROM bookings b
    JOIN skills s ON s.id = b.skill_id
    JOIN users  l ON l.id = b.learner_id
    JOIN users  t ON t.id = b.teacher_id
    $whereSQL
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Summary counts (always global)
$counts = $db->query("SELECT status, COUNT(*) c FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalAll       = array_sum($counts);
$totalPending   = $counts['pending']   ?? 0;
$totalAccepted  = $counts['accepted']  ?? 0;
$totalCompleted = $counts['completed'] ?? 0;
$totalCancelled = ($counts['cancelled'] ?? 0) + ($counts['rejected'] ?? 0);
$totalPoints    = $db->query("SELECT COALESCE(SUM(amount),0) FROM point_transactions WHERE type='earned'")->fetchColumn();

function qs(array $extra = [], array $remove = []) {
    $base = array_diff_key($_GET, array_flip(array_merge(['action','id'], $remove)));
    return http_build_query(array_merge($base, $extra));
}
?>
<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check me-2"></i>Booking Management</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-primary"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-value"><?php echo $totalAll; ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo $totalPending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-info"><i class="fas fa-handshake"></i></div>
                <div class="stat-value"><?php echo $totalAccepted; ?></div>
                <div class="stat-label">Accepted</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-success"><i class="fas fa-check-double"></i></div>
                <div class="stat-value"><?php echo $totalCompleted; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
                <div class="stat-value"><?php echo $totalCancelled; ?></div>
                <div class="stat-label">Cancelled/Rejected</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-coins"></i></div>
                <div class="stat-value"><?php echo number_format($totalPoints); ?></div>
                <div class="stat-label">Points Transferred</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="filter" class="form-select">
                        <option value="all"       <?php echo $filter==='all'       ?'selected':''; ?>>All Bookings</option>
                        <option value="pending"   <?php echo $filter==='pending'   ?'selected':''; ?>>Pending</option>
                        <option value="accepted"  <?php echo $filter==='accepted'  ?'selected':''; ?>>Accepted</option>
                        <option value="completed" <?php echo $filter==='completed' ?'selected':''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter==='cancelled' ?'selected':''; ?>>Cancelled</option>
                        <option value="rejected"  <?php echo $filter==='rejected'  ?'selected':''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Skill title, learner or teacher name / email"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bookings table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Bookings (<?php echo count($bookings); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($bookings)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                <p>No bookings found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Skill</th>
                            <th>Learner</th>
                            <th>Teacher</th>
                            <th>Schedule</th>
                            <th>Duration</th>
                            <th>Points</th>
                            <th>Confirmed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $b['id']; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($b['skill_title']); ?></div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($b['learner_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($b['learner_email']); ?></small>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($b['teacher_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($b['teacher_email']); ?></small>
                            </td>
                            <td>
                                <div><?php echo formatDate($b['scheduled_date']); ?></div>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($b['scheduled_time'])); ?></small>
                            </td>
                            <td class="text-center"><?php echo $b['session_duration']; ?> hr</td>
                            <td>
                                <span class="badge bg-primary"><?php echo $b['total_points']; ?> pts</span>
                            </td>
                            <td class="text-center small">
                                <span title="Teacher confirmed" class="<?php echo $b['teacher_confirmed'] ? 'text-success' : 'text-muted'; ?>">
                                    <i class="fas fa-chalkboard-teacher"></i> <?php echo $b['teacher_confirmed'] ? '✓' : '–'; ?>
                                </span>
                                <br>
                                <span title="Learner confirmed" class="<?php echo $b['learner_confirmed'] ? 'text-success' : 'text-muted'; ?>">
                                    <i class="fas fa-user-graduate"></i> <?php echo $b['learner_confirmed'] ? '✓' : '–'; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusColors = [
                                    'pending'   => 'warning',
                                    'accepted'  => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'secondary',
                                    'rejected'  => 'danger',
                                ];
                                $sc = $statusColors[$b['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $sc; ?>"><?php echo ucfirst($b['status']); ?></span>
                            </td>
                            <td>
                                <?php if (in_array($b['status'], ['pending','accepted'])): ?>
                                <!-- Cancel -->
                                <a href="?action=cancel&id=<?php echo $b['id']; ?>&<?php echo qs(); ?>"
                                   class="btn btn-sm btn-outline-warning mb-1"
                                   onclick="return confirm('Cancel this booking and notify both parties?')"
                                   title="Cancel booking">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <?php endif; ?>

                                <?php if ($b['status'] === 'accepted'): ?>
                                <!-- Force complete -->
                                <a href="?action=force_complete&id=<?php echo $b['id']; ?>&<?php echo qs(); ?>"
                                   class="btn btn-sm btn-outline-success mb-1"
                                   onclick="return confirm('Force-complete this session and transfer points now?')"
                                   title="Force complete">
                                    <i class="fas fa-check-double me-1"></i>Force Complete
                                </a>
                                <?php endif; ?>

                                <?php if (in_array($b['status'], ['cancelled','rejected'])): ?>
                                <!-- Permanent delete -->
                                <a href="?action=delete&id=<?php echo $b['id']; ?>&<?php echo qs(); ?>"
                                   class="btn btn-sm btn-outline-danger mb-1"
                                   onclick="return confirm('Permanently delete this booking record?')"
                                   title="Delete record">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
