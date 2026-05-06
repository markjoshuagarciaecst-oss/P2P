<?php
require_once '../config/database.php';

$pageTitle = 'Activity Reports';

// Check if admin is logged in
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

// Get date range
$period = sanitize($_GET['period'] ?? '7days');
$startDate = '';
$endDate = date('Y-m-d H:i:s');

switch ($period) {
    case '24hours':
        $startDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
        break;
    case '7days':
        $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        break;
    case '30days':
        $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        break;
    case '90days':
        $startDate = date('Y-m-d H:i:s', strtotime('-90 days'));
        break;
}

// Get activity reports
$db = Database::getInstance();

// User registrations
$stmt = $db->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY date DESC");
$stmt->execute([$startDate, $endDate]);
$userRegistrations = $stmt->fetchAll();

// Bookings by status
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM bookings WHERE created_at BETWEEN ? AND ? GROUP BY status");
$stmt->execute([$startDate, $endDate]);
$bookingStats = $stmt->fetchAll();

// Point transactions
$stmt = $db->prepare("SELECT type, SUM(amount) as total, COUNT(*) as count FROM point_transactions WHERE created_at BETWEEN ? AND ? GROUP BY type");
$stmt->execute([$startDate, $endDate]);
$pointStats = $stmt->fetchAll();

// Top active users
$stmt = $db->prepare("
    SELECT u.name, u.email, COUNT(b.id) as booking_count
    FROM users u
    LEFT JOIN bookings b ON (u.id = b.learner_id OR u.id = b.teacher_id) AND b.created_at BETWEEN ? AND ?
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY booking_count DESC
    LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$topUsers = $stmt->fetchAll();

// Recent activities (last 50)
$stmt = $db->prepare("
    SELECT 'user_registration' as type, u.name as actor, '' as target, u.created_at as date
    FROM users u WHERE u.created_at BETWEEN ? AND ?
    UNION ALL
    SELECT 'booking_created' as type, l.name as actor, CONCAT('booked ', s.title, ' with ', t.name) as target, b.created_at as date
    FROM bookings b
    JOIN users l ON b.learner_id = l.id
    JOIN users t ON b.teacher_id = t.id
    JOIN skills s ON b.skill_id = s.id
    WHERE b.created_at BETWEEN ? AND ?
    UNION ALL
    SELECT CONCAT('booking_', b.status) as type, t.name as actor, CONCAT(s.title, ' with ', l.name) as target, b.updated_at as date
    FROM bookings b
    JOIN users l ON b.learner_id = l.id
    JOIN users t ON b.teacher_id = t.id
    JOIN skills s ON b.skill_id = s.id
    WHERE b.updated_at BETWEEN ? AND ? AND b.status IN ('accepted', 'completed', 'cancelled', 'rejected')
    ORDER BY date DESC
    LIMIT 50
");
$stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
$recentActivities = $stmt->fetchAll();

// Skill popularity
$stmt = $db->prepare("
    SELECT s.title, s.category, COUNT(b.id) as booking_count, AVG(s.points_required) as avg_points
    FROM skills s
    LEFT JOIN bookings b ON s.id = b.skill_id AND b.created_at BETWEEN ? AND ?
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY booking_count DESC
    LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$popularSkills = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-bar me-2"></i>Activity Reports</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <!-- Period Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Report Period</label>
                    <select name="period" class="form-select">
                        <option value="24hours" <?php echo $period === '24hours' ? 'selected' : ''; ?>>Last 24 Hours</option>
                        <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90days" <?php echo $period === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- User Registrations -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>User Registrations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>New Users</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userRegistrations as $reg): ?>
                                <tr>
                                    <td><?php echo formatDate($reg['date']); ?></td>
                                    <td><?php echo $reg['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($userRegistrations)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No registrations in this period</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Statistics -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Booking Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookingStats as $stat): ?>
                                <tr>
                                    <td><?php echo ucfirst($stat['status']); ?></td>
                                    <td><?php echo $stat['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($bookingStats)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No bookings in this period</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Point Transactions -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-coins me-2"></i>Point Transactions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Total Points</th>
                                    <th>Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pointStats as $stat): ?>
                                <tr>
                                    <td><?php echo ucfirst($stat['type']); ?></td>
                                    <td><?php echo $stat['total']; ?> pts</td>
                                    <td><?php echo $stat['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($pointStats)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No transactions in this period</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Active Users -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Most Active Users</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topUsers as $user): ?>
                                <tr>
                                    <td><?php echo sanitize($user['name']); ?></td>
                                    <td><?php echo $user['booking_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topUsers)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No activity in this period</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Popular Skills -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Popular Skills</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Skill</th>
                                    <th>Category</th>
                                    <th>Bookings</th>
                                    <th>Avg Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($popularSkills as $skill): ?>
                                <tr>
                                    <td><?php echo sanitize($skill['title']); ?></td>
                                    <td><?php echo sanitize($skill['category']); ?></td>
                                    <td><?php echo $skill['booking_count']; ?></td>
                                    <td><?php echo round($skill['avg_points'], 1); ?> pts</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($popularSkills)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No skills data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Feed -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity Feed</h5>
                </div>
                <div class="card-body">
                    <div class="activity-feed">
                        <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item d-flex mb-3">
                            <div class="activity-icon me-3">
                                <?php
                                $icon = 'fas fa-circle';
                                $color = 'text-muted';
                                switch ($activity['type']) {
                                    case 'user_registration':
                                        $icon = 'fas fa-user-plus';
                                        $color = 'text-primary';
                                        break;
                                    case 'booking_created':
                                        $icon = 'fas fa-calendar-plus';
                                        $color = 'text-info';
                                        break;
                                    case 'booking_accepted':
                                        $icon = 'fas fa-check';
                                        $color = 'text-success';
                                        break;
                                    case 'booking_completed':
                                        $icon = 'fas fa-check-double';
                                        $color = 'text-success';
                                        break;
                                    case 'booking_cancelled':
                                    case 'booking_rejected':
                                        $icon = 'fas fa-times';
                                        $color = 'text-danger';
                                        break;
                                }
                                ?>
                                <i class="<?php echo $icon; ?> <?php echo $color; ?>"></i>
                            </div>
                            <div class="activity-content flex-grow-1">
                                <div class="activity-text">
                                    <strong><?php echo sanitize($activity['actor']); ?></strong>
                                    <?php
                                    switch ($activity['type']) {
                                        case 'user_registration':
                                            echo 'joined the platform';
                                            break;
                                        case 'booking_created':
                                            echo $activity['target'];
                                            break;
                                        case 'booking_accepted':
                                            echo 'accepted booking for ' . $activity['target'];
                                            break;
                                        case 'booking_completed':
                                            echo 'completed session: ' . $activity['target'];
                                            break;
                                        case 'booking_cancelled':
                                            echo 'cancelled booking: ' . $activity['target'];
                                            break;
                                        case 'booking_rejected':
                                            echo 'rejected booking: ' . $activity['target'];
                                            break;
                                    }
                                    ?>
                                </div>
                                <small class="text-muted"><?php echo formatDateTime($activity['date']); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentActivities)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No activities in this period</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.activity-feed {
    max-height: 600px;
    overflow-y: auto;
}

.activity-item {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 30px;
    text-align: center;
    font-size: 1.1rem;
}

.activity-text {
    margin-bottom: 2px;
}
</style>

<?php include '../includes/footer.php'; ?>