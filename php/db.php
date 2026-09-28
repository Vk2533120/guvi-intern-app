<?php
/**
 * db.php — Shared database/service connections
 *
 * Supports environments via environment variables, defaulting to local XAMPP/dev settings:
 * MySQL  : localhost:3306, root, no password, intern_app
 * MongoDB: local instance at 127.0.0.1:27017, database "intern_app_profiles"
 * Redis  : local instance at 127.0.0.1:6379, no password
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
// Environment defaults
// ---------------------------------------------------------------------------
$mysqlHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
$mysqlPort = getenv('MYSQL_PORT') ?: 3306;
$mysqlUser = getenv('MYSQL_USER') !== false ? getenv('MYSQL_USER') : 'root';
$mysqlPass = getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : '';
$mysqlDb   = getenv('MYSQL_DATABASE') ?: 'intern_app';

$mongoUri  = getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017';
$mongoDbNm = getenv('MONGO_DB') ?: 'intern_app_profiles';

$redisScheme = (getenv('REDIS_TLS') === 'true') ? 'tls' : 'tcp';
$redisHost   = getenv('REDIS_HOST') ?: '127.0.0.1';
$redisPort   = getenv('REDIS_PORT') ?: 6379;
$redisPass   = getenv('REDIS_PASSWORD') ?: null;

// ---------------------------------------------------------------------------
// MySQL connection
// ---------------------------------------------------------------------------
$mysqli = new mysqli($mysqlHost, $mysqlUser, $mysqlPass, '', (int)$mysqlPort);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MySQL connection failed: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Create database if it doesn't exist, then select it (preventing SQLi on DB name by using backticks safely)
$safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $mysqlDb); 
$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$mysqli->select_db($safeDb);

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
    $mongoClient     = new MongoDB\Client($mongoUri);
    $mongoDb         = $mongoClient->$mongoDbNm;
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
    $redisConfig = [
        'scheme' => $redisScheme,
        'host'   => $redisHost,
        'port'   => (int)$redisPort,
    ];
    if ($redisPass !== null && $redisPass !== '') {
        $redisConfig['password'] = $redisPass;
    }

    $redis = new Predis\Client($redisConfig);
    $redis->ping(); // verify connectivity
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Redis connection failed: ' . $e->getMessage()]);
    exit;
}

// ---------------------------------------------------------------------------
// Helper: validate session token via Redis
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
