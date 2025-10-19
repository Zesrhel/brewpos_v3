<?php
// Database Configuration Template
// Copy this file to 'db.php' and update with your actual database credentials

session_start();

$DB_HOST = '127.0.0.1';
$DB_NAME = 'brewpos_v3';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {

    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        die("<div style='padding: 20px; font-family: Arial;'>
            <h3>Database Connection Error</h3>
            <p>The database 'brewpos_v3' does not exist. Please:</p>
            <ol>
                <li>Create a database named 'brewpos_v3' in phpMyAdmin</li>
                <li>Import the SQL structure from brewpos_v3.sql</li>
                <li>Refresh this page</li>
            </ol>
            <p>Error details: " . $e->getMessage() . "</p>
        </div>");
    } else {
        die("<h3>Database connection failed:</h3><pre>".$e->getMessage()."</pre>");
    }
}
?>
