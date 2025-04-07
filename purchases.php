<?php
/**
 * Purchases Page
 * This page manages stock purchase transactions
 */

// Set page title
$pageTitle = 'Purchases';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('purchases');

// Require login to access this page
requireLogin();

// Only admin and manager can add purchases
$canAddPurchases = hasRole(['admin', 'manager']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canAddPurchases) {
    // Get and sanitize form data
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $warehouse_id = (int)$_POST['warehouse_id'];
    $variety_id = (int)$_POST['variety_id'];
    $quantity = (float)$_POST['quantity'];
    $unit_price = (float)$_POST['unit_price'];
    $batch_number = sanitize($_POST['batch_number']);
    $invoice_number = sanitize($_POST['invoice_number']);
    $purchase_date = $_POST['purchase_date'];
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $notes = sanitize($_POST['notes']);
    
    // Validate input
    $errors = [];
    
    if ($warehouse_id <= 0) {
        $errors[] = 'Please select a warehouse';
    }
    
    if ($variety_id <= 0) {
        $errors[] = 'Please select a rice variety';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than zero';
    }
    
    if ($unit_price <= 0) {
        $errors[] = 'Unit price must be greater than zero';
    }
    
    if (empty($batch_number)) {
        $errors[] = 'Batch number is required';
    }
    
    if (empty($purchase_date)) {
        $errors[] = 'Purchase date is required';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Insert into stocks table
            $stmt = $db->prepare("
                INSERT INTO stocks (
                    warehouse_id, variety_id, quantity, unit_price,
                    batch_number, expiry_date, notes, created_by
                ) VALUES (
                    :warehouse_id, :variety_id, :quantity, :unit_price,
                    :batch_number, :expiry_date, :notes, :created_by
                )
            ");
            
            $stmt->bindParam(':warehouse_id', $warehouse_id);
            $stmt->bindParam(':variety_id', $variety_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':unit_price', $unit_price);
            $stmt->bindParam(':batch_number', $batch_number);
            $stmt->bindParam(':expiry_date', $expiry_date);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Get the new stock ID
            $stock_id = $db->lastInsertId();
            
            // Insert into purchases table
            $stmt = $db->prepare("
                INSERT INTO purchases (
                    stock_id, supplier_id, invoice_number, purchase_date,
                    total_amount, created_by
                ) VALUES (
                    :stock_id, :supplier_id, :invoice_number, :purchase_date,
                    :total_amount, :created_by
                )
            ");
            
            $total_amount = $quantity * $unit_price;
            
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->bindParam(':supplier_id', $supplier_id);
            $stmt->bindParam(':invoice_number', $invoice_number);
            $stmt->bindParam(':purchase_date', $purchase_date);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Get the purchase ID
            $purchase_id = $db->lastInsertId();
            
            // Record the transaction
            $stmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_type, reference_id, stock_id, 
                    quantity, notes, created_by
                ) VALUES (
                    'purchase', :reference_id, :stock_id,
                    :quantity, :notes, :created_by
                )
            ");
            
            $stmt->bindParam(':reference_id', $purchase_id);
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            setFlashMessage('success', 'Purchase successfully recorded.');
            redirect('purchases.php');
            
        } catch (PDOException $e) {
            // Rollback on error
            $db->rollBack();
            error_log("Purchase insert error: " . $e->getMessage());
            setFlashMessage('error', 'Database error occurred. Please try again later.');
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get purchase data
try {
    // Get suppliers for dropdown
    $stmt = $db->prepare("SELECT supplier_id, name FROM suppliers WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get warehouses for dropdown
    $stmt = $db->prepare("SELECT warehouse_id, name FROM warehouses WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get rice varieties for dropdown
    $stmt = $db->prepare("SELECT variety_id, name, type FROM rice_varieties ORDER BY name");
    $stmt->execute();
    $varieties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent purchases
    $stmt = $db->prepare("
        SELECT 
            p.purchase_id, p.invoice_number, p.purchase_date, p.total_amount,
            s.batch_number, s.quantity, s.unit_price,
            w.name as warehouse_name,
            rv.name as variety_name,
            sp.name as supplier_name,
            u.username as created_by
        FROM purchases p
        JOIN stocks s ON p.stock_id = s.stock_id
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        LEFT JOIN suppliers sp ON p.supplier_id = sp.supplier_id
        JOIN users u ON p.created_by = u.user_id
        ORDER BY p.purchase_date DESC
        LIMIT 50
    ");
    $stmt->execute();
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h3">Purchases</h1>
                <div>
                    <?php if ($canAddPurchases): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#purchaseModal">
                            <i class="fas fa-plus-circle me-1"></i> New Purchase
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Purchase Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice</th>
                                    <th>Supplier</th>
                                    <th>Warehouse</th>
                                    <th>Variety</th>
                                    <th>Batch</th>
                                    <th>Quantity (kg)</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($purchases)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center">No purchases found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($purchases as $purchase): ?>
                                        <tr>
                                            <td><?php echo formatDate($purchase['purchase_date'], 'M d, Y'); ?></td>
                                            <td><?php echo $purchase['invoice_number']; ?></td>
                                            <td><?php echo $purchase['supplier_name'] ?: '-'; ?></td>
                                            <td><?php echo $purchase['warehouse_name']; ?></td>
                                            <td><?php echo $purchase['variety_name']; ?></td>
                                            <td><?php echo $purchase['batch_number']; ?></td>
                                            <td><?php echo number_format($purchase['quantity'], 2); ?></td>
                                            <td><?php echo formatCurrency($purchase['unit_price']); ?></td>
                                            <td><?php echo formatCurrency($purchase['total_amount']); ?></td>
                                            <td><?php echo $purchase['created_by']; ?></td>
                                            <td>
                                                <a href="<?php echo URL_ROOT; ?>/view-purchase.php?id=<?php echo $purchase['purchase_id']; ?>" 
                                                   class="btn btn-sm btn-info"
                                                   data-bs-toggle="tooltip"
                                                   title="View Details">
                                                    <i class="fas fa-eye"></i>
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

<?php if ($canAddPurchases): ?>
<!-- Add Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="purchaseModalLabel">New Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select class="form-select" id="supplier_id" name="supplier_id">
                                <option value="">Select Supplier (Optional)</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>">
                                        <?php echo $supplier['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="invoice_number" class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" required>
                            <div class="invalid-feedback">Please enter an invoice number.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="warehouse_id" class="form-label">Warehouse</label>
                            <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                                <option value="">Select Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                        <?php echo $warehouse['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a warehouse.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="variety_id" class="form-label">Rice Variety</label>
                            <select class="form-select" id="variety_id" name="variety_id" required>
                                <option value="">Select Rice Variety</option>
                                <?php foreach ($varieties as $variety): ?>
                                    <option value="<?php echo $variety['variety_id']; ?>">
                                        <?php echo $variety['name']; ?> (<?php echo $variety['type']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a rice variety.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Quantity (kg)</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0" required>
                            <div class="invalid-feedback">Please enter a valid quantity.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="unit_price" class="form-label">Unit Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="unit_price" name="unit_price" step="0.01" min="0" required>
                                <div class="invalid-feedback">Please enter a valid unit price.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="batch_number" class="form-label">Batch Number</label>
                            <input type="text" class="form-control" id="batch_number" name="batch_number" required>
                            <div class="invalid-feedback">Please enter a batch number.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="purchase_date" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Please select a purchase date.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                        </div>
                        <div class="col-md-6">
                            <label for="total_amount" class="form-label">Total Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control" id="total_amount" readonly>
                            </div>
                            <small class="text-muted">Calculated automatically</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calculate total amount
    const quantityInput = document.getElementById('quantity');
    const unitPriceInput = document.getElementById('unit_price');
    const totalAmountInput = document.getElementById('total_amount');
    
    function calculateTotal() {
        const quantity = parseFloat(quantityInput.value) || 0;
        const unitPrice = parseFloat(unitPriceInput.value) || 0;
        const total = (quantity * unitPrice).toFixed(2);
        totalAmountInput.value = total;
    }
    
    quantityInput.addEventListener('input', calculateTotal);
    unitPriceInput.addEventListener('input', calculateTotal);
});
</script>
<?php endif; ?>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 