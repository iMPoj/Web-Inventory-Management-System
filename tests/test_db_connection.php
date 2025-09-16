<?php
// tests/test_db_connection.php

// Include the database connection script.
// The script has its own try-catch block and will exit with a JSON error on failure.
require_once __DIR__ . '/../db_connect.php';

// If the script successfully completes and the $pdo object is created, this check will pass.
if (isset($pdo) && $pdo instanceof PDO) {
    echo "Test Passed: Database connection successful and \$pdo object is valid.\n";
    exit(0); // Success
} else {
    // This part should theoretically not be reached if db_connect.php fails,
    // because that script calls exit(). But as a fallback...
    echo "Test Failed: \$pdo object was not created or is invalid.\n";
    exit(1); // Failure
}
?>
