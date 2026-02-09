<?php
/**
 * Athlete Dashboard
 * 
 * Displays performance metrics, team info, and training plans.
 * Allows logging sessions, tracking goals, and team participation.
 * 
 * Security:
 * - Only athletes can access this page.
 * - Validates session and role.
 * - Uses prepared statements for all SQL queries.
 */
session_start();
include '../db.php';

// Ensure user is logged in and is an athlete
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if ($_SESSION['role'] === 'coach') {
    header("Location: ../coach/coach_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id']; 
$username = $_SESSION['username']; 

// Retrieve teams
$query = "SELECT t.id as team_id, t.team_name, t.join_code 
          FROM teams t 
          JOIN users u ON t.id = u.team_id 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result(); 

$teams = [];
while ($row = $result->fetch_assoc()) {
    $teams[] = $row;
}

// Performance Metrics: Total, Weekly, Monthly
$total_query = $conn->prepare("SELECT SUM(distance) AS total_distance FROM logged_sessions WHERE athlete_id = ?");
$total_query->bind_param("i", $user_id);
$total_query->execute();
$total_result = $total_query->get_result();
$total_distance = $total_result->fetch_assoc()['total_distance'] ?? 0;

$today = new DateTime();
$monday = clone $today;
$monday->modify('this week')->modify('Monday');
$sunday = clone $monday;
$sunday->modify('+6 days');

$weekly_query = $conn->prepare("SELECT SUM(distance) AS weekly_distance 
                                FROM logged_sessions 
                                WHERE athlete_id = ? 
                                AND log_date BETWEEN ? AND ?");
$monday_date = $monday->format('Y-m-d');
$sunday_date = $sunday->format('Y-m-d');
$weekly_query->bind_param("iss", $user_id, $monday_date, $sunday_date);
$weekly_query->execute(); 
$weekly_result = $weekly_query->get_result();
$weekly_distance = $weekly_result->fetch_assoc()['weekly_distance'] ?? 0;

$first_day_of_month = $today->format('Y-m-01');
$last_day_of_month = $today->format('Y-m-t');

$monthly_query = $conn->prepare("SELECT SUM(distance) AS monthly_distance 
                                 FROM logged_sessions 
                                 WHERE athlete_id = ? 
                                 AND log_date BETWEEN ? AND ?");
$monthly_query->bind_param("iss", $user_id, $first_day_of_month, $last_day_of_month);
$monthly_query->execute();
$monthly_result = $monthly_query->get_result();
$monthly_distance = $monthly_result->fetch_assoc()['monthly_distance'] ?? 0;

// Team goal progress
$current_goal = null;
$completion_percentage = 0;
$athlete_distance_km = 0;

if (!empty($teams)) {
    $team_id = $teams[0]['team_id'];
    $goal_query = $conn->prepare("SELECT * FROM team_goals WHERE team_id = ? ORDER BY start_date DESC LIMIT 1");
    $goal_query->bind_param("i", $team_id);
    $goal_query->execute();
    $goal_result = $goal_query->get_result();
    $current_goal = $goal_result->fetch_assoc();

    if ($current_goal) {
        $goal_distance_km = $current_goal['goal_distance'];
        $athlete_query = $conn->prepare("SELECT SUM(distance) as total_distance 
                                         FROM logged_sessions 
                                         WHERE athlete_id = ? 
                                         AND log_date BETWEEN ? AND ?");
        $athlete_query->bind_param("iss", $user_id, $current_goal['start_date'], $current_goal['end_date']);
        $athlete_query->execute();
        $athlete_result = $athlete_query->get_result();
        $athlete_distance_m = $athlete_result->fetch_assoc()['total_distance'] ?? 0;
        $athlete_distance_km = $athlete_distance_m / 1000;
        if ($goal_distance_km > 0) {
            $completion_percentage = min(100, ($athlete_distance_km / $goal_distance_km) * 100);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Athlete Dashboard</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    .performance-metrics {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background-color: #f9f9f9;
    border-left: 6px solid #1a6fbf; 
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    flex: 1 1 240px;
    padding: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.metric-content {
    flex-grow: 1;
}

.metric-value {
    font-size: 1.8em;
    font-weight: bold;
    color: #333;
    margin-top: 10px;
}

.metric-unit {
    font-size: 0.5em;
    color: #777;
}

.metric-icon {
    font-size: 2em;
    color: #1a6fbf;
    margin-left: 15px;
}

.period-indicator {
    font-size: 0.9em;
    color: #666;
    margin-top: 4px;
}

.progress-bar-container {
    background-color: #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    margin-top: 10px;
    height: 28px;
    width: 100%;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.15);
}

.progress-bar {
    background-color: #2196F3; 
    color: #fff;
    height: 100%;
    text-align: center;
    line-height: 28px;
    font-weight: bold;
    transition: width 0.6s ease;
}
</style>
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
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Athlete Dashboard</h1>
        <div class="welcome-message">
            <h2>Welcome back, <?php echo htmlspecialchars($username); ?></h2>
        </div>

        <section class="performance-section">
            <div class="performance-metrics">
                <div class="metric-card">
                    <div class="metric-content">
                        <h3>Total Distance</h3>
                        <div class="metric-value"><?php echo number_format($total_distance / 1000, 2); ?><span class="metric-unit">km</span></div>
                        <div class="period-indicator">All Time</div>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-content">
                        <h3>Weekly Distance</h3>
                        <div class="metric-value"><?php echo number_format($weekly_distance / 1000, 2); ?><span class="metric-unit">km</span></div>
                        <div class="period-indicator"><?php echo $monday->format('M j'); ?> - <?php echo $sunday->format('M j'); ?></div>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-content">
                        <h3>Monthly Distance</h3>
                        <div class="metric-value"><?php echo number_format($monthly_distance / 1000, 2); ?><span class="metric-unit">km</span></div>
                        <div class="period-indicator"><?php echo $today->format('F Y'); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($current_goal): ?>
        <section class="goal-tracker-section">
            <h2><i class="fas fa-bullseye"></i> Team Goal Progress</h2>
            <p>Goal: <?php echo htmlspecialchars($current_goal['goal_distance']); ?> km</p>
            <p>Your logged distance: <?php echo number_format($athlete_distance_km, 2); ?> km</p>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%;">
                    <?php echo round($completion_percentage); ?>%
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="teams-section">
            <h2><i class="fas fa-users"></i> Your Team</h2>
            <?php if (!empty($teams)): ?>
                <?php foreach ($teams as $team): ?>
                    <div class="team-card card">
                        <div class="team-header">
                            <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                            <span class="team-code">Join Code: <?php echo htmlspecialchars($team['join_code']); ?></span>
                        </div>
                        <?php
                        $training_plans_query = "SELECT * FROM training_plans WHERE team_id = ? ORDER BY week_start_date DESC";
                        $training_plans_stmt = $conn->prepare($training_plans_query);
                        $training_plans_stmt->bind_param("i", $team['team_id']);
                        $training_plans_stmt->execute();
                        $training_plans_result = $training_plans_stmt->get_result();

                        $today = date('Y-m-d');
                        $current_week_found = false;
                        $future_weeks = [];
                        ?>
                        <?php if ($training_plans_result->num_rows > 0): ?>
                            <div class="training-plans">
                                <?php while ($plan = $training_plans_result->fetch_assoc()): ?>
                                    <?php
                                    $week_start = $plan['week_start_date'];
                                    $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
                                    $plan_id = $plan['id'];
                                    ob_start();
                                    ?>
                                    <div class="training-plan">
                                        <h4>Week of <?php echo htmlspecialchars($week_start); ?></h4>
                                        <?php
                                        $sessions_query = "SELECT * FROM training_plan_sessions WHERE training_plan_id = ? 
                                            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
                                        $sessions_stmt = $conn->prepare($sessions_query);
                                        $sessions_stmt->bind_param("i", $plan_id);
                                        $sessions_stmt->execute();
                                        $sessions_result = $sessions_stmt->get_result();
                                        ?>
                                        <?php if ($sessions_result->num_rows > 0): ?>
                                            <table class="training-table">
                                                <thead>
                                                    <tr>
                                                        <th>Day</th>
                                                        <th>Activity</th>
                                                        <th>Details</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $row_num = 0;
                                                    while ($session = $sessions_result->fetch_assoc()): 
                                                        $row_bg = $row_num % 2 === 0 ? '' : '';
                                                    ?>
                                                        <tr class="training-table-row" style="background:<?php echo $row_bg; ?>;">
                                                            <td> <?php echo htmlspecialchars($session['day_of_week']); ?></td>
                                                            <td><?php echo htmlspecialchars($session['activity']); ?> </td>
                                                            <td><?php echo nl2br(htmlspecialchars($session['details'])); ?></td>
                                                        </tr>
                                                    <?php 
                                                        $row_num++;
                                                    endwhile; 
                                                    ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p class="no-sessions">No sessions scheduled for this week.</p>
                                        <?php endif; ?>
                                        <form action="leave_team.php" method="POST" onsubmit="return confirm('Are you sure you want to leave this team?');" style="margin-top: 10px;">
                                            <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($team['team_id']); ?>">
                                            <button type="submit" class="btn btn-danger">Leave Team</button>
                                    </div>
                                    <?php
                                    $plan_html = ob_get_clean();
                                    if ($today >= $week_start && $today <= $week_end) {
                                        echo '<div class="training-plan current-week">';
                                        echo '<h4>This Week: ' . htmlspecialchars($week_start) . ' <span class="current-badge">Current</span></h4>';
                                        echo $plan_html;
                                        echo '</div>';
                                        $current_week_found = true;
                                    } elseif ($today < $week_start) {
                                        $future_weeks[] = ['title' => $week_start, 'content' => $plan_html];
                                    }
                                    ?>
                                <?php endwhile; ?>

                                <?php if (!$current_week_found): ?>
                                    <div class="training-plan current-week">
                                        <h4>This Week</h4>
                                        <p><em>No training plan found for this week yet.</em></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($future_weeks)): ?>
                                    <details class="grouped-plans">
                                        <summary><i class="fas fa-calendar-alt"></i> Upcoming Weeks</summary>
                                        <?php foreach ($future_weeks as $week): ?>
                                            <details class="training-plan-dropdown">
                                                <summary>Week of <?php echo htmlspecialchars($week['title']); ?></summary>
                                                <?php echo $week['content']; ?>
                                            </details>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-plans">No training plans available for this team.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div>
                    <i class="fas fa-users-slash"></i>
                    <p>You haven't joined any teams yet.</p>
                </div>
            <?php endif; ?>
        </section>
        <div class="action-nav">
            <a href="join_team.php" class="action-link"><i class="fas fa-user-plus"></i> Join a Team</a>
            <a href="log_training.php" class="action-link"><i class="fas fa-plus-circle"></i> Log Training</a>
            <a href="../strava/connect_strava.php" class="action-link"><i class="fas fa-plug"></i> Upload from Strava</a>
            <a href="view_logged_sessions.php" class="action-link"><i class="fas fa-history"></i> Logged Sessions & Coach Feedback</a>
            <a href="performance_trends.php?type=all" class="action-link"><i class="fas fa-chart-line"></i> Performance Trends</a>
            <a href="delete_session.php" class="action-link"><i class="fas fa-trash-alt"></i> Delete Training Session</a>
        </div>
    </div>
</main>
</body>
</html>