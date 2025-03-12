<?php
// Get the most frequently failing checklist items for this webmaster
$failing_items_query = "SELECT
                        ci.id,
                        ci.title,
                        ci.stage,
                        COUNT(pcs.id) as fail_count,
                        COUNT(DISTINCT p.id) as projects_count
                      FROM checklist_items ci
                      JOIN project_checklist_status pcs ON ci.id = pcs.checklist_item_id
                      JOIN projects p ON pcs.project_id = p.id
                      WHERE p.webmaster_id = ?
                        AND pcs.status = 'failed'
                        AND ci.is_archived = 0
                      GROUP BY ci.id, ci.title, ci.stage
                      ORDER BY fail_count DESC
                      LIMIT 15";

$stmt = $conn->prepare($failing_items_query);
$stmt->bind_param("i", $webmaster_id);
$stmt->execute();
$failing_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group items by stage
$wp_conversion_items = [];
$page_creation_items = [];
$golive_items = [];

foreach ($failing_items as $item) {
    if ($item['stage'] == 'wp_conversion') {
        $wp_conversion_items[] = $item;
    } elseif ($item['stage'] == 'page_creation') {
        $page_creation_items[] = $item;
    } elseif ($item['stage'] == 'golive') {
        $golive_items[] = $item;
    }
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-exclamation-triangle me-2 text-warning"></i>
            Most Failing Checklist Items
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($failing_items)): ?>
            <div class="text-center py-4">
                <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
                <p class="text-muted mb-0">No failing checklist items found. Great job!</p>
            </div>
        <?php else: ?>
            <ul class="nav nav-tabs" id="failingItemsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="wp-conversion-tab" data-bs-toggle="tab" data-bs-target="#wp-conversion" type="button" role="tab" aria-controls="wp-conversion" aria-selected="true">
                        <i class="bi bi-wordpress text-primary me-1"></i> WP Conversion
                        <?php if (!empty($wp_conversion_items)): ?>
                            <span class="badge bg-primary ms-1"><?php echo count($wp_conversion_items); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="page-creation-tab" data-bs-toggle="tab" data-bs-target="#page-creation" type="button" role="tab" aria-controls="page-creation" aria-selected="false">
                        <i class="bi bi-file-earmark-plus text-warning me-1"></i> Page Creation
                        <?php if (!empty($page_creation_items)): ?>
                            <span class="badge bg-warning text-dark ms-1"><?php echo count($page_creation_items); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="golive-tab" data-bs-toggle="tab" data-bs-target="#golive" type="button" role="tab" aria-controls="golive" aria-selected="false">
                        <i class="bi bi-rocket text-success me-1"></i> Go-Live
                        <?php if (!empty($golive_items)): ?>
                            <span class="badge bg-success ms-1"><?php echo count($golive_items); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-3" id="failingItemsTabContent">
                <!-- WP Conversion Tab -->
                <div class="tab-pane fade show active" id="wp-conversion" role="tabpanel" aria-labelledby="wp-conversion-tab">
                    <?php if (empty($wp_conversion_items)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No failing items in WP Conversion stage.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container mb-3" style="height: 220px;">
                            <div class="row">
                                <?php foreach (array_slice($wp_conversion_items, 0, 5) as $index => $item): ?>
                                    <?php
                                    $percentage = 100; // Default to 100% for first item
                                    if ($index === 0 && !empty($wp_conversion_items)) {
                                        $max_count = $wp_conversion_items[0]['fail_count'];
                                        $percentage = ($item['fail_count'] / $max_count) * 100;
                                    }
                                    ?>
                                    <div class="col-12 mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small text-truncate" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </span>
                                            <span class="small text-muted">
                                                <?php echo $item['fail_count']; ?> failures (<?php echo $item['projects_count']; ?> projects)
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" role="progressbar"
                                                 style="width: <?php echo $percentage; ?>%"
                                                 aria-valuenow="<?php echo $percentage; ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Checklist Item</th>
                                        <th class="text-center">Failures</th>
                                        <th class="text-center">Projects</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wp_conversion_items as $item): ?>
                                        <tr>
                                            <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?php echo $item['fail_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?php echo $item['projects_count']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Page Creation Tab -->
                <div class="tab-pane fade" id="page-creation" role="tabpanel" aria-labelledby="page-creation-tab">
                    <?php if (empty($page_creation_items)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No failing items in Page Creation stage.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container mb-3" style="height: 220px;">
                            <div class="row">
                                <?php foreach (array_slice($page_creation_items, 0, 5) as $index => $item): ?>
                                    <?php
                                    $percentage = 100; // Default to 100% for first item
                                    if ($index === 0 && !empty($page_creation_items)) {
                                        $max_count = $page_creation_items[0]['fail_count'];
                                        $percentage = ($item['fail_count'] / $max_count) * 100;
                                    }
                                    ?>
                                    <div class="col-12 mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small text-truncate" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </span>
                                            <span class="small text-muted">
                                                <?php echo $item['fail_count']; ?> failures (<?php echo $item['projects_count']; ?> projects)
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" role="progressbar"
                                                 style="width: <?php echo $percentage; ?>%"
                                                 aria-valuenow="<?php echo $percentage; ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Checklist Item</th>
                                        <th class="text-center">Failures</th>
                                        <th class="text-center">Projects</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($page_creation_items as $item): ?>
                                        <tr>
                                            <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?php echo $item['fail_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?php echo $item['projects_count']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Go-Live Tab -->
                <div class="tab-pane fade" id="golive" role="tabpanel" aria-labelledby="golive-tab">
                    <?php if (empty($golive_items)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No failing items in Go-Live stage.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container mb-3" style="height: 220px;">
                            <div class="row">
                                <?php foreach (array_slice($golive_items, 0, 5) as $index => $item): ?>
                                    <?php
                                    $percentage = 100; // Default to 100% for first item
                                    if ($index === 0 && !empty($golive_items)) {
                                        $max_count = $golive_items[0]['fail_count'];
                                        $percentage = ($item['fail_count'] / $max_count) * 100;
                                    }
                                    ?>
                                    <div class="col-12 mb-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small text-truncate" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </span>
                                            <span class="small text-muted">
                                                <?php echo $item['fail_count']; ?> failures (<?php echo $item['projects_count']; ?> projects)
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                 style="width: <?php echo $percentage; ?>%"
                                                 aria-valuenow="<?php echo $percentage; ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Checklist Item</th>
                                        <th class="text-center">Failures</th>
                                        <th class="text-center">Projects</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($golive_items as $item): ?>
                                        <tr>
                                            <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?php echo $item['fail_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?php echo $item['projects_count']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($failing_items)): ?>
    <div class="card-footer bg-white text-center p-2">
        <a href="<?php echo BASE_URL; ?>/webmaster/performance_report.php" class="text-decoration-none">
            <i class="bi bi-bar-chart-line me-1"></i> View Full Performance Report
        </a>
    </div>
    <?php endif; ?>
</div>