<?php
// admin/requests.php — Admin problem queue and solution panel
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$title = 'Admin - Problem Queue';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();

$openRequests = $db->fetchAll(
    "SELECT tr.id, tr.title, tr.status, tr.urgency, tr.created_at,
            tr.agreed_rate, tr.currency,
            c.full_name AS client_name,
            e.full_name AS preferred_expert_name
     FROM thinking_requests tr
     INNER JOIN users c ON tr.client_id = c.id
     LEFT JOIN users e ON tr.expert_id = e.id
     WHERE tr.status IN ('submitted','accepted','thinking')
     ORDER BY tr.created_at DESC"
);

$allRequests = $db->fetchAll(
    "SELECT tr.id, tr.title, tr.status, tr.created_at,
            c.full_name AS client_name,
            e.full_name AS assigned_expert_name
     FROM thinking_requests tr
     INNER JOIN users c ON tr.client_id = c.id
     LEFT JOIN users e ON tr.expert_id = e.id
     ORDER BY tr.created_at DESC
     LIMIT 60"
);

$statusClass = static function (string $status): string {
    return match ($status) {
        'submitted' => 'br-badge br-badge-gold',
        'accepted', 'thinking' => 'br-badge br-badge-violet',
        'responded' => 'br-badge br-badge-teal',
        'completed' => 'br-badge br-badge-success',
        default => 'br-badge',
    };
};
?>

<main class="py-5">
    <div class="container">
        <div class="mb-4">
            <h1 class="display-6 fw-bold">Problem Queue</h1>
            <p class="text-muted">Admin can review all problems and submit admin solutions for open items.</p>
        </div>

        <?php
        $activeAdminPage = 'requests';
        require __DIR__ . '/_nav.php';
        ?>

        <div class="br-card p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-semibold mb-0">Open Problems</h6>
                <span class="text-subtle small"><?= number_format(count($openRequests)) ?> open</span>
            </div>

            <div class="table-responsive">
                <table class="table br-table table-dark admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Problem</th>
                            <th>Client</th>
                            <th>Preferred Expert</th>
                            <th>Urgency</th>
                            <th>Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($openRequests as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($r['title']) ?></div>
                                    <div class="text-muted small">#<?= (int) $r['id'] ?> ·
                                        <?= date('M j, Y H:i', strtotime($r['created_at'])) ?></div>
                                </td>
                                <td><?= htmlspecialchars($r['client_name']) ?></td>
                                <td><?= htmlspecialchars($r['preferred_expert_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars(ucfirst($r['urgency'])) ?></td>
                                <td><?= htmlspecialchars(($r['currency'] ?? 'USD') . ' ' . number_format((float) $r['agreed_rate'], 2)) ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn br-btn-gold btn-sm"
                                            onclick="setSolveRequest(<?= (int) $r['id'] ?>)">Solve</button>
                                        <a class="btn br-btn-ghost btn-sm"
                                            href="<?= APP_URL ?>/pages/problem.php?id=<?= (int) $r['id'] ?>">Open</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$openRequests): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No open problems</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="br-card p-4 mb-4">
            <h6 class="fw-semibold mb-3">Submit Admin Solution</h6>
            <form id="admin-solution-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_response">

                <div class="mb-3">
                    <label class="br-form-label">Select Problem</label>
                    <select class="br-form-control form-control" name="request_id" id="admin-request-id" required>
                        <option value="">— Choose an open problem —</option>
                        <?php foreach ($openRequests as $r): ?>
                            <option value="<?= (int) $r['id'] ?>">#<?= (int) $r['id'] ?> ·
                                <?= htmlspecialchars($r['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="mb-3">
                            <label class="br-form-label">Written Solution *</label>
                            <textarea class="br-form-control form-control" name="written_response" rows="5"
                                required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="br-form-label">Key Insights (one per line)</label>
                            <textarea class="br-form-control form-control" name="key_insights" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="br-form-label">Action Items (one per line)</label>
                            <textarea class="br-form-control form-control" name="action_items" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="br-form-label">Resources (one per line)</label>
                            <textarea class="br-form-control form-control" name="resource_links" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="br-form-label">Thinking Minutes</label>
                            <input type="number" class="br-form-control form-control" name="thinking_minutes" min="1"
                                style="max-width:180px">
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="br-profile-section mb-3">
                            <h6 class="fw-semibold mb-3">Optional Voice Solution</h6>
                            <div class="br-recorder">
                                <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                                    <div class="rec-dot" id="admin-rec-dot"></div>
                                    <div class="rec-timer" id="admin-rec-timer">00:00</div>
                                    <div class="text-subtle small" id="admin-rec-status">Record voice solution</div>
                                </div>
                                <div class="br-waveform" id="admin-rec-wave"></div>
                                <div class="d-flex align-items-center justify-content-center gap-3 mt-3">
                                    <button type="button" class="rec-btn-sec" id="admin-rec-trash" disabled>🗑️</button>
                                    <button type="button" class="rec-btn-main" id="admin-rec-main">🎙️</button>
                                    <button type="button" class="rec-btn-sec" id="admin-rec-play" disabled>▶️</button>
                                </div>
                                <div id="admin-rec-preview" style="display:none;margin-top:10px">
                                    <audio id="admin-rec-audio" controls class="w-100"></audio>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn br-btn-gold">Submit Admin Solution</button>
                </div>
            </form>
        </div>

        <div class="br-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-semibold mb-0">All Problems (Admin Visibility)</h6>
                <span class="text-subtle small">latest <?= number_format(count($allRequests)) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table br-table table-dark admin-table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Problem</th>
                            <th>Client</th>
                            <th>Assigned</th>
                            <th>Status</th>
                            <th>Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allRequests as $r): ?>
                            <tr>
                                <td>#<?= (int) $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= htmlspecialchars($r['client_name']) ?></td>
                                <td><?= htmlspecialchars($r['assigned_expert_name'] ?? '—') ?></td>
                                <td><span
                                        class="<?= $statusClass((string) $r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span>
                                </td>
                                <td><a class="btn br-btn-ghost btn-sm"
                                        href="<?= APP_URL ?>/pages/problem.php?id=<?= (int) $r['id'] ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$allRequests): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No problems found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    const APP_URL = '<?= APP_URL ?>';

    const adminRecorder = new VoiceRecorder({
        dot: 'admin-rec-dot',
        timer: 'admin-rec-timer',
        status: 'admin-rec-status',
        wave: 'admin-rec-wave',
        main: 'admin-rec-main',
        trash: 'admin-rec-trash',
        play: 'admin-rec-play',
        audio: 'admin-rec-audio',
        preview: 'admin-rec-preview'
    });

    function setSolveRequest(requestId) {
        const select = document.getElementById('admin-request-id');
        if (!select) return;
        select.value = String(requestId);
        select.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.getElementById('admin-solution-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        adminRecorder.appendTo(formData, 'voice_response');

        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        try {
            const res = await fetch(APP_URL + '/api/manage_request.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            BrainRent.toast(data.success ? data.message : (data.error || 'Could not submit solution'), data.success ? 'success' : 'error');
            if (data.success) {
                setTimeout(() => location.reload(), 900);
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Admin Solution';
            }
        } catch (err) {
            BrainRent.toast('Network error', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Admin Solution';
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>