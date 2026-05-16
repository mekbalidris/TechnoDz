<?php
// User login page.
//
// GET: renders the login form.
// POST: looks up the user by email with a prepared statement, verifies the
//       submitted password against the stored bcrypt hash with
//       password_verify(), and on success starts a user session, merges
//       any guest cart into the user's persistent cart, and redirects to
//       the shop landing page. On failure the form re-renders with a
//       generic "Invalid email or password" message - the same wording for
//       both "no such email" and "wrong password" so we don't leak which
//       accounts exist.

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
        // Prepared SELECT - never concatenate user input into SQL.
        $stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row && password_verify($pwd, $row['password_hash'])) {
            $user_id = (int)$row['id'];
            $_SESSION['user_id'] = $user_id;
            // Sum the guest cart into the user's DB cart and clear the cookie.
            cart_merge_on_login($user_id);
            redirect('/index.php');
        }

        $error = 'Invalid email or password';
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1>Login</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><?= h($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= h(BASE_URL) ?>/login.php" class="auth-form">
    <label>
        Email
        <input type="email" name="email" required value="<?= h($email) ?>">
    </label>
    <label>
        Password
        <input type="password" name="password" required>
    </label>
    <button type="submit">Login</button>
</form>

<p>Don't have an account? <a href="<?= h(BASE_URL) ?>/register.php">Register</a>.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
