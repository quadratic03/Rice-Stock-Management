<?php
/**
 * Warehouses Page
 * This page displays all warehouses and allows management
 */

// Set page title
$pageTitle = 'Warehouses';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('warehouses');

// Require login to access this page
requireLogin();

// Only admin and manager can access this page
if (!hasRole(['admin', 'manager'])) {
    setFlashMessage('error', 'You do not have permission to access that page.');
    redirect('dashboard.php');
}

// Process form submission for adding/editing warehouse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $warehouse_id = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $name = sanitize($_POST['name']);
    $location = sanitize($_POST['location']);
    $capacity = (float)$_POST['capacity'];
    $manager_name = sanitize($_POST['manager_name']);
    $contact_info = sanitize($_POST['contact_info']);
    $status = sanitize($_POST['status']);
    $description = sanitize($_POST['description']);
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Warehouse name is required';
    }
    
    if (empty($location)) {
        $errors[] = 'Location is required';
    }
    
    if ($capacity <= 0) {
        $errors[] = 'Capacity must be greater than zero';
    }
    
    // If no errors, insert/update database
    if (empty($errors)) {
        try {
            if ($warehouse_id > 0) {
                // Update existing warehouse
                $stmt = $db->prepare("
                    UPDATE warehouses SET 
                        name = :name,
                        location = :location,
                        capacity = :capacity,
                        manager_name = :manager_name,
                        contact_info = :contact_info,
                        status = :status,
                        description = :description,
                        updated_at = NOW()
                    WHERE warehouse_id = :warehouse_id
                ");
                $stmt->bindParam(':warehouse_id', $warehouse_id);
                
                $message = 'Warehouse updated successfully.';
            } else {
                // Insert new warehouse
                $stmt = $db->prepare("
                    INSERT INTO warehouses (
                        name, location, capacity, manager_name,
                        contact_info, status, description, created_by
                    ) VALUES (
                        :name, :location, :capacity, :manager_name,
                        :contact_info, :status, :description, :created_by
                    )
                ");
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                
                $message = 'Warehouse added successfully.';
            }
            
            // Bind common parameters
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':capacity', $capacity);
            $stmt->bindParam(':manager_name', $manager_name);
            $stmt->bindParam(':contact_info', $contact_info);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                setFlashMessage('success', $message);
                redirect('warehouses.php');
            } else {
                throw new Exception('Database error occurred');
            }
        } catch (PDOException $e) {
            error_log("Warehouse save error: " . $e->getMessage());
            setFlashMessage('error', 'Database error occurred. Please try again later.');
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Get warehouses from database
try {
    $stmt = $db->prepare("
        SELECT 
            w.*,
            u.username as created_by_name,
            (SELECT COUNT(*) FROM stocks WHERE warehouse_id = w.warehouse_id) as stock_count,
            (SELECT SUM(quantity) FROM stocks WHERE warehouse_id = w.warehouse_id) as total_stock
        FROM warehouses w
        LEFT JOIN users u ON w.created_by = u.user_id
        ORDER BY w.name ASC
    ");
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Warehouse fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading warehouse data. Please try again later.');
    $warehouses = [];
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
                <h1 class="h3">Warehouses</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal">
                        <i class="fas fa-plus-circle me-1"></i> Add Warehouse
                    </button>
                </div>
            </div>
            
            <!-- Warehouses Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Capacity (kg)</th>
                                    <th>Manager</th>
                                    <th>Stock Items</th>
                                    <th>Total Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($warehouses)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No warehouses found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <tr>
                                            <td><?php echo $warehouse['name']; ?></td>
                                            <td><?php echo $warehouse['location']; ?></td>
                                            <td><?php echo number_format($warehouse['capacity'], 2); ?></td>
                                            <td><?php echo $warehouse['manager_name']; ?></td>
                                            <td><?php echo $warehouse['stock_count']; ?></td>
                                            <td><?php echo $warehouse['total_stock'] ? number_format($warehouse['total_stock'], 2) . ' kg' : '-'; ?></td>
                                            <td>
                                                <span class="badge <?php echo $warehouse['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ucfirst($warehouse['status']); ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <button type="button" class="btn btn-sm btn-info view-warehouse" 
                                                        data-id="<?php echo $warehouse['warehouse_id']; ?>"
                                                        data-name="<?php echo $warehouse['name']; ?>"
                                                        data-location="<?php echo $warehouse['location']; ?>"
                                                        data-capacity="<?php echo $warehouse['capacity']; ?>"
                                                        data-manager="<?php echo $warehouse['manager_name']; ?>"
                                                        data-contact="<?php echo $warehouse['contact_info']; ?>"
                                                        data-status="<?php echo $warehouse['status']; ?>"
                                                        data-description="<?php echo htmlspecialchars($warehouse['description']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewWarehouseModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary edit-warehouse" 
                                                        data-id="<?php echo $warehouse['warehouse_id']; ?>"
                                                        data-name="<?php echo $warehouse['name']; ?>"
                                                        data-location="<?php echo $warehouse['location']; ?>"
                                                        data-capacity="<?php echo $warehouse['capacity']; ?>"
                                                        data-manager="<?php echo $warehouse['manager_name']; ?>"
                                                        data-contact="<?php echo $warehouse['contact_info']; ?>"
                                                        data-status="<?php echo $warehouse['status']; ?>"
                                                        data-description="<?php echo htmlspecialchars($warehouse['description']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#warehouseModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
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

<!-- Add/Edit Warehouse Modal -->
<div class="modal fade" id="warehouseModal" tabindex="-1" aria-labelledby="warehouseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <input type="hidden" id="warehouse_id" name="warehouse_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="warehouseModalLabel">Add Warehouse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Warehouse Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Please enter a warehouse name.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                            <div class="invalid-feedback">Please enter a location.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Capacity (kg)</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" step="0.01" min="0" required>
                            <div class="invalid-feedback">Please enter a valid capacity.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="manager_name" class="form-label">Manager Name</label>
                            <input type="text" class="form-control" id="manager_name" name="manager_name">
                        </div>
                        <div class="col-md-6">
                            <label for="contact_info" class="form-label">Contact Information</label>
                            <input type="text" class="form-control" id="contact_info" name="contact_info">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Warehouse Modal -->
<div class="modal fade" id="viewWarehouseModal" tabindex="-1" aria-labelledby="viewWarehouseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewWarehouseModalLabel">Warehouse Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">Name:</dt>
                    <dd class="col-sm-8" id="view-name"></dd>
                    
                    <dt class="col-sm-4">Location:</dt>
                    <dd class="col-sm-8" id="view-location"></dd>
                    
                    <dt class="col-sm-4">Capacity:</dt>
                    <dd class="col-sm-8"><span id="view-capacity"></span> kg</dd>
                    
                    <dt class="col-sm-4">Manager:</dt>
                    <dd class="col-sm-8" id="view-manager"></dd>
                    
                    <dt class="col-sm-4">Contact:</dt>
                    <dd class="col-sm-8" id="view-contact"></dd>
                    
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8" id="view-status"></dd>
                    
                    <dt class="col-sm-4">Description:</dt>
                    <dd class="col-sm-8" id="view-description"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit warehouse button
    const editButtons = document.querySelectorAll('.edit-warehouse');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = document.getElementById('warehouseModal');
            modal.querySelector('.modal-title').textContent = 'Edit Warehouse';
            
            modal.querySelector('#warehouse_id').value = this.dataset.id;
            modal.querySelector('#name').value = this.dataset.name;
            modal.querySelector('#location').value = this.dataset.location;
            modal.querySelector('#capacity').value = this.dataset.capacity;
            modal.querySelector('#manager_name').value = this.dataset.manager;
            modal.querySelector('#contact_info').value = this.dataset.contact;
            modal.querySelector('#status').value = this.dataset.status;
            modal.querySelector('#description').value = this.dataset.description;
        });
    });
    
    // Handle view warehouse button
    const viewButtons = document.querySelectorAll('.view-warehouse');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('view-name').textContent = this.dataset.name;
            document.getElementById('view-location').textContent = this.dataset.location;
            document.getElementById('view-capacity').textContent = Number(this.dataset.capacity).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('view-manager').textContent = this.dataset.manager;
            document.getElementById('view-contact').textContent = this.dataset.contact;
            
            const statusEl = document.getElementById('view-status');
            statusEl.textContent = this.dataset.status.charAt(0).toUpperCase() + this.dataset.status.slice(1);
            statusEl.className = this.dataset.status === 'active' ? 'badge bg-success' : 'badge bg-danger';
            
            document.getElementById('view-description').textContent = this.dataset.description;
        });
    });
    
    // Reset modal when adding new warehouse
    const addButton = document.querySelector('[data-bs-target="#warehouseModal"]');
    addButton.addEventListener('click', function() {
        if (!this.classList.contains('edit-warehouse')) {
            const modal = document.getElementById('warehouseModal');
            modal.querySelector('.modal-title').textContent = 'Add Warehouse';
            modal.querySelector('form').reset();
            modal.querySelector('#warehouse_id').value = '0';
            modal.querySelector('#status').value = 'active';
        }
    });
});
</script>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 