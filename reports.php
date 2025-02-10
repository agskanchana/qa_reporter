<?php
require_once 'includes/config.php';

// Check permissions
$user_role = getUserRole();
$user_id = $_SESSION['user_id'];
checkPermission(['admin', 'qa_manager']);

// Get date range from GET parameters, default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get webmaster filter
$webmaster_id = isset($_GET['webmaster_id']) ? (int)$_GET['webmaster_id'] : 0;

// Get all webmasters for filter
$webmasters_query = "SELECT id, username FROM users WHERE role = 'webmaster'";
$webmasters = $conn->query($webmasters_query);

// Function to get project statistics
function getProjectStats($conn, $start_date, $end_date, $webmaster_id = 0) {
    $where_clause = $webmaster_id ? "AND p.webmaster_id = ?" : "";

    $query = "SELECT
                COUNT(*) as total_projects,
                SUM(CASE WHEN current_status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
                AVG(CASE
                    WHEN current_status = 'completed'
                    THEN DATEDIFF(updated_at, created_at)
                    ELSE NULL
                END) as avg_completion_days
              FROM projects p
              WHERE created_at BETWEEN ? AND ?
              $where_clause";

    $stmt = $conn->prepare($query);
    if ($webmaster_id) {
        $stmt->bind_param("ssi", $start_date, $end_date, $webmaster_id);
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to get failed items statistics
function getFailedItemsStats($conn, $start_date, $end_date, $webmaster_id = 0) {
    $where_clause = $webmaster_id ? "AND p.webmaster_id = ?" : "";

    $query = "SELECT
                ci.title,
                ci.stage,
                COUNT(*) as fail_count
              FROM project_checklist_status pcs
              JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
              JOIN projects p ON pcs.project_id = p.id
              WHERE pcs.status = 'failed'
              AND p.updated_at BETWEEN ? AND ?
              $where_clause
              GROUP BY ci.id
              ORDER BY fail_count DESC
              LIMIT 10";

    $stmt = $conn->prepare($query);
    if ($webmaster_id) {
        $stmt->bind_param("ssi", $start_date, $end_date, $webmaster_id);
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
    }

    $stmt->execute();
    return $stmt->get_result();
}

// Get statistics
$stats = getProjectStats($conn, $start_date, $end_date, $webmaster_id);
$failed_items = getFailedItemsStats($conn, $start_date, $end_date, $webmaster_id);

require_once 'includes/header.php';

?>


    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>Reports</h2>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date"
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date"
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="webmaster_id" class="form-label">Webmaster</label>
                        <select class="form-select" id="webmaster_id" name="webmaster_id">
                            <option value="">All Webmasters</option>
                            <?php while ($webmaster = $webmasters->fetch_assoc()): ?>
                                <option value="<?php echo $webmaster['id']; ?>"
                                        <?php echo $webmaster_id == $webmaster['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($webmaster['username']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Projects</h5>
                        <h2><?php echo $stats['total_projects']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Completed Projects</h5>
                        <h2><?php echo $stats['completed_projects']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Avg. Completion Days</h5>
                        <?php $value = isset($num) && ($num !== null) ? round($num, 1) : 0; ?>
                        <h2><?php echo $value; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Failed Items Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Most Failed Checklist Items</h5>
            </div>
            <div class="card-body">
                <canvas id="failedItemsChart"></canvas>
            </div>
        </div>

        <!-- Detailed Failed Items Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Failed Items Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Stage</th>
                                <th>Fail Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $failed_items->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($item['stage']) {
                                            'wp_conversion' => 'info',
                                            'page_creation' => 'warning',
                                            'golive' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['stage'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $item['fail_count']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Prepare data for the chart
        <?php
        $failed_items->data_seek(0);
        $labels = [];
        $data = [];
        $backgroundColor = [];

        while ($item = $failed_items->fetch_assoc()) {
            $labels[] = $item['title'];
            $data[] = $item['fail_count'];
            $backgroundColor[] = match($item['stage']) {
                'wp_conversion' => 'rgba(13, 202, 240, 0.5)',
                'page_creation' => 'rgba(255, 193, 7, 0.5)',
                'golive' => 'rgba(220, 53, 69, 0.5)',
                default => 'rgba(108, 117, 125, 0.5)'
            };
        }
        ?>

        // Create the chart
        const ctx = document.getElementById('failedItemsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Number of Failures',
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: <?php echo json_encode($backgroundColor); ?>
                }]
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