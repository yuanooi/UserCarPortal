<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle user decision (accept/reject offer)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_decision'])) {
    $acquisition_id = intval($_POST['acquisition_id']);
    $decision = $_POST['decision']; // 'accept' or 'reject'
    $user_response = trim($_POST['user_response']);
    
    // Verify user owns this application
    $verify_stmt = $conn->prepare("SELECT id FROM vehicle_acquisitions WHERE id = ? AND user_id = ? AND status = 'offered'");
    $verify_stmt->bind_param("ii", $acquisition_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        if ($decision === 'accept') {
            // Accept offer
            $stmt = $conn->prepare("UPDATE vehicle_acquisitions SET status = 'accepted', user_response = ?, user_responded_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $user_response, $acquisition_id);
            
            if ($stmt->execute()) {
                // Create notification
                $notification_stmt = $conn->prepare("INSERT INTO acquisition_notifications (acquisition_id, user_id, notification_type, title, message) VALUES (?, ?, 'offer_accepted', 'Offer Accepted', 'You have accepted our acquisition offer, we will arrange payment matters.')");
                $notification_stmt->bind_param("ii", $acquisition_id, $user_id);
                $notification_stmt->execute();
                $notification_stmt->close();
                
                $success_message = "You have accepted the acquisition offer, we will contact you soon to arrange payment matters.";
            } else {
                $error_message = "Operation failed, please try again";
            }
        } else {
            // Reject offer
            $stmt = $conn->prepare("UPDATE vehicle_acquisitions SET status = 'rejected', user_response = ?, user_responded_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $user_response, $acquisition_id);
            
            if ($stmt->execute()) {
                // Create notification
                $notification_stmt = $conn->prepare("INSERT INTO acquisition_notifications (acquisition_id, user_id, notification_type, title, message) VALUES (?, ?, 'offer_rejected', 'Offer Rejected', 'You have rejected our acquisition offer.')");
                $notification_stmt->bind_param("ii", $acquisition_id, $user_id);
                $notification_stmt->execute();
                $notification_stmt->close();
                
                $success_message = "You have rejected the acquisition offer.";
            } else {
                $error_message = "Operation failed, please try again";
            }
        }
        $stmt->close();
    } else {
        $error_message = "Invalid application or you don't have permission to operate this application";
    }
    $verify_stmt->close();
}

// Get all acquisition applications for the user
$acquisitions_stmt = $conn->prepare("
    SELECT va.*, 
           COUNT(ai.id) as image_count
    FROM vehicle_acquisitions va 
    LEFT JOIN acquisition_images ai ON va.id = ai.acquisition_id
    WHERE va.user_id = ? 
    GROUP BY va.id
    ORDER BY va.created_at DESC
");
$acquisitions_stmt->bind_param("i", $user_id);
$acquisitions_stmt->execute();
$acquisitions_result = $acquisitions_stmt->get_result();

// Get user notifications
$notifications_stmt = $conn->prepare("
    SELECT an.*, va.brand, va.model 
    FROM acquisition_notifications an
    JOIN vehicle_acquisitions va ON an.acquisition_id = va.id
    WHERE an.user_id = ? AND an.is_read = 0
    ORDER BY an.created_at DESC
    LIMIT 10
");
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

include 'header.php';
?>

<style>
.receipt-content {
    background: white;
    border: 2px solid #28a745;
    border-radius: 8px;
    padding: 20px;
    margin-top: 10px;
}

.receipt-header h4 {
    color: #28a745;
    font-weight: bold;
}

.receipt-details .d-flex {
    border-bottom: 1px solid #e9ecef;
}

.receipt-details .d-flex:last-child {
    border-bottom: 2px solid #28a745;
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
}

.receipt-footer {
    border-top: 2px solid #28a745;
    padding-top: 15px;
}

.receipt-footer p {
    color: #28a745;
    font-weight: bold;
}

@media print {
    .receipt-content {
        border: none;
        padding: 0;
        margin: 0;
    }
    
    .alert {
        border: none !important;
        background: white !important;
    }
    
    .btn {
        display: none !important;
    }
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-car me-2"></i>My Vehicle Acquisition Applications
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
                    
                    <!-- Notification Area -->
                    <?php if ($notifications_result->num_rows > 0): ?>
                        <div class="alert alert-info">
                            <h6 class="mb-2">
                                <i class="fas fa-bell me-2"></i>Latest Notifications
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
                    
                    <!-- Application List -->
                    <?php if ($acquisitions_result->num_rows > 0): ?>
                        <div class="row g-3">
                            <?php while ($acquisition = $acquisitions_result->fetch_assoc()): ?>
                                <div class="col-lg-6">
                                    <div class="card border-<?php 
                                        switch($acquisition['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'offered': echo 'info'; break;
                                            case 'accepted': echo 'success'; break;
                                            case 'rejected': echo 'danger'; break;
                                            case 'completed': echo 'dark'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <div class="card-header bg-<?php 
                                            switch($acquisition['status']) {
                                                case 'pending': echo 'warning'; break;
                                                case 'offered': echo 'info'; break;
                                                case 'accepted': echo 'success'; break;
                                                case 'rejected': echo 'danger'; break;
                                                case 'completed': echo 'dark'; break;
                                                default: echo 'secondary';
                                            }
                                        ?> text-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-car me-2"></i>
                                                    <?php echo htmlspecialchars($acquisition['brand'] . ' ' . $acquisition['model']); ?>
                                                </h6>
                                                <span class="badge bg-light text-dark">
                                                    <?php 
                                                    switch($acquisition['status']) {
                                                        case 'pending': echo 'Pending Review'; break;
                                                        case 'offered': echo 'Offer Made'; break;
                                                        case 'accepted': echo 'Accepted'; break;
                                                        case 'rejected': echo 'Rejected'; break;
                                                        case 'completed': echo 'Completed'; break;
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
                                                    <strong><?php echo $acquisition['year']; ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Mileage:</small><br>
                                                    <span><?php echo $acquisition['mileage'] ? number_format($acquisition['mileage']) . ' km' : 'Not filled'; ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Your Expected Price:</small><br>
                                                    <span class="text-primary fw-bold">
                                                        <?php echo $acquisition['user_expected_price'] ? 'RM ' . number_format($acquisition['user_expected_price'], 2) : 'Not filled'; ?>
                                                    </span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Our Offer:</small><br>
                                                    <span class="text-success fw-bold">
                                                        <?php echo $acquisition['admin_offer_price'] ? 'RM ' . number_format($acquisition['admin_offer_price'], 2) : 'Pending Offer'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($acquisition['admin_notes']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted">Admin Notes:</small><br>
                                                    <small class="text-info"><?php echo htmlspecialchars($acquisition['admin_notes']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($acquisition['user_response']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted">Your Response:</small><br>
                                                    <small><?php echo htmlspecialchars($acquisition['user_response']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- If offer is made, show accept/reject buttons -->
                                            <?php if ($acquisition['status'] === 'offered'): ?>
                                                <div class="mb-3">
                                                    <form method="POST" id="decisionForm_<?php echo $acquisition['id']; ?>">
                                                        <input type="hidden" name="acquisition_id" value="<?php echo $acquisition['id']; ?>">
                                                        <input type="hidden" name="user_decision" value="1">
                                                        
                                                        <div class="mb-3">
                                                            <label for="user_response_<?php echo $acquisition['id']; ?>" class="form-label fw-semibold">Your Response</label>
                                                            <textarea class="form-control" id="user_response_<?php echo $acquisition['id']; ?>" 
                                                                      name="user_response" rows="2" placeholder="Please tell us your decision..."></textarea>
                                                        </div>
                                                        
                                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                            <button type="submit" class="btn btn-outline-danger me-md-2" name="decision" value="reject"
                                                                    onclick="return confirm('Are you sure you want to reject this offer?')">
                                                                <i class="fas fa-times me-1"></i>Reject Offer
                                                            </button>
                                                            <button type="submit" class="btn btn-success" name="decision" value="accept">
                                                                <i class="fas fa-check me-1"></i>Accept Offer
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- If accepted, show payment information -->
                                            <?php if ($acquisition['status'] === 'accepted'): ?>
                                                <div class="alert alert-success">
                                                    <h6 class="mb-2">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Payment Information
                                                    </h6>
                                                    <p class="mb-2">
                                                        <strong>Acquisition Price:</strong> RM <?php echo number_format($acquisition['admin_offer_price'], 2); ?>
                                                    </p>
                                                    <p class="mb-2">
                                                        <strong>Payment Method:</strong> Bank Transfer / Cash
                                                    </p>
                                                    <p class="mb-0">
                                                        <strong>Contact:</strong> +60 12-345 6789
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- If completed, show payment receipt -->
                                            <?php if ($acquisition['status'] === 'completed'): ?>
                                                <div class="alert alert-success">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-receipt me-2"></i>Payment Receipt
                                                        </h6>
                                                        <button class="btn btn-outline-primary btn-sm" onclick="printReceipt(<?php echo $acquisition['id']; ?>)">
                                                            <i class="fas fa-print me-1"></i>Print Receipt
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Receipt Content -->
                                                    <div id="receipt_<?php echo $acquisition['id']; ?>" class="receipt-content">
                                                        <div class="receipt-header text-center mb-3">
                                                            <h4 class="mb-1">VEHICLE ACQUISITION RECEIPT</h4>
                                                            <p class="mb-0 text-muted">User Car Portal</p>
                                                            <hr>
                                                        </div>
                                                        
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <strong>Receipt No:</strong><br>
                                                                <span class="text-muted">#<?php echo str_pad($acquisition['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                            </div>
                                                            <div class="col-6">
                                                                <strong>Date:</strong><br>
                                                                <span class="text-muted"><?php echo date('Y-m-d H:i', strtotime($acquisition['payment_completed_at'])); ?></span>
                                                            </div>
                                                            <div class="col-6">
                                                                <strong>Vehicle:</strong><br>
                                                                <span class="text-muted"><?php echo htmlspecialchars($acquisition['brand'] . ' ' . $acquisition['model'] . ' (' . $acquisition['year'] . ')'); ?></span>
                                                            </div>
                                                            <div class="col-6">
                                                                <strong>Mileage:</strong><br>
                                                                <span class="text-muted"><?php echo $acquisition['mileage'] ? number_format($acquisition['mileage']) . ' km' : 'N/A'; ?></span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="receipt-details mb-3">
                                                            <div class="d-flex justify-content-between border-bottom py-2">
                                                                <span><strong>Acquisition Price:</strong></span>
                                                                <span><strong>RM <?php echo number_format($acquisition['admin_offer_price'], 2); ?></strong></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between border-bottom py-2">
                                                                <span>Payment Method:</span>
                                                                <span><?php echo htmlspecialchars($acquisition['payment_method']); ?></span>
                                                            </div>
                                                            <?php if ($acquisition['payment_reference']): ?>
                                                            <div class="d-flex justify-content-between border-bottom py-2">
                                                                <span>Reference No:</span>
                                                                <span><?php echo htmlspecialchars($acquisition['payment_reference']); ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <div class="d-flex justify-content-between py-2">
                                                                <span><strong>Total Paid:</strong></span>
                                                                <span><strong>RM <?php echo number_format($acquisition['admin_offer_price'], 2); ?></strong></span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="receipt-footer text-center">
                                                            <hr>
                                                            <p class="mb-1"><strong>Thank you for your business!</strong></p>
                                                            <small class="text-muted">This receipt serves as proof of payment for vehicle acquisition.</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="text-muted small">
                                                <i class="fas fa-calendar me-1"></i>
                                                Application Time: <?php echo date('Y-m-d H:i', strtotime($acquisition['created_at'])); ?>
                                                
                                                <?php if ($acquisition['admin_reviewed_at']): ?>
                                                    <br><i class="fas fa-gavel me-1"></i>
                                                    Review Time: <?php echo date('Y-m-d H:i', strtotime($acquisition['admin_reviewed_at'])); ?>
                                                <?php endif; ?>
                                                
                                                <?php if ($acquisition['user_responded_at']): ?>
                                                    <br><i class="fas fa-reply me-1"></i>
                                                    Response Time: <?php echo date('Y-m-d H:i', strtotime($acquisition['user_responded_at'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-car text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Acquisition Applications</h5>
                            <p class="text-muted">You haven't submitted any vehicle acquisition applications yet</p>
                            <a href="seller_vehicle_management.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Submit New Application
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
function printReceipt(acquisitionId) {
    // Get the receipt content
    const receiptContent = document.getElementById('receipt_' + acquisitionId);
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Write the receipt content to the new window
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Receipt</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                .receipt-header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .receipt-header h4 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: bold;
                }
                .receipt-header p {
                    margin: 5px 0;
                    color: #666;
                }
                .receipt-details {
                    margin: 20px 0;
                }
                .receipt-details .d-flex {
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }
                .receipt-details .d-flex:last-child {
                    border-bottom: none;
                    font-weight: bold;
                    font-size: 16px;
                }
                .receipt-footer {
                    text-align: center;
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 2px solid #333;
                }
                .receipt-footer p {
                    margin: 5px 0;
                    font-weight: bold;
                }
                .receipt-footer small {
                    color: #666;
                }
                .row {
                    display: flex;
                    margin-bottom: 10px;
                }
                .col-6 {
                    flex: 1;
                    padding: 0 10px;
                }
                .border-bottom {
                    border-bottom: 1px solid #eee;
                }
                .py-2 {
                    padding: 8px 0;
                }
                .mb-3 {
                    margin-bottom: 20px;
                }
                .text-muted {
                    color: #666;
                }
                .text-center {
                    text-align: center;
                }
                hr {
                    border: none;
                    border-top: 1px solid #333;
                    margin: 10px 0;
                }
                @media print {
                    body {
                        margin: 0;
                    }
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            ${receiptContent.innerHTML}
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Receipt</button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Focus the window and trigger print dialog
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
    }, 500);
}
</script>
