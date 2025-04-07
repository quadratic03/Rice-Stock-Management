<?php
/**
 * Transactions Page
 * This page displays all stock-related transactions
 */

// Set page title
$pageTitle = 'All Transactions';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('transactions');

// Require login to access this page
requireLogin();

// Define transaction types and their details
$transactionTypes = [
    'all' => ['label' => 'All Transactions', 'icon' => 'list-alt', 'color' => 'primary'],
    'purchase' => ['label' => 'Purchases', 'icon' => 'shopping-cart', 'color' => 'success'],
    'sale' => ['label' => 'Sales', 'icon' => 'cash-register', 'color' => 'info'],
    'transfer' => ['label' => 'Transfers', 'icon' => 'exchange-alt', 'color' => 'warning'],
    'adjustment' => ['label' => 'Adjustments', 'icon' => 'sliders-h', 'color' => 'secondary']
];

// Get current filter type
$currentType = isset($_GET['type']) && array_key_exists($_GET['type'], $transactionTypes) ? $_GET['type'] : 'all';

// Get date range if provided
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Prepare SQL query based on filters
try {
    $params = [];
    $whereClauses = [];
    
    $query = "
        SELECT 
            t.transaction_id,
            t.transaction_type,
            t.reference_id,
            t.stock_id,
            t.quantity,
            t.transaction_date,
            t.notes,
            u.username as created_by,
            s.batch_number,
            rv.name as variety_name,
            w.name as warehouse_name
        FROM transactions t
        JOIN users u ON t.created_by = u.user_id
        JOIN stocks s ON t.stock_id = s.stock_id
        JOIN rice_varieties rv ON s.variety_id = rv.variety_id
        JOIN warehouses w ON s.warehouse_id = w.warehouse_id
    ";
    
    // Filter by transaction type
    if ($currentType !== 'all') {
        $whereClauses[] = "t.transaction_type = :type";
        $params[':type'] = $currentType;
    }
    
    // Filter by date range
    if (!empty($startDate)) {
        $whereClauses[] = "DATE(t.transaction_date) >= :start_date";
        $params[':start_date'] = $startDate;
    }
    
    if (!empty($endDate)) {
        $whereClauses[] = "DATE(t.transaction_date) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    
    // Add WHERE clause if filters exist
    if (!empty($whereClauses)) {
        $query .= " WHERE " . implode(" AND ", $whereClauses);
    }
    
    // Add ORDER BY clause
    $query .= " ORDER BY t.transaction_date DESC LIMIT 200";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Transaction fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading transaction data. Please try again later.');
    $transactions = [];
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
                <h1 class="h3"><?php echo $transactionTypes[$currentType]['label']; ?></h1>
                <div>
                    <a href="<?php echo URL_ROOT; ?>/purchases.php" class="btn btn-success">
                        <i class="fas fa-plus-circle me-1"></i> New Purchase
                    </a>
                    <a href="<?php echo URL_ROOT; ?>/sales.php" class="btn btn-info ms-2">
                        <i class="fas fa-plus-circle me-1"></i> New Sale
                    </a>
                </div>
            </div>
            
            <!-- Transaction Type Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <?php foreach ($transactionTypes as $type => $details): ?>
                            <a href="<?php echo URL_ROOT; ?>/transactions.php?type=<?php echo $type; ?><?php echo !empty($startDate) ? '&start_date=' . $startDate : ''; ?><?php echo !empty($endDate) ? '&end_date=' . $endDate : ''; ?>" 
                               class="btn btn-<?php echo $type === $currentType ? $details['color'] : 'outline-' . $details['color']; ?>">
                                <i class="fas fa-<?php echo $details['icon']; ?> me-1"></i> <?php echo $details['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Date Filter Form -->
                    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                        <input type="hidden" name="type" value="<?php echo $currentType; ?>">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="<?php echo URL_ROOT; ?>/transactions.php?type=<?php echo $currentType; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Batch</th>
                                    <th>Variety</th>
                                    <th>Warehouse</th>
                                    <th>Quantity</th>
                                    <th>Created By</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">No transactions found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <?php 
                                        // Determine badge class based on transaction type
                                        $badgeClass = 'bg-secondary';
                                        switch ($transaction['transaction_type']) {
                                            case 'purchase':
                                                $badgeClass = 'bg-success';
                                                break;
                                            case 'sale':
                                                $badgeClass = 'bg-info';
                                                break;
                                            case 'transfer':
                                                $badgeClass = 'bg-warning';
                                                break;
                                            case 'adjustment':
                                                $badgeClass = 'bg-secondary';
                                                break;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $transaction['transaction_id']; ?></td>
                                            <td><?php echo formatDate($transaction['transaction_date'], 'M d, Y H:i'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $transaction['batch_number']; ?></td>
                                            <td><?php echo $transaction['variety_name']; ?></td>
                                            <td><?php echo $transaction['warehouse_name']; ?></td>
                                            <td><?php echo number_format($transaction['quantity'], 2); ?> kg</td>
                                            <td><?php echo $transaction['created_by']; ?></td>
                                            <td><?php echo $transaction['notes']; ?></td>
                                            <td>
                                                <a href="<?php echo URL_ROOT; ?>/view-transaction.php?id=<?php echo $transaction['transaction_id']; ?>" 
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 