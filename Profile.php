<?php
$page = 'profile'; $title = 'My Profile | Eventora';
require_once 'includes/functions.php';
require_login();
$uid = (int)$_SESSION['user_id'];

$s = $pdo->prepare('SELECT * FROM users WHERE id=?');
$s->execute([$uid]);
$user = $s->fetch();
if (!$user) { header('Location: logout.php'); exit; }

$errors = ['profile' => [], 'password' => []];
$name   = $user['name'];
$email  = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $errors['profile'][] = 'Name must be between 2 and 60 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $errors['profile'][] = 'Please enter a valid email address.';
        }
        if (!$errors['profile']) {
            $c = $pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?');
            $c->execute([$email, $uid]);
            if ($c->fetch()) $errors['profile'][] = 'That email is already used by another account.';
        }
        if (!$errors['profile']) {
            $pdo->prepare('UPDATE users SET name=?, email=? WHERE id=?')->execute([$name, $email, $uid]);
            $_SESSION['user_name'] = $name;
            flash('Profile updated.');
            header('Location: profile.php'); exit;
        }

    } elseif ($action === 'change_password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        if (!password_verify($cur, $user['password'])) {
            $errors['password'][] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors['password'][] = 'New password must be at least 8 characters.';
        } elseif ($new !== $conf) {
            $errors['password'][] = 'The new passwords do not match.';
        } elseif (password_verify($new, $user['password'])) {
            $errors['password'][] = 'New password must be different from the current one.';
        }
        if (!$errors['password']) {
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('Password changed successfully.');
            header('Location: profile.php'); exit;
        }
    }
}

$initial = mb_strtoupper(mb_substr($user['name'], 0, 1));
$isAdmin = (($user['role'] ?? '') === 'admin');

require_once 'includes/header.php'; ?>
<style>
.pf-wrap { padding: 40px 0 70px; }
.pf-head {
    display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
    background: linear-gradient(135deg, #C76B8A, #8F405C); color: #fff; border-radius: 22px;
    padding: 28px 32px; margin-bottom: 24px; box-shadow: 0 10px 28px rgba(143, 64, 92, .25);
}
.pf-avatar {
    width: 74px; height: 74px; border-radius: 50%; background: #fff; color: #B85C7A;
    display: grid; place-items: center; font-size: 2rem; font-weight: 800; flex-shrink: 0;
}
.pf-head h2 { margin: 0 0 4px; font-weight: 800; }
.pf-head p { margin: 0; color: #FBE9EE; }
.pf-card {
    background: #fff; border: 1px solid #F1D9DF; border-radius: 16px; padding: 26px;
    box-shadow: 0 5px 18px rgba(184, 92, 122, .08); height: 100%;
}
.pf-card h4 { color: #B85C7A; font-weight: 700; font-size: 20px; margin-bottom: 4px; }
.pf-card .sub { color: #7A6666; font-size: 14px; margin-bottom: 8px; }
.pf-card label { display: block; font-weight: 600; color: #5C4A4A; margin: 14px 0 6px; font-size: 14px; }
.pf-card input {
    width: 100%; border: 1px solid #F1D9DF; border-radius: 10px; padding: 11px 14px; background: #fff;
}
.pf-card input:focus { outline: none; border-color: #E89AAF; box-shadow: 0 0 0 3px rgba(232, 154, 175, .25); }
.pf-btn {
    margin-top: 20px; background: linear-gradient(135deg, #E89AAF, #C76B8A); color: #fff; border: 0;
    border-radius: 10px; padding: 11px 28px; font-weight: 700; cursor: pointer; transition: .25s;
}
.pf-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(199, 107, 138, .25); }
.pf-err { background: #FDECEE; border: 1px solid #F5C2C7; color: #842029; border-radius: 10px; padding: 10px 14px; margin: 12px 0 0; font-size: 14px; }
.pf-hint { color: #7A6666; font-size: 13px; margin-top: 6px; }
@media (max-width: 767px) { .pf-head { padding: 22px; } }
</style>

<section class="pf-wrap"><div class="container">

    <div class="pf-head">
        <div class="pf-avatar"><?= e($initial) ?></div>
        <div>
            <h2><?= e($user['name']) ?></h2>
            <p><?= e($user['email']) ?> · <?= $isAdmin ? 'Administrator' : 'Member' ?></p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <form method="post" class="pf-card" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <h4>👤 Profile details</h4>
                <p class="sub">Update the name and email used on your account.</p>

                <?php foreach ($errors['profile'] as $msg): ?><div class="pf-err"><?= e($msg) ?></div><?php endforeach; ?>

                <label for="pf_name">Full name</label>
                <input id="pf_name" type="text" name="name" value="<?= e($name) ?>" maxlength="60" required>

                <label for="pf_email">Email</label>
                <input id="pf_email" type="email" name="email" value="<?= e($email) ?>" maxlength="120" required>
                <p class="pf-hint">You will use this email to log in.</p>

                <button class="pf-btn">Save profile</button>
            </form>
        </div>

        <div class="col-lg-6">
            <form method="post" class="pf-card" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <h4>🔒 Change password</h4>
                <p class="sub">Enter your current password to set a new one.</p>

                <?php foreach ($errors['password'] as $msg): ?><div class="pf-err"><?= e($msg) ?></div><?php endforeach; ?>

                <label for="pf_cur">Current password</label>
                <input id="pf_cur" type="password" name="current_password" required>

                <label for="pf_new">New password</label>
                <input id="pf_new" type="password" name="new_password" minlength="8" required>
                <p class="pf-hint">At least 8 characters.</p>

                <label for="pf_conf">Confirm new password</label>
                <input id="pf_conf" type="password" name="confirm_password" minlength="8" required>

                <button class="pf-btn">Change password</button>
            </form>
        </div>
    </div>

</div></section>
<?php include 'includes/footer.php'; ?>