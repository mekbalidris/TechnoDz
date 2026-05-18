<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $pwd   = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($email === '' || $pwd === '') {
        $error = 'Invalid email or password';
    } else {
        // look up the user by email
        $stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row && password_verify($pwd, $row['password_hash'])) {
            $user_id = (int)$row['id'];
            $_SESSION['user_id'] = $user_id;
            // merge the guest cart into the user cart
            cart_merge_on_login($user_id);
            redirect('/index.php');
        }

        $error = 'Invalid email or password';
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1><i class="bi bi-box-arrow-in-right"></i> Login</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= h(BASE_URL) ?>/login.php" class="auth-form">
    <label>
        <i class="bi bi-envelope"></i> Email
        <input type="email" name="email" required value="<?= h($email) ?>">
    </label>
    <label>
        <i class="bi bi-lock"></i> Password
        <input type="password" name="password" required>
    </label>
    <button type="submit"><i class="bi bi-box-arrow-in-right"></i> Login</button>
</form>

<p>Don't have an account? <a href="<?= h(BASE_URL) ?>/register.php"><i class="bi bi-person-plus"></i> Register</a>.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
