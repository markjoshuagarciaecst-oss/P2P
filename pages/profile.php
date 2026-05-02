<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Skill.php';
require_once '../classes/Review.php';

$pageTitle = 'Profile';

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : (isLoggedIn() ? getUserId() : 0);

if (!$userId) {
    redirect('login.php?redirect=profile.php');
}

$userObj = new User();
$skillObj = new Skill();
$reviewObj = new Review();

// Get user data
$profileUser = $userObj->getUserById($userId);

if (!$profileUser) {
    $error = 'User not found';
}

$stats = $userObj->getUserStats($userId);
$userSkills = $skillObj->getUserSkills($userId);
$userReviews = $reviewObj->getUserReviews($userId, 10);

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && getUserId() == $userId) {
    $name = sanitize($_POST['name'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    
    // Handle profile picture upload
    $profilePicture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $uploadDir = __DIR__ . '/../assets/images/';
        $fileName = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            $profilePicture = 'assets/images/' . $fileName;
        }
    }
    
    if ($userObj->updateProfile($userId, $name, $bio, $profilePicture)) {
        $success = 'Profile updated successfully!';
        $profileUser = $userObj->getUserById($userId);
    } else {
        $error = 'Failed to update profile';
    }
}
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
    
    <?php if (!$profileUser): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>User not found
    </div>
    <?php else: ?>
    <div class="row">
        <!-- Profile Sidebar -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <img src="<?php echo asset($profileUser['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                         alt="<?php echo sanitize($profileUser['name']); ?>" 
                         class="profile-avatar mb-3">
                    <h4><?php echo sanitize($profileUser['name']); ?></h4>
                    <p class="text-muted"><?php echo sanitize($profileUser['email']); ?></p>
                    
                    <div class="points-badge mx-auto mb-3" style="width: fit-content;">
                        <i class="fas fa-coins"></i> <?php echo $profileUser['points']; ?> Points
                    </div>
                    
                    <div class="rating-stars mb-3">
                        <?php 
                        $rating = $profileUser['average_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                        }
                        ?>
                        <small class="text-muted">(<?php echo number_format($rating, 1); ?> from <?php echo $profileUser['total_reviews']; ?> reviews)</small>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="stat-value text-primary"><?php echo $stats['skills_offered']; ?></div>
                            <small class="text-muted">Skills</small>
                        </div>
                        <div class="col-4">
                            <div class="stat-value text-success"><?php echo $stats['sessions_completed']; ?></div>
                            <small class="text-muted">Sessions</small>
                        </div>
                        <div class="col-4">
                            <div class="stat-value text-warning"><?php echo $stats['total_earned']; ?></div>
                            <small class="text-muted">Earned</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <small class="text-muted">Member since <?php echo formatDate($profileUser['created_at']); ?></small>
                </div>
            </div>
            
            <!-- Edit Profile (only for own profile) -->
            <?php if (isLoggedIn() && getUserId() == $userId): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo sanitize($profileUser['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      placeholder="Tell us about yourself..."><?php echo sanitize($profileUser['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                   accept="image/*">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-8">
            <!-- Bio -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">About</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br(sanitize($profileUser['bio'] ?? 'No bio available yet.')); ?></p>
                </div>
            </div>
            
            <!-- Skills -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Skills Offered</h5>
                    <?php if (isLoggedIn() && getUserId() == $userId): ?>
                    <a href="my-skills.php" class="btn btn-sm btn-outline-primary">Manage</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($userSkills)): ?>
                    <p class="text-muted">No skills listed yet</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($userSkills as $skill): ?>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <span class="skill-category"><?php echo sanitize($skill['category']); ?></span>
                                    <h6 class="mt-2"><?php echo sanitize($skill['title']); ?></h6>
                                    <p class="small text-muted mb-2"><?php echo substr(sanitize($skill['description']), 0, 80); ?>...</p>
                                    <span class="points-badge" style="font-size: 0.8rem;">
                                        <i class="fas fa-coins"></i> <?php echo $skill['points_required']; ?> pts
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reviews -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($userReviews)): ?>
                    <p class="text-muted">No reviews yet</p>
                    <?php else: ?>
                        <?php foreach ($userReviews as $review): ?>
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
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>