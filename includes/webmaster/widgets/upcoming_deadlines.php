<?php
// Get all upcoming deadlines for this webmaster's projects
$upcoming_deadlines_query = "SELECT p.id, p.name,
                          p.wp_conversion_deadline,
                          DATEDIFF(p.wp_conversion_deadline, CURDATE()) as wp_days_remaining,
                          p.project_deadline,
                          DATEDIFF(p.project_deadline, CURDATE()) as project_days_remaining,
                          p.current_status
                          FROM projects p
                          WHERE p.webmaster_id = ?
                          AND p.current_status NOT LIKE '%complete%'
                          AND (
                            (p.wp_conversion_deadline IS NOT NULL AND p.wp_conversion_deadline >= CURDATE()) OR
                            (p.project_deadline IS NOT NULL AND p.project_deadline >= CURDATE())
                          )
                          ORDER BY
                            CASE
                              WHEN p.wp_conversion_deadline IS NOT NULL AND p.wp_conversion_deadline >= CURDATE()
                                THEN p.wp_conversion_deadline
                              ELSE p.project_deadline
                            END ASC
                          LIMIT 10";

$upcoming_deadlines_stmt = $conn->prepare($upcoming_deadlines_query);
$upcoming_deadlines_stmt->bind_param("i", $webmaster_id);
$upcoming_deadlines_stmt->execute();
$upcoming_deadlines_result = $upcoming_deadlines_stmt->get_result();
$all_deadlines = $upcoming_deadlines_result->fetch_all(MYSQLI_ASSOC);

// Process the deadlines to create a timeline view
$timeline_items = [];

foreach ($all_deadlines as $project) {
    // Add WP conversion deadline if it exists and is in the future
    if (!empty($project['wp_conversion_deadline']) && $project['wp_days_remaining'] >= 0) {
        $timeline_items[] = [
            'project_id' => $project['id'],
            'project_name' => $project['name'],
            'deadline_type' => 'WP Conversion',
            'deadline_date' => $project['wp_conversion_deadline'],
            'days_remaining' => $project['wp_days_remaining'],
            'icon' => 'wordpress'
        ];
    }

    // Add project deadline if it exists and is in the future
    if (!empty($project['project_deadline']) && $project['project_days_remaining'] >= 0) {
        $timeline_items[] = [
            'project_id' => $project['id'],
            'project_name' => $project['name'],
            'deadline_type' => 'Project Completion',
            'deadline_date' => $project['project_deadline'],
            'days_remaining' => $project['project_days_remaining'],
            'icon' => 'calendar-check'
        ];
    }
}

// Sort by deadline date (ascending)
usort($timeline_items, function($a, $b) {
    return strtotime($a['deadline_date']) - strtotime($b['deadline_date']);
});

// Limit to first 5 items for display
$timeline_items = array_slice($timeline_items, 0, 5);
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-calendar-event me-2 text-primary"></i>
            Upcoming Deadlines
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($timeline_items)): ?>
            <div class="text-center py-4">
                <i class="bi bi-calendar-check text-success fs-1 mb-3"></i>
                <p class="text-muted mb-0">No upcoming deadlines at this time.</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($timeline_items as $item): ?>
                    <?php
                    $deadlineClass = 'success';
                    if ($item['days_remaining'] <= 1) {
                        $deadlineClass = 'danger';
                    } elseif ($item['days_remaining'] <= 3) {
                        $deadlineClass = 'warning';
                    } elseif ($item['days_remaining'] <= 7) {
                        $deadlineClass = 'info';
                    }
                    ?>
                    <div class="timeline-item mb-3">
                        <div class="timeline-marker bg-<?php echo $deadlineClass; ?> rounded-circle text-white p-2 d-inline-block me-3">
                            <i class="bi bi-<?php echo $item['icon']; ?>"></i>
                        </div>
                        <div class="timeline-content d-inline-block">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['project_name']); ?></h6>
                                <span class="badge bg-<?php echo $deadlineClass; ?>">
                                    <?php echo date('M j', strtotime($item['deadline_date'])); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-light text-dark">
                                        <?php echo $item['deadline_type']; ?>
                                    </span>
                                    <small class="text-muted d-block mt-1">
                                        <?php
                                        if ($item['days_remaining'] == 0) {
                                            echo '<span class="text-danger fw-bold">Due today!</span>';
                                        } elseif ($item['days_remaining'] == 1) {
                                            echo '<span class="text-danger fw-bold">Due tomorrow!</span>';
                                        } else {
                                            echo $item['days_remaining'] . ' days remaining';
                                        }
                                        ?>
                                    </small>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/view_project.php?id=<?php echo $item['project_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if (count($timeline_items) >= 5): ?>
    <div class="card-footer bg-white text-center p-2">
        <a href="<?php echo BASE_URL; ?>/webmaster/deadlines_calendar.php" class="text-decoration-none">
            <i class="bi bi-calendar3 me-1"></i> View Full Calendar
        </a>
    </div>
    <?php endif; ?>
</div>