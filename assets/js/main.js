/**
 * Rice Stock System - Main JavaScript
 * This file contains common JavaScript functionality for the Rice Stock System
 */

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar toggle
    initSidebarToggle();
    
    // Initialize tooltips and popovers
    initTooltipsAndPopovers();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize delete confirmations
    initDeleteConfirmations();
    
    // Initialize notifications dropdown
    initNotificationsDropdown();
    
    // Initialize auto-dismiss alerts
    initAutoDismissAlerts();
    
    // Initialize DataTables
    initDataTables();
});

/**
 * Initialize sidebar toggle functionality
 */
function initSidebarToggle() {
    const sidebarToggleBtn = document.querySelector('.sidebar-toggle');
    
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapse');
            
            // Store preference in localStorage
            const isCollapsed = document.body.classList.contains('sidebar-collapse');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        });
        
        // Check localStorage for sidebar state
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapse');
        }
    }
}

/**
 * Initialize Bootstrap tooltips and popovers
 */
function initTooltipsAndPopovers() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Initialize form validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Initialize delete confirmations
 */
function initDeleteConfirmations() {
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', event => {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                event.preventDefault();
            }
        });
    });
}

/**
 * Initialize notifications dropdown
 */
function initNotificationsDropdown() {
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    
    if (notificationsDropdown) {
        notificationsDropdown.addEventListener('click', function() {
            // Mark notifications as read via AJAX
            const notificationCount = document.querySelector('.badge-notification');
            
            if (notificationCount && !notificationCount.classList.contains('updating')) {
                notificationCount.classList.add('updating');
                
                // Simple fetch example - replace with actual implementation
                fetch('mark-notifications-read.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            notificationCount.textContent = '0';
                            notificationCount.classList.add('d-none');
                        }
                    })
                    .catch(error => console.error('Error marking notifications as read:', error))
                    .finally(() => {
                        notificationCount.classList.remove('updating');
                    });
            }
        });
    }
}

/**
 * Initialize DataTables
 */
function initDataTables() {
    // Check if jQuery and DataTables are available
    if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables initialization failed: jQuery or DataTables not loaded');
        return;
    }
    
    // Initialize each data table
    $('.data-table').each(function() {
        const table = $(this);
        
        // Get the number of columns in the table header
        const headerColumns = table.find('thead th').length;
        
        // Get the number of columns in the first row of tbody (if exists)
        let bodyColumns = 0;
        const firstRow = table.find('tbody tr:first-child td');
        if (firstRow.length) {
            bodyColumns = firstRow.length;
        }
        
        // Log warning if column counts don't match
        if (headerColumns !== bodyColumns && bodyColumns > 0) {
            console.warn(`DataTables warning: Table has ${headerColumns} columns in header but ${bodyColumns} columns in body. This might cause errors.`);
            
            // If there's a colspan in the first row, it might be a "No data" message
            // In this case, don't show the warning
            const hasColspan = firstRow.filter('[colspan]').length > 0;
            if (!hasColspan) {
                console.warn('Table structure issue: Header and body column counts should match.');
            }
        }
        
        try {
            // Create the DataTable instance with proper configuration
            table.DataTable({
                responsive: true,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                },
                // Use a safer approach that doesn't rely on pre-counting columns
                autoWidth: false
            });
        } catch (error) {
            console.error('DataTables initialization error:', error);
            // Apply a basic non-DataTables sorting/filtering functionality
            table.addClass('table-basic-sort');
        }
    });
}

/**
 * Initialize auto-dismiss alerts
 */
function initAutoDismissAlerts() {
    const autoAlerts = document.querySelectorAll('.alert-dismissible.auto-dismiss');
    
    autoAlerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000); // Auto dismiss after 5 seconds
    });
}

/**
 * Format number as currency
 * 
 * @param {number} amount The amount to format
 * @param {string} symbol The currency symbol (default: $)
 * @param {number} decimals The number of decimal places (default: 2)
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, symbol = '$', decimals = 2) {
    return symbol + parseFloat(amount).toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format date
 * 
 * @param {string|Date} date The date to format
 * @param {string} format The format to use (default: 'YYYY-MM-DD')
 * @returns {string} Formatted date string
 */
function formatDate(date, format = 'YYYY-MM-DD') {
    const d = new Date(date);
    
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    
    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes)
        .replace('ss', seconds);
}

/**
 * Create a chart using Chart.js
 * 
 * @param {string} canvasId The ID of the canvas element
 * @param {string} type The chart type (bar, line, pie, etc.)
 * @param {object} data The chart data
 * @param {object} options The chart options
 * @returns {Chart} The Chart.js instance
 */
function createChart(canvasId, type, data, options = {}) {
    const canvas = document.getElementById(canvasId);
    
    if (!canvas) {
        console.error(`Canvas with ID "${canvasId}" not found.`);
        return null;
    }
    
    return new Chart(canvas, {
        type: type,
        data: data,
        options: options
    });
}

/**
 * Show a loading indicator
 * 
 * @param {string|Element} target The target element or selector
 * @param {string} size The size of the spinner (sm, md, lg)
 * @param {string} message Optional message to display
 */
function showLoading(target, size = 'md', message = 'Loading...') {
    const targetElement = typeof target === 'string' ? document.querySelector(target) : target;
    
    if (!targetElement) {
        return;
    }
    
    const spinnerSize = size === 'sm' ? 'spinner-border-sm' : (size === 'lg' ? 'spinner-border-lg' : '');
    
    const spinner = `
        <div class="text-center loading-indicator">
            <div class="spinner-border ${spinnerSize} text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">${message}</p>
        </div>
    `;
    
    // Store original content
    targetElement.setAttribute('data-original-content', targetElement.innerHTML);
    targetElement.innerHTML = spinner;
}

/**
 * Hide the loading indicator and restore original content
 * 
 * @param {string|Element} target The target element or selector
 */
function hideLoading(target) {
    const targetElement = typeof target === 'string' ? document.querySelector(target) : target;
    
    if (!targetElement) {
        return;
    }
    
    const originalContent = targetElement.getAttribute('data-original-content');
    
    if (originalContent) {
        targetElement.innerHTML = originalContent;
        targetElement.removeAttribute('data-original-content');
    }
} 