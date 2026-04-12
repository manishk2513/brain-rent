<?php
// pages/dashboard-client.php — Client Dashboard
$title = 'Client Dashboard';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$userId = currentUserId();

// Metrics
$metrics = $db->fetchOne(
  "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('submitted','accepted','thinking') THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) AS awaiting,
        IFNULL(SUM(CASE WHEN payment_status = 'released' THEN agreed_rate END),0) AS spent
     FROM thinking_requests WHERE client_id = ?",
  [$userId]
);

// Requests
$requests = $db->fetchAll(
  "SELECT tr.id, tr.title, tr.status, tr.agreed_rate, tr.urgency, tr.created_at,
            u.full_name AS expert_name
     FROM thinking_requests tr
     INNER JOIN users u ON tr.expert_id = u.id
     WHERE tr.client_id = ?
     ORDER BY tr.created_at DESC",
  [$userId]
);
$recentRequests = array_slice($requests, 0, 6);

$statusLabels = [
  'submitted' => ['br-status-submitted', '📬 Submitted'],
  'accepted' => ['br-status-accepted', '✓ Accepted'],
  'thinking' => ['br-status-thinking', '⚙️ In Progress'],
  'responded' => ['br-status-responded', '🎙️ Response Ready'],
  'completed' => ['br-status-completed', '✓ Completed'],
  'declined' => ['br-status-declined', '✗ Declined'],
  'disputed' => ['br-status-disputed', '⚠ Disputed'],
];
?>
<div style="padding-top:64px;display:flex;height:calc(100vh - 64px)">

  <!-- ===== SIDEBAR ===== -->
  <div class="br-sidebar">
    <div class="mb-4 px-2">
      <div class="text-subtle" style="font-size:.72rem;font-weight:600;margin-bottom:2px">CLIENT PORTAL</div>
      <div class="fw-semibold"><?= htmlspecialchars($user['full_name']) ?></div>
    </div>
    <a href="#overview" class="br-nav-item active" onclick="showSection('overview',this)"><i
        class="bi bi-grid icon me-2"></i>Overview</a>
    <a href="#requests" class="br-nav-item" onclick="showSection('requests',this)">
      <i class="bi bi-clipboard icon me-2"></i>My Requests
      <?php if ($metrics['awaiting'] > 0): ?><span
          class="br-nav-badge"><?= $metrics['awaiting'] ?></span><?php endif; ?>
    </a>
    <a href="#notifications" class="br-nav-item" onclick="showSection('notifications',this)"><i
        class="bi bi-bell icon me-2"></i>Notifications</a>
    <a href="#saved" class="br-nav-item" onclick="showSection('saved',this)"><i
        class="bi bi-bookmark icon me-2"></i>Saved Experts</a>
    <div style="margin-top:auto;padding-top:20px;border-top:1px solid var(--br-border)">
      <a href="<?= APP_URL ?>/pages/browse.php" class="br-nav-item"><i class="bi bi-search icon me-2"></i>Find
        Experts</a>
      <?php if (in_array($user['user_type'], ['expert', 'both']) && isExpertVerified($user['id'])): ?>
        <a href="<?= APP_URL ?>/pages/dashboard-expert.php" class="br-nav-item"><i
            class="bi bi-stars icon me-2"></i>Expert Mode</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== MAIN ===== -->
  <div class="br-dash-main">

    <!-- OVERVIEW -->
    <div id="section-overview">
      <div class="mb-4">
        <h1 class="br-section-title fs-3 mb-1">Good day, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?> 👋
        </h1>
        <?php if ($metrics['awaiting'] > 0): ?>
          <p class="text-muted small">You have <?= $metrics['awaiting'] ?> response(s) waiting for your review.</p>
        <?php else: ?>
          <p class="text-muted small">Your expert thinking dashboard.</p>
        <?php endif; ?>
      </div>

      <!-- Metrics -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Total Requests</div>
            <div class="br-metric-value"><?= $metrics['total'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Active</div>
            <div class="br-metric-value text-violet"><?= $metrics['active'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Awaiting Review</div>
            <div class="br-metric-value text-gold"><?= $metrics['awaiting'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Total Spent</div>
            <div class="br-metric-value text-gold">$<?= number_format($metrics['spent'], 0) ?></div>
          </div>
        </div>
      </div>

      <?php if ($metrics['awaiting'] > 0): ?>
        <div class="br-alert br-alert-warning d-flex align-items-center gap-3 mb-4" style="cursor:pointer"
          onclick="showSection('requests',null)">
          <i class="bi bi-mic-fill text-gold fs-5"></i>
          <div class="flex-grow-1">
            <div class="fw-medium small">New expert response(s) ready!</div>
            <div class="text-muted" style="font-size:.8rem">Click to review and release payment</div>
          </div>
          <button class="btn br-btn-gold btn-sm">Review Now →</button>
        </div>
      <?php endif; ?>

      <!-- Recent Requests Table -->
      <div class="br-table">
        <div class="d-flex justify-content-between align-items-center p-3"
          style="border-bottom:1px solid var(--br-border)">
          <h6 class="fw-semibold mb-0">Recent Requests</h6>
          <a href="<?= APP_URL ?>/pages/browse.php" class="btn br-btn-outline btn-sm">+ New Request</a>
        </div>
        <div class="table-responsive">
          <table class="table br-table mb-0">
            <thead>
              <tr>
                <th>Problem</th>
                <th>Expert</th>
                <th>Status</th>
                <th>Rate</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentRequests as $req):
                [$cls, $label] = $statusLabels[$req['status']] ?? ['br-status-completed', $req['status']];
                ?>
                <tr>
                  <td>
                    <div class="fw-medium small">
                      <?= htmlspecialchars(substr($req['title'], 0, 60)) ?>  <?= strlen($req['title']) > 60 ? '…' : '' ?>
                    </div>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars($req['expert_name']) ?></td>
                  <td><span class="br-status <?= $cls ?>"><?= $label ?></span></td>
                  <td class="mono text-gold small">$<?= number_format($req['agreed_rate'], 0) ?></td>
                  <td>
                    <?php if ($req['status'] === 'responded'): ?>
                      <a class="btn br-btn-gold btn-sm"
                        href="<?= APP_URL ?>/pages/problem.php?id=<?= (int) $req['id'] ?>">Review</a>
                    <?php else: ?>
                      <a class="btn br-btn-ghost btn-sm"
                        href="<?= APP_URL ?>/pages/problem.php?id=<?= (int) $req['id'] ?>">View</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$requests): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">No requests yet. <a
                      href="<?= APP_URL ?>/pages/browse.php" class="text-gold">Browse experts →</a></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /overview -->

    <!-- MY REQUESTS -->
    <div id="section-requests">
      <div class="mb-4">
        <h2 class="br-section-title fs-4 mb-1">My Requests</h2>
        <p class="text-muted small">All problems you have submitted to experts.</p>
      </div>

      <div class="br-table">
        <div class="table-responsive">
          <table class="table br-table mb-0">
            <thead>
              <tr>
                <th>Problem</th>
                <th>Expert</th>
                <th>Status</th>
                <th>Urgency</th>
                <th>Rate</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests as $req):
                [$cls, $label] = $statusLabels[$req['status']] ?? ['br-status-completed', $req['status']];
                ?>
                <tr>
                  <td>
                    <div class="fw-medium small">
                      <?= htmlspecialchars(substr($req['title'], 0, 70)) ?>  <?= strlen($req['title']) > 70 ? '…' : '' ?>
                    </div>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars($req['expert_name']) ?></td>
                  <td><span class="br-status <?= $cls ?>"><?= $label ?></span></td>
                  <td class="text-muted small"><?= htmlspecialchars($req['urgency'] ?? 'normal') ?></td>
                  <td class="mono text-gold small">$<?= number_format($req['agreed_rate'], 0) ?></td>
                  <td class="text-muted small"><?= date('M j, Y', strtotime($req['created_at'])) ?></td>
                  <td>
                    <?php if ($req['status'] === 'responded'): ?>
                      <a class="btn br-btn-gold btn-sm"
                        href="<?= APP_URL ?>/pages/problem.php?id=<?= (int) $req['id'] ?>">Review</a>
                    <?php else: ?>
                      <a class="btn br-btn-ghost btn-sm"
                        href="<?= APP_URL ?>/pages/problem.php?id=<?= (int) $req['id'] ?>">View</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$requests): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">No requests yet. <a
                      href="<?= APP_URL ?>/pages/browse.php" class="text-gold">Browse experts →</a></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /requests -->

    <!-- NOTIFICATIONS (placeholder) -->
    <div id="section-notifications" style="display:none">
      <div class="mb-4">
        <h2 class="br-section-title fs-4 mb-1">Notifications</h2>
        <p class="text-muted small">Your latest updates will appear here.</p>
      </div>
      <div class="br-card p-4 text-muted small">No notifications to show.</div>
    </div>

    <!-- SAVED (placeholder) -->
    <div id="section-saved" style="display:none">
      <div class="mb-4">
        <h2 class="br-section-title fs-4 mb-1">Saved Experts</h2>
        <p class="text-muted small">Save experts to revisit later.</p>
      </div>
      <div class="br-card p-4 text-muted small">No saved experts yet.</div>
    </div>

  </div><!-- /dash-main -->
</div>

<!-- ===== RESPONSE REVIEW MODAL ===== -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="background:var(--br-card);border:1px solid var(--br-border2);border-radius:18px">
      <div class="modal-header" style="border-color:var(--br-border)">
        <h5 class="modal-title fw-semibold" style="font-family:'Playfair Display',serif">Expert Response</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="review-modal-body">
        <div class="text-center py-4">
          <div class="spinner-border text-warning"></div>
        </div>
      </div>
      <div class="modal-footer" style="border-color:var(--br-border)">
        <button type="button" class="btn br-btn-ghost" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-dispute">Report Issue</button>
        <button type="button" class="btn br-btn-gold" id="btn-complete">✓ Confirm & Release Payment</button>
      </div>
    </div>
  </div>
</div>

<script>
  const APP_URL = '<?= APP_URL ?>';
  let reviewRequestId = null;

  function showSection(id, el) {
    document.querySelectorAll('[id^="section-"]').forEach(s => s.style.display = 'none');
    const target = document.getElementById('section-' + id);
    if (target) target.style.display = 'block';
    document.querySelectorAll('.br-nav-item').forEach(a => a.classList.remove('active'));
    if (el) el.classList.add('active');
  }

  async function openRequestModal(requestId) {
    reviewRequestId = requestId;
    const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    modal.show();

    const body = document.getElementById('review-modal-body');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';

    const res = await fetch(`${APP_URL}/api/get_request.php?request_id=${requestId}`);
    const data = await res.json();
    if (!data.success) {
      body.innerHTML = '<p class="text-muted text-center py-4">Could not load request.</p>';
      toggleReviewActions(false);
      return;
    }

    const req = data.request || {};
    const r = data.response || null;
    const canReview = req.status === 'responded';

    toggleReviewActions(canReview);

    const insights = JSON.parse(r?.key_insights || '[]');
    const actions = JSON.parse(r?.action_items || '[]');
    const resources = JSON.parse(r?.resources_links || '[]');

    body.innerHTML = `
    <div class="p-3 mb-3" style="background:var(--br-dark3);border-radius:10px">
      <div class="text-subtle" style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Problem Details</div>
      <div class="fw-medium mb-1">${escHtml(req.title || 'Untitled')}</div>
      <div class="text-muted small">Expert: ${escHtml(req.expert_name || '—')} · Urgency: ${escHtml(req.urgency || 'normal')}</div>
      ${req.problem_text ? `<div class="text-muted small mt-2" style="white-space:pre-wrap">${escHtml(req.problem_text)}</div>` : ''}
      ${req.problem_voice_url ? `<audio src="${req.problem_voice_url}" controls class="w-100 mt-2"></audio>` : ''}
    </div>

    ${r ? `
    ${r.voice_url ? `
    <div class="p-3 mb-3" style="background:var(--br-dark3);border-radius:10px">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small fw-medium">🎙️ Voice Response</span>
        ${r.voice_duration_seconds ? `<span class="mono text-muted small">${Math.floor(r.voice_duration_seconds / 60)}:${String(r.voice_duration_seconds % 60).padStart(2, '0')}</span>` : ''}
      </div>
      <audio src="${r.voice_url}" controls class="w-100"></audio>
    </div>` : ''}

    ${r.written_response ? `
    <div class="mb-3">
      <div class="text-subtle small mb-2" style="text-transform:uppercase;letter-spacing:1px">Written Analysis</div>
      <div class="text-muted small lh-lg" style="white-space:pre-wrap">${escHtml(r.written_response)}</div>
    </div>` : ''}

    ${insights.length ? `
    <div class="mb-3">
      <div class="text-subtle small mb-2" style="text-transform:uppercase;letter-spacing:1px">Key Insights</div>
      ${insights.map((ins, i) => `
        <div class="d-flex gap-2 py-2" style="border-bottom:1px solid var(--br-border)">
          <div style="min-width:22px;height:22px;background:var(--br-gold-dim);color:var(--br-gold);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700">${i + 1}</div>
          <div class="text-muted small">${escHtml(ins)}</div>
        </div>`).join('')}
    </div>` : ''}

    ${actions.length ? `
    <div class="mb-3">
      <div class="text-subtle small mb-2" style="text-transform:uppercase;letter-spacing:1px">Action Items</div>
      ${actions.map(a => `<div class="small text-muted mb-1"><label class="d-flex gap-2 align-items-start"><input type="checkbox" style="accent-color:var(--br-gold);margin-top:2px"> <span>${escHtml(a)}</span></label></div>`).join('')}
    </div>` : ''}

    ${resources.length ? `
    <div class="mb-3">
      <div class="text-subtle small mb-2" style="text-transform:uppercase;letter-spacing:1px">Resources</div>
      ${resources.map(link => `<div class="small text-muted mb-1">${escHtml(link)}</div>`).join('')}
    </div>` : ''}
    ` : `<div class="text-muted small">Response not submitted yet.</div>`}

    ${canReview ? `
    <hr class="br-divider">
    <div>
      <div class="small fw-medium mb-2">Rate This Response</div>
      <div class="d-flex gap-4 flex-wrap mb-2">
        ${['Clarity', 'Depth', 'Usefulness'].map(r => `
          <div>
            <div class="text-subtle" style="font-size:.72rem;margin-bottom:3px">${r}</div>
            <div data-star-rating="${r.toLowerCase()}" class="d-flex gap-1">
              ${[1, 2, 3, 4, 5].map(s => `<span class="br-star" data-val="${s}" style="cursor:pointer;color:var(--br-gold)">★</span>`).join('')}
              <input type="hidden" name="${r.toLowerCase()}_rating" value="5">
            </div>
          </div>`).join('')}
      </div>
      <textarea class="br-form-control form-control mt-2" rows="2" id="review-text" placeholder="Optional: Leave a written review…"></textarea>
    </div>` : ''}
    `;

    BrainRent.initStarRatings();

    if (canReview) {
      document.getElementById('btn-complete').onclick = async () => {
        const btn = document.getElementById('btn-complete');
        btn.disabled = true;
        btn.textContent = 'Processing…';

        const clarity = getRating('clarity');
        const depth = getRating('depth');
        const usefulness = getRating('usefulness');
        const overall = Math.round((clarity + depth + usefulness) / 3);

        const res = await BrainRent.post(`${APP_URL}/api/manage_request.php`, {
          action: 'complete',
          request_id: reviewRequestId,
          rating: overall,
          clarity_rating: clarity,
          depth_rating: depth,
          usefulness_rating: usefulness,
          review_text: document.getElementById('review-text')?.value || ''
        });
        if (res.success) {
          bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
          BrainRent.toast('✓ Payment released. Thank you!', 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          BrainRent.toast(res.error || 'Error', 'error');
          btn.disabled = false;
          btn.textContent = '✓ Confirm & Release Payment';
        }
      };

      document.getElementById('btn-dispute').onclick = async () => {
        const reason = prompt('Tell us what went wrong (optional):') || '';
        const res = await BrainRent.post(`${APP_URL}/api/manage_request.php`, {
          action: 'dispute',
          request_id: reviewRequestId,
          reason
        });
        if (res.success) {
          bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
          BrainRent.toast('Dispute submitted. We will contact you soon.', 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          BrainRent.toast(res.error || 'Error', 'error');
        }
      };
    }
  }

  function toggleReviewActions(show) {
    const btnComplete = document.getElementById('btn-complete');
    const btnDispute = document.getElementById('btn-dispute');
    if (btnComplete) btnComplete.style.display = show ? 'inline-block' : 'none';
    if (btnDispute) btnDispute.style.display = show ? 'inline-block' : 'none';
  }

  function getRating(name) {
    const el = document.querySelector(`[data-star-rating="${name}"] input[type=hidden]`);
    return parseInt(el?.value || '5', 10);
  }

  function escHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // Show first section
  document.querySelectorAll('[id^="section-"]').forEach(s => {
    if (s.id !== 'section-overview') s.style.display = 'none';
  });

  const params = new URLSearchParams(window.location.search);
  const requestId = parseInt(params.get('request_id') || '0', 10);
  if (requestId) {
    const nav = document.querySelector('.br-nav-item[href="#requests"]');
    showSection('requests', nav);
    openRequestModal(requestId);
  }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>