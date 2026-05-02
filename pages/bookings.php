<?php
require_once '../config/database.php';
require_once '../classes/Booking.php';
require_once '../classes/Review.php';

$pageTitle = 'My Bookings';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=bookings.php');
}

$bookingObj = new Booking();
$reviewObj = new Review();

// Initialize messages
$error = '';
$success = '';

// Get filter
$filter = sanitize($_GET['filter'] ?? 'all');

// Get bookings based on filter
switch ($filter) {
    case 'pending':
        $learnerBookings = $bookingObj->getLearnerBookings(getUserId(), 'pending');
        $teacherBookings = $bookingObj->getTeacherBookings(getUserId(), 'pending');
        break;
    case 'accepted':
        $learnerBookings = $bookingObj->getLearnerBookings(getUserId(), 'accepted');
        $teacherBookings = $bookingObj->getTeacherBookings(getUserId(), 'accepted');
        break;
    case 'completed':
        $learnerBookings = $bookingObj->getLearnerBookings(getUserId(), 'completed');
        $teacherBookings = $bookingObj->getTeacherBookings(getUserId(), 'completed');
        break;
    default:
        $learnerBookings = $bookingObj->getLearnerBookings(getUserId());
        $teacherBookings = $bookingObj->getTeacherBookings(getUserId());
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    $bookingId = (int)$_POST['booking_id'];
    $rating = (int)$_POST['rating'];
    $comment = sanitize($_POST['comment'] ?? '');
    
    $booking = $bookingObj->getBookingById($bookingId);
    
    if ($booking) {
        $revieweeId = ($booking['learner_id'] == getUserId()) ? $booking['teacher_id'] : $booking['learner_id'];
        
        if ($reviewObj->canReview($bookingId, getUserId())) {
            $reviewObj->create($bookingId, getUserId(), $revieweeId, $rating, $comment);
            $success = 'Review submitted successfully!';
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>My Bookings</h2>
    
    <!-- Filters -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">All</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="?filter=pending">Pending</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'accepted' ? 'active' : ''; ?>" href="?filter=accepted">Accepted</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter === 'completed' ? 'active' : ''; ?>" href="?filter=completed">Completed</a>
        </li>
    </ul>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($learnerBookings) && empty($teacherBookings)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h4>No bookings found</h4>
                <p>Start by booking a skill to learn!</p>
                <a href="skills.php" class="btn btn-primary">Browse Skills</a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <!-- As Learner -->
        <?php if (!empty($learnerBookings)): ?>
        <div class="col-12 mb-4">
            <h5 class="text-muted"><i class="fas fa-user-graduate me-2"></i>As Learner</h5>
            <?php foreach ($learnerBookings as $booking): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="<?php echo asset($booking['teacher_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                                 alt="<?php echo sanitize($booking['teacher_name']); ?>" 
                                 class="profile-avatar-lg rounded-circle">
                        </div>
                        <div class="col-md-6">
                            <h5><?php echo sanitize($booking['skill_title']); ?></h5>
                            <p class="mb-1">with <strong><?php echo sanitize($booking['teacher_name']); ?></strong></p>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?php echo formatDate($booking['scheduled_date']); ?>
                                <i class="fas fa-clock me-1"></i><?php echo date('h:i A', strtotime($booking['scheduled_time'])); ?>
                            </small>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="points-badge"><?php echo $booking['points_required']; ?> pts</span>
                        </div>
                        <div class="col-md-2 text-end">
                            <span class="booking-status <?php echo strtolower($booking['status']); ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                            <?php if ($booking['status'] === 'accepted'): ?>
                            <a href="chat.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info mt-2 d-block">
                                <i class="fas fa-comments me-1"></i>Chat
                            </a>
                            <?php elseif ($booking['status'] === 'completed'): ?>
                                <?php 
                                $existingReview = $reviewObj->getReviewByBooking($booking['id'], getUserId());
                                if (!$existingReview): 
                                ?>
                                <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $booking['id']; ?>">
                                    <i class="fas fa-star me-1"></i>Review
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Review Modal -->
            <?php if ($booking['status'] === 'completed'): ?>
            <?php 
            $existingReview = $reviewObj->getReviewByBooking($booking['id'], getUserId());
            if (!$existingReview): 
            ?>
            <div class="modal fade" id="reviewModal<?php echo $booking['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Leave a Review</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="review">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <div class="modal-body">
                                <div class="mb-3 text-center">
                                    <label class="form-label">Rating</label>
                                    <div class="rating-stars" style="font-size: 2rem;">
                                        <i class="far fa-star" data-value="1"></i>
                                        <i class="far fa-star" data-value="2"></i>
                                        <i class="far fa-star" data-value="3"></i>
                                        <i class="far fa-star" data-value="4"></i>
                                        <i class="far fa-star" data-value="5"></i>
                                    </div>
                                    <input type="hidden" name="rating" id="rating<?php echo $booking['id']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Comment (optional)</label>
                                    <textarea class="form-control" name="comment" rows="3" placeholder="Share your experience..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- As Teacher -->
        <?php if (!empty($teacherBookings)): ?>
        <div class="col-12">
            <h5 class="text-muted"><i class="fas fa-chalkboard-teacher me-2"></i>As Teacher</h5>
            <?php foreach ($teacherBookings as $booking): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="<?php echo asset($booking['learner_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                                 alt="<?php echo sanitize($booking['learner_name']); ?>" 
                                 class="profile-avatar-lg rounded-circle">
                        </div>
                        <div class="col-md-6">
                            <h5><?php echo sanitize($booking['skill_title']); ?></h5>
                            <p class="mb-1">with <strong><?php echo sanitize($booking['learner_name']); ?></strong></p>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?php echo formatDate($booking['scheduled_date']); ?>
                                <i class="fas fa-clock me-1"></i><?php echo date('h:i A', strtotime($booking['scheduled_time'])); ?>
                            </small>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="points-badge">+<?php echo $booking['points_required']; ?> pts</span>
                        </div>
                        <div class="col-md-2 text-end">
                            <span class="booking-status <?php echo strtolower($booking['status']); ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                            <?php if ($booking['status'] === 'pending'): ?>
                            <div class="mt-2">
                                <button class="btn btn-success btn-sm" onclick="acceptBooking(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="rejectBooking(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php elseif ($booking['status'] === 'accepted'): ?>
                            <a href="chat.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info mt-2 d-block mb-2">
                                <i class="fas fa-comments me-1"></i>Chat
                            </a>
                            <button class="btn btn-primary btn-sm d-block w-100" onclick="completeBooking(<?php echo $booking['id']; ?>)">
                                <i class="fas fa-check-double me-1"></i>Complete
                            </button>
                            <?php elseif ($booking['status'] === 'completed'): ?>
                                <?php 
                                $existingReview = $reviewObj->getReviewByBooking($booking['id'], getUserId());
                                if (!$existingReview): 
                                ?>
                                <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#reviewModalT<?php echo $booking['id']; ?>">
                                    <i class="fas fa-star me-1"></i>Review
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Review Modal for Teacher -->
            <?php if ($booking['status'] === 'completed'): ?>
            <?php 
            $existingReview = $reviewObj->getReviewByBooking($booking['id'], getUserId());
            if (!$existingReview): 
            ?>
            <div class="modal fade" id="reviewModalT<?php echo $booking['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Leave a Review</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="review">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <div class="modal-body">
                                <div class="mb-3 text-center">
                                    <label class="form-label">Rating</label>
                                    <div class="rating-stars" style="font-size: 2rem;">
                                        <i class="far fa-star" data-value="1"></i>
                                        <i class="far fa-star" data-value="2"></i>
                                        <i class="far fa-star" data-value="3"></i>
                                        <i class="far fa-star" data-value="4"></i>
                                        <i class="far fa-star" data-value="5"></i>
                                    </div>
                                    <input type="hidden" name="rating" id="ratingT<?php echo $booking['id']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Comment (optional)</label>
                                    <textarea class="form-control" name="comment" rows="3" placeholder="Share your experience..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.rating-stars').forEach(function(container) {
    container.querySelectorAll('i').forEach(function(star) {
        star.addEventListener('click', function() {
            var value = this.dataset.value;
            var input = container.parentElement.querySelector('input[type="hidden"]');
            input.value = value;
            
            container.querySelectorAll('i').forEach(function(s, i) {
                if (i < value) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
        
        star.addEventListener('mouseover', function() {
            var value = this.dataset.value;
            container.querySelectorAll('i').forEach(function(s, i) {
                if (i < value) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                }
            });
        });
        
        star.addEventListener('mouseout', function() {
            var input = container.parentElement.querySelector('input[type="hidden"]');
            var currentValue = input.value || 0;
            container.querySelectorAll('i').forEach(function(s, i) {
                if (i < currentValue) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>