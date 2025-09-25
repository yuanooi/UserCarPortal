<?php
session_start();
include 'includes/db.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Approve / Reject actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $test_drive_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Get the test drive date for this booking
        $stmt = $conn->prepare("SELECT test_date FROM test_drives WHERE id = ?");
        $stmt->bind_param("i", $test_drive_id);
        $stmt->execute();
        $stmt->bind_result($test_date);
        $stmt->fetch();
        $stmt->close();

        // Approve this booking
        $stmt = $conn->prepare("UPDATE test_drives SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $test_drive_id);
        $stmt->execute();
        $stmt->close();

        // Reject all other bookings on the same date
        $stmt = $conn->prepare("UPDATE test_drives SET status = 'rejected' WHERE test_date = ? AND id != ?");
        $stmt->bind_param("si", $test_date, $test_drive_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE test_drives SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $test_drive_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin_test_drive.php");
    exit;
}

// Get all bookings
$result = $conn->query("SELECT * FROM test_drives ORDER BY test_date ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Test Drive Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">🚗 Test Drive Requests</h2>

    <table class="table table-bordered text-center">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Test Drive Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['test_date']) ?></td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                        <span class="badge bg-warning text-dark">Pending</span>
                    <?php elseif ($row['status'] === 'approved'): ?>
                        <span class="badge bg-success">Approved</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                        <a href="admin_test_drive.php?action=approve&id=<?= $row['id'] ?>" class="btn btn-success btn-sm">Approve</a>
                        <a href="admin_test_drive.php?action=reject&id=<?= $row['id'] ?>" class="btn btn-danger btn-sm">Reject</a>
                    <?php else: ?>
                        <span class="text-muted">No Action</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>

</body>
</html>
