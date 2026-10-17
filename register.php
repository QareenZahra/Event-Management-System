<?php
$page = 'register'; $title = 'Register | Eventora';
require_once 'includes/functions.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? ''; $conf = $_POST['confirm'] ?? '';
    if (strlen($name) < 2) $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($pass !== $conf) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        $c = $pdo->prepare('SELECT id FROM users WHERE email=?'); $c->execute([$email]);
        if ($c->fetch()) $errors[] = 'This email is already registered.';
        else {
            $pdo->prepare('INSERT INTO users (name,email,password) VALUES (?,?,?)')
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('Account created! Please login.');
            header('Location: login.php'); exit;
        }
    }
}
require_once 'includes/header.php'; ?>
<section class="form-section"><div class="container"><div class="form-card">
    <h2>Create Account</h2><p class="sub">Join Eventora in a few seconds.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger eventora-alert"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
        <label>Full Name</label><input class="form-control" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
        <label>Email</label><input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        <label>Password</label><input class="form-control" type="password" name="password" required>
        <label>Confirm Password</label><input class="form-control" type="password" name="confirm" required>
        <button class="form-btn">Register</button>
    </form>
    <p class="form-alt">Already have an account? <a href="login.php">Login</a></p>
</div></div></section>
<?php include 'includes/footer.php'; ?>
