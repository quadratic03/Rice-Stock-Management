<?php
/**
 * Rice Varieties Page
 * This page displays and manages rice variety information
 */

// Set page title
$pageTitle = 'Rice Varieties';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('rice-varieties');

// Require login to access this page
requireLogin();

// Check if user has permission
if (!hasRole(['admin', 'manager'])) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    redirect('dashboard.php');
}

// Process form submissions for adding/editing rice varieties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form handling code will go here
    if (isset($_POST['add_variety'])) {
        // Process add variety form
        $name = sanitize($_POST['name']);
        $type = sanitize($_POST['type']);
        $origin = sanitize($_POST['origin']);
        $average_price = (float)$_POST['average_price'];
        $description = sanitize($_POST['description']);
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Variety name is required';
        }
        
        if (empty($type)) {
            $errors[] = 'Rice type is required';
        }
        
        if ($average_price <= 0) {
            $errors[] = 'Average price must be greater than zero';
        }
        
        // If no errors, insert into database
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO rice_varieties (name, type, origin, average_price, description, created_by)
                    VALUES (:name, :type, :origin, :average_price, :description, :created_by)
                ");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':type', $type);
                $stmt->bindParam(':origin', $origin);
                $stmt->bindParam(':average_price', $average_price);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Rice variety added successfully.');
                    redirect('rice-varieties.php');
                } else {
                    setFlashMessage('error', 'Error adding rice variety. Please try again.');
                }
            } catch (PDOException $e) {
                error_log("Rice variety insert error: " . $e->getMessage());
                setFlashMessage('error', 'Database error occurred. Please try again later.');
            }
        } else {
            // Set error message
            setFlashMessage('error', implode('<br>', $errors));
        }
    }
}

// Get rice varieties data
try {
    // Get all rice varieties
    $stmt = $db->prepare("
        SELECT rv.*, u.username as created_by_name
        FROM rice_varieties rv
        LEFT JOIN users u ON rv.created_by = u.user_id
        ORDER BY rv.name ASC
    ");
    $stmt->execute();
    $varieties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Rice varieties data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading rice varieties data. Please try again later.');
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
                <h1 class="h3">Rice Varieties</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVarietyModal">
                        <i class="fas fa-plus-circle me-1"></i> Add New Variety
                    </button>
                </div>
            </div>
            
            <!-- Rice Varieties Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Origin</th>
                                    <th>Average Price</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($varieties)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No rice varieties found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($varieties as $variety): ?>
                                        <tr>
                                            <td><?php echo $variety['name']; ?></td>
                                            <td><?php echo $variety['type']; ?></td>
                                            <td><?php echo $variety['origin'] ?: 'N/A'; ?></td>
                                            <td><?php echo formatCurrency($variety['average_price']); ?></td>
                                            <td><?php echo $variety['created_by_name'] ?? 'System'; ?></td>
                                            <td class="table-actions">
                                                <a href="<?php echo URL_ROOT; ?>/view-variety.php?id=<?php echo $variety['variety_id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo URL_ROOT; ?>/edit-variety.php?id=<?php echo $variety['variety_id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit Variety">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?php echo URL_ROOT; ?>/delete-variety.php?id=<?php echo $variety['variety_id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
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

<!-- Add Rice Variety Modal -->
<div class="modal fade" id="addVarietyModal" tabindex="-1" aria-labelledby="addVarietyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="addVarietyModalLabel">Add New Rice Variety</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Variety Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Please enter a variety name.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="type" class="form-label">Rice Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Select Rice Type</option>
                                <option value="Long Grain">Long Grain</option>
                                <option value="Medium Grain">Medium Grain</option>
                                <option value="Short Grain">Short Grain</option>
                                <option value="Jasmine">Jasmine</option>
                                <option value="Basmati">Basmati</option>
                                <option value="Brown">Brown</option>
                                <option value="Glutinous">Glutinous</option>
                                <option value="Wild">Wild</option>
                                <option value="Black">Black</option>
                                <option value="Red">Red</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="invalid-feedback">Please select a rice type.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="origin" class="form-label">Origin</label>
                            <input type="text" class="form-control" id="origin" name="origin">
                        </div>
                        <div class="col-md-6">
                            <label for="average_price" class="form-label">Average Price (per kg)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="average_price" name="average_price" step="0.01" min="0" required>
                                <div class="invalid-feedback">Please enter a valid price.</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_variety">Save Variety</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 