<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1");
    exit;
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$message = "";
$success = false;

// Handle unlist action
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    $reason = trim($_POST['reason'] ?? '');

    if ($car_id <= 0 || empty($reason)) {
        $message = "⚠️ Invalid car ID or reason missing";
    } else {
        // Fetch car details to get user_id
        $car_stmt = $conn->prepare("SELECT user_id, brand, model, year FROM cars WHERE id = ?");
        $car_stmt->bind_param("i", $car_id);
        $car_stmt->execute();
        $car_result = $car_stmt->get_result();
        
        if ($car_row = $car_result->fetch_assoc()) {
            $user_id = $car_row['user_id'];
            $car_name = $car_row['brand'] . ' ' . $car_row['model'] . ' (' . $car_row['year'] . ')';
            
            // Update car status to rejected (unlisted)
            $update_stmt = $conn->prepare("UPDATE cars SET status = 'rejected' WHERE id = ?");
            $update_stmt->bind_param("i", $car_id);
            if ($update_stmt->execute()) {
                // Insert notification
                $notification_message = "Your car ($car_name) has been unlisted by admin. Reason: $reason";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, car_id, message, status) VALUES (?, ?, ?, 'unread')");
                $notif_stmt->bind_param("iis", $user_id, $car_id, $notification_message);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $message = "✅ Car unlisted successfully. Notification sent to user.";
                $success = true;
            } else {
                $message = "❌ Failed to unlist car";
            }
            $update_stmt->close();
        } else {
            $message = "❌ Car not found";
        }
        $car_stmt->close();
    }
}

// Fetch available cars for unlisting with images
$cars_query = "SELECT c.*, u.username, ci.image as main_image 
                FROM cars c 
                JOIN users u ON c.user_id = u.id 
                LEFT JOIN car_images ci ON c.id = ci.car_id AND ci.is_main = 1
                WHERE c.status IN ('available', 'reserved') 
                ORDER BY c.created_at DESC";
$cars_result = $conn->query($cars_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Unlist Vehicle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .car-card {
            transition: transform 0.2s;
        }
        .car-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-eye-slash me-2"></i>Unlist Vehicle</h2>
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <?php if ($cars_result->num_rows > 0): ?>
                        <?php while ($car = $cars_result->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card car-card h-100">
                                    <div class="card-img-top" style="height: 200px; overflow: hidden;">
                                        <?php if (!empty($car['main_image'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($car['main_image']); ?>" 
                                                 class="w-100 h-100" style="object-fit: cover;" 
                                                 alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light" style="display: none;">
                                                <i class="fas fa-car fa-3x text-muted"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-car fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                            <span class="badge bg-<?php echo $car['status'] === 'available' ? 'success' : 'warning'; ?> status-badge">
                                                <?php echo ucfirst($car['status']); ?>
                                            </span>
                                        </h5>
                                        <p class="card-text">
                                            <strong>Year:</strong> <?php echo htmlspecialchars($car['year']); ?><br>
                                            <strong>Price:</strong> RM <?php echo number_format($car['price']); ?><br>
                                            <strong>Mileage:</strong> <?php echo number_format($car['mileage']); ?> km<br>
                                            <strong>Seller:</strong> <?php echo htmlspecialchars($car['username']); ?>
                                        </p>
                                        <button class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#unlistModal<?php echo $car['id']; ?>">
                                            <i class="fas fa-eye-slash me-1"></i>Unlist
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Unlist Modal -->
                            <div class="modal fade" id="unlistModal<?php echo $car['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-eye-slash me-2"></i>Unlist Vehicle
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Warning:</strong> This will remove the vehicle from public view.
                                                </div>
                                                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ')'); ?></p>
                                                <p><strong>Seller:</strong> <?php echo htmlspecialchars($car['username']); ?></p>
                                                
                                                <div class="mb-3">
                                                    <label for="reason<?php echo $car['id']; ?>" class="form-label">Reason for unlisting:</label>
                                                    <textarea class="form-control" id="reason<?php echo $car['id']; ?>" name="reason" rows="3" required 
                                                              placeholder="Please provide a reason for unlisting this vehicle..."></textarea>
                                                </div>
                                                
                                                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-eye-slash me-1"></i>Unlist Vehicle
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                No vehicles available for unlisting.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

<?php
$conn->close();
?>
