<?php
session_start();
if(!isset($_POST["BUTTON_submit"])){
    unset($_SESSION["HeadElf"]);
    unset($_SESSION["DATA_ADMIN"]);
    unset($_SESSION["DATA_ELF"]);
}
if(isset($_POST["BUTTON_submit"], $_POST["DATA_username"], $_POST["DATA_password"])){
    $username = $_POST["DATA_username"];
    $password = $_POST["DATA_password"];
    $dbc = f_connect(false);
    $usernameDB = mysqli_real_escape_string($dbc, $username);
    $query = "SELECT userName, password FROM sys_User WHERE userName = '$usernameDB' AND role = 'Elf'";
    $statement = mysqli_prepare($dbc, $query);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    if(mysqli_num_rows($result) === 1){
        $row = mysqli_fetch_assoc($result);
        if(password_verify($password, $row["password"])){
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
?>
<!doctype html><html lang="en" dir="ltr"><head><meta charset="UTF-8"><meta name="author" content="Devin De Schepper / Manta-Ralea Alexia Stefania"><title>Elf Login</title><link rel="stylesheet" href="styles/styles.css"></head><body class="promo-body">
<form method="POST" action="index.php?page=userLogin"><div class="login-box"><div class="login-icon">🧝</div><h2 class="login-title">Elf Login</h2>
<?php if(isset($error)){ echo "<p style='color:red;'>$error</p>"; } ?>
<div class="login-input-group"><input id="login-login" name="DATA_username" placeholder="Username:" type="text" required></div>
<div class="login-input-group"><input id="login-password" name="DATA_password" placeholder="Password:" type="password" required></div>
<input type="submit" name="BUTTON_submit" class="login-button" value="Login 🎁"><div class="login-footer-text">❄ Merry Christmas &amp; Happy Holidays ❄</div></div></form></body></html>