<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user' || $_SESSION['user_type'] !== 'buyer') {
    header("Location: index.php?show_login=1");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle removal of favorite
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['car_id'])) {
    $car_id = intval($_POST['car_id']);
    $delete_stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $user_id, $car_id);
        $delete_stmt->execute();
        if ($delete_stmt->affected_rows > 0) {
            $message = "✅ Vehicle removed from favorites";
        } else {
            $message = "❌ Failed to remove from favorites, vehicle not in list";
        }
        $delete_stmt->close();
    } else {
        $message = "❌ Database error, please try again later";
        error_log("My favorites: Delete favorite query prepare error: " . $conn->error);
    }
}

// Get admin phone
$admin_phone = '';
$admin_stmt = $conn->prepare("SELECT phone FROM users WHERE role = 'admin' LIMIT 1");
if ($admin_stmt) {
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_row = $admin_result->fetch_assoc()) {
        $admin_phone = $admin_row['phone'];
    } else {
        error_log("My favorites: No admin found in users table");
    }
    $admin_stmt->close();
} else {
    error_log("My favorites: Admin phone query prepare error: " . $conn->error);
}

// Query favorited vehicles
$stmt = null;
$sql = "SELECT c.*, u.username AS seller 
        FROM favorites f 
        JOIN cars c ON f.car_id = c.id 
        JOIN users u ON c.user_id = u.id 
        WHERE f.user_id = ? AND c.status = 'available'";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("My favorites query prepare error: " . $conn->error);
    $message = "❌ Database query error, please contact the administrator";
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $favorites = $result->fetch_all(MYSQLI_ASSOC);
    $totalFavorites = count($favorites);
    error_log("My favorites: Found $totalFavorites vehicles for user_id $user_id");
    $stmt->close();
}

// Get vehicle main image
function getMainImage($conn, $car_id) {
    $defaultImage = "Uploads/car.jpg";
    $stmt = $conn->prepare("SELECT image FROM car_images WHERE car_id = ? AND is_main = TRUE LIMIT 1");
    if (!$stmt) {
        error_log("My favorites: Image query prepare error for car_id $car_id: " . $conn->error);
        return $defaultImage;
    }
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $imagePath = $row && !empty($row['image']) && file_exists("Uploads/" . trim($row['image'])) 
        ? "Uploads/" . trim($row['image']) 
        : $defaultImage;
    error_log("My favorites: Image path for car_id $car_id: $imagePath, exists: " . (file_exists("Uploads/" . trim($row['image'] ?? 'car.jpg')) ? 'Yes' : 'No'));
    $stmt->close();
    return $imagePath;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - Vehicle Trading Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .car-card { transition: transform 0.3s, box-shadow 0.3s; }
        .car-card:hover { transform: translateY(-5px); box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .card-img-top { border-radius: 8px 8px 0 0; height: 200px; object-fit: cover; }
        .no-favorites { text-align: center; padding: 50px; color: #6c757d; }
        .btn { font-size: 0.9rem; }
        .disabled { pointer-events: none; opacity: 0.6; text-decoration: none; color: #888; }
        @media (max-width: 576px) {
            .car-card { margin-bottom: 20px; }
            .btn { font-size: 0.8rem; }
        }
    </style>
</head>
<body class="bg-light">

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">My Favorites</h2>

    <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($totalFavorites > 0): ?>
        <p class="text-muted mb-4">Total <strong><?php echo $totalFavorites; ?></strong> vehicles favorited</p>
        <div class="row">
            <?php foreach ($favorites as $row): ?>
                <?php
                $imagePath = getMainImage($conn, $row['id']);
                $whatsapp_message = urlencode("I would like to inquire about the {$row['brand']} {$row['model']} (Year: {$row['year']}, Price: RM {$row['price']})");
                $whatsapp_link = $admin_phone ? "https://wa.me/" . str_replace(['+', ' '], '', $admin_phone) . "?text=$whatsapp_message" : "#";
                $link_class = $admin_phone ? '' : 'disabled';
                ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="car-card card shadow-sm border-0 rounded">
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" class="card-img-top" alt="Vehicle Image">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['brand'] . " " . $row['model']); ?></h5>
                            <p class="card-text text-muted">
                                Year: <?php echo htmlspecialchars($row['year']); ?><br>
                                Seller: <?php echo htmlspecialchars($row['seller']); ?><br>
                                Description: <?php echo htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : ''); ?>
                            </p>
                            <h6 class="text-primary fw-bold">RM <?php echo number_format($row['price'], 2); ?></h6>
                            <a href="car_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary w-100 mb-2">View Details</a>
                            <a href="<?php echo htmlspecialchars($whatsapp_link); ?>" class="btn btn-success w-100 mb-2 <?php echo $link_class; ?>" target="_blank">Contact Admin</a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="car_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger w-100">Remove from Favorites</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-favorites">
            <p>You haven't favorited any vehicles yet!</p>
            <a href="index.php" class="btn btn-primary">Browse Vehicles</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

