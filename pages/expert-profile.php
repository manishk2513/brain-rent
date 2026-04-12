<?php
// pages/expert-profile.php — Expert Public Profile
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$expertUserId = (int) ($_GET['id'] ?? 0);
if (!$expertUserId) {
  header('Location: ' . APP_URL . '/pages/browse.php');
  exit;
}

$db = Database::getInstance();

$expert = $db->fetchOne(
  "SELECT ep.*, ep.current_role_name AS `current_role`, u.full_name, u.profile_photo, u.country
     FROM expert_profiles ep INNER JOIN users u ON ep.user_id = u.id
     WHERE ep.user_id = ? AND u.is_active = 1",
  [$expertUserId]
);
if (!$expert) {
  header('Location: ' . APP_URL . '/pages/browse.php');
  exit;
}

$reviews = $db->fetchAll(
  "SELECT r.*, u.full_name AS reviewer_name, u.profile_photo AS reviewer_photo
     FROM reviews r INNER JOIN users u ON r.reviewer_id = u.id
     WHERE r.expert_id = ? AND r.is_public = 1
     ORDER BY r.created_at DESC
     LIMIT 5",
  [$expertUserId]
);

$tags = json_decode($expert['expertise_areas'] ?? '[]', true) ?: [];
$avColors = ['av-1', 'av-2', 'av-3', 'av-4', 'av-5', 'av-6'];
$avColor = $avColors[$expertUserId % count($avColors)];

$title = $expert['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>
<main class="py-4" style="padding-top:80px!important">
  <div class="container">
    <div class="mb-3">
      <a href="<?= APP_URL ?>/pages/browse.php" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i>Browse Experts
      </a>
    </div>

    <div class="row g-4">
      <!-- ===== LEFT COLUMN ===== -->
      <div class="col-12 col-lg-8">

        <!-- Profile Header -->
        <div class="br-profile-header d-flex gap-4 flex-wrap mb-4">
          <div class="br-avatar-lg <?= $avColor ?>" style="flex-shrink:0">
            <?php if ($expert['profile_photo']): ?>
              <img src="<?= htmlspecialchars($expert['profile_photo']) ?>"
                style="width:100%;height:100%;object-fit:cover;border-radius:20px">
            <?php else: ?>
              <?= strtoupper(substr($expert['full_name'], 0, 2)) ?>
            <?php endif; ?>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <h1 class="fs-3 fw-bold mb-0" style="font-family:'Playfair Display',serif">
                <?= htmlspecialchars($expert['full_name']) ?>
              </h1>
              <?php if ($expert['is_verified']): ?>
                <span class="br-badge br-badge-teal"><i class="bi bi-check-circle-fill me-1"></i>Verified</span>
              <?php endif; ?>
              <?php if ($expert['is_available']): ?>
                <span class="br-badge br-badge-success">🟢 Available</span>
              <?php endif; ?>
            </div>
            <p class="text-muted mb-3"><?= htmlspecialchars($expert['headline'] ?? '') ?></p>
            <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
              <span>⭐ <strong class="text-white"><?= number_format($expert['average_rating'], 1) ?></strong>
                (<?= $expert['total_reviews'] ?> reviews)</span>
              <span>📋 <strong class="text-white"><?= number_format($expert['total_sessions']) ?></strong>
                sessions</span>
              <span>⏱️ Responds in <strong class="text-warning">~<?= $expert['max_response_hours'] ?>
                  hours</strong></span>
              <?php if ($expert['country']): ?><span>🌍
                  <?= htmlspecialchars($expert['country']) ?></span><?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($tags as $t): ?>
                <span class="badge"
                  style="background:var(--br-dark3);color:var(--br-text2);border:1px solid var(--br-border);font-weight:400"><?= htmlspecialchars($t) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- About -->
        <div class="br-profile-section mb-4">
          <h5 class="fw-semibold mb-3 pb-2" style="border-bottom:1px solid var(--br-border)">About</h5>
          <p class="text-muted small lh-lg">
            <?= nl2br(htmlspecialchars($expert['bio'] ?? 'This expert has not added a bio yet.')) ?>
          </p>
          <?php if ($expert['current_role'] || $expert['company'] || $expert['linkedin_url']): ?>
            <hr class="br-divider">
            <div class="row g-3 small">
              <?php if ($expert['current_role'] && $expert['company']): ?>
                <div class="col-6">
                  <div class="text-subtle mb-1">Current Role</div>
                  <div><?= htmlspecialchars($expert['current_role'] . ' @ ' . $expert['company']) ?></div>
                </div>
              <?php endif; ?>
              <?php if ($expert['experience_years']): ?>
                <div class="col-6">
                  <div class="text-subtle mb-1">Experience</div>
                  <div><?= $expert['experience_years'] ?> years</div>
                </div>
              <?php endif; ?>
              <?php if ($expert['linkedin_url']): ?>
                <div class="col-12"><a href="<?= htmlspecialchars($expert['linkedin_url']) ?>" target="_blank"
                    class="text-violet text-decoration-none small"><i class="bi bi-linkedin me-1"></i>LinkedIn Profile</a>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="br-profile-section mb-4">
          <h5 class="fw-semibold mb-3 pb-2" style="border-bottom:1px solid var(--br-border)">Performance Stats</h5>
          <div class="row g-3">
            <?php
            $stats = [
              ['Response Rate', '98%', 98],
              ['On-time Delivery', '96%', 96],
              ['Client Satisfaction', '99%', 99],
            ];
            foreach ($stats as $s): ?>
              <div class="col-12">
                <div class="d-flex justify-content-between small mb-1">
                  <span class="text-muted"><?= $s[0] ?></span>
                  <span><?= $s[1] ?></span>
                </div>
                <div class="br-progress">
                  <div class="br-progress-bar" style="width:<?= $s[2] ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Reviews -->
        <div class="br-profile-section">
          <h5 class="fw-semibold mb-3 pb-2" style="border-bottom:1px solid var(--br-border)">
            Client Reviews <span class="text-subtle fw-normal" style="font-size:.85rem">(<?= $expert['total_reviews'] ?>
              total)</span>
          </h5>
          <?php if ($reviews):
            foreach ($reviews as $r): ?>
              <div class="py-3" style="border-bottom:1px solid var(--br-border)">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div>
                    <div class="fw-medium small"><?= htmlspecialchars($r['reviewer_name']) ?></div>
                    <div><?php for ($s = 1; $s <= 5; $s++)
                      echo $s <= $r['rating'] ? '⭐' : '☆'; ?></div>
                  </div>
                  <div class="text-subtle small"><?= date('M j, Y', strtotime($r['created_at'])) ?></div>
                </div>
                <p class="text-muted small mb-0 lh-lg">"<?= htmlspecialchars($r['review_text'] ?? '') ?>"</p>
              </div>
            <?php endforeach;
          else: ?>
            <p class="text-muted small">No reviews yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- ===== BOOKING SIDEBAR ===== -->
      <div class="col-12 col-lg-4">
        <div class="br-booking-card">
          <div class="br-booking-price">$<?= number_format($expert['rate_per_session'], 0) ?> <span class="text-muted"
              style="font-size:1rem;font-family:'DM Sans',sans-serif;font-weight:400">/ session</span></div>
          <p class="text-subtle small mt-1">~<?= $expert['session_duration_minutes'] ?> minutes of focused thinking</p>
          <hr class="br-divider">
          <div class="d-flex flex-column gap-2 mb-4 small text-muted">
            <div><i class="bi bi-check-lg text-success me-2"></i>Written analysis + key insights</div>
            <div><i class="bi bi-check-lg text-success me-2"></i>Recorded voice response</div>
            <div><i class="bi bi-check-lg text-success me-2"></i>Action items & resource links</div>
            <div><i class="bi bi-check-lg text-success me-2"></i>Response within <?= $expert['max_response_hours'] ?>
              hours</div>
            <div><i class="bi bi-check-lg text-success me-2"></i>Escrow-protected payment</div>
          </div>
          <a href="<?= APP_URL ?>/pages/submit-problem.php?expert_id=<?= $expertUserId ?>"
            class="btn br-btn-gold w-100 py-3 mb-2 fw-semibold">
            Submit Your Problem <i class="bi bi-arrow-right ms-1"></i>
          </a>
          <button class="btn br-btn-ghost w-100 py-2">
            <i class="bi bi-chat-dots me-2"></i>Ask a Quick Question
          </button>
          <div class="text-center text-subtle mt-3" style="font-size:.72rem">
            <i class="bi bi-lock-fill me-1"></i>15% platform fee included · Secured by escrow
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>