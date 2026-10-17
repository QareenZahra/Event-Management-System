<?php
$page = 'home';
require_once 'includes/functions.php';

// Logged-in users go to the dashboard, not the landing page
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

require_once 'includes/header.php';

$events = $pdo->query("SELECT * FROM events WHERE visibility='public' AND event_date >= CURDATE()
                       ORDER BY event_date ASC, event_time ASC LIMIT 3")->fetchAll();

// Editable hero content and slider images (set from the admin panel)
$cfg        = site_settings_all();
$slides     = site_slides();
$heroTag    = site_setting('hero_tag', '✨ EVENT MANAGEMENT MADE EASY');
$heroSub    = site_setting('hero_subtitle', 'Weddings, birthdays, conferences or parties. Plan your event, invite your guests and manage every booking in one place.');
$heroTitle  = e(site_setting('hero_title', 'Create, Share & Celebrate Every Event'));
$hlWord     = array_key_exists('hero_highlight', $cfg) ? $cfg['hero_highlight'] : 'Celebrate';
if ($hlWord !== '') {
    $heroTitle = preg_replace('/' . preg_quote(e($hlWord), '/') . '/', '<span>$0</span>', $heroTitle, 1);
}
?>
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
<script>document.documentElement.classList.add('js');</script>

<div class="landing">

<!-- HERO -->
<div id="heroCarousel" class="carousel slide carousel-fade hero-carousel" data-bs-ride="carousel" data-bs-interval="5000">
    <div class="carousel-inner">
        <?php foreach ($slides as $i => $img): ?>
        <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>"><img src="<?= e($img) ?>" class="d-block w-100" alt="Event"></div>
        <?php endforeach; ?>
    </div>

    <div class="hero-overlay">
        <div class="container">
            <span class="hero-tag"><?= e($heroTag) ?></span>
            <h1><?= $heroTitle ?></h1>
            <p><?= e($heroSub) ?></p>
            <a href="register.php" class="hero-btn primary">Get Started Free</a>
            <a href="events.php" class="hero-btn ghost">Browse Events</a>
        </div>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span><span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span><span class="visually-hidden">Next</span>
    </button>
</div>

<!-- FEATURES -->
<section class="feature-strip" style="padding:0">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-4 reveal"><div class="feature-box">
                <div class="f-icon">🔒</div>
                <div><h5>Private Invite Links</h5><p>Share a secret link so only your guests can see your event.</p></div>
            </div></div>
            <div class="col-md-4 reveal"><div class="feature-box">
                <div class="f-icon">✅</div>
                <div><h5>Approval Based Booking</h5><p>Accept or reject every request and stay in control of seats.</p></div>
            </div></div>
            <div class="col-md-4 reveal"><div class="feature-box">
                <div class="f-icon">📅</div>
                <div><h5>Everything in One Place</h5><p>Create, edit and track all your events from one dashboard.</p></div>
            </div></div>
        </div>
    </div>
</section>

<!-- UPCOMING -->
<section class="upcoming-events">
    <div class="container">
        <div class="section-heading reveal">
            <span class="section-tag">🌍 PUBLIC EVENTS</span>
            <h2>Upcoming Events</h2>
            <p>Discover public events you can join and register for.</p>
        </div>
        <div class="row g-4 reveal">
            <?php if (!$events): ?>
                <p class="text-center text-muted">No upcoming events yet. Be the first to create one!</p>
            <?php endif; ?>
            <?php foreach ($events as $ev) include 'includes/event_card.php'; ?>
        </div>
        <div class="view-all reveal"><a href="events.php" class="view-all-btn">Explore All Public Events</a></div>
    </div>
</section>

<!-- CREATE -->
<section class="create-event-section">
    <div class="container reveal">
        <div class="create-event-content">
            <div class="create-event-text">
                <span class="create-tag">✨ PLANNING SOMETHING SPECIAL?</span>
                <h2>Create Your Own Event</h2>
                <p>Planning a wedding, birthday, party, conference or any special occasion? Create your event and manage your guests easily with Eventora.</p>
            </div>
            <a href="create-event.php" class="create-event-btn">Create an Event</a>
        </div>
    </div>
</section>

<!-- CATEGORIES -->
<section class="categories-section">
    <div class="container">
        <div class="section-heading reveal">
            <span class="section-tag">✦ EXPLORE</span>
            <h2>Event Categories</h2>
            <p>Create or discover events for every occasion.</p>
        </div>
        <div class="row g-4">
            <?php
            $cats = ['Wedding'=>'💍','Birthday'=>'🎂','Conference'=>'💻','Music'=>'🎵','Workshop'=>'🎨','Party'=>'🎉'];
            foreach ($cats as $name => $icon): ?>
            <div class="col-lg-2 col-md-4 col-6 reveal">
                <a href="events.php?category=<?= urlencode($name) ?>" class="category-link">
                    <div class="category-card">
                        <div class="category-icon"><?= $icon ?></div>
                        <h4><?= e($name) ?>s</h4>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="why-eventora">
    <div class="container">
        <div class="section-heading reveal">
            <span class="section-tag">✦ SIMPLE PROCESS</span>
            <h2>How Eventora Works</h2>
            <p>Create, manage and attend events with ease.</p>
        </div>
        <div class="row g-4">
            <?php
            $steps = [
              ['📝','Create Event','Create your wedding, birthday, conference, party or any special event.'],
              ['⚙️','Manage Event','Update your event details and manage everything in one place.'],
              ['👥','Manage Guests','Accept booking requests and track your attendees easily.'],
              ['🎉','Celebrate','Bring your guests together and enjoy your special event.'],
            ];
            foreach ($steps as $i => $s): ?>
            <div class="col-lg-3 col-md-6 reveal">
                <div class="why-card">
                    <span class="step-no">0<?= $i + 1 ?></span>
                    <div class="why-icon"><?= $s[0] ?></div>
                    <h3><?= $s[1] ?></h3>
                    <p><?= $s[2] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="eventora-cta">
    <div class="container reveal">
        <div class="cta-content">
            <h2>Have an Event in Mind?</h2>
            <p>Create your own event, invite your guests and manage everything with Eventora.</p>
            <a href="register.php" class="cta-btn">Join Eventora Today</a>
        </div>
    </div>
</section>

</div><!-- /.landing -->

<script>
// Reveal sections smoothly on scroll
(function () {
    const items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) { items.forEach(i => i.classList.add('show')); return; }
    const io = new IntersectionObserver((entries) => {
        entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('show'); io.unobserve(en.target); } });
    }, { threshold: .12 });
    items.forEach((el, i) => { el.style.transitionDelay = (i % 4) * 80 + 'ms'; io.observe(el); });
})();
</script>
<?php include 'includes/footer.php'; ?>