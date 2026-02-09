<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== 'coach') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the coach's team
$team_query = $conn->prepare("SELECT id, team_name FROM teams WHERE coach_id = ?");
$team_query->bind_param("i", $user_id);
$team_query->execute();
$team_result = $team_query->get_result();
$team = $team_result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($team)) {
    $goal_distance = $_POST['goal_distance'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $insert = $conn->prepare("INSERT INTO team_goals (team_id, goal_distance, start_date, end_date) VALUES (?, ?, ?, ?)");
    $insert->bind_param("idss", $team['id'], $goal_distance, $start_date, $end_date);

    if ($insert->execute()) {
        $success_message = "Goal successfully set for your team!";
    } else {
        $error_message = "Error setting goal: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Team Goal</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <h2><i class="fas fa-bullseye"></i> Set Team Goal</h2>

            <?php if (isset($success_message)): ?>
                    <?php echo $success_message; ?>
            <?php endif; ?>

            <?php if (isset($team)): ?>
                <form method="post" action="">
                    <div class="form-group">
                        <label>Team</label>
                        <div class="form-control-static">
                            <?php echo htmlspecialchars($team['team_name']); ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="goal_distance">Goal Distance (km)</label>
                        <input type="number" name="goal_distance" id="goal_distance" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Set Goal
                        </button>
                        <a href="coach_dashboard.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Back
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> You don't have a team assigned.
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>