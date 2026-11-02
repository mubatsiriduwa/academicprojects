<?php
// includes/footer.php
?>
    </main>

    <?php if(isset($_SESSION['user_id'])): ?>
    <footer class="footer">
        <div class="footer-container">
            <p>&copy; 2024 Whitehouse 24hr Medical Centre. All rights reserved.</p>
            <p>Reception Management System</p>
        </div>
    </footer>
    <?php endif; ?>

    <script src="../assets/js/main.js"></script>

    <?php if(isset($_SESSION['success_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success';
            alert.innerHTML = '<i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>';
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        });
    </script>
    <?php unset($_SESSION['success_message']); endif; ?>

    <?php if(isset($_SESSION['error_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.innerHTML = '<i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>';
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        });
    </script>
    <?php unset($_SESSION['error_message']); endif; ?>
</body>
</html>
