<?php
// filepath: c:\wamp64\www\qa_reporter\includes\admin\widgets\qa_projects_tabs.php

// Get ALL WP conversion QA projects (removed LIMIT)
$wp_conversion_projects_query = "SELECT p.*,
                              u.username as webmaster_name,
                              qa.id as assignment_id,
                              IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username
                              FROM projects p
                              LEFT JOIN users u ON p.webmaster_id = u.id
                              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
                              WHERE p.current_status LIKE '%wp_conversion_qa%'
                              ORDER BY p.created_at DESC";
$wp_conversion_projects = $conn->query($wp_conversion_projects_query)->fetch_all(MYSQLI_ASSOC);

// Get ALL page creation QA projects (removed LIMIT)
$page_creation_projects_query = "SELECT p.*,
                             u.username as webmaster_name,
                             qa.id as assignment_id,
                             IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username
                             FROM projects p
                             LEFT JOIN users u ON p.webmaster_id = u.id
                             LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                             LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
                             WHERE p.current_status LIKE '%page_creation_qa%'
                             ORDER BY p.created_at DESC";
$page_creation_projects = $conn->query($page_creation_projects_query)->fetch_all(MYSQLI_ASSOC);

// Get ALL golive QA projects (removed LIMIT)
$golive_projects_query = "SELECT p.*,
                       u.username as webmaster_name,
                       qa.id as assignment_id,
                       IFNULL(qa_user.username, 'Unassigned') as assigned_qa_username
                       FROM projects p
                       LEFT JOIN users u ON p.webmaster_id = u.id
                       LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                       LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
                       WHERE p.current_status LIKE '%golive_qa%'
                       ORDER BY p.created_at DESC";
$golive_projects = $conn->query($golive_projects_query)->fetch_all(MYSQLI_ASSOC);
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
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="wp-conversion-tab" data-bs-toggle="tab"
                    data-bs-target="#wp-conversion" type="button" role="tab"
                    aria-controls="wp-conversion" aria-selected="true">
                    <i class="bi bi-wordpress me-1 text-info"></i>
                    WP Conversion
                    <span class="badge bg-info ms-1"><?php echo count($wp_conversion_projects); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="page-creation-tab" data-bs-toggle="tab"
                    data-bs-target="#page-creation" type="button" role="tab"
                    aria-controls="page-creation" aria-selected="false">
                    <i class="bi bi-file-earmark-plus me-1 text-warning"></i>
                    Page Creation
                    <span class="badge bg-warning text-dark ms-1"><?php echo count($page_creation_projects); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="golive-tab" data-bs-toggle="tab"
                    data-bs-target="#golive" type="button" role="tab"
                    aria-controls="golive" aria-selected="false">
                    <i class="bi bi-rocket me-1 text-success"></i>
                    Go-Live
                    <span class="badge bg-success ms-1"><?php echo count($golive_projects); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3 border border-top-0 rounded-bottom" id="qaProjectsTabContent">
            <!-- WP Conversion QA Tab -->
            <div class="tab-pane fade show active" id="wp-conversion" role="tabpanel" aria-labelledby="wp-conversion-tab">
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
                <?php endif; ?>
            </div>

            <!-- Page Creation QA Tab -->
            <div class="tab-pane fade" id="page-creation" role="tabpanel" aria-labelledby="page-creation-tab">
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
                <?php endif; ?>
            </div>

            <!-- Go-Live QA Tab -->
            <div class="tab-pane fade" id="golive" role="tabpanel" aria-labelledby="golive-tab">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>