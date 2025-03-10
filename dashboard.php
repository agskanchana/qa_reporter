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

// Add this near the top of the file, after authentication checks
// but before displaying any content

// Check for missed deadlines
require_once 'includes/check_missed_deadlines.php';

// Handle QA Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user_role, ['admin', 'qa_manager'])) {
    include_once 'includes/dashboard/handle_assignment.php';
}

// Get all QA reporters for assignment dropdown
 require_once 'includes/dashboard/project_queries.php';


// Add after the webmaster_projects query
require_once 'includes/dashboard/qa_reporter_queries.php';
// Add this after user role check, before the header inclusion

require_once 'includes/header.php';

// Add this code right after the header include and before the container div

require_once 'includes/dashboard/notification.php';

?>

<div class="container-fluid">


    <div class="row">
        <!-- Rest of your dashboard content -->
    <div class="row">


            <main class="col-md-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard </h1>
                    </div>
                <div class="row">

        <div class="col-md-3">

         <!-- Add the notification area here, right after the container-fluid -->
<?php
  require_once 'includes/dashboard/sidebar_content.php';
?>


</div>


        <main class="col-md-9">

<?php if ($user_role === 'webmaster'): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h4>My Projects</h4>
        </div>
        <div class="card-body">
            <?php if (!empty($projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            ['wp_conversion'];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php else: ?>
                <p class="text-muted">No projects found</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($user_role === 'webmaster'): ?>
<!-- Add this to the webmaster dashboard section -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Missed Deadlines Requiring Action</h5>
    </div>
    <div class="card-body">
        <?php
        // Get missed deadlines for this webmaster that need reasons
        $query = "SELECT md.id, md.deadline_type, md.original_deadline, p.name as project_name
                  FROM missed_deadlines md
                  JOIN projects p ON md.project_id = p.id
                  WHERE p.webmaster_id = ? AND md.reason IS NULL
                  ORDER BY md.recorded_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo '<div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Project</th>
                        <th>Deadline Type</th>
                        <th>Original Deadline</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>';

            while ($row = $result->fetch_assoc()) {
                echo '<tr>
                        <td>'.htmlspecialchars($row['project_name']).'</td>
                        <td>'.ucfirst(str_replace('_', ' ', $row['deadline_type'])).'</td>
                        <td>'.date('F j, Y', strtotime($row['original_deadline'])).'</td>
                        <td>
                          <a href="missed_deadline_reason.php?deadline_id='.$row['id'].'"
                             class="btn btn-warning btn-sm">Provide Reason</a>
                        </td>
                      </tr>';
            }

            echo '</tbody></table></div>';
        } else {
            echo '<p class="text-muted">No missed deadlines requiring action.</p>';
        }
        ?>
    </div>
</div>
<?php endif; ?>



<?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
    <!-- WP Conversion QA Projects -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>WP Conversion QA Pending
                <span class="badge bg-primary"><?php echo count($wp_conversion_qa_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($wp_conversion_qa_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wp_conversion_qa_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects pending WP Conversion QA</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page Creation QA Projects -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>Page Creation QA Pending
                <span class="badge bg-primary"><?php echo count($page_creation_qa_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($page_creation_qa_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($page_creation_qa_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td>
                                        <?php if ($project['assigned_qa_username'] === 'Unassigned'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <select name="qa_user_id" class="form-select form-select-sm d-inline-block w-auto" required>
                                                    <option value="">Assign QA</option>
                                                    <?php foreach ($qa_reporters as $qa): ?>
                                                        <option value="<?php echo $qa['id']; ?>">
                                                            <?php echo htmlspecialchars($qa['username']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="assign_qa" class="btn btn-primary btn-sm">
                                                    Assign
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects pending Page Creation QA</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Golive QA Projects -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>Golive QA Pending
                <span class="badge bg-primary"><?php echo count($golive_qa_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($golive_qa_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($golive_qa_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects pending Golive QA</p>
            <?php endif; ?>
        </div>
    </div>

     <!-- Projects with webmasters  -->
     <div class="card mb-4">
        <div class="card-header">
            <h4>Webmaster Projects
                <span class="badge bg-primary"><?php echo count($webmaster_active_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($webmaster_active_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webmaster_active_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            $status_class = 'secondary'; // Default value
                                            $status = $project['current_status'] ?? '';
                                            if ($status === 'wp_conversion') {
                                                $status_class = 'info';
                                            } elseif ($status === 'page_creation') {
                                                $status_class = 'warning';
                                            } elseif ($status === 'golive') {
                                                $status_class = 'primary';
                                            }
                                            echo $status_class;
                                        ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $project['current_status'] ?? 'Unknown')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No active webmaster projects</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- Completed Projects with Pagination -->
    <div class="card">
        <div class="card-header">
            <h4>Completed Projects
                <span class="badge bg-success"><?php echo count($completed_projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php
            $items_per_page = 10;
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $total_pages = ceil(count($completed_projects) / $items_per_page);
            $offset = ($current_page - 1) * $items_per_page;
            $paginated_projects = array_slice($completed_projects, $offset, $items_per_page);
            ?>

            <?php if (!empty($paginated_projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Assigned QA</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php else: ?>
                <p class="text-muted">No completed projects</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


<?php if ($user_role === 'qa_reporter'): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h4>My Assigned Projects (Page Creation QA)
                <span class="badge bg-primary"><?php echo count($projects); ?></span>
            </h4>
        </div>
        <div class="card-body">
            <?php if (!empty($projects)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Status</th>
                                <th>Webmaster</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td>
                                        <?php
                                        $statuses = !empty($project['current_status']) ?
                                            explode(',', $project['current_status']) :
                                            [];
                                        foreach ($statuses as $status):
                                            $status_class = 'secondary'; // Default value
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $status_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $status_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $status_class = 'primary';
                                            } elseif ($status === 'completed') {
                                                $status_class = 'success';
                                            }
                                        ?>
                                            <span class="badge bg-<?php echo $status_class; ?> me-1">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Deleted User'); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                    <td>
                                        <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                           class="btn btn-info btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No projects assigned for Page Creation QA</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/dashboard.js"></script>
</body>
</html>