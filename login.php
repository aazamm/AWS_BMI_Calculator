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
