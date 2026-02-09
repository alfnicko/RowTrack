<?php
/**
 * Database connection handler
 * 
 * Establishes a secure connection to MySQL database with error handling
 * Utilises environment variables for credentials or fallback values
 */

 // Db configuration

 /**
  * Server hostname
  * localhost is used for local development
  * For production, this should be the server's IP or domain name
  */
$servername = "localhost";
/**
 * Database username
 * root is the default MySQL user for local development
 * For production, this should be a user with limited privileges
 */
$username = "root";
/**
 * Database password
 * Default is empty for local development
 * For production, this should be a strong password
 * and should not be hardcoded in the script
 */   
$password = "";
/**
 * Database name to connect to
 * Has to match exactly whats in MySql
 */          
$dbname = "row-track-v2"; 

/**
 * Create connection
 * mysqli is used for MySQLi extension
 * automatically atempts connection  
 */
$conn = new mysqli($servername, $username, $password, $dbname);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
