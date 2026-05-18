<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// don't call require_admin() here, this IS the admin login page

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Invalid username or password';
    } else {
        // look up the admin by username
        $stmt = $conn->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($password, $row['password_hash'])) {
            // separate session key from regular users
            $_SESSION['admin_id'] = (int)$row['id'];
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }

        $error = 'Invalid username or password';
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<h1><i class="bi bi-shield-lock"></i> Admin Login</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/admin/login.php" class="auth-form">
    <label for="username"><i class="bi bi-person"></i> Username</label>
    <input type="text" id="username" name="username" value="<?= h($username) ?>" required autofocus>

    <label for="password"><i class="bi bi-lock"></i> Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i> Login</button>

    <p class="hint"><small>Default: admin / admin123</small></p>
</form>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
