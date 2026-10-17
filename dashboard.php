<?php
$page = 'dashboard'; $title = 'Dashboard | Eventora';
require_once 'includes/functions.php';
require_login();
date_default_timezone_set('Asia/Karachi');
$uid = (int)$_SESSION['user_id'];

// User's name (change `name` if your users column is named differently)
$u = $pdo->prepare("SELECT name FROM users WHERE id=?");
$u->execute([$uid]);
$userName  = $u->fetchColumn() ?: 'there';
$firstName = explode(' ', trim($userName))[0];

$h = (int)date('G');
$greet = $h < 12 ? 'Good Morning' : ($h < 17 ? 'Good Afternoon' : 'Good Evening');

// Counters
$count = function ($sql) use ($pdo, $uid) {
    $s = $pdo->prepare($sql); $s->execute([$uid]); return (int)$s->fetchColumn();
};
$myConfirmed = $count("SELECT COUNT(*) FROM bookings WHERE user_id=? AND status='confirmed'");
$myPending   = $count("SELECT COUNT(*) FROM bookings WHERE user_id=? AND status='pending'");
$myEvents    = $count("SELECT COUNT(*) FROM events WHERE user_id=?");
$requests    = $count("SELECT COUNT(*) FROM bookings b JOIN events e ON e.id=b.event_id
                       WHERE e.user_id=? AND b.status='pending'");

// My upcoming bookings
$s = $pdo->prepare("SELECT b.status, b.seats, e.id AS event_id, e.title, e.event_date, e.event_time, e.location
                    FROM bookings b JOIN events e ON e.id=b.event_id
                    WHERE b.user_id=? AND b.status IN ('pending','confirmed') AND e.event_date >= CURDATE()
                    ORDER BY e.event_date, e.event_time LIMIT 4");
$s->execute([$uid]); $upBookings = $s->fetchAll();

// My upcoming events
$s = $pdo->prepare("SELECT e.id, e.title, e.event_date, e.visibility, e.capacity,
                    COALESCE((SELECT SUM(seats) FROM bookings WHERE event_id=e.id AND status='confirmed'),0) AS booked,
                    (SELECT COUNT(*) FROM bookings WHERE event_id=e.id AND status='pending') AS pending_count
                    FROM events e WHERE e.user_id=? AND e.event_date >= CURDATE()
                    ORDER BY e.event_date LIMIT 4");
$s->execute([$uid]); $upMine = $s->fetchAll();

// Discover: other people's public events
$s = $pdo->prepare("SELECT * FROM events WHERE visibility='public' AND user_id<>? AND event_date >= CURDATE()
                    ORDER BY event_date, event_time LIMIT 3");
$s->execute([$uid]); $events = $s->fetchAll();

$badge = ['pending'=>'bg-warning text-dark','confirmed'=>'bg-success'];
require_once 'includes/header.php'; ?>
<style>
/* =========================
   EVENTORA THEME
   Change the colours here once and the whole site follows.
========================= */

:root {
    --rose: #B85C7A;
    --rose-dark: #8F405C;
    --rose-mid: #C76B8A;
    --rose-light: #E89AAF;
    --blush: #FFF0F4;
    --cream: #FFF8F5;
    --border: #F1D9DF;
    --text: #4A3B3B;
    --text-soft: #5C4A4A;
    --muted: #7A6666;
    --white: #FFFFFF;
    --gradient: linear-gradient(135deg, #E89AAF, #C76B8A);
    --shadow: 0 5px 18px rgba(184, 92, 122, 0.08);
    --shadow-hover: 0 12px 28px rgba(184, 92, 122, 0.16);
}

html { scroll-behavior: smooth; }

body {
    background: linear-gradient(135deg, #FFF8F5, #FFF0F4) no-repeat;
    min-height: 100vh;
    color: var(--text);
    margin: 0;
    padding: 0;
}

/* Bootstrap primary buttons in theme colours */
.btn-primary {
    background: var(--gradient);
    border: 0;
    font-weight: 600;
}
.btn-primary:hover, .btn-primary:focus {
    background: var(--gradient);
    filter: brightness(1.05);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(199, 107, 138, 0.25);
}


/* =========================
   NAVBAR
========================= */

.eventora-navbar {
    background-color: var(--cream);
    padding: 15px 0;
    box-shadow: 0 2px 12px rgba(184, 92, 122, 0.10);
}

.eventora-logo {
    color: var(--rose);
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 1px;
    text-decoration: none;
    white-space: nowrap;
}
.eventora-logo:hover { color: var(--rose-dark); }

.eventora-navbar .navbar-collapse {
    display: flex;
    align-items: center;
    flex-grow: 1;
}

.eventora-links {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 0 auto;
}

.eventora-links .nav-link {
    color: var(--text-soft);
    font-size: 16px;
    font-weight: 500;
    padding: 8px 12px;
    transition: 0.3s ease;
}
.eventora-links .nav-link:hover,
.eventora-links .nav-link.active { color: var(--rose); }

.navbar-buttons {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-left: auto;
}

.login-btn { color: var(--text-soft); text-decoration: none; font-weight: 500; }
.login-btn:hover { color: var(--rose); }

.register-btn {
    background: var(--gradient);
    color: white;
    padding: 9px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: 0.3s ease;
}
.register-btn:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(199, 107, 138, 0.25);
}


/* =========================
   CAROUSEL / HERO
========================= */

#carouselExample { overflow: hidden; }

#carouselExample img,
.hero-carousel img {
    width: 100%;
    height: 500px;
    object-fit: cover;
}

.hero-carousel { position: relative; overflow: hidden; }
.hero-carousel .carousel-item img {
    height: clamp(320px, 68vh, 640px);
    filter: brightness(.6);
}

.hero-overlay {
    position: absolute;
    inset: 0;
    z-index: 3;
    display: flex;
    align-items: center;
    background: linear-gradient(90deg, rgba(74, 59, 59, .72) 0%, rgba(74, 59, 59, .1) 70%);
    pointer-events: none;
}
.hero-overlay .container { pointer-events: auto; }

.hero-tag {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    color: #fff;
    background: rgba(255, 255, 255, .18);
    border: 1px solid rgba(255, 255, 255, .4);
    backdrop-filter: blur(6px);
    margin-bottom: 18px;
}

.hero-overlay h1 {
    color: #fff;
    font-weight: 800;
    font-size: clamp(2rem, 5vw, 3.5rem);
    line-height: 1.15;
    max-width: 640px;
}
.hero-overlay h1 span { color: #FFD3DF; }

.hero-overlay p {
    color: #FBE9EE;
    font-size: 1.1rem;
    max-width: 520px;
    margin: 16px 0 28px;
}

.hero-btn {
    display: inline-block;
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 700;
    text-decoration: none;
    transition: 0.3s ease;
    margin: 0 10px 10px 0;
}
.hero-btn.primary { background: #fff; color: var(--rose); box-shadow: 0 8px 22px rgba(0, 0, 0, .18); }
.hero-btn.ghost { color: #fff; border: 2px solid rgba(255, 255, 255, .75); }
.hero-btn:hover { transform: translateY(-3px); }
.hero-btn.primary:hover { color: var(--rose-dark); }
.hero-btn.ghost:hover { background: rgba(255, 255, 255, .18); color: #fff; }


/* =========================
   FEATURE STRIP (landing)
========================= */

.feature-strip { margin-top: -50px; position: relative; z-index: 5; padding: 0; }

.feature-box {
    display: flex;
    gap: 16px;
    align-items: center;
    background: var(--white);
    border: 1px solid var(--border);
    padding: 22px;
    border-radius: 16px;
    box-shadow: var(--shadow);
    height: 100%;
    transition: 0.3s ease;
}
.feature-box:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); }

.feature-box .f-icon {
    flex: 0 0 56px;
    height: 56px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    font-size: 1.7rem;
    background: var(--blush);
}
.feature-box h5 { margin: 0 0 4px; font-weight: 700; color: var(--rose); font-size: 17px; }
.feature-box p { margin: 0; font-size: 14px; color: var(--muted); }


/* =========================
   SCROLL REVEAL
========================= */

.js .reveal { opacity: 0; transform: translateY(28px); transition: opacity .7s ease, transform .7s ease; }
.js .reveal.show { opacity: 1; transform: none; }


/* =========================
   SECTION HEADING
========================= */

.section-heading { text-align: center; margin-bottom: 45px; }

.section-tag {
    display: inline-block;
    color: var(--rose);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.section-heading h2 { color: var(--rose); font-size: 32px; font-weight: 700; margin-bottom: 10px; }
.section-heading p { color: var(--muted); font-size: 16px; margin: 0; }


/* =========================
   UPCOMING PUBLIC EVENTS
========================= */

.upcoming-events { padding: 70px 0; }

.event-card {
    background-color: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    height: 100%;
    box-shadow: var(--shadow);
    transition: 0.3s ease;
}
.event-card:hover { transform: translateY(-7px); box-shadow: var(--shadow-hover); }

.event-card img { width: 100%; height: 210px; object-fit: cover; display: block; }
.event-card-body { padding: 20px; }

.event-category {
    display: inline-block;
    background-color: var(--blush);
    color: var(--rose);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 10px;
}

.event-card h3 { color: var(--text); font-size: 19px; font-weight: 700; margin-bottom: 15px; }
.event-info { color: var(--muted); font-size: 14px; margin-bottom: 7px; }

.event-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 18px;
    gap: 10px;
}

.public-event { color: var(--rose); font-size: 13px; font-weight: 600; }

.event-btn {
    background: var(--gradient);
    color: white;
    padding: 8px 13px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: 0.3s ease;
}
.event-btn:hover { color: white; transform: translateY(-2px); box-shadow: 0 5px 12px rgba(199, 107, 138, 0.25); }

.view-all { text-align: center; margin-top: 40px; }

.view-all-btn {
    display: inline-block;
    border: 2px solid var(--rose-mid);
    color: var(--rose);
    padding: 10px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: 0.3s ease;
}
.view-all-btn:hover { background-color: var(--rose-mid); color: white; }


/* =========================
   CREATE YOUR OWN EVENT
========================= */

.create-event-section { padding: 65px 0; background: linear-gradient(135deg, #FFF0F4, #FFE7EE); }

.create-event-content {
    background-color: var(--white);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px 45px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
    box-shadow: 0 8px 25px rgba(184, 92, 122, 0.08);
}

.create-event-text { max-width: 700px; }

.create-tag {
    display: inline-block;
    color: var(--rose);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.create-event-text h2 { color: var(--text); font-size: 30px; font-weight: 700; margin-bottom: 12px; }
.create-event-text p { color: var(--muted); font-size: 15px; line-height: 1.7; margin: 0; }

.create-event-btn {
    flex-shrink: 0;
    background: var(--gradient);
    color: white;
    padding: 13px 25px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: 600;
    transition: 0.3s ease;
}
.create-event-btn:hover { color: white; transform: translateY(-3px); box-shadow: 0 8px 18px rgba(199, 107, 138, 0.25); }


/* =========================
   EVENT CATEGORIES
========================= */

.categories-section { padding: 70px 0; background-color: rgba(255, 240, 244, 0.65); }

.category-link { text-decoration: none; display: block; height: 100%; }

.category-card {
    background-color: var(--white);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 25px 10px;
    text-align: center;
    height: 100%;
    box-shadow: 0 4px 15px rgba(184, 92, 122, 0.06);
    transition: 0.3s ease;
}
.category-card:hover {
    transform: translateY(-6px);
    border-color: var(--rose-light);
    box-shadow: 0 10px 25px rgba(184, 92, 122, 0.13);
}

.category-icon { font-size: 35px; margin-bottom: 10px; transition: 0.3s ease; }
.category-card:hover .category-icon { transform: scale(1.15) rotate(-5deg); }
.category-card h4 { color: var(--text-soft); font-size: 15px; font-weight: 600; margin: 0; }


/* =========================
   HOW EVENTORA WORKS
========================= */

.why-eventora { padding: 70px 0; background-color: var(--cream); }

.why-card {
    position: relative;
    overflow: hidden;
    background-color: var(--white);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px 20px;
    text-align: center;
    height: 100%;
    box-shadow: 0 4px 15px rgba(184, 92, 122, 0.06);
    transition: 0.3s ease;
}
.why-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(184, 92, 122, 0.12); }

.why-card .step-no {
    position: absolute;
    top: 8px;
    right: 14px;
    font-size: 2.6rem;
    font-weight: 800;
    color: rgba(199, 107, 138, 0.12);
    line-height: 1;
}

.why-icon { font-size: 38px; margin-bottom: 15px; }
.why-card h3 { color: var(--rose); font-size: 18px; font-weight: 700; margin-bottom: 10px; }
.why-card p { color: var(--muted); font-size: 14px; line-height: 1.6; margin: 0; }


/* =========================
   CTA
========================= */

.eventora-cta { padding: 75px 20px; background: linear-gradient(135deg, #B85C7A, #D98DA5); }

.cta-content { text-align: center; color: white; }
.cta-content h2 { font-size: 34px; font-weight: 700; margin-bottom: 15px; }
.cta-content p { font-size: 16px; max-width: 650px; margin: 0 auto 30px; line-height: 1.7; }

.cta-btn {
    display: inline-block;
    background-color: var(--white);
    color: var(--rose);
    padding: 12px 28px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    transition: 0.3s ease;
}
.cta-btn:hover { color: var(--rose-dark); transform: translateY(-2px); }


/* =========================
   DASHBOARD
========================= */

.dash-welcome {
    background: linear-gradient(135deg, #C76B8A, #8F405C);
    color: #fff;
    border-radius: 22px;
    padding: 38px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 28px rgba(143, 64, 92, .25);
}
.dash-welcome::after {
    content: "🎉";
    position: absolute;
    right: 30px;
    bottom: -20px;
    font-size: 9rem;
    opacity: .15;
}
.dash-welcome h2 { font-weight: 800; margin-bottom: 6px; }
.dash-welcome p { color: #FBE9EE; margin-bottom: 20px; }

.dash-stat {
    display: block;
    text-decoration: none;
    background: var(--white);
    border: 1px solid var(--border);
    border-left: 5px solid var(--rose-mid);
    border-radius: 16px;
    padding: 22px;
    box-shadow: var(--shadow);
    transition: 0.3s ease;
    height: 100%;
}
.dash-stat:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); }
.dash-stat .num { font-size: 2.2rem; font-weight: 800; color: var(--text); line-height: 1; }
.dash-stat .lbl { color: var(--muted); font-size: 14px; margin-top: 6px; }
.dash-stat.ok   { border-left-color: #6BB78F; }
.dash-stat.warn { border-left-color: #E5A94F; }
.dash-stat.info { border-left-color: #7FA8D9; }

.dash-panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    box-shadow: var(--shadow);
    height: 100%;
}
.dash-panel h4 { font-weight: 700; color: var(--rose); font-size: 19px; margin-bottom: 16px; }

.dash-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid #F8E8EC;
}
.dash-item:last-child { border-bottom: 0; }
.dash-item .title { font-weight: 600; color: var(--text); text-decoration: none; }
.dash-item .title:hover { color: var(--rose); }
.dash-item .meta { font-size: 13px; color: var(--muted); }
.dash-empty { color: #A89599; text-align: center; padding: 20px 0; }
.dash-empty a, .text-muted a { color: var(--rose); font-weight: 600; text-decoration: none; }

.quick-action {
    display: block;
    text-align: center;
    text-decoration: none;
    background: var(--white);
    border: 1px solid var(--border);
    color: var(--rose);
    border-radius: 16px;
    padding: 20px 10px;
    font-weight: 700;
    transition: 0.3s ease;
}
.quick-action .qa-icon { display: block; font-size: 1.8rem; margin-bottom: 6px; }
.quick-action:hover { background: var(--gradient); color: #fff; transform: translateY(-4px); border-color: transparent; }


/* =========================
   TABLES + SMALL BUTTONS
   (My Bookings page)
========================= */

.eventora-table {
    background: var(--white);
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: var(--shadow);
    margin-bottom: 0;
}
.eventora-table thead th {
    background: var(--blush);
    color: var(--rose);
    font-size: 14px;
    font-weight: 700;
    padding: 14px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.eventora-table td { padding: 14px; color: var(--text); border-color: #F8E8EC; vertical-align: middle; }
.eventora-table tbody tr:hover { background: #FFFAFB; }
.eventora-table a { color: var(--rose); font-weight: 600; text-decoration: none; }
.eventora-table a:hover { color: var(--rose-dark); }

.mini-btn {
    background: var(--gradient);
    color: #fff;
    border: 0;
    border-radius: 7px;
    padding: 6px 13px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: 0.3s ease;
}
.mini-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 12px rgba(199, 107, 138, 0.25); }
.mini-btn.danger { background: #fff; color: #C0392B; border: 1px solid #E7B4B0; }
.mini-btn.danger:hover { background: #C0392B; color: #fff; box-shadow: none; }


/* =========================
   FOOTER
========================= */

.eventora-footer { background-color: var(--text); color: #F9E8ED; padding: 60px 0 0; }

.footer-logo { color: var(--rose-light); font-size: 23px; font-weight: 700; margin-bottom: 18px; }

.eventora-footer h4 { color: #FFFFFF; font-size: 17px; font-weight: 600; margin-bottom: 20px; }
.eventora-footer p { color: #D8C5C9; font-size: 14px; line-height: 1.7; }

.eventora-footer a {
    display: block;
    color: #D8C5C9;
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 10px;
    transition: 0.3s ease;
}
.eventora-footer a:hover { color: var(--rose-light); }

.footer-bottom { border-top: 1px solid #6A555A; margin-top: 40px; padding: 20px 0; text-align: center; }
.footer-bottom p { margin: 0; }


/* =========================
   TABLET
========================= */

@media (max-width: 991px) {
    .eventora-navbar .navbar-collapse { display: block; }
    .eventora-links { display: block; margin-top: 15px; }
    .eventora-links .nav-link { padding: 10px 0; }
    .navbar-buttons { margin-top: 10px; margin-left: 0; }
    .create-event-content { padding: 35px; }
    #carouselExample img { height: 420px; }
    .feature-strip { margin-top: 30px; }
}


/* =========================
   MOBILE
========================= */

@media (max-width: 768px) {
    .eventora-logo { font-size: 21px; }
    #carouselExample img { height: 300px; }

    .upcoming-events,
    .categories-section,
    .why-eventora { padding: 50px 0; }

    .section-heading h2 { font-size: 27px; }
    .event-card img { height: 200px; }

    .create-event-section { padding: 50px 0; }
    .create-event-content { flex-direction: column; text-align: center; padding: 30px 25px; }
    .create-event-text { max-width: 100%; }
    .create-event-text h2 { font-size: 26px; }
    .create-event-btn { width: 100%; text-align: center; }

    .cta-content h2 { font-size: 27px; }
    .event-bottom { flex-wrap: wrap; }

    .dash-welcome { padding: 26px; }
    .dash-stat .num { font-size: 1.8rem; }
    .dash-item { flex-direction: column; align-items: flex-start; }
    .hero-overlay p { font-size: 1rem; margin: 12px 0 20px; }
}


/* =========================
   SMALL MOBILE
========================= */

@media (max-width: 480px) {
    #carouselExample img { height: 230px; }
    .section-heading h2 { font-size: 24px; }
    .event-card img { height: 180px; }
    .event-card-body { padding: 17px; }
    .create-event-content { padding: 25px 18px; }
    .create-event-text h2 { font-size: 23px; }
    .category-card { padding: 20px 8px; }
    .category-icon { font-size: 30px; }
    .why-card { padding: 25px 17px; }
    .eventora-cta { padding: 55px 15px; }
    .cta-content h2 { font-size: 24px; }
    .hero-btn { padding: 10px 22px; }
}

</style>

<section class="landing" style="padding:40px 0 70px">
<div class="container">

    <!-- WELCOME -->
    <div class="dash-welcome mb-4">
        <h2><?= $greet ?>, <?= e($firstName) ?>! 👋</h2>
        <p><?= e(site_setting('dashboard_subtitle', 'Manage your events and bookings from here.')) ?></p>
        <a href="create-event.php" class="hero-btn primary">+ Create Event</a>
        <a href="events.php" class="hero-btn ghost">Browse Events</a>
    </div>

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><a href="booking.php" class="dash-stat ok">
            <div class="num"><?= $myConfirmed ?></div><div class="lbl">Confirmed Bookings</div></a></div>
        <div class="col-6 col-lg-3"><a href="booking.php" class="dash-stat warn">
            <div class="num"><?= $myPending ?></div><div class="lbl">Awaiting Approval</div></a></div>
        <div class="col-6 col-lg-3"><a href="booking.php" class="dash-stat">
            <div class="num"><?= $myEvents ?></div><div class="lbl">Events I Created</div></a></div>
        <div class="col-6 col-lg-3"><a href="booking.php" class="dash-stat info">
            <div class="num"><?= $requests ?></div><div class="lbl">New Requests for Me</div></a></div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><a href="create-event.php" class="quick-action"><span class="qa-icon">📝</span>Create Event</a></div>
        <div class="col-6 col-md"><a href="events.php" class="quick-action"><span class="qa-icon">🔎</span>Find Events</a></div>
        <div class="col-6 col-md"><a href="booking.php" class="quick-action"><span class="qa-icon">🎟️</span>My Bookings</a></div>
        <div class="col-6 col-md"><a href="booking.php" class="quick-action"><span class="qa-icon">🔗</span>Share Private Link</a></div>
        <div class="col-6 col-md"><a href="profile.php" class="quick-action"><span class="qa-icon">👤</span>Edit Profile</a></div>
    </div>

    <!-- PANELS -->
    <div class="row g-4 mb-5">
        <div class="col-lg-6">
            <div class="dash-panel">
                <h4>🎟️ My Upcoming Bookings</h4>
                <?php if (!$upBookings): ?>
                    <div class="dash-empty">No bookings yet. <a href="events.php">Browse events</a></div>
                <?php endif; ?>
                <?php foreach ($upBookings as $r): ?>
                    <div class="dash-item">
                        <div>
                            <a class="title" href="event-details.php?id=<?= (int)$r['event_id'] ?>"><?= e($r['title']) ?></a>
                            <div class="meta"><?= fmt_date($r['event_date']) ?> · <?= fmt_time($r['event_time']) ?> · <?= e($r['location']) ?></div>
                        </div>
                        <span class="badge <?= $badge[$r['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($r['status'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="dash-panel">
                <h4>⚙️ My Upcoming Events</h4>
                <?php if (!$upMine): ?>
                    <div class="dash-empty">You haven't created any events. <a href="create-event.php">Create one</a></div>
                <?php endif; ?>
                <?php foreach ($upMine as $r): ?>
                    <div class="dash-item">
                        <div>
                            <a class="title" href="event-details.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a>
                            <div class="meta"><?= fmt_date($r['event_date']) ?> · <?= e(ucfirst($r['visibility'])) ?> ·
                                <?= (int)$r['booked'] ?>/<?= (int)$r['capacity'] ?> seats</div>
                        </div>
                        <?php if ($r['pending_count'] > 0): ?>
                            <a href="booking.php" class="badge bg-warning text-dark text-decoration-none"><?= (int)$r['pending_count'] ?> pending</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- DISCOVER -->
    <div class="section-heading">
        <span class="section-tag">🌍 DISCOVER</span>
        <h2>Events You May Like</h2>
    </div>
    <div class="row g-4">
        <?php if (!$events): ?><p class="text-center text-muted">No new public events right now.</p><?php endif; ?>
        <?php foreach ($events as $ev) include 'includes/event_card.php'; ?>
    </div>
    <div class="view-all"><a href="events.php" class="view-all-btn">Explore All Public Events</a></div>

</div>
</section>
<?php include 'includes/footer.php'; ?>