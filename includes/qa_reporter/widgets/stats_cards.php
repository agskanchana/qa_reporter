<?php
// Get statistics for the QA Reporter dashboard

// Get count of projects assigned to this user
$assigned_projects_query = "SELECT COUNT(*) as count
                           FROM qa_assignments
                           WHERE qa_user_id = ?";
$stmt = $conn->prepare($assigned_projects_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assigned_projects = $stmt->get_result()->fetch_assoc()['count'];

// Count projects in different QA stages (assigned to this user)
$projects_by_stage_query = "SELECT
                            SUM(CASE WHEN p.current_status LIKE '%wp_conversion_qa%' THEN 1 ELSE 0 END) as wp_conversion_qa,
                            SUM(CASE WHEN p.current_status LIKE '%page_creation_qa%' THEN 1 ELSE 0 END) as page_creation_qa,
                            SUM(CASE WHEN p.current_status LIKE '%golive_qa%' THEN 1 ELSE 0 END) as golive_qa,
                            SUM(CASE WHEN p.project_deadline < CURDATE() THEN 1 ELSE 0 END) as overdue
                           FROM qa_assignments qa
                           JOIN projects p ON qa.project_id = p.id
                           WHERE qa.qa_user_id = ?
                           AND p.current_status NOT LIKE '%completed%'";
$stmt = $conn->prepare($projects_by_stage_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$projects_by_stage = $stmt->get_result()->fetch_assoc();

// Count completed projects (by this user)
$completed_projects_query = "SELECT COUNT(DISTINCT qa.project_id) as count
                            FROM qa_assignments qa
                            JOIN projects p ON qa.project_id = p.id
                            WHERE qa.qa_user_id = ?
                            AND p.current_status LIKE '%completed%'";
$stmt = $conn->prepare($completed_projects_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_projects = $stmt->get_result()->fetch_assoc()['count'];

// Count QA issues reported by this user
$reported_issues_query = "SELECT COUNT(*) as count
                         FROM comments c
                         WHERE c.user_id = ?
                         AND c.comment LIKE '%failed%'";
$stmt = $conn->prepare($reported_issues_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reported_issues = $stmt->get_result()->fetch_assoc()['count'];
?>

<div class="row">
    <!-- Total Assigned Projects -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-folder-check text-primary fs-4"></i>
                </div>
                <div>
                    <h6 class="card-title text-muted mb-1">My Projects</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $assigned_projects; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- WP Conversion QA Projects -->
    <?php if (!$auto_assign_wp): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-wordpress text-info fs-4"></i>
                </div>
                <div>
                    <h6 class="card-title text-muted mb-1">WP Conversion QA</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $projects_by_stage['wp_conversion_qa'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page Creation QA Projects -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-file-earmark-plus text-warning fs-4"></i>
                </div>
                <div>
                    <h6 class="card-title text-muted mb-1">Page Creation QA</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $projects_by_stage['page_creation_qa'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Go-Live QA Projects -->
    <?php if (!$auto_assign_golive): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-rocket text-success fs-4"></i>
                </div>
                <div>
                    <h6 class="card-title text-muted mb-1">Go-Live QA</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $projects_by_stage['golive_qa'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Completed Projects -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-check2-circle text-success fs-4"></i>
                </div>
                <div>
                    <h6 class="card-title text-muted mb-1">Completed</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $completed_projects; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Overdue Projects -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-exclamation-circle text-danger fs-4"></i>
                </div>
                <div>
                    <h6 class="card-title text-muted mb-1">Overdue</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $projects_by_stage['overdue'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Reported Issues -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-secondary bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-bug text-secondary fs-4"></i>
                </div>
                <div>
                    <h6 class="card-title text-muted mb-1">Issues Reported</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $reported_issues; ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($auto_assign_wp || $auto_assign_golive): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <?php if ($auto_assign_wp && $auto_assign_golive): ?>
                WP Conversion and Go-Live QA tasks are being auto-assigned to admin according to system settings.
            <?php elseif ($auto_assign_wp): ?>
                WP Conversion QA tasks are being auto-assigned to admin according to system settings.
            <?php elseif ($auto_assign_golive): ?>
                Go-Live QA tasks are being auto-assigned to admin according to system settings.
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>