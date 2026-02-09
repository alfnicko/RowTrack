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
$user_id = $_SESSION['user_id'];
$training_plan_id = $_GET['id'] ?? 0;

// Fetch the training plan with basic verification
$plan_query = "SELECT * FROM training_plans WHERE id = ? AND coach_id = ?";
$plan_stmt = $conn->prepare($plan_query);
$plan_stmt->bind_param("ii", $training_plan_id, $user_id);
$plan_stmt->execute();
$plan_result = $plan_stmt->get_result();

if ($plan_result->num_rows === 0) {
    die("<div class='error'>Training plan not found or access denied.</div>");
}

$plan = $plan_result->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update'])) {
        // Update plan and sessions
        $week_start_date = $_POST['week_start_date'];
        
        $update_plan_query = "UPDATE training_plans SET week_start_date = ? WHERE id = ?";
        $update_plan_stmt = $conn->prepare($update_plan_query);
        $update_plan_stmt->bind_param("si", $week_start_date, $training_plan_id);
        $update_plan_stmt->execute();
        
        foreach ($_POST['sessions'] as $day => $session) {
            $update_session_query = "UPDATE training_plan_sessions SET 
                                    activity = ?, 
                                    details = ?, 
                                    session_category = ?
                                    WHERE training_plan_id = ? AND day_of_week = ?";
            $update_stmt = $conn->prepare($update_session_query);
            $update_stmt->bind_param("sssis", 
                $session['activity'],
                $session['details'],
                $session['category'],
                $training_plan_id,
                $day
            );
            $update_stmt->execute();
        }
        
        $_SESSION['success'] = "Training plan updated successfully!";
        header("Location: edit_training_plan.php?id=$training_plan_id");
        exit();
        
    } elseif (isset($_POST['delete'])) {
        // Delete plan and related records
        $conn->begin_transaction();
        
        try {
            $conn->query("DELETE FROM logged_sessions WHERE session_id IN 
                         (SELECT id FROM training_plan_sessions WHERE training_plan_id = $training_plan_id)");
            $conn->query("DELETE FROM training_plan_sessions WHERE training_plan_id = $training_plan_id");
            $conn->query("DELETE FROM training_plans WHERE id = $training_plan_id");
            
            $conn->commit();
            $_SESSION['success'] = "Training plan deleted successfully!";
            header("Location: coach_dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting training plan: " . $e->getMessage();
        }
    }
}

// Fetch sessions for this plan
$sessions_query = "SELECT * FROM training_plan_sessions 
                   WHERE training_plan_id = ? 
                   ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
$sessions_stmt = $conn->prepare($sessions_query);
$sessions_stmt->bind_param("i", $training_plan_id);
$sessions_stmt->execute();
$sessions_result = $sessions_stmt->get_result();

$sessions = [];
while ($session = $sessions_result->fetch_assoc()) {
    $sessions[$session['day_of_week']] = $session;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Training Plan | RowTrack</title>
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
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        .session-type-select {
            margin-bottom: 1rem;
        }
        .session-type-select select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="card">
            <h1><i class="fas fa-edit"></i> Edit Training Plan</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="week_start_date"><i class="fas fa-calendar-day"></i> Week Start Date</label>
                    <input type="date" id="week_start_date" name="week_start_date" 
                           value="<?= htmlspecialchars($plan['week_start_date']) ?>" 
                           class="form-control" required>
                </div>
                
                <h2><i class="fas fa-running"></i> Training Sessions</h2>
                
                <div class="session-grid">
                    <?php
                    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days_of_week as $day): 
                        $session = $sessions[$day] ?? [
                            'activity' => '',
                            'details' => '',
                            'session_category' => 'water'
                        ];
                    ?>
                        <div class="session-card">
                            <h3><?= $day ?></h3>
                            
                            <div class="form-group session-type-select">
                                <label>Session Type</label>
                                <select name="sessions[<?= $day ?>][category]" class="form-control" required>
                                    <option value="water" <?= $session['session_category'] == 'water' ? 'selected' : '' ?>>Water Session</option>
                                    <option value="ergo_steady" <?= $session['session_category'] == 'ergo_steady' ? 'selected' : '' ?>>Ergometer (Steady)</option>
                                    <option value="ergo_work" <?= $session['session_category'] == 'ergo_work' ? 'selected' : '' ?>>Ergometer (Work)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Activity</label>
                                <input type="text" name="sessions[<?= $day ?>][activity]" 
                                       value="<?= htmlspecialchars($session['activity']) ?>" 
                                       class="form-control" placeholder="E.g., Steady State, Intervals">
                            </div>
                            
                            <div class="form-group">
                                <label>Details</label>
                                <textarea name="sessions[<?= $day ?>][details]" class="form-control" rows="3"
                                          placeholder="Workout details, distance, time, etc."><?= htmlspecialchars($session['details']) ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Plan
                    </button>
                    <button type="submit" name="delete" class="btn delete-btn"
                            onclick="return confirm('Are you sure? This will delete ALL related training logs!');">
                        <i class="fas fa-trash-alt"></i> Delete Plan
                    </button>
                    <a href="coach_dashboard.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>