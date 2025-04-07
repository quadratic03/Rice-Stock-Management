<?php
/**
 * Dashboard Page
 * This is the main landing page after login, showing system overview and key metrics
 */

// Set page title 
$pageTitle = 'Dashboard';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('dashboard');

// Require login to access this page
requireLogin();

// Get dashboard data
try {
    // Get total rice stock quantity
    $stmt = $db->prepare("SELECT SUM(quantity) as total_quantity FROM stocks WHERE status = 'available'");
    $stmt->execute();
    $totalStock = $stmt->fetch(PDO::FETCH_ASSOC)['total_quantity'] ?? 0;
    
    // Get warehouse count
    $stmt = $db->prepare("SELECT COUNT(*) as warehouse_count FROM warehouses WHERE status = 'active'");
    $stmt->execute();
    $warehouseCount = $stmt->fetch(PDO::FETCH_ASSOC)['warehouse_count'] ?? 0;
    
    // Get stock value
    $stmt = $db->prepare("SELECT SUM(quantity * unit_price) as total_value FROM stocks WHERE status = 'available'");
    $stmt->execute();
    $totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;
    
    // Get low stock alerts count
    $stmt = $db->prepare("
        SELECT COUNT(*) as low_stock_count 
        FROM stocks 
        WHERE status = 'available' 
        AND quantity <= (minimum_stock_level * :threshold)
    ");
    $lowThreshold = STOCK_LEVEL_LOW / 100;
    $stmt->bindParam(':threshold', $lowThreshold);
    $stmt->execute();
    $lowStockCount = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock_count'] ?? 0;
    
    // Get recent transactions
    $stmt = $db->prepare("
        SELECT t.*, s.variety_id, rv.name as rice_variety, u.username 
        FROM stock_transactions t
        JOIN stocks s ON t.stock_id = s.stock_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        JOIN users u ON t.user_id = u.user_id
        ORDER BY t.transaction_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stock by warehouse for chart
    $stmt = $db->prepare("
        SELECT w.name, SUM(s.quantity) as total
        FROM stocks s
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        WHERE s.status = 'available'
        GROUP BY w.warehouse_id
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stockByWarehouse = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stock by rice variety for chart
    $stmt = $db->prepare("
        SELECT rv.name, SUM(s.quantity) as total
        FROM stocks s
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        WHERE s.status = 'available'
        GROUP BY rv.variety_id
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stockByVariety = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock items
    $stmt = $db->prepare("
        SELECT s.*, rv.name as rice_variety, w.name as warehouse_name,
        (s.quantity / s.minimum_stock_level * 100) as stock_percentage
        FROM stocks s
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        WHERE s.status = 'available' 
        AND s.quantity <= (s.minimum_stock_level * :threshold)
        ORDER BY stock_percentage ASC
        LIMIT 5
    ");
    $stmt->bindParam(':threshold', $lowThreshold);
    $stmt->execute();
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading dashboard data. Please try again later.');
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
                <h1 class="h3">Dashboard</h1>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" id="refresh-dashboard">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-sm btn-outline-primary ms-2" id="print-dashboard">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Key Metrics -->
            <div class="row">
                <!-- Total Rice Stock -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card text-center h-100">
                        <div class="card-body">
                            <div class="card-icon text-primary">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="card-value"><?php echo number_format($totalStock, 2); ?></div>
                            <div class="card-title">Total Stock (kg)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Warehouse Count -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card text-center h-100">
                        <div class="card-body">
                            <div class="card-icon text-success">
                                <i class="fas fa-warehouse"></i>
                            </div>
                            <div class="card-value"><?php echo $warehouseCount; ?></div>
                            <div class="card-title">Active Warehouses</div>
                        </div>
                    </div>
                </div>
                
                <!-- Stock Value -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card text-center h-100">
                        <div class="card-body">
                            <div class="card-icon text-info">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="card-value"><?php echo formatCurrency($totalValue); ?></div>
                            <div class="card-title">Current Stock Value</div>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Alerts -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card dashboard-card text-center h-100">
                        <div class="card-body">
                            <div class="card-icon text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="card-value"><?php echo $lowStockCount; ?></div>
                            <div class="card-title">Low Stock Alerts</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts & Tables Row -->
            <div class="row">
                <!-- Stock Distribution by Warehouse -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-chart-pie me-2"></i> Stock Distribution by Warehouse
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="warehouseChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stock Distribution by Rice Variety -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-chart-bar me-2"></i> Stock by Rice Variety
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="varietyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions & Low Stock Tables -->
            <div class="row">
                <!-- Recent Transactions -->
                <div class="col-lg-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-exchange-alt me-2"></i> Recent Transactions
                            </div>
                            <a href="<?php echo URL_ROOT; ?>/transactions.php" class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Rice Variety</th>
                                            <th>Quantity</th>
                                            <th>Amount</th>
                                            <th>User</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentTransactions)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No transactions found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentTransactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo formatDate($transaction['transaction_date'], 'M d, Y H:i'); ?></td>
                                                    <td>
                                                        <?php 
                                                        $badgeClass = '';
                                                        switch ($transaction['transaction_type']) {
                                                            case 'purchase':
                                                                $badgeClass = 'bg-success';
                                                                break;
                                                            case 'sale':
                                                                $badgeClass = 'bg-info';
                                                                break;
                                                            case 'transfer':
                                                                $badgeClass = 'bg-primary';
                                                                break;
                                                            case 'adjustment':
                                                                $badgeClass = 'bg-warning';
                                                                break;
                                                            case 'loss':
                                                                $badgeClass = 'bg-danger';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <?php echo ucfirst($transaction['transaction_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $transaction['rice_variety']; ?></td>
                                                    <td><?php echo number_format($transaction['quantity'], 2); ?> kg</td>
                                                    <td><?php echo formatCurrency($transaction['total_amount']); ?></td>
                                                    <td><?php echo $transaction['username']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Alerts -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-exclamation-triangle me-2"></i> Low Stock Items
                            </div>
                            <a href="<?php echo URL_ROOT; ?>/inventory.php?filter=low" class="btn btn-sm btn-outline-danger">
                                View All
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (empty($lowStockItems)): ?>
                                    <div class="list-group-item text-center text-success">
                                        <i class="fas fa-check-circle me-2"></i> No low stock items
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($lowStockItems as $item): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo $item['rice_variety']; ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $item['warehouse_name']; ?> | 
                                                        Batch: <?php echo $item['batch_number']; ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-danger">
                                                    <?php echo number_format($item['stock_percentage'], 0); ?>%
                                                </span>
                                            </div>
                                            <div class="progress mt-2" style="height: 10px;">
                                                <div class="progress-bar progress-low" 
                                                    role="progressbar" 
                                                    style="width: <?php echo $item['stock_percentage']; ?>%;" 
                                                    aria-valuenow="<?php echo $item['stock_percentage']; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <small>
                                                    Current: <?php echo number_format($item['quantity'], 2); ?> kg
                                                </small>
                                                <small>
                                                    Min: <?php echo number_format($item['minimum_stock_level'], 2); ?> kg
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?>

<script>
// Charts Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Warehouse Chart
    const warehouseCtx = document.getElementById('warehouseChart').getContext('2d');
    const warehouseChart = new Chart(warehouseCtx, {
        type: 'pie',
        data: {
            labels: [
                <?php 
                foreach ($stockByWarehouse as $item) {
                    echo "'" . $item['name'] . "', ";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    foreach ($stockByWarehouse as $item) {
                        echo $item['total'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value.toLocaleString()} kg (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Variety Chart
    const varietyCtx = document.getElementById('varietyChart').getContext('2d');
    const varietyChart = new Chart(varietyCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                foreach ($stockByVariety as $item) {
                    echo "'" . $item['name'] . "', ";
                }
                ?>
            ],
            datasets: [{
                label: 'Stock Quantity (kg)',
                data: [
                    <?php 
                    foreach ($stockByVariety as $item) {
                        echo $item['total'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Refresh dashboard
    document.getElementById('refresh-dashboard').addEventListener('click', function() {
        window.location.reload();
    });
    
    // Print dashboard
    document.getElementById('print-dashboard').addEventListener('click', function() {
        window.print();
    });
});
</script> 