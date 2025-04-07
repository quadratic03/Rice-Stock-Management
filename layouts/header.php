<?php
/**
 * Header Layout
 * This file contains the header section of the site including DOCTYPE, head, and navigation
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo URL_ROOT; ?>/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($extraCss)): ?>
        <?php echo $extraCss; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container-fluid">
            <!-- Logo -->
            <a class="navbar-brand" href="<?php echo URL_ROOT; ?>/index.php">
                <img src="<?php echo URL_ROOT; ?>/assets/images/logo.png" alt="Rice Stock System Logo" height="40">
                <span class="ms-2 fw-bold"><?php echo SITE_NAME; ?></span>
            </a>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <?php if (isLoggedIn()): ?>
                    <!-- Main Navigation Menu -->
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActivePage('dashboard') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActivePage('inventory') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/inventory.php">
                                <i class="fas fa-boxes me-1"></i> Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActivePage('warehouses') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/warehouses.php">
                                <i class="fas fa-warehouse me-1"></i> Warehouses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActivePage('transactions') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/transactions.php">
                                <i class="fas fa-exchange-alt me-1"></i> Transactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActivePage('reports') ? 'active' : ''; ?>" href="<?php echo URL_ROOT; ?>/reports.php">
                                <i class="fas fa-chart-bar me-1"></i> Reports
                            </a>
                        </li>
                        <?php if (hasRole(['admin', 'manager'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="managementDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog me-1"></i> Management
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="managementDropdown">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/suppliers.php">
                                            <i class="fas fa-truck me-1"></i> Suppliers
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/rice-varieties.php">
                                            <i class="fas fa-seedling me-1"></i> Rice Varieties
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/quality-control.php">
                                            <i class="fas fa-clipboard-check me-1"></i> Quality Control
                                        </a>
                                    </li>
                                    <?php if (hasRole('admin')): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/users.php">
                                                <i class="fas fa-users me-1"></i> User Management
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/settings.php">
                                                <i class="fas fa-sliders-h me-1"></i> System Settings
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- Search Form -->
                    <form class="d-flex mx-auto" action="<?php echo URL_ROOT; ?>/search.php" method="GET">
                        <div class="input-group">
                            <input class="form-control" type="search" name="q" placeholder="Search inventory..." aria-label="Search" required>
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- User Profile and Notifications -->
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <!-- Notifications -->
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php
                                    // TODO: Get unread notifications count from database
                                    $notificationCount = 5; // Placeholder
                                    echo $notificationCount > 0 ? ($notificationCount > 9 ? '9+' : $notificationCount) : '';
                                    ?>
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="width: 300px;">
                                <h6 class="dropdown-header">Notifications</h6>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php
                                    // TODO: Get notifications from database
                                    $notifications = []; // Placeholder
                                    
                                    if (empty($notifications)):
                                    ?>
                                        <p class="dropdown-item text-muted">No notifications</p>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $notification): ?>
                                            <a class="dropdown-item" href="#">
                                                <small class="text-muted"><?php echo formatDate($notification['created_at'], 'M d, H:i'); ?></small><br>
                                                <strong><?php echo $notification['title']; ?></strong><br>
                                                <?php echo $notification['message']; ?>
                                            </a>
                                            <div class="dropdown-divider"></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <a class="dropdown-item text-center" href="<?php echo URL_ROOT; ?>/notifications.php">View all notifications</a>
                            </div>
                        </li>
                        
                        <!-- User Profile -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/profile.php">
                                        <i class="fas fa-user me-1"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/activity-log.php">
                                        <i class="fas fa-history me-1"></i> Activity Log
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo URL_ROOT; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                <?php else: ?>
                    <!-- Guest Navigation Menu -->
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo URL_ROOT; ?>/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <div class="container-fluid mt-5 pt-3">
        <!-- Flash Messages -->
        <?php echo displayFlashMessage(); ?> 