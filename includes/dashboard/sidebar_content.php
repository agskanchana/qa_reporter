<?php
if (!empty($notifications)): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-bell"></i> Notifications
                        <span class="badge bg-danger ms-2"><?php echo count($notifications); ?></span>
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseNotifications">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>
                <div class="card-body" id="notificationsBody">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="alert alert-<?php echo $notification['type']; ?> d-flex align-items-center mb-2">
                            <div>
                                <?php if ($notification['type'] === 'danger'): ?>
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php elseif ($notification['type'] === 'warning'): ?>
                                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                                <?php elseif ($notification['type'] === 'success'): ?>
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                <?php endif; ?>
                                <?php echo $notification['message']; ?>
                            </div>
                            <?php if (isset($notification['id'])): ?>
                            <button type="button" class="btn-close ms-auto mark-read" data-id="<?php echo $notification['id']; ?>"></button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
        <?php if($user_role === 'webmaster'):?>
            <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Most Failing Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="accordion" id="failingItemsAccordion">
                            <?php
                            $stages = ['wp_conversion' => 'WP Conversion',
                                      'page_creation' => 'Page Creation',
                                      'golive' => 'Golive'];

                            foreach ($stages as $stage_key => $stage_name):
                                $items = $failing_items[$stage_key] ?? [];
                            ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#failing<?php echo $stage_key; ?>">
                                            <?php echo $stage_name; ?>
                                            <?php if (!empty($items)): ?>
                                                <span class="badge bg-danger ms-2">
                                                    <?php echo count($items); ?>
                                                </span>
                                            <?php endif; ?>
                                        </button>
                                    </h2>
                                    <div id="failing<?php echo $stage_key; ?>"
                                         class="accordion-collapse collapse"
                                         data-bs-parent="#failingItemsAccordion">
                                        <div class="accordion-body p-2">
                                            <?php if (empty($items)): ?>
                                                <p class="text-muted mb-0">No failing items</p>
                                            <?php else: ?>
                                                <?php foreach ($items as $item): ?>
                                                    <div class="card mb-2">
                                                        <div class="card-body p-2">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small><?php echo htmlspecialchars($item['title']); ?></small>
                                                                <span class="badge bg-danger">
                                                                    <?php echo $item['fail_count']; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
        <?php endif;?>
        <?php if ($user_role === 'admin'): ?>
        <div class="card mb-4">
                <div class="card-header ">
                    <h5 class="card-title mb-0">Webmaster Projects</h5>
                </div>
                <div class="card-body p-0">
                    <div class="accordion" id="webmasterAccordion">
                        <?php foreach ($webmaster_projects as $webmaster_id => $webmaster): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#webmaster<?php echo $webmaster_id; ?>">
                                        <?php echo htmlspecialchars($webmaster['name']); ?>
                                        <span class="badge bg-<?php echo count($webmaster['projects']) === 0 ? 'danger' : 'primary'; ?> ms-2">
                                            <?php echo count($webmaster['projects']); ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="webmaster<?php echo $webmaster_id; ?>"
                                     class="accordion-collapse collapse"
                                     data-bs-parent="#webmasterAccordion">
                                    <div class="accordion-body p-2">
                                        <?php if (empty($webmaster['projects'])): ?>
                                            <p class="text-muted mb-0">No active projects</p>
                                        <?php else: ?>
                                            <?php foreach ($webmaster['projects'] as $project): ?>
                                                <div class="card mb-2">
                                                    <div class="card-body p-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small><?php echo htmlspecialchars($project['name']); ?></small>
                                                            <span class="badge bg-<?php
                                                                $status_class = 'secondary'; // Default value
                                                                if ($project['status'] === 'wp_conversion') {
                                                                    $status_class = 'info';
                                                                } elseif ($project['status'] === 'page_creation') {
                                                                    $status_class = 'warning';
                                                                } elseif ($project['status'] === 'golive') {
                                                                    $status_class = 'primary';
                                                                }
                                                                echo $status_class;
                                                            ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php
            endif;

            if (in_array($user_role, ['admin', 'qa_manager'])): ?>

   <div class="card ">
        <div class="card-header">
            <h5 class="card-title mb-0">QA Reporter Projects</h5>
        </div>
        <div class="card-body p-0">
            <div class="accordion" id="qaReporterAccordion">
                <?php foreach ($qa_reporter_projects as $qa_id => $qa): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#qa<?php echo $qa_id; ?>">
                                <?php echo htmlspecialchars($qa['name']); ?>
                                <span class="badge bg-<?php echo count($qa['projects']) === 0 ? 'danger' : 'success'; ?> ms-2">
                                    <?php echo count($qa['projects']); ?>
                                </span>
                            </button>
                        </h2>
                        <div id="qa<?php echo $qa_id; ?>"
                             class="accordion-collapse collapse"
                             data-bs-parent="#qaReporterAccordion">
                            <div class="accordion-body p-2">
                                <?php if (empty($qa['projects'])): ?>
                                    <p class="text-muted mb-0">No active projects</p>
                                <?php else: ?>
                                    <?php foreach ($qa['projects'] as $project): ?>
                                        <div class="card mb-2">
                                            <div class="card-body p-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small><?php echo htmlspecialchars($project['name']); ?></small>
                                                    <span class="badge bg-<?php
                                                        $status_class = 'secondary'; // Default value
                                                        if ($project['status'] === 'wp_conversion') {
                                                            $status_class = 'info';
                                                        } elseif ($project['status'] === 'page_creation') {
                                                            $status_class = 'warning';
                                                        } elseif ($project['status'] === 'golive') {
                                                            $status_class = 'primary';
                                                        }
                                                        echo $status_class;
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>