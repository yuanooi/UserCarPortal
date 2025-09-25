<?php
session_start();
include 'includes/db.php';

// Vehicle-based calculation functions
function getRecommendedInterestRate($car) {
    $brand = strtolower($car['brand']);
    $year = intval($car['year']);
    $currentYear = date('Y');
    $age = $currentYear - $year;
    
    // Base rate by brand
    $brandRates = [
        'toyota' => 3.2,
        'honda' => 3.3,
        'proton' => 3.8,
        'perodua' => 3.9,
        'nissan' => 3.4,
        'mazda' => 3.5,
        'hyundai' => 3.6,
        'kia' => 3.7,
        'bmw' => 4.2,
        'mercedes' => 4.3,
        'audi' => 4.1,
        'volkswagen' => 4.0
    ];
    
    $baseRate = $brandRates[$brand] ?? 3.8;
    
    // Age adjustment
    if ($age <= 2) {
        $baseRate -= 0.2; // Newer cars get better rates
    } elseif ($age >= 10) {
        $baseRate += 0.5; // Older cars get higher rates
    }
    
    return round($baseRate, 1);
}

function getBrandRiskLevel($brand) {
    $brand = strtolower($brand);
    $riskLevels = [
        'toyota' => 'success',
        'honda' => 'success', 
        'proton' => 'warning',
        'perodua' => 'warning',
        'nissan' => 'info',
        'mazda' => 'info',
        'bmw' => 'danger',
        'mercedes' => 'danger',
        'audi' => 'danger'
    ];
    
    return $riskLevels[$brand] ?? 'info';
}

function getAgeRiskLevel($year) {
    $currentYear = date('Y');
    $age = $currentYear - intval($year);
    
    if ($age <= 3) return 'success';
    if ($age <= 7) return 'info';
    if ($age <= 12) return 'warning';
    return 'danger';
}

function getBaseInsuranceRate($car) {
    $brand = strtolower($car['brand']);
    $year = intval($car['year']);
    $currentYear = date('Y');
    $age = $currentYear - $year;
    
    // Base rates by brand
    $brandRates = [
        'toyota' => 2.2,
        'honda' => 2.3,
        'proton' => 2.8,
        'perodua' => 2.9,
        'nissan' => 2.4,
        'mazda' => 2.5,
        'bmw' => 3.2,
        'mercedes' => 3.3,
        'audi' => 3.1
    ];
    
    $baseRate = $brandRates[$brand] ?? 2.5;
    
    // Age adjustment
    if ($age <= 2) {
        $baseRate += 0.3; // Newer cars cost more to insure
    } elseif ($age >= 10) {
        $baseRate -= 0.5; // Older cars cost less to insure
    }
    
    return round($baseRate, 1);
}

// Check if user is logged in, if not show prompt
$is_logged_in = isset($_SESSION['user_id']);
if (!$is_logged_in) {
    // Show login prompt page
    include 'header.php';
    ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-lock fa-3x text-warning mb-3"></i>
                        <h3 class="mb-3">Login Required</h3>
                        <p class="text-muted mb-4">You need to login to view vehicle details</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="fas fa-sign-in-alt me-2"></i>Login Now
                            </button>
                            <button class="btn btn-outline-primary" onclick="window.history.back()">
                                <i class="fas fa-arrow-left me-2"></i>Go Back
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include 'footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check car_id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ Invalid Vehicle ID");
}
$car_id = intval($_GET['id']);

// Vehicle details
$sql = "SELECT c.*, u.username, u.phone AS seller_phone 
        FROM cars c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Vehicle query preparation failed: " . $conn->error);
    die("❌ Database error");
}
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("❌ Vehicle not found");
}
$car = $result->fetch_assoc();
$stmt->close();

// Get vehicle issues
$issues_sql = "SELECT * FROM vehicle_issues WHERE car_id = ? ORDER BY created_at DESC";
$issues_stmt = $conn->prepare($issues_sql);
$issues_stmt->bind_param("i", $car_id);
$issues_stmt->execute();
$issues_result = $issues_stmt->get_result();
$vehicle_issues = [];
while ($issue = $issues_result->fetch_assoc()) {
    $vehicle_issues[] = $issue;
}
$issues_stmt->close();

// Check if car is ordered
$check_order_stmt = $conn->prepare("SELECT id FROM orders WHERE car_id = ? AND order_status = 'ordered'");
$check_order_stmt->bind_param("i", $car_id);
$check_order_stmt->execute();
$is_ordered = $check_order_stmt->get_result()->num_rows > 0;
$check_order_stmt->close();

// Check car status
if ($car['status'] === 'pending' && $role !== 'admin' && $car['user_id'] != $user_id) {
    die("❌ This vehicle is awaiting admin approval");
} elseif ($car['status'] === 'rejected' && $role !== 'admin' && $car['user_id'] != $user_id) {
    die("❌ This vehicle has been rejected");
}

// Browsing history
$check_stmt = $conn->prepare("SELECT id FROM user_history WHERE user_id = ? AND car_id = ? LIMIT 1");
$check_stmt->bind_param("ii", $user_id, $car_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
if ($check_result->num_rows > 0) {
    $update_stmt = $conn->prepare("UPDATE user_history SET viewed_at = NOW() WHERE user_id = ? AND car_id = ?");
    $update_stmt->bind_param("ii", $user_id, $car_id);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    $insert_stmt = $conn->prepare("INSERT INTO user_history (user_id, car_id, viewed_at) VALUES (?, ?, NOW())");
    $insert_stmt->bind_param("ii", $user_id, $car_id);
    $insert_stmt->execute();
    $insert_stmt->close();
}
$check_stmt->close();

// Vehicle images
$stmtImg = $conn->prepare("SELECT image FROM car_images WHERE car_id = ?");
$stmtImg->bind_param("i", $car_id);
$stmtImg->execute();
$resultImg = $stmtImg->get_result();
$images = $resultImg->fetch_all(MYSQLI_ASSOC);
$stmtImg->close();
$defaultImage = "Uploads/car.jpg";

// Favorites
$is_favorited = false;
$message = '';
if ($role === 'user') {
    $fav_stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND car_id = ?");
    $fav_stmt->bind_param("ii", $user_id, $car_id);
    $fav_stmt->execute();
    $is_favorited = $fav_stmt->get_result()->num_rows > 0;
    $fav_stmt->close();

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['favorite_action'])) {
        if ($_POST['favorite_action'] === 'add' && !$is_favorited) {
            $fav_add_stmt = $conn->prepare("INSERT INTO favorites (user_id, car_id) VALUES (?, ?)");
            $fav_add_stmt->bind_param("ii", $user_id, $car_id);
            $fav_add_stmt->execute();
            $fav_add_stmt->close();
            $message = "✅ Vehicle added to favorites";
            $is_favorited = true;
        } elseif ($_POST['favorite_action'] === 'remove' && $is_favorited) {
            $fav_remove_stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
            $fav_remove_stmt->bind_param("ii", $user_id, $car_id);
            $fav_remove_stmt->execute();
            $fav_remove_stmt->close();
            $message = "✅ Removed from favorites";
            $is_favorited = false;
        }
    }
}

// Admin contact info
$admin_phone = '';
$admin_email = '';
if ($role === 'user') {
    $admin_stmt = $conn->prepare("SELECT phone, email FROM users WHERE role = 'admin' LIMIT 1");
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_row = $admin_result->fetch_assoc()) {
        $admin_phone = $admin_row['phone'];
        $admin_email = $admin_row['email'];
    }
    $admin_stmt->close();
}

// Book Test Drive
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['book_test_drive'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $date = $_POST['date'];
    $time = $_POST['time'];

    if ($name && $email && $date && $time) {
        // Combine date and time into datetime format
        $datetime = $date . ' ' . $time . ':00';
        
        $check_datetime_stmt = $conn->prepare("SELECT id FROM test_drives WHERE car_id = ? AND test_datetime = ? AND status = 'approved'");
        $check_datetime_stmt->bind_param("is", $car_id, $datetime);
        $check_datetime_stmt->execute();
        $check_datetime_result = $check_datetime_stmt->get_result();

        if ($check_datetime_result->num_rows > 0) {
            $message = "❌ This date and time slot is already booked.";
        } else {
            $insert_drive = $conn->prepare("INSERT INTO test_drives (car_id, user_id, name, email, test_date, test_time, test_datetime, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $insert_drive->bind_param("iisssss", $car_id, $user_id, $name, $email, $date, $time, $datetime);
            if ($insert_drive->execute()) {
                $message = "✅ Test drive request sent! Waiting for admin approval.";
            } else {
                error_log("Test drive insertion error: " . $insert_drive->error);
                $message = "❌ Failed to book test drive.";
            }
            $insert_drive->close();
        }
        $check_datetime_stmt->close();
    } else {
        $message = "❌ Please fill in all fields including time.";
    }
}

// Handle Order
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['order'])) {
    if ($car['status'] !== 'available' || $is_ordered) {
        $message = "❌ This car is already ordered or reserved.";
    } else {
        $stmt = $conn->prepare("INSERT INTO orders (user_id, car_id, order_status) VALUES (?, ?, 'ordered')");
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $car_id);
            if ($stmt->execute()) {
                $stmt = $conn->prepare("UPDATE cars SET status = 'reserved' WHERE id = ?");
                $stmt->bind_param("i", $car_id);
                $stmt->execute();
                $message = "✅ Order placed successfully! The car is now reserved for you.";
                // Refresh car data and order status
                $stmt = $conn->prepare("SELECT c.*, u.username, u.phone AS seller_phone 
                                        FROM cars c 
                                        JOIN users u ON c.user_id = u.id 
                                        WHERE c.id = ?");
                $stmt->bind_param("i", $car_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $car = $result->num_rows > 0 ? $result->fetch_assoc() : null;
                $is_ordered = true; // Update local variable
            } else {
                error_log("Order insertion error: " . $stmt->error);
                $message = "❌ Failed to place order. Please try again.";
            }
            $stmt->close();
        } else {
            error_log("Order prepare failed: " . $conn->error);
            $message = "❌ Database error. Please contact admin.";
        }
    }
}

// Handle Buy Now
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['buy_now'])) {
    if ($car['status'] !== 'available' || $is_ordered) {
        $message = "❌ This car is already ordered or reserved.";
    } else {
        header("Location: checkout.php?car_id=" . $car_id);
        exit;
    }
}

// Handle Car Reviews
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_review'])) {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO car_reviews (car_id, reviewer_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $car_id, $user_id, $rating, $comment);
        if ($stmt->execute()) {
            $message = "✅ Review submitted successfully!";
        } else {
            error_log("Review insertion error: " . $stmt->error);
            $message = "❌ Failed to submit review.";
        }
        $stmt->close();
    } else {
        $message = "❌ Please select a valid rating.";
    }
}

// Fetch average rating
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM car_reviews WHERE car_id = ?");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$stmt->bind_result($avg_rating, $total_reviews);
$stmt->fetch();
$stmt->close();

// Fetch recent reviews
$stmt = $conn->prepare("SELECT r.*, u.username FROM car_reviews r JOIN users u ON r.reviewer_id = u.id WHERE r.car_id = ? ORDER BY r.created_at DESC LIMIT 5");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($car['brand'] . " " . $car['model']); ?> - Vehicle Details</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
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

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--dark-color);
        line-height: 1.6;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }

    /* Modern Cards */
    .card {
        border: none;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        transition: var(--transition);
        overflow: hidden;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        position: relative;
    }

    .card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
    }

    .card-header {
        background: var(--gradient-primary);
        color: white;
        border-bottom: none;
        padding: 1.5rem;
        font-weight: 600;
        position: relative;
        overflow: hidden;
    }

    .card-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .card-header:hover::before {
        left: 100%;
    }

    .card-body {
        padding: 2rem;
        position: relative;
    }

    /* Card Image Optimization */
    .card-img-top {
        transition: transform 0.3s ease;
        object-fit: cover;
    }

    .card:hover .card-img-top {
        transform: scale(1.05);
    }

    /* Card Badge Styling */
    .card .badge {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
        border-radius: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Card Title Styling */
    .card-title {
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 0.75rem;
        line-height: 1.3;
    }

    .card-subtitle {
        color: var(--secondary-color);
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    .card-text {
        color: var(--text-color);
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    /* Card Action Buttons */
    .card .btn {
        margin-top: auto;
        width: 100%;
    }

    /* Card Grid Optimization */
    .card-grid {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }

    /* Card Hover Effects */
    .card-hover-lift {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover-lift:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    /* Card Loading State */
    .card-loading {
        position: relative;
        overflow: hidden;
    }

    .card-loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% { left: -100%; }
        100% { left: 100%; }
    }

    /* Modern Buttons */
    .btn {
        font-weight: 500;
        border-radius: var(--border-radius);
        transition: var(--transition);
        border: none;
        padding: 0.75rem 1.5rem;
        font-size: 0.9rem;
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

    .btn-warning {
        background: var(--gradient-warning);
        color: white;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-outline-warning {
        border: 2px solid var(--warning-color);
        color: var(--warning-color);
        background: transparent;
    }

    .btn-outline-warning:hover {
        background: var(--warning-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    /* Modern Alerts */
    .alert {
        border: none;
        border-radius: var(--border-radius);
        padding: 1rem 1.5rem;
        font-weight: 500;
        box-shadow: var(--shadow-sm);
    }

    .alert-info {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
        border-left: 4px solid var(--primary-color);
    }

    .alert-warning {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
        border-left: 4px solid var(--warning-color);
    }

    .alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border-left: 4px solid var(--accent-color);
    }

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border-left: 4px solid var(--danger-color);
    }

    /* Modern Badges */
    .badge {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
        border-radius: 50px;
        font-weight: 600;
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

    /* Modern Forms */
    .form-control, .form-select {
        border: 2px solid #e2e8f0;
        border-radius: var(--border-radius);
        padding: 0.75rem 1rem;
        transition: var(--transition);
        font-size: 0.95rem;
        background: rgba(255, 255, 255, 0.9);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        background: white;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 0.5rem;
    }

    /* Modern Carousel */
    .carousel-control-prev, .carousel-control-next {
        width: 50px;
        height: 50px;
        background: rgba(0, 0, 0, 0.6);
        border-radius: 50%;
        top: 50%;
        transform: translateY(-50%);
        transition: var(--transition);
        backdrop-filter: blur(10px);
    }

    .carousel-control-prev:hover, .carousel-control-next:hover {
        background: rgba(0, 0, 0, 0.8);
        transform: translateY(-50%) scale(1.1);
    }

    .carousel-indicators [data-bs-target] {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        border: none;
        margin: 0 4px;
        transition: var(--transition);
    }

    .carousel-indicators .active {
        background: white;
        transform: scale(1.2);
    }

    /* Modern Modal */
    .modal-content {
        border: none;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-xl);
        backdrop-filter: blur(20px);
        background: rgba(255, 255, 255, 0.95);
    }

    .modal-header {
        background: var(--gradient-primary);
        color: white;
        border-bottom: none;
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .modal-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        background: rgba(248, 250, 252, 0.8);
        border-radius: 0 0 var(--border-radius-lg) var(--border-radius-lg);
    }

    .btn-close-white {
        filter: invert(1);
    }

    /* Price Display */
    .price-display {
        text-align: center;
        background: var(--gradient-primary);
        color: white;
        padding: 1.5rem;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
    }

    .price-currency {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.8);
        font-weight: 600;
        vertical-align: top;
    }

    .price-amount {
        font-size: 2.5rem;
        font-weight: 800;
        color: white;
        line-height: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Vehicle Info Cards */
    .vehicle-info .card {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: var(--transition);
    }

    .vehicle-info .card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
        border-color: rgba(30, 64, 175, 0.2);
    }

    .vehicle-info .card-body {
        padding: 1.5rem;
    }

    .vehicle-info .card-subtitle {
        font-size: 0.85rem;
        color: var(--secondary-color);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .vehicle-info .card-text {
        font-size: 1.1rem;
        color: var(--dark-color);
        font-weight: 700;
        margin-bottom: 0;
        line-height: 1.4;
    }

    /* Vehicle Info Grid */
    .vehicle-info-grid {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }

    /* Vehicle Info Card Icons */
    .vehicle-info .card-icon {
        width: 50px;
        height: 50px;
        background: var(--gradient-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
    }

    /* Vehicle Info Card Stats */
    .vehicle-info .card-stat {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .vehicle-info .card-stat:last-child {
        border-bottom: none;
    }

    .vehicle-info .card-stat-label {
        font-size: 0.9rem;
        color: var(--secondary-color);
        font-weight: 500;
    }

    .vehicle-info .card-stat-value {
        font-size: 1rem;
        color: var(--dark-color);
        font-weight: 700;
    }

    /* Vehicle Info Card Styling */
    .vehicle-info-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .vehicle-info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--gradient-primary);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .vehicle-info-card:hover::before {
        transform: scaleX(1);
    }

    .vehicle-info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        border-color: rgba(30, 64, 175, 0.2);
        background: rgba(255, 255, 255, 0.95);
    }

    .vehicle-info-card .fw-bold {
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
    }

    .vehicle-info-card small {
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Image Gallery */
    .image-gallery {
        position: relative;
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }

    .image-gallery img {
        transition: var(--transition);
    }

    .image-gallery img:hover {
        transform: scale(1.02);
    }

    /* Status Badge */
    .badge.position-absolute {
        backdrop-filter: blur(10px);
        box-shadow: var(--shadow-md);
    }

    /* Action Buttons */
    .d-flex.gap-2 .btn {
        margin: 0.25rem;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .price-amount {
            font-size: 2rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            font-size: 0.85rem;
        }
        
        .container-fluid {
            padding: 0 1rem;
        }
        
        /* Card Grid Responsive */
        .card-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .vehicle-info-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        /* Card Image Responsive */
        .card-img-top {
            height: 180px;
        }
        
        /* Card Header Responsive */
        .card-header {
            padding: 1rem;
        }
        
        .card-title {
            font-size: 1.1rem;
        }
        
        .card-subtitle {
            font-size: 0.8rem;
        }
        
        /* Vehicle Info Cards Responsive */
        .vehicle-info .card-body {
            padding: 1rem;
        }
        
        .vehicle-info .card-icon {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
        
        /* Vehicle Info Card Responsive */
        .vehicle-info-card {
            padding: 1rem;
        }
        
        .vehicle-info-card .fw-bold {
            font-size: 1rem;
        }
        
        .vehicle-info-card small {
            font-size: 0.7rem;
        }
    }

    @media (max-width: 576px) {
        .price-amount {
            font-size: 1.8rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .main-photo-container img {
            height: 300px !important;
        }
        
        /* Mobile Card Optimizations */
        .card {
            margin-bottom: 1rem;
        }
        
        .card-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .vehicle-info-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        /* Mobile Card Images */
        .card-img-top {
            height: 160px;
        }
        
        /* Mobile Card Headers */
        .card-header {
            padding: 0.75rem;
        }
        
        .card-title {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .card-subtitle {
            font-size: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .card-text {
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        /* Mobile Vehicle Info Cards */
        .vehicle-info .card-body {
            padding: 0.75rem;
        }
        
        .vehicle-info .card-icon {
            width: 35px;
            height: 35px;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .vehicle-info .card-subtitle {
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .vehicle-info .card-text {
            font-size: 1rem;
        }
        
        /* Mobile Vehicle Info Card */
        .vehicle-info-card {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .vehicle-info-card .fw-bold {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        
        .vehicle-info-card small {
            font-size: 0.65rem;
        }
        
        .vehicle-info-card:hover {
            transform: translateY(-2px);
        }
        
        /* Mobile Badge Optimization */
        .card .badge {
            font-size: 0.7rem;
            padding: 0.4rem 0.6rem;
        }
        
        /* Mobile Button Optimization */
        .card .btn {
            padding: 0.75rem;
            font-size: 0.85rem;
            margin-top: 0.75rem;
        }
        
        /* Mobile Card Hover Effects */
        .card:hover {
            transform: translateY(-1px);
        }
        
        .vehicle-info .card:hover {
            transform: translateY(-2px);
        }
        
        .thumbnail-img {
            height: 60px !important;
        }
    }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container-fluid mt-4">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">
      <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
          <i class="fas fa-info-circle me-2"></i>
          <?php echo htmlspecialchars($message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- Back Button -->
      <div class="mb-4">
        <a href="<?php echo $role === 'user' ? 'index.php' : 'admin_dashboard.php'; ?>" class="btn btn-outline-primary">
          <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
      </div>

  <!-- Main Vehicle Card -->
  <div class="card mb-4">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1 class="card-title"><?php echo htmlspecialchars($car['brand'] . " " . $car['model']); ?></h1>
          <p class="card-subtitle text-muted"><?php echo htmlspecialchars($car['year']); ?> • <?php echo htmlspecialchars($car['body_type'] ?? 'N/A'); ?></p>
        </div>
        <div class="col-md-4 text-end">
          <div class="price-display">
            <span class="price-currency">RM</span>
            <span class="price-amount"><?php echo number_format($car['price'], 0); ?></span>
          </div>
        </div>
      </div>
    </div>
    
    <div class="card-body">
      <div class="row g-4">
        <!-- Images Section -->
        <div class="col-lg-6">
          <div class="image-gallery">
            <?php if ($is_ordered): ?>
              <div class="badge bg-danger position-absolute top-0 start-0 m-3">
                <i class="fas fa-check-circle me-1"></i>Ordered
              </div>
            <?php endif; ?>
            
            <?php if (!empty($images)): ?>
              <div id="carCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                  <?php foreach ($images as $index => $img):
                    $filePath = "Uploads/" . trim($img['image']);
                    if (!file_exists($filePath)) $filePath = $defaultImage;
                  ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                      <img src="<?php echo htmlspecialchars($filePath); ?>" class="d-block w-100" alt="Vehicle Image <?php echo $index + 1; ?>" style="height: 400px; object-fit: cover;">
                    </div>
                  <?php endforeach; ?>
                </div>
                
                <!-- Carousel Controls -->
                <button class="carousel-control-prev" type="button" data-bs-target="#carCarousel" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carCarousel" data-bs-slide="next">
                  <span class="carousel-control-next-icon"></span>
                </button>
                
                <!-- Carousel Indicators -->
                <div class="carousel-indicators">
                  <?php foreach ($images as $index => $img): ?>
                    <button type="button" data-bs-target="#carCarousel" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active"' : ''; ?> aria-current="true" aria-label="Slide <?php echo $index + 1; ?>"></button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <div class="text-center p-5 bg-light rounded">
                <img src="<?php echo $defaultImage; ?>" class="img-fluid" alt="No Image Available" style="height: 400px; object-fit: cover;">
                <div class="mt-3">
                  <i class="fas fa-image fa-3x text-muted"></i>
                  <p class="mt-2 text-muted">No images available</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Exterior Photos Section -->
        <div class="col-12 mt-4">
          <div class="card">
            <div class="card-header bg-primary text-white" style="cursor: pointer;" onclick="toggleCollapse('exteriorPhotos')">
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                  <i class="fas fa-car me-2"></i>Exterior Photos
                </h5>
                <span class="badge bg-light text-primary">6 Photos</span>
                <i class="fas fa-chevron-down" id="exteriorPhotosIcon"></i>
              </div>
            </div>
            <div class="collapse" id="exteriorPhotos">
              <div class="card-body">
                <div class="row g-3">
                  <!-- Front View -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car1.jpg" class="card-img-top" alt="Front View" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-primary">Front View</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Front View</h6>
                        <p class="card-text text-muted small">Complete front view showing headlights, grille, and bumper design</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Side View -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car2.jpg" class="card-img-top" alt="Side View" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-success">Side View</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Side View</h6>
                        <p class="card-text text-muted small">Profile view showing body lines, wheels, and overall proportions</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Rear View -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car3.jpg" class="card-img-top" alt="Rear View" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-warning">Rear View</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Rear View</h6>
                        <p class="card-text text-muted small">Back view showing taillights, trunk, and rear bumper</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Wheels -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car1.jpg" class="card-img-top" alt="Wheels" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-info">Wheels</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Alloy Wheels</h6>
                        <p class="card-text text-muted small">Premium alloy wheels with detailed design and finish</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Headlights -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car2.jpg" class="card-img-top" alt="Headlights" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-danger">Headlights</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">LED Headlights</h6>
                        <p class="card-text text-muted small">Modern LED headlight system with advanced lighting technology</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Grille -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car3.jpg" class="card-img-top" alt="Grille" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-secondary">Grille</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Front Grille</h6>
                        <p class="card-text text-muted small">Distinctive front grille design with chrome accents</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Interior Photos Section -->
        <div class="col-12 mt-4">
          <div class="card">
            <div class="card-header bg-success text-white" style="cursor: pointer;" onclick="toggleCollapse('interiorPhotos')">
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                  <i class="fas fa-chair me-2"></i>Interior Photos
                </h5>
                <span class="badge bg-light text-success">6 Photos</span>
                <i class="fas fa-chevron-down" id="interiorPhotosIcon"></i>
              </div>
            </div>
            <div class="collapse" id="interiorPhotos">
              <div class="card-body">
                <div class="row g-3">
                  <!-- Dashboard -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car1.jpg" class="card-img-top" alt="Dashboard" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-primary">Dashboard</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Dashboard</h6>
                        <p class="card-text text-muted small">Modern dashboard with digital displays and premium materials</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Seats -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car2.jpg" class="card-img-top" alt="Seats" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-success">Seats</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Premium Seats</h6>
                        <p class="card-text text-muted small">Comfortable leather seats with heating and power adjustment</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Steering Wheel -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car3.jpg" class="card-img-top" alt="Steering Wheel" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-warning">Steering</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Steering Wheel</h6>
                        <p class="card-text text-muted small">Multi-function steering wheel with controls and premium finish</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Center Console -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car1.jpg" class="card-img-top" alt="Center Console" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-info">Console</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Center Console</h6>
                        <p class="card-text text-muted small">Advanced infotainment system with touchscreen and connectivity</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Door Panel -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car2.jpg" class="card-img-top" alt="Door Panel" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-danger">Door Panel</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Door Panel</h6>
                        <p class="card-text text-muted small">Premium door panels with soft-touch materials and controls</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Rear Seats -->
                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 shadow-sm card-hover-lift">
                      <div class="position-relative">
                        <img src="Uploads/car3.jpg" class="card-img-top" alt="Rear Seats" style="height: 200px; object-fit: cover;">
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-secondary">Rear Seats</span>
                        </div>
                      </div>
                      <div class="card-body p-3">
                        <h6 class="card-title">Rear Seats</h6>
                        <p class="card-text text-muted small">Spacious rear seating with comfort features and storage</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Ask About This Vehicle Section -->
        <div class="col-12 mt-4">
          <div class="card">
            <div class="card-header bg-info text-white">
              <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                  <i class="fas fa-question-circle me-2"></i>Ask About This Vehicle
                </h5>
                <span class="badge bg-light text-info">Get Help</span>
              </div>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-lg-8">
                  <p class="text-muted mb-3">
                    Have questions about this <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>? 
                    Our AI assistant can help with general inquiries, or our admin team will respond to specific questions.
                  </p>
                  
                  <?php if (isset($_SESSION['user_id'])): ?>
                    <form id="vehicleQuestionForm" method="POST" action="ai_chat_handler.php">
                      <input type="hidden" name="action" value="vehicle_question">
                      <input type="hidden" name="vehicle_id" value="<?php echo $car['id']; ?>">
                      <input type="hidden" name="vehicle_info" value="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']); ?>">
                      
                      <div class="mb-3">
                        <label for="vehicleQuestion" class="form-label fw-semibold">Your Question</label>
                        <textarea class="form-control" id="vehicleQuestion" name="user_message" rows="3" 
                                  placeholder="Ask about pricing, features, availability, test drive, financing, or any other questions about this vehicle..." required></textarea>
                      </div>
                      
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                          <i class="fas fa-paper-plane me-1"></i>Send Question
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearQuestionForm()">
                          <i class="fas fa-eraser me-1"></i>Clear
                        </button>
                      </div>
                    </form>
                  <?php else: ?>
                    <div class="alert alert-info">
                      <i class="fas fa-info-circle me-2"></i>
                      Please <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="alert-link">login</a> 
                      to ask questions about this vehicle.
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="col-lg-4">
                  <div class="bg-light p-3 rounded">
                    <h6 class="fw-semibold mb-3">
                      <i class="fas fa-lightbulb me-2"></i>Quick Questions
                    </h6>
                    <div class="d-grid gap-2">
                      <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickQuestion('What is the best price for this vehicle?')">
                        <i class="fas fa-dollar-sign me-1"></i>Best Price?
                      </button>
                      <button type="button" class="btn btn-outline-success btn-sm" onclick="setQuickQuestion('Is this vehicle available for test drive?')">
                        <i class="fas fa-car me-1"></i>Test Drive?
                      </button>
                      <button type="button" class="btn btn-outline-info btn-sm" onclick="setQuickQuestion('What financing options are available?')">
                        <i class="fas fa-credit-card me-1"></i>Financing?
                      </button>
                      <button type="button" class="btn btn-outline-warning btn-sm" onclick="setQuickQuestion('What is the vehicle history and condition?')">
                        <i class="fas fa-history me-1"></i>Vehicle History?
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setQuickQuestion('What are the maintenance costs?')">
                        <i class="fas fa-wrench me-1"></i>Maintenance?
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Vehicle Information - Compact Version -->
        <div class="col-lg-6">
          <div class="vehicle-info">
            <div class="info-section">
              <h5 class="info-title mb-3">
                <i class="fas fa-info-circle me-2"></i>Vehicle Information
              </h5>
              
              <div class="row g-2">
                <div class="col-md-4 col-6">
                  <div class="text-center p-2 bg-light rounded vehicle-info-card">
                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($car['brand']); ?></div>
                    <small class="text-muted">Brand</small>
                    </div>
                  </div>
                <div class="col-md-4 col-6">
                  <div class="text-center p-2 bg-light rounded vehicle-info-card">
                    <div class="fw-bold text-success"><?php echo htmlspecialchars($car['model']); ?></div>
                    <small class="text-muted">Model</small>
                </div>
                    </div>
                <div class="col-md-4 col-6">
                  <div class="text-center p-2 bg-light rounded vehicle-info-card">
                    <div class="fw-bold text-warning"><?php echo htmlspecialchars($car['year']); ?></div>
                    <small class="text-muted">Year</small>
                  </div>
                </div>
                <div class="col-md-4 col-6">
                  <div class="text-center p-2 bg-light rounded vehicle-info-card">
                    <div class="fw-bold text-info"><?php echo htmlspecialchars($car['body_type'] ?? 'N/A'); ?></div>
                    <small class="text-muted">Body Type</small>
                    </div>
                  </div>
                <div class="col-md-4 col-6">
                  <div class="text-center p-2 bg-light rounded vehicle-info-card">
                    <div class="fw-bold text-danger"><?php echo htmlspecialchars($car['color'] ?? 'N/A'); ?></div>
                    <small class="text-muted">Color</small>
                </div>
                    </div>
                <div class="col-md-4 col-6">
                  <div class="text-center p-2 bg-light rounded vehicle-info-card">
                    <div class="fw-bold text-secondary"><?php echo number_format($car['mileage'] ?? 0); ?> km</div>
                    <small class="text-muted">Mileage</small>
                  </div>
                </div>
                    </div>
                  </div>
            
            <!-- Price Information - Compact -->
            <div class="price-section mt-3">
              <div class="card">
                <div class="card-body text-center py-3">
                  <h4 class="text-primary mb-1">RM <?php echo number_format($car['price'], 0); ?></h4>
                  <small class="text-muted">Vehicle Price</small>
                  
                  <?php if ($car['insurance']): ?>
                    <div class="mt-2">
                      <small class="text-muted">Est. Insurance: </small>
                      <strong>RM <?php echo number_format($car['insurance'], 2); ?>/Year</strong>
                </div>
                  <?php endif; ?>
                    </div>
                  </div>
                </div>
                    </div>
                  </div>
                </div>
      
      <!-- Loan & Insurance Calculators - Full Width -->
      <div class="row mt-4">
        <!-- Loan Calculator -->
        <div class="col-12 mb-4">
          <div class="card shadow-sm">
            <div class="card-header bg-gradient-primary text-white">
              <div class="d-flex align-items-center">
                <i class="fas fa-calculator me-3 fs-4"></i>
                <div>
                  <h5 class="mb-0">Car Loan Calculator</h5>
                  <small class="opacity-75">Get instant financing estimates for your <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></small>
                </div>
              </div>
            </div>
            <div class="card-body p-4">
              <!-- Quick Info Bar -->
              <div class="row mb-4">
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-car text-primary mb-2"></i>
                    <div class="fw-bold"><?php echo htmlspecialchars($car['brand']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($car['model']); ?></small>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-calendar text-success mb-2"></i>
                    <div class="fw-bold"><?php echo htmlspecialchars($car['year']); ?></div>
                    <small class="text-muted">Model Year</small>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-tag text-warning mb-2"></i>
                    <div class="fw-bold">RM <?php echo number_format($car['price'], 0); ?></div>
                    <small class="text-muted">Vehicle Price</small>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-percentage text-info mb-2"></i>
                    <div class="fw-bold"><?php echo getRecommendedInterestRate($car); ?>%</div>
                    <small class="text-muted">Est. Interest Rate</small>
                  </div>
                </div>
              </div>
              
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="vehiclePrice" class="form-label fw-semibold">
                    <i class="fas fa-car me-2 text-primary"></i>Vehicle Price (RM)
                  </label>
                  <input type="number" class="form-control form-control-lg" id="vehiclePrice" value="<?php echo $car['price']; ?>" readonly>
                  <div class="form-text">Fixed price for this vehicle</div>
                </div>
                <div class="col-md-3">
                  <label for="downPaymentPercent" class="form-label fw-semibold">
                    <i class="fas fa-hand-holding-usd me-2 text-success"></i>Down Payment
                  </label>
                  <div class="input-group">
                    <input type="number" class="form-control form-control-lg" id="downPaymentPercent" value="10" min="0" max="100">
                    <span class="input-group-text">%</span>
                  </div>
                  <div class="form-text">Recommended: 10-20%</div>
                </div>
                <div class="col-md-3">
                  <label for="loanTerm" class="form-label fw-semibold">
                    <i class="fas fa-clock me-2 text-warning"></i>Loan Tenure
                  </label>
                  <select class="form-select form-select-lg" id="loanTerm">
                    <option value="3">3 Years (36 months)</option>
                    <option value="5" selected>5 Years (60 months)</option>
                    <option value="7">7 Years (84 months)</option>
                    <option value="9">9 Years (108 months)</option>
                  </select>
                  <div class="form-text">Longer tenure = lower payment</div>
                </div>
                <div class="col-md-3">
                  <label for="interestRate" class="form-label fw-semibold">
                    <i class="fas fa-chart-line me-2 text-danger"></i>Interest Rate (% p.a.)
                  </label>
                  <div class="input-group">
                    <input type="number" class="form-control form-control-lg" id="interestRate" value="<?php echo getRecommendedInterestRate($car); ?>" min="0" max="20" step="0.1">
                    <span class="input-group-text">%</span>
                  </div>
                  <div class="form-text">Based on credit profile</div>
                </div>
              </div>
              
              <div class="d-grid gap-2 mt-4">
                <button class="btn btn-primary btn-lg" onclick="calculateLoan()">
                  <i class="fas fa-calculator me-2"></i>Calculate My Monthly Payment
                </button>
              </div>
              
              <div id="loanResult" class="mt-4" style="display: none;">
                <div class="card border-success">
                  <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                      <i class="fas fa-check-circle me-2"></i>Your Loan Summary
                    </h6>
                  </div>
                    <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-primary" id="monthlyPayment">RM 0</div>
                          <div class="text-muted">Monthly Payment</div>
                    </div>
                  </div>
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-success" id="downPaymentAmount">RM 0</div>
                          <div class="text-muted">Down Payment</div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-info" id="loanAmount">RM 0</div>
                          <div class="text-muted">Loan Amount</div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-warning" id="totalInterest">RM 0</div>
                          <div class="text-muted">Total Interest</div>
                        </div>
                      </div>
                    </div>
                    <div class="mt-3">
                      <div class="row">
                        <div class="col-md-6">
                          <strong>Total Amount to Pay:</strong>
                          <span id="totalAmount" class="text-primary fs-5 ms-2"></span>
                        </div>
                        <div class="col-md-6">
                          <strong>Effective Interest Rate:</strong>
                          <span id="effectiveRate" class="text-success fs-5 ms-2"></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
                </div>
              </div>
            </div>
            
        <!-- Insurance Calculator -->
        <div class="col-12 mb-4">
          <div class="card shadow-sm">
            <div class="card-header bg-gradient-success text-white">
              <div class="d-flex align-items-center">
                <i class="fas fa-shield-alt me-3 fs-4"></i>
                <div>
                  <h5 class="mb-0">Car Insurance Calculator</h5>
                  <small class="opacity-75">Get instant insurance quotes for your <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></small>
                </div>
              </div>
            </div>
            <div class="card-body p-4">
              <!-- Risk Assessment Dashboard -->
              <div class="row mb-4">
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-shield-alt text-<?php echo getBrandRiskLevel($car['brand']); ?> mb-2"></i>
                    <div class="fw-bold text-<?php echo getBrandRiskLevel($car['brand']); ?>"><?php echo ucfirst(getBrandRiskLevel($car['brand'])); ?></div>
                    <small class="text-muted">Brand Risk</small>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-calendar-alt text-<?php echo getAgeRiskLevel($car['year']); ?> mb-2"></i>
                    <div class="fw-bold text-<?php echo getAgeRiskLevel($car['year']); ?>"><?php echo ucfirst(getAgeRiskLevel($car['year'])); ?></div>
                    <small class="text-muted">Age Factor</small>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-car text-info mb-2"></i>
                    <div class="fw-bold text-info"><?php echo htmlspecialchars($car['body_type'] ?? 'Standard'); ?></div>
                    <small class="text-muted">Body Type</small>
                  </div>
                </div>
                <div class="col-md-3 text-center">
                  <div class="border rounded p-3 bg-light">
                    <i class="fas fa-percentage text-primary mb-2"></i>
                    <div class="fw-bold text-primary"><?php echo getBaseInsuranceRate($car); ?>%</div>
                    <small class="text-muted">Base Rate</small>
                  </div>
                </div>
              </div>
              
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="vehicleValue" class="form-label fw-semibold">
                    <i class="fas fa-tag me-2 text-primary"></i>Vehicle Value (RM)
                  </label>
                  <input type="number" class="form-control form-control-lg" id="vehicleValue" value="<?php echo $car['price']; ?>" min="1000">
                  <div class="form-text">Market value of your vehicle</div>
                </div>
                <div class="col-md-3">
                  <label for="driverAge" class="form-label fw-semibold">
                    <i class="fas fa-user me-2 text-success"></i>Driver Age
                  </label>
                  <select class="form-select form-select-lg" id="driverAge">
                    <option value="18-25">18-25 years (Higher risk)</option>
                    <option value="26-35" selected>26-35 years (Standard)</option>
                    <option value="36-45">36-45 years (Lower risk)</option>
                    <option value="46-55">46-55 years (Lowest risk)</option>
                    <option value="55+">55+ years (Low risk)</option>
                  </select>
                  <div class="form-text">Age affects premium calculation</div>
                </div>
                <div class="col-md-3">
                  <label for="drivingExperience" class="form-label fw-semibold">
                    <i class="fas fa-id-card me-2 text-warning"></i>Driving Experience
                  </label>
                  <select class="form-select form-select-lg" id="drivingExperience">
                    <option value="new">New Driver (0-2 years)</option>
                    <option value="experienced" selected>Experienced (3+ years)</option>
                  </select>
                  <div class="form-text">Experience reduces premium rates</div>
                </div>
                <div class="col-md-3">
                  <label for="coverageType" class="form-label fw-semibold">
                    <i class="fas fa-shield-alt me-2 text-danger"></i>Coverage Type
                  </label>
                  <select class="form-select form-select-lg" id="coverageType">
                    <option value="comprehensive" selected>Comprehensive (Full Coverage)</option>
                    <option value="third_party_fire">Third Party + Fire & Theft</option>
                    <option value="third_party">Third Party Only</option>
                  </select>
                  <div class="form-text">Recommended: Comprehensive</div>
                </div>
              </div>
              
              <div class="d-grid gap-2 mt-4">
                <button class="btn btn-success btn-lg" onclick="calculateInsurance()">
                  <i class="fas fa-shield-alt me-2"></i>Get My Insurance Quote
                </button>
              </div>
              
              <div id="insuranceResult" class="mt-4" style="display: none;">
                <div class="card border-info">
                  <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                      <i class="fas fa-shield-alt me-2"></i>Your Insurance Quote
                    </h6>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-primary" id="annualPremium">RM 0</div>
                          <div class="text-muted">Annual Premium</div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-success" id="monthlyPremium">RM 0</div>
                          <div class="text-muted">Monthly Premium</div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-info" id="coverageTypeResult">-</div>
                          <div class="text-muted">Coverage Type</div>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                          <div class="fs-4 fw-bold text-warning" id="discountApplied">0%</div>
                          <div class="text-muted">Discount Applied</div>
                        </div>
                      </div>
                    </div>
                    <div class="mt-3">
                      <div class="alert alert-light">
                        <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Quote Details:</h6>
                        <ul class="mb-0">
                          <li>Premium calculated based on vehicle value and risk factors</li>
                          <li>Rates may vary based on final assessment</li>
                          <li>Contact us for personalized quotes and discounts</li>
                        </ul>
                    </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Description -->
      <?php if (!empty($car['description'])): ?>
        <div class="mt-4">
          <h5><i class="fas fa-file-alt me-2"></i>Description</h5>
          <p><?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
        </div>
      <?php endif; ?>
      
      <!-- Seller Information -->
      <div class="mt-4">
        <h5><i class="fas fa-user me-2"></i><?php echo ($car['status'] === 'sold' && $car['user_id'] == 6) ? 'Admin Information' : 'Seller Information'; ?></h5>
        <div class="row">
          <div class="col-md-6">
            <p><strong><?php echo ($car['status'] === 'sold' && $car['user_id'] == 6) ? 'Admin:' : 'Seller:'; ?></strong> 
              <?php 
              if ($car['status'] === 'sold' && $car['user_id'] == 6) {
                echo 'Admin';
              } else {
                echo htmlspecialchars($car['username']);
              }
              ?>
            </p>
          </div>
          <div class="col-md-6">
            <p><strong>Phone:</strong> 
              <?php 
              if ($car['status'] === 'sold' && $car['user_id'] == 6) {
                echo htmlspecialchars($admin_phone ?: 'N/A');
              } else {
                echo htmlspecialchars($car['seller_phone'] ?: 'N/A');
              }
              ?>
            </p>
          </div>
        </div>
        <?php if ($car['status'] === 'sold' && $car['user_id'] == 6): ?>
        <div class="row mt-2">
          <div class="col-md-6">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($admin_email ?: 'N/A'); ?></p>
          </div>
          <div class="col-md-6">
            <p><strong>Office:</strong> 123 Jalan Tun Razak, KL</p>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Action Buttons -->
      <div class="mt-4">
        <h5><i class="fas fa-hand-pointer me-2"></i>Actions</h5>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($role === 'user' && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'buyer' && $car['status'] === 'available' && !$is_ordered): ?>
            <?php
            $whatsapp_message = urlencode("I would like to inquire about the {$car['brand']} {$car['model']} (Year: {$car['year']}, Price: RM {$car['price']})");
            $whatsapp_link = $admin_phone ? "https://wa.me/" . str_replace(['+', ' '], '', $admin_phone) . "?text=$whatsapp_message" : "#";
            ?>
            <a href="<?php echo $whatsapp_link; ?>" class="btn btn-primary <?php echo !$admin_phone ? 'disabled' : ''; ?>" target="_blank">
              <i class="fab fa-whatsapp me-2"></i>Contact Admin
            </a>

            <form method="post" class="d-inline">
              <input type="hidden" name="favorite_action" value="<?php echo $is_favorited ? 'remove' : 'add'; ?>">
              <button type="submit" class="btn btn-outline-warning">
                <i class="fas fa-heart me-2"></i><?php echo $is_favorited ? 'Favorited' : 'Add to Favorites'; ?>
              </button>
            </form>

            <form method="post" class="d-inline">
              <button type="submit" name="order" class="btn btn-success">
                <i class="fas fa-shopping-cart me-2"></i>Order Now
              </button>
            </form>

            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#testDriveModal">
              <i class="fas fa-car me-2"></i>Book Test Drive
            </button>
          <?php elseif ($is_ordered): ?>
            <button class="btn btn-primary disabled">
              <i class="fas fa-check-circle me-2"></i>Vehicle Ordered
            </button>
          <?php elseif ($role === 'user' && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'seller' && $car['user_id'] == $user_id): ?>
            <!-- Seller Management Actions -->
            <a href="seller_vehicle_management.php" class="btn btn-outline-primary">
              <i class="fas fa-edit me-2"></i>Manage Vehicle
            </a>
            <a href="seller_purchase_offers.php" class="btn btn-outline-success">
              <i class="fas fa-handshake me-2"></i>View Offers
            </a>
          <?php endif; ?>
        </div>
      </div>


      <!-- Vehicle Issues -->
      <?php if (!empty($vehicle_issues)): ?>
        <div class="mt-4">
          <h5><i class="fas fa-exclamation-triangle me-2"></i>Vehicle Issues</h5>
          <div class="alert alert-warning" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Important:</strong> This vehicle has been inspected and the following issues have been identified:
          </div>
          <div class="row">
            <?php foreach ($vehicle_issues as $issue): ?>
              <div class="col-md-6 mb-3">
                <div class="card border-<?php 
                  echo $issue['severity'] === 'critical' ? 'danger' : 
                      ($issue['severity'] === 'high' ? 'warning' : 
                      ($issue['severity'] === 'medium' ? 'info' : 'success')); 
                ?>">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <h6 class="card-title mb-0">
                        <i class="fas fa-<?php 
                          echo $issue['issue_type'] === 'engine' ? 'cog' : 
                              ($issue['issue_type'] === 'transmission' ? 'cogs' : 
                              ($issue['issue_type'] === 'brakes' ? 'stop-circle' : 
                              ($issue['issue_type'] === 'electrical' ? 'bolt' : 
                              ($issue['issue_type'] === 'body' ? 'car' : 
                              ($issue['issue_type'] === 'interior' ? 'chair' : 
                              ($issue['issue_type'] === 'suspension' ? 'compress' : 
                              ($issue['issue_type'] === 'exhaust' ? 'smoke' : 'wrench'))))))); 
                        ?> me-1"></i>
                        <?php echo ucfirst(htmlspecialchars($issue['issue_type'])); ?>
                      </h6>
                      <span class="badge bg-<?php 
                        echo $issue['severity'] === 'critical' ? 'danger' : 
                            ($issue['severity'] === 'high' ? 'warning' : 
                            ($issue['severity'] === 'medium' ? 'info' : 'success')); 
                      ?>">
                        <?php echo ucfirst($issue['severity']); ?>
                      </span>
                    </div>
                    <p class="card-text small">
                      <?php echo nl2br(htmlspecialchars($issue['issue_description'])); ?>
                    </p>
                    <?php if (!empty($issue['admin_notes'])): ?>
                      <div class="mt-2">
                        <small class="text-muted">
                          <strong>Admin Notes:</strong><br>
                          <?php echo nl2br(htmlspecialchars($issue['admin_notes'])); ?>
                        </small>
                      </div>
                    <?php endif; ?>
                    <div class="mt-2">
                      <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        Reported: <?php echo date('M d, Y', strtotime($issue['created_at'])); ?>
                      </small>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Average Rating -->
      <div class="mt-4">
        <h5><i class="fas fa-star me-2"></i>Rating & Reviews</h5>
        <?php if ($total_reviews > 0): ?>
          <p>
            <strong>⭐ <?= number_format($avg_rating, 1) ?>/5</strong>
            (<?= $total_reviews ?> reviews)
          </p>
        <?php else: ?>
          <p class="text-muted">No reviews yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Review Section -->
  <div class="card mb-4">
    <div class="card-body">
      <h5>Leave a Review</h5>
      <?php if ($role === 'user' && $car['status'] === 'available' && !$is_ordered): ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Rating</label>
            <select name="rating" class="form-select" required>
              <option value="">-- Select --</option>
              <option value="5">⭐⭐⭐⭐⭐ (5)</option>
              <option value="4">⭐⭐⭐⭐ (4)</option>
              <option value="3">⭐⭐⭐ (3)</option>
              <option value="2">⭐⭐ (2)</option>
              <option value="1">⭐ (1)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Comment</label>
            <textarea name="comment" class="form-control" rows="3"></textarea>
          </div>
          <button type="submit" name="submit_review" class="btn btn-success">Submit Review</button>
        </form>
      <?php else: ?>
        <p class="text-muted">Only users can leave reviews for available cars.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Reviews -->
  <div class="card mb-5">
    <div class="card-body">
      <h5>Recent Reviews</h5>
      <?php if (!empty($reviews)): ?>
        <?php foreach ($reviews as $rev): ?>
          <div class="border-bottom mb-3 pb-2">
            <strong><?= htmlspecialchars($rev['username']); ?></strong>
            <span class="text-warning"><?= str_repeat("⭐", $rev['rating']); ?></span>
            <p><?= nl2br(htmlspecialchars($rev['comment'])); ?></p>
            <small class="text-muted"><?= $rev['created_at']; ?></small>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted">No reviews yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

    </div>
  </div>
</div>

<!-- Test Drive Modal -->
<div class="modal fade" id="testDriveModal" tabindex="-1" aria-labelledby="testDriveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="testDriveModalLabel">Book Test Drive</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="book_test_drive" value="1">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Select Date</label>
          <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Select Time</label>
          <select name="time" class="form-select" required>
            <option value="">Choose a time slot</option>
            <option value="09:00">9:00 AM</option>
            <option value="10:00">10:00 AM</option>
            <option value="11:00">11:00 AM</option>
            <option value="12:00">12:00 PM</option>
            <option value="13:00">1:00 PM</option>
            <option value="14:00">2:00 PM</option>
            <option value="15:00">3:00 PM</option>
            <option value="16:00">4:00 PM</option>
            <option value="17:00">5:00 PM</option>
            <option value="18:00">6:00 PM</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-warning">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
function copyLink(url, e) {
  navigator.clipboard.writeText(url).then(() => {
    const tooltip = document.createElement('div');
    tooltip.className = 'share-tooltip';
    tooltip.textContent = 'Link copied!';
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + 'px';
    tooltip.style.top = (rect.top - 30) + 'px';
    
    setTimeout(() => {
      document.body.removeChild(tooltip);
    }, 2000);
  });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});

// Loan Calculator Functions
function calculateLoan() {
  const vehiclePrice = parseFloat(document.getElementById('vehiclePrice').value) || 0;
  const downPaymentPercent = parseFloat(document.getElementById('downPaymentPercent').value) || 0;
  const interestRate = parseFloat(document.getElementById('interestRate').value) || 0;
  const loanTerm = parseInt(document.getElementById('loanTerm').value) || 5;
  
  // Validation
  if (vehiclePrice <= 0) {
    alert('Please enter a valid vehicle price');
    return;
  }
  
  if (downPaymentPercent < 0 || downPaymentPercent > 100) {
    alert('Down payment percentage must be between 0% and 100%');
    return;
  }
  
  const downPaymentAmount = vehiclePrice * (downPaymentPercent / 100);
  const loanAmount = vehiclePrice - downPaymentAmount;
  const monthlyRate = interestRate / 100 / 12;
  const numberOfPayments = loanTerm * 12;
  
  // Calculate monthly payment using the loan formula
  let monthlyPayment;
  if (monthlyRate === 0) {
    monthlyPayment = loanAmount / numberOfPayments;
  } else {
    monthlyPayment = loanAmount * (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) / 
                    (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
  }
  
  const totalAmount = monthlyPayment * numberOfPayments;
  const totalInterest = totalAmount - loanAmount;
  const effectiveRate = ((totalInterest / loanAmount) * 100) / loanTerm;
  
  // Display results
  document.getElementById('monthlyPayment').textContent = 'RM ' + monthlyPayment.toFixed(2);
  document.getElementById('downPaymentAmount').textContent = 'RM ' + downPaymentAmount.toFixed(2);
  document.getElementById('loanAmount').textContent = 'RM ' + loanAmount.toFixed(2);
  document.getElementById('totalInterest').textContent = 'RM ' + totalInterest.toFixed(2);
  document.getElementById('totalAmount').textContent = 'RM ' + totalAmount.toFixed(2);
  document.getElementById('effectiveRate').textContent = effectiveRate.toFixed(2) + '%';
  
  document.getElementById('loanResult').style.display = 'block';
}

// Insurance Calculator Functions
function calculateInsurance() {
  const vehicleValue = parseFloat(document.getElementById('vehicleValue').value) || 0;
  const driverAge = document.getElementById('driverAge').value;
  const drivingExperience = document.getElementById('drivingExperience').value;
  const coverageType = document.getElementById('coverageType').value;
  
  // Vehicle information from PHP
  const vehicleBrand = '<?php echo strtolower($car['brand']); ?>';
  const vehicleYear = <?php echo $car['year']; ?>;
  const currentYear = new Date().getFullYear();
  const vehicleAge = currentYear - vehicleYear;
  
  // Validation
  if (vehicleValue <= 0) {
    alert('Please enter a valid vehicle value');
    return;
  }
  
  // Brand-specific base rates
  const brandRates = {
    'toyota': 0.022,
    'honda': 0.023,
    'proton': 0.028,
    'perodua': 0.029,
    'nissan': 0.024,
    'mazda': 0.025,
    'bmw': 0.032,
    'mercedes': 0.033,
    'audi': 0.031,
    'hyundai': 0.026,
    'kia': 0.027,
    'volkswagen': 0.030
  };
  
  let baseRate = brandRates[vehicleBrand] || 0.025;
  
  // Coverage type adjustments
  switch (coverageType) {
    case 'comprehensive':
      baseRate *= 1.0; // Full rate
      break;
    case 'third_party_fire':
      baseRate *= 0.6; // 40% discount
      break;
    case 'third_party':
      baseRate *= 0.3; // 70% discount
      break;
  }
  
  // Vehicle age adjustments
  if (vehicleAge <= 2) {
    baseRate *= 1.2; // 20% higher for newer cars
  } else if (vehicleAge >= 10) {
    baseRate *= 0.8; // 20% lower for older cars
  }
  
  let annualPremium = vehicleValue * baseRate;
  
  // Age-based adjustments
  switch (driverAge) {
    case '18-25':
      annualPremium *= 1.5; // 50% higher for young drivers
      break;
    case '26-35':
      annualPremium *= 1.0; // Standard rate
      break;
    case '36-45':
      annualPremium *= 0.9; // 10% discount
      break;
    case '46-55':
      annualPremium *= 0.85; // 15% discount
      break;
    case '55+':
      annualPremium *= 0.9; // 10% discount
      break;
  }
  
  // Experience-based adjustments
  if (drivingExperience === 'new') {
    annualPremium *= 1.3; // 30% higher for new drivers
  }
  
  const monthlyPremium = annualPremium / 12;
  
  // Calculate discount percentage
  let discountPercentage = 0;
  if (driverAge === '36-45') discountPercentage += 10;
  if (driverAge === '46-55') discountPercentage += 15;
  if (driverAge === '55+') discountPercentage += 10;
  if (drivingExperience === 'experienced') discountPercentage += 5;
  if (vehicleAge >= 10) discountPercentage += 10;
  
  // Display results
  document.getElementById('annualPremium').textContent = 'RM ' + annualPremium.toFixed(2);
  document.getElementById('monthlyPremium').textContent = 'RM ' + monthlyPremium.toFixed(2);
  document.getElementById('coverageTypeResult').textContent = coverageType.charAt(0).toUpperCase() + coverageType.slice(1).replace('_', ' ');
  document.getElementById('discountApplied').textContent = discountPercentage + '%';
  
  document.getElementById('insuranceResult').style.display = 'block';
}


// Collapse toggle function
function toggleCollapse(targetId) {
  const target = document.getElementById(targetId);
  const icon = document.getElementById(targetId + 'Icon');
  
  if (target.classList.contains('show')) {
    // Collapse
    target.classList.remove('show');
    icon.classList.remove('fa-chevron-up');
    icon.classList.add('fa-chevron-down');
  } else {
    // Expand
    target.classList.add('show');
    icon.classList.remove('fa-chevron-down');
    icon.classList.add('fa-chevron-up');
  }
}

// Vehicle question functions
function setQuickQuestion(question) {
  const textarea = document.getElementById('vehicleQuestion');
  if (textarea) {
    textarea.value = question;
    textarea.focus();
  }
}

function clearQuestionForm() {
  const textarea = document.getElementById('vehicleQuestion');
  if (textarea) {
    textarea.value = '';
    textarea.focus();
  }
}

// Handle vehicle question form submission
function handleVehicleQuestion(event) {
  event.preventDefault();
  
  const form = event.target;
  const formData = new FormData(form);
  
  // Show loading state
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
  submitBtn.disabled = true;
  
  // Send AJAX request
  fetch('ai_chat_handler.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
  })
  .then(data => {
    console.log('Response data:', data);
    if (data.success) {
      // Show success message
      showQuestionResponse(data);
      
      // Clear form
      clearQuestionForm();
    } else {
      // Show error message
      showQuestionError(data.message || 'Failed to send question');
    }
  })
  .catch(error => {
    console.error('AJAX Error:', error);
    showQuestionError('Network error: ' + error.message + '. Please try again.');
  })
  .finally(() => {
    // Reset button
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  });
}

function showQuestionResponse(data) {
  const responseDiv = document.createElement('div');
  responseDiv.className = 'alert alert-success mt-3';
  
  if (data.type === 'ai_reply') {
    responseDiv.innerHTML = `
      <i class="fas fa-robot me-2"></i>
      <strong>AI Assistant:</strong> ${data.ai_reply}
      <br><small class="text-muted">This was an automated response. For more specific questions, our admin team will respond shortly.</small>
    `;
  } else {
    responseDiv.innerHTML = `
      <i class="fas fa-check-circle me-2"></i>
      <strong>Question Sent!</strong> Your question has been forwarded to our admin team. They will respond shortly.
    `;
  }
  
  // Insert after form
  const form = document.getElementById('vehicleQuestionForm');
  form.parentNode.insertBefore(responseDiv, form.nextSibling);
  
  // Auto-remove after 10 seconds
  setTimeout(() => {
    responseDiv.remove();
  }, 10000);
}

function showQuestionError(message) {
  const errorDiv = document.createElement('div');
  errorDiv.className = 'alert alert-danger mt-3';
  errorDiv.innerHTML = `
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Error:</strong> ${message}
  `;
  
  // Insert after form
  const form = document.getElementById('vehicleQuestionForm');
  form.parentNode.insertBefore(errorDiv, form.nextSibling);
  
  // Auto-remove after 5 seconds
  setTimeout(() => {
    errorDiv.remove();
  }, 5000);
}

// Auto-calculate when inputs change
document.addEventListener('DOMContentLoaded', function() {
  // Loan calculator auto-update - 实时自动计算
  const loanInputs = ['vehiclePrice', 'downPaymentPercent', 'interestRate', 'loanTerm'];
  loanInputs.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      element.addEventListener('input', function() {
        calculateLoan(); // 移除条件，总是自动计算
      });
      element.addEventListener('change', function() {
        calculateLoan(); // 选择框变化时也自动计算
      });
    }
  });
  
  // Insurance calculator auto-update - 实时自动计算
  const insuranceInputs = ['vehicleValue', 'driverAge', 'drivingExperience', 'coverageType'];
  insuranceInputs.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      element.addEventListener('input', function() {
        calculateInsurance(); // 移除条件，总是自动计算
      });
      element.addEventListener('change', function() {
        calculateInsurance(); // 选择框变化时也自动计算
      });
    }
  });
  
  // 页面加载时自动计算一次
  setTimeout(function() {
    calculateLoan();
    calculateInsurance();
  }, 500);
  
  // Vehicle question form handler
  const vehicleQuestionForm = document.getElementById('vehicleQuestionForm');
  if (vehicleQuestionForm) {
    vehicleQuestionForm.addEventListener('submit', handleVehicleQuestion);
  }
});
</script>

<?php include 'footer.php'; ?>

</body>
</html>
