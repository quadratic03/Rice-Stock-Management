# Rice Stock System

A comprehensive web-based Rice Stock Management System built using PHP and MySQL.

## Overview

The Rice Stock System is designed to help rice suppliers, warehouses, and distributors efficiently manage their rice inventory. The system provides a user-friendly interface for tracking stock levels, monitoring warehouse capacity, managing transactions, and generating reports.

## Features

- **Dashboard**: Visual overview of key metrics with charts and tables
- **Inventory Management**: Track rice varieties, quantities, and stock levels
- **Warehouse Management**: Manage multiple warehouses and their capacities
- **Transaction Tracking**: Record all stock movements (purchases, sales, transfers)
- **Quality Control**: Monitor and track quality checks and inspections
- **Reporting & Analytics**: Generate custom reports and analyze stock data
- **Supplier Management**: Maintain supplier information and purchase history
- **User Management**: Role-based access control with multiple permission levels
- **Batch Tracking**: Monitor rice batches with production and expiry dates
- **Stock Level Alerts**: Automatic notifications for low stock items

## Technical Details

- **Backend**: PHP 7.4+ with PDO for database access
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Libraries/Frameworks**:
  - Bootstrap 5 (UI Framework)
  - Chart.js (Data Visualization)
  - DataTables (Enhanced Tables)
  - Font Awesome (Icons)
  - jQuery (JavaScript Library)

## Installation

### Prerequisites

- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (optional, for future updates)

### Installation Steps

1. **Clone or download the repository**

   ```
   git clone https://github.com/yourusername/rice-stock-system.git
   ```

2. **Create a MySQL database**

   ```sql
   CREATE DATABASE rice_stock_system;
   ```

3. **Import the database schema**

   Use the included SQL file in the `config` directory:

   ```
   mysql -u username -p rice_stock_system < config/database.sql
   ```

4. **Configure database connection**

   Edit `config/database.php` with your MySQL credentials:

   ```php
   private $host = "localhost";
   private $username = "your_username";
   private $password = "your_password";
   private $db_name = "rice_stock_system";
   ```

5. **Configure application settings**

   Review and edit `config/config.php` to match your environment:

   ```php
   define('URL_ROOT', '/RiceStochSystem'); // Change to your base URL
   ```

6. **Set proper permissions**

   Ensure the web server has write permissions to these directories:
   - `/logs`
   - `/uploads`
   - `/uploads/reports`
   - `/uploads/profiles`
   - `/uploads/temp`

7. **Access the system**

   Open your web browser and navigate to the installation URL. 
   Use the default admin credentials to log in:
   - Username: admin
   - Password: admin123

   **Important**: Change the default admin password immediately after your first login.

## Usage

After installation, you can:

1. Set up your warehouses
2. Add rice varieties
3. Register suppliers
4. Add inventory items
5. Start managing your rice stock

## Directory Structure

```
rice-stock-system/
├── assets/             # Static resources (CSS, JS, images)
├── components/         # Reusable UI components
├── config/             # Configuration files
├── includes/           # Core PHP files
├── layouts/            # Layout templates
├── logs/               # System logs
├── pages/              # Page-specific files
├── uploads/            # Uploaded files
├── index.php           # Entry point
└── README.md           # Documentation
```

## Security

- All user passwords are securely hashed using PHP's password_hash function
- Input validation and sanitization to prevent SQL injection
- Protection against XSS attacks
- CSRF protection for forms
- Role-based access control

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For questions, issues, or support, please open an issue on the GitHub repository or contact the maintainer. 