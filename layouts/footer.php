    </div> <!-- End of container -->

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Version <?php echo SYSTEM_VERSION; ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo URL_ROOT; ?>/assets/js/main.js"></script>
    
    <?php if (isset($extraJs)): ?>
        <?php echo $extraJs; ?>
    <?php endif; ?>
    
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // DataTables are now initialized by the main.js initDataTables() function
    </script>

<?php 
// Execute the 'footer' action hook for plugins and extensions
if (function_exists('do_action')) {
    do_action('footer');
}
?>

<?php if (isset($_GET['debug_tables']) && $_GET['debug_tables'] === '1'): ?>
<script>
// Table structure debugging helper
document.addEventListener('DOMContentLoaded', function() {
    console.log('========= TABLE STRUCTURE DEBUG =========');
    
    const tables = document.querySelectorAll('table.data-table');
    tables.forEach((table, index) => {
        const tableId = table.id || `anonymous-table-${index}`;
        const headerCols = table.querySelectorAll('thead th').length;
        
        console.log(`Table #${index+1} [${tableId}]:`);
        console.log(`  Header columns: ${headerCols}`);
        
        // Check body rows
        const bodyRows = table.querySelectorAll('tbody tr');
        console.log(`  Body rows: ${bodyRows.length}`);
        
        bodyRows.forEach((row, rowIndex) => {
            const actualCols = row.querySelectorAll('td').length;
            const hasColspan = Array.from(row.querySelectorAll('td')).some(td => td.hasAttribute('colspan'));
            
            if (actualCols !== headerCols && !hasColspan) {
                console.warn(`  ⚠️ Row #${rowIndex+1} has ${actualCols} columns instead of ${headerCols}`);
                
                // Log the HTML of the problematic row for inspection
                console.log('  Row content:', row.innerHTML);
            }
        });
        
        console.log('----------------------------------------');
    });
});
</script>
<?php endif; ?>

</body>
</html> 