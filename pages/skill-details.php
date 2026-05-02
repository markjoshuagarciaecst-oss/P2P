<?php
require_once '../config/database.php';
require_once '../classes/Skill.php';
require_once '../classes/User.php';
require_once '../classes/Booking.php';
require_once '../classes/Review.php';

$pageTitle = 'Skill Details';

// Get skill ID
$skillId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$skillId) {
    redirect('skills.php');
}

$skillObj = new Skill();
$userObj = new User();
$bookingObj = new Booking();
$reviewObj = new Review();

// Get skill details
$skill = $skillObj->getSkillById($skillId);

if (!$skill) {
    $error = 'Skill not found';
}

$error = '';
$success = '';

// Handle booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $scheduledDate = sanitize($_POST['scheduled_date'] ?? '');
    $scheduledTime = sanitize($_POST['scheduled_time'] ?? '');
    
    if (empty($scheduledDate) || empty($scheduledTime)) {
        $error = 'Please select date and time';
    } elseif (strtotime($scheduledDate) < strtotime('today')) {
        $error = 'Please select a future date';
    } else {
        // Check if user has enough points
        $currentUser = $userObj->getUserById(getUserId());
        if ($currentUser['points'] < $skill['points_required']) {
            $error = 'Insufficient points. Please earn more points first.';
        } elseif ($skill['user_id'] == getUserId()) {
            $error = 'You cannot book your own skill';
        } else {
            $bookingId = $bookingObj->create(getUserId(), $skill['user_id'], $skillId, $scheduledDate, $scheduledTime);
            if ($bookingId) {
                $success = 'Booking request sent successfully!';
            } else {
                $error = 'Failed to create booking. Please try again.';
            }
        }
    }
}

// Get teacher reviews
$teacherReviews = $reviewObj->getUserReviews($skill['user_id'] ?? 0, 5);
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$skill): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>Skill not found. <a href="skills.php">Browse skills</a>
    </div>
    <?php else: ?>
    <div class="row">
        <!-- Skill Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <span class="skill-category"><?php echo sanitize($skill['category']); ?></span>
                    <span class="skill-level <?php echo strtolower($skill['skill_level']); ?>">
                        <?php echo sanitize($skill['skill_level']); ?>
                    </span>
                    
                    <h2 class="mt-3"><?php echo sanitize($skill['title']); ?></h2>
                    
                    <div class="d-flex align-items-center mb-4">
                        <img src="<?php echo asset($skill['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                             alt="<?php echo sanitize($skill['teacher_name']); ?>" 
                             class="profile-avatar-lg rounded-circle me-3">
                        <div>
                            <h5 class="mb-1"><?php echo sanitize($skill['teacher_name']); ?></h5>
                            <div class="rating-stars">
                                <?php 
                                $rating = $skill['average_rating'] ?? 0;
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                                <small class="text-muted">(<?php echo number_format($rating, 1); ?>)</small>
                            </div>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="points-badge">
                                <i class="fas fa-coins"></i> <?php echo $skill['points_required']; ?> pts
                            </div>
                            <small class="text-muted">per session</small>
                        </div>
                    </div>
                    
                    <h4>Description</h4>
                    <p><?php echo nl2br(sanitize($skill['description'])); ?></p>
                    
                    <hr>
                    
                    <h4>About the Teacher</h4>
                    <p><?php echo nl2br(sanitize($skill['bio'] ?? 'No bio available.')); ?></p>
                </div>
            </div>
            
            <!-- Teacher Reviews -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($teacherReviews)): ?>
                    <p class="text-muted">No reviews yet</p>
                    <?php else: ?>
                        <?php foreach ($teacherReviews as $review): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <img src="<?php echo asset($review['reviewer_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                                     alt="<?php echo sanitize($review['reviewer_name']); ?>" 
                                     class="profile-avatar-sm rounded-circle me-2">
                                <div>
                                    <strong><?php echo sanitize($review['reviewer_name']); ?></strong>
                                    <div class="rating-stars">
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <small class="text-muted ms-auto"><?php echo formatDate($review['created_at']); ?></small>
                            </div>
                            <p class="mb-0"><?php echo sanitize($review['comment'] ?? ''); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Booking Form -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>Book Session</h5>
                </div>
                <div class="card-body">
                    <?php if (!isLoggedIn()): ?>
                    <div class="text-center py-3">
                        <p>Please login to book this session</p>
                        <a href="login.php?redirect=skill-details.php?id=<?php echo $skillId; ?>" class="btn btn-primary">Login</a>
                    </div>
                    <?php else: ?>
                        <?php 
                        $currentUser = $userObj->getUserById(getUserId());
                        if ($currentUser['points'] < $skill['points_required']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You need <?php echo $skill['points_required']; ?> points but you only have <?php echo $currentUser['points']; ?> points.
                            <br><a href="skills.php">Browse skills to teach</a> and earn more points!
                        </div>
                        <?php elseif ($skill['user_id'] == getUserId()): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>This is your skill listing.
                        </div>
                        <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="scheduled_date" class="form-label">Select Date</label>
                                <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="scheduled_time" class="form-label">Select Time</label>
                                <select class="form-select" id="scheduled_time" name="scheduled_time" required>
                                    <option value="">Select time</option>
                                    <option value="09:00:00">9:00 AM</option>
                                    <option value="10:00:00">10:00 AM</option>
                                    <option value="11:00:00">11:00 AM</option>
                                    <option value="12:00:00">12:00 PM</option>
                                    <option value="13:00:00">1:00 PM</option>
                                    <option value="14:00:00">2:00 PM</option>
                                    <option value="15:00:00">3:00 PM</option>
                                    <option value="16:00:00">4:00 PM</option>
                                    <option value="17:00:00">5:00 PM</option>
                                    <option value="18:00:00">6:00 PM</option>
                                    <option value="19:00:00">7:00 PM</option>
                                    <option value="20:00:00">8:00 PM</option>
                                </select>
                            </div>
                            
                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    This will cost <strong><?php echo $skill['points_required']; ?> points</strong> from your balance.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Send Booking Request
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Teacher Stats -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Teacher Stats</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $teacherStats = $userObj->getUserStats($skill['user_id']);
                    ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="stat-value text-primary"><?php echo $teacherStats['sessions_completed']; ?></div>
                            <small class="text-muted">Sessions</small>
                        </div>
                        <div class="col-4">
                            <div class="stat-value text-success"><?php echo $teacherStats['total_earned']; ?></div>
                            <small class="text-muted">Earned</small>
                        </div>
                        <div class="col-4">
                            <div class="stat-value text-warning"><?php echo number_format($skill['average_rating'] ?? 0, 1); ?></div>
                            <small class="text-muted">Rating</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>