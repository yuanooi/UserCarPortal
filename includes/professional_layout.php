<?php
// Professional Layout Template
// This file provides consistent professional styling across all pages

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include 'db.php';

// Get unread notification count for users
$unread_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND status = 'unread'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_count = $result->fetch_assoc()['unread'];
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'PrimeAuto Portal'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Professional Theme CSS -->
    <link href="assets/css/professional-theme.css" rel="stylesheet">
    
    <!-- Custom Page Styles -->
    <?php if (isset($custom_css)): ?>
        <style><?php echo $custom_css; ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>
    
    <!-- Main Content -->
    <main class="professional-section">
        <div class="professional-container">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- User Status Display -->
                <div class="professional-alert professional-alert-info alert-dismissible fade show mb-4" role="alert">
                    <div class="professional-alert-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div>
                        <strong>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</strong>
                        <span class="professional-badge professional-badge-<?php echo $_SESSION['role'] === 'admin' ? 'danger' : ($_SESSION['user_type'] === 'seller' ? 'success' : 'info'); ?> ms-2">
                            <?php 
                            if ($_SESSION['role'] === 'admin') {
                                echo 'Admin';
                            } elseif ($_SESSION['user_type'] === 'seller') {
                                echo 'Seller';
                            } else {
                                echo 'Buyer';
                            }
                            ?>
                        </span>
                        <small class="professional-text-muted d-block">You can now access all features</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Page Content -->
            <?php if (isset($page_content)): ?>
                <?php echo $page_content; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    <!-- Custom Page Scripts -->
    <?php if (isset($custom_js)): ?>
        <script><?php echo $custom_js; ?></script>
    <?php endif; ?>
    
    <!-- Professional Theme JS -->
    <script>
        // Professional Theme JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize professional animations
            const animatedElements = document.querySelectorAll('.professional-animate-fade-in-up, .professional-animate-fade-in-left, .professional-animate-fade-in-right');
            animatedElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Professional button hover effects
            const professionalButtons = document.querySelectorAll('.btn-professional, .btn-professional-outline');
            professionalButtons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Professional card hover effects
            const professionalCards = document.querySelectorAll('.professional-card');
            professionalCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>
