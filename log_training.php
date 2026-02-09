<?php
session_start();
include '../db.php';

// Authentication and role checks
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] === 'coach') {
    header("Location: ../coach/coach_dashboard.php");
    exit();
}

if ($_SESSION['role'] !== 'athlete') {
    header("Location: ../login.php");
    exit();
}

$athlete_id = $_SESSION['user_id'];

// Get athlete's team
$team_query = "SELECT team_id FROM users WHERE id = ?";
$stmt = $conn->prepare($team_query);
$stmt->bind_param("i", $athlete_id);
$stmt->execute();
$stmt->bind_result($team_id);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_id = $_POST['session_id'];
    $duration = $_POST['duration'];
    $distance = $_POST['distance'];
    $athlete_comments = $_POST['athlete_comments'] ?? "";
    $log_date = date('Y-m-d');
    $stroke_rate = $_POST['stroke_rate'];
    $heart_rate = $_POST['heart_rate'];

    $activity_query = "SELECT activity FROM training_plan_sessions WHERE id = ?";
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $stmt->bind_result($activity_name);
    if (!$stmt->fetch()) {
        $error = "Invalid training session selected";
    }
    $stmt->close();

    if (!isset($error)) {
        $query = "INSERT INTO logged_sessions (
                    athlete_id, 
                    session_id, 
                    duration, 
                    distance, 
                    athlete_comments, 
                    log_date, 
                    team_id, 
                    heart_rate, 
                    stroke_rate,
                    activity_name
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "iissssiiis",
            $athlete_id,
            $session_id,
            $duration,
            $distance,
            $athlete_comments,
            $log_date,
            $team_id,
            $heart_rate,
            $stroke_rate,
            $activity_name
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Training session logged successfully!";
            header("Location: athlete_dashboard.php");
            exit();
        } else {
            $error = "Error logging session: " . $conn->error;
        }
    }
}

$plans_query = "SELECT 
                tp.id as plan_id, 
                tp.week_start_date, 
                s.id as session_id, 
                s.day_of_week, 
                s.activity,
                DATE_ADD(tp.week_start_date, INTERVAL 
                    CASE s.day_of_week 
                        WHEN 'Monday' THEN 0
                        WHEN 'Tuesday' THEN 1
                        WHEN 'Wednesday' THEN 2
                        WHEN 'Thursday' THEN 3
                        WHEN 'Friday' THEN 4
                        WHEN 'Saturday' THEN 5
                        WHEN 'Sunday' THEN 6
                    END DAY) as session_date
                FROM training_plans tp
                JOIN training_plan_sessions s ON tp.id = s.training_plan_id
                WHERE tp.team_id = ? 
                AND (
                    s.session_date IS NOT NULL OR 
                    DATE_ADD(tp.week_start_date, INTERVAL 
                        CASE s.day_of_week 
                            WHEN 'Monday' THEN 0
                            WHEN 'Tuesday' THEN 1
                            WHEN 'Wednesday' THEN 2
                            WHEN 'Thursday' THEN 3
                            WHEN 'Friday' THEN 4
                            WHEN 'Saturday' THEN 5
                            WHEN 'Sunday' THEN 6
                        END DAY) <= CURDATE()
                )
                ORDER BY COALESCE(s.session_date, 
                    DATE_ADD(tp.week_start_date, INTERVAL 
                        CASE s.day_of_week 
                            WHEN 'Monday' THEN 0
                            WHEN 'Tuesday' THEN 1
                            WHEN 'Wednesday' THEN 2
                            WHEN 'Thursday' THEN 3
                            WHEN 'Friday' THEN 4
                            WHEN 'Saturday' THEN 5
                            WHEN 'Sunday' THEN 6
                        END DAY)) DESC";

$sessions_stmt = $conn->prepare($plans_query);
$sessions_stmt->bind_param("i", $team_id);
$sessions_stmt->execute();
$sessions_result = $sessions_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Training | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .log-container {
            max-width: 600px;
            margin: 2rem auto;
        }
        .session-select {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
        }
        main.container {
            padding-top: 1rem;
        }
        .error {
            color: #dc3545;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #f5c2c7;
            border-radius: 4px;
        }
    </style>
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
        <div class="log-container card">
            <h2><i class="fas fa-plus-circle"></i> Log Training Session</h2>
            
            <?php if (isset($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="log_training.php">
                <div class="form-group">
                    <label for="session_id"><i class="fas fa-calendar-day"></i> Training Session</label>
                    <select id="session_id" name="session_id" class="form-control session-select" required>
                        <?php while ($session = $sessions_result->fetch_assoc()): 
                            $display_date = isset($session['session_date']) ? 
                                $session['session_date'] : 
                                date('Y-m-d', strtotime($session['week_start_date'] . ' + ' . 
                                    match($session['day_of_week']) {
                                        'Monday' => 0,
                                        'Tuesday' => 1,
                                        'Wednesday' => 2,
                                        'Thursday' => 3,
                                        'Friday' => 4,
                                        'Saturday' => 5,
                                        'Sunday' => 6
                                    } . ' days'));
                        ?>
                            <option value="<?= $session['session_id'] ?>">
                                <?= date('M j', strtotime($display_date)) ?> - 
                                <?= $session['day_of_week'] ?>: 
                                <?= htmlspecialchars($session['activity']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="duration"><i class="fas fa-clock"></i> Duration (minutes)</label>
                    <input type="number" id="duration" name="duration" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="distance"><i class="fas fa-route"></i> Distance (meters)</label>
                    <input type="number" id="distance" name="distance" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="stroke_rate"><i class="fas fa-tachometer-alt"></i> Stroke Rate</label>
                    <input type="number" id="stroke_rate" name="stroke_rate" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="heart_rate"><i class="fas fa-heartbeat"></i> Heart Rate</label>
                    <input type="number" id="heart_rate" name="heart_rate" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="athlete_comments"><i class="fas fa-comment"></i> Comments</label>
                    <textarea id="athlete_comments" name="athlete_comments" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-actions" style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Log Session
                    </button>
                    <a href="athlete_dashboard.php" class="btn btn-outline" style="flex: 1;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>