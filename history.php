<?php
session_start();
include 'includes/db.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $message = "❌ Database connection failed, please try again later";
    $error = true;
} else {
    $error = false;
}

// Check login status
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?show_login=1");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle clear history (independent of query)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_history'])) {
    $stmt = $conn->prepare("DELETE FROM user_history WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: history.php");
        exit();
    } else {
        $message = "❌ Failed to clear history, please try again";
        $error = true;
    }
}

$history = [];
$message = "";
if (!$error) {
    // Query: Fetch unique car_id with latest viewed_at
    $stmt = $conn->prepare("
        SELECT c.id, c.brand, c.model, c.year, c.price, c.description, 
               ci.image AS image, 
               uh_latest.viewed_at
        FROM (
            SELECT car_id, MAX(viewed_at) AS viewed_at
            FROM user_history 
            WHERE user_id = ?
            GROUP BY car_id
        ) uh_latest
        JOIN cars c ON uh_latest.car_id = c.id
        LEFT JOIN car_images ci ON c.id = ci.car_id AND ci.is_main = 1
        ORDER BY uh_latest.viewed_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
    }
    if (empty($history)) {
        $message = "❌ You have no browsing history";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browsing History - User Car Portal">
    <title>User Car Portal - Browsing History</title>
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

        .history-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .history-card {
            background: #f1f5f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .history-card:hover {
            background-color: #e2e8f0;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
        }

        .history-card a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .history-card img {
            max-width: 100px;
            max-height: 100px;
            margin-right: 1rem;
            border-radius: 8px;
            object-fit: cover;
        }

        .history-card h5 {
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .history-card p {
            color: #1e293b;
            margin: 0.25rem 0;
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
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
            .history-section {
                padding: 1.5rem;
            }
            .history-card {
                flex-direction: column;
                text-align: center;
            }
            .history-card a {
                flex-direction: column;
            }
            .history-card img {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<?php include 'header.php'; ?>
<div class="content">
    <div class="container">
        <section class="history-section">
            <h2 class="text-center mb-4" style="color: var(--primary-color);">Browsing History</h2>
            <form method="POST" style="margin-bottom: 1rem;">
                <button type="submit" name="clear_history" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear all browsing history?');">Clear History</button>
            </form>

            <?php if (!empty($message)): ?>
                <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php elseif (!empty($history)): ?>
                <?php foreach ($history as $record): ?>
                    <a href="car_detail.php?id=<?php echo htmlspecialchars($record['id']); ?>" class="history-card">
                        <div class="d-flex align-items-center w-100">
                            <?php 
                            // Standardize image path: Assume image is filename, prepend Uploads/
                            $imagePath = !empty($record['image']) ? 'Uploads/' . htmlspecialchars(trim($record['image'])) : 'Uploads/car.jpg';
                            // Log if file doesn't exist
                            if (!file_exists($imagePath)) {
                                error_log("History: Image not found for car_id {$record['id']}: $imagePath");
                            }
                            ?>
                            <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($record['brand'] . ' ' . $record['model']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="width: 100px; height: 100px; background: #e2e8f0; display: none; align-items: center; justify-content: center; margin-right: 1rem; border-radius: 8px;">
                                <i class="fas fa-car" style="font-size: 2rem; color: var(--secondary-color);"></i>
                            </div>
                            <div>
                                <h5><?php echo htmlspecialchars($record['brand'] . ' ' . $record['model'] . ' (' . $record['year'] . ')'); ?></h5>
                                <p><strong>Price:</strong> RM <?php echo number_format($record['price'], 2); ?></p>
                                <p><strong>Description:</strong> <?php 
                                    $desc = $record['description'];
                                    echo htmlspecialchars(strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc);
                                ?></p>
                                <p><small class="text-muted">Last Viewed: <?php echo htmlspecialchars($record['viewed_at']); ?></small></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- Footer -->
<?php include 'footer.php'; ?>

</body>
</html>

