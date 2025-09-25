<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1&message=" . urlencode("Please log in as an admin to edit vehicles."));
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed in admin_edit_car.php: " . $conn->connect_error);
    $message = "❌ Database connection failed, please contact the administrator";
    header("Location: admincar_details.php?message=" . urlencode($message));
    exit;
}

// Get car_id from URL or form submission
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : (isset($_POST['select_car']) ? intval($_POST['select_car']) : 0);
$car = null;
$images = [];

// Fetch all cars for selection if no car_id is provided
$cars = [];
if ($car_id <= 0) {
    $stmt = $conn->prepare("SELECT id, brand, model FROM cars ORDER BY brand, model");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $cars = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Car list query preparation failed: " . $conn->error);
        $message = "❌ Failed to fetch car list.";
    }
}

// Fetch car details and images if car_id is valid
if ($car_id > 0 && $conn->ping()) {
    $stmt = $conn->prepare("
        SELECT id, user_id, brand, model, year, price, status, description, body_type, instalment, transmission, mileage, color, insurance
        FROM cars
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $car_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $car = $result->fetch_assoc();
            $img_stmt = $conn->prepare("
                SELECT id, image, is_main
                FROM car_images
                WHERE car_id = ?
                ORDER BY is_main DESC, id
            ");
            if ($img_stmt) {
                $img_stmt->bind_param("i", $car_id);
                $img_stmt->execute();
                $img_result = $img_stmt->get_result();
                $images = $img_result->fetch_all(MYSQLI_ASSOC);
                $img_stmt->close();
            } else {
                error_log("Image fetch query preparation failed: " . $conn->error);
                $message = "❌ Database query error for images.";
            }
        } else {
            error_log("Car ID {$car_id} not found in admin_edit_car.php");
            $message = "❌ Car not found.";
            $car_id = 0; // Reset car_id to show car selection
        }
        $stmt->close();
    } else {
        error_log("Car fetch query preparation failed: " . $conn->error);
        $message = "❌ Database query error.";
    }
}

// Handle form submission (car selection or update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['select_car']) && intval($_POST['select_car']) > 0) {
        // Redirect to edit page with selected car_id
        header("Location: admin_edit_car.php?car_id=" . intval($_POST['select_car']));
        exit;
    } elseif (isset($_POST['car_id']) && isset($_POST['csrf_token'])) {
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed in admin_edit_car.php, car_id: " . ($_POST['car_id'] ?? 'unknown'));
            $message = "❌ Invalid request, please try again.";
        } else {
            if (isset($_POST['delete_car'])) {
                // Delete car and associated images
                $conn->begin_transaction();
                try {
                    $delete_img_stmt = $conn->prepare("DELETE FROM car_images WHERE car_id = ?");
                    if ($delete_img_stmt) {
                        $delete_img_stmt->bind_param("i", $car_id);
                        $delete_img_stmt->execute();
                        $delete_img_stmt->close();
                    } else {
                        throw new Exception("Image deletion query preparation failed: " . $conn->error);
                    }

                    $delete_car_stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
                    if ($delete_car_stmt) {
                        $delete_car_stmt->bind_param("i", $car_id);
                        $delete_car_stmt->execute();
                        $delete_car_stmt->close();
                    } else {
                        throw new Exception("Car deletion query preparation failed: " . $conn->error);
                    }

                    $conn->commit();
                    error_log("Car ID {$car_id} deleted by admin ID {$user_id}");
                    $message = "✅ Car deleted successfully!";
                    header("Location: admincar_details.php?message=" . urlencode($message));
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Failed to delete car ID {$car_id}: " . $e->getMessage());
                    $message = "❌ Failed to delete car, please try again.";
                }
            } else {
                // Update car
                $car_id = intval($_POST['car_id']);
                $brand = trim($_POST['brand'] ?? '');
                $model = trim($_POST['model'] ?? '');
                $year = intval($_POST['year'] ?? 0);
                $price = floatval($_POST['price'] ?? 0);
                $status = $_POST['status'] ?? 'pending';
                $description = trim($_POST['description'] ?? '');
                $body_type = trim($_POST['body_type'] ?? '') ?: null;
                $instalment = !empty($_POST['instalment']) ? floatval($_POST['instalment']) : null;
                $transmission = trim($_POST['transmission'] ?? '') ?: null;
                $mileage = !empty($_POST['mileage']) ? intval($_POST['mileage']) : null;
                $color = trim($_POST['color'] ?? '') ?: null;
                $insurance = !empty($_POST['insurance']) ? floatval($_POST['insurance']) : null;
                $delete_images = $_POST['delete_images'] ?? [];
                $main_image_id = isset($_POST['main_image']) && $_POST['main_image'] !== '' ? $_POST['main_image'] : null;

                // Validate inputs
                $errors = [];
                $valid_body_types = ['Sedan', 'SUV', 'Hatchback', 'Coupe', 'Convertible', 'Wagon', 'Van', 'Truck'];
                $valid_transmissions = ['Automatic', 'Manual', 'CVT', 'Dual Clutch'];
                if (empty($brand)) $errors[] = "Brand is required.";
                if (empty($model)) $errors[] = "Model is required.";
                if ($year < 1886 || $year > date('Y') + 1) $errors[] = "Year must be between 1886 and " . (date('Y') + 1) . ".";
                if ($price <= 0) $errors[] = "Price must be greater than 0.";
                if (!in_array($status, ['available', 'reserved', 'sold', 'pending', 'rejected'])) $errors[] = "Invalid status.";
                if ($body_type && !in_array($body_type, $valid_body_types)) $errors[] = "Invalid body type.";
                if ($transmission && !in_array($transmission, $valid_transmissions)) $errors[] = "Invalid transmission type.";
                if ($instalment !== null && $instalment < 0) $errors[] = "Instalment cannot be negative.";
                if ($mileage !== null && $mileage < 0) $errors[] = "Mileage cannot be negative.";
                if ($insurance !== null && $insurance < 0) $errors[] = "Insurance cannot be negative.";

                // Validate image uploads
                $new_images = [];
                $upload_dir = 'C:/xampp/htdocs/user_car_portal/images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $valid_image_ids = array_column($images, 'id');
                if (!empty($_FILES['new_images']['name'][0])) {
                    foreach ($_FILES['new_images']['name'] as $index => $name) {
                        if ($_FILES['new_images']['error'][$index] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                $errors[] = "Image " . ($index + 1) . " must be a JPEG or PNG.";
                            } elseif ($_FILES['new_images']['size'][$index] > 5 * 1024 * 1024) {
                                $errors[] = "Image " . ($index + 1) . " exceeds 5MB size limit.";
                            } else {
                                $filename = 'car_' . time() . '_' . $index . '.' . $ext;
                                $dest_path = $upload_dir . $filename;
                                if (move_uploaded_file($_FILES['new_images']['tmp_name'][$index], $dest_path)) {
                                    $new_images[$index] = 'images/' . $filename;
                                } else {
                                    $errors[] = "Failed to upload image " . ($index + 1) . ".";
                                }
                            }
                        }
                    }
                }

                // Validate main image only if images exist or are uploaded
                if ($main_image_id !== null && !empty($valid_image_ids) && !in_array($main_image_id, $valid_image_ids) && !in_array($main_image_id, array_keys($new_images))) {
                    $errors[] = "Invalid main image selection. Please select a valid existing or new image.";
                }

                // If no main image is selected and images exist, set the first one as main
                if ($main_image_id === null && (!empty($valid_image_ids) || !empty($new_images))) {
                    $main_image_id = !empty($valid_image_ids) ? $valid_image_ids[0] : array_keys($new_images)[0];
                }

                if (empty($errors)) {
                    $conn->begin_transaction();
                    try {
                        // Update cars table
                        $stmt = $conn->prepare("
                            UPDATE cars
                            SET brand = ?, model = ?, year = ?, price = ?, status = ?, description = ?,
                                body_type = ?, instalment = ?, transmission = ?, mileage = ?, color = ?, insurance = ?
                            WHERE id = ?
                        ");
                        if ($stmt) {
                            $stmt->bind_param("ssidsissisisi", $brand, $model, $year, $price, $status, $description,
                                $body_type, $instalment, $transmission, $mileage, $color, $insurance, $car_id);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            throw new Exception("Car update query preparation failed: " . $conn->error);
                        }

                        // Update is_main for existing images
                        if ($main_image_id !== null && !str_starts_with($main_image_id, 'new_')) {
                            $reset_main_stmt = $conn->prepare("UPDATE car_images SET is_main = 0 WHERE car_id = ?");
                            if ($reset_main_stmt) {
                                $reset_main_stmt->bind_param("i", $car_id);
                                $reset_main_stmt->execute();
                                $reset_main_stmt->close();
                            } else {
                                throw new Exception("Reset main image query preparation failed: " . $conn->error);
                            }

                            $set_main_stmt = $conn->prepare("UPDATE car_images SET is_main = 1 WHERE id = ? AND car_id = ?");
                            if ($set_main_stmt) {
                                $set_main_stmt->bind_param("ii", $main_image_id, $car_id);
                                $set_main_stmt->execute();
                                $set_main_stmt->close();
                            } else {
                                throw new Exception("Set main image query preparation failed: " . $conn->error);
                            }
                        }

                        // Delete selected images
                        if (!empty($delete_images)) {
                            $placeholders = implode(',', array_fill(0, count($delete_images), '?'));
                            $delete_stmt = $conn->prepare("
                                DELETE FROM car_images
                                WHERE id IN ($placeholders) AND car_id = ?
                            ");
                            if ($delete_stmt) {
                                $params = array_merge($delete_images, [$car_id]);
                                $types = str_repeat('i', count($delete_images)) . 'i';
                                $delete_stmt->bind_param($types, ...$params);
                                $delete_stmt->execute();
                                $delete_stmt->close();
                            } else {
                                throw new Exception("Image deletion query preparation failed: " . $conn->error);
                            }
                        }

                        // Insert new images
                        foreach ($new_images as $index => $image) {
                            $is_main = ($main_image_id == "new_$index") ? 1 : 0;
                            $insert_img_stmt = $conn->prepare("
                                INSERT INTO car_images (car_id, image, is_main)
                                VALUES (?, ?, ?)
                            ");
                            if ($insert_img_stmt) {
                                $insert_img_stmt->bind_param("isi", $car_id, $image, $is_main);
                                $insert_img_stmt->execute();
                                $insert_img_stmt->close();
                            } else {
                                throw new Exception("New image insert query preparation failed: " . $conn->error);
                            }
                        }

                        $conn->commit();
                        error_log("Car ID {$car_id} updated by admin ID {$user_id}, images updated/deleted/added");
                        $message = "✅ Car updated successfully!";

                        // Refresh car data and images
                        $stmt = $conn->prepare("
                            SELECT id, user_id, brand, model, year, price, status, description, body_type, instalment, transmission, mileage, color, insurance
                            FROM cars
                            WHERE id = ?
                        ");
                        $stmt->bind_param("i", $car_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $car = $result->fetch_assoc();
                        $stmt->close();

                        $img_stmt = $conn->prepare("
                            SELECT id, image, is_main
                            FROM car_images
                            WHERE car_id = ?
                            ORDER BY is_main DESC, id
                        ");
                        $img_stmt->bind_param("i", $car_id);
                        $img_stmt->execute();
                        $img_result = $img_stmt->get_result();
                        $images = $img_result->fetch_all(MYSQLI_ASSOC);
                        $img_stmt->close();
                    } catch (Exception $e) {
                        $conn->rollback();
                        error_log("Failed to update car ID {$car_id}: " . $e->getMessage());
                        $message = "❌ Failed to update car: " . $e->getMessage();
                    }
                } else {
                    $message = "❌ " . implode(" ", $errors);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Edit Vehicle - User Car Portal">
    <title>Edit Vehicle - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .edit-section {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-submit {
            background: var(--accent-color);
            color: white;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: #b02a37;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .btn-delete {
            background: #6b7280;
            color: white;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: var(--transition);
        }

        .btn-delete:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }

        .car-image-preview {
            max-width: 150px;
            height: auto;
            border-radius: 8px;
            margin: 0.5rem;
            border: 2px solid transparent;
        }

        .car-image-preview.main-image {
            border-color: var(--accent-color);
        }

        .image-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .image-item {
            position: relative;
        }

        .new-image-field {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .edit-section {
                margin: 1rem;
                padding: 1.5rem;
            }

            .form-control, .form-select {
                font-size: 0.9rem;
            }

            .btn-submit, .btn-cancel, .btn-delete {
                padding: 0.4rem 1.2rem;
                font-size: 0.9rem;
            }

            .car-image-preview {
                max-width: 100px;
            }
        }

        @media (max-width: 576px) {
            .edit-section {
                padding: 1rem;
            }

            .btn-submit, .btn-cancel, .btn-delete {
                padding: 0.3rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<?php 
error_log("Including header.php, connection status: " . ($conn->ping() ? "active" : "closed"));
include 'header.php'; 
?>

<!-- Edit Car Section -->
<section class="edit-section">
    <div class="container">
        <h2 class="text-center mb-4" style="color: var(--primary-color);">Edit Vehicle</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo strpos($message, '✅') === 0 ? 'success' : 'danger'; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($car_id <= 0): ?>
            <!-- Car Selection Form -->
            <div class="text-center p-4 bg-white rounded" style="box-shadow: var(--card-shadow);">
                <h3 class="mb-3">Select a Vehicle to Edit</h3>
                <?php if (empty($cars)): ?>
                    <p class="text-muted">No vehicles available to edit.</p>
                    <a href="admincar_details.php" class="btn btn-primary">Browse Vehicles</a>
                <?php else: ?>
                    <form method="post" action="admin_edit_car.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-3">
                            <select class="form-select" name="select_car" required>
                                <option value="">Select a vehicle</option>
                                <?php foreach ($cars as $car_option): ?>
                                    <option value="<?php echo $car_option['id']; ?>">
                                        <?php echo htmlspecialchars($car_option['brand'] . ' ' . $car_option['model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-submit">Load Vehicle</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif ($car): ?>
            <!-- Edit Car Form -->
            <form method="post" action="admin_edit_car.php" enctype="multipart/form-data">
                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" class="form-control" id="brand" name="brand" value="<?php echo htmlspecialchars($car['brand']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="model" class="form-label">Model</label>
                        <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($car['model']); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="year" class="form-label">Year</label>
                        <input type="number" class="form-control" id="year" name="year" value="<?php echo htmlspecialchars($car['year']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="price" class="form-label">Price (RM)</label>
                        <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($car['price']); ?>" step="0.01" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="available" <?php echo $car['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="reserved" <?php echo $car['status'] === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                            <option value="sold" <?php echo $car['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                            <option value="pending" <?php echo $car['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="rejected" <?php echo $car['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="body_type" class="form-label">Body Type</label>
                        <select class="form-select" id="body_type" name="body_type">
                            <option value="" <?php echo empty($car['body_type']) ? 'selected' : ''; ?>>Select Body Type</option>
                            <?php
                            $body_types = ['Sedan', 'SUV', 'Hatchback', 'Coupe', 'Convertible', 'Wagon', 'Van', 'Truck'];
                            foreach ($body_types as $type) {
                                echo "<option value=\"$type\"" . ($car['body_type'] === $type ? ' selected' : '') . ">$type</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="instalment" class="form-label">Instalment (RM)</label>
                        <input type="number" class="form-control" id="instalment" name="instalment" value="<?php echo htmlspecialchars($car['instalment'] ?? ''); ?>" step="0.01">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="transmission" class="form-label">Transmission</label>
                        <select class="form-select" id="transmission" name="transmission">
                            <option value="" <?php echo empty($car['transmission']) ? 'selected' : ''; ?>>Select Transmission</option>
                            <?php
                            $transmissions = ['Automatic', 'Manual', 'CVT', 'Dual Clutch'];
                            foreach ($transmissions as $trans) {
                                echo "<option value=\"$trans\"" . ($car['transmission'] === $trans ? ' selected' : '') . ">$trans</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="mileage" class="form-label">Mileage (km)</label>
                        <input type="number" class="form-control" id="mileage" name="mileage" value="<?php echo htmlspecialchars($car['mileage'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="color" class="form-label">Color</label>
                        <input type="text" class="form-control" id="color" name="color" value="<?php echo htmlspecialchars($car['color'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="insurance" class="form-label">Insurance (RM)</label>
                    <input type="number" class="form-control" id="insurance" name="insurance" value="<?php echo htmlspecialchars($car['insurance'] ?? ''); ?>" step="0.01">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($car['description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Existing Images</label>
                    <div class="image-container">
                        <?php if (empty($images)): ?>
                            <p class="text-muted">No existing images.</p>
                        <?php else: ?>
                            <?php foreach ($images as $index => $image): ?>
                                <div class="image-item">
                                    <img src="<?php echo htmlspecialchars($image['image']); ?>" alt="Car Image" class="car-image-preview <?php echo $image['is_main'] ? 'main-image' : ''; ?>">
                                    <input type="hidden" name="image_ids[]" value="<?php echo $image['id']; ?>">
                                    <label class="form-check-label me-2">
                                        <input type="checkbox" name="delete_images[]" value="<?php echo $image['id']; ?>"> Delete
                                    </label>
                                    <label class="form-check-label">
                                        <input type="radio" name="main_image" value="<?php echo $image['id']; ?>" <?php echo $image['is_main'] ? 'checked' : ''; ?>> Set as Main
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Add New Images</label>
                    <div id="new-images-container">
                        <div class="new-image-field">
                            <input type="file" class="form-control" name="new_images[]" accept="image/jpeg,image/png">
                            <label class="form-check-label ms-2">
                                <input type="radio" name="main_image" value="new_0" <?php echo empty($images) ? 'checked' : ''; ?>> Set as Main
                            </label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary mt-2" onclick="addImageField()">Add Another Image</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                    <a href="admincar_details.php" class="btn btn-cancel">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-delete" name="delete_car" value="1" onclick="return confirm('Are you sure you want to delete this car? This action cannot be undone.');">
                        <i class="fas fa-trash me-1"></i>Delete Car
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center p-4 bg-white rounded" style="box-shadow: var(--card-shadow);">
                <i class="fas fa-car fa-3x mb-3 text-muted"></i>
                <h3 class="mb-3">No Vehicle Found</h3>
                <p class="text-muted">Please select a valid vehicle to edit.</p>
                <a href="admincar_details.php" class="btn btn-primary">Browse Vehicles</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Footer -->
<?php 
error_log("Including footer.php, connection status: " . ($conn->ping() ? "active" : "closed"));
include 'footer.php'; 
?>

<script>

let newImageIndex = 1;
function addImageField() {
    const container = document.getElementById('new-images-container');
    const newField = document.createElement('div');
    newField.className = 'new-image-field';
    newField.innerHTML = `
        <input type="file" class="form-control" name="new_images[]" accept="image/jpeg,image/png">
        <label class="form-check-label ms-2">
            <input type="radio" name="main_image" value="new_${newImageIndex}"> Set as Main
        </label>
        <button type="button" class="btn btn-delete" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(newField);
    newImageIndex++;
}
</script>
</body>
</html>
<?php
if ($conn->ping()) {
    $conn->close();
    error_log("Database connection closed successfully in admin_edit_car.php");
} else {
    error_log("Database connection already closed or lost in admin_edit_car.php");
}
?>