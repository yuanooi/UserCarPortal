<?php
session_start();
include 'includes/db.php';

// Check admin permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Handle payment completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_payment'])) {
    $acquisition_id = intval($_POST['acquisition_id']);
    $payment_method = trim($_POST['payment_method']);
    $payment_reference = trim($_POST['payment_reference']);
    
    // Update acquisition application status
    $stmt = $conn->prepare("UPDATE vehicle_acquisitions SET payment_method = ?, payment_reference = ?, payment_status = 'completed', payment_completed_at = NOW(), status = 'completed' WHERE id = ? AND status = 'accepted'");
    $stmt->bind_param("ssi", $payment_method, $payment_reference, $acquisition_id);
    
    if ($stmt->execute()) {
        // Get user ID
        $user_stmt = $conn->prepare("SELECT user_id FROM vehicle_acquisitions WHERE id = ?");
        $user_stmt->bind_param("i", $acquisition_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_id = $user_data['user_id'];
        
        // Create notification
        $notification_stmt = $conn->prepare("INSERT INTO acquisition_notifications (acquisition_id, user_id, notification_type, title, message) VALUES (?, ?, 'payment_completed', 'Payment Completed', 'Your vehicle acquisition payment has been completed successfully!')");
        $notification_stmt->bind_param("ii", $acquisition_id, $user_id);
        $notification_stmt->execute();
        $notification_stmt->close();
        
        $success_message = "Payment completed successfully!";
    } else {
        $error_message = "Payment processing failed, please try again";
    }
    $stmt->close();
}

// Get accepted but unpaid acquisition applications
$accepted_stmt = $conn->prepare("
    SELECT va.*, u.username, u.email, u.phone 
    FROM vehicle_acquisitions va 
    JOIN users u ON va.user_id = u.id 
    WHERE va.status = 'accepted' AND va.payment_status = 'pending'
    ORDER BY va.user_responded_at ASC
");
$accepted_stmt->execute();
$accepted_result = $accepted_stmt->get_result();

// Get all acquisition application statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'offered' THEN 1 ELSE 0 END) as offered,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN payment_status = 'completed' THEN admin_offer_price ELSE 0 END) as total_revenue
    FROM vehicle_acquisitions
");
$stats = $stats_stmt->fetch_assoc();

include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-money-bill-wave me-2"></i>Payment Management
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $stats['total']; ?></h5>
                                    <p class="card-text small">Total Applications</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $stats['pending']; ?></h5>
                                    <p class="card-text small">Pending Review</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $stats['offered']; ?></h5>
                                    <p class="card-text small">Offered</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $stats['accepted']; ?></h5>
                                    <p class="card-text small">Accepted</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $stats['rejected']; ?></h5>
                                    <p class="card-text small">Rejected</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-dark text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo $stats['completed']; ?></h5>
                                    <p class="card-text small">Completed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Revenue Display -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">
                                        <i class="fas fa-dollar-sign me-2"></i>
                                        Total Revenue: RM <?php echo number_format($stats['total_revenue'], 2); ?>
                                    </h3>
                                    <p class="mb-0">From completed acquisition transactions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Payment Applications List -->
                    <div class="row">
                        <div class="col-12">
                            <h5 class="text-success mb-3">
                                <i class="fas fa-clock me-2"></i>Pending Payment Applications
                            </h5>
                            
                            <?php if ($accepted_result->num_rows > 0): ?>
                                <div class="row g-3">
                                    <?php while ($acquisition = $accepted_result->fetch_assoc()): ?>
                                        <div class="col-lg-6">
                                            <div class="card border-success">
                                                <div class="card-header bg-success text-white">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-car me-2"></i>
                                                            <?php echo htmlspecialchars($acquisition['brand'] . ' ' . $acquisition['model']); ?>
                                                        </h6>
                                                        <span class="badge bg-light text-dark">
                                                            <?php echo $acquisition['year']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <small class="text-muted">Owner:</small><br>
                                                            <strong><?php echo htmlspecialchars($acquisition['username']); ?></strong>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Contact:</small><br>
                                                            <small><?php echo htmlspecialchars($acquisition['phone']); ?></small>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Acquisition Price:</small><br>
                                                            <span class="text-success fw-bold fs-5">
                                                                RM <?php echo number_format($acquisition['admin_offer_price'], 2); ?>
                                                            </span>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Accepted Time:</small><br>
                                                            <span><?php echo date('Y-m-d H:i', strtotime($acquisition['user_responded_at'])); ?></span>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($acquisition['user_response']): ?>
                                                        <div class="mb-3">
                                                            <small class="text-muted">User Response:</small><br>
                                                            <small><?php echo htmlspecialchars($acquisition['user_response']); ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Payment Completion Form -->
                                                    <form method="POST" class="mb-3">
                                                        <input type="hidden" name="acquisition_id" value="<?php echo $acquisition['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="payment_method_<?php echo $acquisition['id']; ?>" class="form-label fw-semibold">Payment Method</label>
                                                            <select class="form-select" id="payment_method_<?php echo $acquisition['id']; ?>" name="payment_method" required>
                                                                <option value="">Select Payment Method</option>
                                                                <option value="Bank Transfer">Bank Transfer</option>
                                                                <option value="Cash">Cash</option>
                                                                <option value="Cheque">Cheque</option>
                                                                <option value="Online Transfer">Online Transfer</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="payment_reference_<?php echo $acquisition['id']; ?>" class="form-label fw-semibold">Payment Reference</label>
                                                            <input type="text" class="form-control" id="payment_reference_<?php echo $acquisition['id']; ?>" 
                                                                   name="payment_reference" placeholder="Enter payment reference number or notes...">
                                                        </div>
                                                        
                                                        <div class="d-grid">
                                                            <button type="submit" class="btn btn-success" name="complete_payment"
                                                                    onclick="return confirm('Confirm payment completion? This action cannot be undone.')">
                                                                <i class="fas fa-check me-1"></i>Complete Payment
                                                            </button>
                                                        </div>
                                                    </form>
                                                    
                                                    <div class="text-muted small">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        Application Time: <?php echo date('Y-m-d H:i', strtotime($acquisition['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                    <h5 class="text-muted mt-3">No Pending Payment Applications</h5>
                                    <p class="text-muted">All accepted applications have been paid</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
