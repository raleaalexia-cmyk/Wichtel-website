<?php
// Enable PHP errors when needed.
/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
*/

// Start the session.
session_start();

// Connect to the database.
$dbcSetup = f_connect(false);

// Search for an Admin account.
$checkAdmin = mysqli_query($dbcSetup, "SELECT * FROM sys_User WHERE role = 'Admin'");

// Create a default Admin if none exists.
// This makes sure the site always has at least one working Admin login.
if(mysqli_num_rows($checkAdmin) === 0){
    // Encrypt the default password.
    $hashedDefault = password_hash("admin", PASSWORD_DEFAULT);

    // Prepare the Admin insert.
    $setupStatement = mysqli_prepare($dbcSetup, "INSERT INTO sys_User (userName, password, role) VALUES ('admin', ?, 'Admin')");

    // Add the encrypted password.
    mysqli_stmt_bind_param($setupStatement, "s", $hashedDefault);

    // Create the Admin account.
    mysqli_stmt_execute($setupStatement);

    // Close the statement.
    mysqli_stmt_close($setupStatement);
}

// Close the setup connection.
mysqli_close($dbcSetup);

// Check if the login form was sent.
if (isset(
        $_POST["BUTTON_submit"],
        $_POST["DATA_email"],
        $_POST["DATA_code"]
)) {
    // Store the submitted login values.
    $email = $_POST["DATA_email"];
    $code = $_POST["DATA_code"];

    // Store the page to open after login.
    // Stays empty as long as no login attempt below succeeds.
    $redirectPage = "";

    // Connect to the database.
    $dbc = f_connect(false);

    // Escape the login values.
    $emailDB = mysqli_real_escape_string($dbc, $email);
    $codeDB = mysqli_real_escape_string($dbc, $code);

    // Search for a matching Head Elf.
    // Head Elves log in with their email + the action's password, not a normal account.
    $query = "SELECT actionPassword
              FROM Action
              WHERE email = '$emailDB'
              AND actionPassword = '$codeDB'";

    // Prepare the query.
    $statement = mysqli_prepare($dbc, $query);

    // Run the query.
    mysqli_stmt_execute($statement);

    // Get the result.
    $result = mysqli_stmt_get_result($statement);

    // Check if the Head Elf was found.
    if (mysqli_num_rows($result) > 0) {
        // Read the Head Elf data.
        $row = mysqli_fetch_assoc($result);

        // Save the login in the session.
        $_SESSION["HeadElf"] = $row["actionPassword"];

        // Open the invitation page.
        $redirectPage = "index.php?page=invite";
    } else {
        // Free the Head Elf result.
        mysqli_free_result($result);

        // Close the Head Elf statement.
        mysqli_stmt_close($statement);

        // Use the email field as username.
        // This lets the same input field work for both Head Elf email and Admin/Elf username.
        $usernameDB = mysqli_real_escape_string($dbc, $email);

        // Search for an Admin account.
        $query = "SELECT userName, password
                  FROM sys_User
                  WHERE userName = '$usernameDB'
                  AND role = 'Admin'";

        // Prepare the Admin query.
        $statement = mysqli_prepare($dbc, $query);

        // Run the Admin query.
        mysqli_stmt_execute($statement);

        // Get the Admin result.
        $result = mysqli_stmt_get_result($statement);

        // Check if one Admin was found.
        if (mysqli_num_rows($result) === 1) {
            // Read the Admin data.
            $row = mysqli_fetch_assoc($result);

            // Check the entered password.
            // password_verify() compares the typed code against the stored hash.
            if(password_verify($code, $row["password"])){
                // Save the Admin login.
                $_SESSION["DATA_ADMIN"] = $row["userName"];

                // Open the Admin page.
                $redirectPage = "index.php?page=admin_overview";
            }
        }

        // Try Elf login if Admin failed.
        if($redirectPage === ""){
            // Free the Admin result.
            mysqli_free_result($result);

            // Close the Admin statement.
            mysqli_stmt_close($statement);

            // Search for an Elf account.
            $query = "SELECT userName, password
                      FROM sys_User
                      WHERE userName = '$usernameDB'
                      AND role = 'Elf'";

            // Prepare the Elf query.
            $statement = mysqli_prepare($dbc, $query);

            // Run the Elf query.
            mysqli_stmt_execute($statement);

            // Get the Elf result.
            $result = mysqli_stmt_get_result($statement);

            // Check if one Elf was found.
            if (mysqli_num_rows($result) === 1) {
                // Read the Elf data.
                $row = mysqli_fetch_assoc($result);

                // Check the entered password.
                if(password_verify($code, $row["password"])){
                    // Save the Elf login.
                    $_SESSION["DATA_ELF"] = $row["userName"];

                    // Open the Elf dashboard.
                    $redirectPage = "index.php?page=userDashboard";
                }
            }
        }
    }

    // Continue when login succeeded.
    // $redirectPage only has a value if one of the three login checks above worked.
    if ($redirectPage !== "") {
        // Remember the submitted email.
        saveCookie("COOKIE_email", $email);

        // Free the result.
        mysqli_free_result($result);

        // Close the statement.
        mysqli_stmt_close($statement);

        // Close the database.
        mysqli_close($dbc);

        // Open the correct page.
        header("Location: $redirectPage");

        // Stop the script.
        exit();
    }

    // Create the login error.
    // Reached only if none of the login checks matched.
    $error = "Wrong email, username or code!";

    // Free the result.
    mysqli_free_result($result);

    // Close the statement.
    mysqli_stmt_close($statement);

    // Close the database.
    mysqli_close($dbc);
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <!-- Set the character format. -->
    <meta charset="UTF-8">

    <!-- Set the page authors. -->
    <meta name="author"
          content="Devin De Schepper / Manta-Ralea Alexia Stefania">

    <!-- Set the browser tab title. -->
    <title>Christmas Login</title>

    <!-- Load the CSS file. -->
    <link rel="stylesheet" href="styles/styles.css">
</head>
<body class="promo-body">
<!-- Send the login values with POST. -->
<form method="POST" action="index.php?page=login">
    <!-- Main login box. -->
    <div class="login-box">
        <!-- Login icon. -->
        <div class="login-icon">🎄</div>

        <!-- Page title. -->
        <h2 class="login-title">Christmas Login</h2>

        <?php
        // Show the error if login failed.
        if (isset($error)) {
            echo "<p style='color:red;'>$error</p>";
        }
        ?>

        <!-- Email or username field. -->
        <div class="login-input-group">
            <input id="login-login"
                   name="DATA_email"
                   placeholder="Email:"
                   type="text"
                   value="<?= cookieVal("COOKIE_email") ?>"
                   required>
        </div>

        <!-- Password or code field. -->
        <div class="login-input-group">
            <input id="login-password"
                   name="DATA_code"
                   placeholder="Code:"
                   type="password"
                   required>
        </div>

        <!-- Submit the login form. -->
        <input type="submit"
               name="BUTTON_submit"
               class="login-button"
               value="Login 🎁">

        <!-- Login footer message. -->
        <div class="login-footer-text">
            ❄ Merry Christmas &amp; Happy Holidays ❄
        </div>
    </div>
</form>
</body>
</html>
