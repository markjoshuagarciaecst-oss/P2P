<?php
require_once '../config/database.php';
require_once '../classes/Skill.php';

$pageTitle = 'Browse Skills';

$skillObj = new Skill();
$categories = $skillObj->getCategories();

// Get filter parameters
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$level = sanitize($_GET['level'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Get skills
if ($search) {
    $skills = $skillObj->search($search, $category);
} else {
    $skills = $skillObj->getAllSkills($category, $level, $limit, $offset);
}

// Get total count for pagination
$db = Database::getInstance();
if ($search) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM skills s 
        INNER JOIN users u ON s.user_id = u.id 
        WHERE s.is_active = 1 AND u.is_active = 1 
        AND (s.title LIKE ? OR s.description LIKE ?)
    ");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $sql = "SELECT COUNT(*) as total FROM skills WHERE is_active = 1";
    $params = [];
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    if ($level) {
        $sql .= " AND skill_level = ?";
        $params[] = $level;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}
$totalSkills = $stmt->fetch()['total'];
$totalPages = ceil($totalSkills / $limit);
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-search me-2"></i>Browse Skills</h2>
            <p class="text-muted">Find the perfect skill to learn from our community</p>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search skills..." 
                           value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['name']; ?>" <?php echo $category === $cat['name'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="level">
                        <option value="">All Levels</option>
                        <option value="Beginner" <?php echo $level === 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="Intermediate" <?php echo $level === 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="Expert" <?php echo $level === 'Expert' ? 'selected' : ''; ?>>Expert</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="skills.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results Count -->
    <div class="mb-3">
        <span class="text-muted">Showing <?php echo count($skills); ?> of <?php echo $totalSkills; ?> skills</span>
    </div>
    
    <!-- Skills Grid -->
    <?php if (empty($skills)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h4>No skills found</h4>
                <p>Try adjusting your search or filters</p>
                <a href="skills.php" class="btn btn-primary">Clear Filters</a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($skills as $skill): ?>
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
                        <a href="skill-details.php?id=<?php echo $skill['id']; ?>" class="btn btn-sm btn-outline-primary">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&level=<?php echo urlencode($level); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i >= $page - 2 && $i <= $page + 2): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&level=<?php echo urlencode($level); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&level=<?php echo urlencode($level); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>