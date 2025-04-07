<?php
/**
 * Suppliers Page
 * This page displays and manages supplier information
 */

// Set page title
$pageTitle = 'Suppliers';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('suppliers');

// Require login to access this page
requireLogin();

// Check if user has permission
if (!hasRole(['admin', 'manager'])) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    redirect('dashboard.php');
}

// Process form submissions for adding/editing suppliers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form handling code will go here
    if (isset($_POST['add_supplier'])) {
        // Process add supplier form
        $name = sanitize($_POST['name']);
        $contact_person = sanitize($_POST['contact_person']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        $status = sanitize($_POST['status']);
        $notes = sanitize($_POST['notes']);
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Supplier name is required';
        }
        
        if (empty($phone) && empty($email)) {
            $errors[] = 'At least one contact method (phone or email) is required';
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // If no errors, insert into database
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO suppliers (name, contact_person, phone, email, address, status, notes, created_by)
                    VALUES (:name, :contact_person, :phone, :email, :address, :status, :notes, :created_by)
                ");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':contact_person', $contact_person);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Supplier added successfully.');
                    redirect('suppliers.php');
                } else {
                    setFlashMessage('error', 'Error adding supplier. Please try again.');
                }
            } catch (PDOException $e) {
                error_log("Supplier insert error: " . $e->getMessage());
                setFlashMessage('error', 'Database error occurred. Please try again later.');
            }
        } else {
            // Set error message
            setFlashMessage('error', implode('<br>', $errors));
        }
    }
}

// Get suppliers data
try {
    // Get all suppliers
    $stmt = $db->prepare("
        SELECT s.*, u.username as created_by_name,
        (SELECT COUNT(*) FROM stocks WHERE supplier_id = s.supplier_id) as supply_count
        FROM suppliers s
        LEFT JOIN users u ON s.created_by = u.user_id
        ORDER BY s.status ASC, s.name ASC
    ");
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Suppliers data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading suppliers data. Please try again later.');
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
                <h1 class="h3">Suppliers</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                        <i class="fas fa-plus-circle me-1"></i> Add New Supplier
                    </button>
                </div>
            </div>
            
            <!-- Suppliers Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Supply Count</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($suppliers)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No suppliers found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <?php
                                        // Determine status badge class
                                        $statusClass = '';
                                        switch ($supplier['status']) {
                                            case 'active':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'inactive':
                                                $statusClass = 'bg-secondary';
                                                break;
                                            case 'suspended':
                                                $statusClass = 'bg-danger';
                                                break;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $supplier['name']; ?></td>
                                            <td><?php echo $supplier['contact_person'] ?: 'N/A'; ?></td>
                                            <td><?php echo $supplier['phone'] ?: 'N/A'; ?></td>
                                            <td><?php echo $supplier['email'] ?: 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $supplier['supply_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($supplier['status']); ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <a href="<?php echo URL_ROOT; ?>/view-supplier.php?id=<?php echo $supplier['supplier_id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo URL_ROOT; ?>/edit-supplier.php?id=<?php echo $supplier['supplier_id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit Supplier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($supplier['status'] === 'active'): ?>
                                                    <a href="<?php echo URL_ROOT; ?>/deactivate-supplier.php?id=<?php echo $supplier['supplier_id']; ?>" class="btn btn-sm btn-warning btn-confirm" data-bs-toggle="tooltip" title="Deactivate">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo URL_ROOT; ?>/activate-supplier.php?id=<?php echo $supplier['supplier_id']; ?>" class="btn btn-sm btn-success btn-confirm" data-bs-toggle="tooltip" title="Activate">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
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

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Supplier Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Please enter a supplier name.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_supplier">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 