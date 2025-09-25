<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user' || $_SESSION['user_type'] !== 'seller') {
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

// Handle seller response to purchase offers
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['offer_response'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $offer_id = isset($_POST['offer_id']) ? intval($_POST['offer_id']) : 0;
    $response = $_POST['response'] ?? '';
    $seller_notes = trim($_POST['seller_notes'] ?? '');

    if ($offer_id <= 0 || !in_array($response, ['accept', 'reject'])) {
        $message = "⚠️ Invalid response";
    } else {
        // Fetch offer details
        $stmt = $conn->prepare("SELECT apo.*, c.brand, c.model, c.year FROM admin_purchase_offers apo JOIN cars c ON apo.car_id = c.id WHERE apo.id = ? AND apo.user_id = ? AND apo.status = 'pending'");
        $stmt->bind_param("ii", $offer_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $message = "❌ Offer not found or already responded";
        } else {
            $offer = $result->fetch_assoc();
            $car_name = $offer['brand'] . ' ' . $offer['model'] . ' (' . $offer['year'] . ')';
            
            // Update offer status
            $update_stmt = $conn->prepare("UPDATE admin_purchase_offers SET status = ?, responded_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("si", $response, $offer_id);
            
            if ($update_stmt->execute()) {
                if ($response === 'accept') {
                    // Update car status to sold
                    $car_stmt = $conn->prepare("UPDATE cars SET status = 'sold' WHERE id = ?");
                    $car_stmt->bind_param("i", $offer['car_id']);
                    $car_stmt->execute();
                    $car_stmt->close();
                    
                    $success = true;
                    $message = "✅ You have accepted the purchase offer! The vehicle will be marked as sold.";
                } else {
                    $success = true;
                    $message = "✅ You have rejected the purchase offer.";
                }
                
                // Create notification for admin
                $notification_stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, notification_type, title, message, related_car_id, created_at) VALUES (?, 'offer_response', 'Seller Response', ?, ?, NOW())");
                $notification_message = "Seller has " . $response . "ed your purchase offer for " . $car_name;
                $notification_stmt->bind_param("isi", $offer['admin_id'], $notification_message, $offer['car_id']);
                $notification_stmt->execute();
                $notification_stmt->close();
            } else {
                $message = "❌ Failed to process your response";
            }
            $update_stmt->close();
        }
        $stmt->close();
    }
}

// Fetch seller's purchase offers
$offers_stmt = $conn->prepare("
    SELECT apo.*, c.brand, c.model, c.year, c.price as current_price, c.description,
           u.username as admin_name
    FROM admin_purchase_offers apo 
    JOIN cars c ON apo.car_id = c.id 
    JOIN users u ON apo.admin_id = u.id
    WHERE apo.user_id = ? 
    ORDER BY apo.created_at DESC
");
$offers_stmt->bind_param("i", $_SESSION['user_id']);
$offers_stmt->execute();
$offers_result = $offers_stmt->get_result();
$purchase_offers = $offers_result->fetch_all(MYSQLI_ASSOC);
$offers_stmt->close();

// Fetch seller notifications
$notifications_stmt = $conn->prepare("
    SELECT * FROM seller_notifications 
    WHERE user_id = ? AND notification_type IN ('purchase_offer', 'offer_cancelled')
    ORDER BY created_at DESC 
    LIMIT 10
");
$notifications_stmt->bind_param("i", $_SESSION['user_id']);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();
$notifications_stmt->close();

include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-handshake me-2"></i>Purchase Offers
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

                    <!-- Notifications -->
                    <?php if ($notifications_result->num_rows > 0): ?>
                        <div class="alert alert-info mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-bell me-2"></i>Recent Notifications
                            </h6>
                            <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($notification['message']); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('m-d H:i', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-shopping-cart me-2"></i>Platform Purchase Offers
                            </h5>
                            <p class="text-muted">Review and respond to platform's purchase offers for your vehicles</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="badge bg-info fs-6">
                                <i class="fas fa-handshake me-1"></i>
                                <?php echo count($purchase_offers); ?> Total Offers
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($purchase_offers)): ?>
                        <div class="row g-3">
                            <?php foreach ($purchase_offers as $offer): ?>
                                <div class="col-lg-6">
                                    <div class="card border-<?php 
                                        switch($offer['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'accepted': echo 'success'; break;
                                            case 'rejected': echo 'danger'; break;
                                            case 'cancelled': echo 'secondary'; break;
                                            default: echo 'light';
                                        }
                                    ?> h-100">
                                        <div class="card-header bg-<?php 
                                            switch($offer['status']) {
                                                case 'pending': echo 'warning'; break;
                                                case 'accepted': echo 'success'; break;
                                                case 'rejected': echo 'danger'; break;
                                                case 'cancelled': echo 'secondary'; break;
                                                default: echo 'light';
                                            }
                                        ?> text-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-car me-2"></i>
                                                    <?php echo htmlspecialchars($offer['brand'] . ' ' . $offer['model']); ?>
                                                </h6>
                                                <span class="badge bg-light text-<?php 
                                                    switch($offer['status']) {
                                                        case 'pending': echo 'warning'; break;
                                                        case 'accepted': echo 'success'; break;
                                                        case 'rejected': echo 'danger'; break;
                                                        case 'cancelled': echo 'secondary'; break;
                                                        default: echo 'dark';
                                                    }
                                                ?>">
                                                    <?php 
                                                    switch($offer['status']) {
                                                        case 'pending': echo 'Pending Response'; break;
                                                        case 'accepted': echo 'Accepted'; break;
                                                        case 'rejected': echo 'Rejected'; break;
                                                        case 'cancelled': echo 'Cancelled'; break;
                                                        default: echo 'Unknown';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Year:</small><br>
                                                    <strong><?php echo $offer['year']; ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Current Price:</small><br>
                                                    <span class="text-primary fw-bold">RM <?php echo number_format($offer['current_price'], 2); ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Offer Price:</small><br>
                                                    <span class="text-success fw-bold fs-5">RM <?php echo number_format($offer['purchase_price'], 2); ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Difference:</small><br>
                                                    <?php 
                                                    $difference = $offer['purchase_price'] - $offer['current_price'];
                                                    $diff_class = $difference >= 0 ? 'text-success' : 'text-danger';
                                                    $diff_icon = $difference >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                                    ?>
                                                    <span class="<?php echo $diff_class; ?> fw-bold">
                                                        <i class="fas <?php echo $diff_icon; ?> me-1"></i>
                                                        RM <?php echo number_format(abs($difference), 2); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($offer['admin_notes']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted">Admin Notes:</small><br>
                                                    <small class="text-info"><?php echo htmlspecialchars($offer['admin_notes']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($offer['status'] === 'pending'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                                    <input type="hidden" name="offer_response" value="1">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Your Response Notes (Optional)</label>
                                                        <textarea name="seller_notes" class="form-control" placeholder="Add any notes for the admin..." rows="2"></textarea>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                        <button type="submit" name="response" value="reject" class="btn btn-outline-danger me-md-2"
                                                                onclick="return confirm('Are you sure you want to reject this purchase offer?')">
                                                            <i class="fas fa-times me-1"></i>Reject Offer
                                                        </button>
                                                        <button type="submit" name="response" value="accept" class="btn btn-success"
                                                                onclick="return confirm('Are you sure you want to accept this purchase offer? The vehicle will be marked as sold.')">
                                                            <i class="fas fa-check me-1"></i>Accept Offer
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php elseif ($offer['status'] === 'accepted'): ?>
                                                <div class="alert alert-success">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-check-circle me-2"></i>Offer Accepted
                                                    </h6>
                                                    <p class="mb-2">
                                                        <strong>Purchase Price:</strong> RM <?php echo number_format($offer['purchase_price'], 2); ?>
                                                    </p>
                                                    <p class="mb-2">
                                                        <strong>Contact:</strong> +60 12-345 6789
                                                    </p>
                                                    <p class="mb-0">
                                                        <strong>Next Step:</strong> Please contact us to arrange vehicle pickup and payment
                                                    </p>
                                                </div>
                                            <?php elseif ($offer['status'] === 'rejected'): ?>
                                                <div class="alert alert-danger">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-times-circle me-2"></i>Offer Rejected
                                                    </h6>
                                                    <p class="mb-0">You have rejected this purchase offer. The vehicle remains available for sale.</p>
                                                </div>
                                            <?php elseif ($offer['status'] === 'cancelled'): ?>
                                                <div class="alert alert-secondary">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-ban me-2"></i>Offer Cancelled
                                                    </h6>
                                                    <p class="mb-0">The admin has cancelled this purchase offer.</p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="text-muted small mt-3">
                                                <i class="fas fa-calendar me-1"></i>
                                                Offer Sent: <?php echo date('Y-m-d H:i', strtotime($offer['created_at'])); ?>
                                                
                                                <?php if ($offer['responded_at']): ?>
                                                    <br><i class="fas fa-reply me-1"></i>
                                                    Responded: <?php echo date('Y-m-d H:i', strtotime($offer['responded_at'])); ?>
                                                <?php endif; ?>
                                                
                                                <?php if ($offer['cancelled_at']): ?>
                                                    <br><i class="fas fa-ban me-1"></i>
                                                    Cancelled: <?php echo date('Y-m-d H:i', strtotime($offer['cancelled_at'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-handshake text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Purchase Offers</h5>
                            <p class="text-muted">You haven't received any purchase offers from the platform yet</p>
                            <a href="user_dashboard.php" class="btn btn-primary">
                                <i class="fas fa-car me-1"></i>View Your Vehicles
                            </a>
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
            const response = e.submitter.value;
            
            if (response === 'accept') {
                if (!confirm('Are you sure you want to accept this purchase offer?\n\nAfter acceptance:\n- The vehicle will be marked as sold\n- You need to contact the platform to arrange vehicle handover\n- The platform will pay you according to the offer')) {
                    e.preventDefault();
                    return false;
                }
            } else if (response === 'reject') {
                if (!confirm('Are you sure you want to reject this purchase offer?\n\nAfter rejection:\n- The vehicle will continue to be sold on the platform\n- You can wait for other buyers\n- The platform may make new offers')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
