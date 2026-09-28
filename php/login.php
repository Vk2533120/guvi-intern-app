<?php
/**
 * login.php — Login endpoint
 *
 * Accepts POST with: login (username or email), password
 * Verifies credentials against MySQL (prepared statement + password_verify),
 * generates a UUID session token, stores it in Redis with a 2-hour TTL,
 * and returns the token as JSON.
 */

require_once __DIR__ . '/db.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
}

// ---------------------------------------------------------------------------
// Read & sanitize input
// ---------------------------------------------------------------------------
$login    = trim($_POST['login']    ?? '');
$password =      $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    jsonResponse(422, ['success' => false, 'message' => 'Username/email and password are required.']);
}

// ---------------------------------------------------------------------------
// Look up user by email OR username (prepared statement)
// ---------------------------------------------------------------------------
$stmt = $mysqli->prepare('SELECT id, name, email, username, password FROM users WHERE email = ? OR username = ?');
$stmt->bind_param('ss', $login, $login);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    jsonResponse(401, ['success' => false, 'message' => 'Invalid credentials.']);
}

$user = $result->fetch_assoc();
$stmt->close();

// ---------------------------------------------------------------------------
// Verify password
// ---------------------------------------------------------------------------
if (!password_verify($password, $user['password'])) {
    jsonResponse(401, ['success' => false, 'message' => 'Invalid credentials.']);
}

// ---------------------------------------------------------------------------
// Generate session token (UUID v4) and store in Redis with 2-hour TTL
// ---------------------------------------------------------------------------
$token = bin2hex(random_bytes(16));

$redis->setex('session:' . $token, 7200, $user['id']); // 2 hours TTL

jsonResponse(200, [
    'success'  => true,
    'message'  => 'Login successful!',
    'token'    => $token,
    'user'     => [
        'id'       => $user['id'],
        'name'     => $user['name'],
        'email'    => $user['email'],
        'username' => $user['username'],
    ],
]);
