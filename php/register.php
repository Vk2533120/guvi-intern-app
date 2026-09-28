<?php
/**
 * register.php — Registration endpoint
 *
 * Accepts POST with: name, email, username, password, confirm_password
 * Validates all inputs server-side, hashes the password, and inserts
 * into MySQL via a prepared statement. Returns JSON.
 */

require_once __DIR__ . '/db.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
}

// ---------------------------------------------------------------------------
// Read & sanitize input
// ---------------------------------------------------------------------------
$name            = trim($_POST['name']            ?? '');
$email           = trim($_POST['email']           ?? '');
$username        = trim($_POST['username']        ?? '');
$password        =      $_POST['password']        ?? '';
$confirmPassword =      $_POST['confirm_password'] ?? '';

// ---------------------------------------------------------------------------
// Server-side validation
// ---------------------------------------------------------------------------
$errors = [];

if ($name === '') {
    $errors[] = 'Name is required.';
} elseif (mb_strlen($name) > 100) {
    $errors[] = 'Name must be at most 100 characters.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if ($username === '') {
    $errors[] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters (letters, numbers, underscores).';
}

if (mb_strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    jsonResponse(422, ['success' => false, 'message' => implode(' ', $errors)]);
}

// ---------------------------------------------------------------------------
// Check for duplicate email/username (prepared statement)
// ---------------------------------------------------------------------------
$stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
$stmt->bind_param('ss', $email, $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    jsonResponse(409, ['success' => false, 'message' => 'Email or username already exists.']);
}
$stmt->close();

// ---------------------------------------------------------------------------
// Hash password & insert (prepared statement)
// ---------------------------------------------------------------------------
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$stmt = $mysqli->prepare('INSERT INTO users (name, email, username, password) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $name, $email, $username, $hashedPassword);

if ($stmt->execute()) {
    $stmt->close();
    jsonResponse(201, ['success' => true, 'message' => 'Registration successful! You can now log in.']);
} else {
    $stmt->close();
    jsonResponse(500, ['success' => false, 'message' => 'Registration failed. Please try again.']);
}
