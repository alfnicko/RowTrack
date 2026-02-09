<?php

session_start(); // Start session to access user authentication data
include 'db.php'; // Include database connection file
// Verify if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
// get the metric and time range from the request, default to distance and all time
// Use null operator to set default values if not provided
$metric = $_GET['metric'] ?? 'distance';
$timeRange = $_GET['timeRange'] ?? 'all';
$athlete_id = $_GET['athlete_id'] ?? $_SESSION['user_id'];  // fallback to own ID

// Validate the metrics against a predefined list
// This is to prevent SQL injection and ensure only valid metrics are processed
$validMetrics = ['distance', 'time', 'pace'];
if (!in_array($metric, $validMetrics)) {
    echo json_encode(["error" => "Invalid metric"]);
    exit();
}
// Validate the time range
$dateFilter = "";
if ($timeRange !== "all") {
    // Check if the time range is a valid integer
    $days = intval($timeRange);
    $dateFilter = "AND log_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
}
// Database query to fetch training data
$sql = "
    SELECT 
        YEAR(log_date) AS yr,
        WEEK(log_date, 1) AS wk,
        MIN(log_date) AS week_start,
        SUM(distance) AS total_distance,
        SUM(duration) AS total_duration
    FROM logged_sessions
    WHERE athlete_id = ?
    $dateFilter
    GROUP BY yr, wk
    ORDER BY yr, wk
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $athlete_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $distance_km = $row['total_distance'] / 1000;
    $duration_hr = $row['total_duration'] / 3600;

    if ($metric === 'distance') {
        $value = round($distance_km, 2);
    } elseif ($metric === 'time') {
        $value = round($duration_hr * 60, 2);
    } elseif ($metric === 'pace') {
        if ($row['total_distance'] > 0) {
            // total_duration and total_distance are both summed
            // distance is in meters, duration in seconds
            // so this gives correct seconds per 500m:
            $pace_seconds = ($row['total_duration'] * 60 / $row['total_distance']) * 500;
            $value = round($pace_seconds); // round to nearest second
        } else {
            $value = null;
        }
    }

    $data[] = [
        "log_date" => $row['week_start'],
        "value" => $value
    ];
}

echo json_encode($data);
?>
