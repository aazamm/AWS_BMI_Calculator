<?php
require_once __DIR__ . '/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 86400 * 30,
            'path' => '/bmi/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function registerUser(string $email, string $password, string $displayName): array|false {
    $existing = getUserByEmail($email);
    if ($existing) return false;

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $id = createUser($email, $hash, $displayName);
    return getUserById($id);
}

function loginUser(string $email, string $password): array|false {
    $user = getUserByEmail($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['display_name'];
    return $user;
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function getCurrentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return getUserById($_SESSION['user_id']);
}
