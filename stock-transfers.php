<?php
/**
 * Stock Transfers Page
 * This page manages stock transfers between warehouses
 */

// Set page title
$pageTitle = 'Stock Transfers';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('stock-transfers');

// Require login to access this page
requireLogin();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $stock_id = (int)$_POST['stock_id'];
    $from_warehouse_id = (int)$_POST['from_warehouse_id'];
    $to_warehouse_id = (int)$_POST['to_warehouse_id'];
    $quantity = (float)$_POST['quantity'];
    $reason = sanitize($_POST['reason']);
    
    // Validate input
    $errors = [];
    
    if ($stock_id <= 0) {
        $errors[] = 'Please select a stock item';
    }
    
    if ($from_warehouse_id <= 0 || $to_warehouse_id <= 0) {
        $errors[] = 'Please select both source and destination warehouses';
    }
    
    if ($from_warehouse_id === $to_warehouse_id) {
        $errors[] = 'Source and destination warehouses cannot be the same';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Transfer quantity must be greater than zero';
    }
    
    // If no errors, process the transfer
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Check if source stock has sufficient quantity
            $stmt = $db->prepare("SELECT quantity FROM stocks WHERE stock_id = :stock_id");
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->execute();
            $current_quantity = $stmt->fetchColumn();
            
            if ($current_quantity < $quantity) {
                throw new Exception('Insufficient stock quantity for transfer');
            }
            
            // Update source stock
            $stmt = $db->prepare("
                UPDATE stocks 
                SET quantity = quantity - :quantity,
                    last_updated = NOW()
                WHERE stock_id = :stock_id
            ");
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->execute();
            
            // Check if destination stock exists
            $stmt = $db->prepare("
                SELECT stock_id FROM stocks 
                WHERE warehouse_id = :warehouse_id 
                AND variety_id = (SELECT variety_id FROM stocks WHERE stock_id = :stock_id)
            ");
            $stmt->bindParam(':warehouse_id', $to_warehouse_id);
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->execute();
            $dest_stock_id = $stmt->fetchColumn();
            
            if ($dest_stock_id) {
                // Update existing destination stock
                $stmt = $db->prepare("
                    UPDATE stocks 
                    SET quantity = quantity + :quantity,
                        last_updated = NOW()
                    WHERE stock_id = :stock_id
                ");
                $stmt->bindParam(':quantity', $quantity);
                $stmt->bindParam(':stock_id', $dest_stock_id);
                $stmt->execute();
            } else {
                // Create new destination stock
                $stmt = $db->prepare("
                    INSERT INTO stocks (
                        warehouse_id, variety_id, quantity, unit_price,
                        batch_number, created_by
                    )
                    SELECT 
                        :warehouse_id, variety_id, :quantity, unit_price,
                        CONCAT(batch_number, '-TR'), :created_by
                    FROM stocks 
                    WHERE stock_id = :stock_id
                ");
                $stmt->bindParam(':warehouse_id', $to_warehouse_id);
                $stmt->bindParam(':quantity', $quantity);
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                $stmt->bindParam(':stock_id', $stock_id);
                $stmt->execute();
            }
            
            // Record the transfer
            $stmt = $db->prepare("
                INSERT INTO stock_transfers (
                    stock_id, from_warehouse_id, to_warehouse_id,
                    quantity, reason, created_by
                ) VALUES (
                    :stock_id, :from_warehouse_id, :to_warehouse_id,
                    :quantity, :reason, :created_by
                )
            ");
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->bindParam(':from_warehouse_id', $from_warehouse_id);
            $stmt->bindParam(':to_warehouse_id', $to_warehouse_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            setFlashMessage('success', 'Stock transfer completed successfully.');
            redirect('stock-transfers.php');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            error_log("Stock transfer error: " . $e->getMessage());
            setFlashMessage('error', 'Error processing stock transfer: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get data for dropdowns
try {
    // Get active warehouses
    $stmt = $db->prepare("SELECT warehouse_id, name FROM warehouses WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available stock items
    $stmt = $db->prepare("
        SELECT s.stock_id, s.quantity, s.batch_number,
               w.name as warehouse_name, rv.name as variety_name
        FROM stocks s
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        WHERE s.quantity > 0
        ORDER BY w.name, rv.name
    ");
    $stmt->execute();
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent transfers
    $stmt = $db->prepare("
        SELECT st.*, 
               s.batch_number,
               rv.name as variety_name,
               w1.name as from_warehouse,
               w2.name as to_warehouse,
               u.username as created_by_name
        FROM stock_transfers st
        JOIN stocks s ON st.stock_id = s.stock_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        JOIN warehouses w1 ON st.from_warehouse_id = w1.warehouse_id
        JOIN warehouses w2 ON st.to_warehouse_id = w2.warehouse_id
        JOIN users u ON st.created_by = u.user_id
        ORDER BY st.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading data. Please try again later.');
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
                <h1 class="h3">Stock Transfers</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transferModal">
                        <i class="fas fa-exchange-alt me-1"></i> New Transfer
                    </button>
                </div>
            </div>
            
            <!-- Recent Transfers Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Batch</th>
                                    <th>Variety</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Quantity</th>
                                    <th>Reason</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transfers)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No transfers found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transfers as $transfer): ?>
                                        <tr>
                                            <td><?php echo formatDate($transfer['created_at'], 'M d, Y H:i'); ?></td>
                                            <td><?php echo $transfer['batch_number']; ?></td>
                                            <td><?php echo $transfer['variety_name']; ?></td>
                                            <td><?php echo $transfer['from_warehouse']; ?></td>
                                            <td><?php echo $transfer['to_warehouse']; ?></td>
                                            <td><?php echo number_format($transfer['quantity'], 2); ?> kg</td>
                                            <td><?php echo $transfer['reason']; ?></td>
                                            <td><?php echo $transfer['created_by_name']; ?></td>
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

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="transferModalLabel">New Stock Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="stock_id" class="form-label">Stock Item</label>
                            <select class="form-select" id="stock_id" name="stock_id" required>
                                <option value="">Select Stock Item</option>
                                <?php foreach ($stock_items as $item): ?>
                                    <option value="<?php echo $item['stock_id']; ?>" 
                                            data-warehouse="<?php echo $item['warehouse_name']; ?>"
                                            data-quantity="<?php echo $item['quantity']; ?>">
                                        <?php echo $item['variety_name']; ?> - 
                                        <?php echo $item['batch_number']; ?> 
                                        (<?php echo $item['warehouse_name']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a stock item.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Transfer Quantity (kg)</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0" required>
                            <small class="text-muted">Available: <span id="available-quantity">0</span> kg</small>
                            <div class="invalid-feedback">Please enter a valid quantity.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="from_warehouse_id" class="form-label">From Warehouse</label>
                            <input type="text" class="form-control" id="from_warehouse" readonly>
                            <input type="hidden" id="from_warehouse_id" name="from_warehouse_id">
                        </div>
                        <div class="col-md-6">
                            <label for="to_warehouse_id" class="form-label">To Warehouse</label>
                            <select class="form-select" id="to_warehouse_id" name="to_warehouse_id" required>
                                <option value="">Select Destination Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                        <?php echo $warehouse['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a destination warehouse.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Transfer</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        <div class="invalid-feedback">Please provide a reason for the transfer.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stockSelect = document.getElementById('stock_id');
    const fromWarehouse = document.getElementById('from_warehouse');
    const fromWarehouseId = document.getElementById('from_warehouse_id');
    const availableQuantity = document.getElementById('available-quantity');
    const quantityInput = document.getElementById('quantity');
    
    stockSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            fromWarehouse.value = selectedOption.dataset.warehouse;
            fromWarehouseId.value = selectedOption.value;
            availableQuantity.textContent = selectedOption.dataset.quantity;
            quantityInput.max = selectedOption.dataset.quantity;
        } else {
            fromWarehouse.value = '';
            fromWarehouseId.value = '';
            availableQuantity.textContent = '0';
            quantityInput.max = '';
        }
    });
});
</script>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 