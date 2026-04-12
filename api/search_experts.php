<?php
// =============================================
// api/search_experts.php
// GET /api/search_experts.php
// Returns paginated, filtered expert list
// =============================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

class ExpertSearch
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Search experts with filters + pagination
     * Params (GET):
     *   keyword, category_id, min_price, max_price,
     *   min_rating, verified_only, available_only,
     *   sort (rating|price_low|price_high|sessions|newest),
     *   page (default 1)
     */
    public function search(array $f = []): array
    {
        $viewer = currentUser();
        $viewerType = $viewer['user_type'] ?? 'guest';
        $mustSeeApprovedOnly = !in_array($viewerType, ['admin', 'expert', 'both'], true);
        ensurePendingExpertProfilesTable($this->db);

        $where = [
            "u.is_active = 1",
        ];
        $params = [];

        // Clients (and guests) should see only admin-approved experts.
        if ($mustSeeApprovedOnly) {
            $where[] = "ep.is_verified = 1";
            $where[] = "ep.is_available = 1";
        }

        if (!empty($f['keyword'])) {
            $kw = '%' . $f['keyword'] . '%';
            $where[] = "(ep.headline LIKE ? OR u.full_name LIKE ? OR ep.expertise_areas LIKE ?)";
            $params = array_merge($params, [$kw, $kw, $kw]);
        }

        if (!empty($f['category_id'])) {
            $where[] = "xc.category_id = ?";
            $params[] = (int) $f['category_id'];
        }

        if (isset($f['min_price']) && $f['min_price'] !== '') {
            $where[] = "ep.rate_per_session >= ?";
            $params[] = (float) $f['min_price'];
        }

        if (isset($f['max_price']) && $f['max_price'] !== '') {
            $where[] = "ep.rate_per_session <= ?";
            $params[] = (float) $f['max_price'];
        }

        if (!empty($f['min_rating'])) {
            $where[] = "ep.average_rating >= ?";
            $params[] = (float) $f['min_rating'];
        }

        if (!empty($f['verified_only']) && !$mustSeeApprovedOnly) {
            $where[] = "ep.is_verified = 1";
        }

        if (!empty($f['available_only']) && !$mustSeeApprovedOnly) {
            $where[] = "ep.is_available = 1";
        }

        $whereSql = implode(" AND ", $where);

        $sql = "SELECT
                    u.id        AS user_id,
                    u.full_name,
                    u.profile_photo,
                    ep.id       AS profile_id,
                    ep.headline,
                    ep.expertise_areas,
                    ep.experience_years,
                    ep.current_role_name AS `current_role`,
                    ep.company,
                    ep.rate_per_session,
                    ep.currency,
                    ep.session_duration_minutes,
                    ep.max_response_hours,
                    ep.average_rating,
                    ep.total_reviews,
                    ep.total_sessions,
                    ep.is_verified,
                    ep.is_available,
                    ep.created_at AS profile_created_at,
                    GROUP_CONCAT(ec.name) AS category_names
                FROM expert_profiles ep
                INNER JOIN users u              ON ep.user_id = u.id
                LEFT  JOIN expert_categories xc ON ep.id = xc.expert_profile_id
                LEFT  JOIN expertise_categories ec ON xc.category_id = ec.id
                WHERE {$whereSql}";

        $sql .= " GROUP BY u.id, u.full_name, u.profile_photo,
                            ep.id, ep.headline, ep.expertise_areas,
                            ep.experience_years, ep.current_role_name, ep.company,
                            ep.rate_per_session, ep.currency,
                            ep.session_duration_minutes, ep.max_response_hours,
                            ep.average_rating, ep.total_reviews, ep.total_sessions,
                            ep.is_verified, ep.is_available, ep.created_at";

        $experts = $this->db->fetchAll($sql, $params);

        if (!$mustSeeApprovedOnly) {
            $pendingWhere = [
                "u.is_active = 1",
                "pep.status = 'pending'",
            ];
            $pendingParams = [];

            if (!empty($f['keyword'])) {
                $kw = '%' . $f['keyword'] . '%';
                $pendingWhere[] = "(pep.headline LIKE ? OR u.full_name LIKE ? OR pep.expertise_areas LIKE ?)";
                $pendingParams = array_merge($pendingParams, [$kw, $kw, $kw]);
            }

            if (isset($f['min_price']) && $f['min_price'] !== '') {
                $pendingWhere[] = "pep.rate_per_session >= ?";
                $pendingParams[] = (float) $f['min_price'];
            }

            if (isset($f['max_price']) && $f['max_price'] !== '') {
                $pendingWhere[] = "pep.rate_per_session <= ?";
                $pendingParams[] = (float) $f['max_price'];
            }

            if (!empty($f['min_rating'])) {
                $pendingWhere[] = "0 >= ?";
                $pendingParams[] = (float) $f['min_rating'];
            }

            if (!empty($f['verified_only']) || !empty($f['available_only']) || !empty($f['category_id'])) {
                $pendingWhere[] = "1 = 0";
            }

            $pendingSql = "SELECT
                    u.id AS user_id,
                    u.full_name,
                    u.profile_photo,
                    pep.id AS profile_id,
                    pep.headline,
                    pep.expertise_areas,
                    pep.experience_years,
                    pep.current_role_name AS `current_role`,
                    pep.company,
                    pep.rate_per_session,
                    pep.currency,
                    pep.session_duration_minutes,
                    pep.max_response_hours,
                    0 AS average_rating,
                    0 AS total_reviews,
                    0 AS total_sessions,
                    0 AS is_verified,
                    0 AS is_available,
                    NULL AS category_names,
                    pep.created_at AS profile_created_at
                FROM pending_expert_profiles pep
                INNER JOIN users u ON pep.user_id = u.id
                WHERE " . implode(" AND ", $pendingWhere);

            $pendingExperts = $this->db->fetchAll($pendingSql, $pendingParams);
            if ($pendingExperts) {
                $experts = array_merge($experts, $pendingExperts);
            }
        }

        foreach ($experts as &$expert) {
            if (!array_key_exists('profile_created_at', $expert)) {
                $expert['profile_created_at'] = null;
            }
        }
        unset($expert);

        $sortKey = $f['sort'] ?? 'rating';
        usort($experts, static function (array $a, array $b) use ($sortKey): int {
            $aVerified = (int) ($a['is_verified'] ?? 0);
            $bVerified = (int) ($b['is_verified'] ?? 0);
            if ($aVerified !== $bVerified) {
                return $bVerified <=> $aVerified;
            }

            if ($sortKey === 'price_low') {
                return ((float) ($a['rate_per_session'] ?? 0)) <=> ((float) ($b['rate_per_session'] ?? 0));
            }
            if ($sortKey === 'price_high') {
                return ((float) ($b['rate_per_session'] ?? 0)) <=> ((float) ($a['rate_per_session'] ?? 0));
            }
            if ($sortKey === 'sessions') {
                return ((int) ($b['total_sessions'] ?? 0)) <=> ((int) ($a['total_sessions'] ?? 0));
            }
            if ($sortKey === 'newest') {
                $aTime = strtotime((string) ($a['profile_created_at'] ?? '')) ?: 0;
                $bTime = strtotime((string) ($b['profile_created_at'] ?? '')) ?: 0;
                return $bTime <=> $aTime;
            }

            return ((float) ($b['average_rating'] ?? 0)) <=> ((float) ($a['average_rating'] ?? 0));
        });

        $page = max(1, (int) ($f['page'] ?? 1));
        $perPage = 12;
        $total = count($experts);
        $offset = ($page - 1) * $perPage;
        $experts = array_slice($experts, $offset, $perPage);

        return [
            'experts' => $experts,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}

// Handle request
try {
    $search = new ExpertSearch();
    $result = $search->search($_GET);
    echo json_encode(['success' => true] + $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
