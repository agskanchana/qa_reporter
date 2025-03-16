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

// Handle delete form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
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
        $_SESSION['success'] = "Project deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting project: " . $e->getMessage();
    }

    // Redirect to refresh the page and prevent form resubmission
    header("Location: projects.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
    exit();
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
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Project Management</h2>
            <a href="project_form.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create New Project
            </a>
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
                                    <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-file-earmark-text"></i> Details
                                    </a>
                                    <a href="project_form.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>