<?php
require_once __DIR__ . '/auth.php';
startSession();

$error = '';
$mode = $_POST['mode'] ?? ($_GET['mode'] ?? 'login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');

    if ($mode === 'signup') {
        if ($email === '' || $password === '' || $displayName === '') {
            $error = 'All fields are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $user = registerUser($email, $password, $displayName);
            if ($user === false) {
                $error = 'Email already registered.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['display_name'];
                header('Location: index.php');
                exit;
            }
        }
    } else {
        $user = loginUser($email, $password);
        if ($user === false) {
            $error = 'Invalid email or password.';
        } else {
            header('Location: index.php');
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    header('Location: index.php');
    exit;
}

$visitorCount = incrementVisitorCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'signup' ? 'Sign Up' : 'Login' ?> - BMI Calculator</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1><?= $mode === 'signup' ? 'Create Account' : 'Login' ?></h1>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">

            <?php if ($mode === 'signup'): ?>
                <div class="form-group">
                    <label for="display_name">Display Name</label>
                    <input type="text" id="display_name" name="display_name" placeholder="Your name" required
                           value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Min 6 characters" required>
            </div>

            <div class="button-group">
                <button type="submit" class="btn-primary">
                    <?= $mode === 'signup' ? 'Create Account' : 'Login' ?>
                </button>
            </div>
        </form>

        <div class="auth-divider"><span>or</span></div>

        <a href="google_auth.php" class="btn-google">
            <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.997 8.997 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
            Sign in with Google
        </a>

        <?php if (isset($_GET['error'])): ?>
            <div class="auth-error" style="margin-top: 10px;">Google sign-in failed. Please try again.</div>
        <?php endif; ?>

        <p class="auth-toggle">
            <?php if ($mode === 'signup'): ?>
                Already have an account? <a href="login.php">Login</a>
            <?php else: ?>
                Don't have an account? <a href="login.php?mode=signup">Sign up</a>
            <?php endif; ?>
        </p>

        <p style="margin-top: 15px;"><a href="index.php">Back to Calculator</a></p>

        <div class="visitor-counter">
            Visitors: <?= $visitorCount ?>
        </div>
    </div>
</body>
</html>
