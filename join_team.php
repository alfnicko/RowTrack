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

$user_id = $_SESSION['user_id'];
$join_code = trim($_POST['join_code'] ?? '');
$confirm = ($_POST['confirm'] ?? '') === '1';
$needs_confirmation = false;
$team_name = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && $join_code) {
    $user_query = $conn->prepare("SELECT team_id FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_query->bind_result($current_team_id);
    $user_query->fetch();
    $user_query->close();

    $stmt = $conn->prepare("SELECT id, team_name FROM teams WHERE join_code = ?");
    $stmt->bind_param("s", $join_code);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($team_id, $team_name);
        $stmt->fetch();
        $stmt->close();

        if ($current_team_id && !$confirm) {
            $needs_confirmation = true;
        } else {
            $update = $conn->prepare("UPDATE users SET team_id = ? WHERE id = ?");
            $update->bind_param("ii", $team_id, $user_id);
            if ($update->execute()) {
                $_SESSION['success'] = "You have successfully joined " . htmlspecialchars($team_name) . "!";
                header("Location: athlete_dashboard.php");
                exit();
            } else {
                $error = "Error joining team. Please try again.";
            }
        }
    } else {
        $error = "Invalid join code. Please check and try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Team | RowTrack</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .join-container {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .join-form {
            margin-top: 1.5rem;
        }
        .code-input {
            letter-spacing: 0.5em;
            font-family: monospace;
            text-transform: uppercase;
        }
        .error {
            background: #ffe5e5;
            color: #a70000;
            padding: 1rem;
            border-left: 4px solid #ff4d4d;
            margin-bottom: 1rem;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-left: 4px solid #ffc107;
            margin-bottom: 1rem;
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
    
    <div class="join-container card">
        <h2><i class="fas fa-users"></i> Join a Team</h2>
        <p>Enter the join code provided by your coach</p>

        <?php if (isset($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($needs_confirmation): ?>
            <div class="warning">
                <i class="fas fa-exclamation-triangle"></i>
                You are already a member of another team. Join <strong><?= htmlspecialchars($team_name) ?></strong> instead?
            </div>
            <form method="POST" action="join_team.php" class="join-form">
                <input type="hidden" name="join_code" value="<?= htmlspecialchars($join_code) ?>">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Yes, Join New Team
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="join_team.php" class="join-form">
                <div class="form-group">
                    <label for="join_code">Team Join Code</label>
                    <input type="text" id="join_code" name="join_code"
                        class="form-control code-input" required autofocus
                        value="<?= htmlspecialchars($join_code) ?>">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-sign-in-alt"></i> Join Team
                    </button>
                    <a href="athlete_dashboard.php" class="btn btn-outline" style="flex: 1; text-align: center; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
            </form>
        <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
