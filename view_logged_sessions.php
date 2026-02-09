<?php
session_start();
include '../db.php';

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

$query = "SELECT 
            ls.id, 
            ls.log_date, 
            ls.distance, 
            ls.duration, 
            ls.heart_rate,
            ls.stroke_rate,
            ls.athlete_comments,
            ls.session_category, 
            ls.comments AS coach_comments,
            COALESCE(ls.activity_name, ts.activity, 'Custom Session') AS activity,
            t.team_name
          FROM logged_sessions ls
          LEFT JOIN training_plan_sessions ts ON ls.session_id = ts.id
          LEFT JOIN teams t ON ls.team_id = t.id
          WHERE ls.athlete_id = ?
          ORDER BY ls.log_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $athlete_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Training Logs | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .logs-container {
            max-width: 1000px;
            margin: 2rem auto;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
        }
        .log-table th {
            background: var(--ocean-blue);
            color: white;
            padding: 0.75rem;
            text-align: left;
        }
        .log-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        .log-table tr:hover {
            background: #f9f9f9;
        }
        .athlete-notes {
            color: var(--wave-teal);
            font-style: italic;
        }
        .coach-comments {
            color: var(--deep-water);
            font-weight: 500;
            margin-top: 0.5rem;
            padding-left: 1rem;
            border-left: 3px solid var(--ocean-blue);
        }
        .no-logs {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        .duration-cell {
            white-space: nowrap;
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
    <div class="container">
        <h1><i class="fas fa-clipboard-list"></i> My Training Logs</h1>
        
        <div class="logs-container card">
            <?php if ($result->num_rows > 0): ?>
                <table class="training-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Team</th>
                            <th>Activity</th>
                            <th>Distance</th>
                            <th>Duration</th>
                            <th>Heart Rate</th>
                            <th>Stroke Rate</th>
                            <th>My Notes</th>
                            <th>Coach Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['log_date']) ?></td>
                                <td><?= htmlspecialchars($log['team_name']) ?></td>
                                <td><?= htmlspecialchars($log['activity']) ?></td>
                                <td><?= number_format($log['distance']) ?>m</td>
                                <td class="duration-cell">
                                    <?php 
                                    if ($log['duration']) {
                                        $hours = floor($log['duration'] / 60);
                                        $minutes = $log['duration'] % 60;
                                        echo $hours > 0 ? "{$hours}h " : "";
                                        echo "{$minutes}m";
                                    }
                                    ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($log['heart_rate']?? '')) ?> bpm </td>
                                <td><?= nl2br(htmlspecialchars($log['stroke_rate'] ?? '')) ?> spm</td>
                                <td><?= nl2br(htmlspecialchars($log['athlete_comments'] ?? '')) ?></td>
                                <td><?= nl2br(htmlspecialchars($log['coach_comments'] ?? '')) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-logs">
                    <i class="fas fa-clipboard" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>No training logs found.</p>
                    <p><a href="log_training.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Log a Training Session
                    </a></p>
                </div>
            <?php endif; ?>
        </div>
        <div style="margin-top: 2rem; text-align: center;">
            <a href="athlete_dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>