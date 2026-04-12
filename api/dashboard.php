<?php
// =============================================
// api/dashboard.php
// GET /api/dashboard.php?type=client|expert
// Returns dashboard metrics + recent data
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();

$db = Database::getInstance();
$userId = currentUserId();
$type = $_GET['type'] ?? 'client';

// =============================================
// CLIENT DASHBOARD
// =============================================
if ($type === 'client') {
    $metrics = $db->fetchOne(
        "SELECT
            COUNT(*)                                           AS total_requests,
            SUM(CASE WHEN status IN ('submitted','accepted','thinking') THEN 1 ELSE 0 END) AS active_requests,
            SUM(CASE WHEN status = 'responded'  THEN 1 ELSE 0 END) AS awaiting_review,
            SUM(CASE WHEN status = 'completed'  THEN 1 ELSE 0 END) AS completed,
            IFNULL(SUM(CASE WHEN payment_status = 'released' THEN agreed_rate END), 0) AS total_spent
         FROM thinking_requests
         WHERE client_id = ?",
        [$userId]
    );

    $requests = $db->fetchAll(
        "SELECT
            tr.id, tr.title, tr.status, tr.urgency, tr.agreed_rate,
            tr.created_at, tr.responded_at, tr.response_deadline,
            u.full_name AS expert_name, u.profile_photo AS expert_photo,
            ec.name AS category_name
         FROM thinking_requests tr
         INNER JOIN users u ON tr.expert_id = u.id
         LEFT  JOIN expertise_categories ec ON tr.category_id = ec.id
         WHERE tr.client_id = ?
         ORDER BY tr.created_at DESC
         LIMIT 20",
        [$userId]
    );

    $notifications = $db->fetchAll(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC
         LIMIT 10",
        [$userId]
    );

    jsonResponse([
        'success' => true,
        'metrics' => $metrics,
        'requests' => $requests,
        'notifications' => $notifications,
    ]);
}

// =============================================
// EXPERT DASHBOARD
// =============================================
if ($type === 'expert') {
    requireExpert();

    $profile = $db->fetchOne(
        "SELECT ep.*, u.full_name, u.email, u.profile_photo
         FROM expert_profiles ep INNER JOIN users u ON ep.user_id = u.id
         WHERE ep.user_id = ?",
        [$userId]
    );

    $wallet = $db->fetchOne(
        "SELECT * FROM expert_wallet WHERE expert_user_id = ?",
        [$userId]
    );

    $thisMonth = $db->fetchOne(
        "SELECT
            COUNT(*) AS sessions_count,
            IFNULL(SUM(p.expert_payout),0) AS earnings
         FROM payments p
         INNER JOIN thinking_requests tr ON p.request_id = tr.id
         WHERE p.payee_id = ?
           AND p.status   = 'released'
           AND MONTH(p.released_at) = MONTH(NOW())
           AND YEAR(p.released_at)  = YEAR(NOW())",
        [$userId]
    );

    $newRequests = $db->fetchAll(
        "SELECT
            tr.id, tr.title, tr.problem_text, tr.urgency,
            tr.agreed_rate, tr.response_deadline, tr.created_at,
            u.full_name AS client_name
         FROM thinking_requests tr
         INNER JOIN users u ON tr.client_id = u.id
         WHERE tr.status = 'submitted'
         ORDER BY tr.urgency DESC, tr.created_at ASC",
        []
    );

    $activeRequests = $db->fetchAll(
        "SELECT tr.id, tr.title, tr.status, tr.urgency,
                tr.response_deadline, u.full_name AS client_name
         FROM thinking_requests tr
         INNER JOIN users u ON tr.client_id = u.id
         WHERE tr.status = 'submitted'
            OR (tr.expert_id = ? AND tr.status IN ('accepted','thinking'))
         ORDER BY tr.response_deadline ASC",
        [$userId]
    );

    $recentEarnings = $db->fetchAll(
        "SELECT p.*, tr.title AS request_title, u.full_name AS client_name,
                p.released_at, p.expert_payout
         FROM payments p
         INNER JOIN thinking_requests tr ON p.request_id = tr.id
         INNER JOIN users u ON tr.client_id = u.id
         WHERE p.payee_id = ?
         ORDER BY p.created_at DESC
         LIMIT 15",
        [$userId]
    );

    jsonResponse([
        'success' => true,
        'profile' => $profile,
        'wallet' => $wallet,
        'this_month' => $thisMonth,
        'new_requests' => $newRequests,
        'active_requests' => $activeRequests,
        'recent_earnings' => $recentEarnings,
    ]);
}

jsonResponse(['success' => false, 'error' => 'Invalid type'], 400);
