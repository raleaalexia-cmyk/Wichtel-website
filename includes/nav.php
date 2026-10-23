<?php
// Start the session.
session_start();

// Get the current page name.
// "??" means: use "" if "page" is not set in the URL.
$currentPage = $_GET["page"] ?? "";

// Clear other roles on Elf pages.
// This makes sure a Head Elf or Admin session doesn't leak into the Elf login/dashboard pages.
if(($currentPage === "userLogin" || $currentPage === "userDashboard") && !isset($_POST["BUTTON_submit"])){
    // Remove the Head Elf session.
    unset($_SESSION["HeadElf"]);
    // Remove the Admin session.
    unset($_SESSION["DATA_ADMIN"]);
}
?>

<!-- Main sidebar navigation. -->
<nav class="sidebar">
    <ul>
        <!-- Show links for a logged-in Elf. -->
        <?php if(isset($_SESSION["DATA_ELF"])){ ?>
            <li><a href="index.php?page=userDashboard">Your Overview</a></li>
            <li><a href="index.php?page=userLogin">Login</a></li>

            <!-- Show links for Head Elf or Admin. -->
        <?php } else if(isset($_SESSION["HeadElf"]) || isset($_SESSION["DATA_ADMIN"])){ ?>
            <li><a href="index.php?page=home">Home</a></li>
            <li><a href="index.php?page=action">Action</a></li>
            <li><a href="index.php?page=invite">Invite</a></li>
            <li><a href="index.php?page=draw">Draw</a></li>
            <li><a href="index.php?page=login">Login</a></li>

            <!-- Show actions link only for Head Elf. -->
            <?php if(isset($_SESSION["HeadElf"])){ ?>
                <li><a href="index.php?page=yourActions">Your Actions</a></li>
            <?php } ?>

            <li><a href="index.php?page=about">About</a></li>

            <!-- Show only Elf login on Elf pages. -->
        <?php } else if($currentPage === "userLogin" || $currentPage === "userDashboard"){ ?>
            <li><a href="index.php?page=userLogin">Login</a></li>

            <!-- Show default navigation. -->
        <?php } else { ?>
            <li><a href="index.php?page=home">Home</a></li>
            <li><a href="index.php?page=action">Action</a></li>
            <li><a href="index.php?page=invite">Invite</a></li>
            <li><a href="index.php?page=draw">Draw</a></li>
            <li><a href="index.php?page=login">Login</a></li>
            <li><a href="index.php?page=about">About</a></li>
        <?php } ?>
    </ul>
</nav>
