<?php
// pages/upload-video.php — Upload Problem Solving Video
$title = 'Upload Video';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/upload_media_helpers.php';

$db = Database::getInstance();
$user = currentUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $videoTitle = trim($_POST['title'] ?? '');
  $problemType = trim($_POST['problem_type'] ?? '');
  $difficulty = $_POST['difficulty'] ?? 'beginner';
  $description = trim($_POST['description'] ?? '');

  if (empty($videoTitle)) {
    $error = 'Video title is required';
  } elseif (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    $error = 'Please upload a video file';
  } else {
    $file = $_FILES['video'];
    $allowedExts = ['mp4', 'webm', 'avi', 'mov', 'mkv'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) {
      $error = 'Invalid file type. Allowed: MP4, WEBM, AVI, MOV, MKV.';
    } elseif ($file['size'] > 500 * 1024 * 1024) { // 500MB limit
      $error = 'File too large. Maximum size is 500MB.';
    } else {
      $uploadRoot = __DIR__ . '/../uploads';
      $videosDir = $uploadRoot . '/videos';
      $thumbDir = $uploadRoot . '/thumbnails';

      if (
        (!is_dir($videosDir) && !mkdir($videosDir, 0755, true) && !is_dir($videosDir)) ||
        (!is_dir($thumbDir) && !mkdir($thumbDir, 0755, true) && !is_dir($thumbDir))
      ) {
        $error = 'Upload directory is not writable. Please contact admin.';
      }

      if ($error === '') {
        // Create unique filename
        $filename = uniqid() . '_' . time() . '.' . $ext;
        $uploadPath = $videosDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
          $thumbnail = brAutoCaptureThumbnail($uploadPath, $ext, $thumbDir);

          // Optional manual thumbnail override
          if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbFile = $_FILES['thumbnail'];
            $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
            if (in_array($thumbExt, ['jpg', 'jpeg', 'png', 'webp'])) {
              $thumbFilename = uniqid() . '_thumb.' . $thumbExt;
              $thumbPath = $thumbDir . '/' . $thumbFilename;
              if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                $thumbnail = APP_URL . '/uploads/thumbnails/' . $thumbFilename;
              }
            }
          }

          $filePath = APP_URL . '/uploads/videos/' . $filename;
          $fileSize = $file['size'];

          $db->execute(
            "INSERT INTO problem_solving_videos (title, problem_type, difficulty, description, video_path, video_size, thumbnail, uploaded_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$videoTitle, $problemType, $difficulty, $description, $filePath, $fileSize, $thumbnail, $user['id']]
          );

          $success = 'Video uploaded successfully!';
          header('Location: ' . APP_URL . '/pages/problem-solving.php');
          exit;
        } else {
          $error = 'Failed to upload file. Please check folder permissions and try again.';
        }
      }
    }
  }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="py-5">
  <div class="container" style="max-width: 800px;">

    <div class="mb-4">
      <a href="<?= APP_URL ?>/pages/problem-solving.php" class="text-decoration-none text-muted">
        <i class="bi bi-arrow-left me-2"></i>Back to Videos
      </a>
    </div>

    <h1 class="display-6 fw-bold mb-4">🎥 Upload Problem Solving Video</h1>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="br-card p-4">

      <div class="mb-4">
        <label class="br-form-label">Video Title *</label>
        <input type="text" name="title" class="br-form-control" required
          value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
          placeholder="e.g., Solving Quadratic Equations - Step by Step">
      </div>

      <div class="row mb-4">
        <div class="col-md-6">
          <label class="br-form-label">Problem Type *</label>
          <select name="problem_type" class="br-form-control" required>
            <option value="">Select Type</option>
            <option value="Mathematics">Mathematics</option>
            <option value="Physics">Physics</option>
            <option value="Chemistry">Chemistry</option>
            <option value="Coding">Coding</option>
            <option value="Logic Puzzles">Logic Puzzles</option>
            <option value="Data Structures">Data Structures</option>
            <option value="Algorithms">Algorithms</option>
            <option value="Engineering">Engineering</option>
            <option value="Statistics">Statistics</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="br-form-label">Difficulty Level *</label>
          <select name="difficulty" class="br-form-control" required>
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
      </div>

      <div class="mb-4">
        <label class="br-form-label">Description</label>
        <textarea name="description" class="br-form-control" rows="4"
          placeholder="Describe what problem this video solves and the approach used..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="mb-4">
        <label class="br-form-label">Video File * (MP4, WEBM, AVI, MOV, MKV - Max 500MB)</label>
        <input type="file" name="video" class="br-form-control" accept="video/*" required>
        <small class="text-muted">Upload a clear video showing the problem-solving process</small>
      </div>

      <div class="mb-4">
        <label class="br-form-label">Thumbnail Image (Optional)</label>
        <input type="file" name="thumbnail" class="br-form-control" accept="image/*">
        <small class="text-muted">Auto-generated from video. Upload a custom image only if you want to override
          it.</small>
      </div>

      <div class="alert br-alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Tips for great videos:</strong>
        <ul class="mb-0 mt-2 small">
          <li>Keep videos concise and focused (5-15 minutes ideal)</li>
          <li>Explain each step clearly and show your working</li>
          <li>Use good audio quality - viewers need to hear your explanations</li>
          <li>Consider showing your face or using annotations</li>
        </ul>
      </div>

      <button type="submit" class="btn br-btn-gold btn-lg w-100">
        <i class="bi bi-upload me-2"></i>Upload Video
      </button>

    </form>

  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>