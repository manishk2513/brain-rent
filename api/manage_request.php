<?php
// =============================================
// api/manage_request.php
// POST /api/manage_request.php
// Handles: accept, decline, submit_response, update_problem,
//          complete, dispute, reraise
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$requestId = (int) ($_POST['request_id'] ?? ($_GET['request_id'] ?? 0));

if (!$requestId || !$action) {
    jsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
}

$db = Database::getInstance();
$userId = currentUserId();
$currentUser = currentUser() ?: [];
$userType = $currentUser['user_type'] ?? '';
$isAdmin = $userType === 'admin';
$isVerifiedExpert = in_array($userType, ['expert', 'both'], true) && isExpertVerified($userId);

// =============================================
// EXPERT: Accept Request
// =============================================
if ($action === 'accept') {
    requireExpert();

    $req = $db->fetchOne(
        "SELECT * FROM thinking_requests WHERE id = ? AND expert_id = ? AND status = 'submitted'",
        [$requestId, $userId]
    );
    if (!$req)
        jsonResponse(['success' => false, 'error' => 'Request not found or already handled'], 404);

    $db->execute(
        "UPDATE thinking_requests
         SET status = 'accepted', accepted_at = NOW(), thinking_started_at = NOW()
         WHERE id = ?",
        [$requestId]
    );

    // Notify client
    $db->execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'request_accepted', 'Request accepted', 'Your request has been accepted and the expert is thinking.', ?)",
        [
            $req['client_id'],
            APP_URL . '/pages/dashboard-client.php?request_id=' . $requestId
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Request accepted']);
}

// =============================================
// EXPERT: Decline Request
// =============================================
if ($action === 'decline') {
    requireExpert();

    $req = $db->fetchOne(
        "SELECT * FROM thinking_requests WHERE id = ? AND expert_id = ? AND status = 'submitted'",
        [$requestId, $userId]
    );
    if (!$req)
        jsonResponse(['success' => false, 'error' => 'Not found'], 404);

    $reason = htmlspecialchars($_POST['reason'] ?? '');
    $db->execute(
        "UPDATE thinking_requests SET status = 'declined' WHERE id = ?",
        [$requestId]
    );

    // Trigger refund
    $db->execute(
        "UPDATE payments SET status = 'refunded', refunded_at = NOW() WHERE request_id = ?",
        [$requestId]
    );

    $db->execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'request_declined', 'Request declined', ?, ?)",
        [
            $req['client_id'],
            'Your request was declined. Reason: ' . ($reason ?: 'No reason provided') . '. A full refund will be processed.',
            APP_URL . '/pages/browse.php'
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Request declined and refund initiated']);
}

// =============================================
// EXPERT: Submit Response
// =============================================
if ($action === 'submit_response') {
    if (!$isAdmin && !$isVerifiedExpert) {
        jsonResponse(['success' => false, 'error' => 'Only admin or verified experts can submit solutions'], 403);
    }

    if ($isAdmin) {
        $req = $db->fetchOne(
            "SELECT * FROM thinking_requests WHERE id = ? AND status IN ('submitted','accepted','thinking','responded')",
            [$requestId]
        );
    } else {
        $req = $db->fetchOne(
            "SELECT * FROM thinking_requests
             WHERE id = ?
               AND (
                    status = 'submitted'
                    OR (expert_id = ? AND status IN ('accepted','thinking'))
               )",
            [$requestId, $userId]
        );
    }

    if (!$req)
        jsonResponse(['success' => false, 'error' => 'Request not open for solution'], 404);

    // Voice response upload
    $voicePath = null;
    $voiceDuration = null;
    if (!empty($_FILES['voice_response']) && $_FILES['voice_response']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['audio/webm', 'audio/wav', 'audio/mpeg', 'audio/ogg'];
        if (!in_array($_FILES['voice_response']['type'], $allowed)) {
            jsonResponse(['success' => false, 'error' => 'Invalid audio format'], 400);
        }

        $dir = __DIR__ . "/../uploads/voice_responses/{$userId}/";
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            jsonResponse(['success' => false, 'error' => 'Could not create response upload directory'], 500);
        }

        $filename = uniqid('resp_', true) . '.webm';
        $voicePath = $dir . $filename;
        if (!move_uploaded_file($_FILES['voice_response']['tmp_name'], $voicePath)) {
            jsonResponse(['success' => false, 'error' => 'Could not save voice response'], 500);
        }

        $escaped = escapeshellarg($voicePath);
        $output = shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$escaped} 2>/dev/null");
        if ($output)
            $voiceDuration = (int) floatval(trim($output));
    }

    // Parse JSON arrays
    $keyInsights = json_encode(array_filter(array_map('trim', explode("\n", $_POST['key_insights'] ?? ''))));
    $actionItems = json_encode(array_filter(array_map('trim', explode("\n", $_POST['action_items'] ?? ''))));
    $resourceLinks = json_encode(array_filter(array_map('trim', explode("\n", $_POST['resource_links'] ?? ''))));

    $existingResponse = $db->fetchOne(
        "SELECT * FROM thinking_responses WHERE request_id = ? LIMIT 1",
        [$requestId]
    );

    $written = htmlspecialchars($_POST['written_response'] ?? '');
    $thinkingMinutes = !empty($_POST['thinking_minutes']) ? (int) $_POST['thinking_minutes'] : null;

    if ($existingResponse) {
        $db->execute(
            "UPDATE thinking_responses
             SET expert_id = ?,
                 written_response = ?,
                 voice_response_path = ?,
                 voice_duration_seconds = ?,
                 key_insights = ?,
                 action_items = ?,
                 resources_links = ?,
                 actual_thinking_minutes = ?,
                 created_at = NOW()
             WHERE id = ?",
            [
                $userId,
                $written,
                $voicePath ?: ($existingResponse['voice_response_path'] ?? null),
                $voiceDuration !== null ? $voiceDuration : ($existingResponse['voice_duration_seconds'] ?? null),
                $keyInsights,
                $actionItems,
                $resourceLinks,
                $thinkingMinutes,
                (int) $existingResponse['id'],
            ]
        );
    } else {
        $db->insertGetId(
            "INSERT INTO thinking_responses
                (request_id, expert_id, written_response, voice_response_path,
                 voice_duration_seconds, key_insights, action_items, resources_links, actual_thinking_minutes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $requestId,
                $userId,
                $written,
                $voicePath,
                $voiceDuration,
                $keyInsights,
                $actionItems,
                $resourceLinks,
                $thinkingMinutes,
            ]
        );
    }

    // Update request status
    if ($isAdmin) {
        $db->execute(
            "UPDATE thinking_requests SET status = 'responded', responded_at = NOW() WHERE id = ?",
            [$requestId]
        );
    } else {
        // Assign request to the expert who solved it.
        $db->execute(
            "UPDATE thinking_requests SET status = 'responded', responded_at = NOW(), expert_id = ? WHERE id = ?",
            [$userId, $requestId]
        );
    }

    // Update expert total sessions
    if (!$isAdmin) {
        $db->execute(
            "UPDATE expert_profiles SET total_sessions = total_sessions + 1 WHERE user_id = ?",
            [$userId]
        );
    }

    // Credit pending balance
    if (!$isAdmin) {
        $payment = $db->fetchOne(
            "SELECT expert_payout FROM payments WHERE request_id = ?",
            [$requestId]
        );
        if ($payment) {
            $db->execute(
                "UPDATE payments SET payee_id = ? WHERE request_id = ?",
                [$userId, $requestId]
            );
            $db->execute(
                "UPDATE expert_wallet SET pending_balance = pending_balance + ? WHERE expert_user_id = ?",
                [$payment['expert_payout'], $userId]
            );
            $db->execute(
                "UPDATE payments SET status = 'held', captured_at = NOW() WHERE request_id = ?",
                [$requestId]
            );
        }
    }

    // Notify client
    $db->execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'response_ready', 'Response ready!', 'Your expert has submitted their response. Please review it.', ?)",
        [
            $req['client_id'],
            APP_URL . '/pages/problem.php?id=' . $requestId
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Solution submitted. Client notified.']);
}

// =============================================
// CLIENT: Update Problem Details
// =============================================
if ($action === 'update_problem') {
    $req = $db->fetchOne(
        "SELECT * FROM thinking_requests
         WHERE id = ? AND client_id = ? AND status IN ('submitted','accepted','thinking')",
        [$requestId, $userId]
    );
    if (!$req) {
        jsonResponse(['success' => false, 'error' => 'Problem cannot be edited in current state'], 400);
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') {
        jsonResponse(['success' => false, 'error' => 'Title is required'], 400);
    }

    $problemText = trim((string) ($_POST['problem_text'] ?? ''));
    $urgency = strtolower(trim((string) ($_POST['urgency'] ?? 'normal')));
    if (!in_array($urgency, ['normal', 'urgent', 'critical'], true)) {
        $urgency = 'normal';
    }
    $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;

    $db->execute(
        "UPDATE thinking_requests
         SET title = ?,
             problem_text = ?,
             category_id = ?,
             urgency = ?
         WHERE id = ?",
        [
            htmlspecialchars($title),
            htmlspecialchars($problemText),
            $categoryId,
            $urgency,
            $requestId,
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Problem updated']);
}

// =============================================
// CLIENT: Complete Request (Release Payment)
// =============================================
if ($action === 'complete') {
    $req = $db->fetchOne(
        "SELECT * FROM thinking_requests WHERE id = ? AND client_id = ? AND status = 'responded'",
        [$requestId, $userId]
    );
    if (!$req)
        jsonResponse(['success' => false, 'error' => 'Request not found or not in responded status'], 404);

    // Use stored procedure when a real payment exists; otherwise do a direct completion.
    $payment = $db->fetchOne("SELECT id FROM payments WHERE request_id = ?", [$requestId]);
    if ($payment) {
        $conn = $db->getConnection();
        $stmt = $conn->prepare("CALL sp_complete_request(?,?)");
        $stmt->execute([$requestId, $userId]);
        $row = $stmt->fetch();
        $result = $row['result'] ?? 'error';
        if ($result !== 'success') {
            jsonResponse(['success' => false, 'error' => $result], 500);
        }
    } else {
        $db->execute(
            "UPDATE thinking_requests
             SET status = 'completed', completed_at = NOW(), payment_status = 'released'
             WHERE id = ?",
            [$requestId]
        );
    }

    // Save review if provided
    $rating = (int) ($_POST['rating'] ?? 0);
    if ($rating >= 1 && $rating <= 5) {
        $db->execute(
            "INSERT INTO reviews
                (request_id, reviewer_id, expert_id, rating, review_text,
                 clarity_rating, depth_rating, usefulness_rating)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $requestId,
                $userId,
                $req['expert_id'],
                $rating,
                htmlspecialchars($_POST['review_text'] ?? ''),
                (int) ($_POST['clarity_rating'] ?? $rating),
                (int) ($_POST['depth_rating'] ?? $rating),
                (int) ($_POST['usefulness_rating'] ?? $rating),
            ]
        );
        // Update expert average rating only when the solver has an expert profile.
        $solver = $db->fetchOne("SELECT user_type FROM users WHERE id = ?", [$req['expert_id']]);
        if ($solver && in_array($solver['user_type'], ['expert', 'both'], true)) {
            $conn = $db->getConnection();
            $stmt = $conn->prepare("CALL sp_update_expert_rating(?)");
            $stmt->execute([$req['expert_id']]);
        }
    }

    // Notify expert
    $db->execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'payment_released', 'Payment released!', 'The client confirmed your response. Payment has been released to your wallet.', ?)",
        [
            $req['expert_id'],
            APP_URL . '/pages/dashboard-expert.php#wallet'
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Session completed. Payment released.']);
}

// =============================================
// CLIENT: Raise Dispute
// =============================================
if ($action === 'dispute') {
    $req = $db->fetchOne(
        "SELECT * FROM thinking_requests WHERE id = ? AND client_id = ? AND status = 'responded'",
        [$requestId, $userId]
    );
    if (!$req)
        jsonResponse(['success' => false, 'error' => 'Not found'], 404);

    $reason = htmlspecialchars($_POST['reason'] ?? '');
    $db->execute(
        "UPDATE thinking_requests SET status = 'disputed' WHERE id = ?",
        [$requestId]
    );

    // Admin notification (in real app — email admin)
    jsonResponse(['success' => true, 'message' => 'Dispute raised. Our team will contact you within 24 hours.']);
}

// =============================================
// CLIENT: Re-raise issue for another solution
// =============================================
if ($action === 'reraise') {
    $req = $db->fetchOne(
        "SELECT * FROM thinking_requests WHERE id = ? AND client_id = ? AND status IN ('responded','completed','disputed')",
        [$requestId, $userId]
    );
    if (!$req) {
        jsonResponse(['success' => false, 'error' => 'Request cannot be re-raised'], 400);
    }

    $db->execute(
        "UPDATE thinking_requests
         SET status = 'submitted',
             accepted_at = NULL,
             thinking_started_at = NULL,
             responded_at = NULL,
             completed_at = NULL
         WHERE id = ?",
        [$requestId]
    );

    // Clear the previous response so experts can submit a fresh solution.
    $db->execute("DELETE FROM thinking_responses WHERE request_id = ?", [$requestId]);

    $db->execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'request_reraised', 'Issue re-raised', 'Your request is open again for expert responses.', ?)",
        [
            $userId,
            APP_URL . '/pages/problem.php?id=' . $requestId,
        ]
    );

    jsonResponse(['success' => true, 'message' => 'Issue re-raised. Experts can respond again.']);
}

jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
