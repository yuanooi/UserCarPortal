<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch dashboard statistics
$stats = [];

// Fetch pending vehicles count
$sql = "SELECT COUNT(*) as pending_count FROM cars WHERE status = 'pending'";
$result = $conn->query($sql);
$stats['pending_count'] = $result ? $result->fetch_assoc()['pending_count'] : 0;

// Fetch total users count
$sql = "SELECT COUNT(*) as total_users FROM users";
$result = $conn->query($sql);
$stats['total_users'] = $result ? $result->fetch_assoc()['total_users'] : 0;

// Fetch active listings count (cars with status 'available')
$sql = "SELECT COUNT(*) as active_listings FROM cars WHERE status = 'available'";
$result = $conn->query($sql);
$stats['active_listings'] = $result ? $result->fetch_assoc()['active_listings'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Dashboard - Manage Vehicle Portal">
    <title>Admin Dashboard - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e2e8f0 100%);
            color: #1e293b;
            line-height: 1.6;
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dashboard-title {
            color: var(--danger-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }

        .admin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .btn-admin {
            border-radius: var(--border-radius);
            font-weight: 600;
            padding: 1rem 1.5rem;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 1rem;
        }

        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-primary-admin {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
        }

        .btn-primary-admin:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: white;
        }

        .btn-success-admin {
            background: linear-gradient(135deg, var(--accent-color), #059669);
            color: white;
        }

        .btn-success-admin:hover {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
        }

        .btn-warning-admin {
            background: linear-gradient(135deg, var(--warning-color), #f59e0b);
            color: white;
        }

        .btn-warning-admin:hover {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            color: white;
        }

        .btn-danger-admin {
            background: linear-gradient(135deg, var(--danger-color), #ef4444);
            color: white;
        }

        .btn-danger-admin:hover {
            background: linear-gradient(135deg, #b91c1c, #dc2626);
            color: white;
        }


        .alert {
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
            padding: 1rem 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .admin-card {
                padding: 1.5rem;
            }
            
            .btn-admin {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<?php include 'header.php'; ?>

<!-- Dashboard -->
<div class="container mt-4">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="dashboard-title">
                    <i class="fas fa-shield-alt me-3"></i>Admin Dashboard
                </h1>
                <p class="dashboard-subtitle mb-0">
                    Manage vehicle approvals and portal administration
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="badge bg-danger fs-6 p-2">
                    <i class="fas fa-crown me-1"></i>Administrator Access
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['pending_count']; ?></div>
            <div class="stat-label">Pending Approvals</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['active_listings']; ?></div>
            <div class="stat-label">Active Listings</div>
        </div>
    </div>

    <!-- Status Alert -->
        <?php if ($stats['pending_count'] > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                <div>
                    <h5 class="alert-heading mb-1">Action Required!</h5>
                    <p class="mb-0">There are currently <strong><?php echo $stats['pending_count']; ?></strong> vehicles pending approval</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php else: ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-3 fs-4"></i>
                <div>
                    <h5 class="alert-heading mb-1">All Clear!</h5>
                    <p class="mb-0">No vehicles pending approval</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

    <!-- Admin Actions -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="admin-card">
                <h4 class="mb-3">
                    <i class="fas fa-car me-2"></i>Vehicle Management
                </h4>
                <a href="admin_approved.php" class="btn btn-primary-admin btn-admin">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <span>Approve Vehicles</span>
                    </div>
                    <span class="badge bg-light text-dark"><?php echo $stats['pending_count']; ?> Pending</span>
                </a>
                <a href="admin_edit_car.php" class="btn btn-success-admin btn-admin">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-edit me-2"></i>
                        <span>Edit Vehicles</span>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="admin_unlist_car.php" class="btn btn-warning-admin btn-admin">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-eye-slash me-2"></i>
                        <span>Unlist Vehicles</span>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="admin-card">
                <h4 class="mb-3">
                    <i class="fas fa-chart-line me-2"></i>Portal Management
                </h4>
                <a href="admin_orders.php" class="btn btn-primary-admin btn-admin">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shopping-cart me-2"></i>
                        <span>Manage Orders</span>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="admin_reviews.php" class="btn btn-success-admin btn-admin">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-star me-2"></i>
                        <span>Manage Reviews</span>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="admin_test_drive.php" class="btn btn-warning-admin btn-admin">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-car-side me-2"></i>
                        <span>Test Drive Bookings</span>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <a href="admin_vehicle_issues.php" class="btn btn-danger-admin btn-admin">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Vehicle Issues</span>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="admin-card">
                <h4 class="mb-3">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h4>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="index.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-home me-2"></i>View Public Portal
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="admin_dashboard.php" class="btn btn-outline-success w-100 py-3">
                            <i class="fas fa-sync me-2"></i>Refresh Dashboard
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="logout.php" class="btn btn-outline-danger w-100 py-3">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.admin-card, .stat-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Animate stats numbers
    const statsNumbers = document.querySelectorAll('.stat-number');
    statsNumbers.forEach(number => {
        const target = parseInt(number.textContent);
        if (target > 0) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    number.textContent = target;
                    clearInterval(timer);
                } else {
                    number.textContent = Math.floor(current);
                }
            }, 30);
        }
    });
    
    // Add click effects to admin buttons
    const adminButtons = document.querySelectorAll('.btn-admin');
    adminButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Auto-refresh dashboard every 30 seconds
    setInterval(() => {
        const refreshBtn = document.querySelector('a[href="admin_dashboard.php"]');
        if (refreshBtn && !refreshBtn.classList.contains('btn-outline-success')) {
            // Only refresh if not already refreshing
            window.location.reload();
        }
    }, 30000);
});
</script>
</body>
</html>

