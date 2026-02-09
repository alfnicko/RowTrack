<?php
session_start();
include '../db.php';

// Ensuring that only athletes access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'athlete') {
    header("Location: ../login.php");
    exit();
}

$client_id = "152151";
$redirect_uri = "http://localhost/row-track-v2/strava/strava_callback.php"; 
$scope = "read,activity:read,activity:read_all,activity:write";
// If athlete hasnt connected an account yet, generate the Strava OAuth URL that requests access to their activities
$auth_url = "https://www.strava.com/oauth/authorize?client_id=$client_id&redirect_uri=$redirect_uri&response_type=code&scope=$scope";
// IF there is not access token stored in the session, a connect strava page/button is displayed in order to do so
if (!isset($_SESSION['strava_access_token'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Athlete Dashboard</title>
        <link rel="stylesheet" href="../style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        <div class="card" style="text-align:center;padding:2rem;margin-top:2rem;">
            <a href="<?php echo $auth_url ?>" class="btn btn-primary" style="padding:1rem 2rem;font-size:1.1rem;">
                <i class="fab fa-strava"></i> Connect Strava Account
            </a>
        </div>
        <div style="margin-top:2rem; text-align:center;">
            <a href="../athlete/athlete_dashboard.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to dashboard
            </a>
        </div>
    </main>
    </body>
    </html>
    <?php
    exit();
}
// Here we use the stored access token to getch activities from the Strava API
$access_token = $_SESSION['strava_access_token'];
$url = "https://www.strava.com/api/v3/athlete/activities";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$activities = json_decode($response, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Athlete Dashboard</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .strava-activities-form {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
        }
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
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
    <div class="strava-activities-form">
        <h2><i class="fab fa-strava"></i> Select Strava Activities</h2>
        <form method="POST" action="upload_strava_session.php">
            <?php
            // Filtering and displaying only rowing activities
            foreach ($activities as $activity) {
                if ($activity['sport_type'] === 'Rowing') {
                    $spm = isset($activity['average_cadence']) ? round($activity['average_cadence']) : 'N/A';
                    $hr = isset($activity['average_heartrate']) ? round($activity['average_heartrate']) : 'N/A';
                    ?>
                    <div class="activity-item">
                        <!-- Athletes can tick checkboxes to pick sessions they eant to upload -->
                        <input type="checkbox" name="activities[]" value="<?php echo $activity['id'] ?>"> 
                        <?php echo $activity['name'] ?> - 
                        Distance: <?php echo round($activity['distance']/1000, 2) ?>km, 
                        Time: <?php echo gmdate("H:i:s", $activity['moving_time']) ?>, 
                        SPM: <?php echo $spm ?>, 
                        Avg HR: <?php echo $hr ?>
                    </div>
                    <?php
                }
            }
            ?>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-upload"></i> Upload Selected
                </button>
                <a href="../athlete/athlete_dashboard.php" class="btn btn-outline" style="flex: 1; text-align: center; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-times"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</main>
</body>
</html>