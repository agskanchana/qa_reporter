<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$webmaster_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get webmaster details
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND role = 'webmaster'");
$stmt->bind_param("i", $webmaster_id);
$stmt->execute();
$webmaster = $stmt->get_result()->fetch_assoc();

if (!$webmaster) {
    header("Location: reports.php");
    exit();
}

// Get performance summary for this webmaster
$summary_query = "SELECT
    COUNT(DISTINCT CASE WHEN current_status IN ('wp_conversion', 'wp_conversion_qa') THEN id END) as wp_count,
    COUNT(DISTINCT CASE WHEN current_status IN ('page_creation', 'page_creation_qa') THEN id END) as page_count,
    COUNT(DISTINCT CASE WHEN current_status = 'completed' THEN id END) as completed_count
FROM projects
WHERE webmaster_id = ?
AND DATE(created_at) BETWEEN ? AND ?";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("iss", $webmaster_id, $start_date, $end_date);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Add after the existing summary query

// Get comparison statistics
$comparison_query = "SELECT
    (SELECT COUNT(*) FROM projects
     WHERE webmaster_id = ?
     AND DATE(created_at) BETWEEN ? AND ?) as user_projects,
    (SELECT COUNT(*) FROM projects
     WHERE webmaster_id != ?
     AND DATE(created_at) BETWEEN ? AND ?) as other_projects,
    (SELECT AVG(project_count)
     FROM (
         SELECT COUNT(*) as project_count
         FROM projects
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY webmaster_id
     ) as counts) as average_projects";

$comparison_stmt = $conn->prepare($comparison_query);
$comparison_stmt->bind_param("isisisss",
    $webmaster_id, $start_date, $end_date,
    $webmaster_id, $start_date, $end_date,
    $start_date, $end_date
);
$comparison_stmt->execute();
$comparison = $comparison_stmt->get_result()->fetch_assoc();

// Get top 5 failing items for each stage
$failing_items_query = "SELECT
    ci.title,
    ci.stage,
    COUNT(*) as fail_count
FROM project_checklist_status pcs
JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
JOIN projects p ON pcs.project_id = p.id
WHERE p.webmaster_id = ?
    AND p.created_at BETWEEN ? AND ?
    AND pcs.status = 'failed'
    AND ci.is_archived = 0
    AND pcs.is_archived = 0
GROUP BY ci.stage, ci.id, ci.title
HAVING COUNT(*) > 0
ORDER BY ci.stage, fail_count DESC";

$failing_stmt = $conn->prepare($failing_items_query);
$failing_stmt->bind_param("iss", $webmaster_id, $start_date, $end_date);
$failing_stmt->execute();
$failing_result = $failing_stmt->get_result();

$failing_items = [];
while ($row = $failing_result->fetch_assoc()) {
    if (!isset($failing_items[$row['stage']])) {
        $failing_items[$row['stage']] = [];
    }
    if (count($failing_items[$row['stage']]) < 5) {
        $failing_items[$row['stage']][] = $row;
    }
}

// First, get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM projects p
                WHERE p.webmaster_id = ?
                AND DATE(p.created_at) BETWEEN ? AND ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("iss", $webmaster_id, $start_date, $end_date);
$count_stmt->execute();
$total_projects = $count_stmt->get_result()->fetch_assoc()['total'];

// Set up pagination
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_pages = ceil($total_projects / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Main query with pagination
$query = "SELECT
    p.*,
    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id AND ci.stage = 'wp_conversion'
     AND ci.is_archived = 0 AND pcs.is_archived = 0) as total_wp_items,

    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id AND ci.stage = 'wp_conversion'
     AND pcs.status IN ('passed', 'fixed')
     AND ci.is_archived = 0 AND pcs.is_archived = 0) as wp_items_completed,

    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id AND ci.stage = 'page_creation'
     AND ci.is_archived = 0 AND pcs.is_archived = 0) as total_page_items,

    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id AND ci.stage = 'page_creation'
     AND pcs.status IN ('passed', 'fixed')
     AND ci.is_archived = 0 AND pcs.is_archived = 0) as page_items_completed,

    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id AND ci.stage = 'golive'
     AND ci.is_archived = 0 AND pcs.is_archived = 0) as total_golive_items,

    (SELECT COUNT(*) FROM project_checklist_status pcs
     JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
     WHERE pcs.project_id = p.id AND ci.stage = 'golive'
     AND pcs.status IN ('passed', 'fixed')
     AND ci.is_archived = 0 AND pcs.is_archived = 0) as golive_items_completed

    FROM projects p
    WHERE p.webmaster_id = ?
    AND DATE(p.created_at) BETWEEN ? AND ?
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("issii", $webmaster_id, $start_date, $end_date, $items_per_page, $offset);
$stmt->execute();
$projects = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webmaster Report - <?php echo htmlspecialchars($webmaster['username']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>
                    <a href="reports.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <?php echo htmlspecialchars($webmaster['username']); ?>'s Performance Report
                </h2>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" onclick="copyToClipboard()">
                    <i class="bi bi-link-45deg"></i> Share Report
                </button>
            </div>
        </div>

        <!-- Date Range -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo $webmaster_id; ?>">
                    <div class="col-md-5">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date"
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-5">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date"
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Performance Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">WP Conversion Projects</h6>
                        <h2 class="mb-0"><?php echo $summary['wp_count']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h6 class="card-title">Page Creation Projects</h6>
                        <h2 class="mb-0"><?php echo $summary['page_count']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h6 class="card-title">Completed Projects</h6>
                        <h2 class="mb-0"><?php echo $summary['completed_count']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contribution Comparison -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Contribution Analysis</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <canvas id="contributionChart"></canvas>
                    </div>
                    <div class="col-md-4">
                        <?php
                        $total_projects = $comparison['user_projects'] + $comparison['other_projects'];
                        $user_percentage = $total_projects > 0 ?
                            round(($comparison['user_projects'] / $total_projects) * 100, 1) : 0;
                        $avg_percentage = $total_projects > 0 ?
                            round(($comparison['average_projects'] / $total_projects) * 100, 1) : 0;

                        $performance_label = match(true) {
                            $comparison['user_projects'] > $comparison['average_projects'] * 1.2 =>
                                ['text' => 'Above Average', 'class' => 'success'],
                            $comparison['user_projects'] >= $comparison['average_projects'] * 0.8 =>
                                ['text' => 'Average', 'class' => 'info'],
                            default => ['text' => 'Below Average', 'class' => 'warning']
                        };
                        ?>
                        <div class="text-center">
                            <h3 class="text-<?php echo $performance_label['class']; ?>">
                                <?php echo $performance_label['text']; ?>
                            </h3>
                            <p class="mb-1">Your Projects: <?php echo $comparison['user_projects']; ?></p>
                            <p class="mb-1">Team Average: <?php echo round($comparison['average_projects'], 1); ?></p>
                            <p class="mb-0">Your Contribution: <?php echo $user_percentage; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Failed Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Top 5 Most Failed Items by Stage</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $stages = [
                        'wp_conversion' => ['title' => 'WP Conversion', 'class' => 'info'],
                        'page_creation' => ['title' => 'Page Creation', 'class' => 'warning'],
                        'golive' => ['title' => 'Golive', 'class' => 'success']
                    ];

                    foreach ($stages as $stage => $info):
                        $items = $failing_items[$stage] ?? [];
                    ?>
                        <div class="col-md-4">
                            <div class="card border-<?php echo $info['class']; ?> h-100">
                                <div class="card-header bg-<?php echo $info['class']; ?> bg-opacity-10">
                                    <h6 class="card-title mb-0"><?php echo $info['title']; ?></h6>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($items)): ?>
                                        <p class="text-muted p-3 mb-0">No failing items</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($items as $item): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <small><?php echo htmlspecialchars($item['title']); ?></small>
                                                    <span class="badge bg-danger rounded-pill">
                                                        <?php echo $item['fail_count']; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Projects List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Project Details</h5>
            </div>
            <div class="card-body">
                <?php if ($projects->num_rows > 0): ?>
                    <div class="accordion" id="projectsAccordion">
                        <?php while ($project = $projects->fetch_assoc()): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#project<?php echo $project['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <span><?php echo htmlspecialchars($project['name']); ?></span>
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
                                    </button>
                                </h2>
                                <div id="project<?php echo $project['id']; ?>"
                                     class="accordion-collapse collapse"
                                     data-bs-parent="#projectsAccordion">
                                    <div class="accordion-body">
                                        <small class="text-muted d-block mb-3">
                                            Created: <?php echo date('Y-m-d', strtotime($project['created_at'])); ?>
                                        </small>

                                        <!-- Progress Bars -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <label class="form-label">WP Conversion</label>
                                                <span class="badge bg-info">
                                                    <?php echo $project['wp_items_completed']; ?>/<?php echo $project['total_wp_items']; ?>
                                                </span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-info"
                                                     style="width: <?php echo ($project['total_wp_items'] > 0 ? ($project['wp_items_completed']/$project['total_wp_items']*100) : 0); ?>%">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <label class="form-label">Page Creation</label>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo $project['page_items_completed']; ?>/<?php echo $project['total_page_items']; ?>
                                                </span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-warning"
                                                     style="width: <?php echo ($project['total_page_items'] > 0 ? ($project['page_items_completed']/$project['total_page_items']*100) : 0); ?>%">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <label class="form-label">Golive</label>
                                                <span class="badge bg-success">
                                                    <?php echo $project['golive_items_completed']; ?>/<?php echo $project['total_golive_items']; ?>
                                                </span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success"
                                                     style="width: <?php echo ($project['total_golive_items'] > 0 ? ($project['golive_items_completed']/$project['total_golive_items']*100) : 0); ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Project pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php
                                $queryParams = $_GET;
                                for ($i = 1; $i <= $total_pages; $i++):
                                    $queryParams['page'] = $i;
                                    $queryString = http_build_query($queryParams);
                                ?>
                                    <li class="page-item <?php echo $current_page === $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo $queryString; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No projects found for this date range.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyToClipboard() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            alert('Report URL copied to clipboard!');
        });
    }

    // Add after your existing script
    document.addEventListener('DOMContentLoaded', function() {
        // Store accordion state in session storage
        const accordionItems = document.querySelectorAll('.accordion-collapse');
        accordionItems.forEach(item => {
            item.addEventListener('shown.bs.collapse', function() {
                sessionStorage.setItem(item.id, 'open');
            });
            item.addEventListener('hidden.bs.collapse', function() {
                sessionStorage.removeItem(item.id);
            });

            // Restore accordion state
            if (sessionStorage.getItem(item.id) === 'open') {
                new bootstrap.Collapse(item, { toggle: false }).show();
            }
        });

        // Add to the existing script section
        const contributionCtx = document.getElementById('contributionChart').getContext('2d');
        new Chart(contributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Your Projects', 'Other Webmasters'],
                datasets: [{
                    data: [
                        <?php echo $comparison['user_projects']; ?>,
                        <?php echo $comparison['other_projects']; ?>
                    ],
                    backgroundColor: ['#0d6efd', '#e9ecef'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    });
    </script>

    <style>
    .progress {
        height: 20px;
        margin-bottom: 10px;
    }
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0,0,0,.125);
    }
    .list-group-item {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    .badge.rounded-pill {
        min-width: 2em;
    }
    .bg-opacity-10 {
        --bs-bg-opacity: 0.1;
    }
    /* Add to your existing styles */
    .accordion-button:not(.collapsed) {
        background-color: #f8f9fa;
        color: #000;
    }

    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(0,0,0,.125);
    }

    .accordion-button .badge {
        font-size: 0.875em;
    }

    .pagination {
        margin-bottom: 0;
    }

    .page-link {
        color: #0d6efd;
    }

    .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }


    #contributionChart {
        height: 250px !important;
    }
    </style>
</body>
</html>