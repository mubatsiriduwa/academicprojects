<?php
// includes/header.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whitehouse Medical - <?php echo $pageTitle ?? 'Dashboard'; ?></title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php if(isset($_SESSION['user_id'])): ?>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="dashboard.php">
                    <img src="../assets/images/Whitehouse24hr.png" alt="Whitehouse Medical" class="logo">
                </a>
            </div>

            <div class="nav-toggle" id="navToggle">
                <i class="fas fa-bars"></i>
            </div>

            <ul class="nav-menu" id="navMenu">
                <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="register-patient.php" class="<?php echo $current_page == 'register-patient.php' ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Register Patient</a></li>
                <li><a href="patients-list.php" class="<?php echo $current_page == 'patients-list.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Patients List</a></li>
                <li><a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>

                <li class="nav-dropdown">
                    <a href="#" class="dropdown-toggle">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="profile.php" class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"><i class="fas fa-id-card"></i> My Profile</a></li>
                        <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                        <li><a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>"><i class="fas fa-user-cog"></i> Manage Users</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <script>
        document.getElementById('navToggle')?.addEventListener('click', function() {
            document.getElementById('navMenu').classList.toggle('active');
            const icon = this.querySelector('i');
            if (icon.classList.contains('fa-bars')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navMenu');
            const navToggle = document.getElementById('navToggle');
            if (!navToggle?.contains(event.target) && !navMenu?.contains(event.target)) {
                navMenu?.classList.remove('active');
                const icon = navToggle?.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });

        if (window.innerWidth > 768) {
            document.querySelectorAll('.nav-dropdown').forEach(item => {
                item.addEventListener('mouseenter', () => {
                    item.querySelector('.dropdown-menu').style.display = 'block';
                });
                item.addEventListener('mouseleave', () => {
                    item.querySelector('.dropdown-menu').style.display = 'none';
                });
            });
        } else {
            document.querySelectorAll('.nav-dropdown > .dropdown-toggle').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const dropdown = item.parentElement.querySelector('.dropdown-menu');
                    const isActive = item.parentElement.classList.contains('active');
                    document.querySelectorAll('.nav-dropdown').forEach(el => {
                        el.classList.remove('active');
                        el.querySelector('.dropdown-menu').style.display = 'none';
                    });
                    if (!isActive) {
                        item.parentElement.classList.add('active');
                        dropdown.style.display = 'block';
                    }
                });
            });
        }
    </script>
    <?php endif; ?>

    <main>
