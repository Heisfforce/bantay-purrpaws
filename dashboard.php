<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pageTitle = 'Home';
$user = currentUser();

require_once __DIR__ . '/includes/header.php';
?>

<section class="home-hero" style="--hero-image: url('<?= url('assets/dog2.jpg') ?>')">
    <div class="home-hero-content">
        <p class="page-eyebrow">Welcome back</p>
        <h1 class="home-hero-title">Hello, <?= sanitize(explode(' ', $user['name'])[0]) ?></h1>
        <p class="home-hero-subtitle">Everything you need to help stray animals — in one place.</p>
    </div>
</section>

<div class="feature-section">
    <h2 class="feature-section-title">Rescue &amp; Report</h2>
    <div class="feature-grid">
        <a href="<?= url('report.php') ?>" class="feature-card">
            <span class="feature-card-icon">🐾</span>
            <span class="feature-card-title">Report a Stray Animal</span>
            <span class="feature-card-desc">Submit a rescue report with location and photo proof.</span>
        </a>
        <a href="<?= url('my-reports.php') ?>" class="feature-card">
            <span class="feature-card-icon">📋</span>
            <span class="feature-card-title">My Reports</span>
            <span class="feature-card-desc">Track the status of all your submitted rescue reports.</span>
        </a>
    </div>
</div>

<div class="feature-section">
    <h2 class="feature-section-title">Adoption</h2>
    <div class="feature-grid">
        <a href="<?= url('adoption.php') ?>" class="feature-card">
            <span class="feature-card-icon">❤</span>
            <span class="feature-card-title">Adopt a Pet</span>
            <span class="feature-card-desc">Browse pets available for adoption and apply online.</span>
        </a>
        <a href="<?= url('my-applications.php') ?>" class="feature-card">
            <span class="feature-card-icon">📝</span>
            <span class="feature-card-title">My Applications</span>
            <span class="feature-card-desc">View and track your adoption application status.</span>
        </a>
    </div>
</div>

<div class="feature-section">
    <h2 class="feature-section-title">Updates &amp; Resources</h2>
    <div class="feature-grid">
        <a href="<?= url('announcements.php') ?>" class="feature-card">
            <span class="feature-card-icon">📢</span>
            <span class="feature-card-title">View Announcements</span>
            <span class="feature-card-desc">Official updates from the BantayPurrPaws team.</span>
        </a>
        <a href="<?= url('notifications.php') ?>" class="feature-card">
            <span class="feature-card-icon">🔔</span>
            <span class="feature-card-title">Notifications</span>
            <span class="feature-card-desc">See alerts about your reports and applications.</span>
        </a>
        <a href="<?= url('pet-care.php') ?>" class="feature-card">
            <span class="feature-card-icon">💡</span>
            <span class="feature-card-title">Pet Care Tips</span>
            <span class="feature-card-desc">Helpful guidance for caring for rescued and adopted pets.</span>
        </a>
        <a href="<?= url('contact.php') ?>" class="feature-card">
            <span class="feature-card-icon">✉</span>
            <span class="feature-card-title">Contact Us</span>
            <span class="feature-card-desc">Get in touch with our team for support or inquiries.</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
