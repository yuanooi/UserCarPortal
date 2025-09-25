<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user' || $_SESSION['user_type'] !== 'seller') {
    header("Location: index.php?show_login=1");
    exit();
}

$user_id = $_SESSION['user_id'];

// Query seller's vehicles
$sql = "SELECT * FROM cars WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Seller dashboard query prepare error: " . $conn->error);
    $message = "❌ Database query error, please contact the administrator";
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cars = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get vehicle main image
function getMainImage($conn, $car_id) {
    $defaultImage = "Uploads/car.jpg";
    $stmt = $conn->prepare("SELECT image FROM car_images WHERE car_id = ? AND is_main = TRUE LIMIT 1");
    if (!$stmt) {
        error_log("Seller dashboard: Image query prepare error for car_id $car_id: " . $conn->error);
        return $defaultImage;
    }
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $imagePath = $row && !empty($row['image']) && file_exists("Uploads/" . trim($row['image'])) 
        ? "Uploads/" . trim($row['image']) 
        : $defaultImage;
    $stmt->close();
    return $imagePath;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User Dashboard - Manage Your Vehicles">
    <title>User Dashboard - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
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
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        .car-card { 
            transition: var(--transition);
            border: none;
            border-radius: var(--border-radius);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        
        .car-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .card-img-top { 
            height: 220px; 
            object-fit: cover;
            transition: var(--transition);
        }

        .car-card:hover .card-img-top {
            transform: scale(1.05);
        }
        
        .status-badge { 
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.5rem 0.75rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .no-cars { 
            text-align: center; 
            padding: 4rem 2rem; 
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(10px);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: var(--border-radius);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            transition: var(--transition);
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stats-label {
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
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .car-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-light">

<?php include 'header.php'; ?>

<div class="container mt-4">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="dashboard-title">
                    <i class="fas fa-tachometer-alt me-3"></i>User Dashboard
                </h1>
                <p class="dashboard-subtitle mb-0">
                    Manage your vehicle listings and track their status
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="seller_vehicle_management.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Upload New Vehicle
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo count($cars); ?></div>
                <div class="stats-label">Total Vehicles</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo count(array_filter($cars, function($car) { return $car['status'] === 'available'; })); ?></div>
                <div class="stats-label">Approved</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo count(array_filter($cars, function($car) { return $car['status'] === 'pending'; })); ?></div>
                <div class="stats-label">Pending</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo count(array_filter($cars, function($car) { return $car['status'] === 'rejected'; })); ?></div>
                <div class="stats-label">Rejected</div>
            </div>
        </div>
    </div>

    <!-- Vehicle Grid -->
    <?php if (!empty($cars)): ?>
        <div class="row">
            <?php foreach ($cars as $row): ?>
                <?php $imagePath = getMainImage($conn, $row['id']); ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="car-card card">
                        <div class="position-relative">
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" class="card-img-top" alt="Vehicle Image">
                            <div class="position-absolute top-0 end-0 m-3">
                                <span class="badge bg-<?php echo $row['status'] === 'available' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning'); ?> status-badge">
                                    <i class="fas fa-<?php echo $row['status'] === 'available' ? 'check-circle' : ($row['status'] === 'rejected' ? 'times-circle' : 'clock'); ?> me-1"></i>
                                    <?php echo ucfirst(htmlspecialchars($row['status'] === 'available' ? 'approved' : $row['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-car me-2"></i><?php echo htmlspecialchars($row['brand'] . " " . $row['model']); ?>
                            </h5>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>Year
                                    </small>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['year']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">
                                        <i class="fas fa-tag me-1"></i>Price
                                    </small>
                                    <div class="fw-semibold text-primary">
                                        <?php if ($row['status'] === 'available' && $row['price'] > 0): ?>
                                            RM <?php echo number_format($row['price'], 0); ?>
                                            <small class="text-success d-block">
                                                <i class="fas fa-check-circle me-1"></i>Admin Approved
                                            </small>
                                        <?php elseif ($row['status'] === 'pending'): ?>
                                            <span class="text-warning">Pending Review</span>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-clock me-1"></i>Waiting for Admin
                                            </small>
                                        <?php elseif ($row['status'] === 'rejected'): ?>
                                            <span class="text-danger">Rejected</span>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-times-circle me-1"></i>Not Approved
                                            </small>
                                        <?php else: ?>
                                            RM <?php echo number_format($row['price'], 0); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="car_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-2"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-cars">
            <i class="fas fa-car fa-4x text-muted mb-4"></i>
            <h3 class="mb-3">No Vehicles Yet</h3>
            <p class="text-muted mb-4">You haven't uploaded any vehicles yet. Start by adding your first vehicle!</p>
            <a href="seller_vehicle_management.php" class="btn btn-primary btn-lg">
                <i class="fas fa-plus me-2"></i>Upload Your First Vehicle
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debug dropdown functionality
    console.log('User Dashboard loaded');
    console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? bootstrap.Dropdown.VERSION : 'Bootstrap not loaded');
    console.log('Dropdown elements found:', document.querySelectorAll('.dropdown-toggle').length);
    
    // Force initialize dropdowns if needed
    setTimeout(function() {
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        console.log('Found dropdowns:', dropdowns.length);
        dropdowns.forEach((dropdown, index) => {
            console.log(`Dropdown ${index + 1}:`, dropdown.id || dropdown.className);
            try {
                const dropdownInstance = new bootstrap.Dropdown(dropdown);
                console.log(`Dropdown ${index + 1} initialized successfully`);
            } catch (error) {
                console.error(`Error initializing dropdown ${index + 1}:`, error);
            }
        });
    }, 1000);
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.car-card, .stats-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Animate stats numbers
    const statsNumbers = document.querySelectorAll('.stats-number');
    statsNumbers.forEach(number => {
        const target = parseInt(number.textContent);
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
    });
});
</script>
</body>
</html>

