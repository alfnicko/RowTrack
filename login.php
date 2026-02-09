<?php
// Start a session to store user login information
session_start();
include 'db.php'; // Include database connection file

// Check if the form was submitted using POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') { 
    // Get and samitize the username and password from the form
    $username = trim($_POST['username']);// Sanitize input
    $password = trim($_POST['password']); // Sanitize input
    // Prepare the SQL statement to prevent SQL injection
    $query = "SELECT id, password, role, team_id FROM users WHERE username = ?"; 
    $stmt = $conn->prepare($query);
    // Binf the username parameter to the query
    $stmt->bind_param("s", $username);
    $stmt->execute();
    // Store the result to check if the user exists
    $stmt->store_result();
    // Bind all results to variables
    // id, hashed password, role, and team_id
    $stmt->bind_result($id, $hashed_password, $role, $team_id); 
    $stmt->fetch(); // Fetch the results
    // Check if the user exists and verify the password is correct
    if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) { 
        // Password/username is correct, set session variables
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['team_id'] = $team_id;
        // redirect users based on their roles
        if ($role == 'coach') { 
            header("Location: coach/coach_dashboard.php");
        } else { 
            header("Location: athlete/athlete_dashboard.php");
        }
        exit(); // Always best to exit after a redirect because it stops the script from executing further which could cause issues
    } else {
        $error = "Username or password is invalid"; //error message if login has failed
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | RowTrack</title>
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Link to Font Awesome for icons -->
</head>
<body> 
    <div class="login-hero">
        <div class="container">
            <h1>RowTrack</h1> 
        </div>
    </div>
    <div class="container">
        <div class="login-card card">
            <div class="login-illustration">
                <i class="fas fa-rowing"></i>
            </div>
            <!-- Welcome message -->
            <h2>Welcome to RowTrack - Login Here</h2>
            <p class="subtitle">Please log in to continue to your dashboard</p>
            <!-- Error message if login fails -->
            <?php if (isset($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <!-- Login form -->
            <form action="login.php" method="POST">
                <div class="form-group">
                    <!-- Username input field with icon -->
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                </div>
                <!-- Password input field with icon -->
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
                <!-- Submit button with icon -->
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: var(--space-md);">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
                <a href="register.php" class="text-link">
                    <i class="fas fa-user-plus"></i> No account? Create new account
                </a>
            </div>
        </div>
    </div>
</body>
</html>