<?php
// Save the action name in a cookie.
if (isset($_POST["DATA_action"])) {
    saveCookie("DATA_action", $_POST["DATA_action"]);
}
// Save the first name in a cookie.
if (isset($_POST["DATA_firstname"])) {
    saveCookie("DATA_firstname", $_POST["DATA_firstname"]);
}
// Save the last name in a cookie.
if (isset($_POST["DATA_lastname"])) {
    saveCookie("DATA_lastname", $_POST["DATA_lastname"]);
}
// Save the email in a cookie.
if (isset($_POST["DATA_email"])) {
    saveCookie("DATA_email", $_POST["DATA_email"]);
}
// Save the start date in a cookie.
if (isset($_POST["DATA_start"])) {
    saveCookie("DATA_start", $_POST["DATA_start"]);
}
// Save the end date in a cookie.
if (isset($_POST["DATA_end"])) {
    saveCookie("DATA_end", $_POST["DATA_end"]);
}
// Save the target value in a cookie.
if (isset($_POST["DATA_value"])) {
    saveCookie("DATA_value", $_POST["DATA_value"]);
}

// Store form errors.
$error = "";

// Check if the form and all required fields were submitted.
// isset() with several arguments only returns true if ALL of them are set.
if (isset(
        $_POST["BUTTON_submit"],
        $_POST["DATA_action"],
        $_POST["DATA_firstname"],
        $_POST["DATA_lastname"],
        $_POST["DATA_email"],
        $_POST["DATA_start"],
        $_POST["DATA_end"],
        $_POST["DATA_value"]
)) {
    // Store the submitted values.
    $action = $_POST["DATA_action"];
    $firstname = $_POST["DATA_firstname"];
    $lastname = $_POST["DATA_lastname"];
    $email = $_POST["DATA_email"];
    $start = $_POST["DATA_start"];
    $end = $_POST["DATA_end"];
    $value = $_POST["DATA_value"];

    // Get today and the latest allowed end date.
    $today = date('Y-m-d');
    // strtotime() adds 30 days to the start date, date() turns that back into a normal date string.
    $maxEndDate = date('Y-m-d', strtotime($start . ' +30 days'));

    // Check if the start date is valid.
    // Dates in "Y-m-d" format can be compared like normal strings.
    if($start < $today){
        $error = "Start date cannot be before today.";
        // Check if the end date is before the start date.
    } else if($end < $start){
        $error = "End date cannot be before start date.";
        // Check if the period is longer than 30 days.
    } else if($end > $maxEndDate){
        $error = "End date cannot be more than 30 days after the start date.";
    } else {
        // All date checks passed, now we save everything to the database.

        // Create a unique login code.
        // A random number plus the current time makes a code that is basically unique.
        $randomNumber = mt_rand(100, 100000) . time();

        // Open the database connection.
        $dbc = f_connect(false);

        // Escape text before using it in SQL.
        // This protects against SQL injection before the values go into the queries.
        $action = mysqli_real_escape_string($dbc, $action);
        $firstname = mysqli_real_escape_string($dbc, $firstname);
        $lastname = mysqli_real_escape_string($dbc, $lastname);
        $email = mysqli_real_escape_string($dbc, $email);
        $start = mysqli_real_escape_string($dbc, $start);
        $end = mysqli_real_escape_string($dbc, $end);
        $randomNumber = mysqli_real_escape_string($dbc, $randomNumber);

        // Convert the target value to a number.
        $value = (int) $value;

        // Search for an Elf with this email.
        $query = "SELECT pk_elf
                  FROM Elf
                  WHERE email = '$email'";

        // Prepare the SQL query.
        $statement = mysqli_prepare($dbc, $query);

        // Run the prepared query.
        mysqli_stmt_execute($statement);

        // Get the query result.
        $result = mysqli_stmt_get_result($statement);

        // Use the existing Elf if found.
        // mysqli_num_rows() > 0 means an Elf with this email already exists.
        if (mysqli_num_rows($result) > 0) {
            // Read the Elf data.
            // mysqli_fetch_assoc() gives the row as an array, like column name => value.
            $row = mysqli_fetch_assoc($result);
            $elfId = $row["pk_elf"];

            // Free the result and close the statement.
            mysqli_free_result($result);
            mysqli_stmt_close($statement);
        } else {
            // Free the empty result and close the statement.
            mysqli_free_result($result);
            mysqli_stmt_close($statement);

            // Add a new Elf to the database.
            $query = "INSERT INTO Elf
                      (firstName, lastname, email)
                      VALUES
                      ('$firstname', '$lastname', '$email')";

            // Prepare and run the insert query.
            $statement = mysqli_prepare($dbc, $query);
            mysqli_stmt_execute($statement);

            // Get the ID of the new Elf.
            // mysqli_insert_id() gives back the auto-increment ID of the row we just added.
            $elfId = mysqli_insert_id($dbc);

            // Close the statement.
            mysqli_stmt_close($statement);
        }

        // Save 1 if the wishlist is allowed.
        // The checkbox only shows up in $_POST if it was checked.
        if(isset($_POST["DATA_wishlist"])){
            $allowWishlist = 1;
        } else {
            // Save 0 if the checkbox was not selected.
            $allowWishlist = 0;
        }

        // Convert the Elf ID to a number.
        $elfId = (int) $elfId;

        // Add the new action to the database.
        $query = "INSERT INTO Action
                  (title, firstName, lastName, email, startDate, endDate,
                   targetValue, actionPassword, fk_elf_create, allowWishlist)
                  VALUES
                  ('$action', '$firstname', '$lastname', '$email',
                   '$start', '$end', $value, '$randomNumber', $elfId, $allowWishlist)";

        // Prepare and run the insert query.
        $statement = mysqli_prepare($dbc, $query);
        mysqli_stmt_execute($statement);

        // Close the statement and database.
        mysqli_stmt_close($statement);
        mysqli_close($dbc);

        // Email the login code to the Head Elf.
        $emailResult = emailSent(
                "Head Elf invitation",
                "
            <h2>Willkommen zum Wichteln</h2>
            <p>Du bist Head Elf, hier ist dein login Code: $randomNumber</p>
            ",
                $email
        );

        // Delete saved form cookies after success.
        // So old form data doesn't show up again next time the page is visited.
        deleteCookie("DATA_action");
        deleteCookie("DATA_firstname");
        deleteCookie("DATA_lastname");
        deleteCookie("DATA_email");
        deleteCookie("DATA_start");
        deleteCookie("DATA_end");
        deleteCookie("DATA_value");

        // Clear errors and show whether email delivery succeeded.
        $error = "";
        if ($emailResult === true) {
            $success = "✅ Action successfully created! Check your email for the login code.";
        } else {
            // Show the code to the creator as a fallback if SMTP isn't configured.
            $safeCode = htmlspecialchars($randomNumber, ENT_QUOTES, 'UTF-8');
            $success = "✅ Action created, but the email could not be sent. Your login code is: <strong>" . $safeCode . "</strong>. Configure SMTP to enable email delivery.";
        }
    }
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <!-- Set the page character encoding. -->
    <meta charset="UTF-8">
    <!-- Show the website authors. -->
    <meta name="author" content="Devin De Schepper/Manta-Ralea Alexia Stefania">
    <!-- Set the browser tab title. -->
    <title>Christmas Action</title>
</head>
<body class="promo-body">
<!-- Main content box. -->
<div class="promo-box">
    <!-- Page heading. -->
    <h2>🎁 Christmas Action 🎁</h2>
    <!-- Short page description. -->
    <p class="subtitle">
        Sign up for our festive campaign and spread holiday cheer!
    </p>

    <!-- Show an error message when one exists. -->
    <?php if($error !== ""){ ?>
        <p style="color:red;"><?= $error ?></p>
    <?php } ?>

    <!-- Show the success message after creation. -->
    <?php if(isset($success)){ ?>
        <p style="color:green;"><?= $success ?></p>
    <?php } ?>

    <!-- Send the form data with POST. -->
    <form method="POST" action="">
        <!-- Action name field. -->
        <div class="promo-group">
            <label for="id_action">Action Name:</label>
            <input
                    id="id_action"
                    name="DATA_action"
                    type="text"
                    value="<?= cookieVal("DATA_action") ?>"
                    required>
        </div>

        <!-- First name field. -->
        <div class="promo-group">
            <label for="id_firstname">First Name:</label>
            <input
                    id="id_firstname"
                    name="DATA_firstname"
                    type="text"
                    value="<?= cookieVal("DATA_firstname") ?>"
                    required>
        </div>

        <!-- Last name field. -->
        <div class="promo-group">
            <label for="id_lastname">Last Name:</label>
            <input
                    id="id_lastname"
                    name="DATA_lastname"
                    type="text"
                    value="<?= cookieVal("DATA_lastname") ?>"
                    required>
        </div>

        <!-- Email field. -->
        <div class="promo-group">
            <label for="id_email">Email Address:</label>
            <input
                    id="id_email"
                    name="DATA_email"
                    type="email"
                    value="<?= cookieVal("DATA_email") ?>"
                    required>
        </div>

        <!-- Start and end date fields. -->
        <div class="promo-group">
            <label>Action Period (max 30 days):</label>
            <label for="id_start">Start:</label>
            <input
                    id="id_start"
                    name="DATA_start"
                    type="date"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= cookieVal("DATA_start") ?>"
                    required>
            <label for="id_end">End:</label>
            <input
                    id="id_end"
                    name="DATA_end"
                    type="date"
                    value="<?= cookieVal("DATA_end") ?>"
                    required>
        </div>

        <!-- Wishlist permission checkbox. -->
        <div class="promo-group">
            <label for="id_wishlist">Allow Wishlist</label>
            <input id="id_wishlist" type="checkbox" name="DATA_wishlist" value="1" style="width: auto; margin-right: 8px;">
        </div>

        <!-- Target value field. -->
        <div class="promo-group">
            <label for="id_value">Target Value:</label>
            <input
                    id="id_value"
                    name="DATA_value"
                    type="number"
                    value="<?= cookieVal("DATA_value") ?>"
                    required>
        </div>

        <!-- Submit the form. -->
        <input
                type="submit"
                name="BUTTON_submit"
                value="Create Action"
                class="promo-submit">
    </form>

    <!-- Page footer. -->
    <div class="footer-text">
        © 2026 Christmas Campaign · All rights reserved
    </div>
</div>
</body>
</html>
