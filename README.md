# Wichtel-Website

This repository contains the original Wichtel website source with the existing PHP pages, HTML templates, CSS, JavaScript and SQL scripts.

## Run requirements

- PHP 8.1 or newer
- MySQL or MariaDB
- Composer
- PHP mysqli extension

## Configure the application

1. Create the database and import `CREATE_TABLES+SYSM.sql`.
2. Configure the environment variables shown in `.env.example` on the PHP host. Database and SMTP credentials are intentionally not stored in Git.
3. Run `composer install --no-dev` to install PHPMailer.
4. Upload the project to a PHP-compatible web host and open `index.php?page=home`.

GitHub stores the source code, but GitHub Pages cannot execute PHP or connect to MySQL. The full login, invitation, draw and database features therefore require a PHP/MySQL host.

The existing email templates contain links to the previous website host. Update those links if the application moves to another domain.
