<?php
/**
 * Reports Page
 * This page provides various reports on inventory and sales
 */

// Set page title
$pageTitle = 'Reports';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('reports');

// Require login to access this page
requireLogin();

// Get report type and date range
$reportType = isset($_GET['type']) ? sanitize($_GET['type']) : 'inventory';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Define available report types
$reportTypes = [
    'inventory' => 'Current Inventory Status',
    'stock_movement' => 'Stock Movement',
    'sales' => 'Sales Report',
    'purchases' => 'Purchase Report',
    'profit_loss' => 'Profit/Loss Analysis'
];

// Get report data based on type
$reportData = [];
$chartData = [];

try {
    // Simplified query that will work even if limited data is available
    $query = "SELECT w.name as warehouse_name, 
              COUNT(s.stock_id) as stock_count, 
              SUM(s.quantity) as total_quantity,
              SUM(s.quantity * s.unit_price) as total_value
              FROM warehouses w
              LEFT JOIN stocks s ON w.warehouse_id = s.warehouse_id
              GROUP BY w.warehouse_id
              ORDER BY w.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare chart data
    $chartLabels = [];
    $chartValues = [];
    $chartColors = [];
    
    foreach ($reportData as $index => $row) {
        $chartLabels[] = $row['warehouse_name'];
        $chartValues[] = $row['total_quantity'] ? $row['total_quantity'] : 0;
        
        // Generate random colors for chart
        $r = rand(100, 200);
        $g = rand(100, 200);
        $b = rand(100, 200);
        $chartColors[] = "rgba($r, $g, $b, 0.7)";
    }
    
    $chartData = [
        'labels' => $chartLabels,
        'values' => $chartValues,
        'colors' => $chartColors
    ];
    
} catch (PDOException $e) {
    error_log("Report data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report. Please try again later.');
}

// Include header
include_once __DIR__ . '/layouts/header.php';
?>
<div class="content-wrapper">
    <!-- Sidebar -->
    <?php include_once __DIR__ . '/layouts/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Reports</h1>
                <div>
                    <button type="button" class="btn btn-primary" id="printReport">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                    <button type="button" class="btn btn-success ms-2" id="exportCsv">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </button>
                </div>
            </div>
            
            <!-- Report Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                        <div class="col-md-4">
                            <label for="type" class="form-label">Report Type</label>
                            <select class="form-select" id="type" name="type">
                                <?php foreach ($reportTypes as $type => $label): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $reportType === $type ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Report Content -->
            <div class="card report-card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?php echo $reportTypes[$reportType]; ?></h5>
                    <small class="text-muted">
                        <?php if ($reportType !== 'inventory'): ?>
                            Period: <?php echo formatDate($startDate, 'M d, Y'); ?> - <?php echo formatDate($endDate, 'M d, Y'); ?>
                        <?php else: ?>
                            As of <?php echo formatDate(date('Y-m-d'), 'M d, Y'); ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($reportData)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No data available for the selected report parameters.
                        </div>
                    <?php else: ?>
                        <!-- Chart Display -->
                        <div class="chart-container mb-4" style="position: relative; height:40vh;">
                            <canvas id="reportChart"></canvas>
                        </div>
                        
                        <!-- Table Display -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Warehouse</th>
                                        <th>Stock Items</th>
                                        <th>Total Quantity (kg)</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalItems = 0;
                                    $totalQuantity = 0;
                                    $totalValue = 0;
                                    
                                    foreach ($reportData as $row): 
                                        $totalItems += $row['stock_count'];
                                        $totalQuantity += $row['total_quantity'];
                                        $totalValue += $row['total_value'];
                                    ?>
                                        <tr>
                                            <td><?php echo $row['warehouse_name']; ?></td>
                                            <td><?php echo number_format($row['stock_count']); ?></td>
                                            <td><?php echo number_format($row['total_quantity'] ?? 0, 2); ?></td>
                                            <td><?php echo formatCurrency($row['total_value'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-secondary fw-bold">
                                        <td>Total</td>
                                        <td><?php echo number_format($totalItems); ?></td>
                                        <td><?php echo number_format($totalQuantity, 2); ?></td>
                                        <td><?php echo formatCurrency($totalValue); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Report Chart
    var ctx = document.getElementById('reportChart');
    
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartData['labels'] ?? []); ?>,
                datasets: [{
                    label: 'Total Quantity (kg)',
                    data: <?php echo json_encode($chartData['values'] ?? []); ?>,
                    backgroundColor: <?php echo json_encode($chartData['colors'] ?? []); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity (kg)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Warehouse'
                        }
                    }
                }
            }
        });
    }
    
    // Print Report Button
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
    
    // Export CSV Button
    document.getElementById('exportCsv').addEventListener('click', function() {
        window.location.href = 'export-report.php?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>';
    });
});
</script>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 