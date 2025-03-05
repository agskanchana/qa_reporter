<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Only webmasters can request extensions
if ($user_role !== 'webmaster') {
    header("Location: dashboard.php");
    exit;
}

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$deadline_type = isset($_GET['deadline_type']) ? $_GET['deadline_type'] : 'wp_conversion';

// Make sure this project belongs to the current webmaster
$query = "SELECT * FROM projects WHERE id = ? AND webmaster_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit;
}

$project = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requested_deadline = $conn->real_escape_string($_POST['requested_deadline']);
    $reason = $conn->real_escape_string($_POST['reason']);

    $original_deadline = $deadline_type === 'wp_conversion' ?
                        $project['wp_conversion_deadline'] :
                        $project['project_deadline'];

    // Insert extension request
    $query = "INSERT INTO deadline_extension_requests
              (project_id, requested_by, deadline_type, original_deadline,
               requested_deadline, reason, status, review_comment)
              VALUES (?, ?, ?, ?, ?, ?, 'pending', '')";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissss", $project_id, $user_id, $deadline_type,
                      $original_deadline, $requested_deadline, $reason);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Extension request submitted successfully.";
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Error submitting request: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Deadline Extension</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Request Deadline Extension</h4>
                    </div>
                    <div class="card-body">
                        <h5>Project: <?php echo htmlspecialchars($project['name']); ?></h5>
                        <p>
                            Current <?php echo ucfirst(str_replace('_', ' ', $deadline_type)); ?> deadline:
                            <strong>
                                <?php echo $deadline_type === 'wp_conversion' ?
                                      $project['wp_conversion_deadline'] :
                                      $project['project_deadline']; ?>
                            </strong>
                        </p>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="requested_deadline" class="form-label">Requested New Deadline:</label>
                                <input type="date" class="form-control" id="requested_deadline" name="requested_deadline"
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for Extension Request:</label>
                                <textarea class="form-control" id="reason" name="reason" rows="5" required></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">Submit Request</button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>