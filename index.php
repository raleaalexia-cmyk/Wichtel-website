<?php
// Buffer output so included pages can safely start sessions and send redirects.
ob_start();
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <!-- Set the character format. -->
    <meta charset="UTF-8">
    <!-- Set the page authors. -->
    <meta name="author" content="Devin De Schepper/Manta-Ralea Alexia Stefania">
    <!-- Set the browser tab title. -->
    <title>Index</title>
    <!-- Load the main CSS file. -->
    <link rel="stylesheet" href="styles/styles.css">
</head>
<body>
<?php
// Load PHPMailer through Composer.
require_once __DIR__ . '/vendor/autoload.php';

// Load the navigation menu.
include_once __DIR__ . "/includes/nav.php";

// Store the selected page.
$page = "";
if(isset($_GET['page'])){
    $page = $_GET["page"];
}

// Load the website functions.
include_once __DIR__ . "/functions/function.php";

// Load the selected website page.
if($page == "home"){
    include_once __DIR__ . "/includes/home.php";
}else if($page == "action"){
    include_once __DIR__ . "/includes/action.php";
}else if($page == "login"){
    include_once __DIR__ . "/includes/login.php";
}else if($page == "invite"){
    include_once __DIR__ . "/includes/invite.php";
}else if($page == "draw"){
    include_once __DIR__ . "/includes/draw.php";
}else if($page == "agree"){
    include_once __DIR__ . "/includes/agree.php";
}else if($page == "disagree"){
    include_once __DIR__ . "/includes/disagree.php";
}else if($page == "dashboard"){
    include_once __DIR__ . "/includes/dashboard.php";
}else if($page == "yourActions"){
    include_once __DIR__ . "/includes/yourActions.php";
}else if($page == "admin_overview"){
    include_once __DIR__ . "/includes/admin_overview.php";
}else if($page == "userDashboard"){
    include_once __DIR__ . "/includes/userDashboard.php";
}else if($page == "userLogin"){
    include_once __DIR__ . "/includes/userLogin.php";
}else{
    include_once __DIR__ . "/includes/about.php";
}

// Load the website footer.
include_once __DIR__ . "/includes/footer.php";
?>
<!-- Load the JavaScript file. -->
<script src="javascript/javascript.js"></script>
</body>
</html>
