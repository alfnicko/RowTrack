<?php

// Session, authentication, and database connection
session_start();
include '../db.php';
// If user ID not set in session, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// If user role isn't coach, redirect to appropriate dashboard
if ($_SESSION['role'] === 'athlete') {
    header("Location: ../athlete/athlete_dashboard.php");
    exit();
}

// Coach data initialisation
$user_id = $_SESSION['user_id']; // Current coaches ID
$username = $_SESSION['username']; // Getting for display
$current_date = date("Y-m-d"); // Used for filtering training plans

// Team data retrieval
// Query to get all teams associated with the coach
// This is done by using the coaches ID
$query = "SELECT id as team_id, team_name, join_code FROM teams WHERE coach_id = ?";
$stmt = $conn->prepare($query); // Prepare the statement to prevent SQL injection
$stmt->bind_param("i", $user_id); // Bing the coaches user ID to the query 
$stmt->execute();
$teams_result = $stmt->get_result(); //Result set for team data
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Standard meta tags for character set and viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Title of the page -->
    <title>Coach Dashboard | RowTrack</title>
    <!-- Link to external CSS for styling & Pulling in awesome font-->
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Custom styles for the coach dashboard */
        .athlete-management {
            margin-top: 2rem;
            border-top: 1px solid #eee;
            padding-top: 1.5rem;
        }
        
        .athlete-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--border-radius);
        }
        
        .athlete-header:hover {
            background-color: #f5f5f5;
        }
        
        .athlete-list-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .athlete-list-container.expanded {
            max-height: 500px;
            margin-top: 1rem;
        }
        
        .search-athletes {
            margin-bottom: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .search-athletes input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
        }
        
        .athlete-list {
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            max-height: 300px;
            overflow-y: auto;
        }
        
        .athlete-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .athlete-item:last-child {
            border-bottom: none;
        }
        
        .athlete-item:hover {
            background-color: #f9f9f9;
        }
        
        .athlete-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .remove-athlete {
            color:rgb(255, 255, 255);
            cursor: pointer;
        }
    </style>
</head>
<body>
     <!-- 
    Header section that features logo, user information, and a logout link(easy access, no confusion)
    -->
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
<!-- Main content area for the dashboard -->
<main class="container">
    <div class="dashboard-container">
        <!-- Dashboard Header with greeting message for user, still using the variables defined initially-->
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Coach Dashboard</h1>
        <div class="welcome-message">
            <h2>Welcome back, <?php echo htmlspecialchars($username); ?></h2>
        </div>
<!-- Section for displaying team and their training plans -->
            <section class="teams-section">
        
                <?php if ($teams_result->num_rows > 0): ?>
                    <div class="teams-grid">
                        <?php while ($team = $teams_result->fetch_assoc()): 
                            $team_id = $team['team_id']; ?>
                            <div class="team-box card">
                                <div class="team-header">
                                    <h4><?php echo htmlspecialchars($team['team_name']); ?></h4>
                                    <span class="join-code">Join Code: <?php echo htmlspecialchars($team['join_code']); ?></span>
                                </div>
                                
                                <?php
                                /**
                                 * Training plan display section
                                 * Fetches training plans for the current team
                                 * Organised by month and week
                                 * Allows coaches to view and edit training plans
                                 */
                                $training_plans_query = "SELECT * FROM training_plans WHERE team_id = ? ORDER BY week_start_date ASC";
                                $training_plans_stmt = $conn->prepare($training_plans_query);
                                $training_plans_stmt->bind_param("i", $team_id);
                                $training_plans_stmt->execute();
                                $training_plans_result = $training_plans_stmt->get_result();
                                
                                if ($training_plans_result->num_rows > 0): ?>
                                    <div class="training-plans-accordion">
                                        <h5><i class="fas fa-calendar-alt"></i> Training Plans</h5>
                                        <?php
                                        // Group by month for better organisation
                                        // Create an array to hold plans grouped by month
                                        $plans_by_month = [];
                                        while ($plan = $training_plans_result->fetch_assoc()) {
                                            $month_year = date("Y-m", strtotime($plan['week_start_date']));
                                            if (!isset($plans_by_month[$month_year])) {
                                                $plans_by_month[$month_year] = [];
                                            }
                                            $plans_by_month[$month_year][] = $plan;
                                        }
                                        // Display plans in an accordion format                     
                                        foreach ($plans_by_month as $month_year => $plans): ?>
                                            <details class="month-plan">
                                                <summary><?php echo date("F Y", strtotime($month_year)); ?></summary>
                                                <ul class="plan-list">
                                                    <?php foreach ($plans as $plan): ?>
                                                        <li>
                                                            <span>Week of <?php echo htmlspecialchars($plan['week_start_date']); ?></span>
                                                            <a href="edit_training_plan.php?id=<?php echo $plan['id']; ?>" class="btn btn-outline">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-plans">No training plans created yet.</p>
                                <?php endif; ?>
                                <!-- Athlete management section -->
                                <div class="athlete-management">
                                    <div class="athlete-header" onclick="toggleAthleteList(this)">
                                        <h5><i class="fas fa-user-friends"></i> Team Athletes</h5>
                                        <?php
                                        // Get athlete count for badge display
                                        // Count athletes in this team
                                        $count_stmt = $conn->prepare("SELECT COUNT(*) as athlete_count FROM users 
                                                                     WHERE team_id = ? AND role = 'athlete'");
                                        $count_stmt->bind_param("i", $team_id);
                                        $count_stmt->execute();
                                        $count_result = $count_stmt->get_result();
                                        $athlete_count = $count_result->fetch_assoc()['athlete_count'];
                                        ?>
                                        <span class="badge"><?php echo $athlete_count; ?> athletes</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <!-- Funcitonality for searching athletes in the team -->
                                    <!-- This is simply a collapsable list of athletes in the team, with search functionality -->
                                    <div class="athlete-list-container">
                                        <div class="search-athletes">
                                            <input type="text" placeholder="Search athletes..." oninput="filterAthletes(this)">
                                            <button class="btn btn-outline">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        
                                        <?php
                                        // Get all athletes in this team
                                        $athletes_query = "SELECT u.id, u.username, u.email FROM users u 
                                                          WHERE u.team_id = ? AND u.role = 'athlete'
                                                          ORDER BY u.username ASC";
                                        $athletes_stmt = $conn->prepare($athletes_query);
                                        $athletes_stmt->bind_param("i", $team_id);
                                        $athletes_stmt->execute();
                                        $athletes_result = $athletes_stmt->get_result();
                                        ?>
                                        <!-- Athlete list container -->
                                        <div class="athlete-list">
                                            <?php if ($athletes_result->num_rows > 0): ?>
                                                <?php while ($athlete = $athletes_result->fetch_assoc()): ?>
                                                    <div class="athlete-item" data-search="<?php echo strtolower(htmlspecialchars($athlete['username'] . ' ' . $athlete['email'])); ?>">
                                                        <div class="athlete-info">
                                                            <i class="fas fa-user"></i>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($athlete['username']); ?></strong>
                                                                <div class="athlete-email"><?php echo htmlspecialchars($athlete['email']); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="athlete-actions">
                                                            <!-- Take the athlete id and add to the href to go straight to relevant athlete -->
                                                            <a href="view_training_log.php?athlete_id=<?php echo $athlete['id']; ?>" 
                                                               class="btn btn-outline btn-sm">
                                                               <i class="fas fa-clipboard-list"></i> View Logs
                                                            </a>
                                                            <a href="remove_athlete.php?athlete_id=<?php echo $athlete['id']; ?>&team_id=<?php echo $team_id; ?>" 
                                                               class="btn btn-danger remove-athlete"
                                                               onclick="return confirm('Are you sure you want to remove this athlete from the team?');">
                                                               <i class="fas fa-user-minus"></i> Remove
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <div class="no-athletes">
                                                    <i class="fas fa-user-slash"></i>
                                                    <p>No athletes in this team yet.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- Action buttons for the team -->
                                <div class="team-actions">
                                    <a href="create_training_plan.php?team_id=<?php echo $team_id; ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Add Plan</a>
                                    <a href="delete_team.php?team_id=<?php echo $team_id; ?>" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Team</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div>
                        <i class="fas fa-users-slash"></i>
                        <p>You haven't created any teams yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        <!-- Action links for creating new teams, viewing training logs, and setting goals -->
            <div class="action-nav">
                <a href="create_team.php" class="action-link"><i class="fas fa-users"></i> Create New Team</a>
                <a href="view_training_log.php" class="action-link"><i class="fas fa-clipboard-list"></i> Leave Feedback for athletes</a>
                <a href="coach_set_goal.php" class="action-link"><i class="fas fa-bullseye"></i> Set Team Goals </a>
                <a href="coach_view_performance.php" class="action-link"><i class="fas fa-chart-bar"></i> View Athlete Performance</a>
            </div>
        </div>
    </main>
    <script>
        /**
         * Simple function to toggle the visibility of the athlete list when the header is clicked.
         * This function also changes the icon from down to up arrow and vice versa.
         */
        function toggleAthleteList(header) {
            // Get the container element right after the header
            const container = header.nextElementSibling;
            // Find the chevron icon inside the header
            const icon = header.querySelector('i.fa-chevron-down');
            
            // Toggle the expanded class on the container to show/hide the list
            container.classList.toggle('expanded');
            icon.classList.toggle('fa-chevron-down');
            // Swithcing between up and down chevrons 
            icon.classList.toggle('fa-chevron-up');
        }
        /**
         * Function to filter athletes based on the search input.
         * This function hides athletes that do not match the search term.
         */
        function filterAthletes(input) {
            // Get the search term and convert to lowercase for case-insensitive search
            const searchTerm = input.value.toLowerCase();
            // Find the athlete list container and all athlete items within it
            const athleteList = input.closest('.athlete-list-container').querySelector('.athlete-list');
            // Get all athlete items in the list
            const athletes = athleteList.querySelectorAll('.athlete-item');
            
            // Loop through each athlete item and check if it matches the search term
            athletes.forEach(athlete => {
                // Get the text from the data-search attribute
                const searchData = athlete.getAttribute('data-search');
                // Check if the search term is included in the data-search attribute
                if (searchData.includes(searchTerm)) {
                    athlete.style.display = 'flex';
                } else {
                    athlete.style.display = 'none';
                }
            });
        }
        // Wait for the DOM to load before executing the script
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there is a hash in the URL (e.g., #teamId)
            if (window.location.hash) {
                // Extract the team ID from the hash and find the corresponding header
                const teamId = window.location.hash.substring(1);
                const header = document.querySelector(`[data-team="${teamId}"] .athlete-header`);
                if (header) {
                    toggleAthleteList(header);
                }
            }
        });
    </script>
</body>
</html>