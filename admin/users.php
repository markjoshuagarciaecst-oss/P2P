<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Booking.php';
require_once '../classes/Transaction.php';

$pageTitle = 'User Management';

// Check if admin is logged in
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$userObj = new User();
$bookingObj = new Booking();
$transactionObj = new Transaction();
$db = Database::getInstance();

// Handle actions
$action = $_GET['action'] ?? '';
$userId = (int)($_GET['id'] ?? 0);

if ($action && $userId) {
    switch ($action) {
        case 'ban':
            // Ban user (set is_active = 0)
            $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            $success = 'User has been banned successfully.';
            break;
            
        case 'unban':
            // Unban user (set is_active = 1)
            $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->execute([$userId]);
            $success = 'User has been unbanned successfully.';
            break;
            
        case 'delete':
            // Delete user (soft delete by setting is_active = 0 and changing email)
            $stmt = $db->prepare("UPDATE users SET is_active = 0, email = CONCAT('deleted_', id, '_', email) WHERE id = ?");
            $stmt->execute([$userId]);
            $success = 'User account has been deleted.';
            break;
    }
}

// Get filter
$filter = sanitize($_GET['filter'] ?? 'all');
$search = sanitize($_GET['search'] ?? '');

// Build query
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM skills WHERE user_id = u.id AND is_active = 1) as skills_count,
          (SELECT COUNT(*) FROM bookings WHERE learner_id = u.id OR teacher_id = u.id) as bookings_count,
          (SELECT COUNT(*) FROM point_transactions WHERE user_id = u.id) as transactions_count
          FROM users u WHERE u.role = 'user'";

$params = [];

if ($filter === 'active') {
    $query .= " AND u.is_active = 1";
} elseif ($filter === 'banned') {
    $query .= " AND u.is_active = 0";
}

if ($search) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user details if viewing specific user
$selectedUser = null;
$userBookings = [];
$userTransactions = [];
$userSkills = [];

if ($userId) {
    $selectedUser = $userObj->getUserById($userId);
    if ($selectedUser) {
        $userBookings = $bookingObj->getAllUserBookings($userId);
        $userTransactions = $transactionObj->getUserTransactions($userId, 20);
        $userSkills = $db->prepare("SELECT * FROM skills WHERE user_id = ? ORDER BY created_at DESC");
        $userSkills->execute([$userId]);
        $userSkills = $userSkills->fetchAll();
    }
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

    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    </div>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Filter</label>
                    <select name="filter" class="form-select">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="banned" <?php echo $filter === 'banned' ? 'selected' : ''; ?>>Banned Only</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedUser): ?>
    <!-- User Details Modal -->
    <div class="modal fade show" id="userDetailsModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i><?php echo sanitize($selectedUser['name']); ?>
                        <?php if (!$selectedUser['is_active']): ?>
                        <span class="badge bg-danger">Banned</span>
                        <?php endif; ?>
                    </h5>
                    <a href="users.php?<?php echo http_build_query(array_diff_key($_GET, ['id' => ''])); ?>" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <img src="<?php echo asset($selectedUser['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>"
                                 alt="Profile Picture" class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                            <h6><?php echo sanitize($selectedUser['name']); ?></h6>
                            <p class="text-muted"><?php echo sanitize($selectedUser['email']); ?></p>
                            <div class="mb-2">
                                <span class="badge bg-primary"><?php echo $selectedUser['points']; ?> Points</span>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="border rounded p-3 text-center">
                                        <div class="h4 text-primary"><?php echo count($userSkills); ?></div>
                                        <div class="text-muted">Skills Offered</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3 text-center">
                                        <div class="h4 text-success"><?php echo count($userBookings); ?></div>
                                        <div class="text-muted">Total Bookings</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3 text-center">
                                        <div class="h4 text-info"><?php echo count($userTransactions); ?></div>
                                        <div class="text-muted">Transactions</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3 text-center">
                                        <div class="h4 text-warning"><?php echo $selectedUser['average_rating'] ?? 0; ?></div>
                                        <div class="text-muted">Avg Rating</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="mt-4">
                        <h6>Recent Activity</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($userTransactions, 0, 10) as $transaction): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['type'] === 'earned' ? 'success' : 'primary'; ?>">
                                                <?php echo ucfirst($transaction['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sanitize($transaction['description']); ?></td>
                                        <td><?php echo $transaction['amount']; ?> pts</td>
                                        <td><?php echo formatDate($transaction['created_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if ($selectedUser['is_active']): ?>
                    <a href="?action=ban&id=<?php echo $selectedUser['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])); ?>"
                       class="btn btn-warning" onclick="return confirm('Are you sure you want to ban this user?')">
                        <i class="fas fa-ban me-1"></i>Ban User
                    </a>
                    <?php else: ?>
                    <a href="?action=unban&id=<?php echo $selectedUser['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])); ?>"
                       class="btn btn-success" onclick="return confirm('Are you sure you want to unban this user?')">
                        <i class="fas fa-check me-1"></i>Unban User
                    </a>
                    <?php endif; ?>
                    <a href="?action=delete&id=<?php echo $selectedUser['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])); ?>"
                       class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                        <i class="fas fa-trash me-1"></i>Delete User
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Users (<?php echo count($users); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Stats</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo asset($user['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>"
                                         alt="Profile" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold"><?php echo sanitize($user['name']); ?></div>
                                        <small class="text-muted"><?php echo $user['points']; ?> points</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo sanitize($user['email']); ?></div>
                                <?php if ($user['bio']): ?>
                                <small class="text-muted"><?php echo substr(sanitize($user['bio']), 0, 50); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="text-primary"><?php echo $user['skills_count']; ?> skills</span><br>
                                    <span class="text-success"><?php echo $user['bookings_count']; ?> bookings</span><br>
                                    <span class="text-info"><?php echo $user['transactions_count']; ?> transactions</span>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Banned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <a href="?id=<?php echo $user['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['id' => ''])); ?>"
                                   class="btn btn-sm btn-outline-primary me-1">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($user['is_active']): ?>
                                <a href="?action=ban&id=<?php echo $user['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])); ?>"
                                   class="btn btn-sm btn-outline-warning" onclick="return confirm('Ban this user?')">
                                    <i class="fas fa-ban"></i>
                                </a>
                                <?php else: ?>
                                <a href="?action=unban&id=<?php echo $user['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['action' => '', 'id' => ''])); ?>"
                                   class="btn btn-sm btn-outline-success" onclick="return confirm('Unban this user?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.modal-backdrop {
    z-index: 1040;
}
.modal {
    z-index: 1050;
}
</style>

<?php include '../includes/footer.php'; ?>