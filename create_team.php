<?php
session_start();
include '../db.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] === 'athlete') {
    header("Location: ../athlete/athlete_dashboard.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $team_name = trim($_POST['team_name']);
    $coach_id = $_SESSION['user_id'];
    
    try {
        // Validate team name isn't empty
        if (empty($team_name)) {
            throw new Exception("Team name cannot be empty");
        }
        
        // Check for duplicate team name (for this coach)
        $check_query = "SELECT id FROM teams WHERE coach_id = ? AND team_name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("is", $coach_id, $team_name);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("You already have a team with this name");
        }
        
        // Generate unique join code
        $max_attempts = 10;
        $join_code = '';
        
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $temp_code = mt_rand(100000000, 999999999);
            
            $code_check = $conn->prepare("SELECT id FROM teams WHERE join_code = ?");
            $code_check->bind_param("s", $temp_code);
            $code_check->execute();
            
            if ($code_check->get_result()->num_rows === 0) {
                $join_code = $temp_code;
                break;
            }
        }
        
        if (empty($join_code)) {
            throw new Exception("Could not generate a unique join code");
        }
        
        // Create the team
        $insert_query = "INSERT INTO teams (team_name, coach_id, join_code) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("sis", $team_name, $coach_id, $join_code);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $_SESSION['success'] = "Team created successfully! Join code: $join_code";
        header("Location: coach_dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Team | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        // Client-side validation
        function validateForm() {
            const teamName = document.getElementById('team_name').value.trim();
            if (teamName === '') {
                alert('Please enter a team name');
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <header class="app-header">
        <div class="container">
            <div class="logo">Row<span>Track</span></div>
            <nav>
                <a href="../logout.php" class="action-link logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card" style="max-width: 600px; margin: 2rem auto;">
            <h2><i class="fas fa-users"></i> Create New Team</h2>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="create_team.php" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="team_name">Team Name</label>
                    <input type="text" id="team_name" name="team_name" class="form-control" 
                           placeholder="Enter team name" required
                           maxlength="50" pattern="[A-Za-z0-9\s\-]+"
                           title="Only letters, numbers, spaces and hyphens">
                    <small class="form-text">Max 50 characters. No special characters.</small>
                </div>
                
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Create Team
                    </button>
                    <a href="coach_dashboard.php" class="btn btn-outline" style="flex: 1; text-align: center; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>