<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pageTitle = 'Contact Us';
$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (empty($subject) || empty($message)) {
        $error = 'Please fill out all fields.';
    } else {
        $sent = true;
        flash('success', 'Thank you for reaching out! Our team will get back to you soon.');
        header('Location: ' . url('contact.php'));
        exit;
    }
}

$user = currentUser();
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>Contact Us</h2>
    <p>Have a question or need assistance? We're here to help.</p>
</div>

<div class="flex gap-3 mb-6">
    <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost">← Back to Home</a>
</div>

<div class="contact-layout">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Send a Message</span>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="form-narrow">
                <div class="form-group">
                    <label class="form-label" for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" class="form-control"
                           placeholder="How can we help?" required
                           value="<?= sanitize($_POST['subject'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="message">Message</label>
                    <textarea id="message" name="message" class="form-control" rows="5"
                              placeholder="Write your message here…" required><?= sanitize($_POST['message'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-accent">Send Message</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Other Ways to Reach Us</span>
        </div>
        <div class="card-body">
            <ul class="contact-info-list">
                <li>
                    <span class="contact-info-icon">📧</span>
                    <div>
                        <strong>Email</strong>
                        <p>Reach us through your registered account email for the fastest response.</p>
                    </div>
                </li>
                <li>
                    <span class="contact-info-icon">📢</span>
                    <div>
                        <strong>Announcements</strong>
                        <p>Check <a href="<?= url('announcements.php') ?>">announcements</a> for official updates and news.</p>
                    </div>
                </li>
                <li>
                    <span class="contact-info-icon">📋</span>
                    <div>
                        <strong>Terms &amp; Policies</strong>
                        <p>Review our <a href="<?= url('terms.php') ?>">Terms &amp; Conditions</a> for platform guidelines.</p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
