# Installation Instructions

This guide provides instructions to install and configure the Astral-Tele application, a Telegram Mini App for reading comics built with JS, PHP, MySQL, and S3 compatible storage.

## Requirements
- PHP 8.0+ with extensions: `pdo`, `pdo_mysql`, `curl`, `json`
- MySQL 5.7+ or MariaDB
- Composer (for PHP dependencies)
- An S3-compatible storage (AWS S3, MinIO, DigitalOcean Spaces, etc.)
- A Telegram Bot token (from @BotFather)

## 1. Setup Database
1. Create a MySQL database (e.g., `astral_tele`).
2. Import the `schema.sql` file into your database:
   ```bash
   mysql -u username -p astral_tele < schema.sql
   ```

## 2. Setup PHP Backend
1. Install PHP dependencies using Composer:
   ```bash
   composer install
   ```
2. Open `config.php` (create it if not exists based on `config.example.php` or manually) and update the configuration variables:
   - Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
   - S3 credentials (S3_ENDPOINT, S3_REGION, S3_KEY, S3_SECRET, S3_BUCKET)
   - Telegram Bot credentials (TELEGRAM_BOT_TOKEN)

## 3. Setup Telegram Mini App
1. Go to Telegram and search for `@BotFather`.
2. Use `/newbot` to create a new bot and save the API Token.
3. Configure your Telegram Mini App URL (pointing to the `index.html` of this project hosted securely via HTTPS).
4. Update the `TELEGRAM_BOT_TOKEN` in `config.php` with the token received from BotFather.

## 4. Web Server Configuration
Ensure your web server (Apache, Nginx, or PHP built-in server) points to the root directory where `index.html` is located. 

For development, you can use the PHP built-in server:
```bash
php -S localhost:8000
```

## 5. Security Note
Make sure to secure your endpoints and keep your `config.php` out of public access. Telegram initData validation requires the `TELEGRAM_BOT_TOKEN` to be kept secret.