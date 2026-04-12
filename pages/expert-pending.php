<?php
// pages/expert-pending.php — Expert Verification Pending
$title = 'Expert Verification';
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = currentUser();
if (!$user || !in_array($user['user_type'], ['expert', 'both'])) {
    header('Location: ' . APP_URL . '/pages/dashboard-client.php');
    exit;
}

if (isExpertVerified($user['id'])) {
    header('Location: ' . APP_URL . '/pages/dashboard-expert.php');
    exit;
}

$db = Database::getInstance();
ensurePendingExpertProfilesTable($db);

$pendingProfile = $db->fetchOne(
    "SELECT *
     FROM pending_expert_profiles
     WHERE user_id = ?
     ORDER BY (status = 'pending') DESC, created_at DESC
     LIMIT 1",
    [$user['id']]
) ?: [];

$profile = $db->fetchOne("SELECT * FROM expert_profiles WHERE user_id = ?", [$user['id']]) ?: [];
$displayProfile = !empty($pendingProfile) ? $pendingProfile : $profile;

$reviewNote = trim((string) ($profile['verification_docs'] ?? ''));
$isRejected = (!empty($pendingProfile) && ($pendingProfile['status'] ?? '') === 'rejected') || str_starts_with($reviewNote, 'REJECTED:');
$rejectionReason = '';
if (!empty($pendingProfile) && ($pendingProfile['status'] ?? '') === 'rejected') {
    $rejectionReason = trim((string) ($pendingProfile['admin_note'] ?? ''));
} elseif ($isRejected) {
    $parts = explode('::', $reviewNote, 2);
    $rejectionReason = trim($parts[1] ?? substr($reviewNote, 9));
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="py-5" style="padding-top:90px;">
    <div class="container" style="max-width:900px;">
        <div class="br-card p-4">
            <?php if ($isRejected): ?>
                <h1 class="display-6 fw-bold mb-2">Expert Profile Needs Changes</h1>
                <p class="text-muted">Admin reviewed your profile and requested updates. Please fix the details below and
                    contact admin to review again.</p>
                <?php if ($rejectionReason !== ''): ?>
                    <div class="alert alert-danger mb-4">
                        <strong>Admin feedback:</strong> <?= htmlspecialchars($rejectionReason) ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <h1 class="display-6 fw-bold mb-2">Expert Verification Pending</h1>
                <p class="text-muted">Your expert profile is under review. You will be able to access the expert dashboard
                    after admin approval.</p>
            <?php endif; ?>

            <div class="br-card p-3 mb-4" style="background:var(--br-card2);">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-subtle small">Qualification</div>
                        <div class="fw-medium"><?= htmlspecialchars($displayProfile['qualification'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-subtle small">Domain</div>
                        <div class="fw-medium"><?= htmlspecialchars($displayProfile['domain'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-subtle small">Skills</div>
                        <div class="fw-medium"><?= htmlspecialchars($displayProfile['skills'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-subtle small">Experience (Years)</div>
                        <div class="fw-medium"><?= htmlspecialchars($displayProfile['experience_years'] ?? 'N/A') ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-subtle small">Rate</div>
                        <div class="fw-medium">
                            <?= htmlspecialchars(($displayProfile['currency'] ?? 'USD') . ' ' . ($displayProfile['rate_per_session'] ?? '0.00')) ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-subtle small">Response Time</div>
                        <div class="fw-medium">
                            <?php if (!empty($displayProfile['max_response_hours'])): ?>
                                <?= htmlspecialchars($displayProfile['max_response_hours']) ?> hours
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="<?= APP_URL ?>/pages/dashboard-client.php" class="btn br-btn-ghost">Go to Client Dashboard</a>
                <a href="<?= APP_URL ?>/pages/profile.php" class="btn br-btn-gold">Update Profile</a>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>