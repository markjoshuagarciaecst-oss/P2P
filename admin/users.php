<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Booking.php';
require_once '../classes/Transaction.php';

$pageTitle = 'User Management';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$db             = Database::getInstance();
$userObj        = new User();
$bookingObj     = new Booking();
$transactionObj = new Transaction();

$success = '';
$error   = '';

// ── Handle actions ────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$userId = (int)($_GET['id'] ?? 0);

if ($action && $userId) {
    switch ($action) {
        case 'ban':
            $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role != 'admin'")
               ->execute([$userId]);
            $success = 'User has been banned.';
            break;

        case 'unban':
            $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")
               ->execute([$userId]);
            $success = 'User has been unbanned.';
            break;

        case 'delete':
            // Soft-delete: anonymise email so the address can be reused
            $db->prepare("UPDATE users SET is_active = 0, email = CONCAT('deleted_', id, '_', email) WHERE id = ? AND role != 'admin'")
               ->execute([$userId]);
            $success = 'User account has been deleted.';
            $userId  = 0; // close detail panel
            break;

        case 'adjust_points':
            $points = (int)($_GET['points'] ?? 0);
            if ($points !== 0) {
                $db->prepare("UPDATE users SET points = GREATEST(0, points + ?) WHERE id = ?")
                   ->execute([$points, $userId]);
                $success = 'Points adjusted by ' . ($points > 0 ? '+' : '') . $points . '.';
            }
            break;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter = sanitize($_GET['filter'] ?? 'all');
$search = sanitize($_GET['search'] ?? '');

$where  = ["u.role = 'user'"];
$params = [];

if ($filter === 'active') {
    $where[] = "u.is_active = 1";
} elseif ($filter === 'banned') {
    $where[] = "u.is_active = 0";
}

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM skills       WHERE user_id   = u.id AND is_active = 1) AS skills_count,
           (SELECT COUNT(*) FROM bookings     WHERE learner_id = u.id OR teacher_id = u.id) AS bookings_count,
           (SELECT COUNT(*) FROM point_transactions WHERE user_id = u.id) AS transactions_count
    FROM users u
    $whereSQL
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Summary counts (always across all users, not filtered)
$totalUsers  = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$activeUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='user' AND is_active=1")->fetchColumn();
$bannedUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='user' AND is_active=0")->fetchColumn();
$totalPoints = $db->query("SELECT SUM(points) FROM users WHERE role='user' AND is_active=1")->fetchColumn() ?? 0;

// ── Detail panel ──────────────────────────────────────────────────────────────
$selectedUser     = null;
$userBookings     = [];
$userTransactions = [];
$userSkills       = [];

if ($userId && !in_array($action, ['delete'])) {
    $selectedUser = $userObj->getUserById($userId);
    if ($selectedUser) {
        $userBookings     = $bookingObj->getAllUserBookings($userId);
        $userTransactions = $transactionObj->getUserTransactions($userId, 20);
        $s = $db->prepare("SELECT * FROM skills WHERE user_id = ? ORDER BY created_at DESC");
        $s->execute([$userId]);
        $userSkills = $s->fetchAll();
    }
}

// Build query-string helper (preserves filter/search when navigating)
function qs(array $extra = [], array $remove = []) {
    $base = array_diff_key($_GET, array_flip(array_merge(['action','id'], $remove)));
    return http_build_query(array_merge($base, $extra));
}
?>
<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users-cog me-2"></i>User Management</h2>
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
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-primary"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-success"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?php echo $activeUsers; ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-danger"><i class="fas fa-user-slash"></i></div>
                <div class="stat-value"><?php echo $bannedUsers; ?></div>
                <div class="stat-label">Banned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-coins"></i></div>
                <div class="stat-value"><?php echo number_format($totalPoints); ?></div>
                <div class="stat-label">Points in Circulation</div>
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
                        <option value="all"    <?php echo $filter==='all'    ?'selected':''; ?>>All Users</option>
                        <option value="active" <?php echo $filter==='active' ?'selected':''; ?>>Active Only</option>
                        <option value="banned" <?php echo $filter==='banned' ?'selected':''; ?>>Banned Only</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Name or email"
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

    <!-- User detail panel -->
    <?php if ($selectedUser): ?>
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($selectedUser['name']); ?>
                <?php if (!$selectedUser['is_active']): ?>
                <span class="badge bg-danger ms-2">Banned</span>
                <?php endif; ?>
            </h5>
            <a href="?<?php echo qs(); ?>" class="btn-close btn-close-white"></a>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Avatar + basic info -->
                <div class="col-md-3 text-center border-end">
                    <img src="<?php echo asset($selectedUser['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>"
                         class="rounded-circle mb-3" style="width:90px;height:90px;object-fit:cover" alt="Avatar">
                    <h6 class="mb-1"><?php echo htmlspecialchars($selectedUser['name']); ?></h6>
                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($selectedUser['email']); ?></p>
                    <span class="badge bg-warning text-dark fs-6"><?php echo $selectedUser['points']; ?> pts</span>
                    <p class="text-muted small mt-2">Joined <?php echo formatDate($selectedUser['created_at']); ?></p>
                </div>

                <!-- Stats -->
                <div class="col-md-3 border-end">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="h5 text-primary mb-0"><?php echo count($userSkills); ?></div>
                                <small class="text-muted">Skills</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="h5 text-success mb-0"><?php echo count($userBookings); ?></div>
                                <small class="text-muted">Bookings</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="h5 text-info mb-0"><?php echo count($userTransactions); ?></div>
                                <small class="text-muted">Transactions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="h5 text-warning mb-0"><?php echo number_format($selectedUser['average_rating'] ?? 0, 1); ?></div>
                                <small class="text-muted">Rating</small>
                            </div>
                        </div>
                    </div>

                    <!-- Adjust points -->
                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Adjust Points</label>
                        <form method="GET" class="d-flex gap-2">
                            <input type="hidden" name="action" value="adjust_points">
                            <input type="hidden" name="id" value="<?php echo $selectedUser['id']; ?>">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="number" name="points" class="form-control form-control-sm"
                                   placeholder="e.g. +50 or -20" style="width:120px">
                            <button type="submit" class="btn btn-sm btn-outline-warning"
                                    onclick="return confirm('Adjust this user\'s points?')">Apply</button>
                        </form>
                        <small class="text-muted">Use negative numbers to deduct.</small>
                    </div>
                </div>

                <!-- Recent transactions -->
                <div class="col-md-6">
                    <h6 class="mb-2">Recent Transactions</h6>
                    <?php if (empty($userTransactions)): ?>
                    <p class="text-muted small">No transactions yet.</p>
                    <?php else: ?>
                    <div class="table-responsive" style="max-height:200px;overflow-y:auto">
                        <table class="table table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr><th>Type</th><th>Description</th><th>Pts</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($userTransactions, 0, 10) as $t): ?>
                                <tr>
                                    <td><span class="badge bg-<?php echo $t['type']==='earned'?'success':'primary'; ?>"><?php echo ucfirst($t['type']); ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars($t['description']); ?></td>
                                    <td><?php echo $t['amount']; ?></td>
                                    <td class="small"><?php echo formatDate($t['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <?php if ($selectedUser['is_active']): ?>
            <a href="?action=ban&id=<?php echo $selectedUser['id']; ?>&<?php echo qs(); ?>"
               class="btn btn-warning"
               onclick="return confirm('Ban this user? They will not be able to log in.')">
                <i class="fas fa-ban me-1"></i>Ban User
            </a>
            <?php else: ?>
            <a href="?action=unban&id=<?php echo $selectedUser['id']; ?>&<?php echo qs(); ?>"
               class="btn btn-success"
               onclick="return confirm('Unban this user?')">
                <i class="fas fa-check me-1"></i>Unban User
            </a>
            <?php endif; ?>
            <a href="?action=delete&id=<?php echo $selectedUser['id']; ?>&<?php echo qs(); ?>"
               class="btn btn-danger ms-auto"
               onclick="return confirm('Permanently delete this account? This cannot be undone.')">
                <i class="fas fa-trash me-1"></i>Delete Account
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Users table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Users (<?php echo count($users); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-users fa-3x mb-3"></i>
                <p>No users found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Points</th>
                            <th>Skills</th>
                            <th>Bookings</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr class="<?php echo $u['is_active'] ? '' : 'table-secondary'; ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo asset($u['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>"
                                         class="rounded-circle" style="width:36px;height:36px;object-fit:cover" alt="">
                                    <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                                </div>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge bg-warning text-dark"><?php echo $u['points']; ?></span></td>
                            <td class="text-center"><?php echo $u['skills_count']; ?></td>
                            <td class="text-center"><?php echo $u['bookings_count']; ?></td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Banned</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?php echo formatDate($u['created_at']); ?></td>
                            <td>
                                <!-- View detail -->
                                <a href="?id=<?php echo $u['id']; ?>&<?php echo qs(); ?>"
                                   class="btn btn-sm btn-outline-primary me-1" title="View details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <!-- Ban / Unban -->
                                <?php if ($u['is_active']): ?>
                                <a href="?action=ban&id=<?php echo $u['id']; ?>&<?php echo qs(); ?>"
                                   class="btn btn-sm btn-outline-warning me-1" title="Ban user"
                                   onclick="return confirm('Ban <?php echo htmlspecialchars(addslashes($u['name'])); ?>?')">
                                    <i class="fas fa-ban"></i>
                                </a>
                                <?php else: ?>
                                <a href="?action=unban&id=<?php echo $u['id']; ?>&<?php echo qs(); ?>"
                                   class="btn btn-sm btn-outline-success me-1" title="Unban user"
                                   onclick="return confirm('Unban <?php echo htmlspecialchars(addslashes($u['name'])); ?>?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                <!-- Delete -->
                                <a href="?action=delete&id=<?php echo $u['id']; ?>&<?php echo qs(); ?>"
                                   class="btn btn-sm btn-outline-danger" title="Delete account"
                                   onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes($u['name'])); ?>\'s account permanently?')">
                                    <i class="fas fa-trash"></i>
                                </a>
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
