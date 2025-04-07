<?php
/**
 * Sidebar Layout
 * This file contains the collapsible sidebar navigation
 */
?>
<!-- Sidebar Navigation -->
<div class="sidebar bg-light border-end">
    <div class="position-sticky pt-4">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('dashboard') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            
            <li class="nav-header mt-3 mb-1 text-uppercase text-muted ps-3 small">Inventory</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('inventory') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/inventory.php">
                    <i class="fas fa-boxes me-2"></i> Stock Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('add-stock') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/add-stock.php">
                    <i class="fas fa-plus-circle me-2"></i> Add New Stock
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('stock-transfers') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/stock-transfers.php">
                    <i class="fas fa-exchange-alt me-2"></i> Stock Transfers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('stock-adjustments') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/stock-adjustments.php">
                    <i class="fas fa-sliders-h me-2"></i> Stock Adjustments
                </a>
            </li>
            
            <li class="nav-header mt-3 mb-1 text-uppercase text-muted ps-3 small">Locations</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('warehouses') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/warehouses.php">
                    <i class="fas fa-warehouse me-2"></i> Warehouses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('warehouse-map') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/warehouse-map.php">
                    <i class="fas fa-map-marker-alt me-2"></i> Warehouse Map
                </a>
            </li>
            
            <li class="nav-header mt-3 mb-1 text-uppercase text-muted ps-3 small">Transactions</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('transactions') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/transactions.php">
                    <i class="fas fa-list-alt me-2"></i> All Transactions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('purchases') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/purchases.php">
                    <i class="fas fa-shopping-cart me-2"></i> Purchases
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('sales') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/sales.php">
                    <i class="fas fa-cash-register me-2"></i> Sales
                </a>
            </li>
            
            <li class="nav-header mt-3 mb-1 text-uppercase text-muted ps-3 small">Reporting</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('reports') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/reports.php">
                    <i class="fas fa-chart-bar me-2"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActivePage('analytics') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/analytics.php">
                    <i class="fas fa-chart-line me-2"></i> Analytics
                </a>
            </li>
            
            <?php if (hasRole(['admin', 'manager'])): ?>
                <li class="nav-header mt-3 mb-1 text-uppercase text-muted ps-3 small">Management</li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('suppliers') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/suppliers.php">
                        <i class="fas fa-truck me-2"></i> Suppliers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('rice-varieties') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/rice-varieties.php">
                        <i class="fas fa-seedling me-2"></i> Rice Varieties
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('quality-control') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/quality-control.php">
                        <i class="fas fa-clipboard-check me-2"></i> Quality Control
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if (hasRole('admin')): ?>
                <li class="nav-header mt-3 mb-1 text-uppercase text-muted ps-3 small">Administration</li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('users') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/users.php">
                        <i class="fas fa-users me-2"></i> User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('system-logs') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/system-logs.php">
                        <i class="fas fa-history me-2"></i> System Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('settings') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/settings.php">
                        <i class="fas fa-cog me-2"></i> System Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('backup') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/backup.php">
                        <i class="fas fa-database me-2"></i> Backup & Restore
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</div> 