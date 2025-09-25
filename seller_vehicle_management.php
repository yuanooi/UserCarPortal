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

// Get admin contact info
$admin_phone = '';
$admin_email = '';
$admin_stmt = $conn->prepare("SELECT phone, email FROM users WHERE role = 'admin' LIMIT 1");
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
if ($admin_row = $admin_result->fetch_assoc()) {
    $admin_phone = $admin_row['phone'];
    $admin_email = $admin_row['email'];
}
$admin_stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($action === 'sell_car') {
        // Handle car sale to platform
        $brand = trim($_POST['acquisition_brand'] ?? '');
        $model = trim($_POST['acquisition_model'] ?? '');
        $year = intval($_POST['acquisition_year'] ?? 0);
        $mileage = intval($_POST['acquisition_mileage'] ?? 0);
        $color = trim($_POST['acquisition_color'] ?? '');
        $transmission = trim($_POST['acquisition_transmission'] ?? '');
        $body_type = trim($_POST['acquisition_body_type'] ?? '');
        $condition_description = trim($_POST['acquisition_condition_description'] ?? '');
        $user_expected_price = floatval($_POST['acquisition_user_expected_price'] ?? 0);

        // Validate input
        if (empty($brand) || empty($model) || empty($year)) {
            $message = "⚠️ Brand, model, and year are required fields";
        } else {
            // Insert acquisition request
            $stmt = $conn->prepare("INSERT INTO vehicle_acquisitions (user_id, brand, model, year, mileage, color, transmission, body_type, condition_description, user_expected_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            if ($stmt) {
                $stmt->bind_param("issiissssd", $user_id, $brand, $model, $year, $mileage, $color, $transmission, $body_type, $condition_description, $user_expected_price);
                
                if ($stmt->execute()) {
                    $acquisition_id = $conn->insert_id;
                    
                    // Handle image upload
                    if (isset($_FILES['acquisition_images']) && !empty($_FILES['acquisition_images']['name'][0])) {
                        $upload_dir = "uploads/acquisitions/";
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['acquisition_images']['name']); $i++) {
                            if ($_FILES['acquisition_images']['error'][$i] == 0) {
                                $file_extension = pathinfo($_FILES['acquisition_images']['name'][$i], PATHINFO_EXTENSION);
                                $new_filename = $acquisition_id . "_" . time() . "_" . $i . "." . $file_extension;
                                $upload_path = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($_FILES['acquisition_images']['tmp_name'][$i], $upload_path)) {
                                    // Insert image record
                                    $image_stmt = $conn->prepare("INSERT INTO acquisition_images (acquisition_id, image_path, image_type, is_main) VALUES (?, ?, 'exterior', ?)");
                                    $is_main = ($i == 0) ? 1 : 0;
                                    $image_stmt->bind_param("isi", $acquisition_id, $upload_path, $is_main);
                                    $image_stmt->execute();
                                    $image_stmt->close();
                                }
                            }
                        }
                    }
                    
                    // Create notification for user
                    $notification_stmt = $conn->prepare("INSERT INTO acquisition_notifications (acquisition_id, user_id, notification_type, title, message) VALUES (?, ?, 'status_update', 'Acquisition Application Submitted', 'Your vehicle acquisition application has been successfully submitted and is waiting for admin review.')");
                    $notification_stmt->bind_param("ii", $acquisition_id, $user_id);
                    $notification_stmt->execute();
                    $notification_stmt->close();
                    
                    // Create admin notification
                    $car_name = $brand . ' ' . $model . ' (' . $year . ')';
                    $admin_notification_stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, user_id, notification_type, title, message, related_car_id, status, created_at) VALUES (6, ?, 'acquisition', 'New Vehicle Acquisition Request', 'A seller has submitted a vehicle acquisition request: ' . ?, ?, 'unread', NOW())");
                    $admin_notification_stmt->bind_param("isi", $user_id, $car_name, $acquisition_id);
                    $admin_notification_stmt->execute();
                    $admin_notification_stmt->close();
                    
                    $success = true;
                    $message = "✅ Vehicle sale request submitted successfully! We will contact you within 24 hours.";
                } else {
                    $message = "Failed to submit sale request, please try again";
                }
                $stmt->close();
            } else {
                $message = "Database error, please try again";
            }
        }
    }
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-car me-2"></i>Vehicle Management
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

                    <!-- Sell to Platform Section -->
                    <div class="row">
                        <div class="col-lg-8">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-handshake me-2"></i>Sell Vehicle to Platform
                            </h5>
                            <p class="text-muted mb-4">
                                Sell your vehicle directly to our platform. We will evaluate and make you an offer.
                            </p>
                                    
                                    <form method="POST" enctype="multipart/form-data" id="sellForm">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="action" value="sell_car">
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="acquisition_brand" class="form-label fw-semibold">Brand <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="acquisition_brand" name="acquisition_brand" required>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="acquisition_model" class="form-label fw-semibold">Model <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="acquisition_model" name="acquisition_model" required>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="acquisition_year" class="form-label fw-semibold">Year <span class="text-danger">*</span></label>
                                                <select class="form-select" id="acquisition_year" name="acquisition_year" required>
                                                    <option value="">Select Year</option>
                                                    <?php for ($y = date('Y'); $y >= 1990; $y--): ?>
                                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="acquisition_mileage" class="form-label fw-semibold">Mileage (km)</label>
                                                <input type="number" class="form-control" id="acquisition_mileage" name="acquisition_mileage" min="0">
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="acquisition_color" class="form-label fw-semibold">Color</label>
                                                <input type="text" class="form-control" id="acquisition_color" name="acquisition_color">
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="acquisition_transmission" class="form-label fw-semibold">Transmission</label>
                                                <select class="form-select" id="acquisition_transmission" name="acquisition_transmission">
                                                    <option value="">Select Transmission</option>
                                                    <option value="Manual">Manual</option>
                                                    <option value="Automatic">Automatic</option>
                                                    <option value="CVT">CVT</option>
                                                    <option value="Semi-Automatic">Semi-Automatic</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="acquisition_body_type" class="form-label fw-semibold">Body Type</label>
                                                <select class="form-select" id="acquisition_body_type" name="acquisition_body_type">
                                                    <option value="">Select Body Type</option>
                                                    <option value="Sedan">Sedan</option>
                                                    <option value="SUV">SUV</option>
                                                    <option value="Hatchback">Hatchback</option>
                                                    <option value="Coupe">Coupe</option>
                                                    <option value="Convertible">Convertible</option>
                                                    <option value="Wagon">Wagon</option>
                                                    <option value="Pickup">Pickup</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="acquisition_user_expected_price" class="form-label fw-semibold">Expected Price (RM)</label>
                                                <input type="number" class="form-control" id="acquisition_user_expected_price" name="acquisition_user_expected_price" min="0" step="0.01">
                                            </div>
                                            
                                            <div class="col-12">
                                                <label for="acquisition_condition_description" class="form-label fw-semibold">Vehicle Condition Description</label>
                                                <textarea class="form-control" id="acquisition_condition_description" name="acquisition_condition_description" rows="4" 
                                                          placeholder="Please describe the vehicle condition in detail, including:&#10;- Exterior condition&#10;- Interior condition&#10;- Mechanical condition&#10;- Accident history&#10;- Maintenance records&#10;- Other important information"></textarea>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label for="acquisition_images" class="form-label fw-semibold">Upload Vehicle Photos</label>
                                                <input type="file" class="form-control" id="acquisition_images" name="acquisition_images[]" multiple accept="image/*">
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Upload multiple photos including: exterior, interior, engine, dashboard, etc.
                                                </div>
                                            </div>
                                            
                                            <div class="col-12 mt-4">
                                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                    <button type="button" class="btn btn-outline-secondary me-md-2" onclick="resetSellForm()">
                                                        <i class="fas fa-undo me-1"></i>Reset
                                                    </button>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-handshake me-1"></i>Submit Sale Request
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="col-lg-4">
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6 class="mb-0">
                                                <i class="fas fa-lightbulb me-2"></i>Sale Info
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-unstyled mb-0">
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    We will contact you within 24 hours
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    Provide accurate vehicle information
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    Upload clear vehicle photos
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    Honestly describe vehicle condition
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    Price evaluation based on market
                                                </li>
                                                <li class="mb-0">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    You can cancel request anytime
                                                </li>
                                            </ul>
                                        </div>
                                        
                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-phone me-2"></i>Contact Admin
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="mb-2">
                                                    <i class="fas fa-phone me-2"></i>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($admin_phone ?: 'N/A'); ?>
                                                </p>
                                                <p class="mb-2">
                                                    <i class="fas fa-envelope me-2"></i>
                                                    <strong>Email:</strong> <?php echo htmlspecialchars($admin_email ?: 'N/A'); ?>
                                                </p>
                                                <p class="mb-2">
                                                    <i class="fas fa-building me-2"></i>
                                                    <strong>Office:</strong> 123 Jalan Tun Razak, KL
                                                </p>
                                                <p class="mb-0">
                                                    <i class="fas fa-clock me-2"></i>
                                                    <strong>Hours:</strong> Mon-Fri 9:00-18:00
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

<script>
function resetSellForm() {
    if (confirm('Are you sure you want to reset the sell form? All filled information will be cleared.')) {
        document.getElementById('sellForm').reset();
    }
}

// Form validation
document.getElementById('sellForm').addEventListener('submit', function(e) {
    const brand = document.getElementById('acquisition_brand').value.trim();
    const model = document.getElementById('acquisition_model').value.trim();
    const year = document.getElementById('acquisition_year').value;
    
    if (!brand || !model || !year) {
        e.preventDefault();
        alert('Please fill in all required fields (Brand, Model, Year)');
        return false;
    }
    
    // Show submitting state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
    submitBtn.disabled = true;
});
</script>

<?php include 'footer.php'; ?>
