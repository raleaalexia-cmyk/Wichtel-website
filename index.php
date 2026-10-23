<?php
// Buffer output so session cookies and redirects from included pages can be sent safely.
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
include_once "includes/nav.php";

// Store the selected page.
$page = "";

// Check if a page was selected.
if(isset($_GET['page'])){
    // Get the page name from the URL.
    $page = $_GET["page"];
}

// Load the website functions.
include_once "functions/function.php";

// Load the selected website page.
// This acts as a simple router: the "page" URL parameter decides which file gets included.
if($page == "home"){
    // Load the home page.
    include_once "includes/home.html";
}else if($page == "action"){
    // Load the action page.
    include_once "includes/action.php";
}else if($page == "login"){
    // Load the login page.
    include_once "includes/login.php";
}else if($page == "invite"){
    // Load the invitation page.
    include_once "includes/invite.php";
}else if($page == "draw"){
    // Load the draw page.
    include_once "includes/draw.php";
}else if($page == "agree"){
    // Load the accept page.
    include_once "includes/agree.php";
}else if($page == "disagree"){
    // Load the decline page.
    include_once "includes/disagree.php";
}else if($page == "dashboard"){
    // Load the Admin dashboard.
    include_once "includes/dashboard.php";
}else if($page == "yourActions"){
    // Load the Head Elf page.
    include_once "includes/yourActions.php";
}else if($page == "admin_overview"){
    // Load the Admin overview.
    include_once "includes/admin_overview.php";
}else if($page == "userDashboard"){
    // Load the Elf dashboard.
    include_once "includes/userDashboard.php";
}else if($page == "userLogin"){
    // Load the Elf login page.
    include_once "includes/userLogin.php";
}else{
    // Load the About page by default.
    // This is the fallback when no page or an unknown page is requested.
    include_once "includes/about.html";
}

// Load the website footer.
include_once "includes/footer.php";
?>

<!-- Load the JavaScript file. -->
<script src="javascript/javascript.js"></script>
</body>
</html>
