<?php
// Get WP conversion QA projects
$wp_conversion_projects_query = "SELECT p.*,
                              u.username as webmaster_name,
                              qa.id as assignment_id,
                              IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username
                              FROM projects p
                              LEFT JOIN users u ON p.webmaster_id = u.id
                              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
                              WHERE p.current_status LIKE '%wp_conversion_qa%'
                              ORDER BY p.created_at DESC
                              LIMIT 10";
$wp_conversion_projects = $conn->query($wp_conversion_projects_query)->fetch_all(MYSQLI_ASSOC);

// Get page creation QA projects
$page_creation_projects_query = "SELECT p.*,
                             u.username as webmaster_name,
                             qa.id as assignment_id,
                             IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username
                             FROM projects p
                             LEFT JOIN users u ON p.webmaster_id = u.id
                             LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                             LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
                             WHERE p.current_status LIKE '%page_creation_qa%'
                             ORDER BY p.created_at DESC
                             LIMIT 10";
$page_creation_projects = $conn->query($page_creation_projects_query)->fetch_all(MYSQLI_ASSOC);

// Get golive QA projects
$golive_projects_query = "SELECT p.*,
                       u.username as webmaster_name,
                       qa.id as assignment_id,
                       IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username
                       FROM projects p
                       LEFT JOIN users u ON p.webmaster_id = u.id
                       LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                       LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
                       WHERE p.current_status LIKE '%golive_qa%'
                       ORDER BY p.created_at DESC
                       LIMIT 10";
$golive_projects = $conn->query($golive_projects_query)->fetch_all(MYSQLI_ASSOC);

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

// Determine which tab should be active by default
$active_tab = 'page-creation';
if (!$auto_assign_wp) {
    $active_tab = 'wp-conversion';
} else if (!$auto_assign_golive && count($golive_projects) > 0) {
    $active_tab = 'golive';
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-layers me-2 text-primary"></i>
            QA Projects
        </h5>
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs" id="qaProjectsTabs" role="tablist">
            <?php if (!$auto_assign_wp): ?>
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

            <?php if (!$auto_assign_golive): ?>
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

        <div class="tab-content p-3 border border-top-0 rounded-bottom" id="qaProjectsTabContent">
            <?php if (!$auto_assign_wp): ?>
            <!-- WP Conversion QA Tab -->
            <div class="tab-pane fade <?php echo $active_tab == 'wp-conversion' ? 'show active' : ''; ?>"
                id="wp-conversion" role="tabpanel" aria-labelledby="wp-conversion-tab">
                <?php if (empty($wp_conversion_projects)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No projects currently in WP Conversion QA stage</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Webmaster</th>
                                    <th>QA Reporter</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wp_conversion_projects as $project): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
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
                                        <?php if ($project['assigned_qa_username'] !== 'Unassigned'): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($project['assigned_qa_username'] === 'Unassigned'): ?>
                                        <button type="button" class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignQAModal"
                                                data-project-id="<?php echo $project['id']; ?>"
                                                data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                            <i class="bi bi-person-plus me-1"></i> Assign QA
                                        </button>
                                        <?php else: ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#assignQAModal"
                                                    data-project-id="<?php echo $project['id']; ?>"
                                                    data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                                <i class="bi bi-arrow-repeat"></i> Reassign
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (count($wp_conversion_projects) >= 10): ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>/projects.php?status=wp_conversion_qa" class="btn btn-sm btn-outline-primary">
                            View All WP Conversion QA Projects
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Page Creation QA Tab -->
            <div class="tab-pane fade <?php echo $active_tab == 'page-creation' ? 'show active' : ''; ?>"
                id="page-creation" role="tabpanel" aria-labelledby="page-creation-tab">
                <?php if (empty($page_creation_projects)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No projects currently in Page Creation QA stage</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Webmaster</th>
                                    <th>QA Reporter</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_creation_projects as $project): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
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
                                        <?php if ($project['assigned_qa_username'] !== 'Unassigned'): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($project['assigned_qa_username'] === 'Unassigned'): ?>
                                        <button type="button" class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignQAModal"
                                                data-project-id="<?php echo $project['id']; ?>"
                                                data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                            <i class="bi bi-person-plus me-1"></i> Assign QA
                                        </button>
                                        <?php else: ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#assignQAModal"
                                                    data-project-id="<?php echo $project['id']; ?>"
                                                    data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                                <i class="bi bi-arrow-repeat"></i> Reassign
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (count($page_creation_projects) >= 10): ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>/projects.php?status=page_creation_qa" class="btn btn-sm btn-outline-primary">
                            View All Page Creation QA Projects
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!$auto_assign_golive): ?>
            <!-- Go-Live QA Tab -->
            <div class="tab-pane fade <?php echo $active_tab == 'golive' ? 'show active' : ''; ?>"
                id="golive" role="tabpanel" aria-labelledby="golive-tab">
                <?php if (empty($golive_projects)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No projects currently in Go-Live QA stage</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Webmaster</th>
                                    <th>QA Reporter</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($golive_projects as $project): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
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
                                        <?php if ($project['assigned_qa_username'] !== 'Unassigned'): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($project['assigned_qa_username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($project['assigned_qa_username'] === 'Unassigned'): ?>
                                        <button type="button" class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignQAModal"
                                                data-project-id="<?php echo $project['id']; ?>"
                                                data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                            <i class="bi bi-person-plus me-1"></i> Assign QA
                                        </button>
                                        <?php else: ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#assignQAModal"
                                                    data-project-id="<?php echo $project['id']; ?>"
                                                    data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                                <i class="bi bi-arrow-repeat"></i> Reassign
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (count($golive_projects) >= 10): ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>/projects.php?status=golive_qa" class="btn btn-sm btn-outline-primary">
                            View All Go-Live QA Projects
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($auto_assign_wp && $auto_assign_golive): ?>
        <div class="mt-3 alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            WP Conversion and Go-Live QA tasks are being auto-assigned to admin according to system settings.
        </div>
        <?php elseif ($auto_assign_wp): ?>
        <div class="mt-3 alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            WP Conversion QA tasks are being auto-assigned to admin according to system settings.
        </div>
        <?php elseif ($auto_assign_golive): ?>
        <div class="mt-3 alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Go-Live QA tasks are being auto-assigned to admin according to system settings.
        </div>
        <?php endif; ?>
    </div>
</div>