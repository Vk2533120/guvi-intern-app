<?php
/**
 * profile.php — Profile endpoint (GET = fetch, POST = update)
 *
 * Every request must include the session token via the X-Session-Token header.
 * The token is validated against Redis; if invalid/expired, returns 401.
 *
 * GET  — fetches basic info from MySQL + extended profile from MongoDB.
 * POST — validates & upserts extended profile fields into MongoDB.
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// Authenticate via Redis token
// ---------------------------------------------------------------------------
$token  = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? ($_GET['token'] ?? ($_POST['token'] ?? ''));
$userId = validateToken($redis, $token);

if ($userId === null) {
    jsonResponse(401, ['success' => false, 'message' => 'Unauthorized. Please log in.']);
}

// ---------------------------------------------------------------------------
// GET — Fetch profile
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Basic info from MySQL (prepared statement)
    $stmt = $mysqli->prepare('SELECT id, name, email, username, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        jsonResponse(404, ['success' => false, 'message' => 'User not found.']);
    }

    // Extended profile from MongoDB
    $profileDoc = $profilesCollection->findOne(['user_id' => $userId]);

    $profile = [
        'age'     => '',
        'dob'     => '',
        'contact' => '',
        'address' => '',
    ];

    if ($profileDoc) {
        $profile['age']     = (string) ($profileDoc['age']     ?? '');
        $profile['dob']     = (string) ($profileDoc['dob']     ?? '');
        $profile['contact'] = (string) ($profileDoc['contact'] ?? '');
        $profile['address'] = (string) ($profileDoc['address'] ?? '');
    }

    jsonResponse(200, [
        'success' => true,
        'user'    => [
            'id'         => $user['id'],
            'name'       => htmlspecialchars($user['name'],     ENT_QUOTES, 'UTF-8'),
            'email'      => htmlspecialchars($user['email'],    ENT_QUOTES, 'UTF-8'),
            'username'   => htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'),
            'created_at' => $user['created_at'],
        ],
        'profile' => $profile,
    ]);
}

// ---------------------------------------------------------------------------
// POST — Update extended profile (MongoDB upsert)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Read & sanitize fields
    $age     = trim($_POST['age']     ?? '');
    $dob     = trim($_POST['dob']     ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Server-side validation
    $errors = [];

    if ($age !== '' && (!ctype_digit($age) || (int) $age < 1 || (int) $age > 150)) {
        $errors[] = 'Age must be a number between 1 and 150.';
    }

    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $errors[] = 'Date of birth must be in YYYY-MM-DD format.';
    }

    if ($contact !== '' && !preg_match('/^\+?[\d\s\-()]{7,20}$/', $contact)) {
        $errors[] = 'Contact number is invalid.';
    }

    if (mb_strlen($address) > 500) {
        $errors[] = 'Address must be at most 500 characters.';
    }

    if (!empty($errors)) {
        jsonResponse(422, ['success' => false, 'message' => implode(' ', $errors)]);
    }

    // Upsert into MongoDB (update if exists, insert if not)
    $profilesCollection->updateOne(
        ['user_id' => $userId],
        ['$set' => [
            'user_id' => $userId,
            'age'     => $age !== '' ? (int)$age : '',
            'dob'     => $dob,
            'contact' => $contact,
            'address' => $address,
            'updated_at' => date('Y-m-d H:i:s'),
        ]],
        ['upsert' => true]
    );

    jsonResponse(200, ['success' => true, 'message' => 'Profile updated successfully!']);
}

// Any other method
jsonResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
