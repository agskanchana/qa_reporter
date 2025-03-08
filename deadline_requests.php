<?php
// Move all PHP processing code to the beginning of the file
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $extension_id = isset($_POST['extension_id']) ? (int)$_POST['extension_id'] : 0;
    $action = $_POST['action'];

    if (empty($extension_id)) {
        $error = "Invalid extension request.";
    } else {
        // Get extension details first
        $query = "SELECT * FROM deadline_extension_requests WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $extension_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "Extension request not found.";
        } else {
            $extension = $result->fetch_assoc();
            $project_id = $extension['project_id'];
            $deadline_type = $extension['deadline_type'];
            $requested_deadline = $extension['requested_deadline'];

            if ($action === 'approve') {
                // Approve the extension
                $update_query = "UPDATE deadline_extension_requests
                                SET status = 'approved', approved_by = ?, approved_at = NOW()
                                WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ii", $_SESSION['user_id'], $extension_id);

                if ($stmt->execute()) {
                    // Update the project deadline
                    $deadline_field = $deadline_type === 'project' ? 'project_deadline' : 'wp_conversion_deadline';
                    $update_project_query = "UPDATE projects SET $deadline_field = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_project_query);
                    $stmt->bind_param("si", $requested_deadline, $project_id);

                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Extension request approved successfully.";
                        header("Location: deadline_requests.php?tab=approved");
                        exit();
                    } else {
                        $error = "Error updating project deadline: " . $conn->error;
                    }
                } else {
                    $error = "Error approving extension: " . $conn->error;
                }
            } elseif ($action === 'reject') {
                // Reject the extension
                $update_query = "UPDATE deadline_extension_requests
                                SET status = 'rejected', approved_by = ?, approved_at = NOW()
                                WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ii", $_SESSION['user_id'], $extension_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Extension request rejected.";
                    header("Location: deadline_requests.php?tab=denied");
                    exit();
                } else {
                    $error = "Error rejecting extension: " . $conn->error;
                }
            } else {
                $error = "Invalid action.";
            }
        }
    }

    if ($error) {
        $_SESSION['error'] = $error;
        header("Location: deadline_requests.php");
        exit();
    }
}

// Get all extension requests - for both WP conversion and project deadlines
$query = "SELECT r.*, p.name as project_name, u.username as requested_by_name,
          rev.username as reviewed_by_name,
          r.reviewed_at as review_timestamp
          FROM deadline_extension_requests r
          JOIN projects p ON r.project_id = p.id
          JOIN users u ON r.requested_by = u.id
          LEFT JOIN users rev ON r.reviewed_by = rev.id
          ORDER BY r.created_at DESC";
$result = $conn->query($query);

// Initialize counters properly
$pending_count = 0;
$approved_count = 0;
$denied_count = 0;

// Store results in arrays by status for easier access
$pending_requests = array();
$approved_requests = array();
$denied_requests = array();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Count and categorize by status
        if ($row['status'] === 'pending') {
            $pending_count++;
            $pending_requests[] = $row;
        } elseif ($row['status'] === 'approved') {
            $approved_count++;
            $approved_requests[] = $row;
        } elseif ($row['status'] === 'denied' || (empty($row['status']) && !empty($row['reviewed_at']))) {
            $denied_count++;
            $denied_requests[] = $row;
        }
    }
}

$user_role = getUserRole();
require_once 'includes/header.php';
?>

<!-- HTML content starts here -->
<div class="container mt-4">
    <h2>Deadline Extension Requests</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="requestTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="pending-tab" data-bs-toggle="tab" href="#pending" role="tab">
                        Pending
                        <?php if ($pending_count > 0): ?>
                            <span class="badge bg-warning text-dark"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="approved-tab" data-bs-toggle="tab" href="#approved" role="tab">
                        Approved
                        <?php if ($approved_count > 0): ?>
                            <span class="badge bg-success"><?php echo $approved_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="denied-tab" data-bs-toggle="tab" href="#denied" role="tab">
                        Denied
                        <?php if ($denied_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $denied_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="requestTabsContent">
                <!-- Pending Requests -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (count($pending_requests) > 0): ?>
                        <?php foreach ($pending_requests as $row): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <?php echo htmlspecialchars($row['deadline_type'] === 'wp_conversion' ? 'WP Conversion Deadline' : 'Project Deadline'); ?>
                                        Extension Request
                                    </h5>
                                    <span class="badge bg-primary">Pending</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Project:</strong> <?php echo htmlspecialchars($row['project_name']); ?></p>
                                            <p><strong>Requested By:</strong> <?php echo htmlspecialchars($row['requested_by_name']); ?></p>
                                            <p><strong>Requested On:</strong> <?php echo date('F j, Y g:i A', strtotime($row['created_at'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Original Deadline:</strong> <?php echo date('F j, Y', strtotime($row['original_deadline'])); ?></p>
                                            <p><strong>Requested Deadline:</strong> <?php echo date('F j, Y', strtotime($row['requested_deadline'])); ?></p>
                                            <p><strong>Days Difference:</strong>
                                                <?php
                                                $original = new DateTime($row['original_deadline']);
                                                $requested = new DateTime($row['requested_deadline']);
                                                $diff = $original->diff($requested)->days;
                                                echo $diff . ' days';
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <h6>Reason:</h6>
                                        <p class="border-start border-warning ps-3"><?php echo nl2br(htmlspecialchars($row['reason'])); ?></p>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-end">
                                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $row['id']; ?>">
                                            Approve
                                        </button>
                                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $row['id']; ?>">
                                            Deny
                                        </button>
                                    </div>

                                    <!-- Approve Modal -->
                                    <div class="modal fade" id="approveModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Approve Extension Request</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="review_extension_request.php" method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="status" value="approved">
                                                        <p>Are you sure you want to approve this deadline extension?</p>
                                                        <div class="mb-3">
                                                            <label for="approveComment<?php echo $row['id']; ?>" class="form-label">Comment (Optional):</label>
                                                            <textarea class="form-control" id="approveComment<?php echo $row['id']; ?>" name="comment" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success">Approve</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Deny Extension Request</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="review_extension_request.php" method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="status" value="denied">
                                                        <p>Are you sure you want to deny this deadline extension?</p>
                                                        <div class="mb-3">
                                                            <label for="rejectComment<?php echo $row['id']; ?>" class="form-label">Reason for Denial:</label>
                                                            <textarea class="form-control" id="rejectComment<?php echo $row['id']; ?>" name="comment" rows="3" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Deny</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No pending deadline extension requests.</div>
                    <?php endif; ?>
                </div>

                <!-- Approved Requests -->
                <div class="tab-pane fade" id="approved" role="tabpanel">
                    <?php if (count($approved_requests) > 0): ?>
                        <?php foreach ($approved_requests as $row): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <?php echo htmlspecialchars($row['deadline_type'] === 'wp_conversion' ? 'WP Conversion Deadline' : 'Project Deadline'); ?>
                                        Extension Request
                                    </h5>
                                    <span class="badge bg-light text-success">Approved</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Project:</strong> <?php echo htmlspecialchars($row['project_name']); ?></p>
                                            <p><strong>Requested By:</strong> <?php echo htmlspecialchars($row['requested_by_name']); ?></p>
                                            <p><strong>Approved By:</strong> <?php echo htmlspecialchars($row['reviewed_by_name']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Original Deadline:</strong> <?php echo date('F j, Y', strtotime($row['original_deadline'])); ?></p>
                                            <p><strong>New Deadline:</strong> <?php echo date('F j, Y', strtotime($row['requested_deadline'])); ?></p>
                                            <p><strong>Approved On:</strong>
                                                <?php
                                                echo !empty($row['review_timestamp'])
                                                    ? date('F j, Y g:i A', strtotime($row['review_timestamp']))
                                                    : 'N/A';
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <h6>Request Reason:</h6>
                                        <p class="border-start border-success ps-3"><?php echo nl2br(htmlspecialchars($row['reason'])); ?></p>
                                    </div>
                                    <?php if (!empty($row['review_comment'])): ?>
                                    <div class="mt-3">
                                        <h6>Admin Comment:</h6>
                                        <p class="border-start border-primary ps-3"><?php echo nl2br(htmlspecialchars($row['review_comment'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No approved deadline extension requests.</div>
                    <?php endif; ?>
                </div>

                <!-- Denied Requests -->
                <div class="tab-pane fade" id="denied" role="tabpanel">
                    <?php if (count($denied_requests) > 0): ?>
                        <?php foreach ($denied_requests as $row): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <?php echo htmlspecialchars($row['deadline_type'] === 'wp_conversion' ? 'WP Conversion Deadline' : 'Project Deadline'); ?>
                                        Extension Request
                                    </h5>
                                    <span class="badge bg-light text-danger">Denied</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Project:</strong> <?php echo htmlspecialchars($row['project_name']); ?></p>
                                            <p><strong>Requested By:</strong> <?php echo htmlspecialchars($row['requested_by_name']); ?></p>
                                            <p><strong>Denied By:</strong> <?php echo htmlspecialchars($row['reviewed_by_name']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Original Deadline:</strong> <?php echo date('F j, Y', strtotime($row['original_deadline'])); ?></p>
                                            <p><strong>Requested Deadline:</strong> <?php echo date('F j, Y', strtotime($row['requested_deadline'])); ?></p>
                                            <p><strong>Denied On:</strong>
                                                <?php
                                                echo !empty($row['review_timestamp'])
                                                    ? date('F j, Y g:i A', strtotime($row['review_timestamp']))
                                                    : 'N/A';
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <h6>Request Reason:</h6>
                                        <p class="border-start border-secondary ps-3"><?php echo nl2br(htmlspecialchars($row['reason'])); ?></p>
                                    </div>
                                    <?php if (!empty($row['review_comment'])): ?>
                                    <div class="mt-3">
                                        <h6>Denial Reason:</h6>
                                        <p class="border-start border-danger ps-3"><?php echo nl2br(htmlspecialchars($row['review_comment'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No denied deadline extension requests.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');

    // If there's a tab parameter, activate that tab
    if (tab) {
        const tabElement = document.querySelector(`#${tab}-tab`);
        if (tabElement) {
            const tabTrigger = new bootstrap.Tab(tabElement);
            tabTrigger.show();
        }
    }

    // Add click handlers for tabs to remember active tab
    const tabLinks = document.querySelectorAll('.nav-link');
    tabLinks.forEach(tab => {
        tab.addEventListener('click', function(e) {
            const tabId = e.target.getAttribute('href').substring(1); // Remove the # character
            history.replaceState(null, null, `?tab=${tabId}`);
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>