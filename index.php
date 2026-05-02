<?php
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/Skill.php';
require_once 'classes/SkillRequest.php';

$pageTitle = 'Home';
$skillObj = new Skill();
$userObj = new User();

// Get featured skills
$featuredSkills = $skillObj->getFeaturedSkills(6);
$categories = $skillObj->getCategories();
?>

<?php include 'includes/header.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <h1>Welcome to SkillSwap</h1>
        <p>Exchange knowledge and skills with peers. Teach what you know, learn what you don't.</p>
        <div class="mt-4">
            <?php if (!isLoggedIn()): ?>
            <a href="<?php echo APP_URL; ?>/pages/register.php" class="btn btn-light btn-lg me-2">
                <i class="fas fa-user-plus me-2"></i>Join Now
            </a>
            <?php endif; ?>
            <a href="<?php echo APP_URL; ?>/pages/skills.php" class="btn btn-outline-light btn-lg">
                <i class="fas fa-search me-2"></i>Browse Skills
            </a>
        </div>
    </div>
</section>

<!-- Search Box -->
<div class="container">
    <div class="search-box">
        <form id="searchForm" method="GET" action="<?php echo APP_URL; ?>/pages/skills.php">
            <div class="row g-3">
                <div class="col-md-8">
                    <input type="text" class="form-control" id="searchInput" name="search" placeholder="What do you want to learn?">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['name']; ?>"><?php echo sanitize($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Categories Section -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">Browse by Category</h2>
        <div class="row g-4">
            <?php foreach ($categories as $index => $cat): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?php echo APP_URL; ?>/pages/skills.php?category=<?php echo urlencode($cat['name']); ?>" class="text-decoration-none">
                    <div class="category-card">
                        <i class="fas <?php echo $cat['icon']; ?> text-primary"></i>
                        <h5><?php echo sanitize($cat['name']); ?></h5>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Skills Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Featured Skills</h2>
            <a href="pages/skills.php" class="btn btn-outline-primary">View All</a>
        </div>
        <div class="row g-4">
            <?php if (empty($featuredSkills)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-graduation-cap"></i>
                    <h4>No skills available yet</h4>
                    <p>Be the first to share your skills!</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($featuredSkills as $skill): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card skill-card h-100">
                        <div class="card-body">
                            <span class="skill-category"><?php echo sanitize($skill['category']); ?></span>
                            <span class="skill-level <?php echo strtolower($skill['skill_level']); ?>">
                                <?php echo sanitize($skill['skill_level']); ?>
                            </span>
                            <h5 class="card-title mt-3"><?php echo sanitize($skill['title']); ?></h5>
                            <p class="card-text text-muted"><?php echo substr(sanitize($skill['description']), 0, 100); ?>...</p>
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?php echo asset($skill['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                                     alt="<?php echo sanitize($skill['teacher_name']); ?>" 
                                     class="profile-avatar-sm rounded-circle me-2">
                                <div>
                                    <strong><?php echo sanitize($skill['teacher_name']); ?></strong>
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
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="points-badge">
                                    <i class="fas fa-coins"></i> <?php echo $skill['points_required']; ?> pts
                                </span>
                                <a href="pages/skill-details.php?id=<?php echo $skill['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">How It Works</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="text-center">
                    <div class="step-icon mx-auto mb-4">
                        <i class="fas fa-user-plus fa-3x text-primary"></i>
                    </div>
                    <h4>1. Create Account</h4>
                    <p class="text-muted">Sign up and get 100 free points to start learning.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="step-icon mx-auto mb-4">
                        <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                    </div>
                    <h4>2. Share Your Skills</h4>
                    <p class="text-muted">List what you can teach and earn points from learners.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="step-icon mx-auto mb-4">
                        <i class="fas fa-handshake fa-3x text-primary"></i>
                    </div>
                    <h4>3. Exchange & Learn</h4>
                    <p class="text-muted">Book sessions, learn new skills, and grow your network.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2>Ready to Start Learning?</h2>
        <p class="lead mb-4">Join thousands of users exchanging knowledge every day.</p>
        <a href="pages/register.php" class="btn btn-light btn-lg">
            <i class="fas fa-rocket me-2"></i>Get Started Now
        </a>
    </div>
</section>

<style>
.step-icon {
    width: 100px;
    height: 100px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<?php include 'includes/footer.php'; ?>      