<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1");
    exit;
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$success = false;
$selected_model = $_GET['model'] ?? '';
$active_tab = $_GET['tab'] ?? 'annotate';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_3d_model') {
        $car_id = intval($_POST['car_id'] ?? 0);
        $body_type = $_POST['body_type'] ?? '';
        
        // Debug information
        error_log("3D Model Generation Debug:");
        error_log("Car ID: " . $car_id);
        error_log("Body Type: " . $body_type);
        error_log("POST Data: " . print_r($_POST, true));
        
        if ($car_id <= 0 || empty($body_type)) {
            $message = "⚠️ Please select a vehicle and body type. Car ID: $car_id, Body Type: '$body_type'";
        } else {
            // Generate 3D model (simulated)
            $model_id = 'model_' . $car_id . '_' . time();
            $message = "✅ 3D model generated successfully! You can now annotate issues on the model.";
            $success = true;
            $_SESSION['current_model_id'] = $model_id;
            $_SESSION['current_car_id'] = $car_id;
            
            // Redirect to annotate tab
            header("Location: admin_vehicle_issues.php?tab=annotate");
            exit;
        }
    } elseif ($action === 'add_issue') {
        $car_id = intval($_POST['car_id'] ?? 0);
        $issue_type = $_POST['issue_type'] ?? '';
        $issue_description = trim($_POST['issue_description'] ?? '');
        $severity = $_POST['severity'] ?? 'medium';
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        $model_position = $_POST['model_position'] ?? ''; // Position on 3D model

        if ($car_id <= 0 || empty($issue_type) || empty($issue_description)) {
            $message = "⚠️ Please fill in all required fields";
        } else {
            $stmt = $conn->prepare("INSERT INTO vehicle_issues (car_id, issue_type, issue_description, severity, admin_notes, model_position) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $car_id, $issue_type, $issue_description, $severity, $admin_notes, $model_position);
            
            if ($stmt->execute()) {
                $issue_id = $conn->insert_id;
                
                // Add to history
                $history_stmt = $conn->prepare("INSERT INTO vehicle_issue_history (issue_id, action_type, action_description, admin_id) VALUES (?, 'created', 'Issue created by admin on 3D model', ?)");
                $history_stmt->bind_param("ii", $issue_id, $_SESSION['user_id']);
                $history_stmt->execute();
                $history_stmt->close();
                
                $message = "✅ Vehicle issue added successfully";
                $success = true;
            } else {
                $message = "❌ Failed to add vehicle issue: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'update_status') {
        $issue_id = intval($_POST['issue_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        if ($issue_id <= 0 || empty($new_status)) {
            $message = "⚠️ Invalid issue ID or status";
        } else {
            $stmt = $conn->prepare("UPDATE vehicle_issues SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("ssi", $new_status, $admin_notes, $issue_id);
            
            if ($stmt->execute()) {
                // Add to history
                $history_stmt = $conn->prepare("INSERT INTO vehicle_issue_history (issue_id, action_type, action_description, admin_id) VALUES (?, 'updated', 'Status updated to: $new_status', ?)");
                $history_stmt->bind_param("ii", $issue_id, $_SESSION['user_id']);
                $history_stmt->execute();
                $history_stmt->close();
                
                $message = "✅ Issue status updated successfully";
                $success = true;
            } else {
                $message = "❌ Failed to update issue status: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get vehicle models
$models_query = "SELECT * FROM vehicle_models ORDER BY model_name";
$models_result = $conn->query($models_query);
$models = [];
while ($row = $models_result->fetch_assoc()) {
    $models[] = $row;
}

// Get cars based on selected model
$cars_query = "SELECT c.*, u.username FROM cars c JOIN users u ON c.user_id = u.id WHERE c.status IN ('available', 'reserved', 'pending')";
$params = [];
$types = "";

if (!empty($selected_model)) {
    $cars_query .= " AND c.model = ?";
    $params[] = $selected_model;
    $types .= "s";
}

$cars_query .= " ORDER BY c.created_at DESC";

$cars_stmt = $conn->prepare($cars_query);
if (!empty($params)) {
    $cars_stmt->bind_param($types, ...$params);
}
$cars_stmt->execute();
$cars_result = $cars_stmt->get_result();

// Get vehicle issues
$issues_query = "SELECT vi.*, c.brand, c.model, c.year, u.username as seller_username 
                 FROM vehicle_issues vi 
                 JOIN cars c ON vi.car_id = c.id 
                 JOIN users u ON c.user_id = u.id";
$issues_params = [];
$issues_types = "";

if (!empty($selected_model)) {
    $issues_query .= " WHERE c.model = ?";
    $issues_params[] = $selected_model;
    $issues_types .= "s";
}

$issues_query .= " ORDER BY vi.created_at DESC";

$issues_stmt = $conn->prepare($issues_query);
if (!empty($issues_params)) {
    $issues_stmt->bind_param($issues_types, ...$issues_params);
}
$issues_stmt->execute();
$issues_result = $issues_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Issues Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/professional-theme.css" rel="stylesheet">
    <style>
        .issue-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .issue-card:hover {
            transform: translateY(-2px);
        }
        .severity-low { border-left-color: #28a745; }
        .severity-medium { border-left-color: #ffc107; }
        .severity-high { border-left-color: #fd7e14; }
        .severity-critical { border-left-color: #dc3545; }
        .status-badge {
            font-size: 0.8rem;
        }
        .model-selector {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .body-type-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            border: 2px solid #e9ecef;
        }
        .body-type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        .body-type-card.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
            box-shadow: 0 4px 8px rgba(0,123,255,0.2);
        }
        
        /* Professional 3D Model Styles */
        #car-model-canvas {
            border-radius: 8px;
        }
        
        .model-controls .btn {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .model-controls .btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.4);
        }
        
        .model-info, .interaction-instructions {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="professional-container mt-4">
        <div class="row">
            <div class="col-12">
                <h2 class="professional-heading-2 mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>Vehicle Issues Management
                </h2>

                <!-- Model Selector -->
                <div class="model-selector">
                    <h5><i class="fas fa-car me-2"></i>Select Vehicle Model</h5>
                    <form method="GET" class="d-flex gap-2">
                        <select name="model" class="form-select" onchange="this.form.submit()">
                            <option value="">All Models</option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo htmlspecialchars($model['model_name']); ?>" 
                                        <?php echo $selected_model === $model['model_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model['model_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-light" onclick="window.location.href='admin_vehicle_issues.php'">
                            <i class="fas fa-times"></i> Clear Filter
                        </button>
                    </form>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab === 'annotate' && isset($_SESSION['current_model_id'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-cube me-2"></i>
                        <strong>3D Model Ready!</strong> You can now click on the 3D model to annotate issues.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="issueTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'annotate' ? 'active' : ''; ?>" id="annotate-issue-tab" data-bs-toggle="tab" data-bs-target="#annotate-issue" type="button" role="tab">
                            <i class="fas fa-map-pin me-1"></i>Annotate Issues
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'view-issues' ? 'active' : ''; ?>" id="view-issues-tab" data-bs-toggle="tab" data-bs-target="#view-issues" type="button" role="tab">
                            <i class="fas fa-list me-1"></i>View Issues
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="issueTabsContent">

                    <!-- Annotate Issues Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'annotate' ? 'show active' : ''; ?>" id="annotate-issue" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5><i class="fas fa-map-pin me-2"></i>Annotate Vehicle Issues</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Instructions:</h6>
                                    <p class="mb-0">Please select a vehicle and describe the problems you've identified. This will help users understand what issues exist with specific vehicles.</p>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <form method="POST" id="issueAnnotationForm">
                                            <input type="hidden" name="action" value="add_issue">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            
                                            <!-- Vehicle Selection -->
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h6><i class="fas fa-car me-2"></i>Select Vehicle</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label for="car_id" class="form-label">Vehicle</label>
                                                            <select class="form-select" id="car_id" name="car_id" required onchange="loadCarDetails()">
                                                                <option value="">Choose a vehicle...</option>
                                                                <?php 
                                                                // Reset the result pointer
                                                                $cars_result->data_seek(0);
                                                                while ($car = $cars_result->fetch_assoc()): ?>
                                                                    <option value="<?php echo $car['id']; ?>" 
                                                                            data-brand="<?php echo htmlspecialchars($car['brand']); ?>"
                                                                            data-model="<?php echo htmlspecialchars($car['model']); ?>"
                                                                            data-year="<?php echo $car['year']; ?>"
                                                                            data-body-type="<?php echo htmlspecialchars($car['body_type'] ?? ''); ?>"
                                                                            data-color="<?php echo htmlspecialchars($car['color'] ?? ''); ?>"
                                                                            data-price="<?php echo $car['price']; ?>"
                                                                            data-username="<?php echo htmlspecialchars($car['username']); ?>">
                                                                        <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ') - RM ' . number_format($car['price']) . ' - ' . $car['username']); ?>
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Vehicle Details</label>
                                                            <div id="car-details" class="alert alert-light" style="display: none;">
                                                                <div class="row">
                                                                    <div class="col-6">
                                                                        <strong>Brand:</strong> <span id="detail-brand">-</span><br>
                                                                        <strong>Model:</strong> <span id="detail-model">-</span><br>
                                                                        <strong>Year:</strong> <span id="detail-year">-</span>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <strong>Body Type:</strong> <span id="detail-body-type">-</span><br>
                                                                        <strong>Color:</strong> <span id="detail-color">-</span><br>
                                                                        <strong>Price:</strong> <span id="detail-price">-</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                                
                                                <div class="mb-3">
                                                    <label for="issue_type" class="form-label">Issue Type</label>
                                                    <select class="form-select" id="issue_type" name="issue_type" required>
                                                        <option value="">Select issue type...</option>
                                                        <option value="engine">Engine</option>
                                                        <option value="transmission">Transmission</option>
                                                        <option value="brakes">Brakes</option>
                                                        <option value="electrical">Electrical</option>
                                                        <option value="body">Body</option>
                                                        <option value="interior">Interior</option>
                                                        <option value="suspension">Suspension</option>
                                                        <option value="exhaust">Exhaust</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="severity" class="form-label">Severity Level</label>
                                                    <select class="form-select" id="severity" name="severity">
                                                        <option value="low">Low</option>
                                                        <option value="medium" selected>Medium</option>
                                                        <option value="high">High</option>
                                                        <option value="critical">Critical</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="issue_description" class="form-label">Issue Description</label>
                                                    <textarea class="form-control" id="issue_description" name="issue_description" rows="3" required 
                                                              placeholder="Describe the issue in detail..."></textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="admin_notes" class="form-label">Admin Notes</label>
                                                    <textarea class="form-control" id="admin_notes" name="admin_notes" rows="2" 
                                                              placeholder="Additional notes or recommendations..."></textarea>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-map-pin me-1"></i>Add Issue Annotation
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                            </div>
                        </div>
                    </div>

                    <!-- View Issues Tab -->
                    <div class="tab-pane fade <?php echo $active_tab === 'view-issues' ? 'show active' : ''; ?>" id="view-issues" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5><i class="fas fa-list me-2"></i>Vehicle Issues</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($issues_result->num_rows > 0): ?>
                                    <div class="row">
                                        <?php while ($issue = $issues_result->fetch_assoc()): ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card issue-card severity-<?php echo $issue['severity']; ?>">
                                                    <div class="card-body">
                                                        <h6 class="card-title">
                                                            <?php echo htmlspecialchars($issue['brand'] . ' ' . $issue['model'] . ' (' . $issue['year'] . ')'); ?>
                                                            <span class="badge bg-<?php 
                                                                echo $issue['status'] === 'pending' ? 'warning' : 
                                                                    ($issue['status'] === 'in_progress' ? 'info' : 
                                                                    ($issue['status'] === 'resolved' ? 'success' : 'danger')); 
                                                            ?> status-badge">
                                                                <?php echo ucfirst($issue['status']); ?>
                                                            </span>
                                                        </h6>
                                                        <p class="card-text">
                                                            <strong>Issue:</strong> <?php echo htmlspecialchars($issue['issue_type']); ?><br>
                                                            <strong>Severity:</strong> 
                                                            <span class="badge bg-<?php 
                                                                echo $issue['severity'] === 'low' ? 'success' : 
                                                                    ($issue['severity'] === 'medium' ? 'warning' : 
                                                                    ($issue['severity'] === 'high' ? 'danger' : 'dark')); 
                                                            ?>">
                                                                <?php echo ucfirst($issue['severity']); ?>
                                                            </span><br>
                                                            <strong>Description:</strong> <?php echo htmlspecialchars(substr($issue['issue_description'], 0, 100)) . (strlen($issue['issue_description']) > 100 ? '...' : ''); ?><br>
                                                            <strong>Seller:</strong> <?php echo htmlspecialchars($issue['seller_username']); ?><br>
                                                            <strong>Created:</strong> <?php echo date('Y-m-d H:i', strtotime($issue['created_at'])); ?>
                                                        </p>
                                                        
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewIssueModal<?php echo $issue['id']; ?>">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-warning" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#updateStatusModal<?php echo $issue['id']; ?>">
                                                                <i class="fas fa-edit"></i> Update
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- View Issue Modal -->
                                            <div class="modal fade" id="viewIssueModal<?php echo $issue['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Issue Details</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>Vehicle Information</h6>
                                                                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($issue['brand'] . ' ' . $issue['model'] . ' (' . $issue['year'] . ')'); ?></p>
                                                                    <p><strong>Seller:</strong> <?php echo htmlspecialchars($issue['seller_username']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>Issue Information</h6>
                                                                    <p><strong>Type:</strong> <?php echo htmlspecialchars($issue['issue_type']); ?></p>
                                                                    <p><strong>Severity:</strong> 
                                                                        <span class="badge bg-<?php 
                                                                            echo $issue['severity'] === 'low' ? 'success' : 
                                                                                ($issue['severity'] === 'medium' ? 'warning' : 
                                                                                ($issue['severity'] === 'high' ? 'danger' : 'dark')); 
                                                                        ?>">
                                                                            <?php echo ucfirst($issue['severity']); ?>
                                                                        </span>
                                                                    </p>
                                                                    <p><strong>Status:</strong> 
                                                                        <span class="badge bg-<?php 
                                                                            echo $issue['status'] === 'pending' ? 'warning' : 
                                                                                ($issue['status'] === 'in_progress' ? 'info' : 
                                                                                ($issue['status'] === 'resolved' ? 'success' : 'danger')); 
                                                                        ?>">
                                                                            <?php echo ucfirst($issue['status']); ?>
                                                                        </span>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h6>Issue Description</h6>
                                                            <p><?php echo nl2br(htmlspecialchars($issue['issue_description'])); ?></p>
                                                            <?php if (!empty($issue['admin_notes'])): ?>
                                                                <h6>Admin Notes</h6>
                                                                <p><?php echo nl2br(htmlspecialchars($issue['admin_notes'])); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Update Status Modal -->
                                            <div class="modal fade" id="updateStatusModal<?php echo $issue['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Update Issue Status</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="issue_id" value="<?php echo $issue['id']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="new_status<?php echo $issue['id']; ?>" class="form-label">New Status</label>
                                                                    <select class="form-select" id="new_status<?php echo $issue['id']; ?>" name="new_status" required>
                                                                        <option value="pending" <?php echo $issue['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                        <option value="in_progress" <?php echo $issue['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                        <option value="resolved" <?php echo $issue['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                        <option value="rejected" <?php echo $issue['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="admin_notes<?php echo $issue['id']; ?>" class="form-label">Admin Notes</label>
                                                                    <textarea class="form-control" id="admin_notes<?php echo $issue['id']; ?>" name="admin_notes" rows="3"><?php echo htmlspecialchars($issue['admin_notes']); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Update Status</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No vehicle issues found<?php echo !empty($selected_model) ? ' for ' . htmlspecialchars($selected_model) : ''; ?>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <!-- Vehicle Issues Form Script -->
    <script>
        // Simple form handling for vehicle issues
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Vehicle issues form loaded');
            
            // Handle form submission
            const form = document.getElementById('issueAnnotationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form data
                    const formData = new FormData(form);
                    
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                    submitBtn.disabled = true;
                    
                    // Submit form
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Reset button
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        
                        // Show success message
                        alert('Issue reported successfully!');
                        
                        // Reset form
                        form.reset();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error submitting issue. Please try again.');
                        
                        // Reset button
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
                });
            }
        });
    </script>
    
    <script>
        // Load car details when vehicle is selected
        function loadCarDetails() {
            const select = document.getElementById('car_id');
            const detailsDiv = document.getElementById('car-details');
            
            if (select.value) {
                const option = select.selectedOptions[0];
                
                // Update detail spans
                document.getElementById('detail-brand').textContent = option.dataset.brand || '-';
                document.getElementById('detail-model').textContent = option.dataset.model || '-';
                document.getElementById('detail-year').textContent = option.dataset.year || '-';
                document.getElementById('detail-body-type').textContent = option.dataset.bodyType || '-';
                document.getElementById('detail-color').textContent = option.dataset.color || '-';
                document.getElementById('detail-price').textContent = 'RM ' + (option.dataset.price ? parseInt(option.dataset.price).toLocaleString() : '-');
                
                // Show details
                detailsDiv.style.display = 'block';
            } else {
                // Hide details
                detailsDiv.style.display = 'none';
            }
        }
        
        let selectedBodyType = '';
        let currentModelId = '<?php echo $_SESSION['current_model_id'] ?? ''; ?>';
        let annotationMode = false;
    </script>
</body>
</html>

<?php
$conn->close();
?>
