 <?php
/**
 * Database connection script for a standard XAMPP setup.
 * This file establishes a connection to the MySQL database using PDO (PHP Data Objects).
 */

// --- Database Configuration ---
$host = 'sql310.infinityfree.com';        // The server where the database resides (usually localhost for XAMPP)
$db   = 'if0_39889135_inventory_db'; // The name of the database you created in phpMyAdmin
$user = 'if0_39889135';             // The default username for MySQL in XAMPP
$pass = '20iMPoj256';                  // The default password for MySQL in XAMPP is empty
$charset = 'utf8mb4';       // The character set for the connection, supporting a wide range of characters

// --- Data Source Name (DSN) ---
// This string contains all the information required for the PDO driver to connect to the database.
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// --- PDO Connection Options ---
// These options configure how PDO handles errors, fetches data, and prepares statements.
$options = [
    // Throw exceptions on errors. This is the recommended error mode as it makes it easy to catch and debug issues.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Set the default fetch mode to associative array (e.g., $row['column_name'] instead of $row[0]).
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Disable emulation of prepared statements for better security (prevents SQL injection) and performance.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Establish the Connection ---
try {
     // Create a new PDO instance, which represents the connection to the database.
     // This $pdo variable will be included and used in api.php to run all database queries.
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // If the connection attempt fails, catch the exception.
     
     // Set the HTTP response code to 500 (Internal Server Error) to indicate a server-side problem.
     http_response_code(500);

     // Output a clean JSON error message. The frontend JavaScript can parse this and display a user-friendly error.
     echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $e->getMessage()
     ]);
     
     // Terminate the script immediately to prevent further errors.
     exit;
}