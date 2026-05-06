<?php
require_once '../config/database.php';
require_once '../classes/Booking.php';
require_once '../classes/User.php';

$pageTitle = 'Booking Management';

// Check if admin is logged in
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$bookingObj = new Booking();
$userObj = new User();
$db = Database::getInstance();

// Handle actions
$action = $_GET['action'] ?? '';
$bookingId = (int)($_GET['id'] ?? 0);

if ($action && $bookingId) {
    switch ($action) {
        case 'cancel':
            $bookingObj->cancel($bookingId, getUserId());
            $success = 'Booking has been cancelled by admin.';
            break;
    }
}

// Get filter
$filter = sanitize($_GET['filter'] ?? 'all');
$search = sanitize($_GET['search'] ?? '');

// Build query
$query = "SELECT b.*, s.title as skill_title, s.points_required,
          l.name as learner_name, l.email as learner_email,
          t.name as teacher_name, t.email as teacher_email
          FROM bookings b
          INNER JOIN skills s ON b.skill_id = s.id
          INNER JOIN users l ON b.learner_id = l.id
          INNER JOIN users t ON b.teacher_id = t.id";

$params = [];

if ($filter !== 'all') {
    $query .= " WHERE b.status = ?";
    $params[] = $filter;
}

if ($search) {
    $whereClause = $filter !== 'all' ? " AND" : " WHERE";
    $query .= " $whereClause (s.title LIKE ? OR l.name LIKE ? OR t.name LIKE ? OR l.email LIKE ? OR t.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get statistics
$totalBookings = count($bookings);
$pendingBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$acceptedBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'accepted'));
$completedBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'completed'));
$cancelledBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check me-2"></i>Booking Management</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-primary"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-value"><?php echo $totalBookings; ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo $pendingBookings; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-info"><i class="fas fa-check"></i></div>
                <div class="stat-value"><?php echo $acceptedBookings; ?></div>
                <div class="stat-label">Accepted</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $completedBookings; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-danger"><i class="fas fa-times"></i></div>
                <div class="stat-value"><?php echo $cancelledBookings; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card">
                <div class="stat-icon text-secondary"><i class="fas fa-coins"></i></div>
                <div class="stat-value">
                    <?php
                    $totalPoints = array_sum(array_column($bookings, 'points_required'));
                    echo $totalPoints;
                    ?>
                </div>
                <div class="stat-label">Points Exchanged</div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status Filter</label>
                    <select name="filter" class="form-select">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Bookings</option>
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="accepted" <?php echo $filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by skill, learner, or teacher" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Bookings (<?php echo count($bookings); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Skill</th>
                            <th>Learner</th>
                            <th>Teacher</th>
                            <th>Schedule</th>
                            <th>Points</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo sanitize($booking['skill_title']); ?></div>
                            </td>
                            <td>
                                <div><?php echo sanitize($booking['learner_name']); ?></div>
                                <small class="text-muted"><?php echo sanitize($booking['learner_email']); ?></small>
                            </td>
                            <td>
                                <div><?php echo sanitize($booking['teacher_name']); ?></div>
                                <small class="text-muted"><?php echo sanitize($booking['teacher_email']); ?></small>
                            </td>
                            <td>
                                <div><?php echo formatDate($booking['scheduled_date']); ?></div>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($booking['scheduled_time'])); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $booking['points_required']; ?> pts</span>
                            </td>
                            <td>
                                <span class="booking-status <?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($booking['created_at']); ?></td>
                            <td>
                                <?php if (in_array($booking['status'], ['pending', 'accepted'])): ?>
                                <a href="?action=cancel&id=<?php echo $booking['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])); ?>"
                                   class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this booking?')">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>