<?php
session_start();
include 'includes/db.php';


$login_error = '';
$login_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, username, password, role, user_type FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Check if there's a redirect URL in POST data
                if (isset($_POST['redirect_after_login']) && !empty($_POST['redirect_after_login'])) {
                    $redirect_url = $_POST['redirect_after_login'];
                    header("Location: " . $redirect_url);
                } else {
                    // Redirect based on role and user_type
                    if ($user['role'] === 'admin') {
                        $redirect_url = "admin_dashboard.php";
                    } elseif ($user['user_type'] === 'seller') {
                        $redirect_url = "user_dashboard.php";
                    } elseif ($user['user_type'] === 'buyer') {
                        $redirect_url = "index.php?login=success";
                    } else {
                        $redirect_url = "index.php?login=success";
                    }
                    header("Location: " . $redirect_url);
                }
                exit;
                
                // Handle remember me (optional)
                if (isset($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $stmt_remember = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
                    $stmt_remember->bind_param("isssi", $user['id'], $token, $expires, $token, $expires);
                    $stmt_remember->execute();
                    setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
                    $stmt_remember->close();
                }
                
                $stmt->close();
                $login_success = true;
            } else {
                $login_error = 'Incorrect email or password!';
                $stmt->close();
            }
        } else {
            $login_error = 'User not found!';
            $stmt->close();
        }
    } else {
        $login_error = 'Please fill in complete login information!';
    }
}

// Check remember me
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $user['user_id'];
        $stmt_user = $conn->prepare("SELECT username, role, user_type FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $_SESSION['user_id']);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();
        if ($user_data = $user_result->fetch_assoc()) {
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['user_type'] = $user_data['user_type'];
        }
        $stmt_user->close();
    }
    $stmt->close();
}
// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $message = "❌ Database connection failed, please contact the administrator";
}
error_log("Database connection established in index.php");

// Get admin phone
function getAdminPhone($conn) {
    error_log("Entering getAdminPhone");
    if (!$conn->ping()) {
        error_log("Database connection closed in getAdminPhone");
        return '';
    }
    $admin_phone = '';
    $stmt = $conn->prepare("SELECT phone FROM users WHERE role = 'admin' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $admin_phone = $row['phone'];
        } else {
            error_log("No admin found in users table");
        }
        $stmt->close();
    } else {
        error_log("Admin phone query preparation error: " . $conn->error);
    }
    error_log("Exiting getAdminPhone, phone: " . ($admin_phone ?: 'none'));
    return $admin_phone;
}

// Get all vehicle images
function getAllImages($conn, $car_id) {
    error_log("Entering getAllImages, car_id: $car_id");
    if (!$conn->ping()) {
        error_log("Database connection closed in getAllImages, attempting to reconnect");
        $conn = new mysqli("localhost", "root", "", "car_portal");
        if ($conn->connect_error) {
            error_log("Reconnection failed: " . $conn->connect_error);
            return ["Uploads/car.jpg"];
        }
        $conn->set_charset("utf8mb4");
        error_log("getAllImages reconnection successful");
    }
    $defaultImage = ["Uploads/car.jpg"];
    $stmt = $conn->prepare("SELECT image FROM car_images WHERE car_id = ? ORDER BY is_main DESC");
    if (!$stmt) {
        error_log("Image query preparation error for car ID $car_id: " . $conn->error);
        return $defaultImage;
    }
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $imagePath = !empty($row['image']) && file_exists("Uploads/" . trim($row['image'])) 
            ? "Uploads/" . trim($row['image']) 
            : "Uploads/car.jpg";
        $images[] = $imagePath;
        error_log("Image path for car ID $car_id: $imagePath");
    }
    $stmt->close();
    $images = empty($images) ? $defaultImage : $images;
    error_log("Total images for car ID $car_id: " . count($images));
    return $images;
}

// Calculate monthly instalment
function calculateLoanInstalment($price) {
    $interest_rate = 0.03 / 12; // 3% annual rate, converted to monthly
    $loan_term = 60; // 5 years (60 months)
    if ($price <= 0) return 0;
    $monthly_payment = ($price * $interest_rate * pow(1 + $interest_rate, $loan_term)) / (pow(1 + $interest_rate, $loan_term) - 1);
    return round($monthly_payment, 2);
}

// Estimate insurance cost
function estimateInsurance($price) {
    return round($price * 0.05, 2); // Assume 5% of car price
}

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$model = isset($_GET['model']) ? trim($_GET['model']) : '';
$body_type = isset($_GET['body_type']) ? trim($_GET['body_type']) : '';
$transmission = isset($_GET['transmission']) ? trim($_GET['transmission']) : '';
$color = isset($_GET['color']) ? trim($_GET['color']) : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$min_instalment = isset($_GET['min_instalment']) && is_numeric($_GET['min_instalment']) ? floatval($_GET['min_instalment']) : 0;
$max_instalment = isset($_GET['max_instalment']) && is_numeric($_GET['max_instalment']) ? floatval($_GET['max_instalment']) : 0;
$min_insurance = isset($_GET['min_insurance']) && is_numeric($_GET['min_insurance']) ? floatval($_GET['min_insurance']) : 0;
$max_insurance = isset($_GET['max_insurance']) && is_numeric($_GET['max_insurance']) ? floatval($_GET['max_insurance']) : 0;
$min_year = isset($_GET['min_year']) && is_numeric($_GET['min_year']) ? intval($_GET['min_year']) : 0;
$max_year = isset($_GET['max_year']) && is_numeric($_GET['max_year']) ? intval($_GET['max_year']) : 0;
$min_mileage = isset($_GET['min_mileage']) && is_numeric($_GET['min_mileage']) ? intval($_GET['min_mileage']) : 0;
$max_mileage = isset($_GET['max_mileage']) && is_numeric($_GET['max_mileage']) ? intval($_GET['max_mileage']) : 0;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'Newest';
$perPage = 6;
$offset = ($page - 1) * $perPage;

// Dynamic filter options
$brands = $models = $body_types = $transmissions = $colors = [];
$options_query = "SELECT DISTINCT brand, model, body_type, transmission, color FROM cars WHERE status IN ('available', 'reserved')";
if ($conn->ping()) {
    $result = $conn->query($options_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['brand'])) $brands[] = $row['brand'];
            if (!empty($row['model'])) $models[] = $row['model'];
            if (!empty($row['body_type'])) $body_types[] = $row['body_type'];
            if (!empty($row['transmission'])) $transmissions[] = $row['transmission'];
            if (!empty($row['color'])) $colors[] = $row['color'];
        }
        $brands = array_unique($brands);
        $models = array_unique($models);
        $body_types = array_unique($body_types);
        $transmissions = array_unique($transmissions);
        $colors = array_unique($colors);
        sort($brands);
        sort($models);
        sort($body_types);
        sort($transmissions);
        sort($colors);
    } else {
        error_log("Filter options query error: " . $conn->error);
    }
} else {
    error_log("Database connection closed before filter options query");
}

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(c.brand LIKE ? OR c.model LIKE ? OR c.year LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if (!empty($brand)) {
    $where_conditions[] = "c.brand = ?";
    $params[] = $brand;
    $types .= 's';
}

if (!empty($model)) {
    $where_conditions[] = "c.model = ?";
    $params[] = $model;
    $types .= 's';
}

if (!empty($body_type)) {
    $where_conditions[] = "c.body_type = ?";
    $params[] = $body_type;
    $types .= 's';
}

if (!empty($transmission)) {
    $where_conditions[] = "c.transmission = ?";
    $params[] = $transmission;
    $types .= 's';
}

if (!empty($color)) {
    $where_conditions[] = "c.color = ?";
    $params[] = $color;
    $types .= 's';
}

if ($min_price > 0) {
    $where_conditions[] = "c.price >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price > 0) {
    $where_conditions[] = "c.price <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

if ($min_instalment > 0) {
    $where_conditions[] = "c.instalment >= ?";
    $params[] = $min_instalment;
    $types .= 'd';
}

if ($max_instalment > 0) {
    $where_conditions[] = "c.instalment <= ?";
    $params[] = $max_instalment;
    $types .= 'd';
}

if ($min_insurance > 0) {
    $where_conditions[] = "c.insurance >= ?";
    $params[] = $min_insurance;
    $types .= 'd';
}

if ($max_insurance > 0) {
    $where_conditions[] = "c.insurance <= ?";
    $params[] = $max_insurance;
    $types .= 'd';
}

if ($min_year > 0) {
    $where_conditions[] = "c.year >= ?";
    $params[] = $min_year;
    $types .= 'i';
}

if ($max_year > 0) {
    $where_conditions[] = "c.year <= ?";
    $params[] = $max_year;
    $types .= 'i';
}

if ($min_mileage > 0) {
    $where_conditions[] = "c.mileage >= ?";
    $params[] = $min_mileage;
    $types .= 'i';
}

if ($max_mileage > 0) {
    $where_conditions[] = "c.mileage <= ?";
    $params[] = $max_mileage;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE c.status IN (\'available\', \'reserved\') AND ' . implode(' AND ', $where_conditions) : 'WHERE c.status IN (\'available\', \'reserved\')';

// Add sorting
$order_by = 'ORDER BY c.created_at DESC';
if ($sort === 'Price: Low to High') {
    $order_by = 'ORDER BY c.price ASC';
} elseif ($sort === 'Price: High to Low') {
    $order_by = 'ORDER BY c.price DESC';
}

// Define the vehicle query
$full_sql = "
    SELECT c.*, u.username AS seller, 
           CASE 
               WHEN o.id IS NOT NULL AND o.order_status = 'ordered' THEN 'ordered'
               WHEN o.id IS NOT NULL AND o.order_status = 'completed' THEN 'completed'
               ELSE 'available'
           END AS order_status
    FROM cars c 
    JOIN users u ON c.user_id = u.id 
    LEFT JOIN orders o ON c.id = o.car_id AND o.order_status IN ('ordered', 'completed')
    $where_clause 
    $order_by 
    LIMIT ? OFFSET ?";

// Query vehicles with order status
$totalVehicles = 0;
$cars = [];
if (!$conn->ping()) {
    error_log("Database connection closed before vehicle query, attempting to reconnect");
    $conn = new mysqli("localhost", "root", "", "car_portal");
    if ($conn->connect_error) {
        error_log("Reconnection failed: " . $conn->connect_error);
        $message = "❌ Database connection lost, please contact the administrator";
    } else {
        $conn->set_charset("utf8mb4");
        error_log("Reconnection successful");
    }
}

error_log("Executing vehicle query: " . ($full_sql ?? 'Query not defined') . ", parameters: " . json_encode($params));
if ($conn->ping()) {
    $stmt = $conn->prepare($full_sql);
    if ($stmt) {
        $types .= 'ii';
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $cars = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Query preparation error: " . $conn->error);
        $message = "❌ Database query error, please contact the administrator";
    }

    // Get total records
    $count_sql = "SELECT COUNT(*) AS total FROM cars c JOIN users u ON c.user_id = u.id $where_clause";
    $countStmt = $conn->prepare($count_sql);
    if ($countStmt) {
        if (!empty($where_conditions)) {
            $count_types = substr($types, 0, -2);
            $count_params = array_slice($params, 0, count($params) - 2);
            $countStmt->bind_param($count_types, ...$count_params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalVehicles = $countResult->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        error_log("Total records query preparation error: " . $conn->error);
    }
} else {
    error_log("Vehicle query failed: Database connection unavailable");
    $message = "❌ Database connection lost, please contact the administrator";
}

// Fetch platform reviews
$reviews = [];
$reviewMessage = '';
$sql = "
    SELECT r.id, r.comment, r.reply, r.created_at, r.rating, u.username
    FROM car_reviews r
    LEFT JOIN users u ON r.reviewer_id = u.id
    ORDER BY r.created_at DESC
";
if ($conn->ping()) {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    } else {
        error_log("Reviews query error: " . $conn->error);
        $reviewMessage = "❌ Failed to fetch reviews: " . $conn->error;
    }
} else {
    error_log("Database connection closed before reviews query");
    $reviewMessage = "❌ Database connection lost, please contact the administrator";
}

$admin_phone = getAdminPhone($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User Car Portal - Your Car Trading Platform">
    <title>User Car Portal</title>
    <!-- Bootstrap CSS and Font Awesome are loaded in header.php -->
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

        #topCarousel {
            height: 450px;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        #topCarousel .carousel-item {
            height: 450px;
            position: relative;
        }

        #topCarousel .carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .carousel-caption {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4));
            backdrop-filter: blur(5px);
            border-radius: var(--border-radius);
            padding: 2rem;
            bottom: 20%;
            left: 5%;
            right: 5%;
            text-align: left;
            color: white;
            pointer-events: auto;
            z-index: 10;
        }

        .carousel-caption h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .carousel-caption p {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        /* Enhanced Search Section Styles */
        .search-hero-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .search-hero-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .search-hero-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .search-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .search-subtitle {
            color: var(--secondary-color);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .search-input-container {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            font-size: 1.1rem;
            z-index: 2;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 1.1rem;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .search-suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.2s ease;
        }

        .search-suggestion-item:hover {
            background-color: #f8fafc;
        }

        .search-suggestion-item:last-child {
            border-bottom: none;
        }

        .search-submit-btn {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            white-space: nowrap;
        }

        .search-submit-btn:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .quick-filters {
            background: rgba(248, 250, 252, 0.8);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.5);
        }

        .quick-filter {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
        }

        .quick-filter:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-actions {
            border-top: 1px solid rgba(226, 232, 240, 0.5);
            padding-top: 1.5rem;
        }

        .search-results-count .badge {
            font-size: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 50px;
        }

        /* Advanced Filters Styles */
        .advanced-filters-section {
            margin-bottom: 2rem;
        }

        .advanced-filters-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 1rem;
        }

        .advanced-filters-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .advanced-filters-subtitle {
            color: var(--secondary-color);
            margin-bottom: 2rem;
        }

        .filter-group {
            background: rgba(248, 250, 252, 0.8);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.5);
            height: 100%;
        }

        .filter-group-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .filter-fields .form-control,
        .filter-fields .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-fields .form-control:focus,
        .filter-fields .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filter-actions {
            border-top: 1px solid rgba(226, 232, 240, 0.5);
            padding-top: 1.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .search-hero-section {
                padding: 2rem 0;
            }
            
            .search-hero-card {
                padding: 1.5rem;
            }
            
            .search-title {
                font-size: 2rem;
            }
            
            .search-input-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-submit-btn {
                width: 100%;
            }
            
            .quick-filters .row {
                gap: 1rem;
            }
            
            .advanced-filters-card {
                padding: 1.5rem;
            }
            
            .filter-group {
                margin-bottom: 1.5rem;
            }
        }

        .filter-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .filter-section .accordion-button {
            background: #f8fafc;
            border: none;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .filter-section .accordion-button:not(.collapsed) {
            background: var(--primary-color);
            color: white;
        }

        .filter-section .form-control, .filter-section .form-select {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 0.5rem 1rem;
        }

        .filter-section .btn-clear {
            background: var(--secondary-color);
            border: none;
            color: white;
            border-radius: 50px;
        }

        .filter-section .btn-clear:hover {
            background: #475569;
        }

        .vehicle-grid {
            margin-top: 2rem;
        }

        .car-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            height: 100%;
            position: relative;
        }

        .car-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #3b82f6);
            transform: scaleX(0);
            transition: transform 0.3s ease;
            z-index: 1;
        }

        .car-card:hover::before {
            transform: scaleX(1);
        }

        .car-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        /* Card Image Optimization */
        .car-card .carousel img {
            transition: transform 0.3s ease;
        }

        .car-card:hover .carousel img {
            transform: scale(1.05);
        }

        .separate-figure {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
        }

        .separate-figure::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .separate-figure:hover::before {
            left: 100%;
        }

        .carousel {
            height: 220px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        }

        .carousel-track {
            display: flex;
            height: 100%;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-slide {
            min-width: 100%;
            height: 100%;
            flex-shrink: 0;
        }

        .carousel-slide img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .carousel-slide img:hover {
            transform: scale(1.05);
        }

        .carousel-dots {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 15;
            background: rgba(0, 0, 0, 0.5);
            padding: 8px 16px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .carousel-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .carousel-dot.active {
            background: white;
            border-color: var(--primary-color);
            transform: scale(1.2);
        }

        .carousel-dot:hover {
            background: white;
            transform: scale(1.1);
        }


        .status-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 15;
            backdrop-filter: blur(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .status-badge.reserved {
            background: #f59e0b;
        }

        .status-badge.sold {
            background: #dc3545;
        }

        .card-body {
            padding: 1.5rem;
            position: relative;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.75rem;
            line-height: 1.3;
            transition: color 0.3s ease;
        }

        .car-card:hover .card-title {
            color: var(--primary-color);
        }

        .card-text {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        /* Card Info Icons */
        .card-body .text-muted i {
            color: var(--primary-color);
            width: 16px;
            text-align: center;
        }

        /* Card Tags Styling */
        .price-tag, .loan-tag, .insurance-tag {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0.5rem 0;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .price-tag:last-child, .loan-tag:last-child, .insurance-tag:last-child {
            border-bottom: none;
        }

        .car-card:hover .price-tag,
        .car-card:hover .loan-tag,
        .car-card:hover .insurance-tag {
            color: var(--primary-hover);
            transform: translateX(5px);
        }

        /* Status Badge Enhancement */
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .car-card:hover .status-badge {
            transform: scale(1.05);
        }

        /* Card Loading State */
        .car-card-loading {
            position: relative;
            overflow: hidden;
        }

        .car-card-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: card-loading 1.5s infinite;
            z-index: 1;
        }

        @keyframes card-loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Card Grid Enhancement */
        .vehicle-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        /* Card Image Overlay */
        .car-card .carousel {
            position: relative;
        }

        .car-card .carousel::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.1));
            pointer-events: none;
        }

        /* Card Action Buttons */
        .car-card .btn-professional {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .car-card .btn-professional::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .car-card .btn-professional:hover::before {
            left: 100%;
        }

        .btn {
            border-radius: 50px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--accent-color), #059669);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #10b981);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .pagination .page-link {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-color);
            border: 1px solid #e2e8f0;
            margin: 0 2px;
            transition: var(--transition);
        }

        .pagination .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .no-results i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .reviews-section {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .review-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background-color: #f8fafc;
        }

        .review-card h6 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .review-card .rating {
            color: #f59e0b;
            margin-bottom: 0.5rem;
        }

        .review-card .comment {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .review-card .reply {
            background: #e2e8f0;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .review-card .no-reply {
            color: #94a3b8;
            font-style: italic;
        }

        .review-card .date {
            color: var(--secondary-color);
            font-size: 0.875rem;
        }

        .faq-section {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .faq-section .accordion-button {
            background: #f8fafc;
            border: none;
            font-weight: 600;
            color: var(--secondary-color);
            padding: 1.25rem;
        }

        .faq-section .accordion-button:not(.collapsed) {
            background: var(--primary-color);
            color: white;
        }

        .faq-section .accordion-button:focus {
            box-shadow: none;
            border: none;
        }

        .faq-section .accordion-body {
            background: #f8fafc;
            color: #1e293b;
            font-size: 1rem;
            line-height: 1.7;
        }

        .faq-section .accordion-item {
            border: none;
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .carousel-caption h2 {
                font-size: 1.8rem;
            }
            .carousel-caption p {
                font-size: 1rem;
            }
            .separate-figure {
                height: 45px;
                font-size: 0.9rem;
            }
            .carousel {
                height: 180px;
            }
            .reviews-section, .filter-section, .faq-section {
                margin: 1rem;
                padding: 1.5rem;
            }
            .review-card {
                padding: 1rem;
            }
        }

                /* Login Modal Enhanced Styles */
        .nav-tabs-custom {
            border: none;
            background: transparent;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            padding: 0.75rem 1.5rem;
            color: var(--secondary-color);
            font-weight: 600;
            border-radius: 50px 50px 0 0;
            margin-right: 0.25rem;
            background: #f8fafc;
            transition: var(--transition);
        }

        .nav-tabs-custom .nav-link:hover {
            color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        .tab-pane {
            background: white;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            overflow: hidden;
        }

        /* Modal Fix Styles */
        .modal-backdrop {
            backdrop-filter: blur(5px) !important;
            background-color: rgba(0, 0, 0, 0.4) !important;
            z-index: 1040 !important;
        }

        .modal {
            z-index: 1050 !important;
        }

        .modal .modal-dialog {
            z-index: 1051 !important;
        }

        /* Force cleanup of extra backdrops */
        .modal-backdrop ~ .modal-backdrop {
            display: none !important;
        }

        /* Fix nested modals */
        .modal.show .modal {
            z-index: 1060 !important;
        }

        .modal.show .modal .modal-dialog {
            z-index: 1061 !important;
        }

        /* Ensure click events work properly */
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important; /* Remove possible extra scrollbar */
        }

        /* Fix Terms Modal */
        #termsModal .modal-footer .btn {
            transition: var(--transition);
        }

        #termsModal .modal-footer .btn:hover {
            transform: translateY(-1px);
        }

        .modal-content {
            animation: modalSlideIn 0.4s ease-out;
            border: none;
            border-radius: var(--border-radius);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        #loginModal .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        #loginModal .input-group:focus-within .form-control,
        #loginModal .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        #loginModal .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        @media (max-width: 576px) {
            .nav-tabs-custom .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            
            #loginModal .modal-dialog {
                margin: 0.5rem;
            }
            .carousel-caption {
                bottom: 10%;
                padding: 1rem;
            }
            .carousel-caption h2 {
                font-size: 1.5rem;
            }
            .card-body {
                padding: 1rem;
            }
            .btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
            .price-tag, .loan-tag, .insurance-tag {
                font-size: 1rem;
            }
            
            /* Card Grid Responsive */
            .vehicle-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1rem;
            }
            
            .vehicle-grid .col-lg-4 {
                margin-bottom: 1.5rem;
            }
            
            /* Card Image Responsive */
            .car-card .carousel {
                height: 200px;
            }
            
            /* Card Title Responsive */
            .card-title {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .card-text {
                font-size: 0.85rem;
                margin-bottom: 0.75rem;
            }
            
            /* Card Tags Responsive */
            .price-tag, .loan-tag, .insurance-tag {
                font-size: 0.95rem;
                margin: 0.4rem 0;
                padding: 0.4rem 0;
            }
            
            /* Status Badge Responsive */
            .status-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.7rem;
                top: 8px;
                right: 8px;
            }
            .faq-section .accordion-button {
                font-size: 0.95rem;
                padding: 1rem;
            }
            .faq-section .accordion-body {
                font-size: 0.9rem;
            }
            
            /* Mobile Card Optimizations */
            .vehicle-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .car-card {
                margin-bottom: 1rem;
            }
            
            .car-card .carousel {
                height: 180px;
            }
            
            .card-body {
                padding: 0.75rem;
            }
            
            .card-title {
                font-size: 0.95rem;
                margin-bottom: 0.5rem;
                line-height: 1.2;
            }
            
            .card-text {
                font-size: 0.8rem;
                margin-bottom: 0.75rem;
                line-height: 1.4;
            }
            
            /* Mobile Card Info */
            .card-body .d-flex {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .card-body .text-muted {
                font-size: 0.75rem;
            }
            
            /* Mobile Card Tags */
            .price-tag, .loan-tag, .insurance-tag {
                font-size: 0.85rem;
                margin: 0.3rem 0;
                padding: 0.3rem 0;
            }
            
            /* Mobile Status Badge */
            .status-badge {
                padding: 0.3rem 0.6rem;
                font-size: 0.65rem;
                top: 6px;
                right: 6px;
            }
            
            /* Mobile Button */
            .btn-professional {
                padding: 0.75rem;
                font-size: 0.85rem;
                margin-top: 0.75rem;
            }
            
            /* Mobile Card Hover Effects */
            .car-card:hover {
                transform: translateY(-4px);
            }
            
            .car-card:hover .price-tag,
            .car-card:hover .loan-tag,
            .car-card:hover .insurance-tag {
                transform: translateX(3px);
            }
            
            /* Mobile Separate Figure */
            .separate-figure {
                height: 45px;
                font-size: 0.85rem;
            }
        }
        
        /* Professional Alert Dropdown Styles */
        .professional-alert {
            position: relative;
        }
        
        .professional-alert .dropdown {
            z-index: 1050;
        }
        
        .professional-alert .dropdown-menu {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border-radius: var(--border-radius);
            padding: 0.5rem;
            min-width: 180px;
        }
        
        .professional-alert .dropdown-item {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: var(--transition);
            color: var(--secondary-color);
        }
        
        .professional-alert .dropdown-item:hover {
            background: rgba(30, 64, 175, 0.08);
            color: var(--primary-color);
            transform: translateX(4px);
        }
        
        .professional-alert .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        .professional-alert .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.3);
            color: var(--primary-color);
            background: rgba(255, 255, 255, 0.1);
        }
        
        .professional-alert .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: var(--primary-color);
        }
    </style>
</head>
<body>
<!-- Header -->
<?php 
error_log("Including header.php, connection status: " . ($conn->ping() ? "active" : "closed"));
include 'header.php'; 
?>



<!-- Top Carousel -->
<div id="topCarousel" class="carousel slide mb-5" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#topCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#topCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#topCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="Uploads/car1.jpg" class="d-block w-100" alt="Banner 1" style="height: 400px; object-fit: cover;">
            <div class="carousel-caption d-none d-md-block">
                <h2>Discover Your Dream Car</h2>
                <p>Browse our admin-approved vehicles at affordable prices.</p>
                <a href="#car-list" class="btn btn-primary btn-lg">View Now</a>
            </div>
        </div>
        <div class="carousel-item">
            <img src="Uploads/car2.jpg" class="d-block w-100" alt="Banner 2" style="height: 400px; object-fit: cover;">
            <div class="carousel-caption d-none d-md-block">
                <h2>Special Offers</h2>
                <p>Enjoy exclusive discounts on approved vehicles for a limited time!</p>
                <a href="#car-list" class="btn btn-primary btn-lg">Learn More</a>
            </div>
        </div>
        <div class="carousel-item">
            <img src="Uploads/car3.jpg" class="d-block w-100" alt="Banner 3" style="height: 400px; object-fit: cover;">
            <div class="carousel-caption d-none d-md-block">
                <h2>Professional Support Team</h2>
                <p>Contact us for a seamless car buying experience.</p>
                <a href="contact.php" class="btn btn-primary btn-lg" style="z-index: 20;">Contact Us</a>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#topCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#topCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>

<!-- Enhanced Search Section -->
<section class="search-hero-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="search-hero-card">
                    <div class="search-header text-center mb-4">
                        <h2 class="search-title">
                            <i class="fas fa-search me-3"></i>Find Your Perfect Vehicle
                        </h2>
                        <p class="search-subtitle">Discover amazing cars with our advanced search and filter system</p>
                    </div>
                    
                    <!-- Main Search Form -->
                    <form class="enhanced-search-form" method="get" action="index.php">
                        <div class="search-input-container">
                            <div class="search-input-wrapper">
                                <div class="search-icon">
                    <i class="fas fa-search"></i>
                                </div>
                                <input 
                                    type="search" 
                                    name="search" 
                                    class="search-input" 
                                    placeholder="Search by brand, model, year, or any keyword..." 
                                    value="<?php echo htmlspecialchars($search); ?>" 
                                    aria-label="Search vehicles"
                                    autocomplete="off"
                                    id="mainSearchInput"
                                >
                                <div class="search-suggestions" id="searchSuggestions"></div>
                            </div>
                            <button type="submit" class="search-submit-btn">
                                <i class="fas fa-search me-2"></i>Search
                </button>
                        </div>
                        
                        <!-- Quick Filters -->
                        <div class="quick-filters mt-4">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <select name="brand" class="form-select quick-filter">
                                        <option value="">All Brands</option>
                                        <?php foreach ($brands as $b): ?>
                                            <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $brand === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="body_type" class="form-select quick-filter">
                                        <option value="">All Types</option>
                                        <option value="Sedan" <?php echo $body_type === 'Sedan' ? 'selected' : ''; ?>>Sedan</option>
                                        <option value="SUV" <?php echo $body_type === 'SUV' ? 'selected' : ''; ?>>SUV</option>
                                        <option value="Hatchback" <?php echo $body_type === 'Hatchback' ? 'selected' : ''; ?>>Hatchback</option>
                                        <option value="Coupe" <?php echo $body_type === 'Coupe' ? 'selected' : ''; ?>>Coupe</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="transmission" class="form-select quick-filter">
                                        <option value="">All Transmissions</option>
                                        <option value="Automatic" <?php echo $transmission === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                                        <option value="Manual" <?php echo $transmission === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="sort" class="form-select quick-filter">
                                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                        <option value="mileage_low" <?php echo $sort === 'mileage_low' ? 'selected' : ''; ?>>Mileage: Low to High</option>
                                        <option value="mileage_high" <?php echo $sort === 'mileage_high' ? 'selected' : ''; ?>>Mileage: High to Low</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Search Actions -->
                        <div class="search-actions mt-4">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="false">
                                        <i class="fas fa-sliders-h me-2"></i>Advanced Filters
                                    </button>
                                    <?php if (!empty($search) || !empty($brand) || !empty($model) || !empty($body_type) || !empty($transmission) || !empty($color) || $min_price > 0 || $max_price > 0 || $min_instalment > 0 || $max_instalment > 0 || $min_insurance > 0 || $max_insurance > 0 || $min_year > 0 || $max_year > 0 || $min_mileage > 0 || $max_mileage > 0): ?>
                                        <a href="index.php" class="btn btn-outline-secondary ms-2">
                                            <i class="fas fa-times me-2"></i>Clear Filters
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="search-results-count">
                                        <?php if ($totalVehicles > 0): ?>
                                            <span class="badge bg-primary fs-6">
                                                <i class="fas fa-car me-1"></i><?php echo $totalVehicles; ?> vehicles found
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
            </div>
        </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Advanced Filters Section -->
<section class="advanced-filters-section">
    <div class="container">
        <div class="collapse" id="advancedFilters">
            <div class="advanced-filters-card">
                <div class="advanced-filters-header">
                    <h4 class="advanced-filters-title">
                        <i class="fas fa-sliders-h me-2"></i>Advanced Search Filters
                    </h4>
                    <p class="advanced-filters-subtitle">Refine your search with detailed criteria</p>
                </div>
                
                <form method="get" action="index.php" class="advanced-filters-form">
                    <!-- Preserve search query -->
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    
                    <div class="row g-4">
                        <!-- Vehicle Details -->
                        <div class="col-lg-4">
                            <div class="filter-group">
                                <h5 class="filter-group-title">
                                    <i class="fas fa-car me-2"></i>Vehicle Details
                                </h5>
                                <div class="filter-fields">
                                    <div class="mb-3">
                                    <label class="form-label">Brand</label>
                                    <select name="brand" class="form-select">
                                        <option value="">All Brands</option>
                                        <?php foreach ($brands as $b): ?>
                                            <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $brand === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                    <div class="mb-3">
                                    <label class="form-label">Model</label>
                                    <select name="model" class="form-select">
                                        <option value="">All Models</option>
                                        <?php foreach ($models as $m): ?>
                                            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $model === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                    <div class="mb-3">
                                    <label class="form-label">Body Type</label>
                                    <select name="body_type" class="form-select">
                                        <option value="">All Body Types</option>
                                        <?php foreach ($body_types as $bt): ?>
                                            <option value="<?php echo htmlspecialchars($bt); ?>" <?php echo $body_type === $bt ? 'selected' : ''; ?>><?php echo htmlspecialchars($bt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                    <div class="mb-3">
                                    <label class="form-label">Transmission</label>
                                    <select name="transmission" class="form-select">
                                        <option value="">All Transmissions</option>
                                        <?php foreach ($transmissions as $t): ?>
                                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $transmission === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                    <div class="mb-3">
                                    <label class="form-label">Color</label>
                                    <select name="color" class="form-select">
                                        <option value="">All Colors</option>
                                        <?php foreach ($colors as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $color === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Price & Financial -->
                        <div class="col-lg-4">
                            <div class="filter-group">
                                <h5 class="filter-group-title">
                                    <i class="fas fa-dollar-sign me-2"></i>Price & Financial
                                </h5>
                                <div class="filter-fields">
                                    <div class="mb-3">
                                    <label class="form-label">Price Range (RM)</label>
                                    <div class="row g-2">
                                            <div class="col-6">
                                                <input type="number" name="min_price" class="form-control" placeholder="Min Price" value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                                        </div>
                                            <div class="col-6">
                                                <input type="number" name="max_price" class="form-control" placeholder="Max Price" value="<?php echo $max_price > 0 ? $max_price : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                    <div class="mb-3">
                                        <label class="form-label">Monthly Instalment (RM)</label>
                                    <div class="row g-2">
                                            <div class="col-6">
                                                <input type="number" name="min_instalment" class="form-control" placeholder="Min Instalment" value="<?php echo $min_instalment > 0 ? $min_instalment : ''; ?>">
                                        </div>
                                            <div class="col-6">
                                                <input type="number" name="max_instalment" class="form-control" placeholder="Max Instalment" value="<?php echo $max_instalment > 0 ? $max_instalment : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                    <div class="mb-3">
                                        <label class="form-label">Insurance (RM/year)</label>
                                    <div class="row g-2">
                                            <div class="col-6">
                                                <input type="number" name="min_insurance" class="form-control" placeholder="Min Insurance" value="<?php echo $min_insurance > 0 ? $min_insurance : ''; ?>">
                                        </div>
                                            <div class="col-6">
                                                <input type="number" name="max_insurance" class="form-control" placeholder="Max Insurance" value="<?php echo $max_insurance > 0 ? $max_insurance : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vehicle Condition -->
                        <div class="col-lg-4">
                            <div class="filter-group">
                                <h5 class="filter-group-title">
                                    <i class="fas fa-cogs me-2"></i>Vehicle Condition
                                </h5>
                                <div class="filter-fields">
                                    <div class="mb-3">
                                    <label class="form-label">Year Range</label>
                                    <div class="row g-2">
                                            <div class="col-6">
                                                <input type="number" name="min_year" class="form-control" placeholder="Min Year" value="<?php echo $min_year > 0 ? $min_year : ''; ?>">
                                        </div>
                                            <div class="col-6">
                                                <input type="number" name="max_year" class="form-control" placeholder="Max Year" value="<?php echo $max_year > 0 ? $max_year : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                    <div class="mb-3">
                                    <label class="form-label">Mileage Range (km)</label>
                                    <div class="row g-2">
                                            <div class="col-6">
                                                <input type="number" name="min_mileage" class="form-control" placeholder="Min Mileage" value="<?php echo $min_mileage > 0 ? $min_mileage : ''; ?>">
                                        </div>
                                            <div class="col-6">
                                                <input type="number" name="max_mileage" class="form-control" placeholder="Max Mileage" value="<?php echo $max_mileage > 0 ? $max_mileage : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                    <div class="mb-3">
                                        <label class="form-label">Sort By</label>
                                        <select name="sort" class="form-select">
                                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                            <option value="mileage_low" <?php echo $sort === 'mileage_low' ? 'selected' : ''; ?>>Mileage: Low to High</option>
                                            <option value="mileage_high" <?php echo $sort === 'mileage_high' ? 'selected' : ''; ?>>Mileage: High to Low</option>
                                        </select>
                            </div>
                            </div>
                    </div>
                </div>
            </div>
                    
                    <!-- Filter Actions -->
                    <div class="filter-actions mt-4">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-search me-2"></i>Apply Advanced Filters
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Clear All Filters
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- Total Vehicles -->
    <?php if (isset($message)): ?>
        <div class="alert alert-danger mt-3" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <div class="row justify-content-between align-items-center mb-4">
        <div class="col-md-6">
            <p class="mb-0 text-muted">
                <?php
                if ($totalVehicles > 0) {
                    echo "Found <strong class='text-primary'>$totalVehicles</strong> vehicles";
                    if (!empty($search) || !empty($brand) || !empty($model) || !empty($body_type) || !empty($transmission) || !empty($color) || $min_price > 0 || $max_price > 0 || $min_instalment > 0 || $max_instalment > 0 || $min_insurance > 0 || $max_insurance > 0 || $min_year > 0 || $max_year > 0 || $min_mileage > 0 || $max_mileage > 0) {
                        echo " (Filtered)";
                    }
                } else {
                    echo "No vehicles found";
                }
                ?>
            </p>
        </div>
        <div class="col-md-6 text-end">
            <form id="sortForm" method="get" action="index.php">
                <select class="form-select w-auto d-inline-block" name="sort" onchange="this.form.submit()" style="max-width: 200px;">
                    <option value="Newest" <?php echo $sort === 'Newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="Price: Low to High" <?php echo $sort === 'Price: Low to High' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="Price: High to Low" <?php echo $sort === 'Price: High to Low' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
                <?php foreach ($_GET as $key => $value) {
                    if ($key !== 'sort') {
                        echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                    }
                } ?>
            </form>
        </div>
    </div>

    <!-- Vehicle List -->
    <div class="row vehicle-grid" id="car-list">
        <?php if (!empty($cars)): ?>
            <?php foreach ($cars as $index => $row): ?>
                <?php
                $images = getAllImages($conn, $row['id']);
                $monthly_instalment = $row['instalment'] ? $row['instalment'] : calculateLoanInstalment($row['price']);
                $insurance_cost = $row['insurance'] ? $row['insurance'] : estimateInsurance($row['price']);
                $whatsapp_message = urlencode("I would like to inquire about the {$row['brand']} {$row['model']} (Year: {$row['year']}, Price: RM {$row['price']}, Monthly Instalment: RM " . number_format($monthly_instalment, 2) . ", Insurance: RM " . number_format($insurance_cost, 2) . "/year)");
                $whatsapp_link = $admin_phone ? "https://wa.me/" . str_replace(['+', ' '], '', $admin_phone) . "?text=$whatsapp_message" : "#";
                $link_class = $admin_phone && $row['order_status'] === 'available' ? '' : 'disabled';
                $description = htmlspecialchars(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : '');
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="car-card h-100">
                        <figure class="separate-figure">
                            <i class="fas fa-eye me-2"></i>
                            <figcaption>View Details</figcaption>
                        </figure>
                        <div class="position-relative">
                            <?php if ($row['order_status'] === 'ordered'): ?>
                                <span class="status-badge reserved">Reserved</span>
                            <?php elseif ($row['order_status'] === 'completed'): ?>
                                <span class="status-badge sold">Sold</span>
                            <?php endif; ?>
                            <div class="carousel" data-carousel-index="<?php echo $index; ?>">
                                <div class="carousel-track" data-images="<?php echo json_encode($images); ?>">
                                    <?php foreach ($images as $image): ?>
                                        <div class="carousel-slide">
                                            <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?>" loading="lazy">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="carousel-dots">
                                    <?php foreach ($images as $i => $image): ?>
                                        <span class="carousel-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-slide="<?php echo $i; ?>"></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['brand'] . " " . $row['model']); ?></h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted"><i class="fas fa-calendar me-1"></i><?php echo htmlspecialchars($row['year']); ?></span>
                                <span class="text-muted"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($row['seller']); ?></span>
                            </div>
                            <p class="card-text text-muted"><?php echo $description; ?></p>
                            <div class="price-tag">
                                <i class="fas fa-tag me-1"></i>RM <?php echo number_format($row['price'], 0); ?>
                            </div>
                            <div class="loan-tag">
                                <i class="fas fa-money-check-alt me-1"></i>Monthly: RM <?php echo number_format($monthly_instalment, 2); ?>
                            </div>
                            <div class="insurance-tag">
                                <i class="fas fa-shield-alt me-1"></i>Est. Insurance: RM <?php echo number_format($insurance_cost, 2); ?> / Year
                            </div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="car_detail.php?id=<?php echo $row['id']; ?>" class="btn-professional w-100 mb-2">
                            <?php else: ?>
                                <a href="#" class="btn-professional w-100 mb-2" onclick="redirectToLogin('car_detail.php?id=<?php echo $row['id']; ?>'); return false;">
                            <?php endif; ?>
                                <i class="fas fa-eye me-1"></i>View Details
                            </a>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user' && $row['order_status'] === 'available'): ?>
                                <a href="<?php echo htmlspecialchars($whatsapp_link); ?>" class="btn btn-success w-100 <?php echo $link_class; ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-whatsapp me-1"></i>Contact Us
                                </a>
                            <?php elseif (!isset($_SESSION['user_id']) && $row['order_status'] === 'available'): ?>
                                <button class="btn btn-success w-100" onclick="redirectToLogin('index.php'); return false;">
                                    <i class="fab fa-whatsapp me-1"></i>Contact Us
                                </button>
                            <?php elseif ($row['order_status'] === 'ordered'): ?>
                                <button class="btn btn-success w-100 disabled" disabled>
                                    <i class="fab fa-whatsapp me-1"></i>Vehicle Reserved
                                </button>
                            <?php elseif ($row['order_status'] === 'completed'): ?>
                                <button class="btn btn-success w-100 disabled" disabled>
                                    <i class="fab fa-whatsapp me-1"></i>Vehicle Sold
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-success w-100" onclick="alert('Please log in to contact us');">
                                    <i class="fab fa-whatsapp me-1"></i>Contact Us
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="no-results">
                    <i class="fas fa-car-side fa-3x mb-3"></i>
                    <h3 class="mb-3">No Vehicles Available</h3>
                    <p class="text-muted">
                        <?php echo !empty($search) || !empty($brand) || !empty($model) || !empty($body_type) || !empty($transmission) || !empty($color) || $min_price > 0 || $max_price > 0 || $min_instalment > 0 || $max_instalment > 0 || $min_insurance > 0 || $max_insurance > 0 || $min_year > 0 || $max_year > 0 || $min_mileage > 0 || $max_mileage > 0 
                            ? "No vehicles match your filter criteria. Please adjust the filters or clear them."
                            : "No vehicles have been approved by the administrator yet. Please check back later."; ?>
                    </p>
                    <a href="index.php" class="btn btn-primary">Clear Filters</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalVehicles > $perPage): ?>
        <nav aria-label="Vehicle pagination" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php
                $totalPages = ceil($totalVehicles / $perPage);
                $prevDisabled = $page <= 1 ? 'disabled' : '';
                $nextDisabled = $page >= $totalPages ? 'disabled' : '';
                $queryParams = array_merge($_GET, ['page' => '']);
                unset($queryParams['page']);
                $baseUrl = 'index.php?' . http_build_query($queryParams);
                ?>
                <li class="page-item <?php echo $prevDisabled; ?>">
                    <a class="page-link" href="<?php echo $baseUrl . '&page=' . ($page - 1); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $baseUrl . '&page=' . $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $nextDisabled; ?>">
                    <a class="page-link" href="<?php echo $baseUrl . '&page=' . ($page + 1); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- Reviews Section -->
    <section class="reviews-section">
        <h2 class="text-center mb-4" style="color: var(--primary-color);">User Reviews</h2>
        <?php if ($reviewMessage): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($reviewMessage); ?></div>
        <?php endif; ?>
        <?php if ($reviews): ?>
            <?php foreach ($reviews as $row): ?>
                <div class="review-card">
                    <h6><?php echo htmlspecialchars($row['username'] ?: 'Anonymous User'); ?></h6>
                    <div class="rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= $row['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="comment"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></p>
                    <?php if ($row['reply']): ?>
                        <div class="reply">
                            <strong>Admin Reply:</strong> <?php echo htmlspecialchars($row['reply']); ?>
                        </div>
                    <?php else: ?>
                        <p class="no-reply">No admin reply yet</p>
                    <?php endif; ?>
                    <p class="date">
                        <?php 
                        $date = new DateTime($row['created_at']);
                        echo $date->format('Y-m-d H:i');
                        ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Reviews Yet</h4>
                <p class="text-muted">Be the first to share your feedback!</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <h2 class="text-center mb-4" style="color: var(--primary-color);">Frequently Asked Questions</h2>
        <div class="accordion" id="faqAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="faq1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1" aria-expanded="false" aria-controls="faqCollapse1">
                        <i class="fas fa-car me-2"></i> How do I list my vehicle for sale on the platform?
                    </button>
                </h2>
                <div id="faqCollapse1" class="accordion-collapse collapse" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Log in as a seller, navigate to the "Sell Your Vehicle" page, and fill in the vehicle details and photos. Your listing will be reviewed and approved by our team within 24 hours.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faq2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                        <i class="fas fa-car me-2"></i> How can I ensure the vehicle's condition is reliable?
                    </button>
                </h2>
                <div id="faqCollapse2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        All listed vehicles undergo a basic admin inspection. You can arrange a detailed inspection or test drive with the seller via WhatsApp to verify the condition.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faq3">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                        <i class="fas fa-money-check-alt me-2"></i> How are monthly instalments estimated?
                    </button>
                </h2>
                <div id="faqCollapse3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Unless specified by the seller, estimates use a 3% annual interest rate over a 5-year term. Please contact the seller or bank for exact terms.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faq4">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse4" aria-expanded="false" aria-controls="faqCollapse4">
                        <i class="fas fa-shield-alt me-2"></i> Do I need insurance before driving the vehicle?
                    </button>
                </h2>
                <div id="faqCollapse4" class="accordion-collapse collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Yes, insurance is required before driving. Please arrange insurance with your provider upon purchase completion.
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="all_faqs.php" class="btn btn-primary">View All FAQs</a>
        </div>
    </section>
</div>

<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--border-radius); box-shadow: var(--card-shadow);">
            <!-- Tab Switch -->
            <div class="modal-header border-0 pb-0">
                <ul class="nav nav-tabs nav-tabs-custom border-0 flex-fill" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-tab-pane" type="button" role="tab">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-tab-pane" type="button" role="tab">
                            <i class="fas fa-user-plus me-1"></i>Register
                        </button>
                    </li>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0">
                <div class="tab-content" id="authTabContent">
                    <!-- Login Tab -->
                    <div class="tab-pane fade show active" id="login-tab-pane" role="tabpanel">
                        <div class="p-4">
                            <?php if ($login_error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($login_error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form id="loginForm" method="POST" action="">
                                <input type="hidden" name="login_action" value="1">
                                <input type="hidden" name="return_url" value="<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                                <input type="hidden" name="redirect_after_login" id="redirect_after_login" value="">
                                
                                <div class="mb-3">
                                    <label for="loginEmail" class="form-label fw-semibold">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control" id="loginEmail" name="email" 
                                               placeholder="Enter your email address" required 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="loginPassword" class="form-label fw-semibold">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" class="form-control" id="loginPassword" name="password" 
                                               placeholder="Enter your password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword" style="border-left: none;">
                                            <i class="fas fa-eye" id="loginPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                                    <label class="form-check-label" for="rememberMe">Remember Me</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3 fw-semibold" style="border-radius: 50px;">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login Now
                                </button>
                                
                                <div class="text-center">
                                    <small class="text-muted">Don’t have an account?</small>
                                    <button type="button" class="btn btn-link p-0 text-primary fw-semibold" data-bs-toggle="tab" data-bs-target="#register-tab-pane">
                                        Register Now
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Register Tab -->
                    <div class="tab-pane fade" id="register-tab-pane" role="tabpanel">
                        <div class="p-4">
                            <form id="registerForm" method="POST" action="register.php">
                                <input type="hidden" name="return_url" value="<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="regFirstName" class="form-label fw-semibold">First Name</label>
                                            <input type="text" class="form-control" id="regFirstName" name="first_name" 
                                                   placeholder="Enter your first name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="regLastName" class="form-label fw-semibold">Last Name</label>
                                            <input type="text" class="form-control" id="regLastName" name="last_name" 
                                                   placeholder="Enter your last name" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="regEmail" class="form-label fw-semibold">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control" id="regEmail" name="email" 
                                               placeholder="Enter your email address" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="regPhone" class="form-label fw-semibold">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone text-muted"></i></span>
                                        <input type="tel" class="form-control" id="regPhone" name="phone" 
                                               placeholder="Enter your phone number" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="regPassword" class="form-label fw-semibold">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                                <input type="password" class="form-control" id="regPassword" name="password" 
                                                       placeholder="At least 8 characters" required minlength="8">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleRegPassword" style="border-left: none;">
                                                    <i class="fas fa-eye" id="regPasswordIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="regConfirmPassword" class="form-label fw-semibold">Confirm Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                                <input type="password" class="form-control" id="regConfirmPassword" name="confirm_password" 
                                                       placeholder="Re-enter your password" required>
                                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" style="border-left: none;">
                                                    <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="regUserType" class="form-label fw-semibold">User Type</label>
                                    <select class="form-select" id="regUserType" name="user_type" required>
                                        <option value="">Please select user type</option>
                                        <option value="buyer">Buyer (Purchase Vehicles)</option>
                                        <option value="seller">Seller (Sell Vehicles)</option>
                                    </select>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="regTerms" name="terms" required>
                                    <label class="form-check-label" for="regTerms">
                                        I have read and agree to the
                                        <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#termsModal">
                                            Terms of Service
                                        </a>
                                </label>
                            </div>
                                
                                <button type="submit" class="btn btn-success w-100 mb-3 fw-semibold" style="border-radius: 50px;">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                                
                                <div class="text-center">
                                    <small class="text-muted">Already have an account?</small>
                                    <button type="button" class="btn btn-link p-0 text-primary fw-semibold" data-bs-toggle="tab" data-bs-target="#login-tab-pane">
                                        Login Now
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms of Service Modal -->
<!-- Terms of Service Modal - Fixed Version -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">
                    <i class="fas fa-file-contract me-2"></i>Terms of Service
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                <h6 class="fw-bold mb-3">Terms of Service</h6>
                <p class="small">Welcome to PrimeAuto Portal! By using our service, you agree to the following terms:</p>
                <ul class="small">
                    <li>You must be 18 years or older to use this service</li>
                    <li>All vehicle information must be accurate and truthful</li>
                    <li>Posting false or fraudulent information is prohibited</li>
                    <li>We are not liable for transaction disputes</li>
                    <li>Users must comply with local laws and regulations</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">I've Read</button>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php 
error_log("Including footer.php, connection status: " . ($conn->ping() ? "active" : "closed"));
include 'footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing components...');

    // Enhanced Search Functionality
    const searchInput = document.getElementById('mainSearchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');
    const quickFilters = document.querySelectorAll('.quick-filter');
    
    // Search suggestions data (you can populate this from your database)
    const searchSuggestionsData = [
        'Toyota Camry', 'Honda Civic', 'BMW 3 Series', 'Mercedes C-Class',
        'Audi A4', 'Nissan Altima', 'Hyundai Elantra', 'Ford Focus',
        'Chevrolet Malibu', 'Kia Optima', 'Mazda 3', 'Subaru Impreza',
        'Volkswagen Jetta', 'Lexus IS', 'Infiniti Q50', 'Acura TLX',
        'Genesis G70', 'Cadillac ATS', 'Lincoln MKZ', 'Buick Regal',
        'Sedan', 'SUV', 'Hatchback', 'Coupe', 'Convertible',
        'Automatic', 'Manual', '2020', '2021', '2022', '2023', '2024'
    ];
    
    // Search input functionality
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                hideSuggestions();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                showSuggestions(query);
            }, 300);
        });
        
        searchInput.addEventListener('focus', function() {
            if (this.value.length >= 2) {
                showSuggestions(this.value.toLowerCase().trim());
            }
        });
        
        searchInput.addEventListener('blur', function() {
            // Delay hiding to allow clicking on suggestions
            setTimeout(() => {
                hideSuggestions();
            }, 200);
        });
        
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideSuggestions();
            }
        });
    }
    
    function showSuggestions(query) {
        if (!searchSuggestions) return;
        
        const filteredSuggestions = searchSuggestionsData.filter(item => 
            item.toLowerCase().includes(query)
        ).slice(0, 8); // Limit to 8 suggestions
        
        if (filteredSuggestions.length === 0) {
            hideSuggestions();
            return;
        }
        
        searchSuggestions.innerHTML = filteredSuggestions.map(suggestion => 
            `<div class="search-suggestion-item" data-suggestion="${suggestion}">
                <i class="fas fa-search me-2 text-muted"></i>${suggestion}
            </div>`
        ).join('');
        
        searchSuggestions.style.display = 'block';
        
        // Add click handlers to suggestions
        searchSuggestions.querySelectorAll('.search-suggestion-item').forEach(item => {
            item.addEventListener('click', function() {
                const suggestion = this.dataset.suggestion;
                searchInput.value = suggestion;
                hideSuggestions();
                searchInput.focus();
            });
        });
    }
    
    function hideSuggestions() {
        if (searchSuggestions) {
            searchSuggestions.style.display = 'none';
        }
    }
    
    // Quick filters auto-submit functionality
    quickFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            // Auto-submit form when quick filters change
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // Enhanced search form submission
    const searchForm = document.querySelector('.enhanced-search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchValue = searchInput.value.trim();
            if (searchValue.length < 2) {
                e.preventDefault();
                showAlert('Please enter at least 2 characters to search', 'warning');
                searchInput.focus();
                return false;
            }
        });
    }
    
    // Search input animation on focus
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    }

    // Initialize dropdowns specifically for index.php
    const dropdownTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    const dropdownList = dropdownTriggerList.map(function (dropdownTriggerEl) {
        return new bootstrap.Dropdown(dropdownTriggerEl);
    });
    
    // Ensure user dropdown works properly
    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown) {
        userDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('User dropdown clicked from index.php');
            const dropdown = bootstrap.Dropdown.getInstance(this) || new bootstrap.Dropdown(this);
            dropdown.toggle();
        });
    }

    // Existing functionality remains unchanged
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Existing carousel functionality remains unchanged
    document.querySelectorAll('.carousel').forEach(carousel => {
        const track = carousel.querySelector('.carousel-track');
        const dots = carousel.querySelectorAll('.carousel-dot');
        
        // Skip if track or dots not found
        if (!track || dots.length === 0) {
            console.log('Carousel elements not found, skipping initialization');
            return;
        }
        
        let currentIndex = 0;
        const totalSlides = dots.length;

        function updateCarousel() {
            if (track) {
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
            }
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentIndex);
            });
        }

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentIndex = index;
                updateCarousel();
            });
        });

        setInterval(() => {
            currentIndex = (currentIndex + 1) % totalSlides;
            updateCarousel();
        }, 5000);
    });


    // Password show/hide functionality
    function setupPasswordToggle(toggleBtn, passwordField, iconId) {
        if (toggleBtn && passwordField) {
            const icon = document.getElementById(iconId);
            toggleBtn.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                if (icon) {
                    icon.classList.toggle('fa-eye', type === 'password');
                    icon.classList.toggle('fa-eye-slash', type === 'text');
                }
            });
        }
    }

    // Initialize all password toggles
    setupPasswordToggle(document.getElementById('toggleLoginPassword'), document.getElementById('loginPassword'), 'loginPasswordIcon');
    setupPasswordToggle(document.getElementById('toggleRegPassword'), document.getElementById('regPassword'), 'regPasswordIcon');
    setupPasswordToggle(document.getElementById('toggleConfirmPassword'), document.getElementById('regConfirmPassword'), 'confirmPasswordIcon');

    // Login form validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;
            
            if (!email || !password) {
                e.preventDefault();
                showAlert('Please fill in complete login information!', 'danger');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showAlert('Please enter a valid email address!', 'danger');
                document.getElementById('loginEmail').focus();
                return false;
            }
        });
    }

    // Registration form validation
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('regConfirmPassword').value;
            const terms = document.getElementById('regTerms').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('The two passwords do not match!', 'danger');
                document.getElementById('regConfirmPassword').focus();
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                showAlert('Password must be at least 8 characters long!', 'danger');
                document.getElementById('regPassword').focus();
                return false;
            }
            
            if (!terms) {
                e.preventDefault();
                showAlert('Please read and agree to the Terms of Service!', 'danger');
                document.getElementById('regTerms').focus();
                return false;
            }
        });
    }

    // Custom alert function
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    // Login success handling
    <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
        showAlert('Login successful! Welcome back!', 'success');
        // Clear URL parameters to avoid showing again after refresh
        setTimeout(() => {
            const url = new URL(window.location);
            url.searchParams.delete('login');
            window.history.replaceState({}, document.title, url);
        }, 1000);
    <?php endif; ?>

    // Auto-show login modal when redirected from protected links
    <?php if (isset($_GET['show_login']) && $_GET['show_login'] === '1'): ?>
        // Execute immediately when script loads
        console.log('Auto-show login modal triggered immediately');
        
        setTimeout(function() {
            const loginModal = document.getElementById('loginModal');
            console.log('Login modal element:', loginModal);
            
            if (loginModal) {
                // Use Bootstrap's default modal behavior
                const modal = new bootstrap.Modal(loginModal);
                modal.show();
                console.log('Login modal shown');
                
                // Set return URL for after login
                const returnUrl = <?php echo isset($_GET['return']) ? json_encode($_GET['return']) : '""'; ?>;
                if (returnUrl) {
                    const redirectInput = document.getElementById('redirect_after_login');
                    if (redirectInput) {
                        redirectInput.value = returnUrl;
                    }
                }
                
                // Show message if provided
                <?php if (isset($_GET['message'])): ?>
                    const message = <?php echo json_encode($_GET['message']); ?>;
                    console.log('Showing message:', message);
                    showAlert(message, 'warning');
                <?php endif; ?>
                
                // Clean URL when modal is closed
                loginModal.addEventListener('hidden.bs.modal', function() {
                    console.log('Login modal closed');
                    const url = new URL(window.location);
                    url.searchParams.delete('show_login');
                    url.searchParams.delete('return');
                    url.searchParams.delete('message');
                    window.history.replaceState({}, document.title, url);
                    console.log('URL cleaned');
                });
            } else {
                console.error('Login modal not found');
            }
        }, 500);
    <?php endif; ?>

    // Fix Modal Issues - Critical Fix Code
    function initializeModals() {
        const loginModal = document.getElementById('loginModal');
        const termsModal = document.getElementById('termsModal');
        
        // Fix Login Modal
        if (loginModal) {
            const loginModalInstance = new bootstrap.Modal(loginModal, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            
            loginModal.addEventListener('hidden.bs.modal', function () {
                // Clean up any remaining backdrops
                setTimeout(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => {
                        if (!document.querySelector('.modal.show')) {
                            backdrop.remove();
                        }
                    });
                }, 100);
            });
            
            loginModal.addEventListener('shown.bs.modal', function () {
                const activeTab = document.querySelector('.tab-pane.active');
                if (activeTab.id === 'login-tab-pane') {
                    document.getElementById('loginEmail').focus();
                } else {
                    document.getElementById('regFirstName').focus();
                }
            });
        }
        
        // Fix Terms Modal - Critical Fix
        if (termsModal) {
            const termsModalInstance = new bootstrap.Modal(termsModal, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            
            // When clicking close button
            const closeButtons = termsModal.querySelectorAll('[data-bs-dismiss="modal"]');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    setTimeout(() => {
                        // Force clean all backdrops
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        backdrops.forEach(backdrop => backdrop.remove());
                        
                        // Reinitialize login modal
                        if (loginModal) {
                            loginModalInstance.dispose();
                            new bootstrap.Modal(loginModal, {
                                backdrop: true,
                                keyboard: true,
                                focus: true
                            });
                        }
                    }, 150);
                });
            });
            
            // When clicking backdrop to close
            termsModal.addEventListener('hide.bs.modal', function (e) {
                setTimeout(() => {
                    // Force clean backdrop
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                }, 100);
            });
        }
        
        // Tab switching keeps modal open
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (e) {
                const modal = bootstrap.Modal.getInstance(loginModal);
                if (modal) {
                    modal._isShown = true;
                }
            });
        });
        
        // Global fix: Listen for all modal close events
        document.addEventListener('hidden.bs.modal', function (e) {
            setTimeout(() => {
                // Clean up all extra backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                const openModals = document.querySelectorAll('.modal.show');
                
                if (backdrops.length > openModals.length) {
                    backdrops.forEach((backdrop, index) => {
                        if (index >= openModals.length) {
                            backdrop.remove();
                        }
                    });
                }
            }, 100);
        });
    }
    
    // Initialize modals
    initializeModals();

    // Emergency fix: Clean up all remaining backdrops after page load
    setTimeout(() => {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
            if (!document.querySelector('.modal.show')) {
                backdrop.remove();
            }
        });
    }, 500);

    // Listen for click events to ensure page interactivity
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-backdrop') && !document.querySelector('.modal.show')) {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    }, true);
});

// Function to redirect to login with reminder
function redirectToLogin(targetUrl) {
    // Show login reminder modal
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
    
    // Store the target URL in a hidden input for form submission
    const redirectInput = document.getElementById('redirect_after_login');
    if (redirectInput) {
        redirectInput.value = targetUrl;
    }
}
</script>
</body>
</html>
<?php
if ($conn->ping()) {
    $conn->close();
    error_log("index.php database connection closed successfully");
} else {
    error_log("index.php database connection already closed or lost");
}
?>