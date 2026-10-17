<?php
$page = 'create'; $css = 'create-event'; $title = 'Create Event | Eventora';
require_once 'includes/functions.php';
require_login();
$errors = []; $cats = ['Wedding','Birthday','Conference','Music','Workshop','Party'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $t = trim($_POST['title'] ?? ''); $cat = $_POST['category'] ?? ''; $desc = trim($_POST['description'] ?? '');
    $d = $_POST['event_date'] ?? ''; $tm = $_POST['event_time'] ?? ''; $loc = trim($_POST['location'] ?? '');
    $cap = (int)($_POST['capacity'] ?? 0); $vis = ($_POST['visibility'] ?? '') === 'private' ? 'private' : 'public';
    if ($t === '' || $loc === '') $errors[] = 'Title and location are required.';
    if (!in_array($cat, $cats, true)) $errors[] = 'Choose a valid category.';
    if (!$d || strtotime($d) < strtotime('today')) $errors[] = 'Pick a date today or later.';
    if (!$tm) $errors[] = 'Pick a time.';
    if ($cap < 1 || $cap > 100000) $errors[] = 'Capacity must be at least 1.';

    $image = 'pics/technology.jpg';
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $ok = in_array($ext, ['jpg','jpeg','png','webp'], true)
              && @getimagesize($_FILES['image']['tmp_name']) && $_FILES['image']['size'] <= 3*1024*1024;
        if (!$ok) $errors[] = 'Image must be a JPG/PNG/WEBP under 3MB.';
        else {
            // create the upload folder automatically and check every step
            $dir  = __DIR__ . '/pics/uploads';
            $name = bin2hex(random_bytes(8)) . '.' . $ext;
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                $errors[] = 'Could not create the upload folder (pics/uploads). Check folder permissions.';
            } elseif (!@move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $name)) {
                $errors[] = 'Image upload failed. Please try again.';
            } else {
                $image = 'pics/uploads/' . $name;
            }
        }
    }
    if (!$errors) {
        // private events get a share token right away, so they are never open without a link
        $token = ($vis === 'private') ? bin2hex(random_bytes(16)) : null;
        $pdo->prepare("INSERT INTO events (user_id,title,category,description,event_date,event_time,location,image,capacity,visibility,share_token)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$_SESSION['user_id'],$t,$cat,$desc,$d,$tm,$loc,$image,$cap,$vis,$token]);
        flash('Event created successfully! 🎉');
        header('Location: event-details.php?id=' . $pdo->lastInsertId()); exit;
    }
}
require_once 'includes/header.php'; ?>
<section class="form-section"><div class="container"><div class="form-card wide">
    <h2>Create Your Event</h2><p class="sub">Fill in the details and you're ready to go.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger eventora-alert"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
        <label>Event Title</label><input class="form-control" name="title" value="<?= e($_POST['title'] ?? '') ?>" required>
        <div class="row"><div class="col-md-6"><label>Category</label>
            <select class="form-select" name="category"><?php foreach ($cats as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label>Visibility</label>
            <select class="form-select" name="visibility"><option value="public">Public (listed for everyone)</option><option value="private">Private (link only)</option></select></div></div>
        <div class="row"><div class="col-md-6"><label>Date</label><input class="form-control" type="date" name="event_date" min="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-6"><label>Time</label><input class="form-control" type="time" name="event_time" required></div></div>
        <div class="row"><div class="col-md-8"><label>Location</label><input class="form-control" name="location" value="<?= e($_POST['location'] ?? '') ?>" required></div>
        <div class="col-md-4"><label>Capacity</label><input class="form-control" type="number" name="capacity" min="1" value="50" required></div></div>
        <label>Description</label><textarea class="form-control" name="description" rows="4"><?= e($_POST['description'] ?? '') ?></textarea>
        <label>Cover Image (optional)</label><input class="form-control" type="file" name="image" accept="image/*">
        <button class="form-btn">Create Event</button>
    </form>
</div></div></section>
<?php include 'includes/footer.php'; ?>
