# Intern App

A small register → login → profile app I built for the GUVI internship assignment. You sign up, log in, and land on a profile page where you can save extra details like age, date of birth, contact number and address.

## How it works

- **MySQL** stores the account (name, email, username, hashed password). Every query is a prepared statement.
- **MongoDB** stores the extra profile details, linked to the MySQL user by `user_id`.
- **Redis** stores the login session on the server. No PHP `$_SESSION` anywhere.
- **localStorage** holds the session token in the browser. It's sent to the backend in an `X-Session-Token` header on every request.
- **jQuery AJAX** does all the talking to the backend. There are no normal form submissions.
- **Bootstrap 5** handles the layout and forms, so it works on phones too.

The login flow, in short: the user logs in, PHP checks the password with `password_verify`, generates a random token and saves it in Redis as `session:<token>` → user id (expires after 2 hours). The token goes back to the browser and gets saved in localStorage. Every request to `profile.php` sends it, and the server looks it up in Redis. If it isn't there (expired, logged out, or made up), the server returns a 401 and the page sends you back to login. Logging out deletes the key from Redis and clears localStorage.

## Project structure

```
intern-app/
├── assets/
├── css/
│   └── style.css
├── js/
│   ├── login.js
│   ├── profile.js
│   └── register.js
├── php/
│   ├── db.php        # MySQL, MongoDB and Redis connections
│   ├── login.php
│   ├── logout.php
│   ├── profile.php
│   └── register.php
├── index.html
├── login.html
├── profile.html
├── register.html
└── composer.json
```

HTML, CSS, JS and PHP are all kept in separate files, as the assignment asked.

## What you need installed

- PHP 8.2 (I used XAMPP on Windows)
- MySQL (comes with XAMPP)
- MongoDB running on `127.0.0.1:27017`
- Redis running on `127.0.0.1:6379`
- Composer
- The PHP `mongodb` extension enabled

## Running it locally

**1. Put the project in your web root**

Clone or copy it to `C:\xampp\htdocs\intern-app`.

**2. Enable the MongoDB extension for PHP**

Download the `php_mongodb.dll` that matches your PHP version (8.2, thread safe, x64) from [PECL](https://pecl.php.net/package/mongodb) and drop it into `C:\xampp\php\ext\`. Then open `C:\xampp\php\php.ini`, add this line in the extensions section, and restart Apache:

```ini
extension=mongodb
```

To check it worked, run `php -m` in a fresh terminal and look for `mongodb` in the list.

**3. Install the PHP libraries**

```bash
cd C:\xampp\htdocs\intern-app
composer install
```

The `vendor/` folder isn't committed, so this step is needed. It installs `mongodb/mongodb` and `predis/predis`.

**4. Start the services**

- Start Apache and MySQL from the XAMPP Control Panel.
- Make sure MongoDB is running.
- Check Redis with `redis-cli ping`. It should answer `PONG`.

**5. Open the app**

Go to http://localhost/intern-app/ or Hosted link

The `intern_app` database and `users` table are created automatically the first time you run it. If you'd rather create them yourself in phpMyAdmin, here's the SQL:

```sql
CREATE DATABASE IF NOT EXISTS `intern_app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `intern_app`;

CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100)  NOT NULL,
    `email`      VARCHAR(255)  NOT NULL UNIQUE,
    `username`   VARCHAR(50)   NOT NULL UNIQUE,
    `password`   VARCHAR(255)  NOT NULL,
    `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Default config

Everything is set in `php/db.php`. The defaults match a fresh local setup:

| Service | Setting |
|---------|---------|
| MySQL | `localhost:3306`, user `root`, no password, database `intern_app` |
| MongoDB | `mongodb://127.0.0.1:27017`, database `intern_app_profiles`, collection `profiles` |
| Redis | `127.0.0.1:6379`, no password |

If your setup is different, change the values there. If you ever deploy this, don't use the empty root password.

## Trying it out

1. Register a new account.
2. Log in, and you'll be taken to the profile page.
3. Fill in your age, date of birth, contact and address, then hit update.
4. Refresh the page and your details should still be there.

To see the data behind it:

- **MySQL:** in phpMyAdmin, open `intern_app` → `users`. The password is a bcrypt hash.
- **MongoDB:** in `mongosh`, run `use intern_app_profiles` then `db.profiles.find()`.
- **Redis:** after logging in, run `redis-cli keys "session:*"`. After logging out, the key is gone.

## Things I'd add with more time

- CSRF protection and rate limiting on the login endpoint
- Email verification and a password reset flow
- Moving the database credentials into an environment file
## Run with Docker

This project is fully containerized and can be configured via environment variables.

**1. Start the stack**
```bash
docker compose up --build -d
```
This spins up four containers: the PHP/Apache web server, MySQL 8, MongoDB 7, and Redis 7. Databases use named volumes to persist data.

**2. Open the app**
Navigate to http://localhost:8080/

### Environment Variables

You can configure the application in `php/db.php` by setting these environment variables (perfect for deployments like Render). If not set, they fall back to the XAMPP defaults listed above.

- `MYSQL_HOST` (default: 127.0.0.1)
- `MYSQL_PORT` (default: 3306)
- `MYSQL_USER` (default: root)
- `MYSQL_PASSWORD` (default: "")
- `MYSQL_DATABASE` (default: intern_app)
- `MONGO_URI` (default: mongodb://127.0.0.1:27017, supports mongodb+srv://)
- `MONGO_DB` (default: intern_app_profiles)
- `REDIS_HOST` (default: 127.0.0.1)
- `REDIS_PORT` (default: 6379)
- `REDIS_PASSWORD` (default: null)
- `REDIS_TLS` (default: false, set to 'true' to use tls for Predis)
