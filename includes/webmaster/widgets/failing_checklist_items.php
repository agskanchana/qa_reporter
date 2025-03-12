<?php
// Get the most frequently failing checklist items for this webmaster's projects
$failing_items_query = "SELECT ci.id, ci.title, ci.stage, COUNT(*) as failure_count
                      FROM project_checklist_status pcs
                      JOIN checklist_items ci ON pcs.checklist_item_id = ci.id
                      JOIN projects p ON pcs.project_id = p.id
                      WHERE p.webmaster_id = ?
                      AND pcs.status = 'failed'
                      AND p.current_status NOT LIKE '%complete%'
                      GROUP BY ci.id
                      ORDER BY failure_count DESC
                      LIMIT 5";

$failing_items_stmt = $conn->prepare($failing_items_query);
$failing_items_stmt->bind_param("i", $webmaster_id);
$failing_items_stmt->execute();
$failing_items_result = $failing_items_stmt->get_result();
$failing_items = $failing_items_result->fetch_all(MYSQLI_ASSOC);
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-exclamation-triangle me-2 text-warning"></i>
            Most Failing Checklist Items
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($failing_items)): ?>
            <div class="text-center py-4">
                <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
                <p class="text-muted mb-0">No failing checklist items. Keep up the good work!</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($failing_items as $item): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($item['title']); ?></h6>
                            <span class="badge bg-danger rounded-pill"><?php echo $item['failure_count']; ?></span>
                        </div>
                        <?php
                        $stageClass = 'secondary';
                        $stageName = 'Unknown';

                        if ($item['stage'] === 'wp_conversion') {
                            $stageClass = 'info';
                            $stageName = 'WP Conversion';
                        } elseif ($item['stage'] === 'page_creation') {
                            $stageClass = 'warning';
                            $stageName = 'Page Creation';
                        } elseif ($item['stage'] === 'golive') {
                            $stageClass = 'primary';
                            $stageName = 'Go-Live';
                        }
                        ?>
                        <span class="badge bg-<?php echo $stageClass; ?> mt-1"><?php echo $stageName; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if (count($failing_items) > 0): ?>
    <div class="card-footer bg-light text-center p-2">
        <a href="<?php echo BASE_URL; ?>/webmaster/checklist_guide.php" class="text-decoration-none">
            <i class="bi bi-journals me-1"></i> View Checklist Guidelines
        </a>
    </div>
    <?php endif; ?>
</div>