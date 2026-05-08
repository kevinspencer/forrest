<?php

session_start();
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['authenticated'])) {
    header('Location: /forrest/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        header('Location: /forrest/');
        exit;
    }
    $error = 'Invalid password.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forrest — Login</title>
    <link rel="stylesheet" href="style.css">
    <script src="theme.js"></script>
</head>
<body>

    <header>
        <h1>Forrest</h1>
        <div class="auth-wrap">
            <button id="theme-toggle" class="theme-toggle" onclick="toggleTheme()"></button>
        </div>
    </header>

    <main>
        <div class="login-wrap">
            <form class="login-form" method="post" action="login.php">
                <h2>Sign in</h2>
                <?php if ($error): ?>
                    <p class="login-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autofocus required>
                <button type="submit">Sign in</button>
            </form>
        </div>
    </main>

    <script>updateToggleBtn();</script>
</body>
</html>
