<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?show_login=1");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $car_id = intval($_POST['car_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        // Check if user already reviewed this car
        $stmt = $conn->prepare("SELECT id FROM car_reviews WHERE car_id = ? AND reviewer_id = ?");
        $stmt->bind_param("ii", $car_id, $user_id);
        $stmt->execute();
        $existing_review = $stmt->get_result();
        
        if ($existing_review->num_rows > 0) {
            $message = "❌ You have already reviewed this vehicle.";
        } else {
            $stmt = $conn->prepare("INSERT INTO car_reviews (car_id, reviewer_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $car_id, $user_id, $rating, $comment);
            
            if ($stmt->execute()) {
                $message = "✅ Review submitted successfully!";
            } else {
                error_log("Review insertion error: " . $stmt->error);
                $message = "❌ Failed to submit review.";
            }
        }
        $stmt->close();
    } else {
        $message = "❌ Please provide a valid rating and comment.";
    }
}

// Handle platform feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_platform_feedback'])) {
    $platform_rating = intval($_POST['platform_rating']);
    $feedback_category = trim($_POST['feedback_category']);
    $platform_comment = trim($_POST['platform_comment']);
    
    if ($platform_rating >= 1 && $platform_rating <= 5 && !empty($feedback_category) && !empty($platform_comment)) {
        // Check if user already submitted platform feedback today
        $stmt = $conn->prepare("SELECT id FROM platform_feedback WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $existing_feedback = $stmt->get_result();
        
        if ($existing_feedback->num_rows > 0) {
            $message = "❌ You have already submitted platform feedback today. Please try again tomorrow.";
        } else {
            $stmt = $conn->prepare("INSERT INTO platform_feedback (user_id, rating, category, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $user_id, $platform_rating, $feedback_category, $platform_comment);
            
            if ($stmt->execute()) {
                $message = "✅ Platform feedback submitted successfully! Thank you for helping us improve.";
            } else {
                error_log("Platform feedback insertion error: " . $stmt->error);
                $message = "❌ Failed to submit platform feedback.";
            }
        }
        $stmt->close();
    } else {
        $message = "❌ Please provide a valid rating, category, and comment.";
    }
}

// Get cars that user has purchased for review
$cars_stmt = $conn->prepare("
    SELECT DISTINCT c.*, o.created_at as order_date
    FROM cars c 
    JOIN orders o ON c.id = o.car_id 
    WHERE o.user_id = ? AND o.order_status = 'completed' 
    ORDER BY o.created_at DESC
");
$cars_stmt->bind_param("i", $user_id);
$cars_stmt->execute();
$cars_result = $cars_stmt->get_result();
$cars = $cars_result->fetch_all(MYSQLI_ASSOC);
$cars_stmt->close();

// Get user's reviews
$user_reviews_stmt = $conn->prepare("
    SELECT r.*, c.brand, c.model, c.year 
    FROM car_reviews r 
    JOIN cars c ON r.car_id = c.id 
    WHERE r.reviewer_id = ? 
    ORDER BY r.created_at DESC
");
$user_reviews_stmt->bind_param("i", $user_id);
$user_reviews_stmt->execute();
$user_reviews_result = $user_reviews_stmt->get_result();
$user_reviews = $user_reviews_result->fetch_all(MYSQLI_ASSOC);
$user_reviews_stmt->close();

// Get vehicle main image function
function getMainImage($conn, $car_id) {
    $defaultImage = "Uploads/car.jpg";
    $stmt = $conn->prepare("SELECT image FROM car_images WHERE car_id = ? AND is_main = TRUE LIMIT 1");
    if (!$stmt) {
        return $defaultImage;
    }
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($row = $result->fetch_assoc()) {
        $imagePath = "Uploads/" . trim($row['image']);
        // Check if file exists, if not return default
        if (file_exists($imagePath)) {
            return $imagePath;
        }
    }
    return $defaultImage;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Reviews - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-stars {
            color: #ffc107;
            font-size: 1.2em;
        }
        .car-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .car-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .review-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }
        .btn-review {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .btn-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .tab-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <!-- Header Section -->
        <div class="section-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="fas fa-star me-3"></i>Reviews & Feedback</h1>
                    <p class="mb-0">Review your purchased vehicles or share feedback about our platform</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="stats-card">
                        <h3><?php echo count($user_reviews); ?></h3>
                        <p class="mb-0">Reviews Written</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Display -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs nav-fill mb-4" id="reviewTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles" type="button" role="tab">
                    <i class="fas fa-car me-2"></i>My Purchases
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="platform-tab" data-bs-toggle="tab" data-bs-target="#platform" type="button" role="tab">
                    <i class="fas fa-globe me-2"></i>Platform Feedback
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="my-reviews-tab" data-bs-toggle="tab" data-bs-target="#my-reviews" type="button" role="tab">
                    <i class="fas fa-user me-2"></i>My Reviews
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="reviewTabsContent">
            <!-- Available Vehicles Tab -->
            <div class="tab-pane fade show active" id="vehicles" role="tabpanel">
                <h3 class="mb-4">My Purchased Vehicles</h3>
                
                <?php if (!empty($cars)): ?>
                    <div class="row">
                        <?php foreach ($cars as $car): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card car-card h-100">
                                    <img src="<?php echo htmlspecialchars(getMainImage($conn, $car['id'])); ?>" 
                                         class="car-image" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($car['year']); ?> • <?php echo htmlspecialchars($car['color']); ?></p>
                                        <p class="card-text">
                                            <strong>$<?php echo number_format($car['price']); ?></strong>
                                        </p>
                                        
                                        <!-- Quick Review Form -->
                                        <div class="mt-3">
                                            <button class="btn btn-review w-100" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#reviewModal"
                                                    data-car-id="<?php echo $car['id']; ?>"
                                                    data-car-name="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                                                <i class="fas fa-star me-2"></i>Write Review
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-car fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No purchased vehicles to review</h5>
                        <p class="text-muted">You can only review vehicles you have purchased. Purchase a vehicle first to leave a review!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Platform Feedback Tab -->
            <div class="tab-pane fade" id="platform" role="tabpanel">
                <h3 class="mb-4">Platform Feedback</h3>
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Share Your Experience</h5>
                                <p class="card-text">Help us improve our platform by sharing your overall experience, suggestions, or any issues you've encountered.</p>
                                
                                <form method="post">
                                    <input type="hidden" name="platform_feedback" value="1">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Overall Platform Rating</label>
                                        <select name="platform_rating" class="form-select" required>
                                            <option value="">-- Select Rating --</option>
                                            <option value="5">⭐⭐⭐⭐⭐ Excellent (5)</option>
                                            <option value="4">⭐⭐⭐⭐ Very Good (4)</option>
                                            <option value="3">⭐⭐⭐ Good (3)</option>
                                            <option value="2">⭐⭐ Fair (2)</option>
                                            <option value="1">⭐ Poor (1)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Feedback Category</label>
                                        <select name="feedback_category" class="form-select" required>
                                            <option value="">-- Select Category --</option>
                                            <option value="general">General Experience</option>
                                            <option value="website">Website Usability</option>
                                            <option value="customer_service">Customer Service</option>
                                            <option value="payment">Payment Process</option>
                                            <option value="delivery">Delivery Process</option>
                                            <option value="suggestion">Suggestion</option>
                                            <option value="complaint">Complaint</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Your Feedback</label>
                                        <textarea name="platform_comment" class="form-control" rows="4" 
                                                  placeholder="Share your thoughts about our platform..." required></textarea>
                                    </div>
                                    
                                    <button type="submit" name="submit_platform_feedback" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Why Your Feedback Matters</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Help us improve user experience</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Identify areas for enhancement</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Make the platform better for everyone</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Your voice shapes our future</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Reviews Tab -->
            <div class="tab-pane fade" id="my-reviews" role="tabpanel">
                <h3 class="mb-4">My Reviews</h3>
                
                <?php if (!empty($user_reviews)): ?>
                    <?php foreach ($user_reviews as $review): ?>
                        <div class="review-card card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($review['brand'] . ' ' . $review['model']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($review['year']); ?></small>
                                    </div>
                                    <div class="rating-stars">
                                        <?php echo str_repeat('<i class="fas fa-star"></i>', $review['rating']); ?>
                                        <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $review['rating']); ?>
                                    </div>
                                </div>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                </small>
                                
                                <?php if (!empty($review['reply'])): ?>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <strong class="text-primary">Admin Reply:</strong>
                                        <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($review['reply'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-star fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No reviews yet</h5>
                        <p class="text-muted">Start reviewing vehicles to help other users!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel">Write a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="car_id" id="modalCarId">
                        <h6 id="modalCarName" class="mb-3"></h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select" required>
                                <option value="">-- Select Rating --</option>
                                <option value="5">⭐⭐⭐⭐⭐ Excellent (5)</option>
                                <option value="4">⭐⭐⭐⭐ Very Good (4)</option>
                                <option value="3">⭐⭐⭐ Good (3)</option>
                                <option value="2">⭐⭐ Fair (2)</option>
                                <option value="1">⭐ Poor (1)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Review</label>
                            <textarea name="comment" class="form-control" rows="4" 
                                      placeholder="Share your experience with this vehicle..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_review" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle review modal
        document.getElementById('reviewModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const carId = button.getAttribute('data-car-id');
            const carName = button.getAttribute('data-car-name');
            
            document.getElementById('modalCarId').value = carId;
            document.getElementById('modalCarName').textContent = carName;
        });

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
