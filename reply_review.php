<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_reviews.php?message=" . urlencode("❌ Invalid review ID"));
    exit;
}
$review_id = intval($_GET['id']);

// Fetch review
$stmt = $conn->prepare("
    SELECT r.id, r.car_id, r.reviewer_id, r.rating, r.comment, r.reply, r.created_at, 
           c.brand, c.model, u.username
    FROM car_reviews r 
    LEFT JOIN cars c ON r.car_id = c.id 
    LEFT JOIN users u ON r.reviewer_id = u.id 
    WHERE r.id = ?
");
$stmt->bind_param("i", $review_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: admin_reviews.php?message=" . urlencode("❌ Review not found"));
    exit;
}
$review = $result->fetch_assoc();
$stmt->close();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply']) && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "❌ Invalid CSRF token";
    } else {
        $reply = trim($_POST['reply']);
        if ($reply) {
            $stmt = $conn->prepare("UPDATE car_reviews SET reply = ? WHERE id = ?");
            $stmt->bind_param("si", $reply, $review_id);
            if ($stmt->execute()) {
                $message = "✅ Reply saved successfully";
                header("Location: admin_reviews.php?message=" . urlencode($message));
                exit;
            } else {
                $message = "❌ Failed to save reply: " . $conn->error;
                error_log("Reply save error: " . $conn->error);
            }
            $stmt->close();
        } else {
            $message = "❌ Reply cannot be empty";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reply to Review - Admin Dashboard">
    <title>Reply to Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e2e8f0 100%);
            color: #1e293b;
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .btn {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-secondary:hover {
            background-color: #475569;
            border-color: #475569;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .form-control {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>Reply to Review</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <div class="mb-3">
            <p><strong>Car:</strong> <?php echo htmlspecialchars($review['brand'] . ' ' . $review['model'] ?: 'Unknown Car'); ?></p>
            <p><strong>User:</strong> <?php echo htmlspecialchars($review['username'] ?: 'Unknown User'); ?></p>
            <p><strong>Rating:</strong> <?php echo htmlspecialchars($review['rating'] ?: 'N/A'); ?> ⭐</p>
            <p><strong>Comment:</strong> <?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
            <p><strong>Current Reply:</strong> <?php echo htmlspecialchars($review['reply'] ?: 'No reply yet'); ?></p>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label class="form-label">Your Reply</label>
                <textarea name="reply" class="form-control" rows="5"><?php echo htmlspecialchars($review['reply'] ?: ''); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Reply</button>
            <a href="admin_reviews.php" class="btn btn-secondary">Back</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
