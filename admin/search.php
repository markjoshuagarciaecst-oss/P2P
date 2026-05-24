<?php
require_once '../config/database.php';

$pageTitle = 'Content Search';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    redirect('../index.php');
}

$db = Database::getInstance();

$query    = trim($_GET['q'] ?? '');
$section  = $_GET['section'] ?? 'all'; // all | skills | users | bookings

$skillResults   = [];
$userResults    = [];
$bookingResults = [];

// Highlight helper — wraps matched text in a <mark> tag
function highlight(string $text, string $query): string {
    if ($query === '') return htmlspecialchars($text);
    return preg_replace(
        '/(' . preg_quote(htmlspecialchars($query), '/') . ')/iu',
        '<mark class="bg-warning px-0">$1</mark>',
        htmlspecialchars($text)
    );
}

if ($query !== '') {
    $like = "%$query%";

    if ($section === 'all' || $section === 'skills') {
        $stmt = $db->prepare("
            SELECT s.id, s.title, s.description, s.category, s.skill_level,
                   s.points_required, s.is_active, s.created_at,
                   u.name AS teacher_name, u.email AS teacher_email
            FROM skills s
            JOIN users u ON u.id = s.user_id
            WHERE s.title LIKE ? OR s.description LIKE ? OR s.category LIKE ?
            ORDER BY s.is_active DESC, s.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$like, $like, $like]);
        $skillResults = $stmt->fetchAll();
    }

    if ($section === 'all' || $section === 'users') {
        $stmt = $db->prepare("
            SELECT id, name, email, bio, points, is_active, role, created_at
            FROM users
            WHERE name LIKE ? OR email LIKE ? OR bio LIKE ?
            ORDER BY is_active DESC, created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$like, $like, $like]);
        $userResults = $stmt->fetchAll();
    }

    if ($section === 'all' || $section === 'bookings') {
        $stmt = $db->prepare("
            SELECT b.id, b.status, b.scheduled_date, b.created_at,
                   s.title AS skill_title, s.description AS skill_desc,
                   l.name AS learner_name, l.email AS learner_email,
                   t.name AS teacher_name, t.email AS teacher_email
            FROM bookings b
            JOIN skills s ON s.id = b.skill_id
            JOIN users  l ON l.id = b.learner_id
            JOIN users  t ON t.id = b.teacher_id
            WHERE s.title LIKE ? OR s.description LIKE ?
               OR l.name  LIKE ? OR t.name LIKE ?
               OR l.email LIKE ? OR t.email LIKE ?
            ORDER BY b.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$like, $like, $like, $like, $like, $like]);
        $bookingResults = $stmt->fetchAll();
    }
}

$totalResults = count($skillResults) + count($userResults) + count($bookingResults);

// Suggested flagged words for quick moderation
$flaggedWords = [
    'Inappropriate content' => ['sex','nude','naked','porn','xxx','adult','escort','drugs','hack','scam','fake','cheat','illegal'],
    'Hate speech'           => ['hate','racist','kill','violence','abuse','bully','harass'],
    'Spam / fraud'          => ['free money','get rich','bitcoin','crypto','invest','guaranteed','click here','buy now'],
];
?>
<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-search me-2"></i>Content Moderation Search</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <!-- Search form -->
    <div class="card mb-4 border-primary">
        <div class="card-body">
            <form method="GET" id="searchForm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Search keyword or phrase</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="q" id="searchInput" class="form-control form-control-lg"
                                   placeholder="e.g. inappropriate, scam, free money…"
                                   value="<?php echo htmlspecialchars($query); ?>"
                                   autocomplete="off">
                            <!-- Live suggestions dropdown -->
                            <div id="suggestions" class="dropdown-menu w-100" style="display:none;position:absolute;top:100%;z-index:1000;"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Search in</label>
                        <select name="section" class="form-select form-select-lg">
                            <option value="all"      <?php echo $section==='all'      ?'selected':''; ?>>All sections</option>
                            <option value="skills"   <?php echo $section==='skills'   ?'selected':''; ?>>Skills only</option>
                            <option value="users"    <?php echo $section==='users'    ?'selected':''; ?>>Users only</option>
                            <option value="bookings" <?php echo $section==='bookings' ?'selected':''; ?>>Bookings only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick-select flagged words -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-flag me-2 text-danger"></i>Quick Moderation — click a word to search it</h6>
        </div>
        <div class="card-body">
            <?php foreach ($flaggedWords as $category => $words): ?>
            <div class="mb-2">
                <small class="text-muted fw-semibold me-2"><?php echo $category; ?>:</small>
                <?php foreach ($words as $word): ?>
                <a href="?q=<?php echo urlencode($word); ?>&section=all"
                   class="badge text-decoration-none me-1 mb-1 <?php echo strtolower($query) === strtolower($word) ? 'bg-danger' : 'bg-secondary'; ?>"
                   style="font-size:.8rem">
                    <?php echo htmlspecialchars($word); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($query !== ''): ?>

    <!-- Result summary -->
    <div class="alert <?php echo $totalResults > 0 ? 'alert-warning' : 'alert-success'; ?> d-flex align-items-center mb-4">
        <i class="fas <?php echo $totalResults > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?> me-2 fs-5"></i>
        <div>
            <?php if ($totalResults > 0): ?>
            Found <strong><?php echo $totalResults; ?></strong> result(s) matching
            "<strong><?php echo htmlspecialchars($query); ?></strong>"
            — <?php echo count($skillResults); ?> skill(s), <?php echo count($userResults); ?> user(s), <?php echo count($bookingResults); ?> booking(s).
            <?php else: ?>
            No results found for "<strong><?php echo htmlspecialchars($query); ?></strong>". The platform looks clean for this term.
            <?php endif; ?>
        </div>
    </div>

    <!-- Skills results -->
    <?php if (!empty($skillResults)): ?>
    <div class="card mb-4">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Skills (<?php echo count($skillResults); ?>)</h5>
            <a href="skills.php?search=<?php echo urlencode($query); ?>" class="btn btn-sm btn-light">
                Manage in Skills page →
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Title</th><th>Description</th><th>Teacher</th><th>Category</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($skillResults as $s): ?>
                    <tr class="<?php echo $s['is_active'] ? '' : 'table-secondary'; ?>">
                        <td class="fw-semibold"><?php echo highlight($s['title'], $query); ?></td>
                        <td><small><?php echo highlight(mb_substr($s['description'] ?? '', 0, 120), $query); ?>…</small></td>
                        <td>
                            <?php echo htmlspecialchars($s['teacher_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($s['teacher_email']); ?></small>
                        </td>
                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($s['category']); ?></span></td>
                        <td>
                            <?php if ($s['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Removed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="skills.php?action=delete&id=<?php echo $s['id']; ?>&search=<?php echo urlencode($query); ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Remove this skill from the platform?')">
                                <i class="fas fa-trash me-1"></i>Remove
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Users results -->
    <?php if (!empty($userResults)): ?>
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Users (<?php echo count($userResults); ?>)</h5>
            <a href="users.php?search=<?php echo urlencode($query); ?>" class="btn btn-sm btn-dark">
                Manage in Users page →
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Email</th><th>Bio</th><th>Points</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($userResults as $u): ?>
                    <tr class="<?php echo $u['is_active'] ? '' : 'table-secondary'; ?>">
                        <td class="fw-semibold"><?php echo highlight($u['name'], $query); ?></td>
                        <td><small><?php echo highlight($u['email'], $query); ?></small></td>
                        <td><small><?php echo highlight(mb_substr($u['bio'] ?? '', 0, 100), $query); ?></small></td>
                        <td><span class="badge bg-warning text-dark"><?php echo $u['points']; ?></span></td>
                        <td>
                            <?php if ($u['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Banned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_active'] && $u['role'] !== 'admin'): ?>
                            <a href="users.php?action=ban&id=<?php echo $u['id']; ?>"
                               class="btn btn-sm btn-warning"
                               onclick="return confirm('Ban this user?')">
                                <i class="fas fa-ban me-1"></i>Ban
                            </a>
                            <?php elseif (!$u['is_active']): ?>
                            <a href="users.php?action=unban&id=<?php echo $u['id']; ?>"
                               class="btn btn-sm btn-success"
                               onclick="return confirm('Unban this user?')">
                                <i class="fas fa-check me-1"></i>Unban
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bookings results -->
    <?php if (!empty($bookingResults)): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Bookings (<?php echo count($bookingResults); ?>)</h5>
            <a href="bookings.php?search=<?php echo urlencode($query); ?>" class="btn btn-sm btn-light">
                Manage in Bookings page →
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Skill</th><th>Learner</th><th>Teacher</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bookingResults as $b): ?>
                    <tr>
                        <td class="text-muted small"><?php echo $b['id']; ?></td>
                        <td><?php echo highlight($b['skill_title'], $query); ?></td>
                        <td>
                            <?php echo highlight($b['learner_name'], $query); ?><br>
                            <small class="text-muted"><?php echo highlight($b['learner_email'], $query); ?></small>
                        </td>
                        <td>
                            <?php echo highlight($b['teacher_name'], $query); ?><br>
                            <small class="text-muted"><?php echo highlight($b['teacher_email'], $query); ?></small>
                        </td>
                        <td>
                            <?php
                            $sc = ['pending'=>'warning','accepted'=>'info','completed'=>'success','cancelled'=>'secondary','rejected'=>'danger'];
                            ?>
                            <span class="badge bg-<?php echo $sc[$b['status']] ?? 'secondary'; ?>"><?php echo ucfirst($b['status']); ?></span>
                        </td>
                        <td><small><?php echo formatDate($b['scheduled_date']); ?></small></td>
                        <td>
                            <?php if (in_array($b['status'], ['pending','accepted'])): ?>
                            <a href="bookings.php?action=cancel&id=<?php echo $b['id']; ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Cancel this booking?')">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // end if query ?>
</div>

<script>
// Live suggestions as you type — pulls distinct words from skills titles/descriptions
const input = document.getElementById('searchInput');
const box   = document.getElementById('suggestions');
let timer;

input.addEventListener('input', function () {
    clearTimeout(timer);
    const val = this.value.trim();
    if (val.length < 2) { box.style.display = 'none'; return; }

    timer = setTimeout(() => {
        fetch('<?php echo APP_URL; ?>/admin/search_suggest.php?q=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => {
                if (!data.length) { box.style.display = 'none'; return; }
                box.innerHTML = data.map(w =>
                    `<a class="dropdown-item" href="?q=${encodeURIComponent(w)}&section=<?php echo urlencode($section); ?>">${w}</a>`
                ).join('');
                box.style.display = 'block';
            });
    }, 250);
});

document.addEventListener('click', e => {
    if (!e.target.closest('#searchInput')) box.style.display = 'none';
});
</script>

<?php include '../includes/footer.php'; ?>
