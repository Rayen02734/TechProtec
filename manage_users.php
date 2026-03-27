<?php
/**
 * TechProtect v2.1 – Admin: Manage Users
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(ROLE_ADMIN);
$db = getDB();

$errors = [];

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fn    = sanitize($_POST['full_name']   ?? '');
    $email = sanitize($_POST['email']       ?? '');
    $role  = sanitize($_POST['role']        ?? '');
    $pass  = $_POST['password'] ?? '';
    $badge = sanitize($_POST['badge_number']?? '');

    if (!$fn || !$email || !$role || strlen($pass) < 8)
        $errors[] = 'All required fields must be filled. Password min 8 chars.';
    elseif (!in_array($role, ['admin','police','client']))
        $errors[] = 'Invalid role.';
    else {
        $chk = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) { $errors[] = 'Email already exists.'; }
        else {
            $db->prepare("INSERT INTO users (full_name,email,password_hash,role,badge_number) VALUES(?,?,?,?,?)")
               ->execute([$fn, strtolower($email), hashPassword($pass), $role, $badge ?: null]);
            logAction(currentUserId(), 'ADMIN_ADD_USER', "Added user: $email ($role)");
            setFlash('success', "User $email added successfully.");
            redirect(APP_URL . '/admin/manage_users.php');
        }
    }
}

// Toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    $uid = (int)$_POST['uid'];
    if ($uid !== currentUserId()) {
        $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([$uid]);
        logAction(currentUserId(), 'ADMIN_TOGGLE_USER', "Toggled active for user #$uid");
    }
    redirect(APP_URL . '/admin/manage_users.php');
}

// Filter
$roleFilter = $_GET['role'] ?? '';
$q          = trim($_GET['q'] ?? '');
$where = 'WHERE 1=1';
$params = [];
if ($roleFilter && in_array($roleFilter, ['admin','police','client'])) {
    $where .= ' AND role = ?'; $params[] = $roleFilter;
}
if ($q) {
    $where .= ' AND (full_name LIKE ? OR email LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%";
}
$stmt = $db->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle  = 'Manage Users';
$activePage = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fa-solid fa-users"></i> User Management</div>

<!-- Add User Form -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-plus"></i> Add New User</div></div>
    <?php if ($errors): ?>
    <div class="flash flash-error" style="margin-bottom:12px;">
        <i class="fa-solid fa-circle-xmark"></i><?= e(implode(' ', $errors)) ?>
    </div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Role <span class="required">*</span></label>
                <select name="role" class="form-control" required>
                    <option value="">— Select Role —</option>
                    <option value="client">Client</option>
                    <option value="police">Police</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password <span class="required">*</span></label>
                <input type="password" name="password" class="form-control" placeholder="8+ characters" required>
            </div>
        </div>
        <div class="form-group">
            <label>Badge Number <span style="color:var(--text-dim)">(police only)</span></label>
            <input type="text" name="badge_number" class="form-control" placeholder="Police badge number">
        </div>
        <button type="submit" name="add_user" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add User</button>
    </form>
</div>

<!-- Filter Bar -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex:1;min-width:0;">
        <div class="input-icon" style="flex:1;">
            <i class="fa-solid fa-search"></i>
            <input type="text" name="q" class="form-control" placeholder="Search name or email…" value="<?= e($q) ?>">
        </div>
        <select name="role" class="form-control" style="width:140px;">
            <option value="">All Roles</option>
            <option value="admin"  <?= $roleFilter==='admin'  ? 'selected':'' ?>>Admin</option>
            <option value="police" <?= $roleFilter==='police' ? 'selected':'' ?>>Police</option>
            <option value="client" <?= $roleFilter==='client' ? 'selected':'' ?>>Client</option>
        </select>
        <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-filter"></i></button>
    </form>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Last Login</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr style="<?= !$u['is_active'] ? 'opacity:.5' : '' ?>">
                <td style="font-size:.8rem;color:var(--text-dim)">#<?= $u['id'] ?></td>
                <td>
                    <strong><?= e($u['full_name']) ?></strong>
                    <?php if ($u['badge_number']): ?>
                    <br><small style="color:var(--text-muted)">🎖 <?= e($u['badge_number']) ?></small>
                    <?php endif; ?>
                </td>
                <td style="font-size:.85rem"><?= e($u['email']) ?></td>
                <td><span class="badge badge-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span></td>
                <td>
                    <?php if ($u['is_active']): ?>
                    <span style="color:var(--green);font-size:.82rem;"><i class="fa-solid fa-circle" style="font-size:.5rem;margin-right:4px;"></i>Active</span>
                    <?php else: ?>
                    <span style="color:var(--red);font-size:.82rem;"><i class="fa-solid fa-circle" style="font-size:.5rem;margin-right:4px;"></i>Disabled</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?= formatDate($u['created_at'],'d/m/Y') ?></td>
                <td style="font-size:.8rem;color:var(--text-muted)"><?= $u['last_login'] ? formatDate($u['last_login'],'d/m H:i') : '—' ?></td>
                <td>
                    <?php if ($u['id'] !== currentUserId()): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                        <button type="submit" name="toggle_user" class="btn btn-ghost btn-sm">
                            <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
