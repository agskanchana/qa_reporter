<?php
require_once 'includes/config.php';

// Check permissions
$user_role = getUserRole();
$user_id = $_SESSION['user_id'];
checkPermission(['admin', 'qa_manager']);

// Get date range from GET parameters, default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get all webmasters for filter and statistics
$webmasters_query = "SELECT id, username FROM users WHERE role = 'webmaster'";
$webmasters = $conn->query($webmasters_query);

// Function to get webmaster performance statistics
function getWebmasterStats($conn, $start_date, $end_date) {
    $query = "SELECT
                u.id as webmaster_id,
                u.username as webmaster_name,
                COUNT(DISTINCT CASE
                    WHEN p.current_status IN ('page_creation', 'page_creation_qa', 'golive', 'golive_qa', 'completed')
                    THEN p.id
                    END) as wp_conversions,
                COUNT(DISTINCT CASE
                    WHEN p.current_status IN ('golive', 'golive_qa', 'completed')
                    THEN p.id
                    END) as page_creations,
                COUNT(DISTINCT CASE
                    WHEN p.current_status = 'completed'
                    THEN p.id
                    END) as completed_projects
              FROM users u
              LEFT JOIN projects p ON u.id = p.webmaster_id
                   AND p.updated_at BETWEEN ? AND ?
              WHERE u.role = 'webmaster'
              GROUP BY u.id, u.username
              ORDER BY completed_projects DESC, page_creations DESC, wp_conversions DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result();
}

// Add this function after your existing functions
function getDetailedWebmasterProjects($conn, $start_date, $end_date) {
    $query = "SELECT
                u.id as webmaster_id,
                u.username as webmaster_name,
                p.id as project_id,
                p.name as project_name,
                p.created_at,
                p.current_status,
                (SELECT COUNT(*)
                 FROM project_checklist_status pcs
                 JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                 WHERE pcs.project_id = p.id AND ci.stage = 'wp_conversion'
                 AND pcs.status IN ('passed', 'fixed')) as wp_items_completed,
                (SELECT COUNT(*)
                 FROM checklist_items
                 WHERE stage = 'wp_conversion') as total_wp_items,
                (SELECT COUNT(*)
                 FROM project_checklist_status pcs
                 JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                 WHERE pcs.project_id = p.id AND ci.stage = 'page_creation'
                 AND pcs.status IN ('passed', 'fixed')) as page_items_completed,
                (SELECT COUNT(*)
                 FROM checklist_items
                 WHERE stage = 'page_creation') as total_page_items,
                (SELECT COUNT(*)
                 FROM project_checklist_status pcs
                 JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                 WHERE pcs.project_id = p.id AND ci.stage = 'golive'
                 AND pcs.status IN ('passed', 'fixed')) as golive_items_completed,
                (SELECT COUNT(*)
                 FROM checklist_items
                 WHERE stage = 'golive') as total_golive_items
              FROM users u
              LEFT JOIN projects p ON u.id = p.webmaster_id
                   AND p.created_at BETWEEN ? AND ?
              WHERE u.role = 'webmaster'
              ORDER BY u.username, p.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result();
}

// Get detailed statistics
$detailed_stats = getDetailedWebmasterProjects($conn, $start_date, $end_date);

// Get statistics
$webmaster_stats = getWebmasterStats($conn, $start_date, $end_date);

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Webmaster Performance Report</h2>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                           value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Apply Date Range</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Performance Summary Cards -->
    <div class="row mb-4">
        <?php
        $total_wp = 0;
        $total_page = 0;
        $total_completed = 0;
        $webmaster_stats->data_seek(0);
        while ($stat = $webmaster_stats->fetch_assoc()) {
            $total_wp += $stat['wp_conversions'];
            $total_page += $stat['page_creations'];
            $total_completed += $stat['completed_projects'];
        }
        ?>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total WP Conversions</h5>
                    <h2><?php echo $total_wp; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Total Page Creations</h5>
                    <h2><?php echo $total_page; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Completed</h5>
                    <h2><?php echo $total_completed; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Webmaster Performance Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Webmaster Performance Details</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Webmaster</th>
                            <th class="text-center">WP Conversions</th>
                            <th class="text-center">Page Creations</th>
                            <th class="text-center">Completed Projects</th>
                            <th class="text-center">Performance Chart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $webmaster_stats->data_seek(0);
                        while ($stat = $webmaster_stats->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['webmaster_name']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-info">
                                    <?php echo $stat['wp_conversions']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-warning text-dark">
                                    <?php echo $stat['page_creations']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success">
                                    <?php echo $stat['completed_projects']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="progress">
                                    <?php if ($total_wp > 0): ?>
                                    <div class="progress-bar bg-info"
                                         style="width: <?php echo ($stat['wp_conversions']/$total_wp)*100; ?>%"
                                         title="WP Conversions">
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($total_page > 0): ?>
                                    <div class="progress-bar bg-warning"
                                         style="width: <?php echo ($stat['page_creations']/$total_page)*100; ?>%"
                                         title="Page Creations">
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($total_completed > 0): ?>
                                    <div class="progress-bar bg-success"
                                         style="width: <?php echo ($stat['completed_projects']/$total_completed)*100; ?>%"
                                         title="Completed">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Detailed Project Progress Section -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title">Detailed Project Progress by Webmaster</h5>
    </div>
    <div class="card-body">
        <div class="accordion" id="webmasterAccordion">
            <?php
            $current_webmaster = null;
            $detailed_stats->data_seek(0);
            while ($project = $detailed_stats->fetch_assoc()):
                if ($current_webmaster !== $project['webmaster_id']):
                    if ($current_webmaster !== null): ?>
                        </div></div></div>
                    <?php endif;
                    $current_webmaster = $project['webmaster_id'];
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#webmaster<?php echo $project['webmaster_id']; ?>">
                            <?php echo htmlspecialchars($project['webmaster_name']); ?>
                        </button>
                    </h2>
                    <div id="webmaster<?php echo $project['webmaster_id']; ?>"
                         class="accordion-collapse collapse"
                         data-bs-parent="#webmasterAccordion">
                        <div class="accordion-body">
            <?php endif; ?>

            <?php if ($project['project_id']): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?php echo htmlspecialchars($project['project_name']); ?></h6>
                            <small class="text-muted">
                                Created: <?php echo date('Y-m-d', strtotime($project['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- WP Conversion Progress -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label">WP Conversion</label>
                                <span class="badge bg-info">
                                    <?php echo $project['wp_items_completed']; ?>/<?php echo $project['total_wp_items']; ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-info"
                                     style="width: <?php echo ($project['wp_items_completed']/$project['total_wp_items'])*100; ?>%">
                                </div>
                            </div>
                        </div>

                        <!-- Page Creation Progress -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label">Page Creation</label>
                                <span class="badge bg-warning text-dark">
                                    <?php echo $project['page_items_completed']; ?>/<?php echo $project['total_page_items']; ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning"
                                     style="width: <?php echo ($project['page_items_completed']/$project['total_page_items'])*100; ?>%">
                                </div>
                            </div>
                        </div>

                        <!-- Golive Progress -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label">Golive</label>
                                <span class="badge bg-success">
                                    <?php echo $project['golive_items_completed']; ?>/<?php echo $project['total_golive_items']; ?>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success"
                                     style="width: <?php echo ($project['golive_items_completed']/$project['total_golive_items'])*100; ?>%">
                                </div>
                            </div>
                        </div>

                        <!-- Current Status -->
                        <div class="mt-3">
                            <strong>Current Status:</strong>
                            <span class="badge bg-<?php
                                echo match($project['current_status']) {
                                    'wp_conversion' => 'info',
                                    'page_creation' => 'warning',
                                    'golive' => 'primary',
                                    'completed' => 'success',
                                    default => 'secondary'
                                };
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $project['current_status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php endwhile; ?>
            <?php if ($current_webmaster !== null): ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.progress {
    height: 20px;
    margin-bottom: 10px;
}
.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #0d6efd;
}
.card-header {
    background-color: #f8f9fa;
}
</style>

    <!-- Performance Chart -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title">Performance Visualization</h5>
        </div>
        <div class="card-body">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    <?php
    $webmaster_stats->data_seek(0);
    $labels = [];
    $wp_data = [];
    $page_data = [];
    $completed_data = [];

    while ($stat = $webmaster_stats->fetch_assoc()) {
        $labels[] = $stat['webmaster_name'];
        $wp_data[] = $stat['wp_conversions'];
        $page_data[] = $stat['page_creations'];
        $completed_data[] = $stat['completed_projects'];
    }
    ?>

    // Create the performance chart
    const ctx = document.getElementById('performanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                {
                    label: 'WP Conversions',
                    data: <?php echo json_encode($wp_data); ?>,
                    backgroundColor: 'rgba(13, 202, 240, 0.5)',
                    borderColor: 'rgba(13, 202, 240, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Page Creations',
                    data: <?php echo json_encode($page_data); ?>,
                    backgroundColor: 'rgba(255, 193, 7, 0.5)',
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Completed Projects',
                    data: <?php echo json_encode($completed_data); ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.5)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
</script>
</body>
</html>