<?php

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Proceed with the deletion logic
} else {
    // Display a confirmation dialogue
    echo "<script>
        if (confirm('Are you sure you want to delete this team? Once deleted, all data will be lost.')) {
            window.location.href = '?team_id=" . intval($_GET['team_id']) . "&confirm=yes';
        } else {
            window.location.href = 'coach_dashboard.php';
        }
    </script>";
    exit;
}
// delete_team.php

session_start();
require_once '../db.php'; // Include your database connection file

// Check if the user is logged in and is a coach
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
// Check if a team ID is provided
if (isset($_GET['team_id'])) {
    $team_id = intval($_GET['team_id']);
    $coach_id = $_SESSION['user_id'];

    // Verify that the team belongs to the logged-in coach
    $query = "SELECT id FROM teams WHERE id = ? AND coach_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $team_id, $coach_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Delete the team
        $delete_query = "DELETE FROM teams WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('i', $team_id);

        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Team deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete the team. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "You are not authorized to delete this team.";
    }

    $stmt->close();
    $delete_stmt->close();
} else {
    $_SESSION['error_message'] = "No team ID provided.";
}

// Redirect back to the teams page
header('Location: coach_dashboard.php');
exit;
?>