<?php
/**
 * Export Report to CSV
 * This script exports report data to CSV format
 */

// Include initialization file
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/report-functions.php';

// Require login to access this file
requireLogin();

// Get report parameters
$reportType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Validate report type
$validReportTypes = ['inventory', 'stock_movement', 'sales', 'purchases', 'profit_loss'];
if (!in_array($reportType, $validReportTypes)) {
    die('Invalid report type');
}

// Default to current date range if not provided
if (empty($startDate)) {
    $startDate = date('Y-m-01'); // First day of current month
}

if (empty($endDate)) {
    $endDate = date('Y-m-d'); // Today
}

// Generate CSV content
$csvContent = exportReportToCsv($reportType, $startDate, $endDate);

if (empty($csvContent)) {
    die('No data to export');
}

// Set filename based on report type and date range
$filename = $reportType . '_report_' . $startDate . '_to_' . $endDate . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output CSV content
echo $csvContent;
exit; 