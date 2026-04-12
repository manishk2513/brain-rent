<?php
// pages/submit-problem.php — Submit a problem to an expert
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$expertUserId = (int) ($_GET['expert_id'] ?? 0);
if (!$expertUserId) {
  header('Location: ' . APP_URL . '/pages/browse.php');
  exit;
}

$db = Database::getInstance();
$expert = $db->fetchOne(
  "SELECT ep.*, u.full_name, u.profile_photo
     FROM expert_profiles ep INNER JOIN users u ON ep.user_id = u.id
     WHERE ep.user_id = ? AND ep.is_available = 1 AND u.is_active = 1",
  [$expertUserId]
);
if (!$expert) {
  header('Location: ' . APP_URL . '/pages/browse.php');
  exit;
}

$categories = $db->fetchAll("SELECT id, name FROM expertise_categories WHERE is_active = 1 ORDER BY name");
$title = 'Submit Problem';
require_once __DIR__ . '/../includes/header.php';

$baseFee = $expert['rate_per_session'];
$platformFee = round($baseFee * 0.15, 2);
$total = $baseFee + $platformFee;
$avColors = ['av-1', 'av-2', 'av-3', 'av-4', 'av-5', 'av-6'];
$avColor = $avColors[$expertUserId % count($avColors)];
?>
<main class="py-4" style="padding-top:80px!important">
  <div class="container">
    <div class="mb-3">
      <a href="<?= APP_URL ?>/pages/expert-profile.php?id=<?= $expertUserId ?>"
        class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Back to Expert Profile
      </a>
    </div>
    <div class="mb-4">
      <h1 class="br-section-title fs-3 mb-1">Submit Your Problem</h1>
      <p class="text-muted small">Submitting to <strong
          class="text-warning"><?= htmlspecialchars($expert['full_name']) ?></strong> ·
        <?= htmlspecialchars($expert['headline'] ?? '') ?>
      </p>
    </div>

    <!-- Step Indicator -->
    <div class="br-step-indicator mb-4">
      <div class="br-step-dot active" id="step-dot-1">1</div>
      <div class="br-step-connector"></div>
      <div class="br-step-label small text-muted me-2">Problem Details</div>
      <div class="br-step-dot" id="step-dot-2">2</div>
      <div class="br-step-connector"></div>
      <div class="br-step-label small text-muted me-2">Review & Pay</div>
      <div class="br-step-dot" id="step-dot-3">3</div>
      <div class="br-step-label small text-muted">Confirmation</div>
    </div>

    <form id="submit-form" enctype="multipart/form-data">
      <input type="hidden" name="expert_id" value="<?= $expertUserId ?>">
      <input type="hidden" id="base-rate" value="<?= (int) $baseFee ?>">
      <input type="hidden" name="urgency" id="urgency-input" value="normal">
      <input type="hidden" name="payment_gateway" id="payment-gateway-input" value="razorpay">

      <div class="row g-4">
        <!-- ===== MAIN COLUMN ===== -->
        <div class="col-12 col-lg-8">

          <!-- Step 1: Problem Details -->
          <div id="step1">
            <div class="br-profile-section mb-4">
              <h5 class="fw-semibold mb-3 pb-2" style="border-bottom:1px solid var(--br-border)">Describe Your Problem
              </h5>

              <div class="mb-3">
                <label class="br-form-label">Problem Title *</label>
                <input type="text" name="title" id="problem-title" class="br-form-control form-control"
                  placeholder="e.g. Should I build our own vector database or use Pinecone?" required>
              </div>

              <div class="mb-3">
                <label class="br-form-label">Category</label>
                <select name="category_id" class="br-form-control form-control form-select">
                  <option value="">— Select a category —</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="br-form-label">Describe Your Problem *</label>
                <textarea name="problem_text" id="problem-text" class="br-form-control form-control" rows="6"
                  placeholder="Give the expert all the context they need. Include: what you've tried, constraints you're working with, what a good outcome looks like…"></textarea>
                <div class="d-flex justify-content-end mt-1">
                  <small class="text-subtle" id="char-count">0 / 3000</small>
                </div>
              </div>

              <!-- Voice Recorder -->
              <div class="mb-3">
                <label class="br-form-label">🎙️ Voice Recording <span class="text-subtle">(optional — often richer than
                    text)</span></label>
                <div class="br-recorder">
                  <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                    <div class="rec-dot" id="rec-dot"></div>
                    <div class="rec-timer" id="rec-timer">00:00</div>
                    <div class="text-subtle small" id="rec-status">Ready to record</div>
                  </div>
                  <div class="br-waveform" id="rec-wave"></div>
                  <div class="d-flex align-items-center justify-content-center gap-3 mt-3">
                    <button type="button" class="rec-btn-sec" id="rec-trash" disabled title="Delete">🗑️</button>
                    <button type="button" class="rec-btn-main" id="rec-main">🎙️</button>
                    <button type="button" class="rec-btn-sec" id="rec-play" disabled title="Play">▶️</button>
                  </div>
                  <div id="rec-preview"
                    style="display:none;margin-top:12px;background:var(--br-dark2);border-radius:8px;padding:10px">
                    <audio id="rec-audio" controls class="w-100"></audio>
                  </div>
                </div>
              </div>

              <!-- File Attachments -->
              <div class="mb-3">
                <label class="br-form-label">📎 Attachments <span class="text-subtle">(optional)</span></label>
                <div class="br-dropzone" onclick="document.getElementById('file-input').click()">
                  <i class="bi bi-cloud-upload fs-3 text-muted d-block mb-2"></i>
                  <div class="small text-muted">Drop files here or click to upload</div>
                  <div class="text-subtle" style="font-size:.72rem;margin-top:4px">PDF, images, spreadsheets · Max 10MB
                    each</div>
                  <input type="file" name="attachments[]" id="file-input" multiple style="display:none"
                    onchange="handleFiles(this)">
                </div>
                <div id="file-list"></div>
              </div>
            </div>

            <div class="d-flex justify-content-end">
              <button type="button" class="btn br-btn-gold px-4 py-2" id="btn-next">Continue to Review <i
                  class="bi bi-arrow-right ms-1"></i></button>
            </div>
          </div>

          <!-- Step 2: Review & Pay -->
          <div id="step2" style="display:none">
            <div class="br-profile-section mb-4">
              <h5 class="fw-semibold mb-3 pb-2" style="border-bottom:1px solid var(--br-border)">Review Your Submission
              </h5>

              <div class="br-alert br-alert-info d-flex gap-2 align-items-start mb-4">
                <i class="bi bi-shield-lock-fill text-violet mt-1"></i>
                <span class="small">Your payment will be held in escrow and only released when you confirm satisfaction
                  with the expert's response.</span>
              </div>

              <!-- Problem Summary -->
              <div class="p-3 mb-3" style="background:var(--br-dark3);border-radius:10px">
                <div class="text-subtle"
                  style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Your Problem
                </div>
                <div class="fw-medium mb-1" id="review-title">—</div>
                <div class="text-muted small" id="review-excerpt">—</div>
              </div>

              <!-- Expert Summary -->
              <div class="d-flex align-items-center gap-3 p-3 mb-3"
                style="background:var(--br-dark3);border-radius:10px">
                <div class="br-expert-avatar <?= $avColor ?>"
                  style="width:52px;height:52px;font-size:18px;border-radius:12px">
                  <?= strtoupper(substr($expert['full_name'], 0, 2)) ?>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-medium"><?= htmlspecialchars($expert['full_name']) ?></div>
                  <div class="text-muted small">⭐ <?= number_format($expert['average_rating'], 1) ?> · Responds in
                    ~<?= $expert['max_response_hours'] ?>h</div>
                </div>
                <div class="mono text-gold fs-5">$<?= number_format($baseFee, 0) ?></div>
              </div>

              <!-- Price Breakdown -->
              <div class="p-3 mb-4" style="background:var(--br-dark3);border-radius:10px">
                <div class="d-flex justify-content-between small text-muted mb-2"><span>Session fee</span><span
                    class="mono">$<?= number_format($baseFee, 2) ?></span></div>
                <div class="d-flex justify-content-between small text-muted mb-2"><span>Urgency add-on</span><span
                    class="mono sidebar-urgency">$0.00</span></div>
                <div class="d-flex justify-content-between small text-muted mb-2"><span>Platform fee (15%)</span><span
                    class="mono">$<?= number_format($platformFee, 2) ?></span></div>
                <hr class="br-divider my-2">
                <div class="d-flex justify-content-between fw-semibold"><span>Total charged today</span><span
                    class="mono text-gold sidebar-total">$<?= number_format($total, 2) ?></span></div>
              </div>

              <!-- Payment -->
              <h6 class="fw-semibold mb-3">💳 Payment Gateway (Temporary)</h6>
              <div class="p-3 mb-3" style="background:var(--br-dark3);border-radius:10px">
                <div class="row g-3 mb-2">
                  <div class="col-12">
                    <label class="d-flex align-items-center gap-2 p-2"
                      style="border:1px solid var(--br-border);border-radius:8px;cursor:pointer">
                      <input type="radio" name="payment_gateway_choice" value="razorpay" checked
                        onchange="setGateway(this.value)">
                      <span>Razorpay (fake sandbox)</span>
                    </label>
                  </div>
                  <div class="col-12">
                    <label class="d-flex align-items-center gap-2 p-2"
                      style="border:1px solid var(--br-border);border-radius:8px;cursor:pointer">
                      <input type="radio" name="payment_gateway_choice" value="stripe"
                        onchange="setGateway(this.value)">
                      <span>Stripe (fake sandbox)</span>
                    </label>
                  </div>
                </div>
                <small class="text-subtle d-block mt-2"><i class="bi bi-lock-fill me-1"></i>Temporary bypass checkout
                  for testing flow</small>
              </div>
            </div>

            <div class="d-flex justify-content-between">
              <button type="button" class="btn br-btn-ghost px-4" id="btn-back"><i
                  class="bi bi-arrow-left me-1"></i>Back</button>
              <button type="button" class="btn br-btn-gold px-4 py-2" id="btn-pay">
                <i class="bi bi-lock-fill me-2"></i>Payment Done & Submit <span
                  class="sidebar-total">$<?= number_format($total, 2) ?></span>
              </button>
            </div>
          </div>

          <!-- Step 3: Confirmation -->
          <div id="step3" style="display:none">
            <div class="br-profile-section text-center py-5">
              <div style="font-size:3.5rem;margin-bottom:16px">🎉</div>
              <h3 class="fw-bold mb-2" style="font-family:'Playfair Display',serif">Problem Submitted!</h3>
              <p class="text-muted mb-4"><?= htmlspecialchars($expert['full_name']) ?> has been notified. You'll receive
                their voice + written response within <?= $expert['max_response_hours'] ?> hours.</p>
              <div class="br-alert br-alert-success d-flex gap-2 align-items-center justify-content-center mb-4"
                style="max-width:420px;margin:0 auto">
                <i class="bi bi-check-circle-fill text-success"></i>
                <span class="small" id="payment-success-text">Payment successful · redirecting to problem page</span>
              </div>
              <a href="<?= APP_URL ?>/pages/dashboard-client.php" id="problem-view-link"
                class="btn br-btn-gold px-4 py-2">Open Problem Page <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
          </div>

        </div>

        <!-- ===== SIDEBAR ===== -->
        <div class="col-12 col-lg-4">

          <!-- Urgency Selector -->
          <div class="br-profile-section mb-3">
            <h6 class="fw-semibold mb-3">⚡ Urgency Level</h6>
            <div class="d-flex flex-column gap-2">
              <div class="br-urgency-opt selected" onclick="selectUrgency(this)" data-urgency="normal" data-price="0">
                <span>📬</span>
                <div class="flex-grow-1">
                  <div class="small fw-medium">Normal</div>
                  <div class="text-subtle" style="font-size:.72rem">Within 48 hours · No extra charge</div>
                </div>
              </div>
              <div class="br-urgency-opt" onclick="selectUrgency(this)" data-urgency="urgent" data-price="30">
                <span>⚡</span>
                <div class="flex-grow-1">
                  <div class="small fw-medium">Urgent</div>
                  <div class="text-subtle" style="font-size:.72rem">Within 24 hours · +$30</div>
                </div>
              </div>
              <div class="br-urgency-opt" onclick="selectUrgency(this)" data-urgency="critical" data-price="60">
                <span>🔥</span>
                <div class="flex-grow-1">
                  <div class="small fw-medium">Critical</div>
                  <div class="text-subtle" style="font-size:.72rem">Within 8 hours · +$60</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Price Summary -->
          <div class="br-profile-section mb-3">
            <h6 class="fw-semibold mb-3">💰 Price Summary</h6>
            <div class="d-flex flex-column gap-2 small">
              <div class="d-flex justify-content-between text-muted"><span>Session fee</span><span
                  class="mono">$<?= number_format($baseFee, 0) ?></span></div>
              <div class="d-flex justify-content-between text-muted"><span>Urgency</span><span
                  class="mono sidebar-urgency">$0</span></div>
              <div class="d-flex justify-content-between text-muted"><span>Platform fee (15%)</span><span
                  class="mono">$<?= number_format($platformFee, 0) ?></span></div>
              <hr class="br-divider my-1">
              <div class="d-flex justify-content-between fw-semibold"><span>Total</span><span
                  class="mono text-gold sidebar-total">$<?= number_format($total, 0) ?></span></div>
            </div>
          </div>

          <!-- Tips -->
          <div class="br-profile-section">
            <h6 class="fw-semibold mb-3">💡 Tips for Better Responses</h6>
            <div class="d-flex flex-column gap-2 small text-muted">
              <div class="d-flex gap-2"><span class="text-gold">1.</span><span>Include relevant context — links, data,
                  constraints</span></div>
              <div class="d-flex gap-2"><span class="text-gold">2.</span><span>State what "good" looks like for
                  you</span></div>
              <div class="d-flex gap-2"><span class="text-gold">3.</span><span>Voice recordings often get richer
                  responses</span></div>
              <div class="d-flex gap-2"><span class="text-gold">4.</span><span>One focused problem beats multiple
                  tangled questions</span></div>
            </div>
          </div>
        </div>

      </div>
    </form>
  </div>
</main>

<script>
  const APP_URL = '<?= APP_URL ?>';
  const BASE_RATE = <?= $baseFee ?>;
  const PLAT_FEE = <?= $platformFee ?>;

  // Init voice recorder
  window._recorder = new VoiceRecorder({
    dot: 'rec-dot', timer: 'rec-timer', status: 'rec-status',
    wave: 'rec-wave', main: 'rec-main', trash: 'rec-trash', play: 'rec-play',
    audio: 'rec-audio', preview: 'rec-preview'
  });

  // Char counter
  document.getElementById('problem-text').addEventListener('input', function () {
    document.getElementById('char-count').textContent = `${this.value.length} / 3000`;
  });

  // Urgency selection
  function selectUrgency(el) {
    document.querySelectorAll('.br-urgency-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    const price = parseInt(el.dataset.price || 0);
    const total = BASE_RATE + price + Math.round((BASE_RATE + price) * 0.15);
    document.querySelectorAll('.sidebar-urgency').forEach(e => e.textContent = '$' + price);
    document.querySelectorAll('.sidebar-total').forEach(e => e.textContent = '$' + total.toFixed(2));
    document.getElementById('urgency-input').value = el.dataset.urgency;
  }

  function setGateway(gateway) {
    document.getElementById('payment-gateway-input').value = gateway;
  }

  // File uploads
  function handleFiles(input) {
    const list = document.getElementById('file-list');
    Array.from(input.files).forEach(f => {
      const item = document.createElement('div');
      item.className = 'd-flex align-items-center gap-2 mt-2 small';
      item.style.cssText = 'background:var(--br-dark3);border-radius:8px;padding:7px 10px';
      item.innerHTML = `<i class="bi bi-paperclip text-muted"></i><span>${f.name}</span><span class="text-subtle">${(f.size / 1024 / 1024).toFixed(2)} MB</span>`;
      list.appendChild(item);
    });
  }

  // Step navigation (handled by main.js initSubmitWizard)
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>