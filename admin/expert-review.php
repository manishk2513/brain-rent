<?php
// admin/expert-review.php — Expert Profile Review
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$db = Database::getInstance();
ensurePendingExpertProfilesTable($db);
$expertId = (int) ($_GET['id'] ?? 0);

if ($expertId <= 0) {
    header('Location: ' . APP_URL . '/admin/index.php?status=error&message=Invalid+expert+id');
    exit;
}

$expert = $db->fetchOne(
    "SELECT u.id, u.full_name, u.email, u.phone, u.country, u.created_at, u.is_active, u.user_type,
            pep.id AS pending_profile_id,
            pep.status AS pending_status,
            pep.admin_note AS pending_admin_note,
            pep.desired_user_type,
            pep.created_at AS pending_created_at,
            ep.id AS expert_profile_id,
            COALESCE(pep.headline, ep.headline) AS headline,
            COALESCE(pep.qualification, ep.qualification) AS qualification,
            COALESCE(pep.domain, ep.domain) AS domain,
            COALESCE(pep.skills, ep.skills) AS skills,
            COALESCE(pep.expertise_areas, ep.expertise_areas) AS expertise_areas,
            COALESCE(pep.experience_years, ep.experience_years) AS experience_years,
            COALESCE(pep.current_role_name, ep.current_role_name) AS `current_role`,
            COALESCE(pep.company, ep.company) AS company,
            COALESCE(pep.linkedin_url, ep.linkedin_url) AS linkedin_url,
            COALESCE(pep.portfolio_url, ep.portfolio_url) AS portfolio_url,
            COALESCE(pep.rate_per_session, ep.rate_per_session, 0) AS rate_per_session,
            COALESCE(pep.currency, ep.currency, 'USD') AS currency,
            COALESCE(pep.session_duration_minutes, ep.session_duration_minutes, 10) AS session_duration_minutes,
            COALESCE(pep.max_response_hours, ep.max_response_hours, 48) AS max_response_hours,
            ep.is_verified, ep.is_available, ep.total_sessions, ep.total_reviews, ep.average_rating,
            ep.verification_docs
     FROM users u
     LEFT JOIN pending_expert_profiles pep
       ON pep.user_id = u.id
      AND pep.id = (
          SELECT p2.id
          FROM pending_expert_profiles p2
          WHERE p2.user_id = u.id
          ORDER BY (p2.status = 'pending') DESC, p2.created_at DESC
          LIMIT 1
      )
     LEFT JOIN expert_profiles ep ON ep.user_id = u.id
     WHERE u.id = ? AND u.user_type IN ('expert','both')",
    [$expertId]
);

if (!$expert) {
    header('Location: ' . APP_URL . '/admin/index.php?status=error&message=Expert+not+found');
    exit;
}

$reviewNote = trim((string) ($expert['verification_docs'] ?? ''));
$isRejected = ($expert['pending_status'] ?? '') === 'rejected' || str_starts_with($reviewNote, 'REJECTED:');
$rejectionReason = '';
if (($expert['pending_status'] ?? '') === 'rejected') {
    $rejectionReason = trim((string) ($expert['pending_admin_note'] ?? ''));
} elseif ($isRejected) {
    $parts = explode('::', $reviewNote, 2);
    $rejectionReason = trim($parts[1] ?? substr($reviewNote, 9));
}

$reviewState = 'pending';
if (!empty($expert['is_verified'])) {
    $reviewState = 'approved';
} elseif ($isRejected) {
    $reviewState = 'rejected';
} elseif (empty($expert['pending_profile_id']) && empty($expert['expert_profile_id'])) {
    $reviewState = 'empty';
}

$requestStats = $db->fetchOne(
    "SELECT
        COUNT(*) AS total_requests,
        SUM(CASE WHEN status IN ('submitted','accepted','thinking') THEN 1 ELSE 0 END) AS active_requests,
        SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) AS responded_requests,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_requests
     FROM thinking_requests
     WHERE expert_id = ?",
    [$expertId]
) ?: [];

$responseStats = $db->fetchOne(
    "SELECT COUNT(*) AS total_responses, IFNULL(SUM(actual_thinking_minutes), 0) AS total_thinking_minutes
     FROM thinking_responses
     WHERE expert_id = ?",
    [$expertId]
) ?: [];

$contentStats = [
    'books' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM libraries WHERE uploaded_by = ?", [$expertId])['cnt'] ?? 0,
    'notes' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM notes WHERE uploaded_by = ?", [$expertId])['cnt'] ?? 0,
    'videos' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM problem_solving_videos WHERE uploaded_by = ?", [$expertId])['cnt'] ?? 0,
];

$recentActivity = $db->fetchAll(
    "SELECT created_at, activity_type, activity_text
     FROM (
        SELECT tr.created_at, 'Request' AS activity_type,
               CONCAT('Request: ', tr.title, ' (', tr.status, ')') AS activity_text
        FROM thinking_requests tr
        WHERE tr.expert_id = ?

        UNION ALL

        SELECT rsp.created_at, 'Response' AS activity_type,
               CONCAT('Submitted response for request #', rsp.request_id) AS activity_text
        FROM thinking_responses rsp
        WHERE rsp.expert_id = ?

        UNION ALL

        SELECT l.created_at, 'Book Upload' AS activity_type,
               CONCAT('Uploaded book: ', l.title) AS activity_text
        FROM libraries l
        WHERE l.uploaded_by = ?

        UNION ALL

        SELECT n.created_at, 'Notes Upload' AS activity_type,
               CONCAT('Uploaded notes: ', n.title) AS activity_text
        FROM notes n
        WHERE n.uploaded_by = ?

        UNION ALL

        SELECT v.created_at, 'Video Upload' AS activity_type,
               CONCAT('Uploaded video: ', v.title) AS activity_text
        FROM problem_solving_videos v
        WHERE v.uploaded_by = ?
     ) activity
     ORDER BY created_at DESC
     LIMIT 20",
    [$expertId, $expertId, $expertId, $expertId, $expertId]
);

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';

$title = 'Expert Review';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h1 class="display-6 fw-bold mb-1">Expert Review</h1>
                <p class="text-muted mb-0">Review profile and activity before approving expert access.</p>
            </div>
            <a href="<?= APP_URL ?>/admin/index.php#pending-experts" class="btn br-btn-ghost btn-sm">Back to Pending
                List</a>
        </div>

        <?php
        $activeAdminPage = 'dashboard';
        require __DIR__ . '/_nav.php';
        ?>

        <?php if ($status && $message): ?>
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="br-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="mb-1"><?= htmlspecialchars($expert['full_name']) ?></h4>
                            <div class="text-muted small"><?= htmlspecialchars($expert['email']) ?></div>
                        </div>
                        <div>
                            <?php if ($reviewState === 'approved'): ?>
                                <span class="br-badge br-badge-teal">Approved</span>
                            <?php elseif ($reviewState === 'rejected'): ?>
                                <span class="br-badge br-badge-danger">Rejected</span>
                            <?php else: ?>
                                <span class="br-badge br-badge-gold">Pending Review</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (empty($expert['expert_profile_id']) && empty($expert['pending_profile_id'])): ?>
                        <div class="alert alert-warning mb-3">
                            This expert account has no profile data yet. Ask the user to complete expert registration
                            details and resubmit.
                        </div>
                    <?php endif; ?>

                    <?php if ($isRejected && $rejectionReason !== ''): ?>
                        <div class="alert alert-danger mb-3">
                            <strong>Last rejection reason:</strong> <?= htmlspecialchars($rejectionReason) ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-subtle small">Qualification</div>
                            <div class="fw-medium"><?= htmlspecialchars($expert['qualification'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-subtle small">Domain</div>
                            <div class="fw-medium"><?= htmlspecialchars($expert['domain'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-subtle small">Headline</div>
                            <div class="fw-medium"><?= htmlspecialchars($expert['headline'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-subtle small">Skills</div>
                            <div class="fw-medium"><?= htmlspecialchars($expert['skills'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-subtle small">Experience</div>
                            <div class="fw-medium">
                                <?= !empty($expert['experience_years']) ? (int) $expert['experience_years'] . ' years' : 'N/A' ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-subtle small">Current Role</div>
                            <div class="fw-medium"><?= htmlspecialchars($expert['current_role'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-subtle small">Company</div>
                            <div class="fw-medium"><?= htmlspecialchars($expert['company'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-subtle small">Rate</div>
                            <div class="fw-medium">
                                <?= htmlspecialchars(($expert['currency'] ?? 'USD') . ' ' . number_format((float) ($expert['rate_per_session'] ?? 0), 2)) ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-subtle small">Session Duration</div>
                            <div class="fw-medium">
                                <?= !empty($expert['session_duration_minutes']) ? (int) $expert['session_duration_minutes'] . ' min' : 'N/A' ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-subtle small">Response Time</div>
                            <div class="fw-medium">
                                <?= !empty($expert['max_response_hours']) ? (int) $expert['max_response_hours'] . ' hrs' : 'N/A' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="br-card p-4 mb-3">
                    <h6 class="fw-semibold mb-3">Review Actions</h6>

                    <?php if (!empty($expert['pending_profile_id']) || !empty($expert['expert_profile_id'])): ?>
                        <form method="post" action="<?= APP_URL ?>/admin/actions.php" class="mb-2">
                            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                            <input type="hidden" name="entity" value="experts">
                            <input type="hidden" name="action" value="verify_expert">
                            <input type="hidden" name="id" value="<?= (int) $expert['id'] ?>">
                            <input type="hidden" name="redirect"
                                value="<?= APP_URL ?>/admin/expert-review.php?id=<?= (int) $expert['id'] ?>">
                            <button type="submit" class="btn br-btn-gold w-100">Approve Expert</button>
                        </form>

                        <form method="post" action="<?= APP_URL ?>/admin/actions.php"
                            onsubmit="return confirm('Reject this expert profile?');">
                            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                            <input type="hidden" name="entity" value="experts">
                            <input type="hidden" name="action" value="reject_expert">
                            <input type="hidden" name="id" value="<?= (int) $expert['id'] ?>">
                            <input type="hidden" name="redirect"
                                value="<?= APP_URL ?>/admin/expert-review.php?id=<?= (int) $expert['id'] ?>">
                            <label class="br-form-label mt-2">Rejection Reason</label>
                            <textarea name="reason" class="br-form-control form-control mb-2" rows="3"
                                placeholder="Explain what needs to be fixed" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Reject Profile</button>
                        </form>
                    <?php else: ?>
                        <div class="text-muted small">No expert profile to approve/reject yet.</div>
                    <?php endif; ?>
                </div>

                <div class="br-card p-4">
                    <h6 class="fw-semibold mb-3">Activity Summary</h6>
                    <div class="small d-flex justify-content-between mb-2"><span>Total
                            requests</span><strong><?= (int) ($requestStats['total_requests'] ?? 0) ?></strong></div>
                    <div class="small d-flex justify-content-between mb-2"><span>Active
                            requests</span><strong><?= (int) ($requestStats['active_requests'] ?? 0) ?></strong></div>
                    <div class="small d-flex justify-content-between mb-2"><span>Responses
                            submitted</span><strong><?= (int) ($responseStats['total_responses'] ?? 0) ?></strong></div>
                    <div class="small d-flex justify-content-between mb-2"><span>Completed
                            requests</span><strong><?= (int) ($requestStats['completed_requests'] ?? 0) ?></strong>
                    </div>
                    <div class="small d-flex justify-content-between mb-2"><span>Books
                            uploaded</span><strong><?= (int) $contentStats['books'] ?></strong></div>
                    <div class="small d-flex justify-content-between mb-2"><span>Notes
                            uploaded</span><strong><?= (int) $contentStats['notes'] ?></strong></div>
                    <div class="small d-flex justify-content-between"><span>Videos
                            uploaded</span><strong><?= (int) $contentStats['videos'] ?></strong></div>
                </div>
            </div>
        </div>

        <div class="br-card p-4">
            <h6 class="fw-semibold mb-3">Recent Expert Activity</h6>
            <div class="table-responsive">
                <table class="table br-table table-dark admin-table mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Type</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivity as $activity): ?>
                            <tr>
                                <td class="text-muted small"><?= date('M j, Y H:i', strtotime($activity['created_at'])) ?>
                                </td>
                                <td><?= htmlspecialchars($activity['activity_type']) ?></td>
                                <td><?= htmlspecialchars($activity['activity_text']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentActivity): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">No activity found for this expert yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>