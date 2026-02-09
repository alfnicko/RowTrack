<?php
session_start();
require '../db.php';

// Authentication checks
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== 'athlete') {
    header("Location: ../login.php");
    exit();
}

$coach_id = $_SESSION['user_id'];

// Get current athlete info
$athletes = [
    $_SESSION['user_id'] => $_SESSION['username']
];


// Get selected athlete
$selected_athlete_id = $_GET['athlete_id'] ?? key($athletes);
if (!array_key_exists($selected_athlete_id, $athletes)) {
    $selected_athlete_id = key($athletes);
}

// Session types
$session_types = [
    'water' => ['name' => 'Water Sessions', 'icon' => 'water'],
    'ergo_steady' => ['name' => 'Ergometer (Steady)', 'icon' => 'stopwatch'],
    'ergo_work' => ['name' => 'Ergometer (Work)', 'icon' => 'fire'],
    'all' => ['name' => 'All Sessions', 'icon' => 'list']
];

$selected_type = $_GET['type'] ?? 'water';
if (!array_key_exists($selected_type, $session_types)) {
    $selected_type = 'water';
}

function getAthleteSessions($athlete_id, $session_type) {
    global $conn;
    
    $query = "SELECT 
                ls.id,
                ls.log_date,
                ls.distance, 
                ls.duration,
                ls.stroke_rate,
                ls.heart_rate,
                ls.activity_name,
                tps.session_category,
                tps.activity
              FROM logged_sessions ls
              LEFT JOIN training_plan_sessions tps ON ls.session_id = tps.id
              WHERE ls.athlete_id = ?";
    
    if ($session_type !== 'all') {
        $query .= " AND (tps.session_category = ? OR (? = 'water' AND tps.session_category IS NULL))";
    }
    
    $query .= " ORDER BY ls.log_date DESC LIMIT 20";
    
    $stmt = $conn->prepare($query);
    
    if ($session_type !== 'all') {
        $stmt->bind_param("iss", $athlete_id, $session_type, $session_type);
    } else {
        $stmt->bind_param("i", $athlete_id);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%d:%02d", $hours, $mins);
}

function formatSplitTime($seconds) {
    $minutes = floor($seconds / 60);
    $remaining_seconds = floor($seconds % 60);
    return sprintf("%d:%02d", $minutes, $remaining_seconds);
}

$sessions_result = getAthleteSessions($selected_athlete_id, $selected_type);
$sessions = [];
while ($row = $sessions_result->fetch_assoc()) {
    if ($row['distance'] > 0 && $row['duration'] > 0) {
        $sessions[] = $row;
    }
}

// Prepare chart data
$labels = [];
$splitData = [];
$distanceData = [];

foreach ($sessions as $session) {
    $labels[] = date('M j', strtotime($session['log_date']));
    $splitData[] = ($session['duration'] * 60 / $session['distance']) * 500;
    $distanceData[] = $session['distance'] / 1000;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Performance Trends | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chart-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        <h1><i class="fas fa-chart-line"></i> Athlete Performance Trends</h1>
        <div class="session-tabs">
            <?php foreach ($session_types as $type => $info): ?>
                <a href="?athlete_id=<?= $selected_athlete_id ?>&type=<?= $type ?>" 
                   class="session-tab <?= $selected_type === $type ? 'active' : '' ?>">
                    <i class="fas fa-<?= $info['icon'] ?>"></i> <?= $info['name'] ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($sessions)): ?>
            <div class="card">
                <p>No sessions found for <?= htmlspecialchars($athletes[$selected_athlete_id]) ?> in this category.</p>
            </div>
        <?php else: ?>
            <div class="chart-container">
                <div class="chart-card">
                    <h3>500m Split Time</h3>
                    <canvas id="splitChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Distance (km)</h3>
                    <canvas id="distanceChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>Recent Sessions</h3>
                <table class="training-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Activity</th>
                            <th>Distance</th>
                            <th>Duration</th>
                            <th>Split/500m</th>
                            <th>Heart Rate</th>
                            <th>Stroke Rate</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): 
                            $split = ($session['duration'] * 60 / $session['distance']) * 500;
                            $activity_name = $session['activity_name'] ?? $session['activity'] ?? 'Custom Session';
                        ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($session['log_date'])) ?></td>
                                <td><?= htmlspecialchars($activity_name) ?></td>
                                <td><?= round($session['distance']/1000, 2) ?> km</td>
                                <td><?= formatDuration($session['duration']) ?></td>
                                <td><?= formatSplitTime($split) ?></td>
                                <td><?= htmlspecialchars($session['heart_rate']) ?> bpm</td>
                                <td><?= htmlspecialchars($session['stroke_rate']) ?> spm</td>
                                <td><?= ucfirst(str_replace('_', ' ', $session['session_category'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <div style="display: flex; justify-content: center; margin: 2rem 0;">
            <a href="athlete_dashboard.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    // Split Time Chart
    new Chart(document.getElementById('splitChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_reverse($labels)) ?>,
            datasets: [{
                label: '500m Split Time',
                data: <?= json_encode(array_reverse($splitData)) ?>,
                borderColor: '#1d3557',
                backgroundColor: 'rgba(29, 53, 87, 0.1)',
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    ticks: {
                        callback: function(value) {
                            return formatTime(value);
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Split: ' + formatTime(context.raw);
                        }
                    }
                }
            }
        }
    });

    // Distance Chart
    new Chart(document.getElementById('distanceChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_reverse($labels)) ?>,
            datasets: [{
                label: 'Distance (km)',
                data: <?= json_encode(array_reverse($distanceData)) ?>,
                backgroundColor: 'rgba(42, 157, 143, 0.7)'
            }]
        },
        options: {
            responsive: true
        }
    });
    </script>
</body>
</html>