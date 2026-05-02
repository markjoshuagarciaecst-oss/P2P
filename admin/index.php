<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Skill.php';
require_once '../classes/Booking.php';

$pageTitle = 'Admin Dashboard';

// Check if admin is logged in
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$userObj = new User();
$skillObj = new Skill();
$bookingObj = new Booking();

// Get stats
$db = Database::getInstance();

// Total users
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$totalUsers = $stmt->fetch()['total'];

// Total skills
$stmt = $db->query("SELECT COUNT(*) as total FROM skills WHERE is_active = 1");
$totalSkills = $stmt->fetch()['total'];

// Total bookings
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings");
$totalBookings = $stmt->fetch()['total'];

// Completed sessions
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'completed'");
$completedSessions = $stmt->fetch()['total'];

// Recent users
$stmt = $db->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5");
$recentUsers = $stmt->fetchAll();

// Recent bookings
$stmt = $db->query("SELECT b.*, s.title as skill_title, l.name as learner_name, t.name as teacher_name 
                   FROM bookings b 
                   INNER JOIN skills s ON b.skill_id = s.id 
                   INNER JOIN users l ON b.learner_id = l.id 
                   INNER JOIN users t ON b.teacher_id = t.id 
                   ORDER BY b.created_at DESC LIMIT 5");
$recentBookings = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-cogs me-2"></i>Admin Dashboard</h2>
    
    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-primary"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-success"><i class="fas fa-graduation-cap"></i></div>
                <div class="stat-value"><?php echo $totalSkills; ?></div>
                <div class="stat-label">Active Skills</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-info"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-value"><?php echo $totalBookings; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $completedSessions; ?></div>
                <div class="stat-label">Completed Sessions</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Users -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Recent Users</h5>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Points</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo sanitize($user['name']); ?></td>
                                    <td><?php echo sanitize($user['email']); ?></td>
                                    <td><?php echo $user['points']; ?></td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Recent Bookings</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Skill</th>
                                    <th>Learner</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $booking): ?>
                                <tr>
                                    <td><?php echo sanitize($booking['skill_title']); ?></td>
                                    <td><?php echo sanitize($booking['learner_name']); ?></td>
                                    <td><?php echo sanitize($booking['teacher_name']); ?></td>
                                    <td>
                                        <span class="booking-status <?php echo strtolower($booking['status']); ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="users.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="skills.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-graduation-cap me-2"></i>Manage Skills
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="bookings.php" class="btn btn-outline-info w-100">
                        <i class="fas fa-calendar-alt me-2"></i>Manage Bookings
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="categories.php" class="btn btn-outline-warning w-100">
                        <i class="fas fa-tags me-2"></i>Manage Categories
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>