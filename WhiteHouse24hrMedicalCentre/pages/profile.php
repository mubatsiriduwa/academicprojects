<?php
// pages/profile.php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'My Profile';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? 0;

$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../index.php?error=session_expired");
    exit();
}

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $query = "UPDATE users SET full_name = :full_name WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $user['full_name'] = $full_name;
        $message = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = :password WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $hashed);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
        } else {
            $error = "Failed to change password.";
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="profile-container">
        <h1 class="dashboard-title"><i class="fas fa-id-card"></i> My Profile</h1>

        <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar"><i class="fas fa-user-circle"></i></div>
                <div class="profile-title">
                    <h2><?php echo htmlspecialchars($user['full_name'] ?? 'Unknown'); ?></h2>
                    <p><i class="fas fa-tag"></i> <?php echo ucfirst($user['role'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                <form method="POST">
                    <div class="form-group"><label>Username</label><input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly></div>
                    <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required></div>
                    <div class="form-group"><label>Role</label><input type="text" value="<?php echo ucfirst($user['role'] ?? 'N/A'); ?>" readonly></div>
                    <div class="form-group"><label>Member Since</label><input type="text" value="<?php echo date('d M Y', strtotime($user['created_at'] ?? 'now')); ?>" readonly></div>
                    <div class="form-group"><label>Last Login</label><input type="text" value="<?php echo ($user['last_login'] && $user['last_login'] != '0000-00-00 00:00:00') ? date('d M Y H:i', strtotime($user['last_login'])) : 'Just now'; ?>" readonly></div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <div class="profile-section">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <form method="POST" class="password-form">
                    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" id="new_password" required minlength="6"></div>
                    <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
                    <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('new_password')?.addEventListener('input', function() {
    const strength = checkPasswordStrength(this.value);
    updateStrengthMeter(strength);
});
</script>

<?php include '../includes/footer.php'; ?>
