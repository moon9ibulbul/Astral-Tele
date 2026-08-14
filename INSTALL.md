# Installation & Setup Instructions

Welcome to **Astral-Tele**, a modern, lightweight Telegram Mini App designed for reading comics. It features a fast Vanilla JS and Tailwind CSS frontend, a PHP and MySQL backend, and secure S3-compatible cloud storage for chapter PDFs.

---

## 🛠️ Requirements
Before you begin, ensure your server or hosting environment meets the following requirements:
- **PHP 8.0+** with the following extensions enabled:
  - `pdo` and `pdo_mysql` (for database interaction)
  - `curl` (for API calls)
  - `json` (for parsing JSON payloads)
  - `gd` (optional, for profile picture cropping/resizing)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Composer** (for installing PHP dependencies)
- **S3-Compatible Storage** (e.g., AWS S3, MinIO, Cloudflare R2, DigitalOcean Spaces)
- **Telegram Bot Token** (obtainable from [@BotFather](https://t.me/BotFather))

---

## 🚀 Installation Methods

### Method 1: Automatic Web-Based Installer (Recommended)
This application includes a built-in interactive web installer that automates configuration, database setup, and dependency management.

1. Upload all project files to your web server.
2. Point your domain or local web server to the root of the project.
3. Open your web browser and navigate to `install.php` (e.g., `http://localhost:8000/install.php` or `https://yourdomain.com/install.php`).
4. Follow the step-by-step instructions:
   - **Configuration:** Enter your website details, database credentials, S3 bucket settings, Telegram bot token, and admin user ID.
   - **Database Setup:** The installer will automatically run and populate your database with `schema.sql`.
   - **Dependencies:** The installer will securely retrieve Composer and install required libraries.
   - **Finalization:** The installer will inject the configured app title and securely self-delete for safety.

---

### Method 2: Manual Setup
If you prefer setting up the application manually, follow these steps:

#### 1. Initialize the Database
Import the consolidated schema into your MySQL/MariaDB database:
```bash
mysql -u your_user -p astral_tele < schema.sql
```

#### 2. Install PHP Dependencies
Run Composer in the root directory of the project to download and install all required external packages (such as the AWS SDK for PHP):
```bash
composer install --no-dev --optimize-autoloader
```

#### 3. Create Configuration File
Create a `config.php` file in the root directory based on the following structure:
```php
<?php

$config = [
    'app' => [
        'title' => 'Astral-Tele',
        'url' => 'https://yourdomain.com',
        'admin_id' => '123456789' // Your Telegram User ID for admin access
    ],
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'astral_tele',
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
        'charset' => 'utf8mb4'
    ],
    's3' => [
        'endpoint' => 'https://s3.example.com',
        'region' => 'us-east-1',
        'key' => 'YOUR_S3_KEY',
        'secret' => 'YOUR_S3_SECRET',
        'bucket' => 'astral-tele-bucket'
    ],
    'telegram' => [
        'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN'
    ]
];
```

#### 4. Configure Telegram Mini App
1. Chat with `@BotFather` on Telegram.
2. Execute `/newbot` to create your bot and receive an API token.
3. Update the `bot_token` key inside `config.php` with this token.
4. Execute `/newapp` to create a Telegram Mini App linked to your bot, and configure the app URL to point to your hosted secure HTTPS URL (e.g., `https://yourdomain.com/index.html`).

---

## 💻 Local Development & Testing

### Running a Built-In Development Server
You can quickly run a local server using PHP's built-in web server:
```bash
php -S localhost:8000
```

### Seeding Mock Data
To populate your local environment with sample comics, categories, chapters, and users for testing, run the mock script:
```bash
php mock.php
```

---

## 🔒 Security and Deployment Notes
1. **HTTPS Requirement:** Telegram Mini Apps *require* secure SSL/TLS (`https://`) to run inside Telegram client webviews. Use a tunnel tool like Ngrok or Cloudflare Tunnels for local testing.
2. **Configuration Protection:** Ensure your `config.php` file remains private and is not exposed to the public. Under Apache/Nginx, configure rules to deny access to PHP configuration files.
3. **Database Security:** In production, use a dedicated, low-privilege MySQL user instead of the database root account.
