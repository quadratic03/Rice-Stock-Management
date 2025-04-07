<?php
/**
 * Analytics Page
 * This page provides interactive dashboards and visualizations
 */

// Set page title
$pageTitle = 'Analytics';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('analytics');

// Require login to access this page
requireLogin();

// Get period (default: last 30 days)
$period = isset($_GET['period']) ? sanitize($_GET['period']) : '30days';

// Calculate date ranges based on period
$endDate = date('Y-m-d');
switch ($period) {
    case '7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $periodLabel = 'Last 7 Days';
        break;
    case '30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $periodLabel = 'Last 30 Days';
        break;
    case '90days':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        $periodLabel = 'Last 90 Days';
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        $periodLabel = 'Last 12 Months';
        break;
    case 'ytd':
        $startDate = date('Y-01-01');
        $periodLabel = 'Year to Date';
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $periodLabel = 'Last 30 Days';
        $period = '30days';
}

// Get basic analytics data
try {
    // Get warehouse data for utilization chart
    $stmt = $db->prepare("
        SELECT 
            w.name as warehouse_name,
            w.capacity as capacity,
            COALESCE(SUM(s.quantity), 0) as total_stock,
            (COALESCE(SUM(s.quantity), 0) / w.capacity) * 100 as utilization_percentage
        FROM warehouses w
        LEFT JOIN stocks s ON w.warehouse_id = s.warehouse_id
        GROUP BY w.warehouse_id
        ORDER BY w.name ASC
    ");
    $stmt->execute();
    $warehouseData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $totalWarehouses = count($warehouseData);
    $totalStock = 0;
    $totalCapacity = 0;
    
    foreach ($warehouseData as $warehouse) {
        $totalStock += $warehouse['total_stock'];
        $totalCapacity += $warehouse['capacity'];
    }
    
    $overallUtilization = $totalCapacity > 0 ? ($totalStock / $totalCapacity) * 100 : 0;
    
    // Get variety distribution
    $stmt = $db->prepare("
        SELECT 
            rv.name as variety_name,
            SUM(s.quantity) as total_quantity
        FROM stocks s
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        GROUP BY s.variety_id
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $stmt->execute();
    $varietyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare chart data
    $warehouseLabels = [];
    $utilizationValues = [];
    $utilizationColors = [];
    
    foreach ($warehouseData as $index => $warehouse) {
        $warehouseLabels[] = $warehouse['warehouse_name'];
        $utilizationValues[] = round($warehouse['utilization_percentage'], 2);
        
        // Color based on utilization
        if ($warehouse['utilization_percentage'] > 80) {
            $utilizationColors[] = 'rgba(220, 53, 69, 0.7)'; // High utilization - red
        } elseif ($warehouse['utilization_percentage'] > 50) {
            $utilizationColors[] = 'rgba(255, 193, 7, 0.7)'; // Medium utilization - yellow
        } else {
            $utilizationColors[] = 'rgba(40, 167, 69, 0.7)'; // Low utilization - green
        }
    }
    
    // Variety distribution chart
    $varietyLabels = [];
    $varietyValues = [];
    $varietyColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
    
    foreach ($varietyData as $index => $variety) {
        $varietyLabels[] = $variety['variety_name'];
        $varietyValues[] = $variety['total_quantity'];
    }
    
} catch (PDOException $e) {
    error_log("Analytics data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error generating analytics. Please try again later.');
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
                <h1 class="h3">Analytics Dashboard</h1>
                <div class="btn-group">
                    <a href="?period=7days" class="btn btn-outline-primary <?php echo $period === '7days' ? 'active' : ''; ?>">7 Days</a>
                    <a href="?period=30days" class="btn btn-outline-primary <?php echo $period === '30days' ? 'active' : ''; ?>">30 Days</a>
                    <a href="?period=90days" class="btn btn-outline-primary <?php echo $period === '90days' ? 'active' : ''; ?>">90 Days</a>
                    <a href="?period=ytd" class="btn btn-outline-primary <?php echo $period === 'ytd' ? 'active' : ''; ?>">Year to Date</a>
                    <a href="?period=year" class="btn btn-outline-primary <?php echo $period === 'year' ? 'active' : ''; ?>">12 Months</a>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-primary h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Stock</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalStock, 2); ?> kg</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-boxes fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-success h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Storage Capacity</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalCapacity, 2); ?> kg</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-warehouse fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-info h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Overall Utilization</div>
                                    <div class="row no-gutters align-items-center">
                                        <div class="col-auto">
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo number_format($overallUtilization, 1); ?>%</div>
                                        </div>
                                        <div class="col">
                                            <div class="progress progress-sm mr-2">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(100, $overallUtilization); ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-warning h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Warehouses</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalWarehouses; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-building fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="row">
                <!-- Warehouse Utilization Chart -->
                <div class="col-xl-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Warehouse Utilization</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="warehouseUtilizationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Rice Variety Distribution -->
                <div class="col-xl-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Rice Variety Distribution</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="varietyDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Warehouse Data Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Warehouse Capacity Details</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Warehouse</th>
                                    <th>Total Stock (kg)</th>
                                    <th>Capacity (kg)</th>
                                    <th>Available Space (kg)</th>
                                    <th>Utilization</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($warehouseData as $warehouse): 
                                    $availableSpace = $warehouse['capacity'] - $warehouse['total_stock'];
                                    $utilizationClass = '';
                                    if ($warehouse['utilization_percentage'] > 80) {
                                        $utilizationClass = 'text-danger';
                                    } elseif ($warehouse['utilization_percentage'] > 50) {
                                        $utilizationClass = 'text-warning';
                                    } else {
                                        $utilizationClass = 'text-success';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $warehouse['warehouse_name']; ?></td>
                                        <td><?php echo number_format($warehouse['total_stock'] ?? 0, 2); ?></td>
                                        <td><?php echo number_format($warehouse['capacity'] ?? 0, 2); ?></td>
                                        <td><?php echo number_format($availableSpace ?? 0, 2); ?></td>
                                        <td class="<?php echo $utilizationClass; ?>">
                                            <?php echo number_format($warehouse['utilization_percentage'] ?? 0, 1); ?>%
                                            <div class="progress mt-1" style="height: 5px;">
                                                <div class="progress-bar bg-<?php echo ($warehouse['utilization_percentage'] ?? 0) > 80 ? 'danger' : (($warehouse['utilization_percentage'] ?? 0) > 50 ? 'warning' : 'success'); ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo min(100, $warehouse['utilization_percentage'] ?? 0); ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Warehouse Utilization Chart
    var ctxWarehouse = document.getElementById('warehouseUtilizationChart');
    if (ctxWarehouse) {
        new Chart(ctxWarehouse, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($warehouseLabels); ?>,
                datasets: [{
                    label: 'Utilization (%)',
                    data: <?php echo json_encode($utilizationValues); ?>,
                    backgroundColor: <?php echo json_encode($utilizationColors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Utilization (%)'
                        }
                    }
                }
            }
        });
    }
    
    // Rice Variety Distribution Chart
    var ctxVariety = document.getElementById('varietyDistributionChart');
    if (ctxVariety) {
        new Chart(ctxVariety, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($varietyLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($varietyValues); ?>,
                    backgroundColor: <?php echo json_encode($varietyColors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    }
});
</script>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 