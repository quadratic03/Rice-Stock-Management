<?php
/**
 * Report Functions
 * Helper functions for generating and exporting reports
 */

/**
 * Get report data based on type and date range
 *
 * @param string $reportType Type of report
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return array Report data
 */
function getReportData($reportType, $startDate, $endDate) {
    global $db;
    $reportData = [];
    
    try {
        switch ($reportType) {
            case 'inventory':
                // Current inventory by warehouse
                $stmt = $db->prepare("
                    SELECT 
                        w.warehouse_id,
                        w.name as warehouse_name,
                        SUM(s.quantity) as total_quantity,
                        SUM(s.quantity * s.unit_price) as total_value,
                        COUNT(s.stock_id) as stock_count,
                        (SUM(s.quantity) / w.capacity) * 100 as utilization_percentage
                    FROM warehouses w
                    LEFT JOIN stocks s ON w.warehouse_id = s.warehouse_id
                    GROUP BY w.warehouse_id
                    ORDER BY w.name ASC
                ");
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'stock_movement':
                // Stock movement in date range
                $stmt = $db->prepare("
                    SELECT 
                        t.transaction_id,
                        t.transaction_type,
                        t.reference_id,
                        t.quantity,
                        DATE_FORMAT(t.transaction_date, '%Y-%m-%d') as date,
                        s.batch_number,
                        rv.name as variety_name,
                        w.name as warehouse_name,
                        u.username as created_by
                    FROM transactions t
                    JOIN stocks s ON t.stock_id = s.stock_id
                    JOIN rice_varieties rv ON s.variety_id = rv.variety_id
                    JOIN warehouses w ON s.warehouse_id = w.warehouse_id
                    JOIN users u ON t.created_by = u.user_id
                    WHERE t.transaction_date BETWEEN :start_date AND :end_date
                    ORDER BY t.transaction_date DESC
                ");
                $stmt->bindParam(':start_date', $startDate);
                $stmt->bindParam(':end_date', $endDate . ' 23:59:59');
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'sales':
                // Sales report in date range
                $stmt = $db->prepare("
                    SELECT 
                        s.sale_id,
                        s.invoice_number,
                        s.customer_name,
                        s.quantity,
                        s.sale_price,
                        s.total_amount,
                        s.profit_loss,
                        DATE_FORMAT(s.sale_date, '%Y-%m-%d') as date,
                        s.payment_method,
                        st.batch_number,
                        rv.name as variety_name,
                        w.name as warehouse_name,
                        u.username as created_by
                    FROM sales s
                    JOIN stocks st ON s.stock_id = st.stock_id
                    JOIN rice_varieties rv ON st.variety_id = rv.variety_id
                    JOIN warehouses w ON st.warehouse_id = w.warehouse_id
                    JOIN users u ON s.created_by = u.user_id
                    WHERE s.sale_date BETWEEN :start_date AND :end_date
                    ORDER BY s.sale_date DESC
                ");
                $stmt->bindParam(':start_date', $startDate);
                $stmt->bindParam(':end_date', $endDate . ' 23:59:59');
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'purchases':
                // Purchase report in date range
                $stmt = $db->prepare("
                    SELECT 
                        p.purchase_id,
                        p.invoice_number,
                        p.purchase_date as date,
                        p.total_amount,
                        sp.name as supplier_name,
                        s.quantity,
                        s.unit_price,
                        s.batch_number,
                        rv.name as variety_name,
                        w.name as warehouse_name,
                        u.username as created_by
                    FROM purchases p
                    JOIN stocks s ON p.stock_id = s.stock_id
                    LEFT JOIN suppliers sp ON p.supplier_id = sp.supplier_id
                    JOIN rice_varieties rv ON s.variety_id = rv.variety_id
                    JOIN warehouses w ON s.warehouse_id = w.warehouse_id
                    JOIN users u ON p.created_by = u.user_id
                    WHERE p.purchase_date BETWEEN :start_date AND :end_date
                    ORDER BY p.purchase_date DESC
                ");
                $stmt->bindParam(':start_date', $startDate);
                $stmt->bindParam(':end_date', $endDate . ' 23:59:59');
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'profit_loss':
                // Profit/Loss by rice variety in date range
                $stmt = $db->prepare("
                    SELECT 
                        rv.variety_id,
                        rv.name as variety_name,
                        rv.type as variety_type,
                        COUNT(s.sale_id) as sale_count,
                        SUM(s.quantity) as total_quantity,
                        SUM(s.total_amount) as total_amount,
                        SUM(s.profit_loss) as total_profit,
                        AVG(s.sale_price) as avg_sale_price,
                        CASE 
                            WHEN SUM(s.total_amount) > 0 THEN 
                                (SUM(s.profit_loss) / SUM(s.total_amount)) * 100 
                            ELSE 0 
                        END as profit_margin
                    FROM sales s
                    JOIN stocks st ON s.stock_id = st.stock_id
                    JOIN rice_varieties rv ON st.variety_id = rv.variety_id
                    WHERE s.sale_date BETWEEN :start_date AND :end_date
                    GROUP BY rv.variety_id
                    ORDER BY total_profit DESC
                ");
                $stmt->bindParam(':start_date', $startDate);
                $stmt->bindParam(':end_date', $endDate . ' 23:59:59');
                $stmt->execute();
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
    } catch (PDOException $e) {
        error_log("Report data fetch error: " . $e->getMessage());
        return [];
    }
    
    return $reportData;
}

/**
 * Export report data to CSV
 *
 * @param string $reportType Type of report
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return string CSV content
 */
function exportReportToCsv($reportType, $startDate, $endDate) {
    $reportData = getReportData($reportType, $startDate, $endDate);
    if (empty($reportData)) {
        return '';
    }
    
    // Open output stream
    $output = fopen('php://temp', 'w');
    
    // Define CSV headers based on report type
    switch ($reportType) {
        case 'inventory':
            fputcsv($output, [
                'Warehouse',
                'Total Quantity (kg)',
                'Total Value',
                'Stock Count',
                'Utilization (%)'
            ]);
            
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['warehouse_name'],
                    $row['total_quantity'],
                    $row['total_value'],
                    $row['stock_count'],
                    $row['utilization_percentage']
                ]);
            }
            break;
            
        case 'stock_movement':
            fputcsv($output, [
                'ID',
                'Date',
                'Type',
                'Batch Number',
                'Variety',
                'Warehouse',
                'Quantity',
                'Created By'
            ]);
            
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['transaction_id'],
                    $row['date'],
                    ucfirst($row['transaction_type']),
                    $row['batch_number'],
                    $row['variety_name'],
                    $row['warehouse_name'],
                    $row['quantity'],
                    $row['created_by']
                ]);
            }
            break;
            
        case 'sales':
            fputcsv($output, [
                'Invoice',
                'Date',
                'Customer',
                'Batch',
                'Variety',
                'Warehouse',
                'Quantity (kg)',
                'Sale Price',
                'Total Amount',
                'Profit/Loss',
                'Payment Method',
                'Created By'
            ]);
            
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['invoice_number'],
                    $row['date'],
                    $row['customer_name'],
                    $row['batch_number'],
                    $row['variety_name'],
                    $row['warehouse_name'],
                    $row['quantity'],
                    $row['sale_price'],
                    $row['total_amount'],
                    $row['profit_loss'],
                    $row['payment_method'],
                    $row['created_by']
                ]);
            }
            break;
            
        case 'purchases':
            fputcsv($output, [
                'Invoice',
                'Date',
                'Supplier',
                'Batch',
                'Variety',
                'Warehouse',
                'Quantity (kg)',
                'Unit Price',
                'Total Amount',
                'Created By'
            ]);
            
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['invoice_number'],
                    $row['date'],
                    $row['supplier_name'] ?: 'N/A',
                    $row['batch_number'],
                    $row['variety_name'],
                    $row['warehouse_name'],
                    $row['quantity'],
                    $row['unit_price'],
                    $row['total_amount'],
                    $row['created_by']
                ]);
            }
            break;
            
        case 'profit_loss':
            fputcsv($output, [
                'Variety',
                'Type',
                'Sales Count',
                'Quantity Sold (kg)',
                'Total Sales',
                'Total Profit/Loss',
                'Avg. Sale Price',
                'Profit Margin (%)'
            ]);
            
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['variety_name'],
                    $row['variety_type'],
                    $row['sale_count'],
                    $row['total_quantity'],
                    $row['total_amount'],
                    $row['total_profit'],
                    $row['avg_sale_price'],
                    $row['profit_margin']
                ]);
            }
            break;
    }
    
    // Get the content
    rewind($output);
    $csvContent = stream_get_contents($output);
    fclose($output);
    
    return $csvContent;
}

/**
 * Generate report chart data
 *
 * @param array $reportData Report data
 * @param string $reportType Type of report
 * @return array Chart data
 */
function generateChartData($reportData, $reportType) {
    $chartData = [];
    
    switch ($reportType) {
        case 'inventory':
            foreach ($reportData as $row) {
                $chartData['labels'][] = $row['warehouse_name'];
                $chartData['quantities'][] = $row['total_quantity'];
                $chartData['values'][] = $row['total_value'];
            }
            break;
            
        case 'stock_movement':
            // Process data for chart
            $dates = [];
            $transactionTypes = ['purchase', 'sale', 'transfer', 'adjustment'];
            $typeTotals = [];
            
            // Group by date and transaction type
            foreach ($reportData as $row) {
                $date = $row['date'];
                $type = $row['transaction_type'];
                $quantity = $row['quantity'];
                
                if (!in_array($date, $dates)) {
                    $dates[] = $date;
                }
                
                if (!isset($typeTotals[$type][$date])) {
                    $typeTotals[$type][$date] = 0;
                }
                
                $typeTotals[$type][$date] += $quantity;
            }
            
            // Sort dates
            sort($dates);
            
            $chartData['labels'] = $dates;
            foreach ($transactionTypes as $type) {
                $chartData[$type] = [];
                foreach ($dates as $date) {
                    $chartData[$type][] = isset($typeTotals[$type][$date]) ? $typeTotals[$type][$date] : 0;
                }
            }
            break;
            
        case 'sales':
            // Group by date
            $salesByDate = [];
            foreach ($reportData as $row) {
                $date = $row['date'];
                
                if (!isset($salesByDate[$date])) {
                    $salesByDate[$date] = [
                        'date' => $date,
                        'total_quantity' => 0,
                        'total_amount' => 0,
                        'total_profit' => 0
                    ];
                }
                
                $salesByDate[$date]['total_quantity'] += $row['quantity'];
                $salesByDate[$date]['total_amount'] += $row['total_amount'];
                $salesByDate[$date]['total_profit'] += $row['profit_loss'];
            }
            
            // Sort by date
            ksort($salesByDate);
            
            foreach ($salesByDate as $date => $data) {
                $chartData['labels'][] = formatDate($date, 'M d');
                $chartData['quantities'][] = $data['total_quantity'];
                $chartData['amounts'][] = $data['total_amount'];
                $chartData['profits'][] = $data['total_profit'];
            }
            break;
            
        case 'purchases':
            // Group by date
            $purchasesByDate = [];
            foreach ($reportData as $row) {
                $date = $row['date'];
                
                if (!isset($purchasesByDate[$date])) {
                    $purchasesByDate[$date] = [
                        'date' => $date,
                        'total_quantity' => 0,
                        'total_amount' => 0
                    ];
                }
                
                $purchasesByDate[$date]['total_quantity'] += $row['quantity'];
                $purchasesByDate[$date]['total_amount'] += $row['total_amount'];
            }
            
            // Sort by date
            ksort($purchasesByDate);
            
            foreach ($purchasesByDate as $date => $data) {
                $chartData['labels'][] = formatDate($date, 'M d');
                $chartData['quantities'][] = $data['total_quantity'];
                $chartData['amounts'][] = $data['total_amount'];
            }
            break;
            
        case 'profit_loss':
            foreach ($reportData as $row) {
                $chartData['labels'][] = $row['variety_name'];
                $chartData['quantities'][] = $row['total_quantity'];
                $chartData['amounts'][] = $row['total_amount'];
                $chartData['profits'][] = $row['total_profit'];
                $chartData['margins'][] = $row['profit_margin'];
            }
            break;
    }
    
    return $chartData;
}

/**
 * Get report summary
 *
 * @param array $reportData Report data
 * @param string $reportType Type of report
 * @return array Summary data
 */
function getReportSummary($reportData, $reportType) {
    $summary = [];
    
    switch ($reportType) {
        case 'inventory':
            $summary['total_warehouses'] = count($reportData);
            $summary['total_quantity'] = array_sum(array_column($reportData, 'total_quantity'));
            $summary['total_value'] = array_sum(array_column($reportData, 'total_value'));
            $summary['total_stock_count'] = array_sum(array_column($reportData, 'stock_count'));
            $summary['avg_utilization'] = array_sum(array_column($reportData, 'utilization_percentage')) / max(1, $summary['total_warehouses']);
            break;
            
        case 'stock_movement':
            $summary['total_transactions'] = count($reportData);
            $summary['total_in'] = 0;
            $summary['total_out'] = 0;
            
            foreach ($reportData as $row) {
                if ($row['quantity'] > 0) {
                    $summary['total_in'] += $row['quantity'];
                } else {
                    $summary['total_out'] += abs($row['quantity']);
                }
            }
            
            $summary['net_change'] = $summary['total_in'] - $summary['total_out'];
            break;
            
        case 'sales':
            $summary['total_sales'] = count($reportData);
            $summary['total_quantity'] = array_sum(array_column($reportData, 'quantity'));
            $summary['total_amount'] = array_sum(array_column($reportData, 'total_amount'));
            $summary['total_profit'] = array_sum(array_column($reportData, 'profit_loss'));
            $summary['avg_profit_margin'] = $summary['total_amount'] > 0 
                ? ($summary['total_profit'] / $summary['total_amount']) * 100 
                : 0;
            break;
            
        case 'purchases':
            $summary['total_purchases'] = count($reportData);
            $summary['total_quantity'] = array_sum(array_column($reportData, 'quantity'));
            $summary['total_amount'] = array_sum(array_column($reportData, 'total_amount'));
            $summary['avg_unit_price'] = $summary['total_quantity'] > 0 
                ? $summary['total_amount'] / $summary['total_quantity'] 
                : 0;
            break;
            
        case 'profit_loss':
            $summary['total_varieties'] = count($reportData);
            $summary['total_quantity'] = array_sum(array_column($reportData, 'total_quantity'));
            $summary['total_amount'] = array_sum(array_column($reportData, 'total_amount'));
            $summary['total_profit'] = array_sum(array_column($reportData, 'total_profit'));
            $summary['avg_profit_margin'] = $summary['total_amount'] > 0 
                ? ($summary['total_profit'] / $summary['total_amount']) * 100 
                : 0;
            $summary['profitable_varieties'] = count(array_filter($reportData, function($row) {
                return $row['total_profit'] > 0;
            }));
            break;
    }
    
    return $summary;
}

/**
 * Get setting value from database
 *
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value
 */
function getSetting($key, $default = null) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Setting fetch error: " . $e->getMessage());
        return $default;
    }
}

/**
 * Update setting value in database
 *
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return boolean Success status
 */
function updateSetting($key, $value) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE setting_value = :value
        ");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Setting update error: " . $e->getMessage());
        return false;
    }
} 