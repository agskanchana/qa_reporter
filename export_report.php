<?php
// export_report.php
require_once 'config.php';

// Check permissions
checkPermission(['admin', 'qa_manager']);

// Get parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$webmaster_id = isset($_GET['webmaster_id']) ? (int)$_GET['webmaster_id'] : 0;

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="qa_report_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['QA Report', '', '']);
fputcsv($output, ['Date Range:', $start_date, 'to', $end_date]);
fputcsv($output, ['']);

// Add project statistics
$stats = getProjectStats($conn, $start_date, $end_date, $webmaster_id);
fputcsv($output, ['Project Statistics', '', '']);
fputcsv($output, ['Total Projects', $stats['total_projects'], '']);
fputcsv($output, ['Completed Projects', $stats['completed_projects'], '']);
fputcsv($output, ['Average Completion Days', round($stats['avg_completion_days'], 1), '']);
fputcsv($output, ['']);

// Add failed items
$failed_items = getFailedItemsStats($conn, $start_date, $end_date, $webmaster_id);
fputcsv($output, ['Failed Items', '', '']);
fputcsv($output, ['Item', 'Stage', 'Fail Count']);

while ($item = $failed_items->fetch_assoc()) {
    fputcsv($output, [
        $item['title'],
        ucfirst(str_replace('_', ' ', $item['stage'])),
        $item['fail_count']
    ]);
}

fclose($output);
exit();
?>