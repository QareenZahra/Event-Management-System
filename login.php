<?php
$page = 'login'; $title = 'Login | Eventora';
require_once 'includes/functions.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $s = $pdo->prepare('SELECT * FROM users WHERE email=?'); $s->execute([$email]);
    $u = $s->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id']; $_SESSION['user_name'] = $u['name'];
        flash('Welcome back, ' . $u['name'] . '!');

        // Admins land on the admin panel, everyone else on the dashboard
        $default = (($u['role'] ?? '') === 'admin') ? 'admin.php' : 'dashboard.php';
        $next = basename($_GET['next'] ?? $default);
        if (!preg_match('/^[\w\-]+\.php(\?[\w=&%\-]*)?$/', $next)) $next = $default;
        header('Location: ' . $next); exit;
    }
    $error = 'Invalid email or password.';
}
require_once 'includes/header.php'; ?>
<section class="form-section"><div class="container"><div class="form-card">
    <h2>Welcome Back</h2><p class="sub">Login to manage your events and bookings.</p>
    <?php if ($error): ?><div class="alert alert-danger eventora-alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
        <label>Email</label><input class="form-control" type="email" name="email" required>
        <label>Password</label><input class="form-control" type="password" name="password" required>
        <button class="form-btn">Login</button>
    </form>
    <p class="form-alt">New here? <a href="register.php">Create an account</a></p>
</div></div></section>
<?php include 'includes/footer.php'; ?>