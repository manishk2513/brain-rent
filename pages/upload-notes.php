<?php
// pages/upload-notes.php — Upload Study Notes
$title = 'Upload Notes';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/upload_media_helpers.php';

$db = Database::getInstance();
$user = currentUser();

$error = '';
$success = '';

$formToken = $_SESSION['notes_upload_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $submittedToken = $_POST['form_token'] ?? '';
  if (!$formToken || !$submittedToken || !hash_equals($formToken, $submittedToken)) {
    $error = 'Form expired. Please try again.';
  } else {
    unset($_SESSION['notes_upload_token']);

    $noteTitle = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($noteTitle)) {
      $error = 'Note title is required';
    } elseif (!isset($_FILES['notes']) || $_FILES['notes']['error'] !== UPLOAD_ERR_OK) {
      $error = 'Please upload a notes file';
    } else {
      $file = $_FILES['notes'];
      $allowedExts = ['pdf', 'docx', 'doc', 'txt', 'pptx', 'ppt'];

      $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

      if (!in_array($ext, $allowedExts)) {
        $error = 'Invalid file type. Allowed: PDF, DOC, DOCX, TXT, PPT, PPTX.';
      } elseif ($file['size'] > 25 * 1024 * 1024) { // 25MB limit
        $error = 'File too large. Maximum size is 25MB.';
      } else {
        $uploadRoot = __DIR__ . '/../uploads';
        $notesDir = $uploadRoot . '/notes';
        $thumbDir = $uploadRoot . '/thumbnails';

        if (
          (!is_dir($notesDir) && !mkdir($notesDir, 0755, true) && !is_dir($notesDir)) ||
          (!is_dir($thumbDir) && !mkdir($thumbDir, 0755, true) && !is_dir($thumbDir))
        ) {
          $error = 'Upload directory is not writable. Please contact admin.';
        }

        if ($error === '') {
          // Create unique filename
          $filename = uniqid() . '_' . time() . '.' . $ext;
          $uploadPath = $notesDir . '/' . $filename;

          if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $filePath = APP_URL . '/uploads/notes/' . $filename;
            $fileSize = $file['size'];
            $thumbnail = brAutoCaptureThumbnail($uploadPath, $ext, $thumbDir);

            $db->execute(
              "INSERT INTO notes (title, subject, category, description, file_path, file_size, file_type, thumbnail, uploaded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
              [$noteTitle, $subject, $category, $description, $filePath, $fileSize, $ext, $thumbnail, $user['id']]
            );

            $success = 'Notes uploaded successfully!';
            header('Location: ' . APP_URL . '/pages/notes.php');
            exit;
          } else {
            $error = 'Failed to upload file. Please check folder permissions and try again.';
          }
        }
      }
    }
  }
}

if (empty($_SESSION['notes_upload_token'])) {
  $_SESSION['notes_upload_token'] = bin2hex(random_bytes(16));
}
$formToken = $_SESSION['notes_upload_token'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="py-5">
  <div class="container" style="max-width: 800px;">

    <div class="mb-4">
      <a href="<?= APP_URL ?>/pages/notes.php" class="text-decoration-none text-muted">
        <i class="bi bi-arrow-left me-2"></i>Back to Notes
      </a>
    </div>

    <h1 class="display-6 fw-bold mb-4">📝 Upload Study Notes</h1>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="br-card p-4" id="notes-upload-form">
      <input type="hidden" name="form_token" value="<?= htmlspecialchars($formToken) ?>">

      <div class="mb-4">
        <label class="br-form-label">Note Title *</label>
        <input type="text" name="title" class="br-form-control" required
          value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g., Calculus Chapter 5 Notes">
      </div>

      <div class="row mb-4">
        <div class="col-md-6">
          <label class="br-form-label">Subject *</label>
          <select name="subject" class="br-form-control" required>
            <option value="">Select Subject</option>
            <option value="Mathematics">Mathematics</option>
            <option value="Physics">Physics</option>
            <option value="Chemistry">Chemistry</option>
            <option value="Biology">Biology</option>
            <option value="Computer Science">Computer Science</option>
            <option value="Engineering">Engineering</option>
            <option value="Business">Business</option>
            <option value="Economics">Economics</option>
            <option value="History">History</option>
            <option value="Literature">Literature</option>
            <option value="Psychology">Psychology</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="br-form-label">Category</label>
          <select name="category" class="br-form-control">
            <option value="">Select Category</option>
            <option value="Lecture Notes">Lecture Notes</option>
            <option value="Study Guide">Study Guide</option>
            <option value="Exam Prep">Exam Prep</option>
            <option value="Summary">Summary</option>
            <option value="Practice Problems">Practice Problems</option>
            <option value="Research">Research</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>

      <div class="mb-4">
        <label class="br-form-label">Description</label>
        <textarea name="description" class="br-form-control" rows="4"
          placeholder="Brief description of what these notes cover..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="mb-4">
        <label class="br-form-label">Notes File * (PDF, DOC, DOCX, TXT, PPT, PPTX - Max 25MB)</label>
        <input type="file" name="notes" class="br-form-control" accept=".pdf,.doc,.docx,.txt,.ppt,.pptx" required>
        <small class="text-muted">Thumbnail is auto-generated from the uploaded file.</small>
      </div>

      <button type="submit" class="btn br-btn-gold btn-lg w-100" id="notes-upload-submit">
        <i class="bi bi-upload me-2"></i>Upload Notes
      </button>

    </form>

  </div>
</main>

<script>
  (function () {
    var form = document.getElementById('notes-upload-form');
    var button = document.getElementById('notes-upload-submit');

    if (!form || !button) return;

    form.addEventListener('submit', function () {
      button.disabled = true;
      button.textContent = 'Uploading...';
    });
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>