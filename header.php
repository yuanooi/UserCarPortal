<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection for notification count
include 'includes/db.php';

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Responsive Design -->
    <link href="assets/css/responsive.css" rel="stylesheet">
    <!-- Mobile Optimization -->
    <script src="assets/js/mobile-optimization.js" defer></script>
    
    <style>
        :root {
            --primary-color: #1e40af;
            --primary-hover: #1d4ed8;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            --gradient-success: linear-gradient(135deg, #059669 0%, #10b981 100%);
            --gradient-warning: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            --gradient-danger: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Modern Header Styles */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1050;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
                   overflow: visible !important;
        }

        .header.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-lg);
        }

        /* Modern Navbar */
        .navbar {
            padding: 1rem 0;
            min-height: 80px;
                   overflow: visible !important;
        }

        .navbar-brand {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary-color) !important;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand:hover {
            color: var(--primary-hover) !important;
            transform: translateY(-1px);
        }

        .navbar-brand i {
            font-size: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Modern Navigation Links */
        .nav-link {
            color: var(--secondary-color) !important;
            font-weight: 500;
            padding: 0.75rem 1rem !important;
            border-radius: var(--border-radius);
            transition: var(--transition);
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            background: rgba(30, 64, 175, 0.08);
            transform: translateY(-1px);
        }

        .nav-link.active {
            color: var(--primary-color) !important;
            background: rgba(30, 64, 175, 0.12);
            font-weight: 600;
        }

        .nav-link i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        /* Modern Badges */
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .badge.bg-primary {
            background: var(--gradient-primary) !important;
        }

        .badge.bg-success {
            background: var(--gradient-success) !important;
        }

        .badge.bg-danger {
            background: var(--gradient-danger) !important;
        }

        .badge.bg-warning {
            background: var(--gradient-warning) !important;
        }

        /* Modern Buttons */
        .btn {
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: var(--transition);
            border: none;
            padding: 0.75rem 1.5rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: var(--gradient-primary);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--gradient-success);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Modern Dropdown */
        .dropdown-menu {
            border: none;
            box-shadow: var(--shadow-lg);
            border-radius: var(--border-radius-lg);
            padding: 0.5rem;
            margin-top: 0.5rem;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            z-index: 1060;
            min-width: 200px;
            position: absolute;
            top: 100%;
            left: 0;
            display: none;
        }
               
               .dropdown-menu.show {
                   display: block;
                   opacity: 1;
                   visibility: visible;
               }
               
               /* Simple dropdown styles */
               .dropdown-arrow {
                   transition: var(--transition-fast);
                   font-size: 0.8rem;
                   color: var(--secondary-color);
        }

        .dropdown-item {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: var(--transition);
            color: var(--secondary-color);
        }

        .dropdown-item:hover {
            background: rgba(30, 64, 175, 0.08);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
        }

        /* Modern Toggle Button */
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .navbar-toggler:hover {
            background: rgba(30, 64, 175, 0.08);
        }

        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.25);
        }

        /* Notification Badge */
        .badge-notify {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--gradient-danger);
            color: white;
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-weight: 700;
            animation: pulse 2s infinite;
        }

        /* User Status Display */
        .badge-sm {
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
            margin-top: 0.125rem;
        }

        .dropdown-toggle::after {
            margin-left: 0.5rem;
            display: inline-block;
            vertical-align: middle;
        }
        
        /* Ensure all dropdown toggles show the arrow */
        .nav-link.dropdown-toggle::after {
            content: "" !important;
            border-top: 0.3em solid !important;
            border-right: 0.3em solid transparent !important;
            border-bottom: 0 !important;
            border-left: 0.3em solid transparent !important;
            margin-left: 0.5rem !important;
            display: inline-block !important;
            vertical-align: middle !important;
            width: 0 !important;
            height: 0 !important;
        }
        
        /* Force dropdown arrow for all dropdown toggles */
        .navbar .nav-link.dropdown-toggle::after {
            content: "" !important;
            border-top: 0.3em solid !important;
            border-right: 0.3em solid transparent !important;
            border-bottom: 0 !important;
            border-left: 0.3em solid transparent !important;
            margin-left: 0.5rem !important;
            display: inline-block !important;
            vertical-align: middle !important;
            width: 0 !important;
            height: 0 !important;
        }
        
        /* Specific fix for Communication dropdown */
        #commDropdown::after {
            content: "" !important;
            border-top: 0.3em solid !important;
            border-right: 0.3em solid transparent !important;
            border-bottom: 0 !important;
            border-left: 0.3em solid transparent !important;
            margin-left: 0.5rem !important;
            display: inline-block !important;
            vertical-align: middle !important;
            width: 0 !important;
            height: 0 !important;
        }

        .dropdown-menu-end {
            right: 0;
            left: auto;
        }

        .nav-link.dropdown-toggle {
            padding: 0.5rem 1rem;
        }

        .nav-link.dropdown-toggle:hover {
            background: rgba(30, 64, 175, 0.08);
        }
        
        .nav-item.dropdown {
            position: relative;
        }
        
        .navbar-nav {
            overflow: visible !important;
        }
        
        .navbar-collapse {
            overflow: visible !important;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Responsive Design */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.98);
                border-radius: var(--border-radius-lg);
                margin-top: 1rem;
                padding: 1rem;
                box-shadow: var(--shadow-lg);
            }

            .nav-link {
                margin: 0.25rem 0;
                justify-content: flex-start;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 1.5rem;
            }
            
            .navbar {
                padding: 0.75rem 0;
            }
        }

        /* Login modal styles - Add to existing styles */
        .modal-backdrop {
            backdrop-filter: blur(3px);
            background-color: rgba(0, 0, 0, 0.3);
        }

        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
            border: none;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #loginModal .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        #loginModal .input-group:focus-within .form-control,
        #loginModal .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        #loginModal .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        @media (max-width: 576px) {
            #loginModal .modal-dialog {
            margin: 1rem;
        }
    }

        /* Main navigation container */
        .navbar {
            padding: var(--spacing-sm) var(--spacing-md);
            min-height: 80px;
            display: flex;
            align-items: center;
            /* Key: Increase internal spacing */
            gap: var(--spacing-md);
        }

        /* Logo container - Fixed space + spacing */
        .navbar-brand-container {
            flex-shrink: 0;
            width: 220px; /* Slightly increase width */
            display: flex;
            align-items: center;
            /* Add right margin to create spacing */
            margin-right: var(--spacing-lg);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            flex-shrink: 0;
            min-width: 0;
            /* Add hover effect */
            transition: var(--transition);
            padding: var(--spacing-sm) 0;
        }

        .navbar-brand:hover {
            color: #1d4ed8 !important;
            transform: translateY(-1px);
        }

        /* Navigation content container */
        .navbar-content {
            flex: 1;
            display: flex;
            align-items: center;
            min-width: 0;
            /* Add left margin for further separation */
            padding-left: var(--spacing-md);
            /* Key: Set border to create visual separation */
            position: relative;
        }

        .navbar-content::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            bottom: 20%;
            width: 1px;
            background: rgba(0, 0, 0, 0.1);
            height: 60%;
        }

        /* Hamburger menu button container */
        .navbar-toggler-container {
            flex-shrink: 0;
            width: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Add left margin */
            margin-left: var(--spacing-md);
        }

        .navbar-toggler {
            border: none;
            padding: 0.25rem;
            line-height: 1;
            border-radius: 6px;
            transition: var(--transition);
        }

        .navbar-toggler:hover {
            background: rgba(37, 99, 235, 0.1);
        }

        .navbar-collapse {
            flex-grow: 1;
            max-width: calc(100% - 280px); /* Adjust for new spacing */
            overflow: visible;
        }

        .navbar-nav {
            flex-wrap: wrap;
            max-width: 100%;
            overflow: visible;
            justify-content: flex-start;
            gap: 0.25rem; /* Increase navigation item spacing */
            /* Add left padding for further separation */
            padding-left: var(--spacing-sm);
        }

        .nav-link {
            color: var(--secondary-color) !important;
            font-weight: 500;
            transition: var(--transition);
            border-radius: 8px;
            padding: 0.5rem 0.8rem; /* Slightly increase padding */
            margin: 0 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            flex-shrink: 0;
            min-width: 0;
            text-decoration: none;
            /* Increase hover spacing */
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 2px;
            height: 50%;
            background: transparent;
            transition: background 0.2s ease;
            transform: translateY(-50%);
        }

        .nav-link:hover::before {
            background: var(--primary-color);
        }

        .nav-link i {
            width: 12px;
            text-align: center;
            margin-right: 0.3rem; /* Increase icon and text spacing */
            flex-shrink: 0;
            font-size: 0.8rem;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            background-color: rgba(37, 99, 235, 0.08);
            transform: translateY(-1px);
        }

        .nav-link.text-danger {
            color: #dc3545 !important;
        }

        .nav-link.text-danger:hover {
            color: #ff4c4c !important;
            background-color: rgba(220, 53, 69, 0.08);
        }

        .nav-link.protected {
            position: relative;
        }

        .nav-link.protected::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0.2rem;
            width: 1px;
            height: 50%;
            background: var(--primary-color);
            opacity: 0;
            transition: opacity 0.2s ease;
            transform: translateY(-50%);
        }

        .nav-link.protected:hover::after {
            opacity: 1;
        }

        .badge-notify {
            background-color: #dc3545;
            color: white;
            font-size: 0.65rem;
            padding: 0.15em 0.3em;
            margin-left: 0.3rem; /* Increase badge spacing */
            border-radius: 10px;
            white-space: nowrap;
            flex-shrink: 0;
            line-height: 1;
            /* Add hover effect */
            transition: var(--transition);
        }

        .badge-notify:hover {
            background-color: #b91c1c;
            transform: scale(1.05);
        }

        .container {
            max-width: 100%;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        /* Responsive optimization */
        @media (max-width: 1400px) {
            .navbar-brand-container { width: 200px; margin-right: var(--spacing-md); }
            .navbar-brand { font-size: 1.4rem; }
            .navbar-collapse { max-width: calc(100% - 260px); }
            .nav-link { max-width: 130px; font-size: 0.8rem; padding: 0.45rem 0.6rem; }
        }

        @media (max-width: 1200px) {
            .navbar-brand-container { width: 180px; margin-right: var(--spacing-sm); }
            .navbar-brand { font-size: 1.3rem; }
            .navbar-collapse { max-width: calc(100% - 240px); }
            .nav-link { max-width: 120px; font-size: 0.75rem; padding: 0.4rem 0.5rem; }
            .navbar-nav { gap: 0.2rem; }
        }

        @media (max-width: 992px) {
            .navbar-brand-container { width: 160px; margin-right: var(--spacing-sm); }
            .navbar-brand { font-size: 1.2rem; }
            .navbar-collapse { max-width: calc(100% - 220px); }
            .nav-link { max-width: 110px; font-size: 0.7rem; padding: 0.35rem 0.45rem; }
            .navbar-nav { gap: 0.15rem; padding-left: var(--spacing-xs); }
        }

        @media (max-width: 768px) {
            .header { min-height: 70px; }
            .navbar { 
                padding: 0.5rem 0.75rem; 
                min-height: 70px; 
                gap: var(--spacing-sm);
            }
            .navbar-brand-container { 
                width: 140px; 
                margin-right: var(--spacing-sm);
            }
            .navbar-brand { font-size: 1.1rem; }
            .navbar-toggler-container { width: 40px; margin-left: var(--spacing-sm); }
            
            .navbar-collapse {
                max-width: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border-top: 1px solid #e5e7eb;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                border-radius: 0 0 12px 12px;
                padding: 1.5rem; /* Increase padding */
                margin: 0;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.75rem; /* Increase vertical spacing */
                width: 100%;
                padding: 0;
            }
            
            .nav-link {
                max-width: none;
                width: 100%;
                justify-content: flex-start;
                font-size: 1rem; /* Restore normal font size on mobile */
                padding: 0.75rem 1rem; /* Increase click area */
                margin: 0;
                border-radius: 8px;
                text-align: left;
                /* Add hover background */
                background: transparent;
            }
            
            .nav-link:hover {
                background-color: rgba(37, 99, 235, 0.05);
                transform: none;
                margin-left: 0.5rem; /* Slight indent effect */
            }
            
            .nav-link i { 
                margin-right: 0.75rem; 
                width: 18px; 
                font-size: 1.1rem; 
            }
            
            .nav-link.protected::after { display: none; }
            
            /* Mobile badge optimization */
            .badge-notify {
                margin-left: 0.5rem;
                font-size: 0.75rem;
                padding: 0.25em 0.5em;
            }
        }

        @media (max-width: 576px) {
            .container { padding-left: 0.5rem; padding-right: 0.5rem; }
            .navbar-brand-container { width: 120px; }
            .navbar-brand { font-size: 1rem; }
            .navbar { padding: 0.5rem; gap: 0.5rem; }
        }

        /* Prevent page scrollbar */
        html, body { overflow-x: hidden; }
        .navbar-collapse.show { overflow: visible; }

        /* Visual enhancement: Add Logo shadow */
        .navbar-brand-container {
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.8) 20%, rgba(255,255,255,0.8) 80%, transparent 100%);
        }
    </style>
</head>
<body>
<!-- Modern Header -->
<header class="header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">
            <!-- Brand Logo -->
            <a class="navbar-brand" href="<?php 
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    echo 'admin_dashboard.php';
                } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'seller') {
                    echo 'user_dashboard.php';
                } else {
                    echo 'index.php';
                }
            ?>" title="PrimeAuto Portal">
                <i class="fas fa-car-side"></i>
                <span class="ms-2">PrimeAuto Portal</span>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Home -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php 
                            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                                echo 'admin_dashboard.php';
                            } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'seller') {
                                echo 'user_dashboard.php';
                            } else {
                                echo 'index.php';
                            }
                        ?>" title="Home">
                            <i class="fas fa-home"></i>
                            <span class="ms-1">Home</span>
                        </a>
                    </li>

                    <?php if (isset($_SESSION['user_id'])): ?>

                        <?php if (isset($_SESSION['role'])): ?>
                            <!-- User Menu -->
                            <?php if ($_SESSION['role'] === 'user'): ?>
                                <?php if ($_SESSION['user_type'] === 'buyer'): ?>
                                    <!-- Buyer Features -->
                                    <!-- Shopping Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="buyerShoppingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-shopping-cart"></i><span>Shopping</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="buyerShoppingDropdown">
                                            <li><a class="dropdown-item protected" href="cancel_order.php"><i class="fas fa-shopping-cart me-2"></i>My Orders</a></li>
                                            <li><a class="dropdown-item protected" href="my_favorites.php"><i class="fas fa-heart me-2"></i>My Favorites</a></li>
                                            <li><a class="dropdown-item protected" href="history.php"><i class="fas fa-history me-2"></i>Purchase History</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Vehicle Services Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="buyerVehicleDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-car"></i><span>Vehicles</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="buyerVehicleDropdown">
                                            <li><a class="dropdown-item protected" href="my_acquisitions.php"><i class="fas fa-handshake me-2"></i>My Acquisitions</a></li>
                                            <li><a class="dropdown-item protected" href="book_test_drive.php"><i class="fas fa-calendar-check me-2"></i>Book Test Drive</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Communication Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="buyerCommDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-comments"></i><span>Chat</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="buyerCommDropdown">
                                            <li><a class="dropdown-item protected" href="chat.php"><i class="fas fa-comments me-2"></i>Chat Support</a></li>
                                        </ul>
                                    </li>
                                <?php elseif ($_SESSION['user_type'] === 'seller'): ?>
                                    <!-- Seller Features -->
                                    <!-- Vehicle Management Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="sellerVehicleDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-car"></i><span>Vehicles</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="sellerVehicleDropdown">
                                            <li><a class="dropdown-item protected" href="seller_vehicle_management.php"><i class="fas fa-car me-2"></i>Vehicle Management</a></li>
                                            <li><a class="dropdown-item protected" href="user_dashboard.php"><i class="fas fa-warehouse me-2"></i>My Vehicles</a></li>
                                            <li><a class="dropdown-item protected" href="my_acquisitions.php"><i class="fas fa-handshake me-2"></i>My Acquisitions</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Sales Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="sellerSalesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-shopping-cart"></i><span>Sales</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="sellerSalesDropdown">
                                            <li><a class="dropdown-item protected" href="seller_purchase_offers.php"><i class="fas fa-shopping-cart me-2"></i>Purchase Offers</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Communication Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="sellerCommDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-comments"></i><span>Chat</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="sellerCommDropdown">
                                            <li><a class="dropdown-item protected" href="chat.php"><i class="fas fa-comments me-2"></i>Chat Support</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Notifications -->
                                    <li class="nav-item">
                                        <a class="nav-link position-relative protected" href="seller_notifications_view.php" title="Notifications">
                                            <i class="fas fa-bell"></i>
                                            <span class="ms-1">Alerts</span>
                                            <?php if ($unread_count > 0): ?>
                                                <span class="badge-notify"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <!-- Common Features -->
                                    <li class="nav-item">
                                        <a class="nav-link protected" href="reviews.php" title="Car Reviews">
                                            <i class="fas fa-star"></i><span>Reviews</span>
                                        </a>
                                    </li>
                                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                    <!-- Admin Menu -->
                                    <!-- Vehicle Management Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="vehicleDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-car"></i><span>Vehicles</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="vehicleDropdown">
                                            <li><a class="dropdown-item protected" href="admincar_details.php"><i class="fas fa-gavel me-2"></i>Review Vehicles</a></li>
                                            <li><a class="dropdown-item protected" href="admin_edit_car.php"><i class="fas fa-edit me-2"></i>Edit Vehicle</a></li>
                                            <li><a class="dropdown-item protected" href="admin_unlist_car.php"><i class="fas fa-eye-slash me-2"></i>Unlist Vehicle</a></li>
                                            <li><a class="dropdown-item protected" href="admin_vehicle_issues.php"><i class="fas fa-exclamation-triangle me-2"></i>Vehicle Issues</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Orders & Sales Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="ordersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-shopping-cart"></i><span>Orders</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="ordersDropdown">
                                            <li><a class="dropdown-item protected" href="admin_orders.php"><i class="fas fa-shopping-cart me-2"></i>Admin Orders</a></li>
                                            <li><a class="dropdown-item protected" href="admin_purchase_management.php"><i class="fas fa-handshake me-2"></i>Purchase Management</a></li>
                                            <li><a class="dropdown-item protected" href="admin_payment_management.php"><i class="fas fa-money-bill-wave me-2"></i>Payment Management</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Communication Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="commDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-comments"></i><span>Chat</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="commDropdown">
                                            <li><a class="dropdown-item protected" href="admin_chat_dashboard.php"><i class="fas fa-robot me-2"></i>AI Chat Dashboard</a></li>
                                            <li><a class="dropdown-item protected" href="admin_chat_reply.php"><i class="fas fa-reply me-2"></i>Reply User Messages</a></li>
                                            <li><a class="dropdown-item protected" href="admin_reviews.php"><i class="fas fa-comments me-2"></i>Manage Reviews</a></li>
                                        </ul>
                                    </li>
                                    
                                    <!-- Services Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-tools"></i><span>Services</span>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
                                            <li><a class="dropdown-item protected" href="admin_test_drive.php"><i class="fas fa-calendar-check me-2"></i>Test Drive Requests</a></li>
                                            <li><a class="dropdown-item protected" href="admin_notifications.php"><i class="fas fa-bell me-2"></i>Notifications</a></li>
                                        </ul>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            
                        <?php else: ?>
                            <!-- Guest Menu - Show all features -->
                            <!-- User Features Dropdown -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle protected" href="#" id="guestUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user"></i><span>User Features</span>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="guestUserDropdown">
                                    <li><a class="dropdown-item protected" href="cancel_order.php"><i class="fas fa-shopping-cart me-2"></i>My Orders</a></li>
                                    <li><a class="dropdown-item protected" href="my_favorites.php"><i class="fas fa-heart me-2"></i>My Favorites</a></li>
                                    <li><a class="dropdown-item protected" href="history.php"><i class="fas fa-history me-2"></i>My History</a></li>
                                </ul>
                            </li>


                            <!-- Car Reviews (Public Access) -->
                            <li class="nav-item">
                                <a class="nav-link protected" href="reviews.php" title="Car Reviews">
                                    <i class="fas fa-star"></i><span>Reviews</span>
                                </a>
                            </li>

                            <!-- Auth Links -->
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal" title="Login">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <span class="ms-1">Login</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-toggle="tab" data-bs-target="#register-tab-pane" title="Register">
                                    <i class="fas fa-user-plus"></i>
                                    <span class="ms-1">Join</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- User Status Display (Right Side) -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; padding: 12px 16px !important; border-radius: 8px !important; color: white !important; font-weight: 600 !important; min-width: 200px !important;">
                                    <i class="fas fa-user-circle me-2" style="font-size: 18px !important;"></i>
                                    <?php echo htmlspecialchars(substr($_SESSION['username'] ?? 'User', 0, 15)); ?>
                                    <span class="badge bg-light text-dark ms-2" style="font-size: 11px !important; padding: 4px 8px !important;"><?php echo $_SESSION['role'] === 'admin' ? 'Admin' : ($_SESSION['user_type'] === 'seller' ? 'Seller' : 'Buyer'); ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" style="display: none;">
                                    <!-- This will be replaced by JavaScript -->
                                </ul>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </nav>
</header>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
// Modern Header JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Header scroll effect
    const header = document.querySelector('.header');
    let lastScrollTop = 0;
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        
        lastScrollTop = scrollTop;
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            // Skip if href is just "#" (invalid selector)
            if (href === '#') {
                return;
            }
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
           // Optimized user dropdown functionality
           let dropdownOpen = false;
           
           function toggleUserDropdown() {
               const userDropdown = document.getElementById('userDropdown');
               if (!userDropdown) return;
               
               const buttonRect = userDropdown.getBoundingClientRect();
               
               if (dropdownOpen) {
                   // Close dropdown menu
                   const existingDropdown = document.getElementById('userDropdownMenu');
                   if (existingDropdown) {
                       existingDropdown.remove();
                   }
                   dropdownOpen = false;
                   userDropdown.setAttribute('aria-expanded', 'false');
               } else {
                   // Open dropdown menu
                   const existingDropdown = document.getElementById('userDropdownMenu');
                   if (existingDropdown) {
                       existingDropdown.remove();
                   }
                   
                   const newDropdown = document.createElement('div');
                   newDropdown.id = 'userDropdownMenu';
                   newDropdown.style.cssText = `
                       position: fixed !important;
                       top: ${buttonRect.bottom + 5}px !important;
                       right: ${window.innerWidth - buttonRect.right}px !important;
                       z-index: 99999 !important;
                       background: white !important;
                       border: 1px solid #ddd !important;
                       border-radius: 12px !important;
                       min-width: 280px !important;
                       max-width: 320px !important;
                       padding: 0 !important;
                       box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
                       display: block !important;
                       overflow: hidden !important;
                   `;
                   newDropdown.innerHTML = `
                       <div style="padding: 16px 20px; border-bottom: 1px solid #eee; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                           <div style="font-weight: 700; font-size: 16px; margin-bottom: 4px;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                           <div style="font-size: 13px; opacity: 0.9;"><?php echo $_SESSION['role'] === 'admin' ? 'Administrator' : ($_SESSION['user_type'] === 'seller' ? 'Vehicle Seller' : 'Vehicle Buyer'); ?></div>
                       </div>
                       <a href="my_profile.php" style="display: flex; align-items: center; padding: 14px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; transition: all 0.2s ease;">
                           <i class="fas fa-user" style="margin-right: 12px; width: 18px; color: #667eea;"></i>
                           <span style="font-weight: 500;">My Profile</span>
                       </a>
                       <a href="account_settings.php" style="display: flex; align-items: center; padding: 14px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; transition: all 0.2s ease;">
                           <i class="fas fa-cog" style="margin-right: 12px; width: 18px; color: #667eea;"></i>
                           <span style="font-weight: 500;">Account Settings</span>
                       </a>
                       <?php if ($_SESSION['user_type'] === 'seller'): ?>
                       <a href="seller_vehicle_management.php" style="display: flex; align-items: center; padding: 14px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; transition: all 0.2s ease;">
                           <i class="fas fa-car" style="margin-right: 12px; width: 18px; color: #667eea;"></i>
                           <span style="font-weight: 500;">Vehicle Management</span>
                       </a>
                       <a href="seller_purchase_offers.php" style="display: flex; align-items: center; padding: 14px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; transition: all 0.2s ease;">
                           <i class="fas fa-shopping-cart" style="margin-right: 12px; width: 18px; color: #667eea;"></i>
                           <span style="font-weight: 500;">Purchase Offers</span>
                       </a>
                       <?php elseif ($_SESSION['user_type'] === 'buyer' && $_SESSION['role'] !== 'admin'): ?>
                       <a href="cancel_order.php" class="protected" style="display: flex; align-items: center; padding: 14px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; transition: all 0.2s ease;">
                           <i class="fas fa-shopping-cart" style="margin-right: 12px; width: 18px; color: #667eea;"></i>
                           <span style="font-weight: 500;">My Orders</span>
                       </a>
                       <?php endif; ?>
                       <a href="logout.php" style="display: flex; align-items: center; padding: 14px 20px; color: #dc3545; text-decoration: none; transition: all 0.2s ease;">
                           <i class="fas fa-sign-out-alt" style="margin-right: 12px; width: 18px;"></i>
                           <span style="font-weight: 500;">Logout</span>
                       </a>
                   `;
                   
                   document.body.appendChild(newDropdown);
                   dropdownOpen = true;
                   userDropdown.setAttribute('aria-expanded', 'true');
                   
                   // Add hover effects
                   newDropdown.querySelectorAll('a').forEach(link => {
                       link.addEventListener('mouseenter', function() {
                           this.style.backgroundColor = '#f8f9fa';
                           this.style.transform = 'translateX(4px)';
                       });
                       link.addEventListener('mouseleave', function() {
                           this.style.backgroundColor = 'transparent';
                           this.style.transform = 'translateX(0)';
                       });
                   });
               }
           }
           
           // Bind click event - simplified version
           const userDropdown = document.getElementById('userDropdown');
           if (userDropdown) {
               userDropdown.addEventListener('click', function(e) {
                   e.preventDefault();
                   e.stopPropagation();
                   toggleUserDropdown();
               });
           }
           
           // Close dropdown when clicking outside
           document.addEventListener('click', function(e) {
               const userDropdown = document.getElementById('userDropdown');
               const dynamicDropdown = document.getElementById('userDropdownMenu');
               
               if (userDropdown && dropdownOpen && 
                   !userDropdown.contains(e.target) && 
                   (!dynamicDropdown || !dynamicDropdown.contains(e.target))) {
                   if (dynamicDropdown) {
                       dynamicDropdown.remove();
                   }
                   dropdownOpen = false;
                   userDropdown.setAttribute('aria-expanded', 'false');
               }
           });
           
           
           // Bootstrap dropdowns are already initialized above, no need for custom handling
           
           // Debug dropdown functionality
           console.log('Dropdown elements found:', document.querySelectorAll('.dropdown-toggle').length);
           console.log('Bootstrap version:', bootstrap.Dropdown.VERSION);
});

// Show alert function
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Protected link redirect function
function redirectToLogin(page) {
    // Check if we're on index.php and login modal exists
    const loginModal = document.getElementById('loginModal');
    
    if (loginModal) {
        // We're on index.php, show the modal directly
        console.log('Showing login modal directly');
        
        // Set return URL for after login
        const redirectInput = document.getElementById('redirect_after_login');
        if (redirectInput && page) {
            redirectInput.value = page;
        }
        
        // Show the modal
        const modal = new bootstrap.Modal(loginModal);
        modal.show();
        
        // Show a brief message
        showAlert('Please login to access this feature', 'warning');
        
    } else {
        // We're not on index.php, redirect to index.php with login modal
        console.log('Redirecting to index.php with login modal');
        
        const toast = document.createElement('div');
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div>
                    <strong>Please login or register first</strong>
                    <div class="small">Redirecting to login...</div>
                </div>
            </div>
        `;
        toast.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: white; 
            padding: 1rem; border-radius: 12px; font-size: 0.9rem; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.2); min-width: 300px;
            transform: translateX(100%); opacity: 0; transition: all 0.4s ease;
            border: 1px solid rgba(255,255,255,0.2);
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        }, 100);
        
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
                // Redirect to index.php to show login modal
                const returnUrl = encodeURIComponent(page);
                window.location.href = 'index.php?show_login=1&return=' + returnUrl;
            }, 400);
        }, 1500);
    }
}

// Add click events to protected links after page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!isset($_SESSION['user_id'])): ?>
        // Only add protected link handlers for unauthenticated users
        document.querySelectorAll('.nav-link.protected, .dropdown-item.protected, a.protected').forEach(link => {
            link.addEventListener('click', function(e) {
                // User not logged in - redirect to login modal
                e.preventDefault();
                const href = this.getAttribute('href');
                redirectToLogin(href);
            });
        });
    <?php endif; ?>
    
    <?php if (!isset($_SESSION['user_id'])): ?>
        
        // If login link is clicked but modal doesn't exist, redirect to login.php
        const loginLink = document.querySelector('a[data-bs-target="#loginModal"]');
        if (loginLink) {
            loginLink.addEventListener('click', function(e) {
                // Check if modal exists, redirect if not
                const modal = document.getElementById('loginModal');
                if (!modal) {
                    e.preventDefault();
                    window.location.href = 'login.php';
                }
            });
        }
    <?php endif; ?>
});
</script>

</body>
</html>