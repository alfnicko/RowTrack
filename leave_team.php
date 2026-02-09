<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$query = $conn->prepare("UPDATE users SET team_id = NULL WHERE id = ?");
$query->bind_param("i", $user_id);

if ($query->execute()) {
    $_SESSION['message'] = "You have successfully left the team.";
} else {
    $_SESSION['message'] = "Error: Unable to leave the team.";
}

header("Location: athlete_dashboard.php");
exit();
?>
