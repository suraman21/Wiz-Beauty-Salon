<?php
/**
 * The page behind the link we email a customer.
 * Layout v2 — WIZ editorial, not admin-auth. Focus: one thumb, one decision.
 *
 * Structure: hero summary + 2-col grid (main actions | side help) → stacks on mobile.
 * Tokens are 24h, no account. Every tap is 44px+, focus-visible, radiogroup semantics.
 *
 * @var array<string, mixed> $booking
 * @var string $token
 * @var list<array{date: string, open: bool, reason: string|null, slots: list<string>}> $days
 * @var string|null $error
 * @var string|null $success
 */

use Wiz\Domain\Enums\BookingStatus;

$status = BookingStatus::tryFrom((string) $booking['status']);
$dateTs = strtotime((string) $booking['booking_date']);
$start = substr((string) $booking['start_time'], 0, 5);
$end = substr((string) $booking['end_time'], 0, 5);
$canManage = $status !== null && in_array($status, [BookingStatus::Requested, BookingStatus::Confirmed], true);
$statusLabel = $status?->label() ?? ucfirst((string) $booking['status']);
$statusClass = match ((string) ($status?->value ?? $booking['status'])) {
    'confirmed' => 'status--confirmed',
    'requested' => 'status--requested',
    'cancelled' => 'status--cancelled',
    'declined'  => 'status--cancelled',
    default     => 'status--muted',
};
$hasSlots = false;
foreach ($days as $d) { if ($d['slots'] !== []) { $hasSlots = true; break; } }

// Human date for hero + summary
$heroDate = $dateTs !== false ? date('l, j F', $dateTs) : (string) $booking['booking_date'];
$heroDayNum = $dateTs !== false ? date('j', $dateTs) : '–';
$heroMonth = $dateTs !== false ? date('M', $dateTs) : '';
$heroWeekday = $dateTs !== false ? date('D', $dateTs) : '';
$duration = 0;
try {
    $s = strtotime((string) $booking['booking_date'] . ' ' . (string) $booking['start_time']);
    $e = strtotime((string) $booking['booking_date'] . ' ' . (string) $booking['end_time']);
    if ($s !== false && $e !== false && $e > $s) $duration = (int) round(($e - $s)/60);
} catch (\Throwable $e) {}
$price = $booking['price_cents'] ?? null;
$priceText = $price !== null && (int)$price > 0 ? number_format((int)$price/100) . ' ETB' : null;
?>

<div class="manage-wrap">
    <!-- Top bar: WIZ home + reference -->
    <div class="manage-topbar">
        <a href="/" class="manage-brand" aria-label="WIZ Beauty Salon home">
            <span class="manage-brand-mark">WIZ</span>
            <span class="manage-brand-sub">Beauty Salon</span>
        </a>
        <div class="manage-topbar-right">
            <span class="manage-ref"><?= e((string) $booking['reference']) ?></span>
            <span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="manage-alert manage-alert--success" role="status">
            <span class="manage-alert-icon">✓</span>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="manage-alert manage-alert--error" role="alert">
            <span class="manage-alert-icon">!</span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Hero: what you have -->
    <header class="manage-hero">
        <div class="manage-hero-date">
            <span class="manage-hero-num"><?= e($heroDayNum) ?></span>
            <span class="manage-hero-meta"><?= e($heroWeekday) ?> · <?= e($heroMonth) ?></span>
        </div>
        <div class="manage-hero-main">
            <h1 class="manage-hero-title"><?= e($booking['service_name']) ?></h1>
            <p class="manage-hero-when"><?= e($heroDate) ?> · <?= e($start) ?>–<?= e($end) ?><?php if ($duration) echo ' · ' . $duration . ' min'; ?><?php if ($priceText) echo ' · ' . $priceText; ?></p>
            <p class="manage-hero-policy">Free to change or cancel until 24 hours before. After that, please call.</p>
        </div>
    </header>

    <div class="manage-grid">
        <!-- Main column: details + reschedule -->
        <div class="manage-main">
            <section class="manage-card">
                <h2 class="manage-card-title">Your appointment</h2>
                <dl class="manage-details">
                    <div>
                        <dt>Service</dt>
                        <dd><?= e($booking['service_name']) ?><?php if ($priceText): ?> <span class="manage-detail-sub"><?= e($priceText) ?></span><?php endif; ?></dd>
                    </div>
                    <div>
                        <dt>When</dt>
                        <dd><?= e($heroDate) ?> at <?= e($start) ?><?php if ($duration) echo ' · ' . $duration . ' min'; ?></dd>
                    </div>
                    <div>
                        <dt>Name</dt>
                        <dd><?= e($booking['customer_name']) ?></dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd><a href="tel:<?= e(preg_replace('/[^\\d+]/', '', (string) $booking['customer_phone'])) ?>"><?= e($booking['customer_phone']) ?></a></dd>
                    </div>
                    <?php if (!empty($booking['customer_email'])): ?>
                        <div>
                            <dt>Email</dt>
                            <dd><?= e($booking['customer_email']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['notes'])): ?>
                        <div class="manage-details-full">
                            <dt>Notes</dt>
                            <dd><?= e((string) $booking['notes']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <?php if ($canManage): ?>
                <section class="manage-card manage-card--action" aria-labelledby="reschedule-heading">
                    <div class="manage-card-head">
                        <h2 id="reschedule-heading">Reschedule</h2>
                        <p class="manage-card-hint">Pick a new day — times appear. Your current slot is freed when you confirm.</p>
                    </div>

                    <?php if (!$hasSlots): ?>
                        <p class="manage-empty">No other times are free in the next two weeks. Call the salon and we’ll find you a time.</p>
                        <p style="margin-top:12px;"><a class="btn btn--ghost" href="/contact">Contact the salon</a></p>
                    <?php else: ?>
                        <form method="post" action="/booking/manage/<?= e($token) ?>/reschedule" id="rescheduleForm" class="manage-reschedule-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="date" id="reschedule-date" value="">
                            <input type="hidden" name="time" id="reschedule-time" value="">

                            <!-- Date pills: scrollable row on mobile, grid on desktop -->
                            <fieldset class="manage-fieldset">
                                <legend class="manage-legend">Choose a new date</legend>
                                <div class="manage-date-row" role="radiogroup" aria-label="Choose a new date">
                                    <?php foreach ($days as $day): ?>
                                        <?php if ($day['slots'] === []) { continue; } ?>
                                        <?php $d = (int) strtotime($day['date']); ?>
                                        <button type="button" class="manage-date-pill" data-date="<?= e($day['date']) ?>" data-slots="<?= e(implode(',', $day['slots'])) ?>" role="radio" aria-checked="false">
                                            <span class="pill-weekday"><?= e(date('D', $d)) ?></span>
                                            <strong class="pill-day"><?= e(date('j', $d)) ?></strong>
                                            <span class="pill-month"><?= e(date('M', $d)) ?></span>
                                            <span class="pill-count"><?= count($day['slots']) ?> slots</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <fieldset class="manage-fieldset" id="timeFieldset" hidden>
                                <legend class="manage-legend">Choose a time <span class="manage-legend-sub">Addis Ababa time</span></legend>
                                <div id="timeGrid" class="manage-time-grid" role="radiogroup" aria-label="Choose a new time"></div>
                                <p class="manage-help">Closed days are hidden. Times shown are still free right now.</p>
                            </fieldset>

                            <div id="reschedulePreview" class="manage-preview" hidden>
                                <div class="manage-preview-icon">↻</div>
                                <div>
                                    <strong>Move to <span id="previewDate"></span> at <span id="previewTime"></span></strong>
                                    <span>The old time goes back to the salon.</span>
                                </div>
                            </div>

                            <button type="submit" id="rescheduleSubmit" class="btn btn--primary btn--block manage-submit" disabled>Choose a day</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="manage-card manage-card--muted">
                    <p class="manage-empty" style="margin:0;">This appointment can’t be changed here (status: <?= e($statusLabel) ?>). If you need help, call the salon.</p>
                    <p style="margin-top:14px;"><a class="btn btn--ghost btn--block" href="/booking">Book another time</a></p>
                </section>
            <?php endif; ?>
        </div>

        <!-- Side column: cancel + help + link safety -->
        <aside class="manage-side">
            <?php if ($canManage): ?>
                <section class="manage-card manage-card--soft">
                    <h2 class="manage-card-title manage-card-title--sm">Cancel</h2>
                    <p class="manage-card-hint">The time goes back to the salon — you can always book again.</p>
                    <form method="post" action="/booking/manage/<?= e($token) ?>/cancel" onsubmit="return confirm('Cancel this appointment? This cannot be undone.');" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--block btn--danger">Cancel appointment</button>
                    </form>
                    <p class="manage-help" style="margin-top:8px; text-align:center;">Or try rescheduling first.</p>
                </section>
            <?php endif; ?>

            <section class="manage-card manage-card--help">
                <h2 class="manage-card-title manage-card-title--sm">Need help?</h2>
                <p>Give your reference <code class="manage-code"><?= e((string) $booking['reference']) ?></code> and we’ll help you change it.</p>
                <div class="manage-help-actions">
                    <?php if (!empty($booking['customer_phone'])): ?>
                        <a class="btn btn--ghost btn--sm" href="https://wa.me/<?= e(preg_replace('/[^\\d]/', '', (string) $booking['customer_phone'])) ?>" target="_blank" rel="noreferrer">WhatsApp</a>
                    <?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="/contact">Contact</a>
                    <a class="btn btn--ghost btn--sm" href="/">Home</a>
                </div>
                <p class="manage-link-note">Signed link · valid 24 hours · don’t share it — anyone with it can manage this booking.</p>
                <button type="button" class="btn btn--ghost btn--sm_manage_copy" id="copyLinkBtn" style="margin-top:10px; width:100%;">Copy link</button>
            </section>

            <?php if ($canManage): ?>
                <p class="manage-foot-link"><a href="/booking">Book another time →</a></p>
            <?php endif; ?>
        </aside>
    </div>
</div>

<style>
/* Manage — WIZ editorial, mobile-first. Overrides auth-shell centering. */
.auth-body { align-items: flex-start; padding: 18px 14px; background: #faf8f5; }
.auth-shell--wide { max-width: 960px; }
.manage-wrap { width:100%; }
.manage-topbar { display:flex; align-items:center; justify-content:space-between; gap:12px; padding: 10px 2px 16px; border-bottom:1px solid #e7e2dc; margin-bottom:18px; }
.manage-brand { display:flex; align-items:baseline; gap:8px; text-decoration:none; color:var(--ink); }
.manage-brand-mark { font:700 16px/1 var(--font); letter-spacing:.24em; }
.manage-brand-sub { font-size:11px; letter-spacing:.16em; text-transform:uppercase; color:var(--muted); }
.manage-topbar-right { display:flex; align-items:center; gap:10px; }
.manage-ref { font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); background:#fff; border:1px solid #e7e2dc; padding:5px 10px; border-radius:999px; }
.status-badge { display:inline-flex; align-items:center; padding:5px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
.status--requested { background:#fff7e6; color:#8a5a00; border:1px solid #ffda8a; }
.status--confirmed { background:#e6f7ed; color:#0a6b2e; border:1px solid #a8e0b8; }
.status--cancelled { background:#fce8e8; color:#8a1a1a; border:1px solid #f0b0b0; }
.status--muted { background:#f2f2f2; color:#666; border:1px solid #ddd; }
.manage-alert { display:flex; gap:10px; align-items:flex-start; padding:12px 14px; border-radius:10px; font-size:13.5px; margin-bottom:16px; }
.manage-alert--success { background:#eef6f1; border:1px solid #cfe4d8; color:#245c3c; }
.manage-alert--error { background:#fbeeee; border:1px solid #efd3d3; color:#9b2f2f; }
.manage-alert-icon { flex:none; width:22px; height:22px; display:grid; place-items:center; border-radius:50%; background:currentColor; color:#fff; font-size:12px; font-weight:700; }
.manage-hero { display:grid; grid-template-columns: 84px 1fr; gap:16px; align-items:center; padding:16px; background:#fff; border:1px solid #e7e2dc; border-radius:14px; box-shadow: 0 1px 2px rgba(29,26,24,.04), 0 6px 20px rgba(29,26,24,.05); }
.manage-hero-date { text-align:center; background:#211d1a; color:#fff; border-radius:10px; padding:10px 8px; }
.manage-hero-num { display:block; font:700 30px/1 var(--font); }
.manage-hero-meta { display:block; margin-top:4px; font-size:10.5px; letter-spacing:.08em; text-transform:uppercase; color:#cfc6bd; }
.manage-hero-title { font:600 18px/1.25 var(--font); letter-spacing:-.01em; }
.manage-hero-when { margin-top:6px; font-size:13px; color:var(--ink-soft); }
.manage-hero-policy { margin-top:6px; font-size:12px; color:var(--muted); }
.manage-grid { display:grid; grid-template-columns: 1.65fr .95fr; gap:16px; margin-top:16px; align-items:start; }
.manage-main, .manage-side { display:grid; gap:16px; }
.manage-card { background:#fff; border:1px solid #e7e2dc; border-radius:14px; padding:18px; box-shadow: 0 1px 2px rgba(29,26,24,.04), 0 6px 20px rgba(29,26,24,.05); }
.manage-card-title { font:650 15px/1.3 var(--font); letter-spacing:-.01em; }
.manage-card-title--sm { font-size:14px; }
.manage-card-head h2 { font:650 15px var(--font); }
.manage-card-hint { margin-top:4px; font-size:12.5px; color:var(--muted); line-height:1.5; }
.manage-details { display:grid; gap:12px; margin-top:14px; }
.manage-details div { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #f1ece6; font-size:13.5px; }
.manage-details div:last-child { border-bottom:0; }
.manage-details dt { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); }
.manage-details dd { text-align:right; color:var(--ink); font-weight:500; }
.manage-details dd a { color:var(--brand); }
.manage-detail-sub { color:var(--muted); font-weight:400; margin-left:6px; }
.manage-details-full { flex-direction:column; align-items:flex-start; }
.manage-details-full dt { margin-bottom:4px; }
.manage-details-full dd { text-align:left; }
.manage-card--action { border-color:#d9e8ff; }
.manage-card--soft { background:#fdfcfa; }
.manage-card--help { background:#fff; }
.manage-fieldset { border:0; padding:0; margin:14px 0 0; }
.manage-legend { font:600 12.5px var(--font); letter-spacing:.06em; text-transform:uppercase; color:var(--ink-soft); margin-bottom:8px; }
.manage-legend-sub { font-weight:400; text-transform:none; letter-spacing:0; color:var(--muted); margin-left:6px; }
.manage-date-row { display:flex; gap:8px; overflow-x:auto; padding-bottom:6px; scrollbar-width:thin; -webkit-overflow-scrolling:touch; scroll-snap-type:x mandatory; }
.manage-date-pill { scroll-snap-align:start; flex:0 0 86px; display:flex; flex-direction:column; align-items:center; gap:2px; padding:10px 6px; background:#fff; border:1px solid #d7d0c8; border-radius:12px; cursor:pointer; min-height:88px; transition: border-color .15s, background .15s, color .15s, transform .15s; }
.manage-date-pill:hover { border-color:var(--accent); transform: translateY(-1px); }
.manage-date-pill.is-selected { background:#211d1a; color:#fff; border-color:#211d1a; box-shadow: 0 4px 12px rgba(33,29,26,.18); }
.manage-date-pill.is-selected .pill-weekday, .manage-date-pill.is-selected .pill-month, .manage-date-pill.is-selected .pill-count { color:#cfc6bd; }
.pill-weekday { font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:#7b736c; }
.pill-day { font:700 22px/1 var(--font); }
.pill-month { font-size:11px; color:#4a443f; }
.pill-count { font-size:10.5px; color:#9a938c; margin-top:2px; }
.manage-time-grid { display:flex; flex-wrap:wrap; gap:8px; }
.manage-time-chip { min-width:64px; padding:10px 12px; text-align:center; background:#fff; border:1px solid #d7d0c8; border-radius:999px; font-size:13px; font-weight:500; cursor:pointer; transition: all .15s; }
.manage-time-chip:hover { border-color:var(--brand); }
.manage-time-chip.is-active { background:#211d1a; color:#fff; border-color:#211d1a; }
.manage-preview { display:flex; gap:12px; align-items:center; margin-top:14px; padding:12px 14px; background:#eef6f1; border:1px solid #cfe4d8; border-radius:10px; font-size:13px; }
.manage-preview-icon { flex:none; width:28px; height:28px; display:grid; place-items:center; border-radius:50%; background:var(--ok); color:#fff; font-weight:700; }
.manage-preview strong { display:block; }
.manage-preview span { display:block; font-size:12px; color:var(--muted); margin-top:2px; }
.manage-submit { margin-top:14px; min-height:44px; font-weight:700; }
.manage-submit:disabled { opacity:.45; cursor:not-allowed; }
.manage-empty { font-size:13px; color:var(--muted); line-height:1.6; }
.manage-help { font-size:12px; color:#9a938c; margin-top:8px; text-align:center; }
.manage-code { font:600 12px ui-monospace, monospace; background:#f1ece6; padding:2px 6px; border-radius:6px; }
.manage-help-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
.manage-link-note { margin-top:12px; font-size:11.5px; color:#9a938c; line-height:1.5; }
.btn--primary { background:#211d1a; border-color:#211d1a; color:#fff; }
.btn--primary:hover { background:#3a342f; border-color:#3a342f; }
.btn--danger { color:#9b2f2f; border-color:#e8c4c4; }
.btn--danger:hover { background:#fbeeee; border-color:#d9a8a8; }
.btn--ghost.btn--sm_manage_copy { font-size:12.5px; border-style:dashed; }
.manage-foot-link { text-align:center; font-size:13px; margin-top:8px; }
.manage-foot-link a { color:var(--brand); }
@media (max-width: 860px) {
  .manage-grid { grid-template-columns: 1fr; }
  .manage-hero { grid-template-columns: 68px 1fr; gap:12px; }
  .manage-hero-num { font-size:26px; }
  .manage-topbar { flex-wrap:wrap; }
}
@media (max-width: 520px) {
  .auth-body { padding:12px 10px; }
  .manage-hero { padding:14px; }
  .manage-card { padding:14px; }
  .manage-date-pill { flex:0 0 78px; min-height:82px; }
}
</style>

<script>
(function() {
  const datePills = document.querySelectorAll('.manage-date-pill');
  const timeGrid = document.getElementById('timeGrid');
  const timeFieldset = document.getElementById('timeFieldset');
  const dateInput = document.getElementById('reschedule-date');
  const timeInput = document.getElementById('reschedule-time');
  const preview = document.getElementById('reschedulePreview');
  const previewDate = document.getElementById('previewDate');
  const previewTime = document.getElementById('previewTime');
  const submitBtn = document.getElementById('rescheduleSubmit');
  const copyBtn = document.getElementById('copyLinkBtn');
  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const url = location.href;
      try { await navigator.clipboard.writeText(url); copyBtn.textContent = 'Copied ✓'; setTimeout(()=>copyBtn.textContent='Copy link',1800);} catch { prompt('Copy this link:', url); }
    });
  }
  if (!datePills.length || !timeGrid || !dateInput) return;
  let selectedDate = '';
  function renderTimes(slots) {
    timeGrid.innerHTML = '';
    slots.forEach((t) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'manage-time-chip';
      btn.textContent = t;
      btn.setAttribute('role','radio');
      btn.setAttribute('aria-checked','false');
      btn.addEventListener('click', () => {
        timeGrid.querySelectorAll('.manage-time-chip').forEach(c=>{c.classList.remove('is-active'); c.setAttribute('aria-checked','false');});
        btn.classList.add('is-active'); btn.setAttribute('aria-checked','true');
        timeInput.value = t; previewTime.textContent = t; preview.hidden = false;
        submitBtn.disabled = false; submitBtn.textContent = 'Reschedule to ' + selectedDate + ' at ' + t;
      });
      timeGrid.appendChild(btn);
    });
    timeFieldset.hidden = false;
  }
  datePills.forEach(pill => {
    pill.addEventListener('click', () => {
      datePills.forEach(p=>{p.classList.remove('is-selected'); p.setAttribute('aria-checked','false');});
      pill.classList.add('is-selected'); pill.setAttribute('aria-checked','true');
      selectedDate = pill.getAttribute('data-date') || '';
      const slotsAttr = pill.getAttribute('data-slots') || '';
      const slots = slotsAttr ? slotsAttr.split(',') : [];
      dateInput.value = selectedDate; timeInput.value = '';
      const d = pill.querySelector('.pill-day')?.textContent || '';
      const m = pill.querySelector('.pill-month')?.textContent || '';
      const w = pill.querySelector('.pill-weekday')?.textContent || '';
      previewDate.textContent = w + ' ' + d + ' ' + m;
      preview.hidden = true; submitBtn.disabled = true; submitBtn.textContent = 'Choose a time';
      renderTimes(slots);
      // scroll time into view on mobile
      if (window.innerWidth < 860) timeFieldset.scrollIntoView({behavior:'smooth', block:'nearest'});
    });
  });
  if (datePills[0]) datePills[0].click();
})();
</script>
