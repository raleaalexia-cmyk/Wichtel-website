<body class="promo-body">
<?php
// Check if the form was submitted.
if (isset($_POST["BUTTON_submit"])) {
    // Check if an email was submitted.
    if (isset($_POST["DATA_Email"])) {
        // Show the decline message.
        echo "<p class='disagree-message'>Schade dass du nicht mitmachst</p>";
    }
}

// Check if the action and Elf IDs exist.
if (isset($_GET["action"], $_GET["elf"])) {
    // Convert both IDs to numbers, so they're safe to use in the query below.
    $actionId = (int) $_GET["action"];
    $elfId = (int) $_GET["elf"];

    // Connect to the database.
    $dbc = f_connect(false);

    // Decline this Elf's invitation.
    // This sets the status to "Declined" for this elf + action pair.
    $query = "UPDATE Invite
              SET status = 'Declined'
              WHERE pkfk_action = $actionId
              AND pkfk_elf = $elfId";

    // Prepare the update query.
    $statement = mysqli_prepare($dbc, $query);

    // Run the update query.
    mysqli_stmt_execute($statement);

    // Close the statement.
    mysqli_stmt_close($statement);

    // Close the database connection.
    mysqli_close($dbc);
    ?>

    <!-- Main message box. -->
    <div class="promo-box">
        <!-- Page title. -->
        <h2>🎄 Wichteln 🎄</h2>
        <!-- Decline confirmation. -->
        <p>❌ You have declined the invite.</p>
    </div>

    <?php
} else {
    // Show an error if an ID is missing.
    ?>
    <!-- Invalid link message. -->
    <div class="promo-box">
        <p>❌ Invalid link.</p>
    </div>
    <?php
}
?>
</body>
