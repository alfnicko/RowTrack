<?php
session_start();
include 'db.php';

// Initialize variables to retain form values after submission
$username = $email = $role = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'];

    // Password validation
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    }

    if (empty($error)) {
        // Check if username/email exists
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username or Email already taken, please log in instead.";
        } else {
            // Hash password and insert into DB
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);

            if ($insert_stmt->execute()) {
                $_SESSION['registration_success'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | RowTrack</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .requirement {
            display: flex;
            align-items: center;
            margin: 0.25rem 0;
            font-size: 0.85rem;
            color: #666;
        }
        .requirement i {
            margin-right: 0.5rem;
        }
        .error {
            color: var(--regatta-red);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-hero">
        <div class="container">
            <h1>RowTrack</h1>
        </div>
    </div>
    
    <div class="container">
        <div class="login-card card">          
            <h2>Welcome to RowTrack - Register here</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($username); ?>" 
                           placeholder="Choose your username" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email); ?>" 
                           placeholder="Your email address" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Create a password" required>
                    
                    <!-- Static password requirements (no real-time validation) -->
                    <div class="password-requirements">
                        <div class="requirement">
                            <i class="fas fa-info-circle"></i>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="requirement">
                            <i class="fas fa-info-circle"></i>
                            <span>One uppercase letter</span>
                        </div>
                        <div class="requirement">
                            <i class="fas fa-info-circle"></i>
                            <span>One number</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="form-control" placeholder="Repeat your password" required>
                </div>
                
                <div class="form-group">
                    <label for="role"><i class="fas fa-user-tag"></i> I am an:</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="athlete" <?php echo ($role == 'athlete') ? 'selected' : ''; ?>>Athlete</option>
                        <option value="coach" <?php echo ($role == 'coach') ? 'selected' : ''; ?>>Coach</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: var(--space-md);">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>
            
            <div class="login-links">
                <p>Already have an account? <a href="login.php" class="text-link">
                    <i class="fas fa-sign-in-alt"></i> Log in instead
                </a></p>
            </div>
        </div>
    </div>
</body>
</html>