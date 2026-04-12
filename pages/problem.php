<?php
// pages/problem.php — Problem detail page with edit/re-raise/solution actions
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$db = Database::getInstance();
$user = currentUser() ?: [];
$userId = currentUserId();
$userType = $user['user_type'] ?? '';
$isAdmin = $userType === 'admin';
$isVerifiedExpert = in_array($userType, ['expert', 'both'], true) && isExpertVerified($userId);

$requestId = (int) ($_GET['id'] ?? 0);
if ($requestId <= 0) {
    header('Location: ' . APP_URL . '/pages/dashboard-client.php');
    exit;
}

$request = $db->fetchOne(
    "SELECT tr.*, c.full_name AS client_name,
            e.full_name AS assigned_expert_name,
            ec.name AS category_name
     FROM thinking_requests tr
     INNER JOIN users c ON tr.client_id = c.id
     LEFT JOIN users e ON tr.expert_id = e.id
     LEFT JOIN expertise_categories ec ON tr.category_id = ec.id
     WHERE tr.id = ?",
    [$requestId]
);

if (!$request) {
    header('Location: ' . APP_URL . '/pages/dashboard-client.php');
    exit;
}

$canAccess = false;
if ($isAdmin) {
    $canAccess = true;
} elseif ((int) $request['client_id'] === $userId) {
    $canAccess = true;
} elseif ($isVerifiedExpert && ($request['status'] === 'submitted' || (int) $request['expert_id'] === $userId)) {
    $canAccess = true;
}

if (!$canAccess) {
    header('Location: ' . APP_URL . '/pages/dashboard-client.php');
    exit;
}

$response = $db->fetchOne(
    "SELECT r.*, u.full_name AS responder_name, u.user_type AS responder_type
     FROM thinking_responses r
     INNER JOIN users u ON u.id = r.expert_id
     WHERE r.request_id = ?
     ORDER BY r.id DESC
     LIMIT 1",
    [$requestId]
);

$adminResponseHidden = false;
if ($isAdmin && $response && in_array($response['responder_type'] ?? '', ['expert', 'both'], true)) {
    $adminResponseHidden = true;
    $response = null;
}

$categories = $db->fetchAll("SELECT id, name FROM expertise_categories WHERE is_active = 1 ORDER BY name");

$isOwnerClient = (int) $request['client_id'] === $userId;
$canEditProblem = $isOwnerClient && in_array($request['status'], ['submitted', 'accepted', 'thinking'], true);
$canComplete = $isOwnerClient && $request['status'] === 'responded';
$canReraise = $isOwnerClient && in_array($request['status'], ['responded', 'completed', 'disputed'], true);

$canSubmitSolution = false;
if ($isAdmin && in_array($request['status'], ['submitted', 'accepted', 'thinking', 'responded'], true)) {
    $canSubmitSolution = true;
}
if ($isVerifiedExpert && in_array($request['status'], ['submitted', 'accepted', 'thinking'], true)) {
    $canSubmitSolution = true;
}

function brPathToUrl(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    if (strpos($path, '://') !== false) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    $pos = strpos($path, '/uploads/');
    if ($pos === false) {
        return null;
    }
    return APP_URL . substr($path, $pos);
}

$problemVoiceUrl = brPathToUrl($request['problem_voice_path'] ?? null);
$responseVoiceUrl = brPathToUrl($response['voice_response_path'] ?? null);

$statusBadgeClass = match ((string) $request['status']) {
    'submitted' => 'br-badge br-badge-gold',
    'accepted', 'thinking' => 'br-badge br-badge-violet',
    'responded' => 'br-badge br-badge-teal',
    'completed' => 'br-badge br-badge-success',
    'disputed' => 'br-badge br-badge-danger',
    default => 'br-badge',
};

$title = 'Problem #' . $requestId;
require_once __DIR__ . '/../includes/header.php';
?>

<main class="py-4" style="padding-top:80px!important">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <a href="<?= APP_URL ?>/pages/dashboard-client.php" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
            <span class="<?= $statusBadgeClass ?>"><?= htmlspecialchars((string) $request['status']) ?></span>
        </div>

        <div class="mb-4">
            <h1 class="br-section-title fs-3 mb-1"><?= htmlspecialchars($request['title']) ?></h1>
            <p class="text-muted small mb-0">
                Problem #<?= (int) $request['id'] ?> · Client: <?= htmlspecialchars($request['client_name']) ?>
                · Assigned: <?= htmlspecialchars($request['assigned_expert_name'] ?? 'Unassigned') ?>
                · <?= htmlspecialchars(ucfirst((string) ($request['urgency'] ?? 'normal'))) ?>
            </p>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="br-profile-section mb-4">
                    <h6 class="fw-semibold mb-3">Problem Details</h6>
                    <?php if (!empty($request['problem_text'])): ?>
                        <div class="text-muted small lh-lg" style="white-space:pre-wrap">
                            <?= htmlspecialchars($request['problem_text']) ?></div>
                    <?php else: ?>
                        <div class="text-muted small">No text description was provided.</div>
                    <?php endif; ?>

                    <?php if ($problemVoiceUrl): ?>
                        <div class="mt-3">
                            <label class="br-form-label">Problem Voice</label>
                            <audio controls class="w-100" src="<?= htmlspecialchars($problemVoiceUrl) ?>"></audio>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($canEditProblem): ?>
                    <div class="br-profile-section mb-4">
                        <h6 class="fw-semibold mb-3">Edit Problem</h6>
                        <form id="problem-edit-form">
                            <input type="hidden" name="action" value="update_problem">
                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">

                            <div class="mb-3">
                                <label class="br-form-label">Title</label>
                                <input class="br-form-control form-control" name="title"
                                    value="<?= htmlspecialchars($request['title']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="br-form-label">Category</label>
                                <select class="br-form-control form-control" name="category_id">
                                    <option value="">— Select category —</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int) $cat['id'] ?>" <?= (int) ($request['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="br-form-label">Urgency</label>
                                <select class="br-form-control form-control" name="urgency">
                                    <option value="normal" <?= ($request['urgency'] ?? '') === 'normal' ? 'selected' : '' ?>>
                                        Normal</option>
                                    <option value="urgent" <?= ($request['urgency'] ?? '') === 'urgent' ? 'selected' : '' ?>>
                                        Urgent</option>
                                    <option value="critical" <?= ($request['urgency'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="br-form-label">Problem Text</label>
                                <textarea class="br-form-control form-control" name="problem_text"
                                    rows="6"><?= htmlspecialchars((string) ($request['problem_text'] ?? '')) ?></textarea>
                            </div>
                            <button type="submit" class="btn br-btn-gold">Save Changes</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($response): ?>
                    <div class="br-profile-section mb-4" id="solution-section">
                        <h6 class="fw-semibold mb-3">Submitted Solution</h6>
                        <div class="text-subtle small mb-2">
                            By <?= htmlspecialchars($response['responder_name'] ?? 'Unknown') ?>
                            · <?= date('M j, Y H:i', strtotime($response['created_at'])) ?>
                        </div>

                        <?php if (!empty($response['written_response'])): ?>
                            <div class="text-muted small lh-lg mb-3" style="white-space:pre-wrap">
                                <?= htmlspecialchars($response['written_response']) ?></div>
                        <?php endif; ?>

                        <?php if ($responseVoiceUrl): ?>
                            <div class="mb-3">
                                <audio controls class="w-100" src="<?= htmlspecialchars($responseVoiceUrl) ?>"></audio>
                            </div>
                        <?php endif; ?>

                        <?php $insights = json_decode((string) ($response['key_insights'] ?? '[]'), true) ?: []; ?>
                        <?php if ($insights): ?>
                            <div class="mb-3">
                                <div class="text-subtle small mb-1">Key Insights</div>
                                <ul class="mb-0 text-muted small">
                                    <?php foreach ($insights as $item): ?>
                                        <li><?= htmlspecialchars((string) $item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($adminResponseHidden): ?>
                    <div class="br-profile-section mb-4">
                        <h6 class="fw-semibold mb-2">Submitted Solution</h6>
                        <p class="text-muted small mb-0">An expert solution exists but is hidden from admin view until admin
                            submits a solution.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="br-profile-section mb-3">
                    <h6 class="fw-semibold mb-3">Actions</h6>

                    <?php if ($canComplete): ?>
                        <button type="button" id="btn-complete" class="btn br-btn-gold w-100 mb-2">Confirm Solution</button>
                    <?php endif; ?>

                    <?php if ($canReraise): ?>
                        <button type="button" id="btn-reraise" class="btn btn-outline-danger w-100 mb-2">Re-raise
                            Issue</button>
                    <?php endif; ?>

                    <a href="<?= APP_URL ?>/pages/dashboard-client.php" class="btn br-btn-ghost w-100">Back to
                        Dashboard</a>
                </div>

                <?php if ($canSubmitSolution): ?>
                    <div class="br-profile-section">
                        <h6 class="fw-semibold mb-3">Submit Solution</h6>
                        <form id="solution-form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="submit_response">
                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">

                            <div class="mb-2">
                                <label class="br-form-label">Written Solution *</label>
                                <textarea class="br-form-control form-control" name="written_response" rows="4"
                                    required></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="br-form-label">Key Insights</label>
                                <textarea class="br-form-control form-control" name="key_insights" rows="2"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="br-form-label">Action Items</label>
                                <textarea class="br-form-control form-control" name="action_items" rows="2"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="br-form-label">Resources</label>
                                <textarea class="br-form-control form-control" name="resource_links" rows="2"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="br-form-label">Minutes</label>
                                <input type="number" class="br-form-control form-control" name="thinking_minutes" min="1">
                            </div>

                            <div class="br-recorder mb-3">
                                <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                                    <div class="rec-dot" id="problem-rec-dot"></div>
                                    <div class="rec-timer" id="problem-rec-timer">00:00</div>
                                    <div class="text-subtle small" id="problem-rec-status">Record voice solution</div>
                                </div>
                                <div class="br-waveform" id="problem-rec-wave"></div>
                                <div class="d-flex align-items-center justify-content-center gap-3 mt-3">
                                    <button type="button" class="rec-btn-sec" id="problem-rec-trash" disabled>🗑️</button>
                                    <button type="button" class="rec-btn-main" id="problem-rec-main">🎙️</button>
                                    <button type="button" class="rec-btn-sec" id="problem-rec-play" disabled>▶️</button>
                                </div>
                                <div id="problem-rec-preview" style="display:none;margin-top:10px">
                                    <audio id="problem-rec-audio" controls class="w-100"></audio>
                                </div>
                            </div>

                            <button type="submit" class="btn br-btn-gold w-100">Submit Solution</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    const APP_URL = '<?= APP_URL ?>';
    const REQUEST_ID = <?= (int) $request['id'] ?>;

    const problemRecorder = document.getElementById('solution-form')
        ? new VoiceRecorder({
            dot: 'problem-rec-dot',
            timer: 'problem-rec-timer',
            status: 'problem-rec-status',
            wave: 'problem-rec-wave',
            main: 'problem-rec-main',
            trash: 'problem-rec-trash',
            play: 'problem-rec-play',
            audio: 'problem-rec-audio',
            preview: 'problem-rec-preview'
        })
        : null;

    document.getElementById('problem-edit-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new URLSearchParams(new FormData(this));
        const res = await fetch(APP_URL + '/api/manage_request.php', { method: 'POST', body: fd });
        const data = await res.json();
        BrainRent.toast(data.success ? data.message : (data.error || 'Update failed'), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 700);
        }
    });

    document.getElementById('solution-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        if (problemRecorder) {
            problemRecorder.appendTo(fd, 'voice_response');
        }
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        const res = await fetch(APP_URL + '/api/manage_request.php', { method: 'POST', body: fd });
        const data = await res.json();
        BrainRent.toast(data.success ? data.message : (data.error || 'Submit failed'), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 800);
        } else {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Solution';
        }
    });

    document.getElementById('btn-reraise')?.addEventListener('click', async () => {
        const fd = new URLSearchParams({ action: 'reraise', request_id: String(REQUEST_ID) });
        const res = await fetch(APP_URL + '/api/manage_request.php', { method: 'POST', body: fd });
        const data = await res.json();
        BrainRent.toast(data.success ? data.message : (data.error || 'Could not re-raise'), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 800);
        }
    });

    document.getElementById('btn-complete')?.addEventListener('click', async () => {
        const fd = new URLSearchParams({
            action: 'complete',
            request_id: String(REQUEST_ID),
            rating: '5',
            clarity_rating: '5',
            depth_rating: '5',
            usefulness_rating: '5',
            review_text: 'Completed from problem page.'
        });
        const res = await fetch(APP_URL + '/api/manage_request.php', { method: 'POST', body: fd });
        const data = await res.json();
        BrainRent.toast(data.success ? data.message : (data.error || 'Could not complete'), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 800);
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>