<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Add this function at the top of your file with other functions
function cleanRichText($text) {
    // Replace escaped quotes
    $text = str_replace('\"', '"', $text);
    $text = str_replace("\'", "'", $text);

    // Handle case where entire content was htmlspecialchar'd before saving
    if (strpos($text, '&lt;') !== false) {
        $text = html_entity_decode($text);
    }

    return $text;
}

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_role = getUserRole();

// Get project details
$query = "SELECT p.*, u.username as webmaster_name,
          COALESCE(p.current_status, 'wp_conversion') as current_status,
          p.project_deadline, p.wp_conversion_deadline, p.created_at,
          p.admin_notes, p.webmaster_notes
          FROM projects p
          LEFT JOIN users u ON p.webmaster_id = u.id
          WHERE p.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    header("Location: projects.php");
    exit();
}

// Calculate project duration
$created_date = new DateTime($project['created_at']);
$today = new DateTime();

// Get completion date from history
$completion_date = null;
$completion_query = "SELECT created_at FROM project_status_history
                    WHERE project_id = ? AND status = 'completed'
                    ORDER BY created_at ASC LIMIT 1";
$stmt = $conn->prepare($completion_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$completion_result = $stmt->get_result();

if ($completion_result->num_rows > 0) {
    $completion_date = new DateTime($completion_result->fetch_assoc()['created_at']);
    $project_duration = $created_date->diff($completion_date)->days;
} else {
    // If not completed, calculate days from creation to today
    $project_duration = $created_date->diff($today)->days;
}

// Get status history timeline
$history_query = "SELECT status, action, created_at,
                 (SELECT username FROM users WHERE id = created_by) as username
                 FROM project_status_history
                 WHERE project_id = ?
                 ORDER BY created_at ASC";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$history = $stmt->get_result();

// Calculate days in each phase
$webmaster_days = 0;
$qa_days = 0;
$status_time = [];

// Get first and last status time to calculate days per status
$time_query = "SELECT status,
              MIN(created_at) as start_time,
              MAX(created_at) as end_time
              FROM project_status_history
              WHERE project_id = ?
              GROUP BY status
              ORDER BY start_time ASC";
$stmt = $conn->prepare($time_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$time_results = $stmt->get_result();

while ($time = $time_results->fetch_assoc()) {
    $status_time[$time['status']] = [
        'start' => new DateTime($time['start_time']),
        'end' => new DateTime($time['end_time'])
    ];

    // Calculate days in each phase
    $days = $status_time[$time['status']]['start']->diff($status_time[$time['status']]['end'])->days;
    $days = max(1, $days); // Minimum 1 day

    if (strpos($time['status'], '_qa') !== false) {
        $qa_days += $days;
    } else {
        $webmaster_days += $days;
    }
}

// Get missed deadlines and reasons
$missed_wp_deadline = null;
$missed_project_deadline = null;

$missed_query = "SELECT * FROM missed_deadlines
                WHERE project_id = ?";
$stmt = $conn->prepare($missed_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$missed_results = $stmt->get_result();

while ($missed = $missed_results->fetch_assoc()) {
    if ($missed['deadline_type'] == 'wp_conversion') {
        $missed_wp_deadline = $missed;
    } else if ($missed['deadline_type'] == 'project') {
        $missed_project_deadline = $missed;
    }
}

// Get failed elements in each stage
$failed_items_query = "SELECT ci.stage, ci.title, ci.id
                      FROM project_checklist_status pcs
                      JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                      WHERE pcs.project_id = ? AND pcs.status = 'failed'
                      ORDER BY ci.stage, ci.title";
$stmt = $conn->prepare($failed_items_query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$failed_items = $stmt->get_result();

$failed_by_stage = [];
while ($item = $failed_items->fetch_assoc()) {
    if (!isset($failed_by_stage[$item['stage']])) {
        $failed_by_stage[$item['stage']] = [];
    }
    $failed_by_stage[$item['stage']][] = $item;
}

// Format dates for display
$created_at_display = date('F j, Y', strtotime($project['created_at']));
$project_deadline_display = !empty($project['project_deadline'])
    ? date('F j, Y', strtotime($project['project_deadline']))
    : 'Not set';
$wp_deadline_display = !empty($project['wp_conversion_deadline'])
    ? date('F j, Y', strtotime($project['wp_conversion_deadline']))
    : 'Not set';
$completion_date_display = $completion_date
    ? $completion_date->format('F j, Y')
    : 'Not completed';

// Get current status badges
$statuses = !empty($project['current_status']) ? explode(',', $project['current_status']) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['name']); ?> - Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline::before {
            content: '';
            position: absolute;
            width: 6px;
            background-color: #dee2e6;
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -3px;
        }
        .timeline-item {
            margin-bottom: 20px;
            position: relative;
        }
        .timeline-item::after {
            content: '';
            display: table;
            clear: both;
        }
        .timeline-item .timeline-content {
            width: 45%;
            padding: 10px 15px;
            position: relative;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        .timeline-item:nth-child(odd) .timeline-content {
            float: left;
        }
        .timeline-item:nth-child(even) .timeline-content {
            float: right;
        }
        .timeline-item .timeline-content::before {
            content: '';
            position: absolute;
            top: 10px;
            width: 15px;
            height: 15px;
            right: -8px;
            background-color: white;
            border: 4px solid #0d6efd;
            border-radius: 50%;
        }
        .timeline-item:nth-child(even) .timeline-content::before {
            left: -8px;
        }
        .timeline-item:nth-child(odd) .timeline-content::before {
            right: -8px;
        }
        @media screen and (max-width: 768px) {
            .timeline::before {
                left: 31px;
            }
            .timeline-item .timeline-content {
                width: calc(100% - 80px);
                float: right;
            }
            .timeline-item .timeline-content::before {
                left: -8px;
            }
        }
        .stat-card {
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .project-links a {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #0d6efd;
            text-decoration: none;
        }
        .project-links a:hover {
            text-decoration: underline;
        }
        .project-links a i {
            margin-right: 8px;
        }
        /* Add this to your existing styles */
        .rich-text-content {
            font-family: inherit;
        }
        .rich-text-content h1,
        .rich-text-content h2,
        .rich-text-content h3,
        .rich-text-content h4,
        .rich-text-content h5,
        .rich-text-content h6 {
            margin-top: 0.5em;
            margin-bottom: 0.5em;
        }
        .rich-text-content p {
            margin-bottom: 1em;
        }
        .rich-text-content ul,
        .rich-text-content ol {
            margin-top: 0;
            margin-bottom: 1em;
        }
        .rich-text-content img {
            max-width: 100%;
            height: auto;
        }
        .rich-text-content pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1em;
            margin-bottom: 1em;
        }
        .rich-text-content blockquote {
            border-left: 4px solid #dee2e6;
            padding-left: 1em;
            margin-left: 0;
            color: #6c757d;
        }
        .rich-text-content table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 1em;
            border-collapse: collapse;
        }
        .rich-text-content table td,
        .rich-text-content table th {
            padding: 0.5em;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo htmlspecialchars($project['name']); ?></h1>
            <div>
                <a href="view_project.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary me-2">
                    <i class="bi bi-list-check"></i> Checklist View
                </a>
                <a href="projects.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Projects
                </a>
            </div>
        </div>

        <!-- Project Overview Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Project Overview</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Project Name:</th>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Webmaster:</th>
                                <td><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Not Assigned'); ?></td>
                            </tr>
                            <tr>
                                <th>Created On:</th>
                                <td><?php echo $created_at_display; ?></td>
                            </tr>
                            <tr>
                                <th>Current Status:</th>
                                <td>
                                    <?php if (empty($statuses)): ?>
                                        <span class="badge bg-secondary">No Status</span>
                                    <?php else:
                                        foreach ($statuses as $status):
                                            $badge_class = 'secondary';
                                            if (strpos($status, 'wp_conversion') !== false) {
                                                $badge_class = 'info';
                                            } elseif (strpos($status, 'page_creation') !== false) {
                                                $badge_class = 'warning';
                                            } elseif (strpos($status, 'golive') !== false) {
                                                $badge_class = 'success';
                                            } elseif ($status === 'completed') {
                                                $badge_class = 'primary';
                                            }
                                    ?>
                                        <span class="badge bg-<?php echo $badge_class; ?> me-1">
                                            <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    <?php endforeach; endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>WP Conversion Deadline:</th>
                                <td>
                                    <?php echo $wp_deadline_display; ?>
                                    <?php if ($missed_wp_deadline): ?>
                                        <span class="badge bg-danger ms-2">Missed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Project Deadline:</th>
                                <td>
                                    <?php echo $project_deadline_display; ?>
                                    <?php if ($missed_project_deadline): ?>
                                        <span class="badge bg-danger ms-2">Missed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th>Completion Date:</th>
                                <td><?php echo $completion_date_display; ?></td>
                            </tr>
                            <tr>
                                <th>Total Project Duration:</th>
                                <td><?php echo $project_duration; ?> days</td>
                            </tr>
                            <tr>
                                <th>Days with Webmaster:</th>
                                <td><?php echo $webmaster_days; ?> days</td>
                            </tr>
                            <tr>
                                <th>Days in QA:</th>
                                <td><?php echo $qa_days; ?> days</td>
                            </tr>
                            <tr>
                                <th>Project Links:</th>
                                <td class="project-links">
                                    <?php if (!empty($project['gp_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($project['gp_link']); ?>" target="_blank">
                                            <i class="bi bi-file-earmark-spreadsheet"></i> GP Spreadsheet
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($project['ticket_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($project['ticket_link']); ?>" target="_blank">
                                            <i class="bi bi-ticket-perforated"></i> Ticket Link
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($project['test_site_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($project['test_site_link']); ?>" target="_blank">
                                            <i class="bi bi-globe"></i> Test Site
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($project['live_site_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($project['live_site_link']); ?>" target="_blank">
                                            <i class="bi bi-globe2"></i> Live Site
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Project Statistics Cards -->
            <div class="col-md-4">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Project Progress</h5>
                        <?php
                        $progress_text = "Not Started";
                        $progress_class = "secondary";
                        $progress_percent = 0;

                        if (in_array('completed', $statuses)) {
                            $progress_text = "Completed";
                            $progress_class = "success";
                            $progress_percent = 100;
                        } elseif (in_array('golive_qa', $statuses)) {
                            $progress_text = "GoLive QA";
                            $progress_class = "success";
                            $progress_percent = 90;
                        } elseif (in_array('golive', $statuses)) {
                            $progress_text = "GoLive";
                            $progress_class = "info";
                            $progress_percent = 75;
                        } elseif (in_array('page_creation_qa', $statuses)) {
                            $progress_text = "Page Creation QA";
                            $progress_class = "success";
                            $progress_percent = 60;
                        } elseif (in_array('page_creation', $statuses)) {
                            $progress_text = "Page Creation";
                            $progress_class = "info";
                            $progress_percent = 45;
                        } elseif (in_array('wp_conversion_qa', $statuses)) {
                            $progress_text = "WP Conversion QA";
                            $progress_class = "success";
                            $progress_percent = 30;
                        } elseif (in_array('wp_conversion', $statuses)) {
                            $progress_text = "WP Conversion";
                            $progress_class = "info";
                            $progress_percent = 15;
                        }
                        ?>
                        <h2 class="display-4 text-<?php echo $progress_class; ?>"><?php echo $progress_percent; ?>%</h2>
                        <p class="lead"><?php echo $progress_text; ?></p>
                        <div class="progress">
                            <div class="progress-bar bg-<?php echo $progress_class; ?>"
                                 role="progressbar"
                                 style="width: <?php echo $progress_percent; ?>%"
                                 aria-valuenow="<?php echo $progress_percent; ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h5 class="card-title">Time Distribution</h5>
                        <div class="chart-container" style="position: relative; height:200px;">
                            <canvas id="timeDistribution"></canvas>
                        </div>
                        <div class="mt-3">
                            <span class="badge bg-primary p-2 me-2">Webmaster: <?php echo $webmaster_days; ?> days</span>
                            <span class="badge bg-success p-2">QA: <?php echo $qa_days; ?> days</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Failed Items by Stage</h5>
                        <?php if (empty($failed_by_stage)): ?>
                            <div class="text-center mt-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                                <p class="lead mt-2">No failed items</p>
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($failed_by_stage as $stage => $items): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <?php echo ucwords(str_replace('_', ' ', $stage)); ?>
                                        </span>
                                        <span class="badge bg-danger rounded-pill"><?php echo count($items); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="mt-2">
                                <a href="view_project.php?id=<?php echo $project_id; ?>" class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline Section -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Project Timeline</h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <h5>Project Created</h5>
                            <p class="text-muted"><?php echo $created_at_display; ?></p>
                            <p>Project assigned to <?php echo htmlspecialchars($project['webmaster_name'] ?? 'webmaster'); ?></p>
                        </div>
                    </div>

                    <?php
                    $history->data_seek(0);
                    $count = 0;
                    while ($event = $history->fetch_assoc()):
                        $count++;
                        $status_name = ucwords(str_replace('_', ' ', $event['status']));
                        $event_date = date('F j, Y g:i A', strtotime($event['created_at']));
                        $is_even = $count % 2 == 0;
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <h5><?php echo $status_name; ?></h5>
                                <p class="text-muted"><?php echo $event_date; ?></p>
                                <p>Status updated <?php echo !empty($event['username']) ? 'by ' . htmlspecialchars($event['username']) : ''; ?></p>

                                <?php if ($event['status'] == 'wp_conversion_qa' && $missed_wp_deadline): ?>
                                    <div class="alert alert-danger">
                                        <strong>WP Conversion Deadline Missed</strong><br>
                                        Reason: <?php echo htmlspecialchars($missed_wp_deadline['reason'] ?? 'No reason provided'); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($event['status'] == 'golive_qa' && $missed_project_deadline): ?>
                                    <div class="alert alert-danger">
                                        <strong>Project Deadline Missed</strong><br>
                                        Reason: <?php echo htmlspecialchars($missed_project_deadline['reason'] ?? 'No reason provided'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <?php if ($completion_date): ?>
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <h5>Project Completed</h5>
                                <p class="text-muted"><?php echo $completion_date_display; ?></p>
                                <p>Total duration: <?php echo $project_duration; ?> days</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Missed Deadlines Section (if any) -->
        <?php if ($missed_wp_deadline || $missed_project_deadline): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h3 class="mb-0">Missed Deadlines</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($missed_wp_deadline): ?>
                    <div class="col-md-6">
                        <div class="card border-danger mb-3">
                            <div class="card-header bg-danger text-white">WP Conversion Deadline Missed</div>
                            <div class="card-body">
                                <h5 class="card-title">Original Deadline: <?php echo date('F j, Y', strtotime($missed_wp_deadline['original_deadline'])); ?></h5>
                                <p class="card-text">
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($missed_wp_deadline['reason'] ?? 'No reason provided'); ?>
                                </p>
                                <?php if ($missed_wp_deadline['reason_provided_at']): ?>
                                <p class="card-text text-muted small">
                                    Reason provided on <?php echo date('F j, Y', strtotime($missed_wp_deadline['reason_provided_at'])); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($missed_project_deadline): ?>
                    <div class="col-md-6">
                        <div class="card border-danger mb-3">
                            <div class="card-header bg-danger text-white">Project Deadline Missed</div>
                            <div class="card-body">
                                <h5 class="card-title">Original Deadline: <?php echo date('F j, Y', strtotime($missed_project_deadline['original_deadline'])); ?></h5>
                                <p class="card-text">
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($missed_project_deadline['reason'] ?? 'No reason provided'); ?>
                                </p>
                                <?php if ($missed_project_deadline['reason_provided_at']): ?>
                                <p class="card-text text-muted small">
                                    Reason provided on <?php echo date('F j, Y', strtotime($missed_project_deadline['reason_provided_at'])); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin/Webmaster Notes Section -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Project Notes</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Admin Notes</h5>
                        <div class="border rounded p-3">
                            <?php if (!empty($project['admin_notes'])): ?>
                                <div class="rich-text-content">
                                    <?php echo cleanRichText($project['admin_notes']); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No admin notes.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Webmaster Notes</h5>
                        <div class="border rounded p-3">
                            <?php if (!empty($project['webmaster_notes'])): ?>
                                <div class="rich-text-content">
                                    <?php
                                    // Output the content directly without htmlspecialchars
                                    echo $project['webmaster_notes'];
                                    ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No webmaster notes.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Time Distribution Chart
            const ctx = document.getElementById('timeDistribution').getContext('2d');
            const timeChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Webmaster Time', 'QA Time'],
                    datasets: [{
                        data: [<?php echo $webmaster_days; ?>, <?php echo $qa_days; ?>],
                        backgroundColor: [
                            'rgba(13, 110, 253, 0.7)',
                            'rgba(25, 135, 84, 0.7)'
                        ],
                        borderColor: [
                            'rgba(13, 110, 253, 1)',
                            'rgba(25, 135, 84, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>