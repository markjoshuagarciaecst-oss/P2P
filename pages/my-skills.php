<?php
require_once '../config/database.php';
require_once '../classes/Skill.php';

$pageTitle = 'My Skills';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=my-skills.php');
}

$skillObj = new Skill();
$categories = $skillObj->getCategories();

// Get user's skills
$mySkills = $skillObj->getUserSkills(getUserId());

$error = '';
$success = '';

// Handle add skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $skillLevel = sanitize($_POST['skill_level'] ?? '');
    $pointsRequired = (int)($_POST['points_required'] ?? 10);
    $maxSessionHours = max(1, (int)($_POST['max_session_hours'] ?? 1));
    
    if (empty($title) || empty($description) || empty($category)) {
        $error = 'Please fill in all required fields';
    } else {
        $skillId = $skillObj->create(getUserId(), $title, $description, $category, $skillLevel, $pointsRequired, $maxSessionHours);
        if ($skillId) {
            $success = 'Skill added successfully!';
        } else {
            $error = 'Failed to add skill. Please try again.';
        }
    }
}

// Handle delete skill
if (isset($_GET['delete'])) {
    $skillId = (int)$_GET['delete'];
    $skillObj->delete($skillId, getUserId());
    redirect('my-skills.php');
}
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-graduation-cap me-2"></i>My Skills</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
            <i class="fas fa-plus me-2"></i>Add New Skill
        </button>
    </div>
    
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
    
    <?php if (empty($mySkills)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h4>No skills listed yet</h4>
                <p>Share your knowledge and earn points by teaching others!</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                    <i class="fas fa-plus me-2"></i>Add Your First Skill
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($mySkills as $skill): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="skill-category"><?php echo sanitize($skill['category']); ?></span>
                        <span class="skill-level <?php echo strtolower($skill['skill_level']); ?>">
                            <?php echo sanitize($skill['skill_level']); ?>
                        </span>
                    </div>
                    <h5><?php echo sanitize($skill['title']); ?></h5>
                    <p class="text-muted"><?php echo substr(sanitize($skill['description']), 0, 100); ?>...</p>
                    <div class="points-badge">
                        <i class="fas fa-coins"></i> <?php echo $skill['points_required']; ?> pts/hr
                    </div>
                    <small class="text-muted d-block mt-1">
                        <i class="fas fa-clock me-1"></i>Max <?php echo (int)($skill['max_session_hours'] ?? 1); ?> hr<?php echo ($skill['max_session_hours'] ?? 1) > 1 ? 's' : ''; ?> per session
                    </small>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Posted: <?php echo formatDate($skill['created_at']); ?></small>
                        <a href="?delete=<?php echo $skill['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this skill?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Skill Modal -->
<div class="modal fade" id="addSkillModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Skill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Skill Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Basic Guitar Lessons" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe what you'll teach..." required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['name']; ?>"><?php echo sanitize($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="skill_level" class="form-label">Skill Level</label>
                        <select class="form-select" id="skill_level" name="skill_level">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate" selected>Intermediate</option>
                            <option value="Expert">Expert</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="points_required" class="form-label">Points per Hour</label>
                        <input type="number" class="form-control" id="points_required" name="points_required" value="10" min="1" max="100">
                        <small class="text-muted">How many points learners pay per hour of teaching</small>
                    </div>

                    <div class="mb-3">
                        <label for="max_session_hours" class="form-label">Max Session Length (hours)</label>
                        <input type="number" class="form-control" id="max_session_hours" name="max_session_hours" value="1" min="1" max="8">
                        <small class="text-muted">The longest session you're willing to teach in one booking</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Skill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>