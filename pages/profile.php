<?php
// pages/profile.php — User Profile
$title = 'My Profile';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$user = currentUser();

$error = '';
$success = '';
$pwdError = '';
$pwdSuccess = '';

if (!empty($_GET['pwd_status'])) {
  $pwdMessage = trim((string) ($_GET['pwd_message'] ?? ''));
  if ($_GET['pwd_status'] === 'success') {
    $pwdSuccess = $pwdMessage !== '' ? $pwdMessage : 'Password updated successfully.';
  } elseif ($_GET['pwd_status'] === 'error') {
    $pwdError = $pwdMessage !== '' ? $pwdMessage : 'Could not update password.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fullName = trim($_POST['full_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $country = trim($_POST['country'] ?? '');
  $bio = trim($_POST['bio'] ?? '');

  if (empty($fullName)) {
    $error = 'Full name is required';
  } else {
    $updated = $db->execute(
      "UPDATE users SET full_name = ?, phone = ?, country = ?, bio = ? WHERE id = ?",
      [$fullName, $phone, $country, $bio, $user['id']]
    );

    if ($updated !== false) {
      $success = 'Profile updated successfully!';
      $user = currentUser(); // Refresh user data
    } else {
      $error = 'Failed to update profile';
    }
  }
}

// Get full user details
$userDetails = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);

// Fetch user's uploaded content for quick access on profile.
$myNotes = $db->fetchAll(
  "SELECT id, title, subject, downloads, views, created_at
   FROM notes
   WHERE uploaded_by = ?
   ORDER BY created_at DESC
   LIMIT 5",
  [$user['id']]
);

$myBooks = $db->fetchAll(
  "SELECT id, title, author, category, downloads, views, created_at
   FROM libraries
   WHERE uploaded_by = ?
   ORDER BY created_at DESC
   LIMIT 5",
  [$user['id']]
);

$myVideos = $db->fetchAll(
  "SELECT id, title, problem_type, difficulty, views, likes, created_at
   FROM problem_solving_videos
   WHERE uploaded_by = ?
   ORDER BY created_at DESC
   LIMIT 5",
  [$user['id']]
);

$noteCount = (int) (($db->fetchOne("SELECT COUNT(*) AS total FROM notes WHERE uploaded_by = ?", [$user['id']])['total'] ?? 0));
$bookCount = (int) (($db->fetchOne("SELECT COUNT(*) AS total FROM libraries WHERE uploaded_by = ?", [$user['id']])['total'] ?? 0));
$videoCount = (int) (($db->fetchOne("SELECT COUNT(*) AS total FROM problem_solving_videos WHERE uploaded_by = ?", [$user['id']])['total'] ?? 0));
?>

<main class="py-5">
  <div class="container" style="max-width: 800px;">

    <h1 class="display-6 fw-bold mb-4">My Profile</h1>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($pwdError): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($pwdError) ?></div>
    <?php endif; ?>
    <?php if ($pwdSuccess): ?>
      <div class="alert alert-success"><?= htmlspecialchars($pwdSuccess) ?></div>
    <?php endif; ?>

    <div class="br-card p-4 mb-4">
      <form method="post">

        <div class="mb-4">
          <label class="br-form-label">Full Name *</label>
          <input type="text" name="full_name" class="br-form-control" required
            value="<?= htmlspecialchars($userDetails['full_name']) ?>">
        </div>

        <div class="mb-4">
          <label class="br-form-label">Email Address</label>
          <input type="email" class="br-form-control" value="<?= htmlspecialchars($userDetails['email']) ?>" disabled>
          <small class="text-muted">Email cannot be changed</small>
        </div>

        <div class="row mb-4">
          <div class="col-md-6">
            <label class="br-form-label">Phone</label>
            <input type="tel" name="phone" class="br-form-control"
              value="<?= htmlspecialchars($userDetails['phone'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="br-form-label">Country</label>
            <input type="text" name="country" class="br-form-control"
              value="<?= htmlspecialchars($userDetails['country'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-4">
          <label class="br-form-label">Bio</label>
          <textarea name="bio" class="br-form-control" rows="4"
            placeholder="Tell us about yourself..."><?= htmlspecialchars($userDetails['bio'] ?? '') ?></textarea>
        </div>

        <div class="mb-4">
          <label class="br-form-label">Account Type</label>
          <input type="text" class="br-form-control" value="<?= ucfirst($userDetails['user_type']) ?>" disabled>
        </div>

        <div class="mb-4">
          <label class="br-form-label">Member Since</label>
          <input type="text" class="br-form-control"
            value="<?= date('F j, Y', strtotime($userDetails['created_at'])) ?>" disabled>
        </div>

        <button type="submit" class="btn br-btn-gold">
          <i class="bi bi-check-circle me-2"></i>Save Changes
        </button>

      </form>
    </div>

    <!-- Change Password Section -->
    <div class="br-card p-4">
      <h5 class="fw-semibold mb-3">Change Password</h5>
      <form method="post" action="<?= APP_URL ?>/api/change-password.php">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <div class="mb-3">
          <label class="br-form-label">Current Password</label>
          <input type="password" name="current_password" class="br-form-control" required
            autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="br-form-label">New Password</label>
          <input type="password" name="new_password" class="br-form-control" required minlength="6"
            autocomplete="new-password">
        </div>
        <div class="mb-3">
          <label class="br-form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="br-form-control" required minlength="6"
            autocomplete="new-password">
        </div>
        <button type="submit" class="btn br-btn-ghost">
          <i class="bi bi-key me-2"></i>Update Password
        </button>
      </form>
    </div>

    <!-- My Uploads Section -->
    <div class="br-card p-4 mt-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="fw-semibold mb-0">My Uploads</h5>
        <span class="text-muted small">Latest 5 items from each category</span>
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Notes</h6>
            <span class="br-badge br-badge-violet"><?= $noteCount ?></span>
          </div>

          <?php if (empty($myNotes)): ?>
            <div class="text-muted small mb-2">No notes uploaded yet.</div>
            <a href="<?= APP_URL ?>/pages/upload-notes.php" class="btn br-btn-ghost btn-sm">Upload Notes</a>
          <?php else: ?>
            <?php foreach ($myNotes as $note): ?>
              <div class="mb-3 pb-3" style="border-bottom: 1px solid var(--br-border);">
                <div class="fw-medium small mb-1"><?= htmlspecialchars($note['title']) ?></div>
                <div class="text-subtle" style="font-size: .75rem;">
                  <?= htmlspecialchars($note['subject'] ?: 'General') ?>
                  • <?= date('M j, Y', strtotime($note['created_at'])) ?>
                </div>
                <div class="text-subtle mb-2" style="font-size: .75rem;">
                  <?= number_format((int) $note['downloads']) ?> downloads • <?= number_format((int) $note['views']) ?>
                  views
                </div>
                <div class="d-flex gap-2">
                  <a href="<?= APP_URL ?>/api/view-note.php?id=<?= (int) $note['id'] ?>" target="_blank"
                    class="btn br-btn-ghost btn-sm">View</a>
                  <a href="<?= APP_URL ?>/api/download-note.php?id=<?= (int) $note['id'] ?>"
                    class="btn br-btn-gold btn-sm">Download</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="col-12 col-lg-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">E-Books</h6>
            <span class="br-badge br-badge-gold"><?= $bookCount ?></span>
          </div>

          <?php if (empty($myBooks)): ?>
            <div class="text-muted small mb-2">No e-books uploaded yet.</div>
            <a href="<?= APP_URL ?>/pages/upload-ebook.php" class="btn br-btn-ghost btn-sm">Upload E-Book</a>
          <?php else: ?>
            <?php foreach ($myBooks as $book): ?>
              <div class="mb-3 pb-3" style="border-bottom: 1px solid var(--br-border);">
                <div class="fw-medium small mb-1"><?= htmlspecialchars($book['title']) ?></div>
                <div class="text-subtle" style="font-size: .75rem;">
                  <?= htmlspecialchars($book['author'] ?: 'Unknown author') ?>
                  • <?= date('M j, Y', strtotime($book['created_at'])) ?>
                </div>
                <div class="text-subtle mb-2" style="font-size: .75rem;">
                  <?= htmlspecialchars($book['category'] ?: 'General') ?>
                  • <?= number_format((int) $book['downloads']) ?> downloads • <?= number_format((int) $book['views']) ?>
                  views
                </div>
                <div class="d-flex gap-2">
                  <a href="<?= APP_URL ?>/pages/ebook-detail.php?id=<?= (int) $book['id'] ?>"
                    class="btn br-btn-ghost btn-sm">Open</a>
                  <a href="<?= APP_URL ?>/api/download-ebook.php?id=<?= (int) $book['id'] ?>"
                    class="btn br-btn-gold btn-sm">Download</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="col-12 col-lg-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Videos</h6>
            <span class="br-badge br-badge-teal"><?= $videoCount ?></span>
          </div>

          <?php if (empty($myVideos)): ?>
            <div class="text-muted small mb-2">No videos uploaded yet.</div>
            <a href="<?= APP_URL ?>/pages/upload-video.php" class="btn br-btn-ghost btn-sm">Upload Video</a>
          <?php else: ?>
            <?php foreach ($myVideos as $video): ?>
              <div class="mb-3 pb-3" style="border-bottom: 1px solid var(--br-border);">
                <div class="fw-medium small mb-1"><?= htmlspecialchars($video['title']) ?></div>
                <div class="text-subtle" style="font-size: .75rem;">
                  <?= htmlspecialchars($video['problem_type'] ?: 'General') ?>
                  • <?= htmlspecialchars(ucfirst((string) ($video['difficulty'] ?: 'beginner'))) ?>
                </div>
                <div class="text-subtle mb-2" style="font-size: .75rem;">
                  <?= date('M j, Y', strtotime($video['created_at'])) ?>
                  • <?= number_format((int) $video['views']) ?> views • <?= number_format((int) $video['likes']) ?> likes
                </div>
                <div class="d-flex gap-2">
                  <a href="<?= APP_URL ?>/pages/video-detail.php?id=<?= (int) $video['id'] ?>"
                    class="btn br-btn-ghost btn-sm">Open</a>
                  <a href="<?= APP_URL ?>/api/download-video.php?id=<?= (int) $video['id'] ?>"
                    class="btn br-btn-gold btn-sm">Download</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>