<?php
/**
 * Database Connection Script
 * 
 * This script establishes a connection to the MySQL database using PDO.
 * PDO (PHP Data Objects) provides a consistent interface for database access
 * and offers better security features like prepared statements.
 */

// Database connection parameters
$servername = "localhost";     // Database server address (localhost for XAMPP)
$username = "root";           // MySQL username (default for XAMPP is 'root')
$password = "";              // MySQL password (default for XAMPP is empty)
$dbname = "Feedora";          // Name of the database to connect to
try {
    // Construct the DSN (Data Source Name) for the PDO connection
    // Format: mysql:host=hostname;dbname=database_name;charset=character_set
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    
    // Set PDO options for better security and performance
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,           // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return results as associative arrays
        PDO::ATTR_EMULATE_PREPARES => false,                    // Use real prepared statements
    ];
    
    // Create a new PDO instance (database connection)
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Connection successful - can be used for database operations throughout the page
    // To execute a query: $stmt = $pdo->query("SELECT * FROM table");
    // To use prepared statements: $stmt = $pdo->prepare("SELECT * FROM table WHERE id = ?");
    //                            $stmt->execute([$id]);
    
} catch (PDOException $e) {
    // If connection fails, store the error message (but don't display it publicly in production)
    // In a production environment, you might want to log this error instead
    $connectionError = $e->getMessage();
    // error_log("Database connection failed: " . $connectionError);
}

// The PDO connection ($pdo) will be automatically closed when the script ends
// For explicit closing: $pdo = null;
?>