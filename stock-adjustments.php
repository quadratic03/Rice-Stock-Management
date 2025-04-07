<?php
/**
 * Stock Adjustments Page
 * This page manages stock quantity adjustments
 */

// Set page title
$pageTitle = 'Stock Adjustments';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('stock-adjustments');

// Require login to access this page
requireLogin();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $stock_id = (int)$_POST['stock_id'];
    $adjustment_type = sanitize($_POST['adjustment_type']);
    $quantity = (float)$_POST['quantity'];
    $reason = sanitize($_POST['reason']);
    
    // Validate input
    $errors = [];
    
    if ($stock_id <= 0) {
        $errors[] = 'Please select a stock item';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Adjustment quantity must be greater than zero';
    }
    
    if (empty($reason)) {
        $errors[] = 'Please provide a reason for the adjustment';
    }
    
    // If no errors, process the adjustment
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Get current stock quantity
            $stmt = $db->prepare("SELECT quantity FROM stocks WHERE stock_id = :stock_id");
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->execute();
            $current_quantity = $stmt->fetchColumn();
            
            // Calculate new quantity
            $new_quantity = $adjustment_type === 'add' 
                ? $current_quantity + $quantity 
                : $current_quantity - $quantity;
            
            if ($new_quantity < 0) {
                throw new Exception('Adjustment would result in negative stock quantity');
            }
            
            // Update stock quantity
            $stmt = $db->prepare("
                UPDATE stocks 
                SET quantity = :quantity,
                    last_updated = NOW()
                WHERE stock_id = :stock_id
            ");
            $stmt->bindParam(':quantity', $new_quantity);
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->execute();
            
            // Record the adjustment
            $stmt = $db->prepare("
                INSERT INTO stock_adjustments (
                    stock_id, adjustment_type, quantity,
                    previous_quantity, new_quantity, reason, created_by
                ) VALUES (
                    :stock_id, :adjustment_type, :quantity,
                    :previous_quantity, :new_quantity, :reason, :created_by
                )
            ");
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->bindParam(':adjustment_type', $adjustment_type);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':previous_quantity', $current_quantity);
            $stmt->bindParam(':new_quantity', $new_quantity);
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            setFlashMessage('success', 'Stock adjustment completed successfully.');
            redirect('stock-adjustments.php');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            error_log("Stock adjustment error: " . $e->getMessage());
            setFlashMessage('error', 'Error processing stock adjustment: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get data for dropdowns
try {
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
    
    // Get recent adjustments
    $stmt = $db->prepare("
        SELECT sa.*, 
               s.batch_number,
               rv.name as variety_name,
               w.name as warehouse_name,
               u.username as created_by_name
        FROM stock_adjustments sa
        JOIN stocks s ON sa.stock_id = s.stock_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        JOIN users u ON sa.created_by = u.user_id
        ORDER BY sa.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h3">Stock Adjustments</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustmentModal">
                        <i class="fas fa-sliders-h me-1"></i> New Adjustment
                    </button>
                </div>
            </div>
            
            <!-- Recent Adjustments Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Batch</th>
                                    <th>Variety</th>
                                    <th>Warehouse</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Previous</th>
                                    <th>New</th>
                                    <th>Reason</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($adjustments)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">No adjustments found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($adjustments as $adjustment): ?>
                                        <tr>
                                            <td><?php echo formatDate($adjustment['created_at'], 'M d, Y H:i'); ?></td>
                                            <td><?php echo $adjustment['batch_number']; ?></td>
                                            <td><?php echo $adjustment['variety_name']; ?></td>
                                            <td><?php echo $adjustment['warehouse_name']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $adjustment['adjustment_type'] === 'add' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ucfirst($adjustment['adjustment_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($adjustment['quantity'], 2); ?> kg</td>
                                            <td><?php echo number_format($adjustment['previous_quantity'], 2); ?> kg</td>
                                            <td><?php echo number_format($adjustment['new_quantity'], 2); ?> kg</td>
                                            <td><?php echo $adjustment['reason']; ?></td>
                                            <td><?php echo $adjustment['created_by_name']; ?></td>
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

<!-- Adjustment Modal -->
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-labelledby="adjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="adjustmentModalLabel">New Stock Adjustment</h5>
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
                            <label for="adjustment_type" class="form-label">Adjustment Type</label>
                            <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                                <option value="add">Add Stock</option>
                                <option value="remove">Remove Stock</option>
                            </select>
                            <div class="invalid-feedback">Please select an adjustment type.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Adjustment Quantity (kg)</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0" required>
                            <small class="text-muted">Current: <span id="current-quantity">0</span> kg</small>
                            <div class="invalid-feedback">Please enter a valid quantity.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Adjustment</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        <div class="invalid-feedback">Please provide a reason for the adjustment.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stockSelect = document.getElementById('stock_id');
    const currentQuantity = document.getElementById('current-quantity');
    const adjustmentType = document.getElementById('adjustment_type');
    const quantityInput = document.getElementById('quantity');
    
    stockSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            currentQuantity.textContent = selectedOption.dataset.quantity;
        } else {
            currentQuantity.textContent = '0';
        }
    });
    
    adjustmentType.addEventListener('change', function() {
        if (this.value === 'remove') {
            const maxQuantity = parseFloat(currentQuantity.textContent);
            quantityInput.max = maxQuantity;
        } else {
            quantityInput.removeAttribute('max');
        }
    });
});
</script>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 