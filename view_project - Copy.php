<?php
// view_project.php
require_once 'includes/config.php';
// require_once 'functions.php';
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$project_id = (int)$_GET['id'];
$user_role = getUserRole();

/*
if ($user_role === 'qa_reporter') {
    // Check if the project is assigned to this QA reporter
    $stmt = $conn->prepare("SELECT id FROM qa_assignments WHERE project_id = ? AND qa_user_id = ?");
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "You don't have access to this project.";
        header("Location: dashboard.php");
        exit();
    }
}
*/
// Get project details
$query = "SELECT p.*, u.username as webmaster_name
          FROM projects p
          LEFT JOIN users u ON p.webmaster_id = u.id
          WHERE p.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/functions.php';
syncProjectChecklist($project_id);
// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $item_id = (int)$_POST['item_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    $comment = isset($_POST['comment']) ? $conn->real_escape_string($_POST['comment']) : '';

    // Update checklist item status
    $query = "UPDATE project_checklist_status
              SET status = ?
              WHERE project_id = ? AND checklist_item_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $new_status, $project_id, $item_id);
    $stmt->execute();

    // Add comment if provided
    if ($comment) {
        $query = "INSERT INTO comments (project_id, checklist_item_id, user_id, comment)
                 VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiis", $project_id, $item_id, $_SESSION['user_id'], $comment);
        $stmt->execute();
    }

    // Check and update project status
    updateProjectStatus($conn, $project_id);

    // Redirect to refresh the page
    header("Location: view_project.php?id=" . $project_id);
    exit();
}

// Function to update project status based on checklist items
function updateProjectStatus($conn, $project_id) {
    $query = "SELECT
    current_status,
    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id
     AND ci.stage = 'wp_conversion'
     AND pcs.status = 'fixed') as wp_fixed_count,
    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id
     AND ci.stage = 'wp_conversion'
     AND pcs.status IN ('passed', 'failed')) as wp_qa_count
  FROM projects p
  WHERE id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

    // Update status based on conditions
    $new_status = $result['current_status'];

    if ($result['current_status'] == 'wp_conversion' && $result['wp_fixed_count'] > 0) {
        $new_status = 'wp_conversion_qa';
    } elseif ($result['current_status'] == 'wp_conversion_qa' && $result['wp_qa_count'] > 0) {
        $new_status = 'page_creation';
    }

    // Update project status if changed
    if ($new_status != $result['current_status']) {
        $query = "UPDATE projects SET current_status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $new_status, $project_id);
        $stmt->execute();
    }
}

// Get checklist items with their status
$query = "SELECT ci.*, COALESCE(pcs.status, 'idle') as status,
          (SELECT COUNT(*) FROM comments c
           WHERE c.project_id = ? AND c.checklist_item_id = ci.id) as comment_count
          FROM checklist_items ci
          JOIN project_checklist_status pcs  -- Changed LEFT JOIN to JOIN to only show items in this project
              ON ci.id = pcs.checklist_item_id
              AND pcs.project_id = ?
          ORDER BY ci.stage, ci.id";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $project_id, $project_id);
$stmt->execute();
$checklist_items = $stmt->get_result();

require_once 'includes/header.php'

?>



    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2><?php echo htmlspecialchars($project['name']); ?></h2>
                <p class="text-muted">
                    Status: <span class="badge bg-primary project-status-badge">
                        <?php echo ucfirst(str_replace('_', ' ', $project['current_status'])); ?>
                    </span>
                    Webmaster: <?php
                    if($project['webmaster_name']){
                    echo htmlspecialchars($project['webmaster_name']);
                    }else{
                        echo '<span class="badge bg-danger">
                                                Deleted User</span>';
                    }
                     ?>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs" id="stageTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="wp-tab" data-bs-toggle="tab" href="#wp" role="tab">
                            WP Conversion
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="page-tab" data-bs-toggle="tab" href="#page" role="tab">
                            Page Creation
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="golive-tab" data-bs-toggle="tab" href="#golive" role="tab">
                            Golive
                        </a>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="stageTabContent">
                    <?php
                    $stages = ['wp_conversion', 'page_creation', 'golive'];
                    foreach ($stages as $stage):
                        $active = $stage == 'wp_conversion' ? 'show active' : '';
                    ?>
                    <div class="tab-pane fade <?php echo $active; ?>"
                         id="<?php echo explode('_', $stage)[0]; ?>" role="tabpanel">
                        <div class="accordion" id="<?php echo $stage; ?>Accordion">
                            <?php
                            $checklist_items->data_seek(0);
                            while ($item = $checklist_items->fetch_assoc()):
                                if ($item['stage'] == $stage):
                            ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#item<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        <?php if ($item['is_archived']): ?>
        <span class="badge bg-secondary ms-2" title="This item has been archived">Archived</span>
    <?php endif; ?>
                                       <span class="badge bg-<?php
                                            echo match($item['status']) {
                                                'passed' => 'success',
                                                'failed' => 'danger',
                                                'fixed' => 'warning',
                                                default => 'secondary'
                                            };
                                        ?> ms-2">
                                            <?php echo ucfirst((string)$item['status']); ?>
                                        </span>
                                        <?php if ($item['comment_count'] > 0): ?>
                                            <span class="badge bg-info ms-2">
                                                <i class="bi bi-chat"></i> <?php echo $item['comment_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </button>
                                </h2>
                                <div id="item<?php echo $item['id']; ?>" class="accordion-collapse collapse">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>How to Check:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($item['how_to_check'])); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>How to Fix:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($item['how_to_fix'])); ?></p>
                                            </div>
                                        </div>

                                        <!-- Status Update Form -->
                                        <!-- In the checklist items loop in view_project.php -->
                                        <?php
                                        $isDisabled = false;
                                        $current_status = $project['current_status'];
                                        $user_role = getUserRole();

                                        // Only disable for QA roles when not in QA stage
                                        if (in_array($user_role, ['qa_reporter', 'qa_manager']) && strpos($current_status, '_qa') === false) {
                                            $isDisabled = true;
                                        }
                                        ?>

                                        <form method="POST" data-status-form class="mt-3">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select name="status" class="form-select" required <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                        <option value="">Select Status</option>
                                                        <?php if ($user_role == 'webmaster'): ?>
                                                            <option value="fixed" <?php echo $item['status'] == 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                                        <?php endif; ?>
                                                        <?php if (in_array($user_role, ['qa_reporter', 'qa_manager', 'admin'])): ?>
                                                            <option value="passed" <?php echo $item['status'] == 'passed' ? 'selected' : ''; ?>>Passed</option>
                                                            <option value="failed" <?php echo $item['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <button type="submit" class="btn btn-primary" <?php echo $isDisabled ? 'disabled' : ''; ?>>
                                                        Update Status
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="mt-3 mb-3">
                                                <textarea name="comment" class="form-control" placeholder="Add a comment (optional)"
                                                        <?php echo $isDisabled ? 'disabled' : ''; ?>></textarea>
                                            </div>
                                        </form>

                                        <!-- Comments Section -->

                                        <div class="comments-section">
                                            <?php
                                            $comments_query = "SELECT c.*, u.username, u.role FROM comments c
                                                            JOIN users u ON c.user_id = u.id
                                                            WHERE c.project_id = ? AND c.checklist_item_id = ?
                                                            ORDER BY c.created_at DESC";
                                            $stmt = $conn->prepare($comments_query);
                                            $stmt->bind_param("ii", $project['id'], $item['id']);
                                            $stmt->execute();
                                            $comments = $stmt->get_result();

                                            while ($comment = $comments->fetch_assoc()):
                                                // Set alert class based on user role
                                                $alertClass = match($comment['role']) {
                                                    'webmaster' => 'alert-primary',
                                                    'qa_reporter', 'qa_manager' => 'alert-warning',
                                                    'admin' => 'alert-info',
                                                    default => 'alert-secondary'
                                                };
                                            ?>
                                                <div class="alert <?php echo $alertClass; ?> mb-2">
                                                    <p class="mb-1"><?php echo htmlspecialchars($comment['comment']); ?></p>
                                                    <small>
                                                        By <?php echo htmlspecialchars($comment['username']); ?>
                                                        (<?php echo ucfirst($comment['role']); ?>) -
                                                        <?php echo date('Y-m-d H:i:s', strtotime($comment['created_at'])); ?>
                                                    </small>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>


                                    </div>
                                </div>
                            </div>
                            <?php
                                endif;
                            endwhile;
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    $current_user_query = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($current_user_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $current_user_result = $stmt->get_result();
    $current_user = $current_user_result->fetch_assoc();
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentUserName = <?php echo json_encode($current_user['username']); ?>;
document.querySelectorAll('form[data-status-form]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const statusSelect = this.querySelector('select[name="status"]');
        const submitButton = this.querySelector('button[type="submit"]');

        // Disable form elements during submission
        statusSelect.disabled = true;
        submitButton.disabled = true;

        fetch('update_checklist_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(data);
                // Update the status badge
                const itemRow = this.closest('.accordion-item');
                const statusBadge = itemRow.querySelector('.badge');
                const newStatus = statusSelect.value;
                console.log(statusBadge);
                console.log(itemRow);
// remove return
                // Update badge text and class
                statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                statusBadge.className = `status-badge badge bg-${
                    newStatus === 'passed' ? 'success' :
                    newStatus === 'failed' ? 'danger' :
                    newStatus === 'fixed' ? 'warning' : 'secondary'
                }`;

                // Update project status if it changed
                if (data.newStatus) {
                    const projectStatusBadge = document.querySelector('.project-status-badge');
                    if (projectStatusBadge) {
                        projectStatusBadge.textContent = data.newStatus.replace(/_/g, ' ').replace(/\w\S*/g,
                            txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
                        );
                    }
                }

                // Add success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success mt-2';
                alert.textContent = 'Status updated successfully!';
                this.appendChild(alert);

                // Remove alert after 3 seconds
                setTimeout(() => alert.remove(), 3000);

                // If comment was added, append it to comments section
                const commentText = this.querySelector('textarea[name="comment"]');
                if (commentText.value.trim()) {
                    const commentsSection = itemRow.querySelector('.comments-section');
                    if (commentsSection) {
                        const newComment = document.createElement('div');
                        newComment.className = 'card mb-2 mt-2';
                        newComment.innerHTML = `
                            <div class="card-body">
                                <p class="card-text">${commentText.value}</p>
                                <small class="text-muted">
                                    By ${currentUserName} just now
                                </small>
                            </div>
                        `;
                        commentsSection.insertBefore(newComment, commentsSection.firstChild);
                        commentText.value = '';
                    }
                }
            } else {
                // Show error message
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger mt-2';
                alert.textContent = data.error || 'An error occurred while updating status.';
                this.appendChild(alert);

                // Remove alert after 3 seconds
                setTimeout(() => alert.remove(), 3000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Show error message
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mt-2';
            alert.textContent = 'An error occurred while updating status.';
            this.appendChild(alert);

            // Remove alert after 3 seconds
            setTimeout(() => alert.remove(), 3000);
        })
        .finally(() => {
            // Re-enable form elements
            statusSelect.disabled = false;
            submitButton.disabled = false;
        });
    });
});
</script>
</body>
</html>