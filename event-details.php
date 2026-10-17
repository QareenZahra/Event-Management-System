<?php
$page = 'events'; $title = 'Event Details | Eventora';
require_once 'includes/functions.php';

$id  = (int)($_GET['id'] ?? $_POST['event_id'] ?? 0);
$uid = (int)($_SESSION['user_id'] ?? 0);

// Fetch event
$st = $pdo->prepare("SELECT e.*, COALESCE((SELECT SUM(seats) FROM bookings WHERE event_id=e.id AND status='confirmed'),0) AS booked
                     FROM events e WHERE e.id=?");
$st->execute([$id]);
$event = $st->fetch();
if (!$event) { http_response_code(404); exit('Event not found.'); }

// Private event: only the owner or someone with the correct token can view it
$token   = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$isOwner = $uid && (int)$event['user_id'] === $uid;
// An empty stored token or an empty given token never counts as a match
$stored  = (string)($event['share_token'] ?? '');
$tokenOk = ($stored !== '' && $token !== '' && hash_equals($stored, $token));
if ($event['visibility'] === 'private' && !$isOwner && !$tokenOk) {
    http_response_code(403);
    exit('This is a private event. It can only be opened with an invite link.');
}

$remaining = max(0, (int)$event['capacity'] - (int)$event['booked']);
$back = 'event-details.php?id=' . $id . ($token !== '' ? '&token=' . urlencode($token) : '');

// Booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    csrf_check();
    $uid   = (int)$_SESSION['user_id'];
    $seats = max(1, (int)($_POST['seats'] ?? 1));

    if ((int)$event['user_id'] === $uid) {
        flash('You cannot book your own event.');
    } else {
        // This user's existing booking for this event (any status), because the uniq_booking key
        // (user_id + event_id) allows only one row per user per event
        $chk = $pdo->prepare("SELECT id, status FROM bookings WHERE event_id=? AND user_id=? LIMIT 1");
        $chk->execute([$id, $uid]);
        $existing = $chk->fetch();

        if ($existing && in_array($existing['status'], ['pending', 'confirmed'])) {
            flash('You already have a booking for this event.');
        } elseif ($seats > $remaining) {
            flash('That many seats are not available.');
        } else {
            try {
                if ($existing) {
                    // previously cancelled/rejected: set the same row back to pending
                    $pdo->prepare("UPDATE bookings SET status='pending', seats=? WHERE id=? AND user_id=?")
                        ->execute([$seats, $existing['id'], $uid]);
                } else {
                    $pdo->prepare("INSERT INTO bookings (user_id, event_id, seats, status) VALUES (?,?,?, 'pending')")
                        ->execute([$uid, $id, $seats]);
                }
                flash('Booking request sent. Please wait for the owner approval.');
            } catch (PDOException $ex) {
                // avoid a crash if a duplicate slips through (e.g. double click)
                flash('You already have a booking for this event.');
            }
        }
    }
    header('Location: ' . $back); exit;
}

// This user's current booking
$my = null;
if ($uid) {
    $q = $pdo->prepare("SELECT * FROM bookings WHERE event_id=? AND user_id=? AND status IN ('pending','confirmed') ORDER BY id DESC LIMIT 1");
    $q->execute([$id, $uid]);
    $my = $q->fetch();
}

require_once 'includes/header.php'; ?>
<section class="upcoming-events"><div class="container">
    <div class="section-heading">
        <span class="section-tag"><?= $event['visibility'] === 'private' ? '🔒 PRIVATE EVENT' : '🎉 EVENT' ?></span>
        <h2><?= e($event['title']) ?></h2>
    </div>

    <div class="row g-4">
        <?php if (!empty($event['image'])): ?>
        <div class="col-md-5">
            <img src="<?= e($event['image']) ?>" alt="<?= e($event['title']) ?>" class="img-fluid rounded">
        </div>
        <?php endif; ?>

        <div class="<?= !empty($event['image']) ? 'col-md-7' : 'col-12' ?>">
            <?php if (!empty($event['description'])): ?>
                <p><?= nl2br(e($event['description'])) ?></p>
            <?php endif; ?>
            <ul class="list-unstyled">
                <li>📅 <?= fmt_date($event['event_date']) ?> · <?= fmt_time($event['event_time']) ?></li>
                <li>📍 <?= e($event['location']) ?></li>
                <li>💺 <?= $remaining ?> seats available (<?= (int)$event['capacity'] ?> total)</li>
            </ul>

            <?php if ($isOwner): ?>
                <p class="text-muted">This is your event. Manage requests in <a href="booking.php">My Bookings</a>.</p>
                <p><a href="edit-event.php?id=<?= $id ?>" class="btn btn-primary">✏️ Edit Event</a></p>

            <?php elseif ($my): ?>
                <p>Your booking:
                    <span class="badge <?= $my['status'] === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= e(ucfirst($my['status'])) ?></span>
                    (<?= (int)$my['seats'] ?> seats)</p>
                <?php if ($my['status'] === 'pending'): ?>
                    <p class="text-muted">Waiting for the owner's approval.</p>
                <?php endif; ?>

            <?php elseif ($remaining < 1): ?>
                <p class="text-danger">This event is fully booked.</p>

            <?php elseif (!$uid): ?>
                <p><a href="login.php" class="btn btn-primary">Log in to book</a></p>

            <?php else: ?>
                <form method="post" class="d-flex gap-2 align-items-center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="number" name="seats" value="1" min="1" max="<?= $remaining ?>" class="form-control" style="max-width:100px">
                    <button class="btn btn-primary">Request Booking</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div></section>
<?php include 'includes/footer.php'; ?>