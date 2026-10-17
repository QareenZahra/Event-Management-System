<?php
$page = 'events'; $title = 'Events | Eventora';
require_once 'includes/header.php';
$q = trim($_GET['q'] ?? ''); $cat = trim($_GET['category'] ?? '');
$sql = "SELECT * FROM events WHERE visibility='public' AND event_date >= CURDATE()"; $p = [];
if ($q !== '')   { $sql .= " AND (title LIKE ? OR location LIKE ?)"; $p[] = "%$q%"; $p[] = "%$q%"; }
if ($cat !== '') { $sql .= " AND category = ?"; $p[] = $cat; }
$sql .= " ORDER BY event_date, event_time";
$st = $pdo->prepare($sql); $st->execute($p); $events = $st->fetchAll();
$cats = ['Wedding','Birthday','Conference','Music','Workshop','Party'];
?>
<section class="upcoming-events"><div class="container">
    <div class="section-heading"><span class="section-tag">🌍 PUBLIC EVENTS</span>
        <h2>All Events</h2><p>Search and filter public events.</p></div>
    <form class="filter-bar" method="get">
        <input class="form-control" name="q" placeholder="Search by title or location" value="<?= e($q) ?>">
        <select class="form-select" name="category">
            <option value="">All Categories</option>
            <?php foreach ($cats as $c): ?><option <?= $cat===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
        </select>
        <button class="form-btn">Search</button>
    </form>
    <div class="row g-4">
        <?php if (!$events): ?><p class="text-center text-muted">No events found.</p><?php endif; ?>
        <?php foreach ($events as $ev) include 'includes/event_card.php'; ?>
    </div>
</div></section>
<?php include 'includes/footer.php'; ?>
