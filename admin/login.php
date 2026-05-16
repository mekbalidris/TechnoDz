<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// NOTE: This is the admin login page itself, so we deliberately do NOT call
// require_admin() here - that would cause an infinite redirect loop.

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Invalid username or password';
    } else {
        // Prepared lookup against the admins table - no string concatenation.
        $stmt = $conn->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($password, $row['password_hash'])) {
            // Use a separate session key from $_SESSION['user_id'] so
            // user and admin sessions stay isolated (Req 5.3).
            $_SESSION['admin_id'] = (int)$row['id'];
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }

        // Same generic message for both "no such user" and "wrong password"
        // so we don't reveal which usernames exist.
        $error = 'Invalid username or password';
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<h1>Admin Login</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><?= h($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/admin/login.php" class="auth-form">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= h($username) ?>" required autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn btn-primary">Login</button>

    <p class="hint"><small>Default: admin / admin123</small></p>
</form>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
