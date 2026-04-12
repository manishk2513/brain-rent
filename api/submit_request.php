<?php
// =============================================
// api/submit_request.php
// POST /api/submit_request.php
// Client submits a problem + creates payment order
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();

class ThinkingRequestSubmitter
{
    private Database $db;
    private int $clientId;

    public function __construct(int $clientId)
    {
        $this->db = Database::getInstance();
        $this->clientId = $clientId;
        ensureTemporaryPaymentsTable($this->db);
    }

    /**
     * Full submission flow:
     * 1. Validate expert availability
     * 2. Upload voice recording (if provided)
     * 3. Create thinking_requests row
     * 4. Upload attachments
     * 5. Save temporary fake payment
     * 6. Notify expert
     */
    public function submit(array $data): array
    {
        // ---- 1. Load expert ----
        $expert = $this->db->fetchOne(
            "SELECT ep.*, u.email AS expert_email, u.full_name AS expert_name
             FROM expert_profiles ep
             INNER JOIN users u ON ep.user_id = u.id
             WHERE ep.user_id = ? AND ep.is_available = 1 AND u.is_active = 1",
            [(int) $data['expert_id']]
        );

        if (!$expert) {
            return ['success' => false, 'error' => 'Expert not available'];
        }

        // ---- 2. Check expert active request cap ----
        $active = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM thinking_requests
             WHERE expert_id = ? AND status IN ('submitted','accepted','thinking')",
            [(int) $data['expert_id']]
        );

        if ((int) ($active['cnt'] ?? 0) >= $expert['max_active_requests']) {
            return ['success' => false, 'error' => 'Expert has reached their request limit. Try again later.'];
        }

        // ---- 3. Upload voice recording ----
        $voicePath = null;
        $voiceDuration = null;
        if (!empty($_FILES['voice_recording']) && $_FILES['voice_recording']['error'] === UPLOAD_ERR_OK) {
            $upload = $this->uploadVoice($_FILES['voice_recording'], 'problems');
            if (!$upload['success'])
                return $upload;
            $voicePath = $upload['path'];
            $voiceDuration = $upload['duration'];
        }

        // ---- 4. Urgency add-on ----
        $urgencyFees = ['normal' => 0, 'urgent' => 30, 'critical' => 60];
        $urgency = in_array($data['urgency'] ?? 'normal', array_keys($urgencyFees))
            ? $data['urgency'] : 'normal';
        $agreedRate = $expert['rate_per_session'] + $urgencyFees[$urgency];

        // ---- 5. Deadline ----
        $hours = $urgency === 'critical' ? 8 : ($urgency === 'urgent' ? 24 : $expert['max_response_hours']);
        $deadline = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));

        // ---- 6. Insert request ----
        $requestId = $this->db->insertGetId(
            "INSERT INTO thinking_requests
                (client_id, expert_id, title, problem_text,
                 problem_voice_path, problem_voice_duration,
                 category_id, urgency, agreed_rate, currency,
                 response_deadline, status, payment_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', 'held')",
            [
                $this->clientId,
                (int) $data['expert_id'],
                htmlspecialchars(trim($data['title'])),
                htmlspecialchars(trim($data['problem_text'] ?? '')),
                $voicePath,
                $voiceDuration,
                !empty($data['category_id']) ? (int) $data['category_id'] : null,
                $urgency,
                $agreedRate,
                $expert['currency'],
                $deadline,
            ]
        );

        if (!$requestId) {
            return ['success' => false, 'error' => 'Could not create request'];
        }

        // ---- 7. Attachments ----
        if (!empty($_FILES['attachments']['name'][0])) {
            $this->handleAttachments($requestId, $_FILES['attachments']);
        }

        // ---- 8. Temporary fake payment ----
        $gateway = strtolower(trim((string) ($data['payment_gateway'] ?? 'razorpay')));
        if (!in_array($gateway, ['stripe', 'razorpay'], true)) {
            $gateway = 'razorpay';
        }
        $payment = $this->createTemporaryPayment($requestId, (int) $data['expert_id'], $expert, $agreedRate, $gateway);

        // ---- 9. Notify expert ----
        $this->notifyExpert($expert, $requestId, $data['title']);

        return [
            'success' => true,
            'request_id' => $requestId,
            'problem_url' => APP_URL . '/pages/problem.php?id=' . $requestId,
            'payment' => $payment['data'],
        ];
    }

    // ---- Temporary payment (bypass gateway for now) ----
    private function createTemporaryPayment(int $requestId, int $preferredExpertId, array $expert, float $agreedRate, string $gateway): array
    {
        $txRef = 'temp_' . $gateway . '_' . $requestId . '_' . time();

        $this->db->execute(
            "INSERT INTO temporary_payments
                (request_id, client_id, preferred_expert_id, gateway, amount, currency, status, transaction_ref, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, NOW())",
            [
                $requestId,
                $this->clientId,
                $preferredExpertId,
                $gateway,
                $agreedRate,
                $expert['currency'],
                $txRef,
            ]
        );

        return [
            'success' => true,
            'data' => [
                'gateway' => $gateway,
                'transaction_ref' => $txRef,
                'amount' => (float) $agreedRate,
                'currency' => $expert['currency'] ?? 'USD',
                'mode' => 'temporary',
            ]
        ];
    }

    // ---- Voice Upload ----
    private function uploadVoice(array $file, string $folder): array
    {
        $allowed = ['audio/webm', 'audio/wav', 'audio/mpeg', 'audio/ogg', 'audio/mp4'];
        if (!in_array($file['type'], $allowed)) {
            return ['success' => false, 'error' => 'Invalid audio format'];
        }
        if ($file['size'] > 50 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Voice file too large (max 50MB)'];
        }

        $dir = __DIR__ . "/../uploads/voice_{$folder}/{$this->clientId}/";
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'error' => 'Could not create voice upload directory'];
        }

        $filename = uniqid('voice_', true) . '.webm';
        $path = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return ['success' => false, 'error' => 'Could not save voice recording'];
        }

        // Try to get duration via ffprobe
        $duration = null;
        $escaped = escapeshellarg($path);
        $output = shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$escaped} 2>/dev/null");
        if ($output)
            $duration = (int) floatval(trim($output));

        return ['success' => true, 'path' => $path, 'duration' => $duration];
    }

    // ---- Attachments ----
    private function handleAttachments(int $requestId, array $files): void
    {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK)
                continue;

            $dir = __DIR__ . "/../uploads/attachments/{$requestId}/";
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                continue;
            }

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $filename = uniqid('att_') . '.' . $ext;
            $path = $dir . $filename;
            if (!move_uploaded_file($files['tmp_name'][$i], $path)) {
                continue;
            }

            $this->db->execute(
                "INSERT INTO thinking_request_attachments
                    (request_id, uploaded_by, file_path, file_name, file_size_mb, file_type)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $requestId,
                    $this->clientId,
                    $path,
                    htmlspecialchars($files['name'][$i]),
                    round($files['size'][$i] / (1024 * 1024), 2),
                    $files['type'][$i],
                ]
            );
        }
    }

    // ---- Notification ----
    private function notifyExpert(array $expert, int $requestId, string $title): void
    {
        $db = $this->db;

        // In-app notification
        $db->execute(
            "INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, 'new_request', ?, ?, ?)",
            [
                $expert['user_id'],
                'New thinking request',
                "You have a new request: {$title}",
                APP_URL . '/pages/dashboard-expert.php?request_id=' . $requestId,
            ]
        );

        // Email (requires PHPMailer: composer require phpmailer/phpmailer)
        // sendMail($expert['expert_email'], 'New request: '.$title, '...');
    }
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'POST required'], 405);
}

$submitter = new ThinkingRequestSubmitter(currentUserId());
$result = $submitter->submit($_POST);

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result);
