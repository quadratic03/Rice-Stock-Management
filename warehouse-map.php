<?php
/**
 * Warehouse Map Page
 * This page displays warehouses on an interactive map
 */

// Set page title
$pageTitle = 'Warehouse Map';

// Include initialization file
require_once __DIR__ . '/includes/init.php';

// Set active page
setActivePage('warehouse-map');

// Require login to access this page
requireLogin();

// Get warehouses with location data
try {
    $stmt = $db->prepare("
        SELECT 
            warehouse_id, name, location, status,
            latitude, longitude, 
            (SELECT SUM(quantity) FROM stocks WHERE warehouse_id = warehouses.warehouse_id) as total_stock
        FROM warehouses
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY name ASC
    ");
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Warehouse map data fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading warehouse location data. Please try again later.');
    $warehouses = [];
}

// Set up Google Maps API key from settings
$googleMapsApiKey = getSetting('google_maps_api_key', '');

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
                <h1 class="h3">Warehouse Map</h1>
                <div>
                    <a href="<?php echo URL_ROOT; ?>/warehouses.php" class="btn btn-primary">
                        <i class="fas fa-warehouse me-1"></i> Manage Warehouses
                    </a>
                </div>
            </div>
            
            <!-- Map Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (empty($warehouses)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No warehouses with location data found. Please update warehouse coordinates in the warehouse management page.
                        </div>
                    <?php else: ?>
                        <div id="warehouse-map" style="height: 500px; width: 100%;"></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Warehouse List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Warehouse Locations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Stock Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($warehouses)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No warehouses with location data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <tr>
                                            <td><?php echo $warehouse['name']; ?></td>
                                            <td><?php echo $warehouse['location']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $warehouse['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ucfirst($warehouse['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $warehouse['total_stock'] ? number_format($warehouse['total_stock'], 2) . ' kg' : 'No stock'; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info center-map" 
                                                        data-lat="<?php echo $warehouse['latitude']; ?>" 
                                                        data-lng="<?php echo $warehouse['longitude']; ?>"
                                                        data-bs-toggle="tooltip" 
                                                        title="Center on Map">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </button>
                                                <a href="<?php echo URL_ROOT; ?>/inventory.php?warehouse_id=<?php echo $warehouse['warehouse_id']; ?>" 
                                                   class="btn btn-sm btn-primary"
                                                   data-bs-toggle="tooltip" 
                                                   title="View Inventory">
                                                    <i class="fas fa-boxes"></i>
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

<?php if (!empty($warehouses) && !empty($googleMapsApiKey)): ?>
<!-- Google Maps Scripts -->
<script>
let map;
let markers = [];
let infoWindow;

function initMap() {
    // Create map centered in the middle of all warehouses
    map = new google.maps.Map(document.getElementById("warehouse-map"), {
        zoom: 6,
        center: { lat: 0, lng: 0 },
    });
    
    infoWindow = new google.maps.InfoWindow();
    
    const bounds = new google.maps.LatLngBounds();
    
    // Add markers for each warehouse
    <?php foreach ($warehouses as $warehouse): ?>
    (function() {
        const warehousePos = {
            lat: <?php echo $warehouse['latitude']; ?>,
            lng: <?php echo $warehouse['longitude']; ?>
        };
        
        const marker = new google.maps.Marker({
            position: warehousePos,
            map: map,
            title: "<?php echo addslashes($warehouse['name']); ?>",
            icon: {
                url: "<?php echo URL_ROOT; ?>/assets/images/warehouse-marker-<?php echo $warehouse['status']; ?>.png",
                scaledSize: new google.maps.Size(32, 32)
            }
        });
        
        markers.push(marker);
        bounds.extend(warehousePos);
        
        const contentString = `
            <div class="map-info-window">
                <h5>${marker.getTitle()}</h5>
                <p>
                    <strong>Location:</strong> <?php echo addslashes($warehouse['location']); ?><br>
                    <strong>Status:</strong> <?php echo ucfirst($warehouse['status']); ?><br>
                    <strong>Stock:</strong> <?php echo $warehouse['total_stock'] ? number_format($warehouse['total_stock'], 2) . ' kg' : 'No stock'; ?>
                </p>
                <a href="<?php echo URL_ROOT; ?>/inventory.php?warehouse_id=<?php echo $warehouse['warehouse_id']; ?>" class="btn btn-sm btn-primary">
                    View Inventory
                </a>
            </div>
        `;
        
        marker.addListener("click", () => {
            infoWindow.setContent(contentString);
            infoWindow.open({
                anchor: marker,
                map,
                shouldFocus: false,
            });
        });
    })();
    <?php endforeach; ?>
    
    // Fit map to show all markers
    map.fitBounds(bounds);
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle center map buttons
    const centerButtons = document.querySelectorAll('.center-map');
    centerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);
            
            if (!isNaN(lat) && !isNaN(lng)) {
                map.setCenter({ lat, lng });
                map.setZoom(15);
                
                // Find the corresponding marker and open its info window
                markers.forEach(marker => {
                    if (marker.getPosition().lat() === lat && marker.getPosition().lng() === lng) {
                        google.maps.event.trigger(marker, 'click');
                    }
                });
            }
        });
    });
    
    // Enable tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo $googleMapsApiKey; ?>&callback=initMap&v=weekly">
</script>
<?php else: ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips even without map
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
<?php endif; ?>

<?php 
// Include footer
include_once __DIR__ . '/layouts/footer.php'; 
?> 