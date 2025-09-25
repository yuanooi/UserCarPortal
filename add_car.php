<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "⚠️ Illegal request, CSRF check failed";
        error_log("CSRF validation failed in add_car.php for user_id: " . $_SESSION['user_id']);
    } else {
        // Sanitize input
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year = trim($_POST['year'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $user_id = $_SESSION['user_id'];

        // Validate input
        if (empty($brand) || empty($model) || empty($year) || empty($price)) {
            $message = "⚠️ Make, model, year and price are required";
        } elseif (!is_numeric($year) || $year < 1900 || $year > date('Y') + 1) {
            $message = "⚠️ Please enter a valid year";
        } elseif (!is_numeric($price) || $price <= 0) {
            $message = "⚠️ Please enter a valid price";
        } else {
            // Insert car with 'pending' status
            $stmt = $conn->prepare("INSERT INTO cars (user_id, brand, model, year, price, description, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            if (!$stmt) {
                $message = "❌ Database error, please try again later";
                error_log("Car insert prepare error: " . $conn->error);
            } else {
                $stmt->bind_param("issids", $user_id, $brand, $model, $year, $price, $description);
                if ($stmt->execute()) {
                    $car_id = $conn->insert_id;

                    // Handle image upload
                    if (!empty($_FILES['images']['name'][0])) {
                        $upload_dir = "Uploads/";
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $is_first = true;

                        foreach ($_FILES['images']['tmp_name'] as $index => $tmp_name) {
                            if ($_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {
                                $file_type = $_FILES['images']['type'][$index];
                                if (in_array($file_type, $allowed_types)) {
                                    $file_name = $car_id . "_" . time() . "_" . $index . "." . pathinfo($_FILES['images']['name'][$index], PATHINFO_EXTENSION);
                                    $file_path = $upload_dir . $file_name;

                                    if (move_uploaded_file($tmp_name, $file_path)) {
                                        $is_main = $is_first ? 1 : 0;
                                        $img_stmt = $conn->prepare("INSERT INTO car_images (car_id, image, is_main) VALUES (?, ?, ?)");
                                        $img_stmt->bind_param("isi", $car_id, $file_name, $is_main);
                                        $img_stmt->execute();
                                        $img_stmt->close();
                                        $is_first = false;
                                    } else {
                                        $message = "⚠️ Image upload failed";
                                    }
                                } else {
                                    $message = "⚠️ Only JPEG, PNG, or GIF images are supported";
                                }
                            }
                        }
                    }

                    if (empty($message)) {
                        $success = true;
                        $message = "✅ The vehicle has been submitted and is awaiting administrator approval! You will be redirected to the dashboard shortly....";
                        header("refresh:2;url=admin_dashboard.php");
                    }
                } else {
                    $message = "❌ Failed to add vehicle, please try again later";
                    error_log("Car insert execute error: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}
?>

<?php include 'header.php'; ?>

<div class="container">
    <h3 class="text-center mb-4">🚗 Add Cars</h3>

    <?php if ($message): ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?> text-center">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="mb-3">
            <label class="form-label">Brand</label>
            <input type="text" name="brand" class="form-control" value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Model</label>
            <input type="text" name="model" class="form-control" value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Year</label>
            <input type="number" name="year" class="form-control" value="<?php echo isset($_POST['year']) ? htmlspecialchars($_POST['year']) : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Price (RM)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Upload Photos</label>
            <input type="file" name="images[]" class="form-control" multiple accept="image/jpeg,image/png,image/gif">
        </div>
        <button type="submit" class="btn btn-primary w-100">Submit vehicle</button>
    </form>
    <p class="text-center mt-3">
        <a href="admin_dashboard.php" class="text-decoration-none">Return to dashboard</a>
    </p>
</div>

<?php include 'footer.php'; ?>

<?php
$conn->close();
?>