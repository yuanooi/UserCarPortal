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

// Handle admin purchase offers
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['purchase_action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    $action = $_POST['action'] ?? '';
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if ($car_id <= 0 || !in_array($action, ['offer', 'cancel'])) {
        $message = "⚠️ Invalid action";
    } else {
        if ($action === 'offer' && $purchase_price <= 0) {
            $message = "⚠️ Please enter a valid purchase price";
        } else {
            // Fetch car details
            $stmt = $conn->prepare("SELECT user_id, brand, model, year FROM cars WHERE id = ? AND status = 'available'");
            $stmt->bind_param("i", $car_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $message = "❌ Car not found or not available";
            } else {
                $car = $result->fetch_assoc();
                $user_id = $car['user_id'];
                $car_name = $car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ')';

                if ($action === 'offer') {
                    // Create purchase offer
                    $offer_stmt = $conn->prepare("INSERT INTO admin_purchase_offers (car_id, user_id, admin_id, purchase_price, admin_notes, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                    $offer_stmt->bind_param("iiids", $car_id, $user_id, $_SESSION['user_id'], $purchase_price, $admin_notes);
                    
                    if ($offer_stmt->execute()) {
                        $offer_id = $conn->insert_id;
                        
                        // Create notification for seller
                        $notification_stmt = $conn->prepare("INSERT INTO seller_notifications (user_id, notification_type, title, message, related_car_id, created_at) VALUES (?, 'purchase_offer', 'Platform Purchase Offer', 'We would like to purchase your vehicle for RM " . number_format($purchase_price, 2) . ": ' . ?, ?, NOW())");
                        $notification_stmt->bind_param("isi", $user_id, $car_name, $car_id);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                        
                        $success = true;
                        $message = "✅ Purchase offer sent to seller successfully!";
                    } else {
                        $message = "❌ Failed to create purchase offer";
                    }
                    $offer_stmt->close();
                } else {
                    // Cancel existing offer
                    $cancel_stmt = $conn->prepare("UPDATE admin_purchase_offers SET status = 'cancelled', cancelled_at = NOW() WHERE car_id = ? AND status = 'pending'");
                    $cancel_stmt->bind_param("i", $car_id);
                    
                    if ($cancel_stmt->execute()) {
                        // Create notification for seller
                        $notification_stmt = $conn->prepare("INSERT INTO seller_notifications (user_id, notification_type, title, message, related_car_id, created_at) VALUES (?, 'offer_cancelled', 'Purchase Offer Cancelled', 'We have cancelled our purchase offer for your vehicle ' . ? . '', ?, NOW())");
                        $notification_stmt->bind_param("isi", $user_id, $car_name, $car_id);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                        
                        $success = true;
                        $message = "✅ Purchase offer cancelled successfully!";
                    } else {
                        $message = "❌ Failed to cancel purchase offer";
                    }
                    $cancel_stmt->close();
                }
            }
            $stmt->close();
        }
    }
}

// Fetch available cars for purchase
$cars_stmt = $conn->prepare("
    SELECT c.id, c.brand, c.model, c.year, c.price, c.description, c.status, u.username, u.email, u.phone,
           apo.id as offer_id, apo.purchase_price, apo.status as offer_status, apo.created_at as offer_created_at
    FROM cars c 
    JOIN users u ON c.user_id = u.id 
    LEFT JOIN admin_purchase_offers apo ON c.id = apo.car_id AND apo.status = 'pending'
    WHERE c.status = 'available' 
    ORDER BY c.created_at DESC
");
$cars_stmt->execute();
$cars_result = $cars_stmt->get_result();
$available_cars = $cars_result->fetch_all(MYSQLI_ASSOC);
$cars_stmt->close();

include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>Admin Vehicle Purchase Management
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-car me-2"></i>Available Vehicles for Purchase
                            </h5>
                            <p class="text-muted">Select vehicles to make purchase offers to sellers</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="badge bg-info fs-6">
                                <i class="fas fa-car me-1"></i>
                                <?php echo count($available_cars); ?> Available Vehicles
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($available_cars)): ?>
                        <div class="row g-3">
                            <?php foreach ($available_cars as $car): ?>
                                <div class="col-lg-6">
                                    <div class="card border-<?php echo $car['offer_id'] ? 'info' : 'light'; ?> h-100">
                                        <div class="card-header bg-<?php echo $car['offer_id'] ? 'info' : 'light'; ?> text-<?php echo $car['offer_id'] ? 'white' : 'dark'; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-car me-2"></i>
                                                    <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                                </h6>
                                                <?php if ($car['offer_id']): ?>
                                                    <span class="badge bg-light text-info">
                                                        <i class="fas fa-handshake me-1"></i>Offer Sent
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-tag me-1"></i>Available
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Year:</small><br>
                                                    <strong><?php echo $car['year']; ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Current Price:</small><br>
                                                    <span class="text-primary fw-bold">RM <?php echo number_format($car['price'], 2); ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Seller:</small><br>
                                                    <strong><?php echo htmlspecialchars($car['username']); ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Contact:</small><br>
                                                    <span><?php echo htmlspecialchars($car['phone'] ?: $car['email']); ?></span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($car['description']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted">Description:</small><br>
                                                    <small><?php echo nl2br(htmlspecialchars(substr($car['description'], 0, 100))); ?><?php echo strlen($car['description']) > 100 ? '...' : ''; ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($car['offer_id']): ?>
                                                <div class="alert alert-info mb-3">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-handshake me-2"></i>Purchase Offer Sent
                                                    </h6>
                                                    <p class="mb-2">
                                                        <strong>Offer Price:</strong> RM <?php echo number_format($car['purchase_price'], 2); ?>
                                                    </p>
                                                    <p class="mb-0">
                                                        <strong>Sent:</strong> <?php echo date('Y-m-d H:i', strtotime($car['offer_created_at'])); ?>
                                                    </p>
                                                </div>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                    <input type="hidden" name="purchase_action" value="1">
                                                    <button type="submit" name="action" value="cancel" class="btn btn-outline-danger btn-sm"
                                                            onclick="return confirm('Are you sure you want to cancel this purchase offer?')">
                                                        <i class="fas fa-times me-1"></i>Cancel Offer
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                    <input type="hidden" name="purchase_action" value="1">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Purchase Price (RM) <span class="text-danger">*</span></label>
                                                        <input type="number" class="form-control" name="purchase_price" min="0" step="0.01" placeholder="Enter purchase price" required>
                                                        <div class="form-text">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            This will be your offer to the seller
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Notes (Optional)</label>
                                                        <textarea name="admin_notes" class="form-control" placeholder="Add any notes for the seller..." rows="2"></textarea>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                        <button type="submit" name="action" value="offer" class="btn btn-success"
                                                                onclick="return confirm('Are you sure you want to send this purchase offer?')">
                                                            <i class="fas fa-handshake me-1"></i>Send Purchase Offer
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-car text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Available Vehicles</h5>
                            <p class="text-muted">There are no vehicles available for purchase at the moment</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = e.submitter.value;
            const purchasePriceInput = form.querySelector('input[name="purchase_price"]');
            
            if (action === 'offer' && purchasePriceInput) {
                if (!purchasePriceInput.value || parseFloat(purchasePriceInput.value) <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid purchase price');
                    purchasePriceInput.focus();
                    return false;
                }
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
