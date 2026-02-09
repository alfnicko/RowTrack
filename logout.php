<?php
/** 
 * User logout handler
 * 
 * Secure termination of user sessions 
 */

// Start the PHP session as its required to access or modify session data
session_start();
// Firstly we unset all session variables 
// This removes user specific data from the session
// Preserves the session for the destroy function
session_unset(); 
// Fully destroy the session
// This removes the users session file from the server
// Invalidates the session ID
// But does not delete the session cookie from the browser
session_destroy();  

// redirect user to the login page after logging out
header("Location: login.php");
// This is a good practice to ensure no further code is executed after the redirect
exit();
?>
