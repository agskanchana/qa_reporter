<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if it's a new day and time to check deadlines
check_wp_conversion_deadlines();

require_once 'includes/dashboard/header_queries.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

// Check for missed deadlines
require_once 'includes/check_missed_deadlines.php';

// Handle QA Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user_role, ['admin', 'qa_manager'])) {
    include_once 'includes/dashboard/handle_assignment.php';
}

// Get all necessary data based on user role
require_once 'includes/dashboard/project_queries.php';
require_once 'includes/dashboard/qa_reporter_queries.php';

// Include header with navigation
require_once 'includes/header.php';

// Get notifications for the current user
require_once 'includes/dashboard/notification.php';
?>

<!-- Main Dashboard Container -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Column -->
        <div class="col-lg-3">
            <div class="dashboard-sidebar sticky-top pt-3">
                <!-- User profile summary -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <div class="avatar-container mb-3">
                            <i class="bi bi-person-circle display-4"></i>
                        </div>
                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($_SESSION['username']); ?></h5>
                        <p class="text-muted small"><?php echo ucfirst($user_role); ?></p>

                        <!-- Role-based quick stats -->
                        <div class="d-flex justify-content-around mt-3">
                            <?php if ($user_role === 'webmaster'): ?>
                                <div class="text-center">
                                    <div class="stat-value"><?php echo count($projects); ?></div>
                                    <div class="stat-label">Projects</div>
                                </div>
                                <?php
                                $active_count = 0;
                                foreach ($projects as $project) {
                                    if ($project['current_status'] !== 'completed') {
                                        $active_count++;
                                    }
                                }
                                ?>
                                <div class="text-center">
                                    <div class="stat-value"><?php echo $active_count; ?></div>
                                    <div class="stat-label">Active</div>
                                </div>
                            <?php elseif ($user_role === 'qa_reporter'): ?>
                                <div class="text-center">
                                    <div class="stat-value"><?php echo count($projects); ?></div>
                                    <div class="stat-label">Assigned</div>
                                </div>
                            <?php elseif (in_array($user_role, ['admin', 'qa_manager'])): ?>
                                <div class="text-center">
                                    <div class="stat-value"><?php echo count($wp_conversion_qa_projects); ?></div>
                                    <div class="stat-label">WP QA</div>
                                </div>
                                <div class="text-center">
                                    <div class="stat-value"><?php echo count($page_creation_qa_projects); ?></div>
                                    <div class="stat-label">Page QA</div>
                                </div>
                                <div class="text-center">
                                    <div class="stat-value"><?php echo count($golive_qa_projects); ?></div>
                                    <div class="stat-label">Golive QA</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Include sidebar content based on user role -->
                <?php require_once 'includes/dashboard/sidebar_content.php'; ?>
            </div>
        </div>

        <!-- Main Content Column -->
        <div class="col-lg-9">
            <main class="dashboard-main py-4">
                <!-- Dashboard Header -->
                <div class="page-header d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h2 mb-0">Dashboard</h1>
                        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>

                    <!-- Action buttons based on user role -->
                    <div>
                        <?php if ($user_role === 'webmaster'): ?>
                            <a href="create_project.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> New Project
                            </a>
                        <?php elseif (in_array($user_role, ['admin', 'qa_manager'])): ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-gear me-1"></i> Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="manage_users.php">Manage Users</a></li>
                                    <li><a class="dropdown-item" href="checklist_items.php">Manage Checklists</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="reports.php">Generate Reports</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dashboard Search & Filter Area -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" id="project-search" class="form-control" placeholder="Search projects...">
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-secondary status-filter active" data-status="all">All</button>
                                    <button type="button" class="btn btn-outline-info status-filter" data-status="wp_conversion">WP</button>
                                    <button type="button" class="btn btn-outline-warning status-filter" data-status="page_creation">Pages</button>
                                    <button type="button" class="btn btn-outline-primary status-filter" data-status="golive">Golive</button>
                                    <button type="button" class="btn btn-outline-success status-filter" data-status="completed">Completed</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Stats Overview -->
                <div class="row mb-4">
                    <?php if ($user_role === 'webmaster'): ?>
                        <!-- Webmaster Stats -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card wp-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">WP Conversion</h5>
                                        <?php
                                        $wp_count = 0;
                                        foreach ($projects as $project) {
                                            if (strpos($project['current_status'], 'wp_conversion') !== false) {
                                                $wp_count++;
                                            }
                                        }
                                        ?>
                                        <div class="stat-value"><?php echo $wp_count; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-wordpress stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card page-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Page Creation</h5>
                                        <?php
                                        $page_count = 0;
                                        foreach ($projects as $project) {
                                            if (strpos($project['current_status'], 'page_creation') !== false) {
                                                $page_count++;
                                            }
                                        }
                                        ?>
                                        <div class="stat-value"><?php echo $page_count; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-file-earmark-text stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card golive-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Go-Live</h5>
                                        <?php
                                        $golive_count = 0;
                                        foreach ($projects as $project) {
                                            if (strpos($project['current_status'], 'golive') !== false) {
                                                $golive_count++;
                                            }
                                        }
                                        ?>
                                        <div class="stat-value"><?php echo $golive_count; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-rocket-takeoff stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card completed-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Completed</h5>
                                        <?php
                                        $completed_count = 0;
                                        foreach ($projects as $project) {
                                            if ($project['current_status'] === 'completed') {
                                                $completed_count++;
                                            }
                                        }
                                        ?>
                                        <div class="stat-value"><?php echo $completed_count; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-check-circle stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif (in_array($user_role, ['admin', 'qa_manager'])): ?>
                        <!-- Admin/QA Manager Stats -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card wp-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">WP QA Pending</h5>
                                        <div class="stat-value"><?php echo count($wp_conversion_qa_projects); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-wordpress stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card page-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Page QA Pending</h5>
                                        <div class="stat-value"><?php echo count($page_creation_qa_projects); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-file-earmark-text stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card golive-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Golive QA Pending</h5>
                                        <div class="stat-value"><?php echo count($golive_qa_projects); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-rocket-takeoff stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="stat-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Unassigned</h5>
                                        <div class="stat-value"><?php echo count($unassigned_projects); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-person-x stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($user_role === 'qa_reporter'): ?>
                        <!-- QA Reporter Stats -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <div class="stat-card wp-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Assigned Projects</h5>
                                        <div class="stat-value"><?php echo count($projects); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clipboard-check stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4 mb-3">
                            <div class="stat-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">In Progress</h5>
                                        <?php
                                        $in_progress = 0;
                                        foreach ($projects as $project) {
                                            $statuses = !empty($project['current_status']) ?
                                                explode(',', $project['current_status']) : [];
                                            if (in_array('page_creation_qa', $statuses)) {
                                                $in_progress++;
                                            }
                                        }
                                        ?>
                                        <div class="stat-value"><?php echo $in_progress; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-hourglass-split stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4 mb-3">
                            <div class="stat-card completed-card">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h5 class="stat-label">Completed</h5>
                                        <?php
                                        // This would require additional query to get completed projects
                                        $completed = count($projects) - $in_progress;
                                        ?>
                                        <div class="stat-value"><?php echo $completed; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-check-circle stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Role-specific Main Content Area -->
                <?php if ($user_role === 'webmaster'): ?>
                    <!-- Webmaster Content: Projects Table -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white">
                            <h5 class="mb-0">Your Projects</h5>
                            <span class="badge bg-primary rounded-pill" id="visible-projects-count"><?php echo count($projects); ?></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Project Name</th>
                                            <th>Status</th>
                                            <th>Deadline</th>
                                            <th>QA Assigned</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($projects) === 0): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <div class="empty-state">
                                                        <i class="bi bi-folder-plus display-4 text-muted mb-3"></i>
                                                        <p class="text-muted mb-3">No projects yet. Start by creating your first project.</p>
                                                        <a href="create_project.php" class="btn btn-primary">
                                                            <i class="bi bi-plus-circle me-2"></i> Create New Project
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($projects as $project): ?>
                                                <?php
                                                $statusClass = 'secondary';
                                                $statusBadge = 'Unknown';
                                                $statusColor = '';

                                                if (strpos($project['current_status'], 'wp_conversion') !== false) {
                                                    $statusBadge = 'WP Conversion';
                                                    $statusClass = 'info';
                                                    $statusColor = 'wp';
                                                } elseif (strpos($project['current_status'], 'page_creation') !== false) {
                                                    $statusBadge = 'Page Creation';
                                                    $statusClass = 'warning';
                                                    $statusColor = 'page';
                                                } elseif (strpos($project['current_status'], 'golive') !== false) {
                                                    $statusBadge = 'Go-Live';
                                                    $statusClass = 'primary';
                                                    $statusColor = 'golive';
                                                } elseif ($project['current_status'] === 'completed') {
                                                    $statusBadge = 'Completed';
                                                    $statusClass = 'success';
                                                    $statusColor = 'completed';
                                                }
                                                ?>
                                                <tr class="project-row" data-status="<?php echo htmlspecialchars($project['current_status']); ?>">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="status-indicator status-indicator-<?php echo $statusColor; ?> me-2"></div>
                                                            <a href="view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none project-name fw-medium">
                                                                <?php echo htmlspecialchars($project['name']); ?>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusBadge; ?></span>
                                                        <?php if (strpos($project['current_status'], '_qa') !== false): ?>
                                                            <span class="badge bg-dark ms-1">In QA</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($project['project_deadline'])): ?>
                                                            <?php
                                                            $deadline = strtotime($project['project_deadline']);
                                                            $today = time();
                                                            $diff = $deadline - $today;
                                                            $days = floor($diff / (60 * 60 * 24));

                                                            $deadlineClass = 'success';
                                                            if ($days < 0) {
                                                                $deadlineClass = 'danger';
                                                            } elseif ($days <= 3) {
                                                                $deadlineClass = 'warning';
                                                            } elseif ($days <= 7) {
                                                                $deadlineClass = 'info';
                                                            }
                                                            ?>
                                                            <span class="badge bg-<?php echo $deadlineClass; ?>">
                                                                <?php echo date('M j', $deadline); ?>
                                                                <?php if ($days >= 0): ?>
                                                                    <small>(<?php echo $days; ?> days)</small>
                                                                <?php else: ?>
                                                                    <small>(overdue)</small>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-dark">Not set</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="webmaster-name">
                                                        <?php if (!empty($project['assigned_qa_username']) && $project['assigned_qa_username'] !== 'None'): ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="avatar avatar-sm bg-primary me-2">
                                                                    <?php echo strtoupper(substr($project['assigned_qa_username'], 0, 1)); ?>
                                                                </span>
                                                                <span><?php echo htmlspecialchars($project['assigned_qa_username']); ?></span>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Unassigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group">
                                                            <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                                               class="btn btn-sm btn-outline-primary"
                                                               data-bs-toggle="tooltip"
                                                               title="View Project">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="edit_project.php?id=<?php echo $project['id']; ?>"
                                                               class="btn btn-sm btn-outline-secondary"
                                                               data-bs-toggle="tooltip"
                                                               title="Edit Project">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button"
                                                                   class="btn btn-sm btn-outline-info toggle-project-details"
                                                                   data-project-id="<?php echo $project['id']; ?>"
                                                                   data-bs-toggle="tooltip"
                                                                   title="Show Details">
                                                                <i class="bi bi-plus-circle"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <!-- Expandable details row (hidden by default) -->
                                                <tr id="project-details-<?php echo $project['id']; ?>" class="d-none">
                                                    <td colspan="5">
                                                        <div class="p-3 bg-light rounded">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6 class="fw-bold mb-2">Project Details</h6>
                                                                    <p class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y', strtotime($project['created_at'])); ?></p>
                                                                    <p class="mb-1"><strong>WP Conversion Deadline:</strong>
                                                                        <?php echo !empty($project['wp_conversion_deadline']) ?
                                                                            date('M j, Y', strtotime($project['wp_conversion_deadline'])) : 'Not set'; ?>
                                                                    </p>
                                                                    <p class="mb-1"><strong>URL:</strong>
                                                                        <?php if (!empty($project['url'])): ?>
                                                                            <a href="<?php echo htmlspecialchars($project['url']); ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 300px;">
                                                                                <?php echo htmlspecialchars($project['url']); ?>
                                                                                <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            Not set
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6 class="fw-bold mb-2">Progress</h6>
                                                                    <?php
                                                                    $stages = ['wp_conversion' => 'WP Conversion', 'page_creation' => 'Page Creation', 'golive' => 'Go-Live'];
                                                                    $currentStatus = explode(',', $project['current_status']);

                                                                    foreach ($stages as $stage => $stageName):
                                                                        $isCompleted = false;
                                                                        $isActive = false;
                                                                        $progress = 0;

                                                                        if ($project['current_status'] === 'completed') {
                                                                            $isCompleted = true;
                                                                            $progress = 100;
                                                                        } elseif (in_array($stage, $currentStatus)) {
                                                                            $isActive = true;
                                                                            $progress = 50;

                                                                            // If it's in QA, progress is 75%
                                                                            if (in_array($stage.'_qa', $currentStatus)) {
                                                                                $progress = 75;
                                                                            }
                                                                        } elseif (array_search($stage, array_keys($stages)) < array_search(explode('_', $currentStatus[0])[0], array_keys($stages))) {
                                                                            // If we're past this stage, it's completed
                                                                            $isCompleted = true;
                                                                            $progress = 100;
                                                                        }

                                                                        $stageClass = $isActive ? 'primary' : ($isCompleted ? 'success' : 'secondary');
                                                                    ?>
                                                                    <div class="mb-2">
                                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                                            <span class="small"><?php echo $stageName; ?></span>
                                                                            <span class="small fw-medium"><?php echo $progress; ?>%</span>
                                                                        </div>
                                                                        <div class="progress" style="height: 6px;">
                                                                            <div class="progress-bar bg-<?php echo $stageClass; ?>"
                                                                                 role="progressbar"
                                                                                 style="width: <?php echo $progress; ?>%;"
                                                                                 aria-valuenow="<?php echo $progress; ?>"
                                                                                 aria-valuemin="0"
                                                                                 aria-valuemax="100"></div>
                                                                        </div>
                                                                    </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                        <div class="card-footer d-flex justify-content-center bg-white py-3">
                            <nav aria-label="Projects pagination">
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Webmaster Content: Deadlines Timeline -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Upcoming Deadlines</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $upcoming_deadlines = [];
                            foreach ($projects as $project) {
                                if (!empty($project['wp_conversion_deadline'])) {
                                    $upcoming_deadlines[] = [
                                        'project_id' => $project['id'],
                                        'project_name' => $project['name'],
                                        'deadline' => $project['wp_conversion_deadline'],
                                        'type' => 'WP Conversion',
                                        'icon' => 'wordpress'
                                    ];
                                }
                                if (!empty($project['project_deadline'])) {
                                    $upcoming_deadlines[] = [
                                        'project_id' => $project['id'],
                                        'project_name' => $project['name'],
                                        'deadline' => $project['project_deadline'],
                                        'type' => 'Project Completion',
                                        'icon' => 'check-circle'
                                    ];
                                }
                            }

                            // Sort by deadline (ascending)
                            usort($upcoming_deadlines, function($a, $b) {
                                return strtotime($a['deadline']) - strtotime($b['deadline']);
                            });

                            // Keep only upcoming (and recent) deadlines
                            $filtered_deadlines = array_filter($upcoming_deadlines, function($deadline) {
                                $deadline_time = strtotime($deadline['deadline']);
                                $past_days = 3; // Show deadlines from up to 3 days ago
                                return $deadline_time >= strtotime("-$past_days days");
                            });

                            // Limit to next 5
                            $filtered_deadlines = array_slice($filtered_deadlines, 0, 5);
                            ?>

                            <?php if (empty($filtered_deadlines)): ?>
                                <div class="empty-state text-center py-4">
                                    <i class="bi bi-calendar-check display-4 text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No upcoming deadlines.</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($filtered_deadlines as $i => $deadline): ?>
                                        <?php
                                        $deadline_time = strtotime($deadline['deadline']);
                                        $today = time();
                                        $diff = $deadline_time - $today;
                                        $days = ceil($diff / (60 * 60 * 24));

                                        $deadlineClass = 'success';
                                        if ($days < 0) {
                                            $deadlineClass = 'danger';
                                        } elseif ($days <= 3) {
                                            $deadlineClass = 'warning';
                                        }
                                        ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-<?php echo $deadlineClass; ?>">
                                                <i class="bi bi-<?php echo $deadline['icon']; ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($deadline['project_name']); ?></h6>
                                                    <span class="badge bg-<?php echo $deadlineClass; ?> rounded-pill">
                                                        <?php if ($days < 0): ?>
                                                            <?php echo abs($days); ?> days overdue
                                                        <?php elseif ($days === 0): ?>
                                                            Today
                                                        <?php else: ?>
                                                            In <?php echo $days; ?> days
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <p class="mb-0"><span class="badge bg-light text-dark"><?php echo $deadline['type']; ?></span></p>
                                                        <small class="text-muted"><?php echo date('F j, Y', $deadline_time); ?></small>
                                                    </div>
                                                    <a href="view_project.php?id=<?php echo $deadline['project_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye me-1"></i> View
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Webmaster Content: Recent Activity -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Activity</h5>
                            <a href="activity_log.php" class="btn btn-sm btn-link">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php
                                // You would need to fetch activity logs from database
                                // Here's a static example:
                                $activities = [
                                    [
                                        'type' => 'qa_feedback',
                                        'message' => 'QA feedback received on Project XYZ',
                                        'time' => '2 hours ago',
                                        'icon' => 'chat-right-text',
                                        'color' => 'primary'
                                    ],
                                    [
                                        'type' => 'status_change',
                                        'message' => 'Project ABC moved to Page Creation',
                                        'time' => '1 day ago',
                                        'icon' => 'arrow-right-circle',
                                        'color' => 'success'
                                    ],
                                    [
                                        'type' => 'deadline_set',
                                        'message' => 'WP Conversion deadline set for Project DEF',
                                        'time' => '2 days ago',
                                        'icon' => 'calendar-event',
                                        'color' => 'info'
                                    ]
                                ];

                                if (empty($activities)):
                                ?>
                                <li class="list-group-item text-center py-4">
                                    <p class="text-muted mb-0">No recent activity.</p>
                                </li>
                                <?php else: foreach ($activities as $activity): ?>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon bg-light-<?php echo $activity['color']; ?> text-<?php echo $activity['color']; ?> rounded-circle p-2 me-3">
                                            <i class="bi bi-<?php echo $activity['icon']; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <p class="mb-0"><?php echo $activity['message']; ?></p>
                                            <small class="text-muted"><?php echo $activity['time']; ?></small>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php elseif ($user_role === 'qa_reporter'): ?>
    <!-- QA Reporter Content: Projects Requiring QA -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <h5 class="mb-0">Projects Requiring QA</h5>
            <span class="badge bg-primary rounded-pill" id="visible-projects-count"><?php echo count($projects); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Status</th>
                            <th>Webmaster</th>
                            <th>Deadline</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($projects) === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="bi bi-clipboard-check display-4 text-muted mb-3"></i>
                                        <p class="text-muted mb-0">No projects are currently assigned to you for QA.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($projects as $project): ?>
                                <?php
                                $statusClass = 'secondary';
                                $statusBadge = 'Unknown';
                                $statusColor = '';
                                $qaStage = '';

                                if (strpos($project['current_status'], 'wp_conversion_qa') !== false) {
                                    $statusBadge = 'WP Conversion QA';
                                    $statusClass = 'info';
                                    $statusColor = 'wp';
                                    $qaStage = 'wp_conversion';
                                } elseif (strpos($project['current_status'], 'page_creation_qa') !== false) {
                                    $statusBadge = 'Page Creation QA';
                                    $statusClass = 'warning';
                                    $statusColor = 'page';
                                    $qaStage = 'page_creation';
                                } elseif (strpos($project['current_status'], 'golive_qa') !== false) {
                                    $statusBadge = 'Go-Live QA';
                                    $statusClass = 'primary';
                                    $statusColor = 'golive';
                                    $qaStage = 'golive';
                                }

                                // Get qa progress
                                $qa_items_query = "SELECT COUNT(*) as total,
                                   SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed,
                                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                                   SUM(CASE WHEN status = 'not_checked' OR status IS NULL THEN 1 ELSE 0 END) as not_checked
                                   FROM project_checklist_status
                                   WHERE project_id = ? AND stage = ?";
                                $stmt = $conn->prepare($qa_items_query);
                                $stmt->bind_param("is", $project['id'], $qaStage);
                                $stmt->execute();
                                $qa_stats = $stmt->get_result()->fetch_assoc();

                                $progressPercentage = 0;
                                if ($qa_stats['total'] > 0) {
                                    $progressPercentage = round(($qa_stats['passed'] + $qa_stats['failed']) / $qa_stats['total'] * 100);
                                }
                                ?>
                                <tr class="project-row" data-status="<?php echo htmlspecialchars($qaStage); ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="status-indicator status-indicator-<?php echo $statusColor; ?> me-2"></div>
                                            <a href="qa_review.php?id=<?php echo $project['id']; ?>&stage=<?php echo $qaStage; ?>"
                                               class="text-decoration-none project-name fw-medium">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusBadge; ?></span>
                                        <div class="progress mt-1" style="height: 5px; width: 100px;">
                                            <?php
                                            $progressClass = 'primary';
                                            if ($progressPercentage == 100) {
                                                $progressClass = 'success';
                                            } elseif ($progressPercentage > 0) {
                                                $progressClass = 'info';
                                            }
                                            ?>
                                            <div class="progress-bar bg-<?php echo $progressClass; ?>"
                                                 role="progressbar"
                                                 style="width: <?php echo $progressPercentage; ?>%"
                                                 aria-valuenow="<?php echo $progressPercentage; ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $progressPercentage; ?>% complete</small>
                                    </td>
                                    <td class="webmaster-name">
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-sm bg-secondary me-2">
                                                <?php echo strtoupper(substr($project['webmaster_name'], 0, 1)); ?>
                                            </span>
                                            <span><?php echo htmlspecialchars($project['webmaster_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $deadlineField = 'project_deadline';
                                        if ($qaStage === 'wp_conversion') {
                                            $deadlineField = 'wp_conversion_deadline';
                                        }

                                        if (!empty($project[$deadlineField])):
                                            $deadline = strtotime($project[$deadlineField]);
                                            $today = time();
                                            $diff = $deadline - $today;
                                            $days = floor($diff / (60 * 60 * 24));

                                            $deadlineClass = 'success';
                                            if ($days < 0) {
                                                $deadlineClass = 'danger';
                                            } elseif ($days <= 3) {
                                                $deadlineClass = 'warning';
                                            } elseif ($days <= 7) {
                                                $deadlineClass = 'info';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $deadlineClass; ?>">
                                                <?php echo date('M j', $deadline); ?>
                                                <?php if ($days >= 0): ?>
                                                    <small>(<?php echo $days; ?> days)</small>
                                                <?php else: ?>
                                                    <small>(overdue)</small>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="qa_review.php?id=<?php echo $project['id']; ?>&stage=<?php echo $qaStage; ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="tooltip"
                                               title="Perform QA">
                                                <i class="bi bi-clipboard-check"></i>
                                            </a>
                                            <a href="project_preview.php?id=<?php echo $project['id']; ?>"
                                               class="btn btn-sm btn-outline-info"
                                               data-bs-toggle="tooltip"
                                               title="Preview Site">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button"
                                                   class="btn btn-sm btn-outline-secondary toggle-project-details"
                                                   data-project-id="<?php echo $project['id']; ?>"
                                                   data-bs-toggle="tooltip"
                                                   title="Show Details">
                                                <i class="bi bi-plus-circle"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Expandable details row (hidden by default) -->
                                <tr id="project-details-<?php echo $project['id']; ?>" class="d-none">
                                    <td colspan="5">
                                        <div class="p-3 bg-light rounded">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="fw-bold mb-2">Project Details</h6>
                                                    <p class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y', strtotime($project['created_at'])); ?></p>
                                                    <p class="mb-1"><strong>URL:</strong>
                                                        <?php if (!empty($project['url'])): ?>
                                                            <a href="<?php echo htmlspecialchars($project['url']); ?>" target="_blank">
                                                                <?php echo htmlspecialchars($project['url']); ?>
                                                                <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            Not set
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="fw-bold mb-2">QA Progress</h6>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="me-3">
                                                            <span class="badge bg-success"><?php echo $qa_stats['passed']; ?></span>
                                                            <small class="text-muted d-block">Passed</small>
                                                        </div>
                                                        <div class="me-3">
                                                            <span class="badge bg-danger"><?php echo $qa_stats['failed']; ?></span>
                                                            <small class="text-muted d-block">Failed</small>
                                                        </div>
                                                        <div>
                                                            <span class="badge bg-secondary"><?php echo $qa_stats['not_checked']; ?></span>
                                                            <small class="text-muted d-block">Not Checked</small>
                                                        </div>
                                                    </div>

                                                    <?php if ($qa_stats['failed'] > 0): ?>
                                                        <div class="alert alert-warning p-2 small mb-0">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                                            This project has <?php echo $qa_stats['failed']; ?> failing item(s) that need attention.
                                                        </div>
                                                    <?php elseif ($progressPercentage == 100 && $qa_stats['failed'] == 0): ?>
                                                        <div class="alert alert-success p-2 small mb-0">
                                                            <i class="bi bi-check-circle me-1"></i>
                                                            All checks have passed. Ready to approve.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- QA Reporter Content: Recent Activity -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent QA Activity</h5>
            <a href="qa_history.php" class="btn btn-sm btn-link">View All</a>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php
                // Fetch QA activity from database (placeholder data)
                $qa_activities = [
                    [
                        'project_name' => 'Project Alpha',
                        'status' => 'Completed QA',
                        'stage' => 'wp_conversion',
                        'time' => '2 hours ago',
                        'icon' => 'check-circle',
                        'color' => 'success'
                    ],
                    [
                        'project_name' => 'Project Beta',
                        'status' => 'Started QA',
                        'stage' => 'page_creation',
                        'time' => '1 day ago',
                        'icon' => 'clipboard',
                        'color' => 'info'
                    ],
                    [
                        'project_name' => 'Project Gamma',
                        'status' => 'Submitted feedback',
                        'stage' => 'golive',
                        'time' => '3 days ago',
                        'icon' => 'chat-right-text',
                        'color' => 'warning'
                    ]
                ];

                if (empty($qa_activities)):
                ?>
                <li class="list-group-item text-center py-4">
                    <p class="text-muted mb-0">No recent QA activity.</p>
                </li>
                <?php else: foreach ($qa_activities as $activity): ?>
                <li class="list-group-item">
                    <div class="d-flex align-items-center">
                        <div class="activity-icon bg-light-<?php echo $activity['color']; ?> text-<?php echo $activity['color']; ?> rounded-circle p-2 me-3">
                            <i class="bi bi-<?php echo $activity['icon']; ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-0">
                                <strong><?php echo htmlspecialchars($activity['project_name']); ?></strong>:
                                <?php echo $activity['status']; ?>
                                <span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $activity['stage'])); ?></span>
                            </p>
                            <small class="text-muted"><?php echo $activity['time']; ?></small>
                        </div>
                    </div>
                </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>

    <!-- QA Reporter Content: Performance Metrics -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Your QA Performance</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="display-6 fw-bold text-primary mb-2">92%</div>
                        <h6 class="text-muted mb-0">Issue Detection Rate</h6>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="display-6 fw-bold text-success mb-2">6.4h</div>
                        <h6 class="text-muted mb-0">Avg. Completion Time</h6>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="display-6 fw-bold text-info mb-2">24</div>
                        <h6 class="text-muted mb-0">QAs Completed</h6>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <h6 class="fw-bold mb-3">Most Common Issues Found</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Issue Type</th>
                            <th>Stage</th>
                            <th class="text-end">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Missing meta descriptions</td>
                            <td><span class="badge bg-info">WP Conversion</span></td>
                            <td class="text-end">18</td>
                        </tr>
                        <tr>
                            <td>Broken links</td>
                            <td><span class="badge bg-warning">Page Creation</span></td>
                            <td class="text-end">15</td>
                        </tr>
                        <tr>
                            <td>Mobile responsiveness issues</td>
                            <td><span class="badge bg-primary">Go-Live</span></td>
                            <td class="text-end">12</td>
                        </tr>
                        <tr>
                            <td>Image optimization</td>
                            <td><span class="badge bg-warning">Page Creation</span></td>
                            <td class="text-end">9</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php elseif (in_array($user_role, ['admin', 'qa_manager'])): ?>
    <!-- Admin Content: QA Assignments Needed -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <h5 class="mb-0">Projects Needing QA Assignment</h5>
            <?php if (count($unassigned_projects) > 0): ?>
                <span class="badge bg-danger rounded-pill"><?php echo count($unassigned_projects); ?></span>
            <?php else: ?>
                <span class="badge bg-success rounded-pill">0</span>
            <?php endif; ?>
        </div>
        <?php if (count($unassigned_projects) === 0): ?>
            <div class="card-body text-center py-4">
                <i class="bi bi-check-circle-fill display-4 text-success mb-3"></i>
                <p class="text-muted">All projects have been assigned to QA reporters.</p>
            </div>
        <?php else: ?>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unassigned_projects as $project): ?>
                                <?php
                                $statusClass = 'secondary';
                                $statusBadge = 'Unknown';
                                $statusColor = '';

                                if (strpos($project['current_status'], 'wp_conversion_qa') !== false) {
                                    $statusBadge = 'WP Conversion QA';
                                    $statusClass = 'info';
                                    $statusColor = 'wp';
                                } elseif (strpos($project['current_status'], 'page_creation_qa') !== false) {
                                    $statusBadge = 'Page Creation QA';
                                    $statusClass = 'warning';
                                    $statusColor = 'page';
                                } elseif (strpos($project['current_status'], 'golive_qa') !== false) {
                                    $statusBadge = 'Go-Live QA';
                                    $statusClass = 'primary';
                                    $statusColor = 'golive';
                                }
                                ?>
                                <tr class="project-row">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="status-indicator status-indicator-<?php echo $statusColor; ?> me-2"></div>
                                            <a href="view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none project-name fw-medium">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusBadge; ?></span></td>
                                    <td class="webmaster-name">
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-sm bg-secondary me-2">
                                                <?php echo strtoupper(substr($project['webmaster_name'], 0, 1)); ?>
                                            </span>
                                            <span><?php echo htmlspecialchars($project['webmaster_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('M j, Y', strtotime($project['created_at'])); ?></small></td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignQAModal"
                                                data-project-id="<?php echo $project['id']; ?>"
                                                data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                            <i class="bi bi-person-plus me-1"></i> Assign QA
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Admin Content: Projects By Stage -->
    <div class="row">
        <!-- WP Conversion QA Projects -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">WP Conversion QA</h5>
                    <span class="badge bg-info rounded-pill"><?php echo count($wp_conversion_qa_projects); ?></span>
                </div>
                <?php if (count($wp_conversion_qa_projects) === 0): ?>
                    <div class="card-body text-center py-4">
                        <i class="bi bi-wordpress display-4 text-muted mb-3"></i>
                        <p class="text-muted">No projects in WP Conversion QA stage.</p>
                    </div>
                <?php else: ?>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($wp_conversion_qa_projects, 0, 5) as $project): ?>
                                <div class="list-group-item border-0 py-3">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 fw-bold">
                                            <a href="view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </a>
                                        </h6>
                                        <?php if ($project['assigned_qa_username'] !== 'Unassigned'): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="bi bi-person-check me-1"></i>
                                                <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Webmaster: <?php echo htmlspecialchars($project['webmaster_name']); ?></small>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($project['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($wp_conversion_qa_projects) > 5): ?>
                        <div class="card-footer text-center bg-white">
                            <a href="projects.php?stage=wp_conversion_qa" class="btn btn-sm btn-light">View All <?php echo count($wp_conversion_qa_projects); ?></a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Page Creation QA Projects -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Page Creation QA</h5>
                    <span class="badge bg-warning rounded-pill"><?php echo count($page_creation_qa_projects); ?></span>
                </div>
                <?php if (count($page_creation_qa_projects) === 0): ?>
                    <div class="card-body text-center py-4">
                        <i class="bi bi-file-earmark-text display-4 text-muted mb-3"></i>
                        <p class="text-muted">No projects in Page Creation QA stage.</p>
                    </div>
                <?php else: ?>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($page_creation_qa_projects, 0, 5) as $project): ?>
                                <div class="list-group-item border-0 py-3">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 fw-bold">
                                            <a href="view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </a>
                                        </h6>
                                        <?php if ($project['assigned_qa_username'] !== 'Unassigned'): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="bi bi-person-check me-1"></i>
                                                <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Webmaster: <?php echo htmlspecialchars($project['webmaster_name']); ?></small>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($project['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($page_creation_qa_projects) > 5): ?>
                        <div class="card-footer text-center bg-white">
                            <a href="projects.php?stage=page_creation_qa" class="btn btn-sm btn-light">View All <?php echo count($page_creation_qa_projects); ?></a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Go-Live QA Projects -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Go-Live QA</h5>
                    <span class="badge bg-primary rounded-pill"><?php echo count($golive_qa_projects); ?></span>
                </div>
                <?php if (count($golive_qa_projects) === 0): ?>
                    <div class="card-body text-center py-4">
                        <i class="bi bi-rocket-takeoff display-4 text-muted mb-3"></i>
                        <p class="text-muted">No projects in Go-Live QA stage.</p>
                    </div>
                <?php else: ?>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($golive_qa_projects, 0, 5) as $project): ?>
                                <div class="list-group-item border-0 py-3">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 fw-bold">
                                            <a href="view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </a>
                                        </h6>
                                        <?php if ($project['assigned_qa_username'] !== 'Unassigned'): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="bi bi-person-check me-1"></i>
                                                <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Webmaster: <?php echo htmlspecialchars($project['webmaster_name']); ?></small>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($project['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($golive_qa_projects) > 5): ?>
                        <div class="card-footer text-center bg-white">
                            <a href="projects.php?stage=golive_qa" class="btn btn-sm btn-light">View All <?php echo count($golive_qa_projects); ?></a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
            </main>
        </div>
    </div>
</div>

<!-- QA Assignment Modal for Admin/QA Manager -->
<?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
<div class="modal fade" id="assignQAModal" tabindex="-1" aria-labelledby="assignQAModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignQAModalLabel">Assign QA Reporter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="dashboard.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="project_id" id="modal-project-id">
                    <div class="mb-3">
                        <label for="project-name" class="form-label">Project</label>
                        <input type="text" class="form-control" id="modal-project-name" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="qa_user_id" class="form-label">Assign to QA Reporter</label>
                        <select class="form-select" name="qa_user_id" id="qa_user_id" required>
                            <option value="">Select a QA Reporter</option>
                            <?php foreach ($qa_reporters as $reporter): ?>
                                <option value="<?php echo $reporter['id']; ?>"><?php echo htmlspecialchars($reporter['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_qa" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script to update modal values when opened
document.addEventListener('DOMContentLoaded', function() {
    const assignQAModal = document.getElementById('assignQAModal');
    if (assignQAModal) {
        assignQAModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data attributes
            const projectId = button.getAttribute('data-project-id');
            const projectName = button.getAttribute('data-project-name');

            // Update the modal's content
            const modalProjectId = assignQAModal.querySelector('#modal-project-id');
            const modalProjectName = assignQAModal.querySelector('#modal-project-name');

            modalProjectId.value = projectId;
            modalProjectName.value = projectName;
        });
    }
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/dashboard.js"></script>
</body>
</html>