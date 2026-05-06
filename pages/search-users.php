<?php
require_once '../config/database.php';
require_once '../classes/User.php';

$pageTitle = 'Search Users';

$search = sanitize($_GET['search'] ?? '');
$userObj = new User();

if (!empty($search)) {
    $users = $userObj->search($search);
} else {
    $users = $userObj->getAllUsers(50, 0);
}
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-users me-2"></i>Search Users</h2>
            <p class="text-muted">Find other learners and teachers by name, email, bio, or skill.</p>
        </div>
        <div class="col-md-4">
            <form class="d-flex" method="GET" action="<?php echo APP_URL; ?>/pages/search-users.php">
                <input type="search" class="form-control" name="search" placeholder="Search users..." value="<?php echo sanitize($search); ?>">
                <button class="btn btn-primary ms-2" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">No users found</h5>
                <p class="card-text">Try a different name, email, or skill keyword.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($users as $user): ?>
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex align-items-start gap-3">
                            <img src="<?php echo asset($user['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>" alt="<?php echo sanitize($user['name']); ?>" class="rounded-circle" style="width: 70px; height: 70px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1"><?php echo sanitize($user['name']); ?></h5>
                                <p class="text-muted mb-1"><?php echo sanitize($user['bio'] ?: 'No bio available.'); ?></p>
                                <p class="mb-2 small text-secondary">
                                    <strong><?php echo sanitize($user['points']); ?></strong> points &middot;
                                    <strong><?php echo sanitize($user['skills_count'] ?? 0); ?></strong> skills
                                </p>
                                <a href="<?php echo APP_URL; ?>/pages/profile.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
