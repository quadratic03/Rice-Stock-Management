<?php
/**
 * Inventory Page
 * This page displays the current stock levels and inventory status
 */

// Set page title
$pageTitle = 'Stock Overview';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('inventory');

// Require login to access this page
requireLogin();

// Get inventory data
try {
    // Get all stock items with their current quantities
    $stmt = $db->prepare("
        SELECT 
            s.stock_id,
            s.warehouse_id,
            s.variety_id,
            s.quantity,
            s.unit_price,
            s.last_updated,
            w.name as warehouse_name,
            rv.name as variety_name,
            rv.type as variety_type
        FROM stocks s
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        ORDER BY w.name ASC, rv.name ASC
    ");
    $stmt->execute();
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Inventory data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading inventory data. Please try again later.');
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
                <h1 class="h3">Stock Overview</h1>
                <div>
                    <a href="<?php echo URL_ROOT; ?>/add-stock.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Add New Stock
                    </a>
                </div>
            </div>
            
            <!-- Stock Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Warehouse</th>
                                    <th>Rice Variety</th>
                                    <th>Type</th>
                                    <th>Quantity (kg)</th>
                                    <th>Unit Price</th>
                                    <th>Total Value</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($stocks)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No stock items found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($stocks as $stock): ?>
                                        <tr>
                                            <td><?php echo $stock['warehouse_name']; ?></td>
                                            <td><?php echo $stock['variety_name']; ?></td>
                                            <td><?php echo $stock['variety_type']; ?></td>
                                            <td><?php echo number_format($stock['quantity'], 2); ?></td>
                                            <td><?php echo formatCurrency($stock['unit_price']); ?></td>
                                            <td><?php echo formatCurrency($stock['quantity'] * $stock['unit_price']); ?></td>
                                            <td><?php echo formatDate($stock['last_updated'], 'M d, Y H:i'); ?></td>
                                            <td class="table-actions">
                                                <a href="<?php echo URL_ROOT; ?>/view-stock.php?id=<?php echo $stock['stock_id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo URL_ROOT; ?>/edit-stock.php?id=<?php echo $stock['stock_id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit Stock">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?php echo URL_ROOT; ?>/transfer-stock.php?id=<?php echo $stock['stock_id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Transfer Stock">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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