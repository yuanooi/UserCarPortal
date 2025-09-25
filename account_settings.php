<?php
session_start();
require_once 'header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_type = $_SESSION['user_type'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $password_error = "New password must be at least 6 characters long.";
    } else {
        // Verify current password
        $check_query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $password_success = "Password changed successfully!";
            } else {
                $password_error = "Failed to change password. Please try again.";
            }
        } else {
            $password_error = "Current password is incorrect.";
        }
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_delete = $_POST['confirm_delete'];
    $delete_password = $_POST['delete_password'];
    
    if ($confirm_delete !== 'DELETE') {
        $delete_error = "Please type 'DELETE' to confirm account deletion.";
    } elseif (empty($delete_password)) {
        $delete_error = "Please enter your password to confirm deletion.";
    } else {
        // Verify password
        $check_query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (password_verify($delete_password, $user_data['password'])) {
            // Delete account
            $delete_query = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                session_destroy();
                header('Location: index.php?message=account_deleted');
                exit();
            } else {
                $delete_error = "Failed to delete account. Please try again.";
            }
        } else {
            $delete_error = "Password is incorrect.";
        }
    }
}

// Get user data
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
            <!-- Password Change Section -->
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">
                        <i class="fas fa-key me-2"></i>Change Password
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($password_error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $password_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($password_success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $password_success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">
                                <i class="fas fa-lock me-1"></i>Current Password
                            </label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">
                                <i class="fas fa-key me-1"></i>New Password
                            </label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="6" required>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-check me-1"></i>Confirm New Password
                            </label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   minlength="6" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-save me-1"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Section -->
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Account Information
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Username:</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user_data['username']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Email:</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user_data['email']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Account Type:</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-primary"><?php echo ucfirst($user_type); ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Member Since:</label>
                                <p class="form-control-plaintext"><?php echo date('F j, Y', strtotime($user_data['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Phone:</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user_data['phone'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="my_profile.php" class="btn btn-info me-md-2">
                            <i class="fas fa-user-edit me-1"></i>Edit Profile
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home me-1"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="card shadow border-danger">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-warning me-2"></i>
                        <strong>Warning:</strong> Deleting your account will permanently remove all your data, including vehicles, orders, and reviews. This action cannot be undone.
                    </div>
                    
                    <?php if (isset($delete_error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $delete_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" onsubmit="return confirmDelete()">
                        <div class="mb-3">
                            <label for="confirm_delete" class="form-label">
                                <i class="fas fa-trash me-1"></i>Type 'DELETE' to confirm
                            </label>
                            <input type="text" class="form-control" id="confirm_delete" name="confirm_delete" 
                                   placeholder="Type DELETE here" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="delete_password" class="form-label">
                                <i class="fas fa-lock me-1"></i>Enter your password
                            </label>
                            <input type="password" class="form-control" id="delete_password" name="delete_password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="delete_account" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i>Delete Account Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    const confirmText = document.getElementById('confirm_delete').value;
    if (confirmText !== 'DELETE') {
        alert('Please type "DELETE" to confirm account deletion.');
        return false;
    }
    
    return confirm('Are you absolutely sure you want to delete your account? This action cannot be undone and will permanently remove all your data.');
}
</script>

<?php require_once 'footer.php'; ?>
