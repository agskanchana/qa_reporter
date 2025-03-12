<?php
// Get webmaster workload data - projects in all statuses except completed
$webmaster_workload_query = "SELECT
                             u.id as webmaster_id,
                             u.username as webmaster_name,
                             COUNT(p.id) as total_projects,
                             SUM(CASE WHEN p.current_status LIKE '%wp_conversion%' AND p.current_status NOT LIKE '%qa%' THEN 1 ELSE 0 END) as wp_conversion,
                             SUM(CASE WHEN p.current_status LIKE '%page_creation%' AND p.current_status NOT LIKE '%qa%' THEN 1 ELSE 0 END) as page_creation,
                             SUM(CASE WHEN p.current_status LIKE '%golive%' AND p.current_status NOT LIKE '%qa%' THEN 1 ELSE 0 END) as golive,

                             /* Detailed QA status breakdown */
                             SUM(CASE WHEN p.current_status LIKE '%wp_conversion_qa%' THEN 1 ELSE 0 END) as wp_qa,
                             SUM(CASE WHEN p.current_status LIKE '%page_creation_qa%' THEN 1 ELSE 0 END) as page_qa,
                             SUM(CASE WHEN p.current_status LIKE '%golive_qa%' THEN 1 ELSE 0 END) as golive_qa,

                             /* Overall QA total */
                             SUM(CASE WHEN p.current_status LIKE '%_qa%' THEN 1 ELSE 0 END) as in_qa,

                             COUNT(CASE WHEN p.project_deadline < CURDATE() AND p.current_status NOT LIKE '%complete%' THEN 1 END) as overdue
                             FROM users u
                             LEFT JOIN projects p ON u.id = p.webmaster_id AND p.current_status NOT LIKE '%complete%'
                             WHERE u.role = 'webmaster'
                             GROUP BY u.id, u.username
                             ORDER BY total_projects DESC";

$webmaster_workload = $conn->query($webmaster_workload_query)->fetch_all(MYSQLI_ASSOC);

// Total overall projects
$total_active_projects_query = "SELECT COUNT(*) as count FROM projects WHERE current_status NOT LIKE '%complete%'";
$total_active_projects = $conn->query($total_active_projects_query)->fetch_assoc()['count'];
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-people me-2 text-primary"></i>
            Webmaster Workload
        </h5>
        <span class="badge bg-secondary">
            <?php echo $total_active_projects; ?> Active Projects
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($webmaster_workload)): ?>
        <div class="text-center py-4">
            <p class="text-muted">No webmasters with projects found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Webmaster</th>
                        <th class="text-center" title="Total projects">Total</th>
                        <th class="text-center" title="WP Conversion">WP</th>
                        <th class="text-center" title="Page Creation">Pages</th>
                        <th class="text-center" title="Go-Live">GoLive</th>
                        <th class="text-center">Overdue</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webmaster_workload as $webmaster): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm bg-primary text-white me-2">
                                    <?php echo !empty($webmaster['webmaster_name']) ? substr($webmaster['webmaster_name'], 0, 1) : '?'; ?>
                                </div>
                                <?php echo htmlspecialchars($webmaster['webmaster_name']); ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?php echo $webmaster['total_projects']; ?></span>
                        </td>
                        <!-- WP Column - WM and QA combined -->
                        <td class="text-center">
                            <?php if ($webmaster['wp_conversion'] > 0 || $webmaster['wp_qa'] > 0): ?>
                                <div>
                                    <?php if ($webmaster['wp_conversion'] > 0): ?>
                                        <span class="badge bg-primary" title="In WP Conversion">
                                            WM (<?php echo $webmaster['wp_conversion']; ?>)
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($webmaster['wp_qa'] > 0): ?>
                                        <span class="badge bg-primary bg-opacity-50 ms-1" title="In WP Conversion QA">
                                            QA (<?php echo $webmaster['wp_qa']; ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Pages Column - WM and QA combined -->
                        <td class="text-center">
                            <?php if ($webmaster['page_creation'] > 0 || $webmaster['page_qa'] > 0): ?>
                                <div>
                                    <?php if ($webmaster['page_creation'] > 0): ?>
                                        <span class="badge bg-warning text-dark" title="In Page Creation">
                                            WM (<?php echo $webmaster['page_creation']; ?>)
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($webmaster['page_qa'] > 0): ?>
                                        <span class="badge bg-warning bg-opacity-50 text-dark ms-1" title="In Page Creation QA">
                                            QA (<?php echo $webmaster['page_qa']; ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- GoLive Column - WM and QA combined -->
                        <td class="text-center">
                            <?php if ($webmaster['golive'] > 0 || $webmaster['golive_qa'] > 0): ?>
                                <div>
                                    <?php if ($webmaster['golive'] > 0): ?>
                                        <span class="badge bg-success" title="In Go-Live">
                                            WM (<?php echo $webmaster['golive']; ?>)
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($webmaster['golive_qa'] > 0): ?>
                                        <span class="badge bg-success bg-opacity-50 ms-1" title="In Go-Live QA">
                                            QA (<?php echo $webmaster['golive_qa']; ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($webmaster['overdue'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $webmaster['overdue']; ?></span>
                            <?php else: ?>
                                <span class="text-success"><i class="bi bi-check-circle"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="../projects.php?webmaster_id=<?php echo $webmaster['webmaster_id']; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-folder me-1"></i> Projects
                                </a>
                                <a href="../message.php?user_id=<?php echo $webmaster['webmaster_id']; ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-chat"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>