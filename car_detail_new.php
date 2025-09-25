<?php
session_start();
include 'includes/db.php';

// 检查用户是否登录，如果没有登录则显示提示
$is_logged_in = isset($_SESSION['user_id']);
if (!$is_logged_in) {
    // 显示登录提示页面
    include 'header.php';
    ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-lock fa-3x text-warning mb-3"></i>
                        <h3 class="mb-3">需要登录</h3>
                        <p class="text-muted mb-4">您需要登录才能查看车辆详情</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="fas fa-sign-in-alt me-2"></i>立即登录
                            </button>
                            <button class="btn btn-outline-primary" onclick="window.history.back()">
                                <i class="fas fa-arrow-left me-2"></i>返回
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

// Admin phone
$admin_phone = '';
if ($role === 'user') {
    $admin_stmt = $conn->prepare("SELECT phone FROM users WHERE role = 'admin' LIMIT 1");
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_row = $admin_result->fetch_assoc()) {
        $admin_phone = $admin_row['phone'];
    }
    $admin_stmt->close();
}

// Book Test Drive
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['book_test_drive'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $date = $_POST['date'];

    if ($name && $email && $date) {
        $check_date_stmt = $conn->prepare("SELECT id FROM test_drives WHERE car_id = ? AND test_date = ? AND status = 'approved'");
        $check_date_stmt->bind_param("is", $car_id, $date);
        $check_date_stmt->execute();
        $check_date_result = $check_date_stmt->get_result();

        if ($check_date_result->num_rows > 0) {
            $message = "❌ This date is already booked.";
        } else {
            $insert_drive = $conn->prepare("INSERT INTO test_drives (car_id, user_id, name, email, test_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $insert_drive->bind_param("iisss", $car_id, $user_id, $name, $email, $date);
            if ($insert_drive->execute()) {
                $message = "✅ Test drive request sent! Waiting for admin approval.";
            } else {
                error_log("Test drive insertion error: " . $insert_drive->error);
                $message = "❌ Failed to book test drive.";
            }
            $insert_drive->close();
        }
        $check_date_stmt->close();
    } else {
        $message = "❌ Please fill in all fields.";
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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        <!-- Vehicle Information -->
        <div class="col-lg-6">
          <div class="vehicle-info">
            <div class="info-section">
              <h4 class="info-title">
                <i class="fas fa-info-circle me-2"></i>Vehicle Information
              </h4>
              
              <div class="row">
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Brand</h6>
                      <p class="card-text"><?php echo htmlspecialchars($car['brand']); ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Model</h6>
                      <p class="card-text"><?php echo htmlspecialchars($car['model']); ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Year</h6>
                      <p class="card-text"><?php echo htmlspecialchars($car['year']); ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Body Type</h6>
                      <p class="card-text"><?php echo htmlspecialchars($car['body_type'] ?? 'N/A'); ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Color</h6>
                      <p class="card-text"><?php echo htmlspecialchars($car['color'] ?? 'N/A'); ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Mileage</h6>
                      <p class="card-text"><?php echo number_format($car['mileage'] ?? 0); ?> km</p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Transmission</h6>
                      <p class="card-text"><?php echo htmlspecialchars($car['transmission'] ?? 'N/A'); ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted">Fuel Type</h6>
                      <p class="card-text"><?php echo htmlspecialchars($car['fuel_type'] ?? 'N/A'); ?></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Price Information -->
            <div class="price-section mt-4">
              <div class="card">
                <div class="card-body text-center">
                  <h5 class="card-title">Price</h5>
                  <h2 class="text-primary">RM <?php echo number_format($car['price'], 0); ?></h2>
                  
                  <?php if ($car['insurance']): ?>
                    <div class="mt-3">
                      <small class="text-muted">Est. Insurance</small><br>
                      <strong>RM <?php echo number_format($car['insurance'], 2); ?> / Year</strong>
                    </div>
                  <?php endif; ?>
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
        <h5><i class="fas fa-user me-2"></i>Seller Information</h5>
        <div class="row">
          <div class="col-md-6">
            <p><strong>Seller:</strong> <?php echo htmlspecialchars($car['username']); ?></p>
          </div>
          <div class="col-md-6">
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($car['seller_phone'] ?: 'N/A'); ?></p>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="mt-4">
        <h5><i class="fas fa-hand-pointer me-2"></i>Actions</h5>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($role === 'user' && $car['status'] === 'available' && !$is_ordered): ?>
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
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-warning">Submit</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
</script>

<?php include 'footer.php'; ?>

</body>
</html>
