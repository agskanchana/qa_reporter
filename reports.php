<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
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
            WHEN p.current_status IN ('wp_conversion', 'wp_conversion_qa')
            THEN p.id
        END) as wp_conversions,
        COUNT(DISTINCT CASE
            WHEN p.current_status IN ('page_creation', 'page_creation_qa')
            THEN p.id
        END) as page_creations,
        COUNT(DISTINCT CASE
            WHEN p.current_status IN ('golive', 'golive_qa')
            THEN p.id
        END) as golive_projects,
        COUNT(DISTINCT CASE
            WHEN p.current_status = 'completed'
            THEN p.id
        END) as completed_projects,
        COUNT(DISTINCT p.id) as total_projects
    FROM users u
    LEFT JOIN projects p ON u.id = p.webmaster_id
        AND DATE(p.created_at) BETWEEN ? AND ?
    WHERE u.role = 'webmaster'
    GROUP BY u.id, u.username
    ORDER BY completed_projects DESC, golive_projects DESC,
             page_creations DESC, wp_conversions DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result();
}

// Add this function after your existing functions


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
                            <th class="text-center">Active Projects</th>
                            <th class="text-center">Project Stages</th>
                            <th class="text-center">Completed</th>
                            <th class="text-center">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $webmaster_stats->data_seek(0);
                        while ($stat = $webmaster_stats->fetch_assoc()):
                            $active_projects = $stat['wp_conversions'] + $stat['page_creations'] + $stat['golive_projects'];
                        ?>
                        <tr>
                            <td>
                                <a href="webmaster_report.php?id=<?php echo $stat['webmaster_id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                    <?php echo htmlspecialchars($stat['webmaster_name']); ?>
                                </a>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary">
                                    <?php echo $active_projects; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($stat['wp_conversions'] > 0): ?>
                                    <span class="badge bg-info" title="WP Conversion">
                                        <?php echo $stat['wp_conversions']; ?> WP
                                    </span>
                                <?php endif; ?>
                                <?php if ($stat['page_creations'] > 0): ?>
                                    <span class="badge bg-warning text-dark" title="Page Creation">
                                        <?php echo $stat['page_creations']; ?> Page
                                    </span>
                                <?php endif; ?>
                                <?php if ($stat['golive_projects'] > 0): ?>
                                    <span class="badge bg-success" title="Golive">
                                        <?php echo $stat['golive_projects']; ?> Live
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-dark">
                                    <?php echo $stat['completed_projects']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="progress">
                                    <?php
                                    $total = $stat['total_projects'];
                                    if ($total > 0):
                                        if ($stat['wp_conversions'] > 0):
                                            $wp_percent = ($stat['wp_conversions']/$total)*100;
                                    ?>
                                        <div class="progress-bar bg-info"
                                             style="width: <?php echo $wp_percent; ?>%"
                                             title="WP Conversion: <?php echo $stat['wp_conversions']; ?>">
                                        </div>
                                    <?php endif;
                                        if ($stat['page_creations'] > 0):
                                            $page_percent = ($stat['page_creations']/$total)*100;
                                    ?>
                                        <div class="progress-bar bg-warning"
                                             style="width: <?php echo $page_percent; ?>%"
                                             title="Page Creation: <?php echo $stat['page_creations']; ?>">
                                        </div>
                                    <?php endif;
                                        if ($stat['golive_projects'] > 0):
                                            $golive_percent = ($stat['golive_projects']/$total)*100;
                                    ?>
                                        <div class="progress-bar bg-success"
                                             style="width: <?php echo $golive_percent; ?>%"
                                             title="Golive: <?php echo $stat['golive_projects']; ?>">
                                        </div>
                                    <?php endif;
                                        if ($stat['completed_projects'] > 0):
                                            $completed_percent = ($stat['completed_projects']/$total)*100;
                                    ?>
                                        <div class="progress-bar bg-dark"
                                             style="width: <?php echo $completed_percent; ?>%"
                                             title="Completed: <?php echo $stat['completed_projects']; ?>">
                                        </div>
                                    <?php endif;
                                    endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- Existing Performance Chart -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Performance Visualization</h5>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Project Distribution Pie Chart -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Project Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
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

    <?php
    $webmaster_stats->data_seek(0);
    $pie_labels = [];
    $pie_data = [];
    $pie_colors = [];

    while ($stat = $webmaster_stats->fetch_assoc()) {
        $total_projects = $stat['wp_conversions'] + $stat['page_creations'] + $stat['completed_projects'];
        if ($total_projects > 0) {
            $pie_labels[] = $stat['webmaster_name'];
            $pie_data[] = $total_projects;
            $pie_colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
        }
    }
    ?>

    // Add the distribution chart
    const pieCtx = document.getElementById('distributionChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($pie_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($pie_data); ?>,
                backgroundColor: <?php echo json_encode($pie_colors); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.raw / total) * 100).toFixed(1);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>