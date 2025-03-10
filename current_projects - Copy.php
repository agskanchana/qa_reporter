<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Skip login check since this page will be publicly accessible

// Get all projects that are NOT in golive_qa or completed status
$query = "SELECT p.*, u.username as webmaster_name,
         (DATEDIFF(CURRENT_DATE, p.created_at) + 1) as days_active, /* Add +1 here */
         p.created_at as assigned_date
         FROM projects p
         LEFT JOIN users u ON p.webmaster_id = u.id
         WHERE (p.current_status NOT LIKE '%golive_qa%'
                AND p.current_status NOT LIKE '%completed%')
                OR p.current_status IS NULL
         ORDER BY p.created_at DESC";

$result = $conn->query($query);
$projects = [];

while ($row = $result->fetch_assoc()) {
    // Calculate days from creation to first golive_qa
    $project_id = $row['id'];

    // Check when project first reached golive_qa status (if ever)
    $history_query = "SELECT created_at
                     FROM project_status_history
                     WHERE project_id = ? AND status = 'golive_qa'
                     ORDER BY created_at ASC
                     LIMIT 1";
    $stmt = $conn->prepare($history_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $history_result = $stmt->get_result();

    if ($history_result->num_rows > 0) {
        $golive_date = new DateTime($history_result->fetch_assoc()['created_at']);
        $created_date = new DateTime($row['created_at']);
        $interval = $created_date->diff($golive_date);
        $row['days_to_golive_qa'] = $interval->days;
    } else {
        $row['days_to_golive_qa'] = null;
    }

    // Calculate project progress
    $row['progress_percent'] = 0;
    $row['progress_class'] = "secondary";
    $row['progress_text'] = "Not Started";

    $statuses = !empty($row['current_status']) ? explode(',', $row['current_status']) : [];

    if (in_array('completed', $statuses)) {
        $row['progress_percent'] = 100;
        $row['progress_class'] = "success";
        $row['progress_text'] = "Completed";
    } elseif (in_array('golive_qa', $statuses)) {
        $row['progress_percent'] = 90;
        $row['progress_class'] = "success";
        $row['progress_text'] = "GoLive QA";
    } elseif (in_array('golive', $statuses)) {
        $row['progress_percent'] = 75;
        $row['progress_class'] = "info";
        $row['progress_text'] = "GoLive";
    } elseif (in_array('page_creation_qa', $statuses)) {
        $row['progress_percent'] = 60;
        $row['progress_class'] = "success";
        $row['progress_text'] = "Page Creation QA";
    } elseif (in_array('page_creation', $statuses)) {
        $row['progress_percent'] = 45;
        $row['progress_class'] = "info";
        $row['progress_text'] = "Page Creation";
    } elseif (in_array('wp_conversion_qa', $statuses)) {
        $row['progress_percent'] = 30;
        $row['progress_class'] = "success";
        $row['progress_text'] = "WP Conversion QA";
    } elseif (in_array('wp_conversion', $statuses)) {
        $row['progress_percent'] = 15;
        $row['progress_class'] = "info";
        $row['progress_text'] = "WP Conversion";
    }

    $projects[] = $row;
}

// Function to get badge class for status
function getStatusBadgeClass($status) {
    if (strpos($status, 'wp_conversion') !== false) {
        return 'info';
    } elseif (strpos($status, 'page_creation') !== false) {
        return 'warning';
    } elseif (strpos($status, 'golive') !== false) {
        return 'success';
    } else {
        return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Projects</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .project-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-header {
            font-weight: bold;
            background-color: #f1f1f1;
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
        .notes-container {
            max-height: 100px;
            overflow-y: auto;
        }
        .badge {
            font-size: 85%;
        }
        .days-counter {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .days-active {
            color: #dc3545;
        }
        .days-to-completion {
            color: #198754;
        }
        .footer {
            margin-top: 40px;
            padding: 20px 0;
            background-color: #343a40;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Project Status Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Current Projects</h1>
            <p class="text-muted">Last updated: <?php echo date('F j, Y, g:i a'); ?></p>
        </div>

        <?php if (count($projects) == 0): ?>
            <div class="alert alert-info">
                <h4 class="alert-heading">No active projects!</h4>
                <p>There are currently no active projects in the system.</p>
            </div>
        <?php else: ?>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($projects as $project): ?>
                <div class="col">
                    <div class="card project-card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($project['name']); ?></h5>
                            <?php
                            $statuses = !empty($project['current_status']) ? explode(',', $project['current_status']) : [];
                            if (!empty($statuses)):
                                foreach($statuses as $status):
                                    $badge_class = getStatusBadgeClass($status);
                            ?>
                                <span class="badge bg-<?php echo $badge_class; ?> ms-1">
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </span>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <span class="badge bg-secondary">No Status</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><strong>Assigned to:</strong></span>
                                    <span><?php echo htmlspecialchars($project['webmaster_name'] ?? 'Not assigned'); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Assigned on:</strong></span>
                                    <span><?php echo date('M j, Y', strtotime($project['assigned_date'])); ?></span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="text-muted">Days active:</span>
                                    <span class="days-counter days-active"><?php echo $project['days_active']; ?></span>
                                </div>

                                <?php if (!is_null($project['days_to_golive_qa'])): ?>
                                <div>
                                    <span class="text-muted">Days to GoLive QA:</span>
                                    <span class="days-counter days-to-completion"><?php echo $project['days_to_golive_qa']; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="progress-container">
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $project['progress_class']; ?>"
                                         role="progressbar"
                                         style="width: <?php echo $project['progress_percent']; ?>%"
                                         aria-valuenow="<?php echo $project['progress_percent']; ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="progress-status">
                                    <span class="progress-text text-<?php echo $project['progress_class']; ?>">
                                        <?php echo $project['progress_text']; ?>
                                    </span>
                                    <span><?php echo $project['progress_percent']; ?>%</span>
                                </div>
                            </div>

                            <h6 class="card-subtitle mb-2 text-muted">Project Links</h6>
                            <div class="project-links mb-3">
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
                            </div>

                            <div class="accordion" id="notesAccordion<?php echo $project['id']; ?>">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingNotes<?php echo $project['id']; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#collapseNotes<?php echo $project['id']; ?>">
                                            Project Notes
                                        </button>
                                    </h2>
                                    <div id="collapseNotes<?php echo $project['id']; ?>" class="accordion-collapse collapse"
                                         aria-labelledby="headingNotes<?php echo $project['id']; ?>">
                                        <div class="accordion-body">
                                            <?php if (!empty($project['admin_notes'])): ?>
                                                <h6>Admin Notes:</h6>
                                                <div class="notes-container mb-2">
                                                    <?php echo nl2br(htmlspecialchars($project['admin_notes'])); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($project['webmaster_notes'])): ?>
                                                <h6>Webmaster Notes:</h6>
                                                <div class="notes-container">
                                                    <?php echo nl2br(htmlspecialchars($project['webmaster_notes'])); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (empty($project['admin_notes']) && empty($project['webmaster_notes'])): ?>
                                                <p class="text-muted">No notes available for this project.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>