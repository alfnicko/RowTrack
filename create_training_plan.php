<?php
session_start();
include '../db.php';

// Check if the user is logged in and has the "coach" role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] === 'athlete') {
    header("Location: ../athlete/athlete_dashboard.php");
    exit();
}

if ($_SESSION['role'] !== 'coach') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if team_id is passed in URL
if (isset($_GET['team_id'])) {
    $team_id = $_GET['team_id'];

    // Ensure coach is associated with the team
    $team_query = "SELECT * FROM teams WHERE id = ? AND coach_id = ?";
    $team_stmt = $conn->prepare($team_query);
    $team_stmt->bind_param("ii", $team_id, $user_id);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();

    if ($team_result->num_rows == 0) {
        die("<div class='error'>Error: You are not assigned to this team.</div>");
    }
} else {
    die("<div class='error'>Error: No team ID provided.</div>");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $week_start_date = $_POST['week_start_date'];
    
    // Validate that the selected date is a Monday
    $day_of_week = date('N', strtotime($week_start_date));
    if ($day_of_week != 1) {
        $error = "Please select a Monday as the week start date";
    } else {
        // Insert the training plan
        $insert_plan_query = "INSERT INTO training_plans (coach_id, team_id, week_start_date) VALUES (?, ?, ?)";
        $insert_plan_stmt = $conn->prepare($insert_plan_query);
        $insert_plan_stmt->bind_param("iis", $user_id, $team_id, $week_start_date);

        if ($insert_plan_stmt->execute()) {
            $training_plan_id = $insert_plan_stmt->insert_id;

            // Insert training sessions with correct dates
            $days_of_week = [
                'Monday' => 0,
                'Tuesday' => 1,
                'Wednesday' => 2,
                'Thursday' => 3,
                'Friday' => 4,
                'Saturday' => 5,
                'Sunday' => 6
            ];
            
            foreach ($_POST['sessions'] as $day => $session) {
                $activity = $session['activity'] ?? "";
                $details = $session['details'] ?? "";
                $category = $session['category'] ?? "water"; // Default to water session

                if (!empty($activity) || !empty($details)) {
                    // Calculate the actual date for this session
                    $days_to_add = $days_of_week[$day];
                    $session_date = date('Y-m-d', strtotime("$week_start_date + $days_to_add days"));
                    
                    $insert_session_query = "INSERT INTO training_plan_sessions (
                        training_plan_id, 
                        day_of_week, 
                        activity, 
                        details,
                        session_date,
                        session_category
                    ) VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $insert_session_stmt = $conn->prepare($insert_session_query);
                    $insert_session_stmt->bind_param("isssss", 
                        $training_plan_id, 
                        $day, 
                        $activity, 
                        $details,
                        $session_date,
                        $category
                    );
                    $insert_session_stmt->execute();
                }
            }

            $_SESSION['success'] = "Training plan created successfully!";
            header("Location: coach_dashboard.php");
            exit();
        } else {
            $error = "Error creating training plan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Training Plan | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--space-md);
            margin: var(--space-lg) 0;
        }
        
        .session-card {
            background: white;
            padding: var(--space-md);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border-top: 3px solid var(--ocean-blue);
        }
        
        .form-actions {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-lg);
        }
        
        .date-note {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        
        .date-indicator {
            font-weight: normal;
            color: #666;
            font-size: 0.8em;
        }
        
        .session-type-select {
            margin-bottom: var(--space-md);
        }
        
        .session-type-select select {
            width: 100%;
            padding: 8px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('week_start_date');
            
            dateInput.addEventListener('change', function() {
                // Update the displayed dates when week start date changes
                const startDate = new Date(this.value);
                const dayOfWeek = startDate.getDay(); // 0=Sun, 1=Mon, etc.
                
                // Show warning if not Monday
                if (dayOfWeek !== 1) {
                    alert('Please select a Monday as the week start date');
                    this.value = '';
                    return;
                }
                
                // Update all session date indicators
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                days.forEach((day, index) => {
                    const sessionDate = new Date(startDate);
                    sessionDate.setDate(startDate.getDate() + index);
                    
                    const dateSpans = document.querySelectorAll(`.${day.toLowerCase()}-date`);
                    dateSpans.forEach(span => {
                        span.textContent = sessionDate.toLocaleDateString('en-US', { 
                            weekday: 'short', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                    });
                });
            });
        });
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
        <div class="card">
            <h1><i class="fas fa-calendar-plus"></i> Create Training Plan</h1>
            
            <?php if (isset($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="create_training_plan.php?team_id=<?php echo $team_id; ?>">
                <div class="form-group">
                    <label for="week_start_date"><i class="fas fa-calendar-day"></i> Week Start Date (Monday)</label>
                    <input type="date" id="week_start_date" name="week_start_date" class="form-control" required>
                    <p class="date-note">Please select the Monday of the week you're planning for</p>
                </div>
                
                <h2><i class="fas fa-running"></i> Training Sessions</h2>
                
                <div class="session-grid">
                    <?php
                    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days_of_week as $day): ?>
                        <div class="session-card">
                            <h3><?php echo $day; ?> <span class="<?php echo strtolower($day); ?>-date date-indicator">(select week start date)</span></h3>
                            
                            <div class="form-group session-type-select">
                                <label>Session Type</label>
                                <select name="sessions[<?php echo $day; ?>][category]" class="form-control" required>
                                    <option value="water">Water Session</option>
                                    <option value="ergo_steady">Ergometer (Steady State)</option>
                                    <option value="ergo_work">Ergometer (Work Session)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Activity</label>
                                <input type="text" name="sessions[<?php echo $day; ?>][activity]" class="form-control" placeholder="E.g., Steady State, Intervals">
                            </div>
                            
                            <div class="form-group">
                                <label>Details</label>
                                <textarea name="sessions[<?php echo $day; ?>][details]" class="form-control" rows="3" placeholder="Workout details, distance, time, etc."></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Plan
                    </button>
                    <a href="coach_dashboard.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>