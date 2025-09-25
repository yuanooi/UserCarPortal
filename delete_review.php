<?php
session_start();
include 'includes/db.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Validate review ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_reviews.php?message=" . urlencode("❌ Invalid review ID"));
    exit;
}
$review_id = intval($_GET['id']);

// Delete review
$stmt = $conn->prepare("DELETE FROM car_reviews WHERE id = ?");
$stmt->bind_param("i", $review_id);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        header("Location: admin_reviews.php?message=" . urlencode("✅ Review deleted successfully"));
    } else {
        header("Location: admin_reviews.php?message=" . urlencode("❌ Review not found"));
    }
} else {
    error_log("Delete review error: " . $conn->error);
    header("Location: admin_reviews.php?message=" . urlencode("❌ Failed to delete review: " . $conn->error));
}
$stmt->close();
$conn->close();
?>