<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

$username = '';
$email    = '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $pwd      = (string)($_POST['password'] ?? '');

    // basic checks
    if ($username === '' || $email === '' || $pwd === '') {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($pwd) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // check if email or username is already used
        $stmt = $conn->prepare(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Username or email already registered';
            $stmt->close();
        } else {
            $stmt->close();

            // hash the password before storing
            $hash = password_hash($pwd, PASSWORD_DEFAULT);

            $ins = $conn->prepare(
                'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)'
            );
            $ins->bind_param('sss', $username, $email, $hash);
            $ins->execute();
            $ins->close();

            redirect('/login.php');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<h1><i class="bi bi-person-plus"></i> Register</h1>

<?php if ($error !== ''): ?>
    <div class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= h(BASE_URL) ?>/register.php" class="auth-form">
    <label>
        <i class="bi bi-person"></i> Username
        <input type="text" name="username" value="<?= h($username) ?>" required>
    </label>

    <label>
        <i class="bi bi-envelope"></i> Email
        <input type="email" name="email" value="<?= h($email) ?>" required>
    </label>

    <label>
        <i class="bi bi-lock"></i> Password
        <input type="password" name="password" required>
    </label>

    <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Register</button>
</form>

<p>Already have an account? <a href="<?= h(BASE_URL) ?>/login.php"><i class="bi bi-box-arrow-in-right"></i> Log in</a>.</p>

<?php
include __DIR__ . '/includes/footer.php';
