<?php
$adminNav = [
    'dashboard' => ['label' => 'Dashboard', 'href' => APP_URL . '/admin/index.php'],
    'requests' => ['label' => 'Problems', 'href' => APP_URL . '/admin/requests.php'],
    'users' => ['label' => 'Users', 'href' => APP_URL . '/admin/users.php'],
    'notes' => ['label' => 'Notes', 'href' => APP_URL . '/admin/notes.php'],
    'libraries' => ['label' => 'Books', 'href' => APP_URL . '/admin/libraries.php'],
    'videos' => ['label' => 'Videos', 'href' => APP_URL . '/admin/videos.php'],
];
$activeAdminPage = $activeAdminPage ?? 'dashboard';
?>

<div class="br-card p-3 mb-4">
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($adminNav as $key => $item): ?>
            <a href="<?= $item['href'] ?>"
                class="btn <?= $key === $activeAdminPage ? 'br-btn-gold' : 'br-btn-ghost' ?> btn-sm">
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>