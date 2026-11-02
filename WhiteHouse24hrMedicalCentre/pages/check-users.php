<?php
// check-users.php - Check user passwords in database
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>User Check</h1>";

$query = "SELECT user_id, username, password, LENGTH(password) as pwd_length FROM users";
$stmt = $db->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Password Hash</th><th>Length</th><th>Is Hashed?</th></tr>";

foreach ($users as $user) {
    $is_hashed = (strlen($user['password']) >= 60 && password_get_info($user['password'])['algo']);
    echo "<tr>";
    echo "<td>" . $user['user_id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . substr($user['password'], 0, 50) . "...</td>";
    echo "<td>" . $user['pwd_length'] . "</td>";
    echo "<td>" . ($is_hashed ? "✅ Yes" : "❌ No") . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Test Login with admin123</h2>";

// Test admin user
$admin_query = "SELECT * FROM users WHERE username = 'admin'";
$admin = $db->query($admin_query)->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    if (password_verify('admin123', $admin['password'])) {
        echo "<p style='color: green;'>✅ Password 'admin123' works for admin user!</p>";
    } else {
        echo "<p style='color: red;'>❌ Password 'admin123' does NOT work for admin user.</p>";
        
        // Create a new hash
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        echo "<p>New hash for 'admin123': " . $new_hash . "</p>";
        
        // Update the password
        $update = $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        if ($update->execute([$new_hash])) {
            echo "<p style='color: green;'>✅ Admin password has been reset! Try logging in now.</p>";
        }
    }
}

echo "<h2>Test Login with reception123</h2>";

$reception_query = "SELECT * FROM users WHERE username = 'reception'";
$reception = $db->query($reception_query)->fetch(PDO::FETCH_ASSOC);

if ($reception) {
    if (password_verify('reception123', $reception['password'])) {
        echo "<p style='color: green;'>✅ Password 'reception123' works for reception user!</p>";
    } else {
        echo "<p style='color: red;'>❌ Password 'reception123' does NOT work for reception user.</p>";
        
        $new_hash = password_hash('reception123', PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE users SET password = ? WHERE username = 'reception'");
        if ($update->execute([$new_hash])) {
            echo "<p style='color: green;'>✅ Reception password has been reset! Try logging in now.</p>";
        }
    }
}
?>