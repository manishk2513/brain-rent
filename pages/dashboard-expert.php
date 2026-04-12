<?php
// pages/dashboard-expert.php — Expert Dashboard
$title = 'Expert Dashboard';
require_once __DIR__ . '/../config/auth.php';
requireExpert();
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$userId = currentUserId();

$profile = $db->fetchOne("SELECT ep.*, u.full_name FROM expert_profiles ep INNER JOIN users u ON ep.user_id = u.id WHERE ep.user_id = ?", [$userId]);
$wallet = $db->fetchOne("SELECT * FROM expert_wallet WHERE expert_user_id = ?", [$userId]);
$newReqs = $db->fetchAll(
  "SELECT tr.*, u.full_name AS client_name, pu.full_name AS preferred_expert_name,
          (CASE WHEN tr.expert_id = ? THEN 1 ELSE 0 END) AS is_assigned_to_me
   FROM thinking_requests tr
   INNER JOIN users u ON tr.client_id = u.id
   LEFT JOIN users pu ON tr.expert_id = pu.id
   WHERE tr.status = 'submitted'
   ORDER BY tr.urgency DESC, tr.created_at",
  [$userId]
);
$activeReqs = $db->fetchAll(
  "SELECT tr.*, u.full_name AS client_name
   FROM thinking_requests tr
   INNER JOIN users u ON tr.client_id = u.id
   WHERE tr.status = 'submitted'
      OR (tr.expert_id = ? AND tr.status IN ('accepted','thinking'))
   ORDER BY tr.created_at DESC",
  [$userId]
);
$thisMonth = $db->fetchOne("SELECT COUNT(*) AS cnt, IFNULL(SUM(p.expert_payout),0) AS earnings FROM payments p INNER JOIN thinking_requests tr ON p.request_id = tr.id WHERE p.payee_id = ? AND p.status = 'released' AND MONTH(p.released_at)=MONTH(NOW()) AND YEAR(p.released_at)=YEAR(NOW())", [$userId]);
$recentEarnings = $db->fetchAll("SELECT p.expert_payout, p.status, p.created_at, tr.title AS req_title, u.full_name AS client_name FROM payments p INNER JOIN thinking_requests tr ON p.request_id = tr.id INNER JOIN users u ON tr.client_id = u.id WHERE p.payee_id = ? ORDER BY p.created_at DESC LIMIT 10", [$userId]);
$categories = $db->fetchAll("SELECT id, name FROM expertise_categories WHERE is_active = 1 ORDER BY name");

$urgencyColors = ['normal' => 'br-badge-gray', 'urgent' => 'br-badge-gold', 'critical' => 'br-badge-danger'];
$urgencyLabels = ['normal' => 'Normal', 'urgent' => '⚡ Urgent', 'critical' => '🔥 Critical'];

$bankName = $wallet['bank_account_name'] ?? '';
$bankNumber = $wallet['bank_account_number'] ?? '';
$bankIfsc = $wallet['bank_ifsc'] ?? '';
$upiId = $wallet['upi_id'] ?? '';
$maskedAccount = $bankNumber
  ? str_repeat('*', max(0, strlen($bankNumber) - 4)) . substr($bankNumber, -4)
  : '—';
?>
<div style="padding-top:64px;display:flex;height:calc(100vh - 64px)">

  <!-- ===== SIDEBAR ===== -->
  <div class="br-sidebar">
    <div class="mb-3 px-2">
      <div class="text-subtle" style="font-size:.72rem;font-weight:600;margin-bottom:2px">EXPERT PORTAL</div>
      <div class="fw-semibold small"><?= htmlspecialchars($user['full_name']) ?></div>
      <?php if ($profile['is_verified']): ?><span class="br-badge br-badge-teal mt-1" style="font-size:.68rem">✓
          Verified</span><?php endif; ?>
    </div>
    <a href="#" class="br-nav-item active" onclick="showSection('exp-overview',this)"><i
        class="bi bi-grid me-2"></i>Overview</a>
    <a href="#" class="br-nav-item" onclick="showSection('exp-requests',this)">
      <i class="bi bi-inbox me-2"></i>Requests
      <?php if (count($newReqs)): ?><span class="br-nav-badge"><?= count($newReqs) ?></span><?php endif; ?>
    </a>
    <a href="#" class="br-nav-item" onclick="showSection('exp-respond',this)"><i class="bi bi-mic me-2"></i>Submit
      Response</a>
    <a href="#" class="br-nav-item" onclick="showSection('exp-wallet',this)"><i class="bi bi-wallet2 me-2"></i>Wallet &
      Earnings</a>
    <a href="#" class="br-nav-item" onclick="showSection('exp-settings',this)"><i class="bi bi-gear me-2"></i>Profile
      Settings</a>
    <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--br-border)">
      <a href="<?= APP_URL ?>/pages/dashboard-client.php" class="br-nav-item"><i class="bi bi-person me-2"></i>Client
        Mode</a>
    </div>
  </div>

  <!-- ===== MAIN ===== -->
  <div class="br-dash-main">

    <!-- ===== OVERVIEW ===== -->
    <div id="section-exp-overview">
      <div class="mb-4">
        <h1 class="br-section-title fs-3 mb-1">Expert Dashboard 🧠</h1>
        <p class="text-muted small"><?= count($newReqs) ?> open problem(s) available to experts.</p>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Total Sessions</div>
            <div class="br-metric-value"><?= $profile['total_sessions'] ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Active</div>
            <div class="br-metric-value text-violet"><?= count($activeReqs) ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Avg. Rating</div>
            <div class="br-metric-value text-gold"><?= number_format($profile['average_rating'], 1) ?>⭐</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">This Month</div>
            <div class="br-metric-value text-gold">$<?= number_format($thisMonth['earnings'], 0) ?></div>
          </div>
        </div>
      </div>

      <!-- Wallet Quick View -->
      <div class="br-wallet-card mb-4">
        <div class="text-subtle small mb-1" style="text-transform:uppercase;letter-spacing:1px">Available Balance</div>
        <div class="br-wallet-balance">$<?= number_format($wallet['available_balance'], 2) ?></div>
        <div class="text-muted small mt-1">$<?= number_format($wallet['pending_balance'], 2) ?> pending ·
          $<?= number_format($wallet['total_earned'], 2) ?> total earned</div>
        <div class="d-flex gap-2 mt-3">
          <button class="btn br-btn-ghost btn-sm" onclick="BrainRent.toast('Withdrawal initiated!','success')">Withdraw
            Funds</button>
          <button class="btn br-btn-outline btn-sm" onclick="showSection('exp-wallet',null)">View Statement</button>
        </div>
      </div>

      <!-- New Requests -->
      <?php if ($newReqs): ?>
        <div class="br-table mb-4">
          <div class="d-flex justify-content-between align-items-center p-3"
            style="border-bottom:1px solid var(--br-border)">
            <h6 class="mb-0 fw-semibold">📥 Open Problem Grid</h6>
            <span class="br-badge br-badge-gold"><?= count($newReqs) ?> open</span>
          </div>
          <div class="table-responsive">
            <table class="table br-table mb-0">
              <thead>
                <tr>
                  <th>Problem</th>
                  <th>Client</th>
                  <th>Urgency</th>
                  <th>Rate</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($newReqs as $req): ?>
                  <tr>
                    <td>
                      <div class="fw-medium small">
                        <?= htmlspecialchars(substr($req['title'], 0, 55)) ?>    <?= strlen($req['title']) > 55 ? '…' : '' ?>
                      </div>
                      <?php if (!empty($req['preferred_expert_name'])): ?>
                        <div class="text-subtle" style="font-size:.72rem">Preferred:
                          <?= htmlspecialchars($req['preferred_expert_name']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($req['client_name']) ?></td>
                    <td><span
                        class="br-badge <?= $urgencyColors[$req['urgency']] ?>"><?= $urgencyLabels[$req['urgency']] ?></span>
                    </td>
                    <td class="mono text-gold small">$<?= number_format($req['agreed_rate'], 0) ?></td>
                    <td>
                      <?php if (!empty($req['is_assigned_to_me'])): ?>
                        <div class="d-flex gap-1">
                          <button class="btn br-btn-gold btn-sm" data-action="accept"
                            data-request-id="<?= $req['id'] ?>">Accept</button>
                          <button class="btn br-btn-ghost btn-sm" data-action="decline"
                            data-request-id="<?= $req['id'] ?>">Decline</button>
                        </div>
                      <?php else: ?>
                        <button class="btn br-btn-gold btn-sm" type="button"
                          onclick="jumpToSolve(<?= (int) $req['id'] ?>)">Solve Now</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ===== SUBMIT RESPONSE ===== -->
    <div id="section-exp-respond" style="display:none">
      <div class="mb-4">
        <h1 class="br-section-title fs-3 mb-1">Submit Response 🎙️</h1>
        <p class="text-muted small">Select an active request and submit your thinking.</p>
      </div>

      <?php if ($activeReqs): ?>
        <div class="mb-4">
          <label class="br-form-label">Select Request</label>
          <select class="br-form-control form-control" id="respond-select" onchange="loadRequestDetail(this.value)">
            <option value="">— Choose a request —</option>
            <?php foreach ($activeReqs as $r): ?>
              <option value="<?= $r['id'] ?>"><?= htmlspecialchars(substr($r['title'], 0, 70)) ?> ·
                <?= htmlspecialchars($r['client_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div id="request-detail-area"></div>

      <form id="response-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="submit_response">
        <input type="hidden" name="request_id" id="respond-req-id">

        <div class="row g-4">
          <div class="col-12 col-lg-8">
            <div class="br-profile-section mb-3">
              <h6 class="fw-semibold mb-3">🎙️ Voice Response <span class="text-subtle">(highly recommended)</span></h6>
              <div class="br-recorder">
                <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                  <div class="rec-dot" id="exp-rec-dot"></div>
                  <div class="rec-timer" id="exp-rec-timer">00:00</div>
                  <div class="text-subtle small" id="exp-rec-status">Record your thinking</div>
                </div>
                <div class="br-waveform" id="exp-rec-wave"></div>
                <div class="d-flex align-items-center justify-content-center gap-3 mt-3">
                  <button type="button" class="rec-btn-sec" id="exp-rec-trash" disabled>🗑️</button>
                  <button type="button" class="rec-btn-main" id="exp-rec-main">🎙️</button>
                  <button type="button" class="rec-btn-sec" id="exp-rec-play" disabled>▶️</button>
                </div>
                <div id="exp-rec-preview" style="display:none;margin-top:10px"><audio id="exp-rec-audio" controls
                    class="w-100"></audio></div>
              </div>
            </div>
            <div class="br-profile-section mb-3">
              <div class="mb-3">
                <label class="br-form-label">📝 Written Analysis *</label>
                <textarea name="written_response" class="br-form-control form-control" rows="6"
                  placeholder="Your structured analysis and reasoning…"></textarea>
              </div>
              <div class="mb-3">
                <label class="br-form-label">💡 Key Insights <span class="text-subtle">(one per line)</span></label>
                <textarea name="key_insights" class="br-form-control form-control" rows="4"
                  placeholder="1. At your current scale, pgvector is underrated&#10;2. Your Postgres expertise is a genuine moat here&#10;3. Pinecone's real cost isn't what you think"></textarea>
              </div>
              <div class="mb-3">
                <label class="br-form-label">☐ Action Items for Client <span class="text-subtle">(one per
                    line)</span></label>
                <textarea name="action_items" class="br-form-control form-control" rows="4"
                  placeholder="1. Run pgvector HNSW benchmark with your actual data&#10;2. Get Pinecone enterprise pricing before deciding&#10;3. Read the 2024 ANN benchmarks comparison"></textarea>
              </div>
              <div class="mb-3">
                <label class="br-form-label">🔗 Resource Links <span class="text-subtle">(one per line)</span></label>
                <textarea name="resource_links" class="br-form-control form-control" rows="3"
                  placeholder="https://pgvector.github.io&#10;https://ann-benchmarks.com"></textarea>
              </div>
              <div>
                <label class="br-form-label">⏱️ Time Spent (minutes)</label>
                <input type="number" name="thinking_minutes" class="br-form-control form-control" placeholder="e.g. 25"
                  style="max-width:160px">
              </div>
            </div>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn br-btn-ghost">Save Draft</button>
              <button type="submit" class="btn br-btn-gold px-4">Submit Response <i
                  class="bi bi-arrow-right ms-1"></i></button>
            </div>
          </div>
          <div class="col-12 col-lg-4">
            <div class="br-profile-section mb-3">
              <h6 class="fw-semibold mb-3">📋 Response Checklist</h6>
              <div class="d-flex flex-column gap-2 small">
                <?php foreach (['Voice recording included', 'At least 3 key insights', 'Clear, actionable items', 'Resource links added', 'Addressed client urgency'] as $item): ?>
                  <label class="d-flex align-items-center gap-2"><input type="checkbox"
                      style="accent-color:var(--br-gold)"> <?= $item ?></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="br-profile-section">
              <h6 class="fw-semibold mb-2">🎯 Quality Tips</h6>
              <div class="d-flex flex-column gap-2 text-muted small">
                <div>• Voice first — think out loud, then write the structured version</div>
                <div>• Be opinionated: "I would choose X because…" beats a pros/cons list</div>
                <div>• Surface what the client would miss without your expertise</div>
                <div>• Shorter and sharper beats comprehensive and vague</div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- ===== WALLET ===== -->
    <div id="section-exp-wallet" style="display:none">
      <div class="mb-4">
        <h1 class="br-section-title fs-3 mb-1">Wallet & Earnings 💰</h1>
      </div>
      <div class="br-wallet-card mb-4">
        <div class="text-subtle small mb-1" style="text-transform:uppercase;letter-spacing:1px">Available to Withdraw
        </div>
        <div class="br-wallet-balance">$<?= number_format($wallet['available_balance'], 2) ?></div>
        <div class="text-muted small mt-1">$<?= number_format($wallet['pending_balance'], 2) ?> pending · Releases after
          client confirmation</div>
        <div class="d-flex gap-2 mt-3">
          <button class="btn br-btn-gold btn-sm" onclick="BrainRent.toast('Withdrawal initiated!','success')">Withdraw
            to Bank</button>
          <button class="btn br-btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#walletModal">Add Bank Account
            / UPI</button>
        </div>
      </div>

      <div class="br-profile-section mb-4">
        <h6 class="fw-semibold mb-2">Payout Details</h6>
        <div class="small text-muted mb-2">Add your bank account or UPI ID to receive payouts.</div>
        <div class="row g-2 small">
          <div class="col-12 col-md-6">
            <div class="text-subtle">Account Name</div>
            <div><?= htmlspecialchars($bankName ?: '—') ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-subtle">Account Number</div>
            <div><?= htmlspecialchars($maskedAccount) ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-subtle">IFSC</div>
            <div><?= htmlspecialchars($bankIfsc ?: '—') ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-subtle">UPI ID</div>
            <div><?= htmlspecialchars($upiId ?: '—') ?></div>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn br-btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#walletModal">Edit Payout
            Details</button>
        </div>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">This Month</div>
            <div class="br-metric-value text-gold">$<?= number_format($thisMonth['earnings'], 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Total Earned</div>
            <div class="br-metric-value">$<?= number_format($wallet['total_earned'], 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Total Withdrawn</div>
            <div class="br-metric-value">$<?= number_format($wallet['total_withdrawn'], 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="br-metric-card">
            <div class="br-metric-label">Sessions (Month)</div>
            <div class="br-metric-value"><?= $thisMonth['cnt'] ?></div>
          </div>
        </div>
      </div>
      <div class="br-table">
        <div class="p-3 fw-semibold small" style="border-bottom:1px solid var(--br-border)">Recent Transactions</div>
        <div class="table-responsive">
          <table class="table br-table mb-0">
            <thead>
              <tr>
                <th>Request</th>
                <th>Client</th>
                <th>Date</th>
                <th>Status</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentEarnings as $e): ?>
                <tr>
                  <td class="small"><?= htmlspecialchars(substr($e['req_title'], 0, 50)) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($e['client_name']) ?></td>
                  <td class="text-muted small"><?= date('M j', strtotime($e['created_at'])) ?></td>
                  <td><span
                      class="br-status <?= $e['status'] === 'released' ? 'br-status-completed' : 'br-status-thinking' ?>"><?= ucfirst($e['status']) ?></span>
                  </td>
                  <td class="text-end mono <?= $e['status'] === 'released' ? 'text-success' : 'text-muted' ?>">
                    +$<?= number_format($e['expert_payout'], 2) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$recentEarnings): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">No transactions yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===== SETTINGS ===== -->
    <div id="section-exp-settings" style="display:none">
      <div class="mb-4">
        <h1 class="br-section-title fs-3 mb-1">Profile Settings ⚙️</h1>
      </div>
      <div class="row g-4">
        <div class="col-12 col-lg-8">
          <div class="br-profile-section">
            <form method="POST" action="<?= APP_URL ?>/api/update_profile.php">
              <div class="mb-3"><label class="br-form-label">Headline</label><input type="text" name="headline"
                  class="br-form-control form-control" value="<?= htmlspecialchars($profile['headline'] ?? '') ?>">
              </div>
              <div class="mb-3"><label class="br-form-label">Bio</label><textarea name="bio"
                  class="br-form-control form-control"
                  rows="5"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea></div>
              <div class="mb-3"><label class="br-form-label">Expertise Areas <span
                    class="text-subtle">(comma-separated)</span></label>
                <input type="text" name="expertise_areas" class="br-form-control form-control"
                  value="<?= htmlspecialchars(implode(', ', json_decode($profile['expertise_areas'] ?? '[]', true) ?? [])) ?>">
              </div>
              <div class="row g-3 mb-3">
                <div class="col-6"><label class="br-form-label">Session Rate (USD)</label><input type="number"
                    name="rate_per_session" class="br-form-control form-control"
                    value="<?= $profile['rate_per_session'] ?>"></div>
                <div class="col-6"><label class="br-form-label">Max Response Hours</label><input type="number"
                    name="max_response_hours" class="br-form-control form-control"
                    value="<?= $profile['max_response_hours'] ?>"></div>
                <div class="col-6"><label class="br-form-label">Max Concurrent Requests</label><input type="number"
                    name="max_active_requests" class="br-form-control form-control"
                    value="<?= $profile['max_active_requests'] ?>"></div>
                <div class="col-6"><label class="br-form-label">Availability</label>
                  <select name="is_available" class="br-form-control form-control form-select">
                    <option value="1" <?= $profile['is_available'] ? 'selected' : '' ?>>🟢 Available</option>
                    <option value="0" <?= !$profile['is_available'] ? 'selected' : '' ?>>🔴 Paused</option>
                  </select>
                </div>
              </div>
              <button type="button" class="btn br-btn-gold" onclick="BrainRent.toast('Profile updated!','success')">Save
                Changes</button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ===== WALLET MODAL ===== -->
<div class="modal fade" id="walletModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--br-card);border:1px solid var(--br-border2);border-radius:18px">
      <div class="modal-header" style="border-color:var(--br-border)">
        <h5 class="modal-title fw-semibold" style="font-family:'Playfair Display',serif">Bank Account / UPI</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="wallet-form">
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="br-form-label">Account Holder Name</label>
              <input type="text" name="bank_account_name" class="br-form-control form-control"
                value="<?= htmlspecialchars($bankName) ?>" placeholder="e.g. Rahul Sharma">
            </div>
            <div class="col-12 col-md-6">
              <label class="br-form-label">Account Number</label>
              <input type="text" name="bank_account_number" class="br-form-control form-control"
                value="<?= htmlspecialchars($bankNumber) ?>" placeholder="e.g. 1234567890">
            </div>
            <div class="col-12 col-md-6">
              <label class="br-form-label">IFSC</label>
              <input type="text" name="bank_ifsc" class="br-form-control form-control"
                value="<?= htmlspecialchars($bankIfsc) ?>" placeholder="e.g. HDFC0001234">
            </div>
            <div class="col-12 col-md-6">
              <label class="br-form-label">UPI ID</label>
              <input type="text" name="upi_id" class="br-form-control form-control"
                value="<?= htmlspecialchars($upiId) ?>" placeholder="e.g. name@upi">
            </div>
          </div>
          <div class="text-subtle small mt-3">Provide either complete bank details or a UPI ID. You can update this
            anytime.</div>
        </div>
        <div class="modal-footer" style="border-color:var(--br-border)">
          <button type="button" class="btn br-btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn br-btn-gold" id="wallet-save-btn">Save Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const APP_URL = '<?= APP_URL ?>';

  // Expert voice recorder
  window._expertRecorder = new VoiceRecorder({
    dot: 'exp-rec-dot',
    timer: 'exp-rec-timer',
    status: 'exp-rec-status',
    wave: 'exp-rec-wave',
    main: 'exp-rec-main',
    trash: 'exp-rec-trash',
    play: 'exp-rec-play',
    audio: 'exp-rec-audio',
    preview: 'exp-rec-preview'
  });

  function showSection(id, el) {
    document.querySelectorAll('[id^="section-exp-"]').forEach(s => s.style.display = 'none');
    const t = document.getElementById('section-' + id);
    if (t) t.style.display = 'block';
    document.querySelectorAll('.br-nav-item').forEach(a => a.classList.remove('active'));
    if (el) el.classList.add('active');
  }

  document.getElementById('response-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const reqId = document.getElementById('respond-req-id').value;
    if (!reqId) {
      BrainRent.toast('Please select a request first', 'error');
      return;
    }
    const fd = new FormData(this);
    _expertRecorder.appendTo(fd, 'voice_response');
    const res = await fetch(APP_URL + '/api/manage_request.php', {
      method: 'POST',
      body: fd
    });
    const data = await res.json();
    BrainRent.toast(data.success ? 'Response submitted! Client notified.' : (data.error || 'Error'), data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
  });

  async function loadRequestDetail(id) {
    document.getElementById('respond-req-id').value = id;
  }

  function jumpToSolve(requestId) {
    showSection('exp-respond', document.querySelector('.br-nav-item[onclick*="exp-respond"]'));
    const select = document.getElementById('respond-select');
    if (!select) return;
    select.value = String(requestId);
    loadRequestDetail(requestId);
  }

  document.querySelectorAll('[id^="section-exp-"]').forEach(s => {
    if (s.id !== 'section-exp-overview') s.style.display = 'none';
  });

  document.getElementById('wallet-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('wallet-save-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const fd = new FormData(this);
    const res = await fetch(APP_URL + '/api/update_wallet.php', {
      method: 'POST',
      body: fd
    });
    const data = await res.json();

    if (data.success) {
      BrainRent.toast('Payout details saved', 'success');
      const modalEl = document.getElementById('walletModal');
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.hide();
      setTimeout(() => location.reload(), 600);
    } else {
      BrainRent.toast(data.error || 'Unable to save details', 'error');
      btn.disabled = false;
      btn.textContent = 'Save Details';
    }
  });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>