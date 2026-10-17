<?php
$page = 'create'; $css = 'create-event'; $title = 'Edit Event | Eventora';
require_once 'includes/functions.php';
require_login();
$uid  = (int)$_SESSION['user_id'];
$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$cats = ['Wedding','Birthday','Conference','Music','Workshop','Party'];

// Load the event together with the number of confirmed seats
$st = $pdo->prepare("SELECT e.*, COALESCE((SELECT SUM(seats) FROM bookings WHERE event_id=e.id AND status='confirmed'),0) AS booked
                     FROM events e WHERE e.id=?");
$st->execute([$id]);
$event = $st->fetch();
if (!$event) { http_response_code(404); exit('Event not found.'); }

// Only the owner or an admin (role read from the database) may edit
$r = $pdo->prepare("SELECT role FROM users WHERE id=?");
$r->execute([$uid]);
$isAdmin = ($r->fetchColumn() === 'admin');
if ((int)$event['user_id'] !== $uid && !$isAdmin) {
    http_response_code(403);
    exit('You can only edit your own events.');
}

$errors = [];
$f = [ // values shown in the form
    'title' => $event['title'], 'category' => $event['category'], 'description' => $event['description'],
    'event_date' => $event['event_date'], 'event_time' => substr((string)$event['event_time'], 0, 5),
    'location' => $event['location'], 'capacity' => $event['capacity'], 'visibility' => $event['visibility'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f['title']       = trim($_POST['title'] ?? '');
    $f['category']    = $_POST['category'] ?? '';
    $f['description'] = trim($_POST['description'] ?? '');
    $f['event_date']  = $_POST['event_date'] ?? '';
    $f['event_time']  = $_POST['event_time'] ?? '';
    $f['location']    = trim($_POST['location'] ?? '');
    $f['capacity']    = (int)($_POST['capacity'] ?? 0);
    $f['visibility']  = ($_POST['visibility'] ?? '') === 'private' ? 'private' : 'public';

    if ($f['title'] === '' || $f['location'] === '') $errors[] = 'Title and location are required.';
    if (!in_array($f['category'], $cats, true)) $errors[] = 'Choose a valid category.';
    if (!$f['event_date'] || !strtotime($f['event_date'])) {
        $errors[] = 'Pick a valid date.';
    } elseif ($f['event_date'] !== $event['event_date'] && strtotime($f['event_date']) < strtotime('today')) {
        $errors[] = 'A new date must be today or later.';
    }
    if (!$f['event_time']) $errors[] = 'Pick a time.';
    if ($f['capacity'] < 1 || $f['capacity'] > 100000) {
        $errors[] = 'Capacity must be at least 1.';
    } elseif ($f['capacity'] < (int)$event['booked']) {
        $errors[] = 'Capacity cannot be lower than the ' . (int)$event['booked'] . ' seats already confirmed.';
    }

    if (!$errors) {
        // a private event must always have a token
        $token = $event['share_token'] ?? '';
        if ($f['visibility'] === 'private' && $token === '') $token = bin2hex(random_bytes(16));

        $pdo->prepare("UPDATE events SET title=?, category=?, description=?, event_date=?, event_time=?,
                       location=?, capacity=?, visibility=?, share_token=? WHERE id=?")
            ->execute([$f['title'], $f['category'], $f['description'], $f['event_date'], $f['event_time'],
                       $f['location'], $f['capacity'], $f['visibility'], $token !== '' ? $token : null, $id]);
        flash('Event updated successfully.');
        header('Location: event-details.php?id=' . $id); exit;
    }
}
require_once 'includes/header.php'; ?>
<section class="form-section"><div class="container"><div class="form-card wide">
    <h2>Edit Event</h2><p class="sub">Change the details below and save.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger eventora-alert"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <label>Event Title</label><input class="form-control" name="title" value="<?= e($f['title']) ?>" required>
        <div class="row"><div class="col-md-6"><label>Category</label>
            <select class="form-select" name="category"><?php foreach ($cats as $c): ?><option <?= $f['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label>Visibility</label>
            <select class="form-select" name="visibility">
                <option value="public"  <?= $f['visibility'] === 'public'  ? 'selected' : '' ?>>Public (listed for everyone)</option>
                <option value="private" <?= $f['visibility'] === 'private' ? 'selected' : '' ?>>Private (link only)</option>
            </select></div></div>
        <div class="row"><div class="col-md-6"><label>Date</label><input class="form-control" type="date" name="event_date" value="<?= e($f['event_date']) ?>" required></div>
        <div class="col-md-6"><label>Time</label><input class="form-control" type="time" name="event_time" value="<?= e($f['event_time']) ?>" required></div></div>
        <div class="row"><div class="col-md-8"><label>Location</label><input class="form-control" name="location" value="<?= e($f['location']) ?>" required></div>
        <div class="col-md-4"><label>Capacity</label><input class="form-control" type="number" name="capacity" min="<?= max(1, (int)$event['booked']) ?>" value="<?= (int)$f['capacity'] ?>" required></div></div>
        <label>Description</label><textarea class="form-control" name="description" rows="4"><?= e($f['description']) ?></textarea>
        <button class="form-btn">Save Changes</button>
        <p class="form-alt"><a href="event-details.php?id=<?= $id ?>">Cancel</a></p>
    </form>
</div></div></section>
<?php include 'includes/footer.php'; ?>
