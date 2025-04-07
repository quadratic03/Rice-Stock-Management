<?php
/**
 * Sales Page
 * This page manages rice sales transactions
 */

// Set page title
$pageTitle = 'Sales';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('sales');

// Require login to access this page
requireLogin();

// Only admin and manager can add sales
$canAddSales = hasRole(['admin', 'manager']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canAddSales) {
    // Get and sanitize form data
    $stock_id = (int)$_POST['stock_id'];
    $customer_name = sanitize($_POST['customer_name']);
    $quantity = (float)$_POST['quantity'];
    $sale_price = (float)$_POST['sale_price'];
    $sale_date = $_POST['sale_date'];
    $invoice_number = sanitize($_POST['invoice_number']);
    $payment_method = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes']);
    
    // Validate input
    $errors = [];
    
    if ($stock_id <= 0) {
        $errors[] = 'Please select a stock item';
    }
    
    if (empty($customer_name)) {
        $errors[] = 'Customer name is required';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than zero';
    }
    
    if ($sale_price <= 0) {
        $errors[] = 'Sale price must be greater than zero';
    }
    
    if (empty($sale_date)) {
        $errors[] = 'Sale date is required';
    }
    
    if (empty($invoice_number)) {
        $errors[] = 'Invoice number is required';
    }
    
    // If no errors, check available quantity and process sale
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Check if selected stock has enough quantity
            $stmt = $db->prepare("SELECT quantity, unit_price FROM stocks WHERE stock_id = :stock_id");
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->execute();
            $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stockData) {
                throw new Exception('Selected stock item not found');
            }
            
            if ($stockData['quantity'] < $quantity) {
                throw new Exception('Insufficient stock quantity available');
            }
            
            // Update stock quantity
            $newQuantity = $stockData['quantity'] - $quantity;
            $stmt = $db->prepare("
                UPDATE stocks 
                SET quantity = :quantity,
                    last_updated = NOW()
                WHERE stock_id = :stock_id
            ");
            $stmt->bindParam(':quantity', $newQuantity);
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->execute();
            
            // Insert sale record
            $stmt = $db->prepare("
                INSERT INTO sales (
                    stock_id, customer_name, quantity, sale_price,
                    total_amount, sale_date, invoice_number, 
                    payment_method, notes, created_by
                ) VALUES (
                    :stock_id, :customer_name, :quantity, :sale_price,
                    :total_amount, :sale_date, :invoice_number,
                    :payment_method, :notes, :created_by
                )
            ");
            
            $total_amount = $quantity * $sale_price;
            
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->bindParam(':customer_name', $customer_name);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':sale_price', $sale_price);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':sale_date', $sale_date);
            $stmt->bindParam(':invoice_number', $invoice_number);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Get the sale ID
            $sale_id = $db->lastInsertId();
            
            // Record the transaction
            $stmt = $db->prepare("
                INSERT INTO transactions (
                    transaction_type, reference_id, stock_id, 
                    quantity, notes, created_by
                ) VALUES (
                    'sale', :reference_id, :stock_id,
                    :quantity, :notes, :created_by
                )
            ");
            
            $stmt->bindParam(':reference_id', $sale_id);
            $stmt->bindParam(':stock_id', $stock_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            // Calculate profit/loss
            $profit = ($sale_price - $stockData['unit_price']) * $quantity;
            $stmt = $db->prepare("
                UPDATE sales 
                SET profit_loss = :profit_loss 
                WHERE sale_id = :sale_id
            ");
            $stmt->bindParam(':profit_loss', $profit);
            $stmt->bindParam(':sale_id', $sale_id);
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            setFlashMessage('success', 'Sale successfully recorded.');
            redirect('sales.php');
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollBack();
            error_log("Sale insert error: " . $e->getMessage());
            setFlashMessage('error', 'Error recording sale: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get data for the page
try {
    // Get available stock items for dropdown
    $stmt = $db->prepare("
        SELECT 
            s.stock_id, s.quantity, s.unit_price, s.batch_number,
            w.name as warehouse_name,
            rv.name as variety_name,
            rv.type as variety_type
        FROM stocks s
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        WHERE s.quantity > 0
        ORDER BY w.name, rv.name
    ");
    $stmt->execute();
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent sales
    $stmt = $db->prepare("
        SELECT 
            sa.sale_id, sa.customer_name, sa.quantity, sa.sale_price, 
            sa.total_amount, sa.sale_date, sa.invoice_number, 
            sa.payment_method, sa.profit_loss,
            s.batch_number,
            w.name as warehouse_name,
            rv.name as variety_name,
            u.username as created_by
        FROM sales sa
        JOIN stocks s ON sa.stock_id = s.stock_id
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        JOIN users u ON sa.created_by = u.user_id
        ORDER BY sa.sale_date DESC
        LIMIT 50
    ");
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h3">Sales</h1>
                <div>
                    <?php if ($canAddSales): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#saleModal">
                            <i class="fas fa-plus-circle me-1"></i> New Sale
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sales Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th>Variety</th>
                                    <th>Warehouse</th>
                                    <th>Quantity (kg)</th>
                                    <th>Sale Price</th>
                                    <th>Total</th>
                                    <th>Profit/Loss</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center">No sales found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?php echo formatDate($sale['sale_date'], 'M d, Y'); ?></td>
                                            <td><?php echo $sale['invoice_number']; ?></td>
                                            <td><?php echo $sale['customer_name']; ?></td>
                                            <td><?php echo $sale['variety_name']; ?></td>
                                            <td><?php echo $sale['warehouse_name']; ?></td>
                                            <td><?php echo number_format($sale['quantity'], 2); ?></td>
                                            <td><?php echo formatCurrency($sale['sale_price']); ?></td>
                                            <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $sale['profit_loss'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo formatCurrency($sale['profit_loss']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $sale['payment_method']; ?></td>
                                            <td>
                                                <a href="<?php echo URL_ROOT; ?>/view-sale.php?id=<?php echo $sale['sale_id']; ?>" 
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

<?php if ($canAddSales): ?>
<!-- Sale Modal -->
<div class="modal fade" id="saleModal" tabindex="-1" aria-labelledby="saleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="saleModalLabel">New Sale</h5>
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
                                            data-quantity="<?php echo $item['quantity']; ?>"
                                            data-price="<?php echo $item['unit_price']; ?>">
                                        <?php echo $item['variety_name']; ?> (<?php echo $item['variety_type']; ?>) - 
                                        <?php echo $item['warehouse_name']; ?> - 
                                        <?php echo $item['batch_number']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a stock item.</div>
                            <small class="text-muted">Available quantity: <span id="available-quantity">0</span> kg</small>
                        </div>
                        <div class="col-md-6">
                            <label for="customer_name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                            <div class="invalid-feedback">Please enter a customer name.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Quantity (kg)</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0" required>
                            <div class="invalid-feedback">Please enter a valid quantity.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="sale_price" class="form-label">Sale Price (per kg)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="sale_price" name="sale_price" step="0.01" min="0" required>
                                <div class="invalid-feedback">Please enter a valid sale price.</div>
                            </div>
                            <small class="text-muted">Purchase price: <span id="purchase-price">0.00</span> per kg</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="invoice_number" class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" required>
                            <div class="invalid-feedback">Please enter an invoice number.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="sale_date" class="form-label">Sale Date</label>
                            <input type="date" class="form-control" id="sale_date" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Please select a sale date.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Mobile Payment">Mobile Payment</option>
                            </select>
                            <div class="invalid-feedback">Please select a payment method.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="total_amount" class="form-label">Total Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control" id="total_amount" readonly>
                            </div>
                            <small class="text-muted">Estimated profit: <span id="estimated-profit">0.00</span></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Sale</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stockSelect = document.getElementById('stock_id');
    const availableQty = document.getElementById('available-quantity');
    const purchasePrice = document.getElementById('purchase-price');
    const quantityInput = document.getElementById('quantity');
    const salePriceInput = document.getElementById('sale_price');
    const totalAmountInput = document.getElementById('total_amount');
    const estimatedProfit = document.getElementById('estimated-profit');
    
    stockSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const quantity = parseFloat(selectedOption.dataset.quantity);
            const price = parseFloat(selectedOption.dataset.price);
            
            availableQty.textContent = quantity.toFixed(2);
            purchasePrice.textContent = price.toFixed(2);
            quantityInput.max = quantity;
            
            // Set initial sale price suggestion (10% profit margin)
            salePriceInput.value = (price * 1.1).toFixed(2);
            
            calculateTotal();
        } else {
            availableQty.textContent = '0';
            purchasePrice.textContent = '0.00';
            quantityInput.max = '';
            salePriceInput.value = '';
            totalAmountInput.value = '';
            estimatedProfit.textContent = '0.00';
        }
    });
    
    function calculateTotal() {
        const quantity = parseFloat(quantityInput.value) || 0;
        const salePrice = parseFloat(salePriceInput.value) || 0;
        const purchaseUnitPrice = parseFloat(purchasePrice.textContent) || 0;
        
        const total = (quantity * salePrice).toFixed(2);
        const profit = (quantity * (salePrice - purchaseUnitPrice)).toFixed(2);
        
        totalAmountInput.value = total;
        estimatedProfit.textContent = profit;
        
        // Change color based on profit/loss
        if (parseFloat(profit) >= 0) {
            estimatedProfit.className = 'text-success';
        } else {
            estimatedProfit.className = 'text-danger';
        }
    }
    
    quantityInput.addEventListener('input', calculateTotal);
    salePriceInput.addEventListener('input', calculateTotal);
});
</script>
<?php endif; ?>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 