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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $team_id = $_POST['team_id'];
    $session_date = $_POST['session_date'];
    $activity = trim($_POST['activity']);
    $details = trim($_POST['details']);

    // Insert training session into session_logs
    $query = "INSERT INTO session_logs (team_id, date, activity, details) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $team_id, $session_date, $activity, $details);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Training session uploaded successfully!";
        header("Location: coach_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Error uploading training session.";
    }
}

header("Location: coach_dashboard.php");
exit();
?>
