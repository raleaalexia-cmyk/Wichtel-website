<?php
// Configure these values as environment variables on the PHP host.
// Do not commit database credentials to the repository.
define('DB_HOST', getenv('WICHTEL_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('WICHTEL_DB_USER') ?: '');
define('DB_PW', getenv('WICHTEL_DB_PASSWORD') ?: '');
define('DB_NAME', getenv('WICHTEL_DB_NAME') ?: '');
?>
