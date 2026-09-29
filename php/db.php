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
// Helper: send a JSON response and exit
// ---------------------------------------------------------------------------
function jsonResponse(int $httpCode, array $data): void
{
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

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
$mysqlSslRaw = trim(strtolower((string)(getenv('MYSQL_SSL') ?: '')));
$mysqlSsl    = in_array($mysqlSslRaw, ['true', '1'], true);

$mongoUri  = getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017';
$mongoDbNm = getenv('MONGO_DB') ?: 'intern_app_profiles';

$redisScheme = (getenv('REDIS_TLS') === 'true') ? 'tls' : 'tcp';
$redisHost   = getenv('REDIS_HOST') ?: '127.0.0.1';
$redisPort   = getenv('REDIS_PORT') ?: 6379;
$redisPass   = getenv('REDIS_PASSWORD') ?: null;

// ---------------------------------------------------------------------------
// MySQL connection
// ---------------------------------------------------------------------------
$mysqli = mysqli_init();
error_log("Connecting MySQL with SSL: " . ($mysqlSsl ? 'true' : 'false'));

if ($mysqlSsl) {
    $mysqli->ssl_set(null, null, '/etc/ssl/certs/ca-certificates.crt', null, null);
    $connected = @$mysqli->real_connect($mysqlHost, $mysqlUser, $mysqlPass, '', (int)$mysqlPort, null, MYSQLI_CLIENT_SSL);
} else {
    $connected = @$mysqli->real_connect($mysqlHost, $mysqlUser, $mysqlPass, '', (int)$mysqlPort);
}

if (!$connected) {
    error_log("MySQL connection failed: " . mysqli_connect_error());
    jsonResponse(500, ['success' => false, 'message' => 'Database connection failed.']);
}
$mysqli->set_charset('utf8mb4');

// Safe database name to prevent syntax errors
$safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $mysqlDb); 

// In restricted cloud environments (like TiDB Cloud), CREATE DATABASE might be forbidden.
// We attempt it gracefully, but don't crash if it fails (using @).
@$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Select the database; if this fails, the DB doesn't exist and couldn't be created.
if (!$mysqli->select_db($safeDb)) {
    error_log("MySQL select_db failed: " . $mysqli->error);
    jsonResponse(500, ['success' => false, 'message' => 'Database connection failed.']);
}

// Create users table if it doesn't exist
$tableCreated = $mysqli->query("
    CREATE TABLE IF NOT EXISTS `users` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(100)  NOT NULL,
        `email`      VARCHAR(255)  NOT NULL UNIQUE,
        `username`   VARCHAR(50)   NOT NULL UNIQUE,
        `password`   VARCHAR(255)  NOT NULL,
        `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

if (!$tableCreated) {
    error_log("MySQL users table creation failed: " . $mysqli->error);
    jsonResponse(500, ['success' => false, 'message' => 'Database initialization failed.']);
}

// ---------------------------------------------------------------------------
// MongoDB connection
// ---------------------------------------------------------------------------
try {
    $mongoClient     = new MongoDB\Client($mongoUri);
    $mongoDbSelected = $mongoClient->$mongoDbNm;
    $profilesCollection = $mongoDbSelected->profiles;
} catch (Exception $e) {
    error_log("MongoDB connection failed: " . $e->getMessage());
    jsonResponse(500, ['success' => false, 'message' => 'Database connection failed.']);
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
    
    // Add password if provided
    if ($redisPass !== null && $redisPass !== '') {
        $redisConfig['password'] = $redisPass;
    }

    $redis = new Predis\Client($redisConfig);
    $redis->ping(); // verify connectivity
} catch (Exception $e) {
    error_log("Redis connection failed: " . $e->getMessage());
    jsonResponse(500, ['success' => false, 'message' => 'Database connection failed.']);
}

// ---------------------------------------------------------------------------
// Helper: validate session token via Redis
// ---------------------------------------------------------------------------
function validateToken(Predis\Client $redis, ?string $token): ?int
{
    if ($token === null || $token === '') {
        return null;
    }
    try {
        $userId = $redis->get('session:' . $token);
        return $userId !== null ? (int) $userId : null;
    } catch (Exception $e) {
        error_log("Redis get token failed: " . $e->getMessage());
        return null;
    }
}
