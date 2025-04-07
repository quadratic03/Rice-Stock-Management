<?php
/**
 * Add Stock Page
 * This page allows users to add new stock items to the inventory
 */

// Set page title
$pageTitle = 'Add New Stock';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('add-stock');

// Require login to access this page
requireLogin();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $warehouse_id = (int)$_POST['warehouse_id'];
    $variety_id = (int)$_POST['variety_id'];
    $quantity = (float)$_POST['quantity'];
    $unit_price = (float)$_POST['unit_price'];
    $batch_number = sanitize($_POST['batch_number']);
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
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
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
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Stock added successfully.');
                redirect('inventory.php');
            } else {
                setFlashMessage('error', 'Error adding stock. Please try again.');
            }
        } catch (PDOException $e) {
            error_log("Stock insert error: " . $e->getMessage());
            setFlashMessage('error', 'Database error occurred. Please try again later.');
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get warehouses and rice varieties for dropdowns
try {
    // Get active warehouses
    $stmt = $db->prepare("SELECT warehouse_id, name FROM warehouses WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get rice varieties
    $stmt = $db->prepare("SELECT variety_id, name, type FROM rice_varieties ORDER BY name");
    $stmt->execute();
    $varieties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dropdown data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading form data. Please try again later.');
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
                <h1 class="h3">Add New Stock</h1>
                <div>
                    <a href="<?php echo URL_ROOT; ?>/inventory.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Inventory
                    </a>
                </div>
            </div>
            
            <!-- Add Stock Form -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
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
                                <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 