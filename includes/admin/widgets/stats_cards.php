<?php
// filepath: c:\wamp64\www\qa_reporter\includes\admin\widgets\stats_cards.php

// Get projects count for summary stats
$wp_qa_query = "SELECT COUNT(*) as count FROM projects WHERE current_status LIKE '%wp_conversion_qa%'";
$wp_qa_count = $conn->query($wp_qa_query)->fetch_assoc()['count'];

$page_qa_query = "SELECT COUNT(*) as count FROM projects WHERE current_status LIKE '%page_creation_qa%'";
$page_qa_count = $conn->query($page_qa_query)->fetch_assoc()['count'];

$golive_qa_query = "SELECT COUNT(*) as count FROM projects WHERE current_status LIKE '%golive_qa%'";
$golive_qa_count = $conn->query($golive_qa_query)->fetch_assoc()['count'];

$unassigned_query = "SELECT COUNT(*) as count FROM projects p
                     LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                     WHERE qa.id IS NULL
                     AND (p.current_status LIKE '%wp_conversion_qa%'
                          OR p.current_status LIKE '%page_creation_qa%'
                          OR p.current_status LIKE '%golive_qa%')";
$unassigned_count = $conn->query($unassigned_query)->fetch_assoc()['count'];
?>

<div class="row">
    <div class="col-md-3">
        <div class="card border-0 bg-info text-white shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">WP QA Pending</h6>
                        <h2 class="mb-0"><?php echo $wp_qa_count; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-wordpress fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 bg-warning text-dark shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">Page QA Pending</h6>
                        <h2 class="mb-0"><?php echo $page_qa_count; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-plus fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 bg-primary text-white shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">Golive QA Pending</h6>
                        <h2 class="mb-0"><?php echo $golive_qa_count; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-rocket fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 bg-danger text-white shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">Unassigned</h6>
                        <h2 class="mb-0"><?php echo $unassigned_count; ?></h2>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>