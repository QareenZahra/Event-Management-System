<?php
$page = 'admin'; $title = 'Admin Dashboard | Eventora';
require_once 'includes/functions.php';
require_login();
$uid = (int)$_SESSION['user_id'];

// ---------- Admin check (always from the database, never from the session) ----------
$chk = $pdo->prepare("SELECT role FROM users WHERE id=?");
$chk->execute([$uid]);
if ($chk->fetchColumn() !== 'admin') {
    http_response_code(403);
    exit('Access denied. Admins only.');
}

$tabs = [
    'overview' => '📊 Overview',
    'users'    => '👥 Users',
    'events'   => '🎉 Events',
    'bookings' => '🎟️ Bookings',
    'settings' => '⚙️ Site Settings',
    'slider'   => '🖼️ Slider',
];
$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';

// Editable website texts (key => label, max length, field type, default text)
$settingFields = [
    'site_name'          => ['group' => 'General',   'label' => 'Website name',                      'max' => 40,  'type' => 'text',     'default' => 'EVENTORA'],
    'hero_tag'           => ['group' => 'Home page', 'label' => 'Small tag above the heading',       'max' => 60,  'type' => 'text',     'default' => '✨ EVENT MANAGEMENT MADE EASY'],
    'hero_title'         => ['group' => 'Home page', 'label' => 'Main heading',                      'max' => 90,  'type' => 'text',     'default' => 'Create, Share & Celebrate Every Event'],
    'hero_highlight'     => ['group' => 'Home page', 'label' => 'Highlighted word in heading (leave empty for none)', 'max' => 30, 'type' => 'text', 'default' => 'Celebrate'],
    'hero_subtitle'      => ['group' => 'Home page', 'label' => 'Text under the heading',            'max' => 220, 'type' => 'textarea', 'default' => 'Weddings, birthdays, conferences or parties. Plan your event, invite your guests and manage every booking in one place.'],
    'dashboard_subtitle' => ['group' => 'Dashboard', 'label' => 'Dashboard welcome text',            'max' => 120, 'type' => 'text',     'default' => 'Manage your events and bookings from here.'],
    'footer_text'        => ['group' => 'Footer',    'label' => 'Footer description',                'max' => 250, 'type' => 'textarea', 'default' => 'Create, manage and celebrate every event with Eventora.'],
    'footer_copyright'   => ['group' => 'Footer',    'label' => 'Copyright line',                    'max' => 120, 'type' => 'text',     'default' => '© 2026 Eventora. All rights reserved.'],
];

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
    if ($action === 'delete_user') {
        $t = $pdo->prepare("SELECT role FROM users WHERE id=?");
        $t->execute([$id]);
        $role = $t->fetchColumn();
        if ($role === false)            flash('User not found.');
        elseif ($id === $uid)           flash('You cannot delete your own account.');
        elseif ($role === 'admin')      flash('Remove admin rights first, then delete this user.');
        else {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM bookings WHERE user_id=? OR event_id IN (SELECT id FROM events WHERE user_id=?)")->execute([$id, $id]);
            $pdo->prepare("DELETE FROM events WHERE user_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $pdo->commit();
            flash('User and all their data deleted.');
        }

    } elseif ($action === 'make_admin') {
        $pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$id]);
        flash('User is now an admin.');

    } elseif ($action === 'remove_admin') {
        if ($id === $uid) flash('You cannot remove your own admin rights.');
        else {
            $pdo->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$id]);
            flash('Admin rights removed.');
        }

    } elseif ($action === 'delete_event') {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM bookings WHERE event_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
        $pdo->commit();
        flash('Event deleted.');

    } elseif ($action === 'approve_booking') {
        // same capacity rule as the owner's accept: never confirm more seats than the event has
        $q = $pdo->prepare("SELECT b.seats, e.capacity,
                (SELECT COALESCE(SUM(seats),0) FROM bookings WHERE event_id=e.id AND status='confirmed') AS booked
                FROM bookings b JOIN events e ON e.id=b.event_id
                WHERE b.id=? AND b.status='pending'");
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row) {
            flash('Booking not found or already handled.');
        } elseif ($row['booked'] + $row['seats'] > $row['capacity']) {
            flash('Not enough seats left, cannot approve this booking.');
        } else {
            $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=? AND status='pending'")->execute([$id]);
            flash('Booking approved.');
        }

    } elseif ($action === 'cancel_booking') {
        $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND status IN ('pending','confirmed')")->execute([$id]);
        flash('Booking cancelled.');

    } elseif ($action === 'save_settings') {
        $up = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        foreach ($settingFields as $k => $field) {
            $up->execute([$k, trim(mb_substr((string)($_POST[$k] ?? ''), 0, $field['max']))]);
        }
        flash('Settings saved.');

    } elseif ($action === 'upload_slide') {
        $file  = $_FILES['slide'] ?? null;
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash('Upload failed. Please choose an image (check the size limit in php.ini).');
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            flash('Image is too large (max 5 MB).');
        } else {
            $info = @getimagesize($file['tmp_name']);
            $mime = $info['mime'] ?? '';
            if (!isset($types[$mime])) {
                flash('Only JPG, PNG or WEBP images are allowed.');
            } else {
                $dir = __DIR__ . '/pics/slides';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $name = 'slide_' . bin2hex(random_bytes(6)) . '.' . $types[$mime];
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
                    $next = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM slides")->fetchColumn();
                    $pdo->prepare("INSERT INTO slides (image, sort_order) VALUES (?,?)")->execute(['pics/slides/' . $name, $next]);
                    flash('Slide added.');
                } else {
                    flash('Could not save the image. Check the pics/slides folder permissions.');
                }
            }
        }

    } elseif ($action === 'delete_slide') {
        $s = $pdo->prepare("SELECT image FROM slides WHERE id=?");
        $s->execute([$id]);
        $img = $s->fetchColumn();
        if ($img !== false) {
            $pdo->prepare("DELETE FROM slides WHERE id=?")->execute([$id]);
            // only delete files that were uploaded through this panel
            if (strpos($img, 'pics/slides/') === 0) {
                $path = __DIR__ . '/pics/slides/' . basename($img);
                if (is_file($path)) @unlink($path);
            }
            flash('Slide removed.');
        }

    } elseif ($action === 'move_slide') {
        $all = array_map('intval', $pdo->query("SELECT id FROM slides ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN));
        $i   = array_search($id, $all, true);
        if ($i !== false) {
            $j = (($_POST['dir'] ?? '') === 'left') ? $i - 1 : $i + 1;
            if (isset($all[$j])) {
                $tmp = $all[$i]; $all[$i] = $all[$j]; $all[$j] = $tmp;
                $u = $pdo->prepare("UPDATE slides SET sort_order=? WHERE id=?");
                foreach ($all as $n => $sid) $u->execute([$n + 1, $sid]);
            }
        }
    }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('Database error. If this is about settings or slides, run the setup SQL first.');
    }
    header('Location: admin.php?tab=' . urlencode($tab)); exit;
}

// ---------- Page header + helpers (nothing is printed below until the view) ----------
require_once 'includes/header.php';

// ---------- Data ----------
$q      = trim($_GET['q'] ?? '');
$like   = '%' . $q . '%';
$status = $_GET['status'] ?? '';
$vis    = $_GET['vis'] ?? '';

$one = function ($sql) use ($pdo) { return (int)$pdo->query($sql)->fetchColumn(); };

if ($tab === 'overview') {
    $totalUsers   = $one("SELECT COUNT(*) FROM users");
    $totalEvents  = $one("SELECT COUNT(*) FROM events");
    $publicEv     = $one("SELECT COUNT(*) FROM events WHERE visibility='public'");
    $privateEv    = $one("SELECT COUNT(*) FROM events WHERE visibility='private'");
    $bs = $pdo->query("SELECT status, COUNT(*) FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $recentEvents = $pdo->query("SELECT e.id, e.title, e.event_date, e.visibility, u.name AS owner
                                 FROM events e JOIN users u ON u.id=e.user_id ORDER BY e.id DESC LIMIT 5")->fetchAll();
    $recentBook   = $pdo->query("SELECT b.status, b.seats, e.title, u.name AS user_name
                                 FROM bookings b JOIN events e ON e.id=b.event_id JOIN users u ON u.id=b.user_id
                                 ORDER BY b.id DESC LIMIT 5")->fetchAll();

} elseif ($tab === 'users') {
    $s = $pdo->prepare("SELECT u.id, u.name, u.email, u.role,
                        (SELECT COUNT(*) FROM events WHERE user_id=u.id) AS ev_count,
                        (SELECT COUNT(*) FROM bookings WHERE user_id=u.id) AS bk_count
                        FROM users u WHERE u.name LIKE ? OR u.email LIKE ? ORDER BY u.id DESC LIMIT 200");
    $s->execute([$like, $like]); $rows = $s->fetchAll();

} elseif ($tab === 'events') {
    $sql = "SELECT e.id, e.title, e.event_date, e.visibility, e.capacity, u.name AS owner,
            COALESCE((SELECT SUM(seats) FROM bookings WHERE event_id=e.id AND status='confirmed'),0) AS booked,
            (SELECT COUNT(*) FROM bookings WHERE event_id=e.id AND status='pending') AS pending_count
            FROM events e JOIN users u ON u.id=e.user_id WHERE e.title LIKE ?";
    $args = [$like];
    if (in_array($vis, ['public', 'private'], true)) { $sql .= " AND e.visibility=?"; $args[] = $vis; }
    $s = $pdo->prepare($sql . " ORDER BY e.event_date DESC LIMIT 200");
    $s->execute($args); $rows = $s->fetchAll();

} elseif ($tab === 'settings') {
    $cur = site_settings_all();
    try { $pdo->query("SELECT 1 FROM settings LIMIT 1"); $tblOk = true; }
    catch (PDOException $ex) { $tblOk = false; }

} elseif ($tab === 'slider') {
    try { $slides = $pdo->query("SELECT id, image FROM slides ORDER BY sort_order, id")->fetchAll(); }
    catch (PDOException $ex) { $slides = null; }

} else { // bookings
    $sql = "SELECT b.id, b.status, b.seats, e.title, u.name AS user_name
            FROM bookings b JOIN events e ON e.id=b.event_id JOIN users u ON u.id=b.user_id
            WHERE (e.title LIKE ? OR u.name LIKE ?)";
    $args = [$like, $like];
    if (in_array($status, ['pending', 'confirmed', 'rejected', 'cancelled'], true)) { $sql .= " AND b.status=?"; $args[] = $status; }
    $s = $pdo->prepare($sql . " ORDER BY b.id DESC LIMIT 200");
    $s->execute($args); $rows = $s->fetchAll();
}

$badge = ['pending'=>'bg-warning text-dark','confirmed'=>'bg-success','rejected'=>'bg-danger','cancelled'=>'bg-secondary'];

// small helper: one-button POST form
function adm_btn($action, $id, $tab, $label, $class = '', $confirm = '') {
    $on = $confirm ? ' onsubmit="return confirm(\'' . $confirm . '\')"' : '';
    return '<form method="post" class="d-inline"' . $on . '>' . csrf_field()
         . '<input type="hidden" name="action" value="' . $action . '">'
         . '<input type="hidden" name="id" value="' . (int)$id . '">'
         . '<input type="hidden" name="tab" value="' . e($tab) . '">'
         . '<button class="adm-btn ' . $class . '">' . $label . '</button></form> ';
}

?>
<style>
.adm-wrap { padding: 40px 0 70px; }
.adm-head {
    background: linear-gradient(135deg, #C76B8A, #8F405C); color: #fff; border-radius: 22px;
    padding: 30px 34px; margin-bottom: 24px; box-shadow: 0 10px 28px rgba(143, 64, 92, .25);
}
.adm-head h2 { font-weight: 800; margin: 0 0 4px; }
.adm-head p { margin: 0; color: #FBE9EE; }
.adm-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; }
.adm-tabs a {
    padding: 10px 20px; border-radius: 50px; background: #fff; border: 1px solid #F1D9DF; color: #5C4A4A;
    font-weight: 600; text-decoration: none; transition: .25s;
}
.adm-tabs a:hover { color: #B85C7A; border-color: #E89AAF; }
.adm-tabs a.active { background: linear-gradient(135deg, #E89AAF, #C76B8A); color: #fff; border-color: transparent; }
.adm-stat {
    background: #fff; border: 1px solid #F1D9DF; border-left: 5px solid #C76B8A; border-radius: 16px;
    padding: 20px; box-shadow: 0 5px 18px rgba(184, 92, 122, .08); height: 100%;
}
.adm-stat .num { font-size: 2rem; font-weight: 800; color: #4A3B3B; line-height: 1; }
.adm-stat .lbl { color: #7A6666; font-size: 14px; margin-top: 6px; }
.adm-stat.ok { border-left-color: #6BB78F; } .adm-stat.warn { border-left-color: #E5A94F; } .adm-stat.info { border-left-color: #7FA8D9; }
.adm-panel {
    background: #fff; border: 1px solid #F1D9DF; border-radius: 16px; padding: 22px;
    box-shadow: 0 5px 18px rgba(184, 92, 122, .08); height: 100%;
}
.adm-panel h4 { color: #B85C7A; font-weight: 700; font-size: 19px; margin-bottom: 14px; }
.adm-table { width: 100%; background: #fff; border-collapse: separate; border-spacing: 0; border-radius: 14px; overflow: hidden; box-shadow: 0 5px 18px rgba(184, 92, 122, .08); }
.adm-table th { background: #FFF0F4; color: #B85C7A; font-size: 14px; padding: 13px 14px; white-space: nowrap; border-bottom: 1px solid #F1D9DF; text-align: left; }
.adm-table td { padding: 12px 14px; border-bottom: 1px solid #F8E8EC; color: #4A3B3B; vertical-align: middle; }
.adm-table tr:last-child td { border-bottom: 0; }
.adm-table tbody tr:hover { background: #FFFAFB; }
.adm-table a { color: #B85C7A; font-weight: 600; text-decoration: none; }
.adm-btn {
    background: linear-gradient(135deg, #E89AAF, #C76B8A); color: #fff; border: 0; border-radius: 7px;
    padding: 5px 12px; font-size: 13px; font-weight: 600; cursor: pointer; margin: 2px 0; transition: .25s;
}
.adm-btn:hover { transform: translateY(-2px); }
.adm-btn.danger { background: #fff; color: #C0392B; border: 1px solid #E7B4B0; }
.adm-btn.danger:hover { background: #C0392B; color: #fff; }
.adm-btn.dark { background: #4A3B3B; }
.adm-filter { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.adm-filter input, .adm-filter select { border: 1px solid #F1D9DF; border-radius: 10px; padding: 9px 14px; background: #fff; min-width: 200px; }
.adm-item { display: flex; justify-content: space-between; gap: 10px; padding: 11px 0; border-bottom: 1px solid #F8E8EC; }
.adm-item:last-child { border-bottom: 0; }
.adm-item .meta { font-size: 13px; color: #7A6666; }
.adm-empty { text-align: center; color: #A89599; padding: 26px 0; }
.adm-form label { display: block; font-weight: 600; color: #5C4A4A; margin: 14px 0 6px; }
.adm-form input[type=text], .adm-form textarea { width: 100%; border: 1px solid #F1D9DF; border-radius: 10px; padding: 10px 14px; background: #fff; }
.adm-group { margin: 26px 0 0; padding-top: 16px; border-top: 1px solid #F8E8EC; }
.adm-group:first-of-type { margin-top: 0; padding-top: 0; border-top: 0; }
.adm-hint { color: #7A6666; font-size: 13px; margin: 12px 0 0; }
.adm-slide { background: #fff; border: 1px solid #F1D9DF; border-radius: 14px; overflow: hidden; box-shadow: 0 5px 18px rgba(184, 92, 122, .08); }
.adm-slide img { width: 100%; height: 150px; object-fit: cover; display: block; }
.adm-slide .bar { display: flex; gap: 6px; align-items: center; padding: 10px; flex-wrap: wrap; }
.adm-slide .bar form { margin: 0; }
@media (max-width: 767px) { .adm-head { padding: 22px; } .adm-table { display: block; overflow-x: auto; } }
</style>

<section class="adm-wrap"><div class="container">

    <div class="adm-head">
        <h2>🛡️ Admin Dashboard</h2>
        <p>Manage users, events and bookings across the whole website.</p>
        <a href="profile.php" class="adm-btn" style="display:inline-block;margin-top:14px;padding:8px 20px;background:#fff;color:#8F405C;text-decoration:none">👤 My Profile</a>
    </div>

    <div class="adm-tabs">
        <?php foreach ($tabs as $k => $label): ?>
            <a href="admin.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

<?php if ($tab === 'overview'): ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="adm-stat"><div class="num"><?= $totalUsers ?></div><div class="lbl">Total Users</div></div></div>
        <div class="col-6 col-lg-3"><div class="adm-stat info"><div class="num"><?= $totalEvents ?></div><div class="lbl">Total Events (<?= $publicEv ?> public / <?= $privateEv ?> private)</div></div></div>
        <div class="col-6 col-lg-3"><div class="adm-stat warn"><div class="num"><?= (int)($bs['pending'] ?? 0) ?></div><div class="lbl">Pending Bookings</div></div></div>
        <div class="col-6 col-lg-3"><div class="adm-stat ok"><div class="num"><?= (int)($bs['confirmed'] ?? 0) ?></div><div class="lbl">Confirmed Bookings</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6"><div class="adm-panel">
            <h4>🆕 Latest Events</h4>
            <?php if (!$recentEvents): ?><div class="adm-empty">No events yet.</div><?php endif; ?>
            <?php foreach ($recentEvents as $r): ?>
                <div class="adm-item">
                    <div><a href="event-details.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['title']) ?></strong></a>
                        <div class="meta">by <?= e($r['owner']) ?> · <?= fmt_date($r['event_date']) ?></div></div>
                    <span class="badge bg-secondary align-self-center"><?= e(ucfirst($r['visibility'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div></div>

        <div class="col-lg-6"><div class="adm-panel">
            <h4>🎟️ Latest Bookings</h4>
            <?php if (!$recentBook): ?><div class="adm-empty">No bookings yet.</div><?php endif; ?>
            <?php foreach ($recentBook as $r): ?>
                <div class="adm-item">
                    <div><strong><?= e($r['title']) ?></strong>
                        <div class="meta"><?= e($r['user_name']) ?> · <?= (int)$r['seats'] ?> seat(s)</div></div>
                    <span class="badge <?= $badge[$r['status']] ?? 'bg-secondary' ?> align-self-center"><?= e(ucfirst($r['status'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>

<?php elseif ($tab === 'users'): ?>

    <form class="adm-filter" method="get">
        <input type="hidden" name="tab" value="users">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or email">
        <button class="adm-btn">Search</button>
    </form>
    <div class="table-responsive">
    <table class="adm-table">
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Events</th><th>Bookings</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="adm-empty">No users found.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): $isMe = ((int)$r['id'] === $uid); ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['name']) ?><?= $isMe ? ' <span class="badge bg-info text-dark">You</span>' : '' ?></td>
                <td><?= e($r['email']) ?></td>
                <td><span class="badge <?= $r['role'] === 'admin' ? 'bg-dark' : 'bg-secondary' ?>"><?= e(ucfirst($r['role'])) ?></span></td>
                <td><?= (int)$r['ev_count'] ?></td>
                <td><?= (int)$r['bk_count'] ?></td>
                <td>
                <?php if (!$isMe): ?>
                    <?= $r['role'] === 'admin'
                        ? adm_btn('remove_admin', $r['id'], 'users', 'Remove admin', 'dark', 'Remove admin rights from this user?')
                        : adm_btn('make_admin', $r['id'], 'users', 'Make admin', '', 'Give admin rights to this user?') ?>
                    <?php if ($r['role'] !== 'admin') echo adm_btn('delete_user', $r['id'], 'users', 'Delete', 'danger', 'Delete this user with all their events and bookings?'); ?>
                <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

<?php elseif ($tab === 'events'): ?>

    <form class="adm-filter" method="get">
        <input type="hidden" name="tab" value="events">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search event title">
        <select name="vis">
            <option value="">All types</option>
            <option value="public"  <?= $vis === 'public'  ? 'selected' : '' ?>>Public</option>
            <option value="private" <?= $vis === 'private' ? 'selected' : '' ?>>Private</option>
        </select>
        <button class="adm-btn">Filter</button>
        <a href="create-event.php" class="adm-btn" style="text-decoration:none;margin-left:auto;display:inline-block">+ Add Event</a>
    </form>
    <div class="table-responsive">
    <table class="adm-table">
        <thead><tr><th>#</th><th>Event</th><th>Organizer</th><th>Date</th><th>Type</th><th>Booked / Capacity</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="adm-empty">No events found.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><a href="event-details.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a>
                    <?php if ($r['pending_count'] > 0): ?><span class="badge bg-warning text-dark"><?= (int)$r['pending_count'] ?> pending</span><?php endif; ?></td>
                <td><?= e($r['owner']) ?></td>
                <td><?= fmt_date($r['event_date']) ?></td>
                <td><span class="badge <?= $r['visibility'] === 'private' ? 'bg-dark' : 'bg-secondary' ?>"><?= e(ucfirst($r['visibility'])) ?></span></td>
                <td><?= (int)$r['booked'] ?> / <?= (int)$r['capacity'] ?></td>
                <td><?= adm_btn('delete_event', $r['id'], 'events', 'Delete', 'danger', 'Delete this event and all its bookings?') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

<?php elseif ($tab === 'settings'): ?>

    <?php if (!$tblOk): ?>
        <div class="adm-panel"><h4>One-time setup needed</h4>
            <p>Run the setup SQL (the <code>settings</code> and <code>slides</code> tables) in phpMyAdmin, then refresh this page.</p></div>
    <?php else: ?>
    <form method="post" class="adm-panel adm-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="tab" value="settings">
        <?php $lastGroup = ''; foreach ($settingFields as $k => $field): ?>
            <?php if ($field['group'] !== $lastGroup): $lastGroup = $field['group']; ?>
                <h4 class="adm-group"><?= e($lastGroup) ?></h4>
            <?php endif; ?>
            <?php $val = array_key_exists($k, $cur) ? $cur[$k] : $field['default']; ?>
            <label for="f_<?= $k ?>"><?= e($field['label']) ?></label>
            <?php if ($field['type'] === 'textarea'): ?>
                <textarea id="f_<?= $k ?>" name="<?= $k ?>" rows="3" maxlength="<?= (int)$field['max'] ?>"><?= e($val) ?></textarea>
            <?php else: ?>
                <input id="f_<?= $k ?>" type="text" name="<?= $k ?>" maxlength="<?= (int)$field['max'] ?>" value="<?= e($val) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <button class="adm-btn mt-3" style="padding:10px 28px">Save changes</button>
        <p class="adm-hint">Changes appear on the website straight away. If you empty a field, its default text is used.</p>
    </form>
    <?php endif; ?>

<?php elseif ($tab === 'slider'): ?>

    <?php if ($slides === null): ?>
        <div class="adm-panel"><h4>One-time setup needed</h4>
            <p>Run the setup SQL (the <code>settings</code> and <code>slides</code> tables) in phpMyAdmin, then refresh this page.</p></div>
    <?php else: ?>
    <div class="adm-panel mb-4">
        <h4>➕ Add a slide</h4>
        <form method="post" enctype="multipart/form-data" class="adm-filter" style="margin-bottom:6px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_slide">
            <input type="hidden" name="tab" value="slider">
            <input type="file" name="slide" accept="image/jpeg,image/png,image/webp" required>
            <button class="adm-btn" style="padding:9px 24px">Upload</button>
        </form>
        <p class="adm-hint" style="margin:0">JPG, PNG or WEBP, up to 5 MB. Wide images (about 1920 x 700) look best.</p>
    </div>

    <?php if (!$slides): ?><div class="adm-empty">No slides yet. The website shows its default pictures until you upload one.</div><?php endif; ?>
    <div class="row g-3">
        <?php foreach ($slides as $n => $sl): ?>
        <div class="col-md-4"><div class="adm-slide">
            <img src="<?= e($sl['image']) ?>" alt="Slide <?= $n + 1 ?>">
            <div class="bar">
                <span class="badge bg-secondary">#<?= $n + 1 ?></span>
                <?php foreach (['left' => '←', 'right' => '→'] as $dir => $arrow): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move_slide">
                    <input type="hidden" name="id" value="<?= (int)$sl['id'] ?>">
                    <input type="hidden" name="dir" value="<?= $dir ?>">
                    <input type="hidden" name="tab" value="slider">
                    <button class="adm-btn dark"><?= $arrow ?></button>
                </form>
                <?php endforeach; ?>
                <?= adm_btn('delete_slide', $sl['id'], 'slider', 'Remove', 'danger', 'Remove this slide from the slider?') ?>
            </div>
        </div></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php else: ?>

    <form class="adm-filter" method="get">
        <input type="hidden" name="tab" value="bookings">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search event or user">
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach (['pending','confirmed','rejected','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="adm-btn">Filter</button>
    </form>
    <div class="table-responsive">
    <table class="adm-table">
        <thead><tr><th>#</th><th>User</th><th>Event</th><th>Seats</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="adm-empty">No bookings found.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['user_name']) ?></td>
                <td><?= e($r['title']) ?></td>
                <td><?= (int)$r['seats'] ?></td>
                <td><span class="badge <?= $badge[$r['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                <td>
                    <?php if ($r['status'] === 'pending') echo adm_btn('approve_booking', $r['id'], 'bookings', 'Approve'); ?>
                    <?php if (in_array($r['status'], ['pending', 'confirmed'])) echo adm_btn('cancel_booking', $r['id'], 'bookings', 'Cancel', 'danger', 'Cancel this booking?'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

<?php endif; ?>

</div></section>
<?php include 'includes/footer.php'; ?>