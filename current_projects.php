<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Skip login check since this page will be publicly accessible

// Get all projects that are NOT in golive_qa or completed status
$query = "SELECT p.*, u.username as webmaster_name,
         (DATEDIFF(CURRENT_DATE, p.created_at) + 1) as days_active,
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

    // Group projects by stage for filtering
    $main_status = !empty($statuses) ? end($statuses) : 'no_status';
    $row['main_status'] = $main_status;

    $projects[] = $row;
}

// Count projects by status
$status_counts = [];
foreach ($projects as $project) {
    $main_status = $project['main_status'];
    if (!isset($status_counts[$main_status])) {
        $status_counts[$main_status] = 0;
    }
    $status_counts[$main_status]++;
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
    <title>Current Projects Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #f72585;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #ffd166;
            --info-color: #4895ef;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgcGF0dGVyblRyYW5zZm9ybT0icm90YXRlKDQ1KSI+PHJlY3QgaWQ9InBhdHRlcm4tYmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMDMpIj48L3JlY3Q+PHBhdGggZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIgZD0iTTAgMGwxMCAxMFoiPjwvcGF0aD48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IGZpbGw9InVybCgjcGF0dGVybikiIGhlaWdodD0iMTAwJSIgd2lkdGg9IjEwMCUiPjwvcmVjdD48L3N2Zz4=');
            opacity: 0.5;
        }

        .header h1 {
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .header .badge {
            position: relative;
            z-index: 1;
        }

        .project-card {
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            height: 100%;
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            background: white;
        }

        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
        }

        .card-header h3 {
            margin-bottom: 0;
            font-weight: 600;
            font-size: 1.25rem;
            line-height: 1.4;
        }

        .card-body {
            padding: 1.5rem;
        }

        .project-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            text-align: center;
            flex: 1;
            min-width: calc(50% - 10px);
            padding: 1rem 0.75rem;
            background-color: #f8f9fa;
            border-radius: 12px;
            margin: 0;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }

        .meta-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            opacity: 0.5;
        }

        .meta-item.days-active::before {
            background: var(--danger-color);
        }

        .meta-item.days-to-completion::before {
            background: var(--success-color);
        }

        .meta-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.05);
            background-color: #f2f3f5;
        }

        .meta-item .value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
        }

        .meta-item .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-top: 5px;
            display: block;
        }

        .progress-container {
            margin-bottom: 1.5rem;
            position: relative;
            padding-top: 5px;
            padding-bottom: 10px;
        }

        .progress {
            height: 0.75rem;
            border-radius: 1rem;
            background-color: #e9ecef;
            margin-bottom: 0.5rem;
            overflow: visible;
        }

        .progress-bar {
            border-radius: 1rem;
            position: relative;
            transition: width 1s ease-in-out;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translate(50%, -50%);
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: white;
            border: 3px solid;
            border-color: inherit;
        }

        .progress-status {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
        }

        .progress-text {
            font-weight: 600;
        }

        .project-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .project-links a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--dark-gray);
            text-decoration: none;
            border-radius: 12px;
            background-color: #f8f9fa;
            transition: all 0.2s;
            flex: 1;
            min-width: calc(50% - 10px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .project-links a:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.05);
        }

        .project-links a i {
            margin-right: 10px;
            font-size: 1.25rem;
            color: var(--primary-color);
        }

        .project-links a.gp-link i {
            color: #34a853;
        }

        .project-links a.ticket-link i {
            color: #fbbc04;
        }

        .project-links a.test-link i {
            color: #4285f4;
        }

        .project-links a.live-link i {
            color: #ea4335;
        }

        .notes-container {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 12px;
            margin-top: 1rem;
            max-height: 150px;
            overflow-y: auto;
            border-left: 4px solid #dee2e6;
        }

        .notes-container.admin-notes {
            border-left-color: var(--primary-color);
        }

        .notes-container.webmaster-notes {
            border-left-color: var(--info-color);
        }

        .notes-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #495057;
            display: flex;
            align-items: center;
        }

        .notes-title i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        .footer {
            background-color: var(--dark-gray);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
            position: relative;
        }

        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
            font-weight: 700;
        }

        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 50px;
            height: 4px;
            background-color: var(--accent-color);
            border-radius: 2px;
        }

        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            letter-spacing: 0.5px;
            border-radius: 8px;
        }

        .days-counter {
            font-weight: 700;
            font-size: 1.75rem;
            line-height: 1;
            display: block;
            color: var(--primary-color);
        }

        .days-active {
            color: var(--danger-color);
        }

        .days-to-completion {
            color: var(--success-color);
        }

        .status-badges {
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .description-text {
            color: rgba(255,255,255,0.8);
            line-height: 1.6;
        }

        .search-container {
            position: relative;
            margin-bottom: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-box input {
            padding: 1rem 1rem 1rem 3rem;
            border-radius: 30px;
            border: none;
            width: 100%;
            font-size: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .search-box input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.25), 0 5px 15px rgba(0,0,0,0.1);
        }

        .search-box .bi-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            background-color: white;
            border: 2px solid transparent;
            color: var (--dark-gray);
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }

        .filter-btn.active {
            color: white;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .filter-btn .count {
            display: inline-block;
            background-color: rgba(0,0,0,0.1);
            border-radius: 30px;
            padding: 2px 8px;
            margin-left: 8px;
            font-size: 0.8rem;
        }

        .filter-btn.active .count {
            background-color: rgba(255,255,255,0.3);
        }

        .expand-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            padding: 0;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }

        .expand-btn:hover {
            color: var(--secondary-color);
        }

        .expand-btn i {
            transition: transform 0.2s;
            margin-right: 5px;
        }

        .collapsed-notes {
            max-height: 80px;
            overflow: hidden;
            position: relative;
        }

        .collapsed-notes::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 30px;
            background: linear-gradient(transparent, #f8f9fa);
        }

        .project-deadline {
            font-weight: 600;
            padding: 8px 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .project-deadline i {
            margin-right: 5px;
        }

        .no-projects {
            text-align: center;
            padding: 4rem 2rem;
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .no-projects i {
            font-size: 5rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .no-projects h3 {
            font-weight: 600;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .meta-item {
                min-width: calc(50% - 5px);
            }

            .project-links a {
                flex: 0 0 100%;
            }

            .filter-container {
                overflow-x: auto;
                justify-content: flex-start;
                padding-bottom: 10px;
            }

            .filter-btn {
                flex: 0 0 auto;
            }

            .header {
                padding: 1.5rem 0;
            }

            .header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center" data-aos="fade-down">
                <h1>Project Status Dashboard</h1>
                <span class="badge bg-light text-dark fs-6 mt-2 mt-md-0">
                    <i class="bi bi-clock"></i> Last Updated: <?php echo date('F j, Y, g:i a'); ?>
                </span>
            </div>
        </div>
    </header>

    <div class="container py-5">
        <div class="row mb-4" data-aos="fade-up">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="section-title">Current Active Projects</h2>
                <p class="description-text">
                    This dashboard shows all current active projects in development.
                    Projects in golive QA or completed status are not displayed here.
                </p>
            </div>
        </div>

        <div class="search-container" data-aos="fade-up" data-aos-delay="100">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="projectSearch" placeholder="Search by project name, webmaster, or status...">
            </div>

            <div class="filter-container">
                <button class="filter-btn active" data-filter="all">
                    All Projects <span class="count"><?php echo count($projects); ?></span>
                </button>
                <?php
                foreach ($status_counts as $status => $count):
                    if ($status == 'no_status') {
                        $display_name = 'No Status';
                        $badge_class = 'secondary';
                    } else {
                        $display_name = ucwords(str_replace('_', ' ', $status));
                        $badge_class = getStatusBadgeClass($status);
                    }
                ?>
                <button class="filter-btn filter-<?php echo $badge_class; ?>" data-filter="<?php echo $status; ?>">
                    <?php echo $display_name; ?> <span class="count"><?php echo $count; ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (count($projects) == 0): ?>
            <div class="no-projects" data-aos="zoom-in">
                <i class="bi bi-clipboard-check"></i>
                <h3>No Active Projects</h3>
                <p class="text-muted">There are currently no active projects in the system.</p>
            </div>
        <?php else: ?>

        <div class="row row-cols-1 row-cols-lg-2 g-4 project-container">
            <?php
            $delay = 150;
            foreach ($projects as $project):
            ?>
                <div class="col project-item" data-status="<?php echo $project['main_status']; ?>" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="card project-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3><?php echo htmlspecialchars($project['name']); ?></h3>
                                <?php
                                $statuses = !empty($project['current_status']) ? explode(',', $project['current_status']) : [];
                                if (!empty($statuses)):
                                    $main_status = end($statuses); // Get the last/latest status
                                    $badge_class = getStatusBadgeClass($main_status);
                                ?>
                                    <span class="badge bg-<?php echo $badge_class; ?> fs-6">
                                        <?php echo ucwords(str_replace('_', ' ', $main_status)); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary fs-6">No Status</span>
                                <?php endif; ?>
                            </div>

                            <div class="status-badges">
                                <?php
                                if (!empty($statuses)):
                                    foreach($statuses as $status):
                                        if ($status !== $main_status): // Don't repeat the main status
                                            $badge_class = getStatusBadgeClass($status);
                                ?>
                                        <span class="badge bg-<?php echo $badge_class; ?> bg-opacity-75">
                                            <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                        </span>
                                <?php
                                        endif;
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="project-meta">
                                <div class="meta-item days-active">
                                    <span class="value days-counter"><?php echo $project['days_active']; ?></span>
                                    <span class="label">Days Active</span>
                                </div>

                                <div class="meta-item">
                                    <span class="value">
                                        <?php echo htmlspecialchars($project['webmaster_name'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="label">Webmaster</span>
                                </div>

                                <div class="meta-item">
                                    <span class="value">
                                        <?php echo date('M j', strtotime($project['assigned_date'])); ?>
                                    </span>
                                    <span class="label">Assigned Date</span>
                                </div>

                                <?php if (!is_null($project['days_to_golive_qa'])): ?>
                                <div class="meta-item days-to-completion">
                                    <span class="value days-counter"><?php echo $project['days_to_golive_qa']; ?></span>
                                    <span class="label">Days to GoLive QA</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="progress-container">
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $project['progress_class']; ?>"
                                         role="progressbar"
                                         style="width: 0%"
                                         data-width="<?php echo $project['progress_percent']; ?>%"
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

                            <h6 class="fw-bold mb-3">Project Links</h6>
                            <div class="project-links">
                                <?php if (!empty($project['gp_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['gp_link']); ?>" target="_blank" title="<?php echo htmlspecialchars($project['gp_link']); ?>" class="gp-link">
                                        <i class="bi bi-file-earmark-spreadsheet"></i> GP Spreadsheet
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($project['ticket_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['ticket_link']); ?>" target="_blank" title="<?php echo htmlspecialchars($project['ticket_link']); ?>" class="ticket-link">
                                        <i class="bi bi-ticket-perforated"></i> Ticket Link
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($project['test_site_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['test_site_link']); ?>" target="_blank" title="<?php echo htmlspecialchars($project['test_site_link']); ?>" class="test-link">
                                        <i class="bi bi-globe"></i> Test Site
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($project['live_site_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($project['live_site_link']); ?>" target="_blank" title="<?php echo htmlspecialchars($project['live_site_link']); ?>" class="live-link">
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
                                                <h6 class="notes-title"><i class="bi bi-person-badge"></i> Admin Notes:</h6>
                                                <div class="notes-container admin-notes mb-2">
                                                    <?php echo nl2br(htmlspecialchars($project['admin_notes'])); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($project['webmaster_notes'])): ?>
                                                <h6 class="notes-title"><i class="bi bi-person"></i> Webmaster Notes:</h6>
                                                <div class="notes-container webmaster-notes">
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
            <?php
                $delay += 100;
            endforeach;
            ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true
        });

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('projectSearch');
            const projectItems = document.querySelectorAll('.project-item');
            const filterButtons = document.querySelectorAll('.filter-btn');
            const progressBars = document.querySelectorAll('.progress-bar');

            // Animate progress bars
            progressBars.forEach(bar => {
                const width = bar.getAttribute('data-width');
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });

            // Filter projects
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    projectItems.forEach(item => {
                        const status = item.getAttribute('data-status');
                        if (filter === 'all' || filter === status) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });

            // Search projects
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                projectItems.forEach(item => {
                    const name = item.querySelector('h3').textContent.toLowerCase();
                    const webmaster = item.querySelector('.meta-item:nth-child(2) .value').textContent.toLowerCase();
                    const status = item.getAttribute('data-status').toLowerCase();
                    if (name.includes(query) || webmaster.includes(query) || status.includes(query)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>