<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = getUserRole();

// Only webmasters can access this page
if ($user_role !== 'webmaster') {
    header("Location: dashboard.php");
    exit;
}

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$deadline_id = isset($_GET['deadline_id']) ? (int)$_GET['deadline_id'] : 0;
$success = '';
$error = '';
$reason_submitted = false;
$deadline = null;

// Handle form submission for reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reason'])) {
    $deadline_id = (int)$_POST['deadline_id'];
    $reason = trim($_POST['reason']);

    // Update the missed deadline with the reason
    $query = "UPDATE missed_deadlines
              SET reason = ?,
                  reason_provided_by = ?,
                  reason_provided_at = NOW()
              WHERE id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $reason, $user_id, $deadline_id);

    if ($stmt->execute()) {
        $success = "Reason submitted successfully.";
        $reason_submitted = true;

        // Get the project details for the extension form
        $query = "SELECT m.*, p.name as project_name, p.webmaster_id, p.id as project_id,
                  p.wp_conversion_deadline, p.project_deadline
                  FROM missed_deadlines m
                  JOIN projects p ON m.project_id = p.id
                  WHERE m.id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $deadline_id);
        $stmt->execute();
        $deadline = $stmt->get_result()->fetch_assoc();
    } else {
        $error = "Error submitting reason: " . $conn->error;
    }
}

// Handle extension request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_extension'])) {
    $project_id = (int)$_POST['project_id'];
    $deadline_type = $_POST['deadline_type'];
    $requested_deadline = $_POST['requested_deadline'];
    $extension_reason = trim($_POST['extension_reason']);

    // Ensure this project belongs to the current webmaster
    $check_query = "SELECT * FROM projects WHERE id = ? AND webmaster_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $project = $result->fetch_assoc();
        $original_deadline = $deadline_type === 'wp_conversion' ?
                            $project['wp_conversion_deadline'] :
                            $project['project_deadline'];

        // Create the deadline_extension_requests table if it doesn't exist
        $check_table = $conn->query("SHOW TABLES LIKE 'deadline_extension_requests'");
        if ($check_table->num_rows == 0) {
            $create_table = "CREATE TABLE IF NOT EXISTS `deadline_extension_requests` (
                `id` int NOT NULL AUTO_INCREMENT,
                `project_id` int NOT NULL,
                `requested_by` int NOT NULL,
                `deadline_type` enum('wp_conversion','project') NOT NULL,
                `original_deadline` date NOT NULL,
                `requested_deadline` date NOT NULL,
                `reason` text NOT NULL,
                `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
                `reviewed_by` int DEFAULT NULL,
                `reviewed_at` datetime DEFAULT NULL,
                `review_comment` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `project_id` (`project_id`),
                KEY `requested_by` (`requested_by`),
                KEY `reviewed_by` (`reviewed_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
            $conn->query($create_table);
        }

        // Insert extension request
        $query = "INSERT INTO deadline_extension_requests
                  (project_id, requested_by, deadline_type, original_deadline,
                   requested_deadline, reason, status)
                  VALUES (?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iissss", $project_id, $user_id, $deadline_type,
                          $original_deadline, $requested_deadline, $extension_reason);

        if ($stmt->execute()) {
            // Notify admins about the extension request
            $request_id = $conn->insert_id;
            $admin_query = "SELECT id FROM users WHERE role = 'admin'";
            $admins = $conn->query($admin_query);

            while ($admin = $admins->fetch_assoc()) {
                $message = "New deadline extension request for project '{$project['name']}'. " .
                           "Deadline type: " . ucfirst(str_replace('_', ' ', $deadline_type));

                $notify_query = "INSERT INTO notifications
                               (user_id, role, message, type, is_read)
                               VALUES (?, 'admin', ?, 'info', 0)";
                $stmt = $conn->prepare($notify_query);
                $stmt->bind_param("is", $admin['id'], $message);
                $stmt->execute();
            }

            $_SESSION['success'] = "Extension request submitted successfully.";
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Error submitting extension request: " . $conn->error;
        }
    } else {
        $error = "Invalid project.";
    }
}

// If not submitted reason yet, get the deadline details
if (!$reason_submitted) {
    $query = "SELECT m.*, p.name as project_name, p.webmaster_id, p.id as project_id,
              p.wp_conversion_deadline, p.project_deadline
              FROM missed_deadlines m
              JOIN projects p ON m.project_id = p.id
              WHERE m.id = ? AND p.webmaster_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $deadline_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: dashboard.php");
        exit;
    }

    $deadline = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $reason_submitted ? "Request Deadline Extension" : "Missed Deadline Explanation"; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <?php if (!$reason_submitted): ?>
                    <!-- Step 1: Submit reason for missing deadline -->
                    <div class="card-header bg-warning text-dark">
                        <h4>Missed Deadline Explanation</h4>
                    </div>
                    <div class="card-body">
                        <h5>Project: <?php echo htmlspecialchars($deadline['project_name']); ?></h5>
                        <p>
                            You have missed the
                            <strong><?php echo ucfirst(str_replace('_', ' ', $deadline['deadline_type'])); ?></strong>
                            deadline on <strong><?php echo $deadline['original_deadline']; ?></strong>.
                        </p>

                        <form method="POST">
                            <input type="hidden" name="deadline_id" value="<?php echo $deadline_id; ?>">

                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for missing the deadline:</label>
                                <textarea class="form-control" id="reason" name="reason" rows="5" required></textarea>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" name="submit_reason" class="btn btn-primary">Submit Reason</button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <!-- Step 2: Request deadline extension -->
                    <div class="card-header bg-info text-white">
                        <h4>Request Deadline Extension</h4>
                    </div>
                    <div class="card-body">
                        <h5>Project: <?php echo htmlspecialchars($deadline['project_name']); ?></h5>
                        <p>
                            Current <?php echo ucfirst(str_replace('_', ' ', $deadline['deadline_type'])); ?> deadline:
                            <strong>
                                <?php echo $deadline['deadline_type'] === 'wp_conversion' ?
                                      $deadline['wp_conversion_deadline'] :
                                      $deadline['project_deadline']; ?>
                            </strong>
                        </p>

                        <form method="POST">
                            <input type="hidden" name="project_id" value="<?php echo $deadline['project_id']; ?>">
                            <input type="hidden" name="deadline_type" value="<?php echo $deadline['deadline_type']; ?>">

                            <div class="mb-3">
                                <label for="requested_deadline" class="form-label">Requested New Deadline:</label>
                                <input type="date" class="form-control" id="requested_deadline" name="requested_deadline"
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="extension_reason" class="form-label">Reason for Extension Request:</label>
                                <textarea class="form-control" id="extension_reason" name="extension_reason" rows="5" required></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" name="submit_extension" class="btn btn-primary">Submit Extension Request</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>