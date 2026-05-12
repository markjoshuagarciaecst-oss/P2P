<?php
require_once '../config/database.php';
require_once '../classes/Skill.php';

$pageTitle = 'Skill Management';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$db = Database::getInstance();

$success = '';
$error   = '';

// ── Handle actions ────────────────────────────────────────────────────────────
$action   = $_GET['action'] ?? '';
$skillId  = (int)($_GET['id'] ?? 0);

if ($action && $skillId) {
    switch ($action) {
        case 'delete':
            // Soft-delete: set is_active = 0
            $stmt = $db->prepare("UPDATE skills SET is_active = 0 WHERE id = ?");
            $stmt->execute([$skillId]);
            $success = 'Skill has been removed from the platform.';
            break;

        case 'restore':
            $stmt = $db->prepare("UPDATE skills SET is_active = 1 WHERE id = ?");
            $stmt->execute([$skillId]);
            $success = 'Skill has been restored.';
            break;

        case 'hard_delete':
            // Permanent delete — also removes related bookings/reviews via FK cascade
            $stmt = $db->prepare("DELETE FROM skills WHERE id = ?");
            try {
                $stmt->execute([$skillId]);
                $success = 'Skill permanently deleted.';
            } catch (PDOException $e) {
                $error = 'Cannot permanently delete — the skill has existing bookings. Use Remove instead.';
            }
            break;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter   = sanitize($_GET['filter']   ?? 'active');
$search   = sanitize($_GET['search']   ?? '');
$category = sanitize($_GET['category'] ?? '');

$where  = [];
$params = [];

if ($filter === 'active') {
    $where[] = "s.is_active = 1";
} elseif ($filter === 'removed') {
    $where[] = "s.is_active = 0";
}

if ($search) {
    $where[] = "(s.title LIKE ? OR s.description LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where[] = "s.category = ?";
    $params[] = $category;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$skills = $db->prepare("
    SELECT s.*,
           u.name  AS teacher_name,
           u.email AS teacher_email,
           u.profile_picture,
           IFNULL(s.max_session_hours, 1) AS max_session_hours,
           (SELECT COUNT(*) FROM bookings b WHERE b.skill_id = s.id) AS booking_count
    FROM skills s
    INNER JOIN users u ON s.user_id = u.id
    $whereSQL
    ORDER BY s.created_at DESC
");
$skills->execute($params);
$skills = $skills->fetchAll();

// Categories for filter dropdown
$categories = $db->query("SELECT DISTINCT category FROM skills ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Summary counts
$totalActive  = $db->query("SELECT COUNT(*) FROM skills WHERE is_active = 1")->fetchColumn();
$totalRemoved = $db->query("SELECT COUNT(*) FROM skills WHERE is_active = 0")->fetchColumn();
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-graduation-cap me-2"></i>Skill Management</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $totalActive; ?></div>
                <div class="stat-label">Active Skills</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon text-danger"><i class="fas fa-ban"></i></div>
                <div class="stat-value"><?php echo $totalRemoved; ?></div>
                <div class="stat-label">Removed Skills</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon text-primary"><i class="fas fa-list"></i></div>
                <div class="stat-value"><?php echo $totalActive + $totalRemoved; ?></div>
                <div class="stat-label">Total Skills</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="filter" class="form-select">
                        <option value="all"     <?php echo $filter === 'all'     ? 'selected' : ''; ?>>All Skills</option>
                        <option value="active"  <?php echo $filter === 'active'  ? 'selected' : ''; ?>>Active Only</option>
                        <option value="removed" <?php echo $filter === 'removed' ? 'selected' : ''; ?>>Removed Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Skill title, description or teacher name"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Skills table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Skills (<?php echo count($skills); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($skills)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                <p>No skills found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Skill</th>
                            <th>Teacher</th>
                            <th>Category</th>
                            <th>Level</th>
                            <th>Rate</th>
                            <th>Bookings</th>
                            <th>Status</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skills as $skill): ?>
                        <tr class="<?php echo $skill['is_active'] ? '' : 'table-secondary text-muted'; ?>">
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($skill['title']); ?></div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars(mb_substr($skill['description'] ?? '', 0, 60)); ?>…
                                </small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo asset($skill['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>"
                                         class="rounded-circle" style="width:32px;height:32px;object-fit:cover"
                                         alt="<?php echo htmlspecialchars($skill['teacher_name']); ?>">
                                    <div>
                                        <div><?php echo htmlspecialchars($skill['teacher_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($skill['teacher_email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($skill['category']); ?></span></td>
                            <td>
                                <?php
                                $lvlClass = ['Beginner' => 'success', 'Intermediate' => 'warning', 'Expert' => 'danger'];
                                $cls = $lvlClass[$skill['skill_level']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars($skill['skill_level']); ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?php echo $skill['points_required']; ?> pts/hr</span>
                                <br><small class="text-muted">max <?php echo $skill['max_session_hours']; ?> hr</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?php echo $skill['booking_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($skill['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Removed</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo formatDate($skill['created_at']); ?></small></td>
                            <td>
                                <!-- View on site -->
                                <a href="<?php echo APP_URL; ?>/pages/skill-details.php?id=<?php echo $skill['id']; ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-secondary me-1"
                                   title="View skill">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <?php if ($skill['is_active']): ?>
                                <!-- Remove (soft delete) -->
                                <a href="?action=delete&id=<?php echo $skill['id']; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>"
                                   class="btn btn-sm btn-danger"
                                   title="Remove skill"
                                   onclick="return confirm('Remove this skill from the platform? The teacher will no longer be able to receive bookings for it.')">
                                    <i class="fas fa-trash me-1"></i>Remove
                                </a>
                                <?php else: ?>
                                <!-- Restore -->
                                <a href="?action=restore&id=<?php echo $skill['id']; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>"
                                   class="btn btn-sm btn-success me-1"
                                   title="Restore skill"
                                   onclick="return confirm('Restore this skill?')">
                                    <i class="fas fa-undo me-1"></i>Restore
                                </a>
                                <!-- Permanent delete -->
                                <a href="?action=hard_delete&id=<?php echo $skill['id']; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   title="Permanently delete"
                                   onclick="return confirm('Permanently delete this skill? This cannot be undone and will fail if the skill has existing bookings.')">
                                    <i class="fas fa-times me-1"></i>Delete Permanently
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
