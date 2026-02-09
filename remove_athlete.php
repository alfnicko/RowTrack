<?php
session_start();
require '../db.php';

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

$athlete_id = $_GET['athlete_id'];
$team_id = $_GET['team_id'];

// Verify the coach owns this team
$stmt = $conn->prepare("SELECT id FROM teams WHERE id = ? AND coach_id = ?");
$stmt->bind_param("ii", $team_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['error'] = "Unauthorized action";
    header("Location: coach_dashboard.php");
    exit();
}

// Remove athlete from team
$stmt = $conn->prepare("UPDATE users SET team_id = NULL WHERE id = ? AND role = 'athlete'");
$stmt->bind_param("i", $athlete_id);
$stmt->execute();

if ($stmt->affected_rows === 1) {
    $_SESSION['message'] = "Athlete removed from team successfully";
} else {
    $_SESSION['error'] = "Failed to remove athlete";
}

header("Location: coach_dashboard.php");
exit();
?>