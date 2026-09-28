<?php
/**
 * db.php — Shared database/service connections
 *
 * MySQL  : XAMPP bundled MySQL 8.0 (localhost:3306, root, no password)
 * MongoDB: local instance at 127.0.0.1:27017, database "intern_app_profiles"
 * Redis  : local instance at 127.0.0.1:6379, no password
 *
 * This file is included by every PHP endpoint. It:
 *   1. Bootstraps Composer autoload (for mongodb/mongodb and predis/predis).
 *   2. Opens a MySQLi connection with prepared-statement support.
 *   3. Provides a MongoDB collection handle for profile documents.
 *   4. Provides a Redis (Predis) client for session-token storage.
 *   5. Creates the MySQL database and users table if they don't exist.
 */

// ---------------------------------------------------------------------------
// Composer autoload
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------------------------
// CORS & JSON headers (every endpoint returns JSON)
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Pre-flight OPTIONS handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------------
// MySQL connection
// ---------------------------------------------------------------------------
$mysqli = new mysqli('localhost', 'root', '', '', 3306);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MySQL connection failed: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Create database if it doesn't exist, then select it
$mysqli->query("CREATE DATABASE IF NOT EXISTS `intern_app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$mysqli->select_db('intern_app');

// Create users table if it doesn't exist
$mysqli->query("
    CREATE TABLE IF NOT EXISTS `users` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(100)  NOT NULL,
        `email`      VARCHAR(255)  NOT NULL UNIQUE,
        `username`   VARCHAR(50)   NOT NULL UNIQUE,
        `password`   VARCHAR(255)  NOT NULL,
        `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ---------------------------------------------------------------------------
// MongoDB connection
// ---------------------------------------------------------------------------
try {
    $mongoClient     = new MongoDB\Client('mongodb://127.0.0.1:27017');
    $mongoDb         = $mongoClient->intern_app_profiles;
    $profilesCollection = $mongoDb->profiles;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MongoDB connection failed: ' . $e->getMessage()]);
    exit;
}

// ---------------------------------------------------------------------------
// Redis connection (via Predis)
// ---------------------------------------------------------------------------
try {
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);
    $redis->ping(); // verify connectivity
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Redis connection failed: ' . $e->getMessage()]);
    exit;
}

// ---------------------------------------------------------------------------
// Helper: validate session token via Redis
// Returns the numeric user ID on success, or null on failure.
// ---------------------------------------------------------------------------
function validateToken(Predis\Client $redis, ?string $token): ?int
{
    if ($token === null || $token === '') {
        return null;
    }
    $userId = $redis->get('session:' . $token);
    return $userId !== null ? (int) $userId : null;
}

// ---------------------------------------------------------------------------
// Helper: send a JSON response and exit
// ---------------------------------------------------------------------------
function jsonResponse(int $httpCode, array $data): void
{
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}
