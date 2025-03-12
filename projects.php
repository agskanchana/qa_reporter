<?php

require_once 'includes/config.php';
require_once 'includes/functions.php';
// Check if user is admin
checkPermission(['admin']);
$user_role = getUserRole();

// Add these lines to handle session messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of projects
$total_query = "SELECT COUNT(*) as total FROM projects";
$total_result = $conn->query($total_query);
$total_projects = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_projects / $items_per_page);

// Get all webmasters
$webmasters_query = "SELECT id, username FROM users WHERE role = 'webmaster'";
$webmasters = $conn->query($webmasters_query);

// Get projects with pagination
$projects_query = "SELECT p.*, u.username as webmaster_name
                  FROM projects p
                  LEFT JOIN users u ON p.webmaster_id = u.id
                  ORDER BY p.created_at DESC
                  LIMIT ? OFFSET ?";
$stmt = $conn->prepare($projects_query);
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$projects = $stmt->get_result();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $project_name = $conn->real_escape_string($_POST['project_name']);
                $webmaster_id = (int)$_POST['webmaster_id'];
                $project_deadline = $conn->real_escape_string($_POST['project_deadline']);
                $wp_conversion_deadline = $conn->real_escape_string($_POST['wp_conversion_deadline']);
                $gp_link = $conn->real_escape_string($_POST['gp_link']);
                $ticket_link = $conn->real_escape_string($_POST['ticket_link']);
                $test_site_link = isset($_POST['test_site_link']) ? $conn->real_escape_string($_POST['test_site_link']) : '';
                $live_site_link = isset($_POST['live_site_link']) ? $conn->real_escape_string($_POST['live_site_link']) : '';
                $admin_notes = $conn->real_escape_string($_POST['admin_notes']);
                $webmaster_notes = $conn->real_escape_string($_POST['webmaster_notes']);

                // Check if project name already exists
                $check_query = "SELECT COUNT(*) as count FROM projects WHERE name = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("s", $project_name);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $count = $result->fetch_assoc()['count'];

                if ($count > 0) {
                    $_SESSION['error'] = "A project with this name already exists!";
                    header("Location: projects.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
                    exit();
                }

                $conn->begin_transaction();
                try {
                    // Insert project with new fields
                    $query = "INSERT INTO projects (name, webmaster_id, current_status, project_deadline,
                              wp_conversion_deadline, gp_link, ticket_link, test_site_link, live_site_link,
                              admin_notes, webmaster_notes)
                              VALUES (?, ?, 'wp_conversion', ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sissssssss", $project_name, $webmaster_id, $project_deadline,
                                     $wp_conversion_deadline, $gp_link, $ticket_link, $test_site_link,
                                     $live_site_link, $admin_notes, $webmaster_notes);
                    $stmt->execute();

                    $project_id = $conn->insert_id;

                    // Insert only non-archived checklist items for new projects
                    $query = "INSERT INTO project_checklist_status (project_id, checklist_item_id, status)
                            SELECT ?, id, 'idle'
                            FROM checklist_items
                            WHERE is_archived = 0";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $project_id);
                    $stmt->execute();

                    $conn->commit();
                    $_SESSION['success'] = "Project created successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error creating project: " . $e->getMessage();
                }
                header("Location: projects.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
                exit();
                break;

            case 'edit':
                $project_id = (int)$_POST['project_id'];
                $project_name = $conn->real_escape_string($_POST['project_name']);
                $webmaster_id = (int)$_POST['webmaster_id'];
                $project_deadline = $conn->real_escape_string($_POST['project_deadline']);
                $wp_conversion_deadline = $conn->real_escape_string($_POST['wp_conversion_deadline']);
                $gp_link = $conn->real_escape_string($_POST['gp_link']);
                $ticket_link = $conn->real_escape_string($_POST['ticket_link']);
                $test_site_link = isset($_POST['test_site_link']) ? $conn->real_escape_string($_POST['test_site_link']) : '';
                $live_site_link = isset($_POST['live_site_link']) ? $conn->real_escape_string($_POST['live_site_link']) : '';
                $admin_notes = $conn->real_escape_string($_POST['admin_notes']);
                $webmaster_notes = $conn->real_escape_string($_POST['webmaster_notes']);

                $query = "UPDATE projects SET
                          name = ?,
                          webmaster_id = ?,
                          project_deadline = ?,
                          wp_conversion_deadline = ?,
                          gp_link = ?,
                          ticket_link = ?,
                          test_site_link = ?,
                          live_site_link = ?,
                          admin_notes = ?,
                          webmaster_notes = ?
                          WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sissssssssi", $project_name, $webmaster_id, $project_deadline,
                                 $wp_conversion_deadline, $gp_link, $ticket_link,
                                 $test_site_link, $live_site_link, $admin_notes,
                                 $webmaster_notes, $project_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Project updated successfully!";
                } else {
                    $_SESSION['error'] = "Error updating project: " . $stmt->error;
                }
                break;

            case 'delete':
                $project_id = (int)$_POST['project_id'];

                $conn->begin_transaction();
                try {
                    // Delete related records first
                    $tables = ['project_checklist_status', 'comments', 'qa_assignments'];
                    foreach ($tables as $table) {
                        $query = "DELETE FROM $table WHERE project_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $project_id);
                        $stmt->execute();
                    }

                    // Delete the project
                    $query = "DELETE FROM projects WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $project_id);
                    $stmt->execute();

                    $conn->commit();
                    $success = "Project deleted successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error deleting project: " . $e->getMessage();
                }
                break;
        }

        // Redirect to refresh the page and prevent form resubmission
        header("Location: projects.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects - QA Reporter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Add this to the head section for improved styling -->
    <style>
        .project-actions {
            white-space: nowrap;
        }

        .project-table th {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 1;
        }

        .modal-lg {
            max-width: 800px;
        }

        @media (max-width: 767px) {
            .table-responsive {
                max-height: 70vh;
            }
        }
    </style>
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Project Management</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                <i class="bi bi-plus-circle"></i> Create New Project
            </button>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Project List</h4>
                <span class="badge bg-primary"><?php echo $total_projects; ?> Total Projects</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover project-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Webmaster</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($project = $projects->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="project_details.php?id=<?php echo $project['id']; ?>">
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </a>
                                </td>
                                <td><?php
                                if($project['webmaster_name'] == null){
                                    echo '<span class="badge bg-danger">Deleted User</span>';
                                }else{
                                    echo htmlspecialchars($project['webmaster_name']);
                                }
                                 ?></td>
                                <td>
                                    <?php
                                    $statuses = !empty($project['current_status']) ? explode(',', $project['current_status']) : [];
                                    if (empty($statuses)): ?>
                                        <span class="badge bg-secondary">No Status</span>
                                    <?php else:
                                        foreach ($statuses as $status):
                                            // Determine badge color based on status
                                            $badge_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $badge_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $badge_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $badge_class = 'success';
                                            } elseif ($status === 'completed') {
                                                $badge_class = 'primary';
                                            }
                                    ?>
                                        <span class="badge bg-<?php echo $badge_class; ?> me-1">
                                            <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($project['created_at'])); ?></td>
                                <td class="text-end project-actions">
                                    <!-- <a href="view_project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> View
                                    </a> -->
                                    <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-file-earmark-text"></i> Details
                                    </a>
                                    <button class="btn btn-sm btn-warning"
                                            onclick="editProject(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this project? This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Project pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo url('projects.php'); ?>?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo url('projects.php'); ?>?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo url('projects.php'); ?>?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Project Modal -->
    <div class="modal fade" id="createProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="project_name" class="form-label">Project Name</label>
                                <input type="text" class="form-control" id="project_name" name="project_name" required>
                                <!-- Feedback will be inserted here by JavaScript -->
                            </div>

                            <div class="col-md-6">
                                <label for="webmaster_id" class="form-label">Assign Webmaster</label>
                                <select class="form-select" id="webmaster_id" name="webmaster_id" required>
                                    <option value="">Select Webmaster</option>
                                    <?php
                                    $webmasters->data_seek(0);
                                    while ($webmaster = $webmasters->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $webmaster['id']; ?>">
                                            <?php echo htmlspecialchars($webmaster['username']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="project_deadline" class="form-label">Project Deadline</label>
                                <input type="date" class="form-control" id="project_deadline" name="project_deadline" required>
                            </div>

                            <div class="col-md-6">
                                <label for="wp_conversion_deadline" class="form-label">WP Conversion Deadline</label>
                                <input type="date" class="form-control" id="wp_conversion_deadline" name="wp_conversion_deadline" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="gp_link" class="form-label">GP Link (Google Spreadsheet)</label>
                                <input type="url" class="form-control" id="gp_link" name="gp_link" placeholder="https://docs.google.com/spreadsheets/..." required>
                            </div>

                            <div class="col-md-6">
                                <label for="ticket_link" class="form-label">Ticket Link</label>
                                <input type="url" class="form-control" id="ticket_link" name="ticket_link" placeholder="https://..." required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="test_site_link" class="form-label">Test Site Link <span class="text-muted">(optional)</span></label>
                                <input type="url" class="form-control" id="test_site_link" name="test_site_link" placeholder="https://...">
                            </div>

                            <div class="col-md-6">
                                <label for="live_site_link" class="form-label">Live Site Link <span class="text-muted">(optional)</span></label>
                                <input type="url" class="form-control" id="live_site_link" name="live_site_link" placeholder="https://...">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="admin_notes" class="form-label">Notes by Admin</label>
                                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="webmaster_notes" class="form-label">Notes by Webmaster</label>
                                <textarea class="form-control" id="webmaster_notes" name="webmaster_notes" rows="3"
                                         <?php echo $user_role !== 'admin' ? 'readonly' : ''; ?>></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="createProjectBtn">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="project_id" id="edit_project_id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_project_name" class="form-label">Project Name</label>
                                <input type="text" class="form-control" id="edit_project_name" name="project_name" required>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_webmaster_id" class="form-label">Assign Webmaster</label>
                                <select class="form-select" id="edit_webmaster_id" name="webmaster_id" required>
                                    <option value="">Select Webmaster</option>
                                    <?php
                                    $webmasters->data_seek(0);
                                    while ($webmaster = $webmasters->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $webmaster['id']; ?>">
                                            <?php echo htmlspecialchars($webmaster['username']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_project_deadline" class="form-label">Project Deadline</label>
                                <input type="date" class="form-control" id="edit_project_deadline" name="project_deadline" required>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_wp_conversion_deadline" class="form-label">WP Conversion Deadline</label>
                                <input type="date" class="form-control" id="edit_wp_conversion_deadline" name="wp_conversion_deadline" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_gp_link" class="form-label">GP Link</label>
                                <input type="url" class="form-control" id="edit_gp_link" name="gp_link" required>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_ticket_link" class="form-label">Ticket Link</label>
                                <input type="url" class="form-control" id="edit_ticket_link" name="ticket_link" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_test_site_link" class="form-label">Test Site Link <span class="text-muted">(optional)</span></label>
                                <input type="url" class="form-control" id="edit_test_site_link" name="test_site_link">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_live_site_link" class="form-label">Live Site Link <span class="text-muted">(optional)</span></label>
                                <input type="url" class="form-control" id="edit_live_site_link" name="live_site_link">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_admin_notes" class="form-label">Notes by Admin</label>
                                <textarea class="form-control" id="edit_admin_notes" name="admin_notes" rows="3"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_webmaster_notes" class="form-label">Notes by Webmaster</label>
                                <textarea class="form-control" id="edit_webmaster_notes" name="webmaster_notes" rows="3"
                                         <?php echo $user_role !== 'admin' ? 'readonly' : ''; ?>></textarea>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editProject(project) {
        document.getElementById('edit_project_id').value = project.id;
        document.getElementById('edit_project_name').value = project.name;
        document.getElementById('edit_webmaster_id').value = project.webmaster_id;

        // Set the new fields
        if (project.project_deadline) {
            document.getElementById('edit_project_deadline').value = project.project_deadline;
        }
        if (project.wp_conversion_deadline) {
            document.getElementById('edit_wp_conversion_deadline').value = project.wp_conversion_deadline;
        }
        if (project.gp_link) {
            document.getElementById('edit_gp_link').value = project.gp_link;
        }
        if (project.ticket_link) {
            document.getElementById('edit_ticket_link').value = project.ticket_link;
        }
        if (project.test_site_link) {
            document.getElementById('edit_test_site_link').value = project.test_site_link;
        }
        if (project.live_site_link) {
            document.getElementById('edit_live_site_link').value = project.live_site_link;
        }

        // Set the notes fields
        if (project.admin_notes) {
            document.getElementById('edit_admin_notes').value = project.admin_notes;
        }
        if (project.webmaster_notes) {
            document.getElementById('edit_webmaster_notes').value = project.webmaster_notes;
        }

        new bootstrap.Modal(document.getElementById('editProjectModal')).show();
    }

    // Replace the existing document.addEventListener for project name validation with this one
    document.addEventListener('DOMContentLoaded', function() {
        const projectNameInput = document.getElementById('project_name');
        const createProjectButton = document.getElementById('createProjectBtn');
        let timeoutId;

        if (projectNameInput) {
            projectNameInput.addEventListener('input', function() {
                clearTimeout(timeoutId);
                const projectName = this.value;

                // Remove any existing feedback
                this.classList.remove('is-invalid', 'is-valid');
                const existingFeedback = this.nextElementSibling;
                if (existingFeedback && existingFeedback.classList.contains('invalid-feedback')) {
                    existingFeedback.remove();
                }

                if (projectName.length > 0) {
                    timeoutId = setTimeout(() => {
                        fetch('check_project_name.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'project_name=' + encodeURIComponent(projectName)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                projectNameInput.classList.add('is-invalid');
                                createProjectButton.disabled = true;

                                // Add invalid feedback
                                const feedback = document.createElement('div');
                                feedback.classList.add('invalid-feedback');
                                feedback.textContent = 'This project name already exists!';
                                projectNameInput.parentNode.appendChild(feedback);
                            } else {
                                projectNameInput.classList.add('is-valid');
                                createProjectButton.disabled = false;
                            }
                        });
                    }, 500); // Delay of 500ms to prevent too many requests
                }
            });
        }

        // Modal event handlers to reset form when closing
        const createProjectModal = document.getElementById('createProjectModal');
        if (createProjectModal) {
            createProjectModal.addEventListener('hidden.bs.modal', function () {
                // Reset form when modal is closed
                const form = this.querySelector('form');
                if (form) {
                    form.reset();

                    // Reset validation state
                    const inputs = form.querySelectorAll('.is-invalid, .is-valid');
                    inputs.forEach(input => {
                        input.classList.remove('is-invalid', 'is-valid');
                    });

                    // Enable submit button
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }

                    // Remove feedback messages
                    const feedbacks = form.querySelectorAll('.invalid-feedback, .valid-feedback');
                    feedbacks.forEach(feedback => {
                        feedback.remove();
                    });
                }
            });
        }
    });

    // Update the document.addEventListener block that handles date fields

    document.addEventListener('DOMContentLoaded', function() {
        // Pre-select deadlines excluding weekends
        const projectDeadlineInput = document.getElementById('project_deadline');
        const wpConversionDeadlineInput = document.getElementById('wp_conversion_deadline');
        const editProjectDeadlineInput = document.getElementById('edit_project_deadline');
        const editWpConversionDeadlineInput = document.getElementById('edit_wp_conversion_deadline');

        // Function to calculate business days, excluding weekends
        function addBusinessDays(date, days) {
            let result = new Date(date);
            let addedDays = 0;

            while (addedDays < days) {
                result.setDate(result.getDate() + 1);
                // Skip weekends (0 = Sunday, 6 = Saturday)
                if (result.getDay() !== 0 && result.getDay() !== 6) {
                    addedDays++;
                }
            }

            return result;
        }

        // Get current date
        const today = new Date();

        // Calculate WP Conversion Deadline (7 business days)
        const wpConversionDeadline = addBusinessDays(today, 7);

        // Calculate Project Deadline (18 business days)
        const projectDeadline = addBusinessDays(today, 18);

        // Format dates as YYYY-MM-DD for input fields
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // Set the default values for the date inputs
        wpConversionDeadlineInput.value = formatDateForInput(wpConversionDeadline);
        projectDeadlineInput.value = formatDateForInput(projectDeadline);

        // Also apply to the edit form when it's opened
        const editProjectModal = document.getElementById('editProjectModal');
        if (editProjectModal) {
            editProjectModal.addEventListener('show.bs.modal', function() {
                // Only set default dates if they're not already set from the project data
                const editWpDeadline = document.getElementById('edit_wp_conversion_deadline');
                const editProjectDeadline = document.getElementById('edit_project_deadline');

                if (!editWpDeadline.value) {
                    editWpDeadline.value = formatDateForInput(wpConversionDeadline);
                }

                if (!editProjectDeadline.value) {
                    editProjectDeadline.value = formatDateForInput(projectDeadline);
                }
            });
        }
    });
    </script>
</body>
</html>