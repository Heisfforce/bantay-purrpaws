<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pageTitle = 'Pet Care Tips';

$tips = [
    [
        'icon' => '🏠',
        'title' => 'Create a Safe Space',
        'body' => 'Give your pet a quiet area with a comfortable bed, fresh water, and away from loud noises while they adjust to their new home.',
    ],
    [
        'icon' => '🍽',
        'title' => 'Feed a Balanced Diet',
        'body' => 'Use age-appropriate pet food and maintain regular feeding times. Avoid giving human food that may be harmful to animals.',
    ],
    [
        'icon' => '💉',
        'title' => 'Stay Up to Date on Vaccines',
        'body' => 'Schedule regular vet check-ups and keep vaccinations current to protect your pet and your community.',
    ],
    [
        'icon' => '🚿',
        'title' => 'Grooming & Hygiene',
        'body' => 'Brush your pet regularly, trim nails when needed, and bathe them with pet-safe products to keep skin and coat healthy.',
    ],
    [
        'icon' => '🎾',
        'title' => 'Exercise & Enrichment',
        'body' => 'Daily walks, playtime, and mental stimulation help prevent boredom and support your pet\'s physical and emotional well-being.',
    ],
    [
        'icon' => '🆘',
        'title' => 'Know Emergency Signs',
        'body' => 'Watch for sudden lethargy, loss of appetite, vomiting, or difficulty breathing — contact a veterinarian immediately if you notice these.',
    ],
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>Pet Care Tips</h2>
    <p>Essential guidance for caring for rescued and adopted pets.</p>
</div>

<div class="flex gap-3 mb-6">
    <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost">← Back to Home</a>
</div>

<div class="tips-grid">
    <?php foreach ($tips as $tip): ?>
    <article class="tip-card">
        <span class="tip-card-icon"><?= $tip['icon'] ?></span>
        <h3><?= sanitize($tip['title']) ?></h3>
        <p><?= sanitize($tip['body']) ?></p>
    </article>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
