<?php
// Start the session.
session_start();

// Clear all roles on a fresh page visit so another role cannot leak into this login.
if (!isset($_POST["BUTTON_submit"])) {
    unset($_SESSION["HeadElf"]);
    unset($_SESSION["DATA_ADMIN"]);
    unset($_SESSION["DATA_ELF"]);
}

// Check if the login form was submitted.
if (isset($_POST["BUTTON_submit"], $_POST["DATA_username"], $_POST["DATA_password"])) {
    $username = trim((string)$_POST["DATA_username"]);
    $password = (string)$_POST["DATA_password"];

    // Connect to the database.
    $dbc = f_connect(false);
    if (!$dbc) {
        $error = "Database connection failed. Please check the server configuration.";
    } else {
        // Search for the Elf account.
        $statement = mysqli_prepare($dbc, "SELECT userName, password FROM sys_User WHERE userName = ? AND role = 'Elf'");
        mysqli_stmt_bind_param($statement, "s", $username);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);

        // Check if one account was found.
        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);

            // Compare the submitted password with the stored password hash.
            if (password_verify($password, $row["password"])) {
                $_SESSION["DATA_ELF"] = $row["userName"];
                mysqli_free_result($result);
                mysqli_stmt_close($statement);
                mysqli_close($dbc);
                header("Location: index.php?page=userDashboard");
                exit();
            }
        }

        $error = "Wrong username or password!";
        mysqli_free_result($result);
        mysqli_stmt_close($statement);
        mysqli_close($dbc);
    }
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <!-- Set the character format. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Set the page authors. -->
    <meta name="author" content="Devin De Schepper / Manta-Ralea Alexia Stefania">

    <!-- Set the browser tab title. -->
    <title>Elf Login</title>

    <!-- Load the existing stylesheet from its project folder. -->
    <link rel="stylesheet" href="styles/styles.css">
</head>
<body class="promo-body">
<!-- Elf login form. -->
<form method="POST" action="index.php?page=userLogin">
    <!-- Main login box. -->
    <div class="login-box">
        <!-- Elf icon. -->
        <div class="login-icon">🧝</div>

        <!-- Page title. -->
        <h2 class="login-title">Elf Login</h2>

        <?php if (isset($error)) { ?>
            <p style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php } ?>

        <!-- Username field. -->
        <div class="login-input-group">
            <input id="login-login"
                   name="DATA_username"
                   placeholder="Username:"
                   type="text"
                   required>
        </div>

        <!-- Password field. -->
        <div class="login-input-group">
            <input id="login-password"
                   name="DATA_password"
                   placeholder="Password:"
                   type="password"
                   required>
        </div>

        <!-- Submit the login form. -->
        <input type="submit"
               name="BUTTON_submit"
               class="login-button"
               value="Login 🎁">

        <!-- Footer message. -->
        <div class="login-footer-text">
            ❄ Merry Christmas &amp; Happy Holidays ❄
        </div>
    </div>
</form>
</body>
</html>
