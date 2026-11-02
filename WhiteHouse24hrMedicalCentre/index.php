<?php
// index.php - Login Page (Root)
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit();
}

require_once 'config/database.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $username = $_POST['username'];
        $password = $_POST['password'];

        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                $error = "Your account has been deactivated. Please contact an administrator.";
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                $update = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
                $stmt = $db->prepare($update);
                $stmt->bindParam(':user_id', $user['user_id']);
                $stmt->execute();

                require_once 'includes/functions.php';
                logActivity($user['user_id'], 'LOGIN', 'User logged in successfully');

                header("Location: pages/dashboard.php");
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "User not found!";
        }
    } catch (Exception $e) {
        $error = "Database error. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whitehouse Medical - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="assets/images/Whitehouse24hr.png" alt="Whitehouse Medical Centre" class="login-logo">
                <h2>Reception Management System</h2>
                <p>Please login to continue</p>
            </div>

            <?php if($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" required placeholder="Enter your username">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="demo-info">
                <p><strong><i class="fas fa-info-circle"></i> Demo Credentials:</strong></p>
                <p>Admin: username: <strong>admin</strong> / password: <strong>admin123</strong></p>
                <p>Receptionist: username: <strong>reception</strong> / password: <strong>reception123</strong></p>
            </div>
        </div>
    </div>
</body>
</html>
