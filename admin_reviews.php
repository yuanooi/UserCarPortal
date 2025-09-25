<?php
session_start();
include 'includes/db.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get all reviews
$sql = "
    SELECT r.id, r.car_id, r.reviewer_id, r.rating, r.comment, r.reply, r.created_at, 
           c.brand AS car_brand, c.model AS car_model, c.year, u.username
    FROM car_reviews r
    LEFT JOIN cars c ON r.car_id = c.id
    LEFT JOIN users u ON r.reviewer_id = u.id
    ORDER BY r.created_at DESC
";
$result = $conn->query($sql);
$reviews = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
} else {
    error_log("Admin reviews query error: " . $conn->error);
    $message = "❌ Failed to fetch reviews: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage Reviews - Admin Dashboard">
    <title>Manage Reviews - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
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
            display: flex;
            flex-direction: column;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--card-shadow);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        .nav-link {
            color: var(--secondary-color) !important;
            font-weight: 500;
            transition: var(--transition);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            background-color: rgba(37, 99, 235, 0.1);
        }

        .content {
            flex: 1 0 auto;
            padding: 2rem 0;
        }

        .admin-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            max-width: 1200px;
            margin: 0 auto 2rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f1f5f9;
        }

        .table-striped > tbody > tr:nth-of-type(odd) > td {
            background-color: #f8fafc;
        }

        .table-hover > tbody > tr:hover > td {
            background-color: #e2e8f0;
        }

        .no-reply {
            color: #94a3b8;
            font-style: italic;
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
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: #dc2626;
            border-color: #dc2626;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
            border-color: #b91c1c;
            transform: translateY(-1px);
        }

        .footer {
            flex-shrink: 0;
            background: #1e293b;
            color: white;
            padding: 1rem 0;
            text-align: center;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .admin-section {
                padding: 1.5rem;
                margin: 0 1rem;
            }
            
            .table {
                font-size: 0.875rem;
            }
            
            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<?php include 'header.php'; ?>
<div class="content">
    <div class="container">
        <section class="admin-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 style="color: var(--primary-color); margin: 0;">Review Management</h2>
                <a href="admin_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($reviews): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Car</th>
                                <th>User</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Reply</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['id']); ?></strong></td>
                                <td>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($row['car_brand'] . " " . $row['car_model']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['year']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['username'] ?: 'Unknown User'); ?></td>
                                <td><?php echo htmlspecialchars($row['rating'] ?: 'N/A'); ?> ⭐</td>
                                <td>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></p>
                                </td>
                                <td>
                                    <?php if ($row['reply']): ?>
                                        <div class="bg-light p-2 rounded" style="font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($row['reply']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-reply">No reply yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                        $date = new DateTime($row['created_at']);
                                        echo $date->format('M j, Y \a\t g:i A');
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="reply_review.php?id=<?php echo htmlspecialchars($row['id']); ?>" 
                                           class="btn btn-primary btn-sm" 
                                           title="Reply to Review">
                                            <i class="fas fa-reply me-1"></i>Reply
                                        </a>
                                        <a href="delete_review.php?id=<?php echo htmlspecialchars($row['id']); ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this review?');"
                                           title="Delete Review">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No reviews found</h4>
                    <p class="text-muted">There are currently no reviews to moderate.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- Footer -->
<?php include 'footer.php'; ?>

</body>
</html>
