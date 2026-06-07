<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/adoption.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/adoption-requests.php'));
    exit;
}

$pageTitle = 'My Applications';
$user = currentUser();
$db   = getDB();

$stmt = $db->prepare(
    'SELECT a.*, p.name AS pet_name, p.breed, p.image AS pet_image
     FROM adoption_applications a
     JOIN pets p ON p.id = a.pet_id
     WHERE a.user_id = ?
     ORDER BY a.created_at DESC'
);
$stmt->execute([(int) $user['id']]);
$applications = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>My Adoption Applications</h2>
    <p>Track the status of your pet adoption requests.</p>
</div>

<div class="flex gap-3 mb-6">
    <a href="<?= url('adoption.php') ?>" class="btn btn-accent">❤ Browse Pets</a>
    <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost">← Back to Home</a>
</div>

<div class="card">
    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <div class="empty-icon">📝</div>
            <h3>No applications yet</h3>
            <p>When you apply to adopt a pet, your applications will appear here.</p>
            <a href="<?= url('adoption.php') ?>" class="btn btn-accent mt-3">Adopt a Pet</a>
        </div>
    <?php else: ?>
        <div class="application-cards">
            <?php foreach ($applications as $app):
                $status = formatApplicationStatus($app['status']);
            ?>
            <article class="application-card">
                <div class="application-card-image">
                    <img src="<?= petImageUrl($app['pet_image'] ?? null) ?>" alt="<?= sanitize($app['pet_name']) ?>">
                </div>
                <div class="application-card-body">
                    <div class="application-card-head">
                        <h3><?= sanitize($app['pet_name']) ?></h3>
                        <span class="status-badge <?= $status['class'] ?>">
                            <span class="status-dot"></span>
                            <?= $status['label'] ?>
                        </span>
                    </div>
                    <?php if (!empty($app['breed'])): ?>
                    <p class="text-secondary text-sm"><?= sanitize($app['breed']) ?></p>
                    <?php endif; ?>
                    <dl class="application-card-meta">
                        <div>
                            <dt>Submitted</dt>
                            <dd><?= date('M j, Y', strtotime($app['created_at'])) ?></dd>
                        </div>
                        <?php if (!empty($app['schedule_date'])): ?>
                        <div>
                            <dt>Scheduled visit</dt>
                            <dd><?= date('M j, Y', strtotime($app['schedule_date'])) ?><?= !empty($app['schedule_time']) ? ' at ' . date('g:i A', strtotime($app['schedule_time'])) : '' ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <?php if ($app['status'] === 'approved'): ?>
                    <p class="text-sm text-secondary">Our team will contact you with next steps for completing the adoption.</p>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                    <p class="text-sm text-secondary">This application was not approved. You may apply for another pet.</p>
                    <?php else: ?>
                    <p class="text-sm text-secondary">Your application is under review. We'll notify you when there's an update.</p>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
