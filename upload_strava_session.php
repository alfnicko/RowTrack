<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'athlete') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['strava_access_token'])) {
    die("You must connect to Strava first.");
}

if (!isset($_POST['activities']) || empty($_POST['activities'])) {
    die("No activities selected.");
}

$access_token = $_SESSION['strava_access_token'];
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'] ?? null;
$dummy_session_id = 1;

foreach ($_POST['activities'] as $activity_id) {
    // Fetch activity details from Strava
    $url = "https://www.strava.com/api/v3/activities/$activity_id";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $activity = json_decode($response, true);

    // Skip if invalid or not a rowing session
    if (!isset($activity['id']) || $activity['sport_type'] !== 'Rowing') {
        echo "Skipping invalid or non-rowing activity.<br>";
        continue;
    }

    $activity_name = $activity['name'] ?? 'Unnamed Activity';
    $distance = $activity['distance'] ?? 0;
    $duration_seconds = $activity['moving_time'] ?? 0;
    $duration_minutes = round($duration_seconds / 60, 2);
    $formatted_time = sprintf("%02d:%02d", floor($duration_seconds / 60), $duration_seconds % 60);

    // Check for duplicates
    $check_query = "SELECT id FROM logged_sessions WHERE athlete_id = ? AND strava_activity_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $user_id, $activity['id']);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        echo "Already uploaded: " . htmlspecialchars($activity_name) .
             " (" . round($distance / 1000, 2) . " km, " . $formatted_time . ")<br>";
        $check_stmt->close();
        continue;
    }
    $check_stmt->close();

    // Extract more data
    $spm = isset($activity['average_cadence']) ? round($activity['average_cadence']) : null;
    $avg_hr = isset($activity['average_heartrate']) ? round($activity['average_heartrate']) : null;
    $max_hr = isset($activity['max_heartrate']) ? round($activity['max_heartrate']) : null;

    $start_date = $activity['start_date'] ?? date('Y-m-d H:i:s');
    $log_date = date('Y-m-d', strtotime($start_date));

    // Save to database
    $query = "INSERT INTO logged_sessions 
              (athlete_id, session_id, distance, duration, log_date, team_id, activity_name, stroke_rate, heart_rate, strava_activity_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Error preparing query: " . $conn->error);
    }

    $stmt->bind_param(
        "iiddsssiii",
        $user_id,
        $dummy_session_id,
        $distance,
        $duration_minutes,
        $log_date,
        $team_id,
        $activity_name,
        $spm,
        $avg_hr,
        $activity['id']
    );

    if ($stmt->execute()) {
        echo "Uploaded: " . htmlspecialchars($activity_name) .
             " (" . round($distance / 1000, 2) . " km, " . $formatted_time . "), " .
             "SPM: " . ($spm ?? 'N/A') . ", " .
             "Avg HR: " . ($avg_hr ?? 'N/A') . "<br>";
    } else {
        echo "Error: " . $stmt->error . "<br>";
    }

    $stmt->close();
}

echo "<a href='../athlete/athlete_dashboard.php'>Back to dashboard</a>";
?>