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
                    // Insert project
                    $query = "INSERT INTO projects (name, webmaster_id, current_status) VALUES (?, ?, 'wp_conversion')";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("si", $project_name, $webmaster_id);
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

                $query = "UPDATE projects SET name = ?, webmaster_id = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sii", $project_name, $webmaster_id, $project_id);

                if ($stmt->execute()) {
                    $success = "Project updated successfully!";
                } else {
                    $error = "Error updating project.";
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

        <div class="container mt-4">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- ...rest of your existing HTML... -->
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h4>Add New Project</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="project_name" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="project_name" name="project_name" required>
                            <!-- Feedback will be inserted here by JavaScript -->
                        </div>

                        <div class="col-md-6 mb-3">
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
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4>Project List</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Webmaster</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($project = $projects->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                <td><?php
                                if($project['webmaster_name'] == null){
                                    echo '<span class="badge bg-danger">
                                                Deleted User</span>';
                                }else{
                                echo htmlspecialchars($project['webmaster_name']);
                                }
                                 ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo ucfirst(str_replace('_', ' ', $project['current_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                <td>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="project_id" id="edit_project_id">

                        <div class="mb-3">
                            <label for="edit_project_name" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="edit_project_name" name="project_name" required>
                        </div>

                        <div class="mb-3">
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

        new bootstrap.Modal(document.getElementById('editProjectModal')).show();
    }

    // Add this inside the <script> tags in projects.php
    document.addEventListener('DOMContentLoaded', function() {
        const projectNameInput = document.getElementById('project_name');
        const createProjectButton = document.querySelector('button[type="submit"]');
        let timeoutId;

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
    });
    </script>
</body>
</html>