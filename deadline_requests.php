<?php
require_once 'includes/config.php';
$user_role = getUserRole();
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get all extension requests - only for WP conversion
$query = "SELECT r.*, p.name as project_name, u.username as requested_by_name,
          rev.username as reviewed_by_name,
          r.reviewed_at as review_timestamp
          FROM deadline_extension_requests r
          JOIN projects p ON r.project_id = p.id
          JOIN users u ON r.requested_by = u.id
          LEFT JOIN users rev ON r.reviewed_by = rev.id
          WHERE r.deadline_type = 'wp_conversion'
          ORDER BY r.created_at DESC";
$result = $conn->query($query);

// Debug info collection
$debug_data = [];
$debug_data['query'] = $query;
$debug_data['rows'] = [];
$pending_count = 0;
$approved_count = 0;
$denied_count = 0;
$empty_status_count = 0;
$other_status = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $debug_data['rows'][] = $row;

        // Count statuses
        if ($row['status'] === 'pending') {
            $pending_count++;
        } elseif ($row['status'] === 'approved') {
            $approved_count++;
        } elseif ($row['status'] === 'denied') {
            $denied_count++;
        } elseif (empty($row['status'])) {
            $empty_status_count++;
        } else {
            if (!isset($other_status[$row['status']])) {
                $other_status[$row['status']] = 0;
            }
            $other_status[$row['status']]++;
        }
    }
    $result->data_seek(0);
}

// Fix empty status values if needed
if ($empty_status_count > 0) {
    // We found records with empty status, let's try to fix them
    $fix_query = "UPDATE deadline_extension_requests SET status = 'denied' WHERE status = '' AND reviewed_at IS NOT NULL";
    $fix_result = $conn->query($fix_query);
    $fixed_count = $conn->affected_rows;

    if ($fixed_count > 0) {
        // Re-run the original query to get updated data
        $result = $conn->query($query);
    }
}

// Database table structure
$table_structure = [];
$structure_query = "DESCRIBE deadline_extension_requests";
$structure_result = $conn->query($structure_query);
if ($structure_result) {
    while ($row = $structure_result->fetch_assoc()) {
        $table_structure[] = $row;
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <h2>WP Conversion Deadline Extension Requests</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if ($empty_status_count > 0): ?>
    <div class="alert alert-warning">
        Found <?php echo $empty_status_count; ?> records with empty status values.
        <?php if ($fixed_count > 0): ?>
            Fixed <?php echo $fixed_count; ?> records.
        <?php endif; ?>
    </div>
    <?php endif; ?>



    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="requestTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="pending-tab" data-bs-toggle="tab" href="#pending" role="tab">Pending</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="approved-tab" data-bs-toggle="tab" href="#approved" role="tab">Approved</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="denied-tab" data-bs-toggle="tab" href="#denied" role="tab">Denied</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="requestTabsContent">
                <!-- Pending Requests -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php
                    $has_pending = false;
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()) {
                        if ($row['status'] === 'pending') {
                            $has_pending = true;
                    ?>
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
                    <?php
                        }
                    }
                    if (!$has_pending) {
                        echo '<div class="alert alert-info">No pending deadline extension requests.</div>';
                    }
                    ?>
                </div>

                <!-- Approved Requests -->
                <div class="tab-pane fade" id="approved" role="tabpanel">
                    <?php
                    $has_approved = false;
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()) {
                        if ($row['status'] === 'approved') {
                            $has_approved = true;
                    ?>
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
                    <?php
                        }
                    }
                    if (!$has_approved) {
                        echo '<div class="alert alert-info">No approved deadline extension requests.</div>';
                    }
                    ?>
                </div>

                <!-- Denied Requests -->
                <div class="tab-pane fade" id="denied" role="tabpanel">
                    <?php
                    $has_denied = false;
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()) {
                        // Check for both 'denied' and empty status with reviewed_at not null
                        if ($row['status'] === 'denied' || (empty($row['status']) && !empty($row['review_timestamp']))) {
                            $has_denied = true;
                    ?>
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
                    <?php
                        }
                    }
                    if (!$has_denied) {
                        echo '<div class="alert alert-info">No denied deadline extension requests.</div>';
                    }
                    ?>
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