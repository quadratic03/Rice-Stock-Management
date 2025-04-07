<?php
/**
 * Database Configuration File
 * This file contains database connection settings for the Rice Stock System
 */

class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $db_name = "rice_stock_system";
    private $conn;
    
    // Get the database connection
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}
?> 