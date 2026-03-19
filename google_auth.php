<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
startSession();
loadEnv();

$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = getenv('BASE_URL') ?: 'https://aaronzammit.com/bmi';
$redirectUri = rtrim($redirectUri, '/') . '/google_auth.php';

if (!$clientId || !$clientSecret) {
    header('Location: login.php?error=google_not_configured');
    exit;
}

// Step 1: No code — redirect to Google
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// Step 2: Callback with code
if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    header('Location: login.php?error=invalid_state');
    exit;
}
unset($_SESSION['oauth_state']);

// Exchange code for tokens
$tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code' => $_GET['code'],
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]),
    ],
]));

if ($tokenResponse === false) {
    header('Location: login.php?error=token_exchange_failed');
    exit;
}

$tokens = json_decode($tokenResponse, true);
if (empty($tokens['access_token'])) {
    header('Location: login.php?error=no_access_token');
    exit;
}

// Fetch user profile
$profileResponse = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create([
    'http' => [
        'header' => 'Authorization: Bearer ' . $tokens['access_token'],
    ],
]));

if ($profileResponse === false) {
    header('Location: login.php?error=profile_fetch_failed');
    exit;
}

$profile = json_decode($profileResponse, true);
$googleId = $profile['id'] ?? null;
$email = $profile['email'] ?? null;
$name = $profile['name'] ?? $email;

if (!$googleId || !$email) {
    header('Location: login.php?error=missing_profile');
    exit;
}

// Find or create user
$user = getUserByGoogleId($googleId);

if (!$user) {
    $user = getUserByEmail($email);
    if ($user) {
        // Link Google ID to existing account
        linkGoogleId($user['id'], $googleId);
    } else {
        // Create new OAuth-only user
        $id = createGoogleUser($email, $name, $googleId);
        $user = getUserById($id);
    }
}

// Log in
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['display_name'];
header('Location: index.php');
exit;
