<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Skill.php';
require_once '../classes/Booking.php';
require_once '../classes/Transaction.php';
require_once '../classes/Notification.php';

$pageTitle = 'Dashboard';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=dashboard.php');
}

$userObj = new User();
$skillObj = new Skill();
$bookingObj = new Booking();
$transactionObj = new Transaction();
$notificationObj = new Notification();

// Get current user data
$currentUser = $userObj->getUserById(getUserId());
$stats = $userObj->getUserStats(getUserId());
$upcomingSessions = $bookingObj->getUpcomingSessions(getUserId(), 5);
$mySkills = $skillObj->getUserSkills(getUserId());
$recentTransactions = $transactionObj->getRecentTransactions(getUserId(), 5);
$pendingRequests = $bookingObj->getPendingRequests(getUserId());
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="<?php echo asset($currentUser['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                         alt="Profile" class="profile-avatar mb-3">
                    <h5><?php echo sanitize($currentUser['name']); ?></h5>
                    <p class="text-muted"><?php echo sanitize($currentUser['email']); ?></p>
                    <div class="points-badge mx-auto mb-3" style="width: fit-content;">
                        <i class="fas fa-coins"></i> <?php echo $currentUser['points']; ?> Points
                    </div>
                    <div class="rating-stars">
                        <?php 
                        $rating = $currentUser['average_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                        }
                        ?>
                        <small class="text-muted">(<?php echo number_format($rating, 1); ?>)</small>
                    </div>
                </div>
            </div>
            
            <div class="list-group mb-4">
                <a href="dashboard.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
                <a href="my-skills.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-graduation-cap me-2"></i>My Skills
                </a>
                <a href="bookings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-calendar-alt me-2"></i>My Bookings
                </a>
                <a href="transactions.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-coins me-2"></i>Transactions
                </a>
                <a href="profile.php?id=<?php echo getUserId(); ?>" class="list-group-item list-group-item-action">
                    <i class="fas fa-user me-2"></i>My Profile
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="stat-icon text-primary"><i class="fas fa-coins"></i></div>
                        <div class="stat-value"><?php echo $currentUser['points']; ?></div>
                        <div class="stat-label">My Points</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="stat-icon text-success"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-value"><?php echo $stats['skills_offered']; ?></div>
                        <div class="stat-label">Skills Offered</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="stat-icon text-info"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-value"><?php echo $stats['sessions_completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card">
                        <div class="stat-icon text-warning"><i class="fas fa-star"></i></div>
                        <div class="stat-value"><?php echo $currentUser['total_reviews']; ?></div>
                        <div class="stat-label">Reviews</div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Requests (for teachers) -->
            <?php if (!empty($pendingRequests)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Pending Booking Requests</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($pendingRequests as $request): ?>
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                        <img src="<?php echo asset($request['learner_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                             alt="<?php echo sanitize($request['learner_name']); ?>" 
                             class="profile-avatar-sm rounded-circle me-3">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo sanitize($request['learner_name']); ?></h6>
                            <p class="mb-1 text-muted"><?php echo sanitize($request['skill_title']); ?></p>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?php echo formatDate($request['scheduled_date']); ?>
                                <i class="fas fa-clock me-1"></i><?php echo date('h:i A', strtotime($request['scheduled_time'])); ?>
                            </small>
                        </div>
                        <div class="points-badge me-3"><?php echo $request['points_required']; ?> pts</div>
                        <div>
                            <button class="btn btn-success btn-sm" onclick="acceptBooking(<?php echo $request['id']; ?>)">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="rejectBooking(<?php echo $request['id']; ?>)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Sessions -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Sessions</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingSessions)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No upcoming sessions</p>
                        <a href="skills.php" class="btn btn-primary">Browse Skills</a>
                    </div>
                    <?php else: ?>
                        <?php foreach ($upcomingSessions as $session): ?>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div class="me-3 text-center">
                                <div class="text-primary fw-bold"><?php echo date('d', strtotime($session['scheduled_date'])); ?></div>
                                <small class="text-muted"><?php echo date('M', strtotime($session['scheduled_date'])); ?></small>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo sanitize($session['skill_title']); ?></h6>
                                <p class="mb-0 text-muted">with <?php echo sanitize($session['other_party_name']); ?></p>
                            </div>
                            <span class="booking-status <?php echo strtolower($session['status']); ?>">
                                <?php echo ucfirst($session['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- My Skills & Recent Transactions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>My Skills</h5>
                            <a href="my-skills.php" class="btn btn-sm btn-outline-primary">Manage</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($mySkills)): ?>
                            <div class="text-center py-3">
                                <p class="text-muted">No skills listed yet</p>
                               
                            </div>
                            <?php else: ?>
                                <ul class="list-unstyled">
                                    <?php foreach (array_slice($mySkills, 0, 3) as $skill): ?>
                                    <li class="mb-2">
                                        <span class="skill-category"><?php echo sanitize($skill['category']); ?></span>
                                        <strong><?php echo sanitize($skill['title']); ?></strong>
                                        <small class="text-muted d-block"><?php echo $skill['points_required']; ?> pts/session</small>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                            <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentTransactions)): ?>
                            <div class="text-center py-3">
                                <p class="text-muted">No transactions yet</p>
                            </div>
                            <?php else: ?>
                                <ul class="list-unstyled">
                                    <?php foreach ($recentTransactions as $trans): ?>
                                    <li class="mb-2 pb-2 border-bottom">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo sanitize($trans['description']); ?></span>
                                            <span class="<?php echo $trans['type'] === 'earned' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                <?php echo $trans['type'] === 'earned' ? '+' : '-'; ?><?php echo $trans['amount']; ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?php echo formatDate($trans['created_at']); ?></small>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>