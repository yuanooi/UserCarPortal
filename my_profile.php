<?php
session_start();
require_once 'header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_type = $_SESSION['user_type'];
$role = $_SESSION['role'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    
    // Basic validation
    if (empty($new_username) || empty($new_email)) {
        $error_message = "Username and email are required.";
    } else {
        // Update user profile
        $update_query = "UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssi", $new_username, $new_email, $new_phone, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['username'] = $new_username;
            $success_message = "Profile updated successfully!";
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
    }
}

// Get current user data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-user-circle me-2"></i>My Profile
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-1"></i>Username
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-1"></i>Email
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone me-1"></i>Phone
                                    </label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="user_type" class="form-label">
                                        <i class="fas fa-tag me-1"></i>Account Type
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user_type); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        
                        <div class="mb-3">
                            <label for="registration_date" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Member Since
                            </label>
                            <input type="text" class="form-control" 
                                   value="<?php echo date('F j, Y', strtotime($user_data['created_at'])); ?>" readonly>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left me-1"></i>Back to Home
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Statistics -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-car fa-2x text-primary mb-2"></i>
                            <h5 class="card-title">Vehicles</h5>
                            <?php
                            $vehicle_count = 0;
                            if ($user_type === 'seller') {
                                $count_query = "SELECT COUNT(*) FROM cars WHERE user_id = ?";
                                $stmt = $conn->prepare($count_query);
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $vehicle_count = $result->fetch_row()[0];
                            }
                            ?>
                            <p class="card-text h4"><?php echo $vehicle_count; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-shopping-cart fa-2x text-success mb-2"></i>
                            <h5 class="card-title">Orders</h5>
                            <?php
                            $order_count = 0;
                            if ($user_type === 'buyer') {
                                $count_query = "SELECT COUNT(*) FROM user_history WHERE user_id = ?";
                                $stmt = $conn->prepare($count_query);
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $order_count = $result->fetch_row()[0];
                            }
                            ?>
                            <p class="card-text h4"><?php echo $order_count; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-star fa-2x text-warning mb-2"></i>
                            <h5 class="card-title">History</h5>
                            <?php
                            $review_count = 0;
                            // Reviews table doesn't exist, using user_history as alternative
                            $count_query = "SELECT COUNT(*) FROM user_history WHERE user_id = ?";
                            $stmt = $conn->prepare($count_query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $review_count = $result->fetch_row()[0];
                            ?>
                            <p class="card-text h4"><?php echo $review_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
