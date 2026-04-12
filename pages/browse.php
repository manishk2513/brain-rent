<?php
// pages/browse.php — Browse & Filter Experts
$title = 'Browse Experts';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();

// Load categories for filter
$categories = $db->fetchAll("SELECT id, name, icon FROM expertise_categories WHERE is_active = 1 ORDER BY name");
$avColors = ['av-1', 'av-2', 'av-3', 'av-4', 'av-5', 'av-6'];
$canSeePendingBadge = in_array(($user['user_type'] ?? ''), ['admin', 'expert', 'both'], true);
?>
<main class="py-4" style="padding-top:80px!important">
  <div class="container">

    <!-- Page Header -->
    <div class="mb-4">
      <h1 class="br-section-title fs-2 mb-1">Browse Experts</h1>
      <p class="text-muted small">Find the right thinker for your problem</p>
    </div>

    <!-- Search Bar -->
    <div class="br-search mb-4" style="max-width:600px">
      <span class="br-search-icon"><i class="bi bi-search"></i></span>
      <input type="text" id="search-input" placeholder="Search by name, skill, or industry…" oninput="liveSearch()">
    </div>

    <div class="row g-4">

      <!-- ===== FILTERS SIDEBAR ===== -->
      <div class="col-12 col-lg-3">
        <div class="br-filters">
          <form id="filter-form" onsubmit="return false">

            <div class="mb-4">
              <div class="br-filter-label mb-2">Category</div>
              <div class="br-filter-opt">
                <input type="radio" name="category_id" value="" id="cat-all" checked class="form-check-input">
                <label for="cat-all" class="mb-0 w-100 cursor-pointer">All Categories</label>
              </div>
              <?php foreach ($categories as $cat): ?>
                <div class="br-filter-opt">
                  <input type="radio" name="category_id" value="<?= $cat['id'] ?>" id="cat-<?= $cat['id'] ?>"
                    class="form-check-input" onchange="applyFilters()">
                  <label for="cat-<?= $cat['id'] ?>" class="mb-0 w-100"
                    style="cursor:pointer"><?= htmlspecialchars($cat['name']) ?></label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mb-4">
              <div class="br-filter-label mb-2">Session Rate (USD)</div>
              <div class="d-flex gap-2">
                <input type="number" name="min_price" class="br-form-control" placeholder="Min" min="0"
                  onchange="applyFilters()">
                <input type="number" name="max_price" class="br-form-control" placeholder="Max" min="0"
                  onchange="applyFilters()">
              </div>
            </div>

            <div class="mb-4">
              <div class="br-filter-label mb-2">Minimum Rating</div>
              <?php foreach (['', '4.0', '4.5', '4.8'] as $r): ?>
                <div class="br-filter-opt">
                  <input type="radio" name="min_rating" value="<?= $r ?>" id="r-<?= str_replace('.', '_', $r) ?>"
                    <?= $r === '' ? 'checked' : '' ?> class="form-check-input" onchange="applyFilters()">
                  <label for="r-<?= str_replace('.', '_', $r) ?>" class="mb-0 w-100" style="cursor:pointer">
                    <?= $r ? '⭐ ' . $r . '+' : 'Any rating' ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="mb-4">
              <div class="br-filter-label mb-2">Expert Status</div>
              <div class="br-filter-opt">
                <input type="checkbox" name="verified_only" value="1" id="verified" class="form-check-input"
                  onchange="applyFilters()">
                <label for="verified" class="mb-0" style="cursor:pointer">✅ Verified Only</label>
              </div>
              <div class="br-filter-opt">
                <input type="checkbox" name="available_only" value="1" id="available" class="form-check-input"
                  onchange="applyFilters()">
                <label for="available" class="mb-0" style="cursor:pointer">🟢 Available Now</label>
              </div>
            </div>

            <button type="button" class="btn br-btn-gold w-100" onclick="applyFilters()">Apply Filters</button>
            <button type="button" class="btn br-btn-ghost w-100 mt-2" onclick="resetFilters()">Reset</button>
          </form>
        </div>
      </div>

      <!-- ===== RESULTS ===== -->
      <div class="col-12 col-lg-9">

        <!-- Sort Bar -->
        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
          <span class="text-subtle small">Sort by:</span>
          <button class="br-sort-btn active" onclick="setSort(this,'rating')">⭐ Top Rated</button>
          <button class="br-sort-btn" onclick="setSort(this,'price_low')">💰 Price ↑</button>
          <button class="br-sort-btn" onclick="setSort(this,'price_high')">💰 Price ↓</button>
          <button class="br-sort-btn" onclick="setSort(this,'sessions')">📊 Most Sessions</button>
          <button class="br-sort-btn" onclick="setSort(this,'newest')">✨ Newest</button>
          <span class="ms-auto text-subtle small" id="results-count">Loading…</span>
        </div>

        <!-- Expert Cards Grid -->
        <div class="row g-3" id="experts-grid">
          <div class="col-12 text-center py-5">
            <div class="spinner-border text-warning" role="status"></div>
          </div>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="d-flex justify-content-center gap-2 mt-4"></div>
      </div>
    </div>
  </div>
</main>

<script>
  const APP_URL = '<?= APP_URL ?>';
  const avColors = <?= json_encode($avColors) ?>;
  const CAN_SEE_PENDING_BADGE = <?= json_encode($canSeePendingBadge) ?>;
  const VIEWER_ROLE = <?= json_encode($user['user_type'] ?? 'guest') ?>;
  let currentSort = 'rating';
  let currentPage = 1;

  async function loadExperts(page = 1) {
    currentPage = page;
    const form = document.getElementById('filter-form');
    const fd = new FormData(form);
    const params = new URLSearchParams(fd);
    const keyword = document.getElementById('search-input').value.trim();
    if (keyword) params.set('keyword', keyword);
    params.set('sort', currentSort);
    params.set('page', page);

    document.getElementById('experts-grid').innerHTML = `
    <div class="col-12 text-center py-5">
      <div class="spinner-border text-warning" role="status"></div>
    </div>`;

    try {
      const res = await fetch(APP_URL + '/api/search_experts.php?' + params.toString());
      const data = await res.json();
      if (!data.success) { renderError(data.error); return; }

      document.getElementById('results-count').textContent = `${data.total} experts found`;
      renderExperts(data.experts);
      renderPagination(data.page, data.total_pages);
    } catch (e) {
      renderError('Failed to load experts.');
    }
  }

  function renderExperts(experts) {
    const grid = document.getElementById('experts-grid');
    if (!experts.length) {
      grid.innerHTML = `
      <div class="col-12 text-center py-5 text-muted">
        <div style="font-size:3rem;opacity:.4">🔍</div>
        <h5 class="mt-3">No experts found</h5>
        <p class="small">Try adjusting your filters</p>
      </div>`;
      return;
    }
    grid.innerHTML = experts.map((e, i) => {
      const isVerified = Number(e.is_verified) === 1;
      const isAvailable = Number(e.is_available) === 1;
      const cardHref = (!isVerified && CAN_SEE_PENDING_BADGE)
        ? (VIEWER_ROLE === 'admin'
          ? `${APP_URL}/admin/expert-review.php?id=${e.user_id}`
          : 'javascript:void(0)')
        : `${APP_URL}/pages/expert-profile.php?id=${e.user_id}`;
      const statusBadge = isVerified
        ? '<div class="position-absolute top-0 end-0 m-3"><span class="br-badge br-badge-teal" style="font-size:.68rem">✓ Verified</span></div>'
        : (CAN_SEE_PENDING_BADGE
          ? '<div class="position-absolute top-0 end-0 m-3"><span class="br-badge br-badge-gold" style="font-size:.68rem">Pending approval</span></div>'
          : '');

      return `
    <div class="col-12 col-sm-6 col-xl-4">
        <a href="${cardHref}" class="text-decoration-none">
        <div class="br-card br-expert-card p-4 h-100">
          ${statusBadge}
          <div class="br-expert-avatar ${avColors[i % avColors.length]} mb-3">
            ${e.full_name.substring(0, 2).toUpperCase()}
          </div>
          <div class="fw-semibold">${escHtml(e.full_name)}</div>
          <div class="text-muted small mb-3">${escHtml(e.headline || '')}</div>
          <div class="d-flex flex-wrap gap-1 mb-3">
            ${(JSON.parse(e.expertise_areas || '[]') || []).slice(0, 3).map(t =>
        `<span class="badge" style="background:var(--br-dark3);color:var(--br-text2);border:1px solid var(--br-border);font-weight:400">${escHtml(t)}</span>`
      ).join('')}
          </div>
          <div class="d-flex justify-content-between align-items-center pt-3" style="border-top:1px solid var(--br-border)">
            <div>
              <div class="mono text-gold">$${Number(e.rate_per_session).toFixed(0)}<span class="text-muted" style="font-size:.72rem">/session</span></div>
              <div class="text-subtle" style="font-size:.7rem">${Number(e.total_sessions).toLocaleString()} sessions</div>
            </div>
            <div class="text-end">
              <div class="small text-muted">⭐ ${Number(e.average_rating).toFixed(1)} <span class="text-subtle">(${e.total_reviews})</span></div>
              <div style="font-size:.7rem;${isAvailable ? 'color:#4ecb71' : 'color:var(--br-text3)'}">
                ${isAvailable ? '🟢 Available' : '⚪ Busy'}
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>`;
    }).join('');
  }

  function renderPagination(page, totalPages) {
    const el = document.getElementById('pagination');
    if (totalPages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    if (page > 1) html += `<button class="btn br-btn-ghost btn-sm" onclick="loadExperts(${page - 1})">← Prev</button>`;
    for (let p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p++) {
      html += `<button class="btn btn-sm ${p === page ? 'br-btn-gold' : 'br-btn-ghost'}" onclick="loadExperts(${p})">${p}</button>`;
    }
    if (page < totalPages) html += `<button class="btn br-btn-ghost btn-sm" onclick="loadExperts(${page + 1})">Next →</button>`;
    el.innerHTML = html;
  }

  function renderError(msg) {
    document.getElementById('experts-grid').innerHTML = `<div class="col-12 text-center text-muted py-4">${escHtml(msg)}</div>`;
  }

  function setSort(el, sort) {
    document.querySelectorAll('.br-sort-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    currentSort = sort;
    loadExperts(1);
  }

  let searchTimer;
  function liveSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadExperts(1), 350);
  }

  function applyFilters() { loadExperts(1); }
  function resetFilters() {
    document.getElementById('filter-form').reset();
    document.getElementById('search-input').value = '';
    loadExperts(1);
  }

  function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // Initial load
  loadExperts(1);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>