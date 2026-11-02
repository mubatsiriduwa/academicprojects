<?php
// pages/users.php - User Management (Admin only)
require_once '../includes/functions.php';
requireLogin();
requireAdmin();

$pageTitle = 'Manage Users';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

try {
    $db->query("SELECT is_active FROM users LIMIT 1");
    $has_is_active = true;
} catch (PDOException $e) {
    $has_is_active = false;
    try {
        $db->exec("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
        $db->exec("UPDATE users SET is_active = 1");
        $has_is_active = true;
    } catch (PDOException $ex) {}
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = sanitize($_POST['username']);
    $full_name = sanitize($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $errors = [];

    $check = $db->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->rowCount() > 0) { $errors[] = "Username already exists"; }
    if (strlen($password) < 6) { $errors[] = "Password must be at least 6 characters"; }
    if ($password !== $confirm_password) { $errors[] = "Passwords do not match"; }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($has_is_active) {
            $query = "INSERT INTO users (username, password, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)";
        } else {
            $query = "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)";
        }
        $stmt = $db->prepare($query);
        if ($stmt->execute([$username, $hashed_password, $full_name, $role])) {
            $message = "User added successfully!";
            logActivity($_SESSION['user_id'], 'USER_ADDED', "Added new user: $username");
        } else { $error = "Failed to add user"; }
    } else { $error = implode("<br>", $errors); }
}

if (isset($_GET['delete']) && !isset($_POST['add_user'])) {
    $user_id = (int)$_GET['delete'];
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account";
    } else {
        try {
            $db->beginTransaction();
            $user_query = $db->prepare("SELECT username FROM users WHERE user_id = ?");
            $user_query->execute([$user_id]);
            $user_data = $user_query->fetch(PDO::FETCH_ASSOC);
            $username = $user_data['username'] ?? 'Unknown';

            $db->prepare("DELETE FROM activity_log WHERE user_id = ?")->execute([$user_id]);
            $db->prepare("UPDATE visits SET registered_by = NULL WHERE registered_by = ?")->execute([$user_id]);
            $db->prepare("UPDATE visits SET checked_out_by = NULL WHERE checked_out_by = ?")->execute([$user_id]);
            $db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);

            $db->commit();
            $message = "User '$username' deleted successfully";
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Failed to delete user: " . $e->getMessage();
        }
    }
}

if (isset($_GET['toggle']) && $has_is_active) {
    $user_id = (int)$_GET['toggle'];
    if ($user_id != $_SESSION['user_id']) {
        $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?")->execute([$user_id]);
        $status_query = $db->prepare("SELECT is_active, username FROM users WHERE user_id = ?");
        $status_query->execute([$user_id]);
        $user = $status_query->fetch(PDO::FETCH_ASSOC);
        $status_text = $user['is_active'] ? 'activated' : 'deactivated';
        $message = "User '{$user['username']}' $status_text successfully";
    }
}

if ($has_is_active) {
    $query = "SELECT user_id, username, full_name, role, created_at, last_login, is_active FROM users ORDER BY created_at DESC";
} else {
    $query = "SELECT user_id, username, full_name, role, created_at, last_login, 1 as is_active FROM users ORDER BY created_at DESC";
}
$users = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container">
    <div class="users-header">
        <h1 class="dashboard-title"><i class="fas fa-users-cog"></i> Manage Users</h1>
        <button onclick="showAddUserForm()" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add New User</button>
    </div>

    <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

    <div id="addUserForm" class="add-user-form" style="display: none;">
        <h3><i class="fas fa-user-plus"></i> Add New User</h3>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group"><label>Username *</label><input type="text" name="username" required></div>
                <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
                <div class="form-group"><label>Password *</label><input type="password" name="password" required minlength="6"></div>
                <div class="form-group"><label>Confirm Password *</label><input type="password" name="confirm_password" required></div>
                <div class="form-group"><label>Role *</label><select name="role" required><option value="receptionist">Receptionist</option><option value="admin">Administrator</option></select></div>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_user" class="btn btn-success"><i class="fas fa-save"></i> Add User</button>
                <button type="button" onclick="hideAddUserForm()" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <h3>System Users</h3>
        <p class="records-count"><i class="fas fa-info-circle"></i> Total users: <?php echo count($users); ?></p>
        <?php if(empty($users)): ?>
            <div class="empty-message"><i class="fas fa-users"></i><p>No users found</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><?php if($has_is_active): ?><th>Status</th><?php endif; ?><th>Created</th><th>Last Login</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><span class="user-role <?php echo $user['role'] === 'admin' ? 'role-admin' : 'role-receptionist'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                        <?php if($has_is_active): ?><td><span class="user-status <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>"></span><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></td><?php endif; ?>
                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                        <td><?php echo $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td>
                            <div class="action-buttons">
                                <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                    <?php if($has_is_active): ?><a href="?toggle=<?php echo $user['user_id']; ?>" class="btn btn-small btn-warning" title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>" onclick="return confirm('Are you sure?')"><i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i></a><?php endif; ?>
                                    <a href="?delete=<?php echo $user['user_id']; ?>" class="btn btn-small btn-danger" title="Delete" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i></a>
                                <?php else: ?>
                                    <span class="btn btn-small disabled">Current User</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function showAddUserForm() { document.getElementById('addUserForm').style.display = 'block'; document.getElementById('addUserForm').scrollIntoView({ behavior: 'smooth' }); }
function hideAddUserForm() { document.getElementById('addUserForm').style.display = 'none'; }
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.transition = 'opacity 1s';
        alert.style.opacity = '0';
        setTimeout(function() { if (alert && alert.parentNode) { alert.remove(); } }, 1000);
    });
}, 5000);
</script>

<?php include '../includes/footer.php'; ?>
