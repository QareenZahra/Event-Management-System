<?php
$page = 'booking'; $title = 'My Bookings | Eventora';
require_once 'includes/functions.php';
require_login();
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bid    = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel_booking') {
        $pdo->prepare("UPDATE bookings SET status='cancelled'
                       WHERE id=? AND user_id=? AND status IN ('pending','confirmed')")
            ->execute([$bid, $uid]);
        flash('Booking cancelled.');

    } elseif ($action === 'delete_event') {
        $pdo->prepare("DELETE FROM bookings WHERE event_id IN (SELECT id FROM events WHERE id=? AND user_id=?)")
            ->execute([$bid, $uid]);
        $pdo->prepare("DELETE FROM events WHERE id=? AND user_id=?")->execute([$bid, $uid]);
        flash('Event deleted.');

    } elseif ($action === 'accept_booking' || $action === 'reject_booking') {
        // only pending bookings of my own events
        $q = $pdo->prepare("SELECT b.seats, e.capacity,
                (SELECT COALESCE(SUM(seats),0) FROM bookings WHERE event_id=e.id AND status='confirmed') AS booked
                FROM bookings b JOIN events e ON e.id=b.event_id
                WHERE b.id=? AND e.user_id=? AND b.status='pending'");
        $q->execute([$bid, $uid]);
        $row = $q->fetch();

        if ($row) {
            if ($action === 'accept_booking') {
                if ($row['booked'] + $row['seats'] > $row['capacity']) {
                    flash('Not enough seats left, cannot accept this booking.');
                } else {
                    $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?")->execute([$bid]);
                    flash('Booking accepted.');
                }
            } else {
                $pdo->prepare("UPDATE bookings SET status='rejected' WHERE id=?")->execute([$bid]);
                flash('Booking rejected.');
            }
        }

    } elseif ($action === 'make_link') {
        // create a token for a private event (if it does not have one yet)
        $pdo->prepare("UPDATE events SET share_token=? WHERE id=? AND user_id=? AND visibility='private'
                       AND (share_token IS NULL OR share_token='')")
            ->execute([bin2hex(random_bytes(16)), $bid, $uid]);
        flash('Share link ready.');
    }
    header('Location: booking.php'); exit;
}

// My bookings
$b = $pdo->prepare("SELECT b.*, e.title, e.event_date, e.event_time, e.location, e.image
                    FROM bookings b JOIN events e ON e.id=b.event_id WHERE b.user_id=? ORDER BY e.event_date");
$b->execute([$uid]); $bookings = $b->fetchAll();

// Events I created
$m = $pdo->prepare("SELECT e.*, COALESCE((SELECT SUM(seats) FROM bookings WHERE event_id=e.id AND status='confirmed'),0) AS booked,
                    (SELECT COUNT(*) FROM bookings WHERE event_id=e.id AND status='pending') AS pending_count
                    FROM events e WHERE e.user_id=? ORDER BY e.event_date");
$m->execute([$uid]); $mine = $m->fetchAll();

// Pending requests on my events (change u.name if your users column is named differently)
$p = $pdo->prepare("SELECT b.id, b.seats, e.title, u.name AS user_name
                    FROM bookings b
                    JOIN events e ON e.id=b.event_id
                    JOIN users u ON u.id=b.user_id
                    WHERE e.user_id=? AND b.status='pending' ORDER BY b.id DESC");
$p->execute([$uid]); $requests = $p->fetchAll();

$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
      . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$badge = ['pending'=>'bg-warning text-dark','confirmed'=>'bg-success','rejected'=>'bg-danger','cancelled'=>'bg-secondary'];
require_once 'includes/header.php'; ?>
<section class="upcoming-events"><div class="container">

    <!-- MY BOOKINGS -->
    <div class="section-heading"><span class="section-tag">🎟️ MY BOOKINGS</span><h2>Events I'm Attending</h2></div>
    <?php if (!$bookings): ?><p class="text-center text-muted">No bookings yet. <a href="events.php">Browse events</a></p><?php endif; ?>
    <div class="table-responsive"><?php if ($bookings): ?>
    <table class="table eventora-table align-middle"><thead><tr><th>Event</th><th>Date</th><th>Location</th><th>Seats</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($bookings as $r): ?>
        <tr><td><?= e($r['title']) ?></td>
            <td><?= fmt_date($r['event_date']) ?> · <?= fmt_time($r['event_time']) ?></td>
            <td><?= e($r['location']) ?></td><td><?= (int)$r['seats'] ?></td>
            <td><span class="badge <?= $badge[$r['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            <td><?php if (in_array($r['status'], ['pending','confirmed'])): ?>
                <form method="post" onsubmit="return confirm('Cancel this booking?')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_booking"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="mini-btn danger">Cancel</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?></div>

    <!-- BOOKING REQUESTS (for the event owner) -->
    <?php if ($requests): ?>
    <div class="section-heading mt-5"><span class="section-tag">📩 REQUESTS</span><h2>Pending Booking Requests</h2></div>
    <div class="table-responsive">
    <table class="table eventora-table align-middle"><thead><tr><th>User</th><th>Event</th><th>Seats</th><th></th></tr></thead><tbody>
    <?php foreach ($requests as $r): ?>
        <tr><td><?= e($r['user_name']) ?></td><td><?= e($r['title']) ?></td><td><?= (int)$r['seats'] ?></td>
            <td class="d-flex gap-2">
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="accept_booking"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="mini-btn">Accept</button></form>
                <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject_booking"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="mini-btn danger">Reject</button></form>
            </td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>

    <!-- EVENTS I CREATED -->
    <div class="section-heading mt-5"><span class="section-tag">⚙️ MANAGE</span><h2>Events I Created</h2></div>
    <?php if (!$mine): ?><p class="text-center text-muted">You haven't created any events. <a href="create-event.php">Create one</a></p><?php endif; ?>
    <div class="table-responsive"><?php if ($mine): ?>
    <table class="table eventora-table align-middle"><thead><tr><th>Event</th><th>Date</th><th>Type</th><th>Booked / Capacity</th><th></th></tr></thead><tbody>
    <?php foreach ($mine as $r): ?>
        <tr><td><a href="event-details.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a>
                <?php if ($r['pending_count'] > 0): ?><span class="badge bg-warning text-dark"><?= (int)$r['pending_count'] ?> pending</span><?php endif; ?></td>
            <td><?= fmt_date($r['event_date']) ?></td><td><?= e($r['visibility']) ?></td>
            <td><?= (int)$r['booked'] ?> / <?= (int)$r['capacity'] ?></td>
            <td class="d-flex gap-2 flex-wrap">
                <?php if ($r['visibility'] === 'private'): ?>
                    <?php if (!empty($r['share_token'])):
                        $link = $base . '/event-details.php?id=' . (int)$r['id'] . '&token=' . $r['share_token']; ?>
                        <button type="button" class="mini-btn" data-link="<?= e($link) ?>" onclick="copyLink(this)">🔗 Share Link</button>
                    <?php else: ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="make_link"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="mini-btn">🔗 Create Share Link</button></form>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="edit-event.php?id=<?= (int)$r['id'] ?>" class="mini-btn text-decoration-none" style="color:#fff">✏️ Edit</a>
                <form method="post" onsubmit="return confirm('Delete this event?')"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_event"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="mini-btn danger">Delete</button></form>
            </td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?></div>
</div></section>

<script>
function copyLink(btn) {
    const link = btn.dataset.link;
    const done = () => { const t = btn.textContent; btn.textContent = '✅ Copied!'; setTimeout(() => btn.textContent = t, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(link).then(done);
    else { prompt('Copy this link:', link); }
}
</script>
<?php include 'includes/footer.php'; ?>