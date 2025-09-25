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

// Handle approve/reject actions for car sales
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['car_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $admin_price = floatval($_POST['admin_price'] ?? 0);

    if ($car_id <= 0 || !in_array($action, ['approve', 'reject'])) {
        $message = "⚠️ Invalid action";
    } else {
        if ($action === 'approve' && $admin_price <= 0) {
            $message = "⚠️ Please enter a valid price for approval";
        } else {
            // Fetch car details to get user_id
            $stmt = $conn->prepare("SELECT user_id, brand, model FROM cars WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("i", $car_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $message = "❌ Car not found or not pending";
            } else {
                $car = $result->fetch_assoc();
                $user_id = $car['user_id'];
                $car_name = $car['brand'] . ' ' . $car['model'];

                if ($action === 'approve') {
                    // Update car status and set admin price
                    $update_stmt = $conn->prepare("UPDATE cars SET status = 'available', price = ? WHERE id = ?");
                    $update_stmt->bind_param("di", $admin_price, $car_id);
                    
                    if ($update_stmt->execute()) {
                        // Insert notification
                        $notification_message = "Your car ($car_name) has been approved with price RM " . number_format($admin_price, 2) . ". Reason: $reason";
                        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, car_id, message, status) VALUES (?, ?, ?, 'unread')");
                        $notif_stmt->bind_param("iis", $user_id, $car_id, $notification_message);
                        $notif_stmt->execute();
                        $notif_stmt->close();

                        $success = true;
                        $message = "✅ Car approved with price RM " . number_format($admin_price, 2) . "! Notification sent to user.";
                    } else {
                        $message = "❌ Failed to approve car";
                        error_log("Car approval error: " . $update_stmt->error);
                    }
                } else {
                    // Update car status to rejected
                    $update_stmt = $conn->prepare("UPDATE cars SET status = 'rejected' WHERE id = ?");
                    $update_stmt->bind_param("i", $car_id);
                    
                    if ($update_stmt->execute()) {
                        // Insert notification
                        $notification_message = "Your car ($car_name) has been rejected. Reason: $reason";
                        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, car_id, message, status) VALUES (?, ?, ?, 'unread')");
                        $notif_stmt->bind_param("iis", $user_id, $car_id, $notification_message);
                        $notif_stmt->execute();
                        $notif_stmt->close();

                        $success = true;
                        $message = "✅ Car rejected successfully! Notification sent to user.";
                    } else {
                        $message = "❌ Failed to reject car";
                        error_log("Car rejection error: " . $update_stmt->error);
                    }
                }
                $update_stmt->close();
            }
            $stmt->close();
        }
    }
}

// Handle acquisition approve/reject actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acquisition_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $acquisition_id = isset($_POST['acquisition_id']) ? intval($_POST['acquisition_id']) : 0;
    $action = $_POST['acquisition_action'] ?? '';
    $admin_offer_price = floatval($_POST['admin_offer_price'] ?? 0);
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if ($acquisition_id <= 0 || !in_array($action, ['approve', 'reject'])) {
        $message = "⚠️ Invalid action";
    } else {
        if ($action === 'approve' && $admin_offer_price <= 0) {
            $message = "⚠️ Please enter a valid offer price for approval";
        } else {
            // Fetch acquisition details
            $stmt = $conn->prepare("SELECT user_id, brand, model FROM vehicle_acquisitions WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("i", $acquisition_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $message = "❌ Acquisition not found or not pending";
            } else {
                $acquisition = $result->fetch_assoc();
                $user_id = $acquisition['user_id'];
                $vehicle_name = $acquisition['brand'] . ' ' . $acquisition['model'];

                if ($action === 'approve') {
                    // Approve acquisition with offer price
                    $update_stmt = $conn->prepare("UPDATE vehicle_acquisitions SET admin_offer_price = ?, admin_notes = ?, status = 'offered', admin_reviewed_at = NOW() WHERE id = ?");
                    $update_stmt->bind_param("dsi", $admin_offer_price, $admin_notes, $acquisition_id);
                    
                    if ($update_stmt->execute()) {
                        // Create notification
                        $notification_stmt = $conn->prepare("INSERT INTO acquisition_notifications (acquisition_id, user_id, notification_type, title, message) VALUES (?, ?, 'offer_made', 'Acquisition Application Approved', 'Congratulations! Your vehicle acquisition application has been approved with a purchase price of RM " . number_format($admin_offer_price, 2) . ". Please review the details and decide whether to accept.')");
                        $notification_stmt->bind_param("ii", $acquisition_id, $user_id);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                        
                        $success = true;
                        $message = "✅ Acquisition approved with offer price RM " . number_format($admin_offer_price, 2) . "!";
                    } else {
                        $message = "❌ Failed to approve acquisition";
                    }
                } else {
                    // Reject acquisition
                    $update_stmt = $conn->prepare("UPDATE vehicle_acquisitions SET admin_notes = ?, status = 'rejected', admin_reviewed_at = NOW() WHERE id = ?");
                    $update_stmt->bind_param("si", $admin_notes, $acquisition_id);
                    
                    if ($update_stmt->execute()) {
                        // Create notification
                        $notification_stmt = $conn->prepare("INSERT INTO acquisition_notifications (acquisition_id, user_id, notification_type, title, message) VALUES (?, ?, 'status_update', 'Acquisition Application Rejected', 'Sorry, we cannot purchase your vehicle. Reason: ' . ?)");
                        $notification_stmt->bind_param("iis", $acquisition_id, $user_id, $admin_notes);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                        
                        $success = true;
                        $message = "✅ Acquisition rejected successfully!";
                    } else {
                        $message = "❌ Failed to reject acquisition";
                    }
                }
                $update_stmt->close();
            }
            $stmt->close();
        }
    }
}

// Fetch pending cars for sale
$stmt = $conn->prepare("SELECT c.id, c.brand, c.model, c.year, c.price, c.description, u.username 
                        FROM cars c 
                        JOIN users u ON c.user_id = u.id 
                        WHERE c.status = 'pending'");
$stmt->execute();
$result = $stmt->get_result();
$pending_cars = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch pending acquisitions
$acquisition_stmt = $conn->prepare("
    SELECT va.*, u.username, u.email, u.phone 
    FROM vehicle_acquisitions va 
    JOIN users u ON va.user_id = u.id 
    WHERE va.status = 'pending' 
    ORDER BY va.created_at ASC
");
$acquisition_stmt->execute();
$acquisition_result = $acquisition_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Review Cars</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --bg-light: #f8f9fa;
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
        }

        .container {
            max-width: 900px;
            margin-top: 50px;
        }

        .car-card {
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            border-radius: var(--border-radius);
            font-weight: 600;
            background: var(--primary-color);
            border-color: var(--primary-color);
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-danger {
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-danger:hover {
            background: #c82333;
            border-color: #c82333;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: var(--border-radius);
        }

        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <h3 class="text-center mb-4"><i class="fas fa-gavel me-2"></i>Admin Review Dashboard</h3>

    <?php if ($message): ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?> text-center">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="reviewTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="cars-tab" data-bs-toggle="tab" data-bs-target="#cars" type="button" role="tab">
                <i class="fas fa-car me-2"></i>Car Sales (<?php echo count($pending_cars); ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="acquisitions-tab" data-bs-toggle="tab" data-bs-target="#acquisitions" type="button" role="tab">
                <i class="fas fa-handshake me-2"></i>Acquisitions (<?php echo $acquisition_result->num_rows; ?>)
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="reviewTabsContent">
        <!-- Car Sales Tab -->
        <div class="tab-pane fade show active" id="cars" role="tabpanel">
            <h5 class="text-primary mb-3"><i class="fas fa-car me-2"></i>Pending Car Sales</h5>

    <?php if (empty($pending_cars)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No pending car sales</h5>
                    <p class="text-muted">All car sales have been reviewed</p>
                </div>
    <?php else: ?>
                <div class="row g-3">
        <?php foreach ($pending_cars as $car): ?>
                        <div class="col-lg-6">
                            <div class="car-card card shadow border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-car me-2"></i>
                                        <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                        <span class="badge bg-dark ms-2"><?php echo htmlspecialchars($car['year']); ?></span>
                                    </h6>
                                </div>
                <div class="card-body">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Seller:</small><br>
                                            <strong><?php echo htmlspecialchars($car['username']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Price:</small><br>
                                            <span class="text-primary fw-bold">RM <?php echo number_format($car['price'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($car['description']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Description:</small><br>
                                            <small><?php echo nl2br(htmlspecialchars($car['description'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                        <input type="hidden" name="car_action" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Sale Price (RM) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" name="admin_price" min="0" step="0.01" placeholder="Enter sale price" required>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Price must be set when approving sale
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Review Reason</label>
                                            <textarea name="reason" class="form-control" placeholder="Enter review reason (optional)..."></textarea>
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger me-md-2"
                                                    onclick="return confirm('Are you sure you want to reject this vehicle sale application?')">
                                                <i class="fas fa-times me-1"></i>Reject Sale
                                            </button>
                                            <button type="submit" name="action" value="approve" class="btn btn-success"
                                                    onclick="return confirm('Are you sure you want to approve this vehicle sale application?')">
                                                <i class="fas fa-check me-1"></i>Approve Sale
                                            </button>
                                        </div>
                                    </form>
                                </div>
                </div>
            </div>
        <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Acquisitions Tab -->
        <div class="tab-pane fade" id="acquisitions" role="tabpanel">
            <h5 class="text-primary mb-3"><i class="fas fa-handshake me-2"></i>Pending Acquisitions</h5>
            
            <?php if ($acquisition_result->num_rows == 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No pending acquisitions</h5>
                    <p class="text-muted">All acquisition requests have been reviewed</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php while ($acquisition = $acquisition_result->fetch_assoc()): ?>
                        <div class="col-lg-6">
                            <div class="car-card card shadow border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-handshake me-2"></i>
                                        <?php echo htmlspecialchars($acquisition['brand'] . ' ' . $acquisition['model']); ?>
                                        <span class="badge bg-light text-dark ms-2"><?php echo $acquisition['year']; ?></span>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Applicant:</small><br>
                                            <strong><?php echo htmlspecialchars($acquisition['username']); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Contact:</small><br>
                                            <small><?php echo htmlspecialchars($acquisition['phone']); ?></small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Mileage:</small><br>
                                            <span><?php echo $acquisition['mileage'] ? number_format($acquisition['mileage']) . ' km' : 'Not specified'; ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Expected Price:</small><br>
                                            <span class="text-primary fw-bold">
                                                <?php echo $acquisition['user_expected_price'] ? 'RM ' . number_format($acquisition['user_expected_price'], 2) : 'Not specified'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($acquisition['condition_description']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Vehicle Condition:</small><br>
                                            <small><?php echo htmlspecialchars(substr($acquisition['condition_description'], 0, 100)) . (strlen($acquisition['condition_description']) > 100 ? '...' : ''); ?></small>
                                        </div>
    <?php endif; ?>

                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="acquisition_id" value="<?php echo $acquisition['id']; ?>">
                                        <input type="hidden" name="acquisition_action" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Acquisition Price (RM) <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" name="admin_offer_price" min="0" step="0.01" placeholder="Enter acquisition price" required>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Price must be filled when approving acquisition
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Review Notes</label>
                                            <textarea name="admin_notes" class="form-control" placeholder="Enter review notes (optional)..." rows="3"></textarea>
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="submit" name="acquisition_action" value="reject" class="btn btn-outline-danger me-md-2"
                                                    onclick="return confirm('Are you sure you want to reject this acquisition application?')">
                                                <i class="fas fa-times me-1"></i>Reject Acquisition
                                            </button>
                                            <button type="submit" name="acquisition_action" value="approve" class="btn btn-success"
                                                    onclick="return confirm('Are you sure you want to approve this acquisition application?')">
                                                <i class="fas fa-check me-1"></i>Approve Acquisition
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <div class="text-muted small mt-3">
                                        <i class="fas fa-calendar me-1"></i>
                                        Applied: <?php echo date('Y-m-d H:i', strtotime($acquisition['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-center mt-4">
        <a href="admin_dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </p>
</div>

<script>
// Review form validation
document.addEventListener('DOMContentLoaded', function() {
    const allForms = document.querySelectorAll('form[action=""]');
    
    allForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = e.submitter.value;
            
            // Check if it's a car sale form
            const carPriceInput = form.querySelector('input[name="admin_price"]');
            const carReasonInput = form.querySelector('textarea[name="reason"]');
            
            // Check if it's an acquisition form
            const acquisitionPriceInput = form.querySelector('input[name="admin_offer_price"]');
            const acquisitionNotesInput = form.querySelector('textarea[name="admin_notes"]');
            
            // Car sale form validation
            if (carPriceInput && carReasonInput) {
                // If approve is selected, check if price is filled
                if (action === 'approve') {
                    if (!carPriceInput.value || parseFloat(carPriceInput.value) <= 0) {
                        e.preventDefault();
                        alert('Valid sale price must be filled when approving sale');
                        carPriceInput.focus();
                        return false;
                    }
                }
            }
            
            // Acquisition form validation
            if (acquisitionPriceInput && acquisitionNotesInput) {
                // If approve is selected, check if price is filled
                if (action === 'approve') {
                    if (!acquisitionPriceInput.value || parseFloat(acquisitionPriceInput.value) <= 0) {
                        e.preventDefault();
                        alert('Valid acquisition price must be filled when approving acquisition');
                        acquisitionPriceInput.focus();
                        return false;
                    }
                }
            }
        });
    });
});
</script>
</body>
</html>
