<?php
require_once '../config/database.php';
require_once '../classes/Transaction.php';

$pageTitle = 'Transactions';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=transactions.php');
}

$transactionObj = new Transaction();

// Get transactions
$transactions = $transactionObj->getUserTransactions(getUserId());
$totalEarned = $transactionObj->getTotalEarned(getUserId());
$totalSpent = $transactionObj->getTotalSpent(getUserId());
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-coins me-2"></i>My Transactions</h2>
    
    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon text-success"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-value text-success">+<?php echo $totalEarned; ?></div>
                <div class="stat-label">Total Earned</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon text-danger"><i class="fas fa-arrow-down"></i></div>
                <div class="stat-value text-danger">-<?php echo $totalSpent; ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon text-primary"><i class="fas fa-wallet"></i></div>
                <div class="stat-value text-primary"><?php echo $_SESSION['user_points']; ?></div>
                <div class="stat-label">Current Balance</div>
            </div>
        </div>
    </div>
    
    <!-- Transactions List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h4>No transactions yet</h4>
                <p>Start teaching or learning to see your transaction history</p>
                <a href="skills.php" class="btn btn-primary">Browse Skills</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                        <tr>
                            <td><?php echo formatDateTime($trans['created_at']); ?></td>
                            <td><?php echo sanitize($trans['description']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $trans['type'] === 'earned' ? 'success' : ($trans['type'] === 'spent' ? 'danger' : 'info'); ?>">
                                    <?php echo ucfirst($trans['type']); ?>
                                </span>
                            </td>
                            <td class="<?php echo $trans['type'] === 'earned' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                <?php echo $trans['type'] === 'earned' ? '+' : '-'; ?><?php echo $trans['amount']; ?>
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