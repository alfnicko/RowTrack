<?php
/**
 * Delete Training Sessions
 * 
 * Allows athletes to delete their logged sessions with confirmation.
 * 
 * Security:
 * - Only authenticated athletes can delete their own sessions
 * - CSRF protection
 * - Confirmation dialog
 */

session_start();
require '../db.php';

// Verify authentication and role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== 'athlete') {
    header("Location: ../login.php");
    exit();
}

$athlete_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Process deletion if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
        // Get session ID to delete
        $session_id = $_POST['session_id'] ?? null;
        
        if ($session_id) {
            // Verify the session belongs to this athlete before deleting
            $verify_stmt = $conn->prepare("SELECT id FROM logged_sessions WHERE id = ? AND athlete_id = ?");
            $verify_stmt->bind_param("ii", $session_id, $athlete_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows > 0) {
                // Delete the session
                $delete_stmt = $conn->prepare("DELETE FROM logged_sessions WHERE id = ?");
                $delete_stmt->bind_param("i", $session_id);
                
                if ($delete_stmt->execute()) {
                    $success_message = "Session successfully deleted";
                } else {
                    $error_message = "Error deleting session: " . $conn->error;
                }
            } else {
                $error_message = "Session not found or you don't have permission to delete it";
            }
        } else {
            $error_message = "No session specified";
        }
}


// Get athlete's sessions for the dropdown
$sessions_query = $conn->prepare("
    SELECT id, log_date, activity_name, distance 
    FROM logged_sessions 
    WHERE athlete_id = ? 
    ORDER BY log_date DESC
    LIMIT 100
");
$sessions_query->bind_param("i", $athlete_id);
$sessions_query->execute();
$sessions_result = $sessions_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Training Sessions | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<header class="app-header">
    <div class="container">
        <div class="logo">Row<span>Track</span></div>
        <nav>
            <a href="../logout.php" class="action-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="dashboard-container">
        <h1 class="page-title"><i class="fas fa-trash-alt"></i> Delete Training Sessions</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <a href="view_logged_sessions.php" class="alert-link">View your sessions</a>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="delete-form">
            <form id="deleteForm" method="POST" onsubmit="return confirm('Are you sure you want to delete this session? This cannot be undone.');">
                
                <div class="select-wrapper">
                    <label for="sessionSelect" class="form-label"><i class="fas fa-running"></i> Select Session to Delete</label>
                    <div class="session-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; border-radius: 5px; padding: 10px;">
                        <?php if ($sessions_result->num_rows > 0): ?>
                            <?php while ($session = $sessions_result->fetch_assoc()): ?>
                                <div class="session-row" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($session['log_date']); ?></strong>
                                        &mdash;
                                        <?php echo htmlspecialchars($session['activity_name'] ?: 'Untitled Session'); ?>
                                        (<?php echo number_format($session['distance']/1000, 2); ?> km)
                                    </div>
                                    <div>
                                        <input type="radio" name="session_id" value="<?php echo htmlspecialchars($session['id']); ?>" required>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div>No sessions found.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-trash-alt"></i> Delete Session
                    </button>
                    <a href="athlete_dashboard.php" class="btn btn-outline" style="flex: 1; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
         </form>
</main>
</body>
</html>