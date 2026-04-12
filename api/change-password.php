<?php
// api/change-password.php — Change password for any logged-in user
require_once __DIR__ . '/../config/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('Invalid request method.'));
    exit;
}

if (!verifyCsrf($_POST['csrf'] ?? '')) {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('Session expired. Please try again.'));
    exit;
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('Please fill all password fields.'));
    exit;
}

if (strlen($newPassword) < 6) {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('New password must be at least 6 characters.'));
    exit;
}

if ($newPassword !== $confirmPassword) {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('New password and confirm password do not match.'));
    exit;
}

if ($currentPassword === $newPassword) {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('New password must be different from current password.'));
    exit;
}

$db = Database::getInstance();
$userId = currentUserId();
$user = $db->fetchOne(
    "SELECT id, password_hash, is_active FROM users WHERE id = ?",
    [$userId]
);

if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('Account not found or inactive.'));
    exit;
}

if (!password_verify($currentPassword, (string) $user['password_hash'])) {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('Current password is incorrect.'));
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
$updated = $db->execute(
    "UPDATE users SET password_hash = ? WHERE id = ?",
    [$newHash, $userId]
);

if ($updated <= 0) {
    header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=error&pwd_message=' . rawurlencode('No changes made to password.'));
    exit;
}

header('Location: ' . APP_URL . '/pages/profile.php?pwd_status=success&pwd_message=' . rawurlencode('Password updated successfully.'));
exit;
