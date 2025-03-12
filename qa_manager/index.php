<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has qa_manager role
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

// Only allow qa_manager access to this page
if ($user_role !== 'qa_manager') {
    // Redirect to appropriate dashboard based on role
    switch ($user_role) {
        case 'admin':
            header("Location: ../admin/index.php");
            break;
        case 'qa_reporter':
            header("Location: ../qa_reporter/index.php");
            break;
        case 'webmaster':
            header("Location: ../webmaster/index.php");
            break;
        default:
            header("Location: ../index.php");
            break;
    }
    exit();
}

// Handle QA Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_qa']) && isset($_POST['project_id']) && isset($_POST['qa_user_id'])) {
        // Get the submitted data
        $project_id = (int)$_POST['project_id'];
        $qa_user_id = (int)$_POST['qa_user_id'];

        // Validate the data
        if ($project_id <= 0 || $qa_user_id <= 0) {
            $_SESSION['error_message'] = "Invalid project or QA user ID.";
        } else {
            // Check if the project exists
            $project_check_stmt = $conn->prepare("SELECT id, name FROM projects WHERE id = ?");
            $project_check_stmt->bind_param("i", $project_id);
            $project_check_stmt->execute();
            $project_result = $project_check_stmt->get_result();

            if ($project_result->num_rows === 0) {
                $_SESSION['error_message'] = "Project not found.";
            } else {
                $project = $project_result->fetch_assoc();

                // Check if the QA user exists and has correct role
                $user_check_stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ? AND role IN ('qa_reporter', 'qa_manager')");
                $user_check_stmt->bind_param("i", $qa_user_id);
                $user_check_stmt->execute();
                $user_result = $user_check_stmt->get_result();

                if ($user_result->num_rows === 0) {
                    $_SESSION['error_message'] = "Selected user is not a valid QA reporter.";
                } else {
                    $qa_user = $user_result->fetch_assoc();

                    // Check if project is already assigned
                    $check_stmt = $conn->prepare("SELECT id FROM qa_assignments WHERE project_id = ?");
                    $check_stmt->bind_param("i", $project_id);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result();

                    // Begin transaction for data consistency
                    $conn->begin_transaction();

                    try {
                        if ($existing->num_rows > 0) {
                            // Update existing assignment
                            $assignment_id = $existing->fetch_assoc()['id'];
                            $stmt = $conn->prepare("UPDATE qa_assignments SET qa_user_id = ?, assigned_by = ?, assigned_at = NOW() WHERE id = ?");
                            $stmt->bind_param("iii", $qa_user_id, $user_id, $assignment_id);
                            $stmt->execute();

                            // Add to project status history
                            $action = "QA Reporter reassigned to " . $qa_user['username'];
                            $status = "qa_assignment";
                            $history_stmt = $conn->prepare("INSERT INTO project_status_history (project_id, status, action, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                            $history_stmt->bind_param("issi", $project_id, $status, $action, $user_id);
                            $history_stmt->execute();

                            $_SESSION['success_message'] = "QA Reporter reassigned successfully.";
                        } else {
                            // Create new assignment
                            $stmt = $conn->prepare("INSERT INTO qa_assignments (project_id, qa_user_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
                            $stmt->bind_param("iii", $project_id, $qa_user_id, $user_id);
                            $stmt->execute();

                            // Add to project status history
                            $action = "QA Reporter assigned to " . $qa_user['username'];
                            $status = "qa_assignment";
                            $history_stmt = $conn->prepare("INSERT INTO project_status_history (project_id, status, action, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                            $history_stmt->bind_param("issi", $project_id, $status, $action, $user_id);
                            $history_stmt->execute();

                            $_SESSION['success_message'] = "QA Reporter assigned successfully.";
                        }

                        // Commit transaction
                        $conn->commit();

                        // Create notification for the QA user
                        $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, created_at, is_read)
                                VALUES (?, 'info', ?, NOW(), 0)");
                        $notification_content = "You have been assigned to QA project: " . $project['name'];
                        $notification_stmt->bind_param("is", $qa_user_id, $notification_content);
                        $notification_stmt->execute();

                    } catch (Exception $e) {
                        // Roll back transaction on error
                        $conn->rollback();
                        $_SESSION['error_message'] = "Error assigning QA Reporter: " . $e->getMessage();
                    }
                }
            }
        }

        // Redirect to the same page to prevent form resubmission
        header("Location: index.php");
        exit();
    }
}

// Get all QA reporters for assignment dropdown
$qa_reporters = [];
$stmt = $conn->prepare("SELECT id, username FROM users WHERE role IN ('qa_reporter', 'qa_manager') ORDER BY username");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $qa_reporters[] = $row;
}

// Get auto-assign settings
$auto_assign_wp = false;
$auto_assign_golive = false;

$auto_assign_query = "SELECT setting_key, is_enabled FROM auto_assign_to_admin";
$auto_assign_result = $conn->query($auto_assign_query);
if ($auto_assign_result && $auto_assign_result->num_rows > 0) {
    while ($row = $auto_assign_result->fetch_assoc()) {
        if ($row['setting_key'] == 'wp_conversion') {
            $auto_assign_wp = (bool)$row['is_enabled'];
        } elseif ($row['setting_key'] == 'golive') {
            $auto_assign_golive = (bool)$row['is_enabled'];
        }
    }
}

$page_title = "QA Manager Dashboard";
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- <div class="row">
        <div class="col-12">
            <h1 class="mb-4">QA Manager Dashboard</h1>
        </div>
    </div> -->

    <!-- Stats Cards -->
    <?php
    // include_once '../includes/qa_manager/widgets/stats_cards.php';
    ?>

    <div class="row mt-4">
        <!-- Left Column -->
        <div class="col-md-4">
            <!-- Notifications Widget -->
            <?php include_once '../includes/qa_manager/widgets/notifications.php'; ?>

            <!-- Unassigned Projects Widget -->
            <?php include_once '../includes/qa_manager/widgets/unassigned_projects.php'; ?>

            <!-- QA Reporter Workload Widget -->
            <?php include_once '../includes/qa_manager/widgets/qa_reporter_workload.php'; ?>
        </div>

        <!-- Right Column -->
        <div class="col-md-8">
            <!-- QA Projects Tabs Widget -->
            <?php include_once '../includes/qa_manager/widgets/qa_projects_tabs.php'; ?>
        </div>
    </div>
</div>

<!-- QA Assignment Modal -->
<?php include_once '../includes/qa_manager/modals/assign_qa_modal.php'; ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom scripts -->
<script src="<?php echo BASE_URL; ?>/assets/js/qa-manager-main.js"></script>

<!-- Initialize modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle QA Assignment Modal
    const assignQAModal = document.getElementById('assignQAModal');

    if (assignQAModal) {
        console.log('QA Modal found, initializing...'); // Debug log

        assignQAModal.addEventListener('show.bs.modal', function(event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data attributes
            const projectId = button.getAttribute('data-project-id');
            const projectName = button.getAttribute('data-project-name');

            console.log('Modal opened for project:', projectId, projectName); // Debug log

            // Update the modal's content
            const modalProjectId = assignQAModal.querySelector('#modal-project-id');
            const modalProjectName = assignQAModal.querySelector('#modal-project-name');

            if (modalProjectId && modalProjectName) {
                modalProjectId.value = projectId;
                modalProjectName.value = projectName;
            }
        });

        // Handle form submit for validation
        const form = assignQAModal.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(event) {
                const qaUserSelect = form.querySelector('#qa_user_id');
                const projectId = form.querySelector('#modal-project-id');

                if (!qaUserSelect.value) {
                    event.preventDefault();
                    alert('Please select a QA Reporter');
                    return false;
                }

                if (!projectId.value) {
                    event.preventDefault();
                    console.error('Missing project ID');
                    return false;
                }

                console.log('Form submitted with project ID:', projectId.value, 'and QA user ID:', qaUserSelect.value);
            });
        }
    } else {
        console.error('QA Assignment Modal element not found in DOM');
    }
});
</script>
</body>
</html>