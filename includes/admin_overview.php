<?php
// Start or continue the session.
session_start();

// Check if logout was clicked.
if (isset($_POST["logout"])) {
    // Remove all session values.
    session_unset();
    // Delete the session.
    session_destroy();
    // Return to the login page.
    header("Location: index.php?page=login");
    // Stop the script.
    exit();
}

// Check if the admin is logged in.
// If there's no "DATA_ADMIN" in the session, the user never logged in.
if (!isset($_SESSION["DATA_ADMIN"])) {
    // Send unauthorized users to login.
    header("Location: index.php?page=login");
    exit();
}

// Connect to the database.
$dbc = f_connect(false);

/* DELETE AN ACTION */
// Check if an action should be deleted.
if (isset($_POST["deleteAction"])) {
    // Convert the action ID to a number, so we don't insert raw text into the query.
    $actionId = (int) $_POST["deleteAction"];

    // Continue only with a valid ID.
    if ($actionId > 0) {
        // Delete the selected action.
        $query = "DELETE FROM Action
                  WHERE pk_action = ?";

        // Prepare the delete query.
        $statement = mysqli_prepare($dbc, $query);

        // Add the ID to the placeholder.
        // "i" means the bound value is an integer.
        mysqli_stmt_bind_param(
                $statement,
                "i",
                $actionId
        );

        // Run the delete query.
        mysqli_stmt_execute($statement);

        // Close the statement.
        mysqli_stmt_close($statement);
    }

    // Close the database connection.
    mysqli_close($dbc);

    // Reload the admin page.
    header("Location: index.php?page=admin_overview");
    exit();
}

/* SEND A MESSAGE */
// Check if the contact form was submitted.
if (isset($_POST["BUTTON_send"], $_POST["DATA_developer"], $_POST["DATA_message"])) {
    // Store the selected developer.
    $developer = $_POST["DATA_developer"];
    // Store the written message.
    $message = $_POST["DATA_message"];

    // Email the message to the developer.
    // htmlspecialchars() turns special characters like < and > into safe text, so the message can't break the HTML.
    emailSent("Website Contact Message", "
        <h2>📩 New Message from Admin</h2>
        <p>" . htmlspecialchars($message) . "</p>
    ", $developer);

    // Mark the message as sent.
    $sent = true;
}

/* GET ALL ACTIONS */
// Select the needed action data.
$query = "SELECT pk_action,
                 title,
                 firstName,
                 lastName,
                 startDate,
                 endDate,
                 targetValue,
                 actionStatus
          FROM Action
          ORDER BY startDate DESC";

// Prepare the select query.
$statement = mysqli_prepare($dbc, $query);

// Run the select query.
mysqli_stmt_execute($statement);

// Get the query result.
$result = mysqli_stmt_get_result($statement);

// Create an empty actions array.
$actions = array();

// Add each action to the array.
// mysqli_fetch_assoc() reads one row at a time as an array, and the loop stops when there are no more rows.
while ($row = mysqli_fetch_assoc($result)) {
    $actions[] = $row;
}

// Free the stored result.
mysqli_free_result($result);

// Close the statement.
mysqli_stmt_close($statement);

// Close the database connection.
mysqli_close($dbc);
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <!-- Set the character format. -->
    <meta charset="UTF-8">
    <!-- Set the page authors. -->
    <meta name="author"
          content="Devin De Schepper / Manta-Ralea Alexia Stefania">
    <!-- Make the page responsive. -->
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <!-- Set the browser tab title. -->
    <title>Admin Overview</title>
    <!-- Load the CSS file. -->
    <link rel="stylesheet" href="styles/styles.css">
</head>
<body class="promo-body admin-overview-page">
<!-- Main admin content. -->
<main class="promo-box admin-container">
    <!-- Admin header section. -->
    <section class="admin-header">
        <!-- Admin icon. -->
        <div class="login-icon">
            🎅
        </div>

        <!-- Admin title and information. -->
        <div class="admin-header-text">
            <!-- Page title. -->
            <h1 class="login-title">
                Admin Overview
            </h1>

            <!-- Show the logged-in admin. -->
            <p>
                Welcome,
                <strong>
                    <?php
                    // Display the admin safely.
                    // htmlspecialchars() escapes the value so it can't be used to inject HTML/JS.
                    echo htmlspecialchars(
                            $_SESSION["DATA_ADMIN"]
                    );
                    ?>
                </strong>
            </p>

            <!-- Login confirmation. -->
            <p class="admin-message">
                You are successfully logged in as an administrator.
            </p>
        </div>

        <!-- Admin navigation buttons. -->
        <div class="admin-buttons">
            <!-- Link to the dashboard. -->
            <a href="index.php?page=dashboard"
               class="dashboard-button">
                Dashboard
            </a>

            <!-- Logout form. -->
            <form method="POST">
                <!-- Submit the logout request. -->
                <button type="submit"
                        name="logout"
                        class="logout-button">
                    Logout
                </button>
            </form>
        </div>
    </section>

    <!-- Database and contact section. -->
    <section class="actions-overview">
        <!-- Section heading. -->
        <div class="overview-title">
            <div>
                <h2>
                    Database Administration
                </h2>
                <p>
                    Manage the website database through your hosting provider's database administration panel.
                </p>
            </div>
        </div>

        <!-- Developer contact area. -->
        <div class="admin-message">
            <!-- Contact instructions. -->
            <p>
                If you have questions for the developers of this website,
                choose a recipient below and send them a message.
            </p>

            <!-- Send the contact form with POST. -->
            <form method="POST" action="">
                <!-- Developer selection. -->
                <div class="promo-group">
                    <label for="id_developer">Send to:</label>
                    <select id="id_developer" name="DATA_developer" required>
                        <option value="manal284@school.lu">Alexia (manal284@school.lu)</option>
                        <option value="desde661@school.lu">Devin (desde661@school.lu)</option>
                    </select>
                </div>

                <!-- Message field. -->
                <div class="promo-group">
                    <label for="id_message">Your Message:</label>
                    <textarea id="id_message" name="DATA_message" rows="5" style="width:100%;" required></textarea>
                </div>

                <!-- Send the message. -->
                <input type="submit" name="BUTTON_send" value="Send Message" class="promo-submit">
            </form>

            <!-- Show confirmation after sending. -->
            <?php if(isset($sent)){ ?>
                <p style="color:green;">✅ Your message has been sent!</p>
            <?php } ?>
        </div>
    </section>
</main>
</body>
</html>
