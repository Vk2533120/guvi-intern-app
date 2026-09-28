<?php
/**
 * logout.php — Logout endpoint
 *
 * Accepts POST with the session token (X-Session-Token header or POST param).
 * Deletes the Redis key so the token is no longer valid.
 */

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? ($_POST['token'] ?? '');

if ($token !== '') {
    $redis->del('session:' . $token);
}

jsonResponse(200, ['success' => true, 'message' => 'Logged out successfully.']);
