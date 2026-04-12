<?php
// =============================================
// api/get_request.php
// GET /api/get_request.php?request_id=123
// Returns request + response for client view
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();

$requestId = (int) ($_GET['request_id'] ?? 0);
if (!$requestId) {
    jsonResponse(['success' => false, 'error' => 'Missing request_id'], 400);
}

$db = Database::getInstance();
$userId = currentUserId();
$viewer = currentUser() ?: [];
$viewerType = $viewer['user_type'] ?? 'guest';
$isAdmin = $viewerType === 'admin';
$isExpert = in_array($viewerType, ['expert', 'both'], true) && isExpertVerified($userId);

$requestParams = [$requestId];
$requestWhere = "tr.id = ?";

if ($isAdmin) {
    // Admin can inspect every problem.
    $requestWhere .= "";
} elseif ($isExpert) {
    // Verified experts can inspect open/shared requests and their own assigned requests.
    $requestWhere .= " AND (tr.status = 'submitted' OR tr.expert_id = ? OR tr.client_id = ?)";
    $requestParams[] = $userId;
    $requestParams[] = $userId;
} else {
    // Clients can only inspect their own problems.
    $requestWhere .= " AND tr.client_id = ?";
    $requestParams[] = $userId;
}

$request = $db->fetchOne(
    "SELECT tr.*, u.full_name AS expert_name, u.profile_photo AS expert_photo,
            ec.name AS category_name
     FROM thinking_requests tr
     INNER JOIN users u ON tr.expert_id = u.id
     LEFT JOIN expertise_categories ec ON tr.category_id = ec.id
     WHERE {$requestWhere}",
    $requestParams
);

if (!$request) {
    jsonResponse(['success' => false, 'error' => 'Request not found'], 404);
}

$response = $db->fetchOne(
    "SELECT r.*, su.full_name AS responder_name, su.user_type AS responder_type
     FROM thinking_responses r
     INNER JOIN users su ON su.id = r.expert_id
     WHERE r.request_id = ?
     ORDER BY r.id DESC
     LIMIT 1",
    [$requestId]
);

function filePathToUrl(?string $path): ?string
{
    if (!$path) {
        return null;
    }

    if (strpos($path, '://') !== false) {
        return $path;
    }

    $path = str_replace('\\', '/', $path);
    $uploadsPos = strpos($path, '/uploads/');
    if ($uploadsPos === false) {
        return null;
    }

    $rel = substr($path, $uploadsPos);
    return APP_URL . $rel;
}

$request['problem_voice_url'] = filePathToUrl($request['problem_voice_path'] ?? null);
if ($response) {
    // Rule: admin cannot view expert-provided solutions unless admin has provided one.
    if ($isAdmin && in_array($response['responder_type'] ?? '', ['expert', 'both'], true)) {
        $response = null;
    }
}

if ($response) {
    $response['voice_url'] = filePathToUrl($response['voice_response_path'] ?? null);
}

jsonResponse([
    'success' => true,
    'request' => $request,
    'response' => $response,
]);
