<?php
// admin/index.php — Admin Dashboard
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$title = 'Admin Portal';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
ensurePendingExpertProfilesTable($db);
$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';

$stats = [
    'experts' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE user_type IN ('expert','both')")['cnt'] ?? 0,
    'clients' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE user_type IN ('client','both')")['cnt'] ?? 0,
    'notes' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM notes")['cnt'] ?? 0,
    'books' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM libraries")['cnt'] ?? 0,
    'videos' => $db->fetchOne("SELECT COUNT(*) AS cnt FROM problem_solving_videos")['cnt'] ?? 0,
];

$pendingExperts = $db->fetchAll(
    "SELECT u.id, u.full_name, u.email, u.phone, u.country, u.created_at,
                        pep.id AS pending_profile_id,
                        ep.id AS expert_profile_id,
                        COALESCE(pep.headline, ep.headline) AS headline,
                        COALESCE(pep.qualification, ep.qualification) AS qualification,
                        COALESCE(pep.domain, ep.domain) AS domain,
                        COALESCE(pep.skills, ep.skills) AS skills,
                        COALESCE(pep.expertise_areas, ep.expertise_areas) AS expertise_areas,
                        COALESCE(pep.experience_years, ep.experience_years) AS experience_years,
                        COALESCE(pep.current_role_name, ep.current_role_name) AS `current_role`,
                        COALESCE(pep.company, ep.company) AS company,
                        COALESCE(pep.rate_per_session, ep.rate_per_session, 0) AS rate_per_session,
                        COALESCE(pep.currency, ep.currency, 'USD') AS currency,
                        COALESCE(pep.session_duration_minutes, ep.session_duration_minutes) AS session_duration_minutes,
                        COALESCE(pep.max_response_hours, ep.max_response_hours) AS max_response_hours,
                        pep.status AS pending_status,
                        pep.created_at AS pending_created_at
         FROM users u
         LEFT JOIN pending_expert_profiles pep
             ON pep.user_id = u.id
            AND pep.status = 'pending'
         LEFT JOIN expert_profiles ep
             ON ep.user_id = u.id
            AND IFNULL(ep.is_verified, 0) = 0
            AND (ep.verification_docs IS NULL OR ep.verification_docs NOT LIKE 'REJECTED:%')
         WHERE u.user_type IN ('expert','both')
             AND u.is_active = 1
             AND (pep.id IS NOT NULL OR ep.id IS NOT NULL)
         ORDER BY COALESCE(pep.created_at, u.created_at) DESC
         LIMIT 10"
);

$experts = $db->fetchAll(
    "SELECT u.id, u.full_name, u.email, u.phone, u.country, u.user_type, u.is_active,
            IFNULL(ep.total_sessions, 0) AS total_sessions,
            IFNULL(ep.average_rating, 0) AS average_rating,
            IFNULL(ep.total_reviews, 0) AS total_reviews,
            IFNULL(ep.is_verified, 0) AS is_verified,
            IFNULL(ep.is_available, 0) AS is_available,
            ep.headline, ep.expertise_areas, ep.experience_years, ep.rate_per_session, ep.currency,
            (SELECT COUNT(*) FROM thinking_requests tr WHERE tr.expert_id = u.id AND tr.status IN ('responded','completed')) AS solved_count
     FROM users u
     LEFT JOIN expert_profiles ep ON ep.user_id = u.id
     WHERE u.user_type IN ('expert','both')
     ORDER BY total_sessions DESC, u.created_at DESC
     LIMIT 10"
);

$clients = $db->fetchAll(
    "SELECT u.id, u.full_name, u.email, u.phone, u.country, u.user_type, u.is_active, u.created_at,
            (SELECT COUNT(*) FROM thinking_requests tr WHERE tr.client_id = u.id) AS total_requests,
            (SELECT COUNT(*) FROM thinking_requests tr WHERE tr.client_id = u.id AND tr.status = 'completed') AS completed_requests
     FROM users u
     WHERE u.user_type IN ('client','both')
     ORDER BY u.created_at DESC
     LIMIT 10"
);

$recentNotes = $db->fetchAll(
    "SELECT n.id, n.title, n.is_active, n.created_at, u.full_name AS uploader_name, u.email AS uploader_email
     FROM notes n LEFT JOIN users u ON n.uploaded_by = u.id
     ORDER BY n.created_at DESC LIMIT 5"
);

$recentBooks = $db->fetchAll(
    "SELECT l.id, l.title, l.is_active, l.created_at, u.full_name AS uploader_name, u.email AS uploader_email
     FROM libraries l LEFT JOIN users u ON l.uploaded_by = u.id
     ORDER BY l.created_at DESC LIMIT 5"
);

$recentVideos = $db->fetchAll(
    "SELECT v.id, v.title, v.is_active, v.created_at, u.full_name AS uploader_name, u.email AS uploader_email
     FROM problem_solving_videos v LEFT JOIN users u ON v.uploaded_by = u.id
     ORDER BY v.created_at DESC LIMIT 5"
);
?>

<main class="py-5">
    <div class="container">
        <div class="mb-4">
            <h1 class="display-6 fw-bold">Admin Portal</h1>
            <p class="text-muted">Experts, clients, and uploads overview.</p>
        </div>

        <?php
        $activeAdminPage = 'dashboard';
        require __DIR__ . '/_nav.php';
        ?>

        <?php if ($status && $message): ?>
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> mb-4">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="br-card p-3">
                    <div class="text-subtle small">Experts</div>
                    <div class="fw-bold fs-4"><?= number_format($stats['experts']) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="br-card p-3">
                    <div class="text-subtle small">Clients</div>
                    <div class="fw-bold fs-4"><?= number_format($stats['clients']) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="br-card p-3">
                    <div class="text-subtle small">Notes</div>
                    <div class="fw-bold fs-4"><?= number_format($stats['notes']) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="br-card p-3">
                    <div class="text-subtle small">Books</div>
                    <div class="fw-bold fs-4"><?= number_format($stats['books']) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="br-card p-3">
                    <div class="text-subtle small">Videos</div>
                    <div class="fw-bold fs-4"><?= number_format($stats['videos']) ?></div>
                </div>
            </div>
        </div>

        <div class="br-card p-3 mb-4" id="pending-experts">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-semibold mb-0">Pending Expert Verifications</h6>
                <span class="text-subtle small"><?= number_format(count($pendingExperts)) ?> pending</span>
            </div>
            <div class="table-responsive">
                <table class="table br-table table-dark admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Expert</th>
                            <th>Expertise</th>
                            <th>Experience</th>
                            <th>Rate</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingExperts as $pe): ?>
                            <?php
                            $skillsList = [];
                            if (!empty($pe['skills'])) {
                                $skillsList = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', $pe['skills']))));
                            }
                            $areasList = [];
                            if (!empty($pe['expertise_areas'])) {
                                $decoded = json_decode($pe['expertise_areas'], true);
                                if (is_array($decoded)) {
                                    $areasList = $decoded;
                                }
                            }
                            $tags = array_slice(array_unique(array_merge($areasList, $skillsList)), 0, 6);
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($pe['full_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($pe['email']) ?></div>
                                    <?php if (!empty($pe['phone']) || !empty($pe['country'])): ?>
                                        <div class="text-subtle small">
                                            <?= htmlspecialchars(trim(($pe['phone'] ?? '') . (($pe['phone'] && $pe['country']) ? ' · ' : '') . ($pe['country'] ?? ''))) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($pe['headline'])): ?>
                                        <div class="small"><?= htmlspecialchars($pe['headline']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted small">
                                        <?= htmlspecialchars($pe['qualification'] ?? 'N/A') ?>
                                        <?php if (!empty($pe['domain'])): ?>
                                            · <?= htmlspecialchars($pe['domain']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($tags): ?>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <?php foreach ($tags as $tag): ?>
                                                <span class="badge"
                                                    style="background:var(--br-dark3);color:var(--br-text2);border:1px solid var(--br-border);font-weight:400">
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <?= $pe['experience_years'] ? (int) $pe['experience_years'] . ' yrs' : 'N/A' ?>
                                    </div>
                                    <?php if (!empty($pe['current_role']) || !empty($pe['company'])): ?>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars(trim(($pe['current_role'] ?? '') . (($pe['current_role'] && $pe['company']) ? ' · ' : '') . ($pe['company'] ?? ''))) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <?= htmlspecialchars($pe['currency'] ?? 'USD') ?>
                                        <?= number_format((float) ($pe['rate_per_session'] ?? 0), 2) ?>
                                    </div>
                                    <div class="text-muted small">
                                        <?= $pe['session_duration_minutes'] ? (int) $pe['session_duration_minutes'] . ' min' : 'N/A' ?>
                                        ·
                                        <?= $pe['max_response_hours'] ? (int) $pe['max_response_hours'] . ' hrs' : 'N/A' ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="<?= APP_URL ?>/admin/expert-review.php?id=<?= (int) $pe['id'] ?>"
                                            class="btn br-btn-ghost btn-sm">Review</a>
                                        <?php if (!empty($pe['pending_profile_id']) || !empty($pe['expert_profile_id'])): ?>
                                            <form method="post" action="<?= APP_URL ?>/admin/actions.php"
                                                style="display:inline-block">
                                                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                                <input type="hidden" name="entity" value="experts">
                                                <input type="hidden" name="action" value="verify_expert">
                                                <input type="hidden" name="id" value="<?= (int) $pe['id'] ?>">
                                                <input type="hidden" name="redirect" value="<?= APP_URL ?>/admin/index.php">
                                                <button type="submit" class="btn br-btn-gold btn-sm">Verify</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$pendingExperts): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No pending experts</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="br-card p-3 mb-4">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= APP_URL ?>/pages/upload-notes.php" class="btn br-btn-gold btn-sm">Upload Notes</a>
                <a href="<?= APP_URL ?>/pages/upload-ebook.php" class="btn br-btn-gold btn-sm">Upload Book</a>
                <a href="<?= APP_URL ?>/pages/upload-video.php" class="btn br-btn-gold btn-sm">Upload Video</a>
            </div>
        </div>

        <div class="row g-4 admin-pair-row">
            <div class="col-lg-6">
                <div class="br-card p-3 admin-scroll-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">Experts</h6>
                        <a class="text-warning small" href="<?= APP_URL ?>/admin/users.php">View all</a>
                    </div>
                    <div class="admin-scroll-body">
                        <div class="table-responsive">
                            <table class="table br-table table-dark admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Expert</th>
                                        <th>Expertise</th>
                                        <th>Solved</th>
                                        <th>Sessions</th>
                                        <th>Rating</th>
                                        <th>Verified</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($experts as $e): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($e['full_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($e['email']) ?></div>
                                                <?php if (!empty($e['phone']) || !empty($e['country'])): ?>
                                                    <div class="text-subtle small">
                                                        <?= htmlspecialchars(trim(($e['phone'] ?? '') . (($e['phone'] && $e['country']) ? ' · ' : '') . ($e['country'] ?? ''))) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $areas = $e['expertise_areas'] ?? '';
                                                if ($areas) {
                                                    $decoded = json_decode($areas, true);
                                                    if (is_array($decoded)) {
                                                        $areas = implode(', ', $decoded);
                                                    }
                                                }
                                                ?>
                                                <div class="small"><?= htmlspecialchars($e['headline'] ?? '') ?></div>
                                                <?php if (!empty($areas)): ?>
                                                    <div class="text-muted small"><?= htmlspecialchars($areas) ?></div>
                                                <?php endif; ?>
                                                <div class="text-subtle small">
                                                    <?= $e['experience_years'] ? (int) $e['experience_years'] . ' yrs exp' : 'Experience: N/A' ?>
                                                    <?php if (!empty($e['rate_per_session'])): ?>
                                                        · <?= htmlspecialchars($e['currency'] ?? 'USD') ?>
                                                        <?= number_format((float) $e['rate_per_session'], 2) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?= number_format($e['solved_count']) ?></td>
                                            <td><?= number_format($e['total_sessions']) ?></td>
                                            <td><?= number_format((float) $e['average_rating'], 1) ?></td>
                                            <td><?= $e['is_verified'] ? 'Yes' : 'No' ?></td>
                                            <td><?= $e['is_active'] ? 'Active' : 'Disabled' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$experts): ?>
                                        <tr>
                                            <td colspan="7" class="text-muted text-center">No experts found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="br-card p-3 admin-scroll-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">Clients</h6>
                        <a class="text-warning small" href="<?= APP_URL ?>/admin/users.php">View all</a>
                    </div>
                    <div class="admin-scroll-body">
                        <div class="table-responsive">
                            <table class="table br-table table-dark admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Requests</th>
                                        <th>Completed</th>
                                        <th>Member Since</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($c['full_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($c['email']) ?></div>
                                                <?php if (!empty($c['phone']) || !empty($c['country'])): ?>
                                                    <div class="text-subtle small">
                                                        <?= htmlspecialchars(trim(($c['phone'] ?? '') . (($c['phone'] && $c['country']) ? ' · ' : '') . ($c['country'] ?? ''))) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format($c['total_requests']) ?></td>
                                            <td><?= number_format($c['completed_requests']) ?></td>
                                            <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                                            <td><?= $c['is_active'] ? 'Active' : 'Disabled' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$clients): ?>
                                        <tr>
                                            <td colspan="5" class="text-muted text-center">No clients found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 admin-triple-row">
            <div class="col-lg-4">
                <div class="br-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">Recent Notes</h6>
                        <div class="d-flex gap-2">
                            <a class="text-warning small" href="<?= APP_URL ?>/admin/notes.php">View all</a>
                            <a class="text-warning small" href="<?= APP_URL ?>/pages/upload-notes.php">Upload</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table br-table table-dark admin-table mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Uploader</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentNotes as $n): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($n['title']) ?></td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($n['uploader_name'] ?? 'Unknown') ?>
                                            </div>
                                            <?php if (!empty($n['uploader_email'])): ?>
                                                <div class="text-muted small"><?= htmlspecialchars($n['uploader_email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a class="btn br-btn-ghost btn-sm"
                                                href="<?= APP_URL ?>/api/view-note.php?id=<?= (int) $n['id'] ?>"
                                                target="_blank">View</a>
                                            <a class="btn br-btn-ghost btn-sm"
                                                href="<?= APP_URL ?>/api/download-note.php?id=<?= (int) $n['id'] ?>">Download</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$recentNotes): ?>
                                    <tr>
                                        <td colspan="3" class="text-muted text-center">No notes found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="br-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">Recent Books</h6>
                        <div class="d-flex gap-2">
                            <a class="text-warning small" href="<?= APP_URL ?>/admin/libraries.php">View all</a>
                            <a class="text-warning small" href="<?= APP_URL ?>/pages/upload-ebook.php">Upload</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table br-table table-dark admin-table mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Uploader</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBooks as $b): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($b['title']) ?></td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($b['uploader_name'] ?? 'Unknown') ?>
                                            </div>
                                            <?php if (!empty($b['uploader_email'])): ?>
                                                <div class="text-muted small"><?= htmlspecialchars($b['uploader_email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a class="btn br-btn-ghost btn-sm"
                                                href="<?= APP_URL ?>/api/view-ebook.php?id=<?= (int) $b['id'] ?>"
                                                target="_blank">View</a>
                                            <a class="btn br-btn-ghost btn-sm"
                                                href="<?= APP_URL ?>/api/download-ebook.php?id=<?= (int) $b['id'] ?>">Download</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$recentBooks): ?>
                                    <tr>
                                        <td colspan="3" class="text-muted text-center">No books found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="br-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">Recent Videos</h6>
                        <div class="d-flex gap-2">
                            <a class="text-warning small" href="<?= APP_URL ?>/admin/videos.php">View all</a>
                            <a class="text-warning small" href="<?= APP_URL ?>/pages/upload-video.php">Upload</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table br-table table-dark admin-table mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Uploader</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentVideos as $v): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['title']) ?></td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($v['uploader_name'] ?? 'Unknown') ?>
                                            </div>
                                            <?php if (!empty($v['uploader_email'])): ?>
                                                <div class="text-muted small"><?= htmlspecialchars($v['uploader_email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a class="btn br-btn-ghost btn-sm"
                                                href="<?= APP_URL ?>/pages/video-detail.php?id=<?= (int) $v['id'] ?>"
                                                target="_blank">Open</a>
                                            <a class="btn br-btn-ghost btn-sm"
                                                href="<?= APP_URL ?>/api/download-video.php?id=<?= (int) $v['id'] ?>">Download</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$recentVideos): ?>
                                    <tr>
                                        <td colspan="3" class="text-muted text-center">No videos found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>