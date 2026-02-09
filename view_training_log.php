<?php
session_start();
include '../db.php';

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

$coach_id = $_SESSION['user_id'];

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $log_id = $_POST['log_id'];
    $comment = trim($_POST['comment']);
    
    $update_query = "UPDATE logged_sessions SET comments = ? WHERE id = ? AND team_id IN (SELECT id FROM teams WHERE coach_id = ?)";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $comment, $log_id, $coach_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Comment added successfully!";
    } else {
        $_SESSION['error'] = "Error adding comment.";
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Fetch teams associated with the coach
$query = "SELECT id, team_name FROM teams WHERE coach_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$teams_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Athlete Logs | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .search-container {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }
        .athlete-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .athlete-header {
            padding: 1rem;
            background: #f5f5f5;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            display: none;
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
        .comment-form {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .comment-input {
            flex-grow: 1;
            padding: 0.5rem;
        }
        .no-logs {
            padding: 1rem;
            color: #666;
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
        <h1><i class="fas fa-clipboard-list"></i> Athlete Training Logs</h1>
        
        <div class="search-container card">
            <div class="form-group" style="flex-grow: 1;">
                <label for="athleteSearch"><i class="fas fa-search"></i> Search Athletes</label>
                <input type="text" id="athleteSearch" class="form-control" placeholder="Type to search...">
            </div>
            <div class="form-group">
                    <?php while ($team = $teams_result->fetch_assoc()): ?>
                        <option value="team-<?= $team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <?php 
        // Reset pointer for teams result
        $teams_result->data_seek(0);
        
        if ($teams_result->num_rows == 0): ?>
            <div class="card">
                <p>You are not associated with any teams.</p>
            </div>
        <?php else: ?>
            <?php while ($team = $teams_result->fetch_assoc()): ?>
                <div class="team-section" id="team-<?= $team['id'] ?>">
                    <h2><?= htmlspecialchars($team['team_name']) ?></h2>
                    
                    <?php
                    $athletes_query = "SELECT id, username FROM users WHERE team_id = ? ORDER BY username";
                    $athletes_stmt = $conn->prepare($athletes_query);
                    $athletes_stmt->bind_param("i", $team['id']);
                    $athletes_stmt->execute();
                    $athletes_result = $athletes_stmt->get_result();
                    
                    while ($athlete = $athletes_result->fetch_assoc()): ?>
                        <div class="athlete-card" data-athlete="<?= strtolower($athlete['username']) ?>">
                            <div class="athlete-header" onclick="toggleLogs(this)">
                                <h3><?= htmlspecialchars($athlete['username']) ?></h3>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            
                            <?php
                            $logs_query = "SELECT ls.id, ls.log_date, ls.distance, ls.duration, 
                                          ls.stroke_rate, ls.heart_rate, ls.athlete_comments, ls.comments, ls.activity_name,
                                          COALESCE(ls.activity_name, ts.activity, 'Strava Session') AS activity
                                   FROM logged_sessions ls
                                   LEFT JOIN training_plan_sessions ts ON ls.session_id = ts.id
                                   WHERE ls.athlete_id = ? AND ls.team_id = ?
                                   ORDER BY ls.log_date DESC";
                            $logs_stmt = $conn->prepare($logs_query);
                            $logs_stmt->bind_param("ii", $athlete['id'], $team['id']);
                            $logs_stmt->execute();
                            $logs_result = $logs_stmt->get_result();
                            ?>
                            
                            <table class="training-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Activity</th>
                                        <th>Distance (m)</th>
                                        <th>Duration</th>
                                        <th>Stroke Rate</th>
                                        <th>Heart Rate</th>
                                        <th>Athlete Notes</th>
                                        <th>Your Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($logs_result->num_rows > 0): ?>
                                        <?php while ($log = $logs_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($log['log_date']) ?></td>
                                                <td><?= htmlspecialchars($log['activity']) ?></td>
                                                <td><?= number_format($log['distance']) ?>m</td>
                                                <td>
                                                    <?php 
                                                    if ($log['duration']) {
                                                        $hours = floor($log['duration'] / 60);
                                                        $minutes = $log['duration'] % 60;
                                                        echo $hours > 0 ? "{$hours}h " : "";
                                                        echo "{$minutes}m";
                                                    }
                                                    ?>
                                                </td>
                                                <td><?= number_format($log['stroke_rate']) ?>spm</td>
                                                <td><?= number_format($log['heart_rate']) ?>bpm</td>
                                                <td><?= nl2br(htmlspecialchars($log['athlete_comments'] ?? '')) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($log['comments']) ?>
                                                    <form class="comment-form" method="POST">
                                                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                        <input type="text" name="comment" class="comment-input" 
                                                               placeholder="Add comment..." value="<?= htmlspecialchars($log['comments'] ?? '') ?>">
                                                        <button type="submit" class="btn btn-small">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="no-logs">No training logs found for this athlete.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <script>
        // Toggle log visibility
        function toggleLogs(header) {
            const table = header.nextElementSibling;
            const icon = header.querySelector('i');
            
            if (table.style.display === 'table') {
                table.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                table.style.display = 'table';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }
        
        // Search functionality
        document.getElementById('athleteSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const athleteCards = document.querySelectorAll('.athlete-card');
            
            athleteCards.forEach(card => {
                const athleteName = card.getAttribute('data-athlete');
                if (athleteName.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Team filter
        document.getElementById('teamSelect').addEventListener('change', function() {
            const teamId = this.value;
            const teamSections = document.querySelectorAll('.team-section');
            
            if (!teamId) {
                teamSections.forEach(section => section.style.display = '');
                return;
            }
            
            teamSections.forEach(section => {
                if (section.id === teamId) {
                    section.style.display = '';
                } else {
                    section.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>

<div style="text-align: center; margin-bottom: 1rem;">
    <a href="coach_dashboard.php" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>