<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?show_login=1");
    exit;
}

// Get car_id from URL parameter
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 1;

// Fetch car details
$stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php?message=" . urlencode("Car not found"));
    exit;
}

$car = $result->fetch_assoc();
$stmt->close();

// Handle review submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        // Check if user already reviewed this car
        $stmt = $conn->prepare("SELECT id FROM car_reviews WHERE car_id = ? AND reviewer_id = ?");
        $stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
        $stmt->execute();
        $existing_review = $stmt->get_result();
        
        if ($existing_review->num_rows > 0) {
            $message = "❌ You have already reviewed this car.";
        } else {
            $stmt = $conn->prepare("INSERT INTO car_reviews (car_id, reviewer_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $car_id, $_SESSION['user_id'], $rating, $comment);
            
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

// Fetch average rating and total reviews
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM car_reviews WHERE car_id = ?");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$stmt->bind_result($avg_rating, $total_reviews);
$stmt->fetch();
$stmt->close();

// Fetch all reviews for this car
$stmt = $conn->prepare("
    SELECT r.*, u.username, u.email 
    FROM car_reviews r 
    JOIN users u ON r.reviewer_id = u.id 
    WHERE r.car_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if current user has already reviewed this car
$user_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM car_reviews WHERE car_id = ? AND reviewer_id = ?");
    $stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
    $stmt->execute();
    $user_reviewed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($car['brand'] . " " . $car['model']); ?> - Reviews</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-stars {
            color: #ffc107;
            font-size: 1.2em;
        }
        .review-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .car-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .stats-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-4">
        <!-- Car Information -->
        <div class="car-info">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><?php echo htmlspecialchars($car['brand'] . " " . $car['model']); ?></h1>
                    <p class="mb-0"><?php echo htmlspecialchars($car['year']); ?> • <?php echo htmlspecialchars($car['color']); ?> • <?php echo htmlspecialchars($car['fuel_type']); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="car_detail.php?id=<?php echo $car_id; ?>" class="btn btn-light">
                        <i class="fas fa-eye"></i> View Details
                    </a>
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

        <div class="row">
            <!-- Statistics -->
            <div class="col-md-4">
                <div class="stats-card">
                    <h3 class="text-primary"><?php echo number_format($avg_rating, 1); ?></h3>
                    <div class="rating-stars mb-2">
                        <?php 
                        $full_stars = floor($avg_rating);
                        $half_star = ($avg_rating - $full_stars) >= 0.5;
                        for ($i = 0; $i < $full_stars; $i++) echo '<i class="fas fa-star"></i>';
                        if ($half_star) echo '<i class="fas fa-star-half-alt"></i>';
                        for ($i = $full_stars + ($half_star ? 1 : 0); $i < 5; $i++) echo '<i class="far fa-star"></i>';
                        ?>
                    </div>
                    <p class="text-muted"><?php echo $total_reviews; ?> Review<?php echo $total_reviews != 1 ? 's' : ''; ?></p>
                </div>

                <!-- Review Form -->
                <?php if (!$user_reviewed): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Write a Review</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
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
                                    <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience with this vehicle..." required></textarea>
                                </div>
                                <button type="submit" name="submit_review" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Review
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card mt-3">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <h5>Review Submitted</h5>
                            <p class="text-muted">You have already reviewed this vehicle.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reviews List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-comments"></i> Customer Reviews
                            <span class="badge bg-primary ms-2"><?php echo $total_reviews; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($reviews)): ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                                <div class="rating-stars">
                                                    <?php echo str_repeat('<i class="fas fa-star"></i>', $review['rating']); ?>
                                                    <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $review['rating']); ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                        
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
                                <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Reviews Yet</h5>
                                <p class="text-muted">Be the first to review this vehicle!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
