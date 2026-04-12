<?php
// admin/users.php — User Management
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$title = 'Admin - Users';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$users = $db->fetchAll(
    "SELECT u.id, u.full_name, u.email, u.user_type, u.is_active, u.created_at,
            u.phone, u.country, u.profile_photo, u.bio,
            ep.id AS expert_profile_id,
            ep.is_verified AS expert_is_verified,
            ep.is_available AS expert_is_available,
            ep.headline, ep.qualification, ep.domain, ep.skills, ep.expertise_areas,
            ep.experience_years, ep.current_role_name AS `current_role`, ep.company,
            ep.linkedin_url, ep.portfolio_url,
            ep.rate_per_session, ep.currency, ep.session_duration_minutes, ep.max_response_hours
     FROM users u
     LEFT JOIN expert_profiles ep ON ep.user_id = u.id
     ORDER BY u.created_at ASC"
);

$userDetails = [];
foreach ($users as $u) {
    $expertiseList = [];
    if (!empty($u['expertise_areas'])) {
        $decoded = json_decode($u['expertise_areas'], true);
        if (is_array($decoded)) {
            $expertiseList = $decoded;
        } else {
            $expertiseList = array_filter(array_map('trim', explode(',', $u['expertise_areas'])));
        }
    }

    $skillsList = [];
    if (!empty($u['skills'])) {
        $skillsList = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', $u['skills']))));
    }

    $userDetails[$u['id']] = [
        'id' => (int) $u['id'],
        'full_name' => $u['full_name'],
        'email' => $u['email'],
        'phone' => $u['phone'] ?? '',
        'country' => $u['country'] ?? '',
        'user_type' => $u['user_type'],
        'is_active' => (int) $u['is_active'] === 1,
        'created_at' => $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '',
        'profile_photo' => $u['profile_photo'] ?? '',
        'bio' => $u['bio'] ?? '',
        'is_expert' => in_array($u['user_type'], ['expert', 'both']),
        'expert' => $u['expert_profile_id'] ? [
            'is_verified' => (int) $u['expert_is_verified'] === 1,
            'is_available' => (int) $u['expert_is_available'] === 1,
            'headline' => $u['headline'] ?? '',
            'qualification' => $u['qualification'] ?? '',
            'domain' => $u['domain'] ?? '',
            'skills' => $skillsList,
            'expertise_areas' => $expertiseList,
            'experience_years' => $u['experience_years'] ?? '',
            'current_role' => $u['current_role'] ?? '',
            'company' => $u['company'] ?? '',
            'linkedin_url' => $u['linkedin_url'] ?? '',
            'portfolio_url' => $u['portfolio_url'] ?? '',
            'rate_per_session' => $u['rate_per_session'] ?? '',
            'currency' => $u['currency'] ?? '',
            'session_duration_minutes' => $u['session_duration_minutes'] ?? '',
            'max_response_hours' => $u['max_response_hours'] ?? '',
        ] : null,
    ];
}

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
?>

<main class="py-5">
    <div class="container">
        <div class="mb-4">
            <h1 class="display-6 fw-bold">User Management</h1>
            <p class="text-muted">View and manage all users.</p>
        </div>

        <?php
        $activeAdminPage = 'users';
        require __DIR__ . '/_nav.php';
        ?>

        <?php if ($status && $message): ?>
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="br-card p-3">
            <div class="table-responsive">
                <table class="table br-table table-dark admin-table mb-0">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1;
                        foreach ($users as $u): ?>
                            <tr class="admin-user-row" data-user-id="<?= (int) $u['id'] ?>">
                                <td><?= $serial++ ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['user_type']) ?></td>
                                <td><?= $u['is_active'] ? 'Active' : 'Disabled' ?></td>
                                <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <?php if (in_array($u['user_type'], ['expert', 'both'])): ?>
                                        <a href="<?= APP_URL ?>/admin/expert-review.php?id=<?= (int) $u['id'] ?>"
                                            class="btn br-btn-ghost btn-sm">Review</a>
                                    <?php endif; ?>
                                    <?php if ($u['user_type'] !== 'admin'): ?>
                                        <form method="post" action="<?= APP_URL ?>/admin/actions.php"
                                            style="display:inline-block">
                                            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="entity" value="users">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="redirect" value="<?= APP_URL ?>/admin/users.php">
                                            <button type="submit" class="btn br-btn-ghost btn-sm">
                                                <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Admin</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="br-card p-3 mt-4" id="user-detail-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                <h6 class="fw-semibold mb-0">User Details</h6>
                <span class="text-subtle small" id="user-detail-badge"></span>
            </div>
            <div id="user-detail-empty" class="text-muted small">Click a user row to view details.</div>
            <div id="user-detail-body" class="row g-3" style="display:none;">
                <div class="col-6 col-lg-3">
                    <div class="text-subtle small">User ID</div>
                    <div class="fw-medium" id="detail-id"></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-subtle small">Name</div>
                    <div class="fw-medium" id="detail-name"></div>
                </div>
                <div class="col-12 col-lg-3">
                    <div class="text-subtle small">Email</div>
                    <div class="fw-medium" id="detail-email"></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-subtle small">Type</div>
                    <div class="fw-medium" id="detail-type"></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-subtle small">Status</div>
                    <div class="fw-medium" id="detail-status"></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-subtle small">Phone</div>
                    <div class="fw-medium" id="detail-phone"></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-subtle small">Country</div>
                    <div class="fw-medium" id="detail-country"></div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-subtle small">Created</div>
                    <div class="fw-medium" id="detail-created"></div>
                </div>
                <div class="col-12">
                    <div class="text-subtle small">Bio</div>
                    <div class="fw-medium" id="detail-bio"></div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3" id="expert-actions" style="display:none;">
                <button type="button" class="btn br-btn-ghost btn-sm" id="expert-detail-btn">Expert Details</button>
                <form method="post" action="<?= APP_URL ?>/admin/actions.php" id="verify-expert-form"
                    style="display:none;">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="entity" value="experts">
                    <input type="hidden" name="action" value="verify_expert">
                    <input type="hidden" name="id" id="verify-expert-id" value="">
                    <input type="hidden" name="redirect" value="<?= APP_URL ?>/admin/users.php">
                    <button type="submit" class="btn br-btn-gold btn-sm">Verify Expert</button>
                </form>
            </div>

            <div class="br-card p-3 mt-3 d-none" id="expert-detail-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <h6 class="fw-semibold mb-0">Expert Details</h6>
                    <span class="text-subtle small" id="expert-status"></span>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <div class="text-subtle small">Qualification</div>
                        <div class="fw-medium" id="expert-qualification"></div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="text-subtle small">Domain</div>
                        <div class="fw-medium" id="expert-domain"></div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="text-subtle small">Skills</div>
                        <div class="fw-medium" id="expert-skills"></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="text-subtle small">Experience (Years)</div>
                        <div class="fw-medium" id="expert-experience"></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="text-subtle small">Current Role</div>
                        <div class="fw-medium" id="expert-role"></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="text-subtle small">Company</div>
                        <div class="fw-medium" id="expert-company"></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="text-subtle small">Headline</div>
                        <div class="fw-medium" id="expert-headline"></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="text-subtle small">Rate</div>
                        <div class="fw-medium" id="expert-rate"></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="text-subtle small">Session Duration</div>
                        <div class="fw-medium" id="expert-duration"></div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="text-subtle small">Max Response</div>
                        <div class="fw-medium" id="expert-response"></div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="text-subtle small">LinkedIn</div>
                        <div class="fw-medium" id="expert-linkedin"></div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="text-subtle small">Portfolio</div>
                        <div class="fw-medium" id="expert-portfolio"></div>
                    </div>
                    <div class="col-12">
                        <div class="text-subtle small">Expertise Areas</div>
                        <div class="fw-medium" id="expert-areas"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    const adminUsers = <?= json_encode($userDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const rows = document.querySelectorAll('.admin-user-row');
    const userDetailEmpty = document.getElementById('user-detail-empty');
    const userDetailBody = document.getElementById('user-detail-body');
    const userDetailBadge = document.getElementById('user-detail-badge');
    const expertActions = document.getElementById('expert-actions');
    const expertDetailBtn = document.getElementById('expert-detail-btn');
    const expertDetailCard = document.getElementById('expert-detail-card');
    const verifyExpertForm = document.getElementById('verify-expert-form');
    const verifyExpertId = document.getElementById('verify-expert-id');

    const detailFields = {
        id: document.getElementById('detail-id'),
        name: document.getElementById('detail-name'),
        email: document.getElementById('detail-email'),
        type: document.getElementById('detail-type'),
        status: document.getElementById('detail-status'),
        phone: document.getElementById('detail-phone'),
        country: document.getElementById('detail-country'),
        created: document.getElementById('detail-created'),
        bio: document.getElementById('detail-bio')
    };

    const expertFields = {
        status: document.getElementById('expert-status'),
        qualification: document.getElementById('expert-qualification'),
        domain: document.getElementById('expert-domain'),
        skills: document.getElementById('expert-skills'),
        experience: document.getElementById('expert-experience'),
        role: document.getElementById('expert-role'),
        company: document.getElementById('expert-company'),
        headline: document.getElementById('expert-headline'),
        rate: document.getElementById('expert-rate'),
        duration: document.getElementById('expert-duration'),
        response: document.getElementById('expert-response'),
        linkedin: document.getElementById('expert-linkedin'),
        portfolio: document.getElementById('expert-portfolio'),
        areas: document.getElementById('expert-areas')
    };

    let expertVisible = false;

    function safeText(value) {
        if (value === null || value === undefined || value === '') return 'N/A';
        return String(value);
    }

    function setField(el, value) {
        if (!el) return;
        el.textContent = safeText(value);
    }

    function renderUserDetails(user) {
        if (!user) return;

        userDetailEmpty.style.display = 'none';
        userDetailBody.style.display = 'flex';

        setField(detailFields.id, user.id);
        setField(detailFields.name, user.full_name);
        setField(detailFields.email, user.email);
        setField(detailFields.type, user.user_type);
        setField(detailFields.status, user.is_active ? 'Active' : 'Disabled');
        setField(detailFields.phone, user.phone);
        setField(detailFields.country, user.country);
        setField(detailFields.created, user.created_at);
        setField(detailFields.bio, user.bio);

        if (user.user_type === 'admin') {
            userDetailBadge.textContent = 'Admin';
        } else {
            userDetailBadge.textContent = user.is_expert ? 'Expert' : 'User';
        }

        if (user.is_expert && user.expert) {
            expertActions.style.display = 'flex';
            verifyExpertForm.style.display = user.expert.is_verified ? 'none' : 'inline-block';
            verifyExpertId.value = user.id;
        } else {
            expertActions.style.display = 'none';
        }

        expertVisible = false;
        expertDetailCard.classList.add('d-none');
        expertDetailBtn.textContent = 'Expert Details';

        if (user.is_expert && user.expert) {
            const exp = user.expert;
            expertFields.status.textContent = exp.is_verified ? 'Verified' : 'Pending Verification';
            setField(expertFields.qualification, exp.qualification);
            setField(expertFields.domain, exp.domain);
            setField(expertFields.skills, (exp.skills || []).join(', '));
            setField(expertFields.experience, exp.experience_years);
            setField(expertFields.role, exp.current_role);
            setField(expertFields.company, exp.company);
            setField(expertFields.headline, exp.headline);

            if (exp.rate_per_session) {
                const rate = `${exp.currency || 'USD'} ${Number(exp.rate_per_session).toFixed(2)}`;
                setField(expertFields.rate, rate);
            } else {
                setField(expertFields.rate, 'N/A');
            }

            setField(expertFields.duration, exp.session_duration_minutes ? `${exp.session_duration_minutes} min` : 'N/A');
            setField(expertFields.response, exp.max_response_hours ? `${exp.max_response_hours} hrs` : 'N/A');
            setField(expertFields.linkedin, exp.linkedin_url);
            setField(expertFields.portfolio, exp.portfolio_url);
            setField(expertFields.areas, (exp.expertise_areas || []).join(', '));
        }
    }

    expertDetailBtn.addEventListener('click', () => {
        expertVisible = !expertVisible;
        expertDetailCard.classList.toggle('d-none', !expertVisible);
        expertDetailBtn.textContent = expertVisible ? 'Hide Expert Details' : 'Expert Details';
    });

    rows.forEach(row => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('button, a, form, input')) return;
            rows.forEach(r => r.classList.remove('active'));
            row.classList.add('active');
            const userId = row.dataset.userId;
            renderUserDetails(adminUsers[userId]);
        });
    });

    if (rows.length) {
        rows[0].classList.add('active');
        renderUserDetails(adminUsers[rows[0].dataset.userId]);
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>