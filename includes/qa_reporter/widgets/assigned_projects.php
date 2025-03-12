<?php
// Get auto-assign settings to determine which tabs to show
$auto_assign_wp = false;
$auto_assign_golive = false;

// Check if auto_assign_to_admin table exists
$table_check = $conn->query("SHOW TABLES LIKE 'auto_assign_to_admin'");
if ($table_check && $table_check->num_rows > 0) {
    $settings_query = "SELECT setting_key, is_enabled FROM auto_assign_to_admin";
    $settings_result = $conn->query($settings_query);

    if ($settings_result) {
        while ($row = $settings_result->fetch_assoc()) {
            if ($row['setting_key'] == 'wp_conversion') {
                $auto_assign_wp = (bool)$row['is_enabled'];
            } elseif ($row['setting_key'] == 'golive') {
                $auto_assign_golive = (bool)$row['is_enabled'];
            }
        }
    }
}

// Get WP conversion QA projects assigned to this user
$wp_conversion_projects_query = "SELECT p.*,
                              u.username as webmaster_name
                              FROM projects p
                              JOIN qa_assignments qa ON p.id = qa.project_id
                              LEFT JOIN users u ON p.webmaster_id = u.id
                              WHERE qa.qa_user_id = ?
                              AND p.current_status LIKE '%wp_conversion_qa%'
                              ORDER BY p.created_at DESC";
$stmt = $conn->prepare($wp_conversion_projects_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wp_conversion_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get page creation QA projects assigned to this user
$page_creation_projects_query = "SELECT p.*,
                             u.username as webmaster_name
                             FROM projects p
                             JOIN qa_assignments qa ON p.id = qa.project_id
                             LEFT JOIN users u ON p.webmaster_id = u.id
                             WHERE qa.qa_user_id = ?
                             AND p.current_status LIKE '%page_creation_qa%'
                             ORDER BY p.created_at DESC";
$stmt = $conn->prepare($page_creation_projects_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$page_creation_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get golive QA projects assigned to this user
$golive_projects_query = "SELECT p.*,
                       u.username as webmaster_name
                       FROM projects p
                       JOIN qa_assignments qa ON p.id = qa.project_id
                       LEFT JOIN users u ON p.webmaster_id = u.id
                       WHERE qa.qa_user_id = ?
                       AND p.current_status LIKE '%golive_qa%'
                       ORDER BY p.created_at DESC";
$stmt = $conn->prepare($golive_projects_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$golive_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total projects assigned to this user
$total_assigned_projects = count($wp_conversion_projects) + count($page_creation_projects) + count($golive_projects);

// Determine which tab should be active by default
$active_tab = 'page-creation';
if (!$auto_assign_wp && count($wp_conversion_projects) > 0) {
    $active_tab = 'wp-conversion';
} else if (!$auto_assign_golive && count($golive_projects) > 0) {
    $active_tab = 'golive';
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-clipboard-check me-2 text-primary"></i>
            My Assigned Projects
            <span class="badge bg-primary ms-2"><?php echo $total_assigned_projects; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if ($total_assigned_projects === 0): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-clipboard text-muted" style="font-size: 2.5rem;"></i>
                </div>
                <h6 class="fw-normal text-muted">No projects assigned to you at the moment</h6>
                <p class="text-muted small">
                    Projects will appear here when they are assigned to you for QA.
                </p>
            </div>
        <?php else: ?>
            <ul class="nav nav-tabs" id="myProjectsTabs" role="tablist">
                <?php if (!$auto_assign_wp && count($wp_conversion_projects) > 0): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab == 'wp-conversion' ? 'active' : ''; ?>"
                        id="wp-conversion-tab" data-bs-toggle="tab"
                        data-bs-target="#wp-conversion" type="button" role="tab"
                        aria-controls="wp-conversion" aria-selected="<?php echo $active_tab == 'wp-conversion' ? 'true' : 'false'; ?>">
                        <i class="bi bi-wordpress me-1 text-info"></i>
                        WP Conversion
                        <span class="badge bg-info ms-1"><?php echo count($wp_conversion_projects); ?></span>
                    </button>
                </li>
                <?php endif; ?>

                <?php if (count($page_creation_projects) > 0): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab == 'page-creation' ? 'active' : ''; ?>"
                        id="page-creation-tab" data-bs-toggle="tab"
                        data-bs-target="#page-creation" type="button" role="tab"
                        aria-controls="page-creation" aria-selected="<?php echo $active_tab == 'page-creation' ? 'true' : 'false'; ?>">
                        <i class="bi bi-file-earmark-plus me-1 text-warning"></i>
                        Page Creation
                        <span class="badge bg-warning text-dark ms-1"><?php echo count($page_creation_projects); ?></span>
                    </button>
                </li>
                <?php endif; ?>

                <?php if (!$auto_assign_golive && count($golive_projects) > 0): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $active_tab == 'golive' ? 'active' : ''; ?>"
                        id="golive-tab" data-bs-toggle="tab"
                        data-bs-target="#golive" type="button" role="tab"
                        aria-controls="golive" aria-selected="<?php echo $active_tab == 'golive' ? 'true' : 'false'; ?>">
                        <i class="bi bi-rocket me-1 text-success"></i>
                        Go-Live
                        <span class="badge bg-success ms-1"><?php echo count($golive_projects); ?></span>
                    </button>
                </li>
                <?php endif; ?>
            </ul>

            <div class="tab-content p-3 border border-top-0 rounded-bottom" id="myProjectsTabContent">
                <?php if (!$auto_assign_wp && count($wp_conversion_projects) > 0): ?>
                <!-- WP Conversion QA Tab -->
                <div class="tab-pane fade <?php echo $active_tab == 'wp-conversion' ? 'show active' : ''; ?>"
                    id="wp-conversion" role="tabpanel" aria-labelledby="wp-conversion-tab">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Webmaster</th>
                                    <th>Deadline</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wp_conversion_projects as $project): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </a>
                                        <div class="small text-muted">
                                            Created: <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-primary text-white me-2">
                                                <?php echo !empty($project['webmaster_name']) ? substr($project['webmaster_name'], 0, 1) : '?'; ?>
                                            </div>
                                            <?php echo htmlspecialchars($project['webmaster_name'] ?? 'Unassigned'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $deadline = new DateTime($project['project_deadline']);
                                        $today = new DateTime();
                                        $interval = $today->diff($deadline);
                                        $days_remaining = $deadline >= $today ? $interval->days : -$interval->days;

                                        if ($days_remaining < 0) {
                                            echo '<span class="badge bg-danger">Overdue by ' . abs($days_remaining) . ' days</span>';
                                        } elseif ($days_remaining == 0) {
                                            echo '<span class="badge bg-warning text-dark">Due today</span>';
                                        } elseif ($days_remaining <= 3) {
                                            echo '<span class="badge bg-warning text-dark">' . $days_remaining . ' days left</span>';
                                        } else {
                                            echo '<span class="badge bg-success">' . $days_remaining . ' days left</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-clipboard-check me-1"></i> Start QA
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (count($page_creation_projects) > 0): ?>
                <!-- Page Creation QA Tab -->
                <div class="tab-pane fade <?php echo $active_tab == 'page-creation' ? 'show active' : ''; ?>"
                    id="page-creation" role="tabpanel" aria-labelledby="page-creation-tab">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Webmaster</th>
                                    <th>Deadline</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_creation_projects as $project): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </a>
                                        <div class="small text-muted">
                                            Created: <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-primary text-white me-2">
                                                <?php echo !empty($project['webmaster_name']) ? substr($project['webmaster_name'], 0, 1) : '?'; ?>
                                            </div>
                                            <?php echo htmlspecialchars($project['webmaster_name'] ?? 'Unassigned'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $deadline = new DateTime($project['project_deadline']);
                                        $today = new DateTime();
                                        $interval = $today->diff($deadline);
                                        $days_remaining = $deadline >= $today ? $interval->days : -$interval->days;

                                        if ($days_remaining < 0) {
                                            echo '<span class="badge bg-danger">Overdue by ' . abs($days_remaining) . ' days</span>';
                                        } elseif ($days_remaining == 0) {
                                            echo '<span class="badge bg-warning text-dark">Due today</span>';
                                        } elseif ($days_remaining <= 3) {
                                            echo '<span class="badge bg-warning text-dark">' . $days_remaining . ' days left</span>';
                                        } else {
                                            echo '<span class="badge bg-success">' . $days_remaining . ' days left</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-clipboard-check me-1"></i> Start QA
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$auto_assign_golive && count($golive_projects) > 0): ?>
                <!-- Go-Live QA Tab -->
                <div class="tab-pane fade <?php echo $active_tab == 'golive' ? 'show active' : ''; ?>"
                    id="golive" role="tabpanel" aria-labelledby="golive-tab">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Webmaster</th>
                                    <th>Deadline</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($golive_projects as $project): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </a>
                                        <div class="small text-muted">
                                            Created: <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-primary text-white me-2">
                                                <?php echo !empty($project['webmaster_name']) ? substr($project['webmaster_name'], 0, 1) : '?'; ?>
                                            </div>
                                            <?php echo htmlspecialchars($project['webmaster_name'] ?? 'Unassigned'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $deadline = new DateTime($project['project_deadline']);
                                        $today = new DateTime();
                                        $interval = $today->diff($deadline);
                                        $days_remaining = $deadline >= $today ? $interval->days : -$interval->days;

                                        if ($days_remaining < 0) {
                                            echo '<span class="badge bg-danger">Overdue by ' . abs($days_remaining) . ' days</span>';
                                        } elseif ($days_remaining == 0) {
                                            echo '<span class="badge bg-warning text-dark">Due today</span>';
                                        } elseif ($days_remaining <= 3) {
                                            echo '<span class="badge bg-warning text-dark">' . $days_remaining . ' days left</span>';
                                        } else {
                                            echo '<span class="badge bg-success">' . $days_remaining . ' days left</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-clipboard-check me-1"></i> Start QA
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($total_assigned_projects > 0 &&
                         ((count($wp_conversion_projects) === 0 && !$auto_assign_wp) &&
                          (count($page_creation_projects) === 0) &&
                          (count($golive_projects) === 0 && !$auto_assign_golive))): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No projects in the selected category</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($auto_assign_wp || $auto_assign_golive): ?>
            <div class="mt-3 alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <?php if ($auto_assign_wp && $auto_assign_golive): ?>
                    WP Conversion and Go-Live QA tasks are being auto-assigned to admin according to system settings.
                <?php elseif ($auto_assign_wp): ?>
                    WP Conversion QA tasks are being auto-assigned to admin according to system settings.
                <?php elseif ($auto_assign_golive): ?>
                    Go-Live QA tasks are being auto-assigned to admin according to system settings.
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>