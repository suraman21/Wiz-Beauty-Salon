<?php
/**
 * The page behind the link we email a customer.
 * Redesigned to Design OS + Research Report standard.
 *
 * No account, no password: the signed link is the key. Everything here is
 * written for somebody standing in a queue on a phone.
 *
 * @var array<string, mixed> $booking
 * @var string $token
 * @var list<array{date: string, open: bool, reason: string|null, slots: list<string>}> $days
 * @var string|null $error
 * @var string|null $success
 */

use Wiz\Domain\Enums\BookingStatus;

$status = BookingStatus::tryFrom((string) $booking['status']);
$date = strtotime((string) $booking['booking_date']);
$start = substr((string) $booking['start_time'], 0, 5);
$end = substr((string) $booking['end_time'], 0, 5);
$canManage = $status !== null && in_array($status, [BookingStatus::Requested, BookingStatus::Confirmed], true);
$statusLabel = $status?->label() ?? ucfirst((string) $booking['status']);
$statusClass = match ((string) ($status?->value ?? $booking['status'])) {
    'confirmed' => 'status--confirmed',
    'requested' => 'status--requested',
    'cancelled' => 'status--cancelled',
    'declined' => 'status--cancelled',
    default => 'status--muted',
};
$hasSlots = false;
foreach ($days as $d) { if ($d['slots'] !== []) { $hasSlots = true; break; } }
?>

<section class="card card--auth card--wide manage-page">
    <div class="manage-head">
        <p class="overline-soft"><?= e((string) $booking['reference']) ?></p>
        <span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
    </div>

    <h1 class="card__title">
        <?= e($statusLabel) ?> &middot; <?= e($booking['service_name']) ?>
    </h1>
    <p class="card__sub">
        <?= e(date('l, j F Y', $date === false ? time() : $date)) ?>
        &middot; <?= e($start) ?>–<?= e($end) ?>
    </p>
    <p class="manage-policy-note">Free to reschedule or cancel until 24 hours before your time. After that, please call the salon.</p>

    <?php if (!empty($success)): ?>
        <div class="alert alert--success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <dl class="detail-grid detail-grid--tight manage-details">
        <div>
            <dt>Service</dt>
            <dd><?= e($booking['service_name']) ?></dd>
        </div>
        <div>
            <dt>When</dt>
            <dd><?= e(date('l j F Y', $date === false ? time() : $date)) ?> at <?= e($start) ?></dd>
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
    </dl>

    <?php if ($canManage): ?>

        <div class="manage-card manage-card--action">
            <div class="manage-card-head">
                <h2>Reschedule</h2>
                <span class="manage-card-hint">Pick a new day and time — your current slot is freed as soon as you confirm</span>
            </div>

            <?php if (!$hasSlots): ?>
                <p class="empty">No other times are free in the next two weeks. Please call the salon and we’ll find you a time.</p>
            <?php else: ?>
                <form method="post" action="/booking/manage/<?= e($token) ?>/reschedule" id="rescheduleForm" class="manage-reschedule-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="date" id="reschedule-date" value="">
                    <input type="hidden" name="time" id="reschedule-time" value="">

                    <div class="manage-date-grid" role="radiogroup" aria-label="Choose a new date">
                        <?php foreach ($days as $day): ?>
                            <?php if ($day['slots'] === []) { continue; } ?>
                            <?php $d = (int) strtotime($day['date']); ?>
                            <button type="button" class="manage-date-pill" data-date="<?= e($day['date']) ?>" data-slots="<?= e(implode(',', $day['slots'])) ?>" role="radio" aria-checked="false">
                                <span class="pill-weekday"><?= e(date('D', $d)) ?></span>
                                <strong class="pill-day"><?= e(date('j', $d)) ?></strong>
                                <span class="pill-month"><?= e(date('M', $d)) ?></span>
                                <span class="pill-count"><?= count($day['slots']) ?> times</span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div id="timeGrid" class="manage-time-grid" role="radiogroup" aria-label="Choose a new time" hidden>
                        <!-- Filled by JS -->
                    </div>

                    <div id="reschedulePreview" class="manage-preview" hidden>
                        <p><strong>Move to <span id="previewDate"></span> at <span id="previewTime"></span></strong><br>
                        <small>Your current appointment will be updated. The old time goes back to the salon.</small></p>
                    </div>

                    <button type="submit" id="rescheduleSubmit" class="btn btn--primary btn--block" disabled>Choose a day first</button>
                    <p class="manage-help">Times are in Addis Ababa time. Days the salon is closed are not shown.</p>
                </form>
            <?php endif; ?>
        </div>

        <div class="manage-card manage-card--danger">
            <div class="manage-card-head">
                <h2>Cancel this appointment</h2>
                <span class="manage-card-hint">The time goes back to the salon — you can always book again</span>
            </div>
            <p class="card__sub" style="margin-top: 8px;">Need to cancel? Do it here. If you’re unsure, try rescheduling first.</p>
            <form method="post" action="/booking/manage/<?= e($token) ?>/cancel"
                  onsubmit="return confirm('Cancel this appointment? This cannot be undone.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--block btn--danger">Cancel appointment</button>
            </form>
        </div>

    <?php else: ?>
        <div class="manage-card manage-card--muted">
            <p class="empty" style="margin: 0;">
                This appointment can no longer be changed here (status: <?= e($statusLabel) ?>). If something is wrong, call the salon.
            </p>
            <p class="manage-again">
                <a class="btn btn--ghost btn--block" href="/booking">Book another time</a>
            </p>
        </div>
    <?php endif; ?>

    <div class="manage-help-footer">
        <p><strong>Need help?</strong> Call or WhatsApp the salon — give your reference <code><?= e((string) $booking['reference']) ?></code> and we’ll help you change it.</p>
        <p class="manage-contact">
            <?php if (!empty($booking['customer_phone'])): ?>
                <a href="https://wa.me/<?= e(preg_replace('/[^\\d]/', '', (string) $booking['customer_phone'])) ?>" target="_blank" rel="noreferrer">WhatsApp</a> ·
            <?php endif; ?>
            <a href="/contact">Contact page</a> · <a href="/">Home</a>
        </p>
        <p class="manage-link-note">This link is signed and valid for 24 hours. Don’t share it — anyone with the link can manage this booking.</p>
    </div>
</section>

<style>
/* Manage page — professional redesign (scoped to .manage-page) */
.manage-page .manage-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:6px; }
.status-badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
.status--requested { background:#fff7e6; color:#8a5a00; border:1px solid #ffda8a; }
.status--confirmed { background:#e6f7ed; color:#0a6b2e; border:1px solid #a8e0b8; }
.status--cancelled { background:#fce8e8; color:#8a1a1a; border:1px solid #f0b0b0; }
.status--muted { background:#f2f2f2; color:#666; border:1px solid #ddd; }
.manage-policy-note { margin-top:8px; font-size:12px; color:#6b6b6b; line-height:1.5; }
.manage-details { margin-top:16px; }
.manage-card { margin-top:20px; padding:18px; background:#fff; border:1px solid #e8e8e8; border-radius:12px; }
.manage-card--action { border-color:#d9e8ff; background:#f8fbff; }
.manage-card--danger { border-color:#f0d0d0; background:#fff8f8; }
.manage-card--muted { background:#f9f9f9; }
.manage-card-head h2 { font-size:15px; font-weight:700; margin:0; }
.manage-card-hint { display:block; font-size:12px; color:#6b6b6b; margin-top:4px; line-height:1.4; }
.manage-date-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap:8px; margin:16px 0 12px; }
.manage-date-pill { display:flex; flex-direction:column; align-items:center; gap:2px; padding:10px 6px; background:#fff; border:1px solid #ddd; border-radius:8px; cursor:pointer; transition: all .2s; min-height:82px; }
.manage-date-pill:hover { border-color:#8ab4ff; }
.manage-date-pill.is-selected { background:#0f172a; color:#fff; border-color:#0f172a; }
.manage-date-pill.is-selected .pill-weekday, .manage-date-pill.is-selected .pill-month, .manage-date-pill.is-selected .pill-count { color:rgba(255,255,255,.85); }
.pill-weekday { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#888; }
.pill-day { font:600 22px/1 system-ui, sans-serif; }
.pill-month { font-size:11px; color:#666; }
.pill-count { font-size:10px; color:#999; margin-top:2px; }
.manage-time-grid { display:flex; flex-wrap:wrap; gap:8px; margin:12px 0; }
.manage-time-chip { padding:8px 12px; background:#fff; border:1px solid #ddd; border-radius:999px; font-size:13px; cursor:pointer; transition: all .2s; }
.manage-time-chip:hover { border-color:#8ab4ff; }
.manage-time-chip.is-active { background:#0f172a; color:#fff; border-color:#0f172a; }
.manage-preview { margin:12px 0; padding:10px 12px; background:#fff; border:1px solid #d9e8ff; border-radius:8px; font-size:13px; line-height:1.5; }
.manage-help { font-size:11px; color:#888; margin-top:8px; }
.manage-help-footer { margin-top:22px; padding-top:16px; border-top:1px solid #eee; font-size:13px; line-height:1.6; color:#555; }
.manage-contact { margin-top:6px; }
.manage-contact a { color:#0f172a; }
.manage-link-note { margin-top:8px; font-size:11px; color:#999; }
#rescheduleSubmit:disabled { opacity:.5; cursor:not-allowed; }
.btn--primary { background:#0f172a; color:#fff; border-color:#0f172a; }
.btn--primary:hover { background:#1e293b; }
.btn--danger { color:#8a1a1a; border-color:#f0b0b0; }
.btn--danger:hover { background:#fce8e8; border-color:#e8a0a0; }
</style>

<script>
(function() {
  const datePills = document.querySelectorAll('.manage-date-pill');
  const timeGrid = document.getElementById('timeGrid');
  const dateInput = document.getElementById('reschedule-date');
  const timeInput = document.getElementById('reschedule-time');
  const preview = document.getElementById('reschedulePreview');
  const previewDate = document.getElementById('previewDate');
  const previewTime = document.getElementById('previewTime');
  const submitBtn = document.getElementById('rescheduleSubmit');
  if (!datePills.length || !timeGrid || !dateInput) return;
  let selectedDate = '';
  let selectedSlots = [];

  function renderTimes(slots) {
    timeGrid.innerHTML = '';
    timeGrid.hidden = false;
    slots.forEach((t, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'manage-time-chip';
      btn.textContent = t;
      btn.setAttribute('role','radio');
      btn.setAttribute('aria-checked','false');
      btn.addEventListener('click', () => {
        timeGrid.querySelectorAll('.manage-time-chip').forEach(c=>{c.classList.remove('is-active'); c.setAttribute('aria-checked','false');});
        btn.classList.add('is-active');
        btn.setAttribute('aria-checked','true');
        timeInput.value = t;
        previewTime.textContent = t;
        preview.hidden = false;
        submitBtn.disabled = false;
        submitBtn.textContent = 'Reschedule to ' + selectedDate + ' at ' + t;
      });
      timeGrid.appendChild(btn);
    });
  }

  datePills.forEach(pill => {
    pill.addEventListener('click', () => {
      datePills.forEach(p=>{p.classList.remove('is-selected'); p.setAttribute('aria-checked','false');});
      pill.classList.add('is-selected');
      pill.setAttribute('aria-checked','true');
      selectedDate = pill.getAttribute('data-date') || '';
      const slotsAttr = pill.getAttribute('data-slots') || '';
      selectedSlots = slotsAttr ? slotsAttr.split(',') : [];
      dateInput.value = selectedDate;
      timeInput.value = '';
      previewDate.textContent = pill.textContent.replace(/\s+/g,' ').trim();
      // Extract human date from pill
      const d = pill.querySelector('.pill-day')?.textContent || '';
      const m = pill.querySelector('.pill-month')?.textContent || '';
      const w = pill.querySelector('.pill-weekday')?.textContent || '';
      previewDate.textContent = w + ' ' + d + ' ' + m;
      preview.hidden = true;
      submitBtn.disabled = true;
      submitBtn.textContent = 'Choose a time';
      renderTimes(selectedSlots);
    });
  });

  // Auto-select first date for convenience
  if (datePills[0]) datePills[0].click();
})();
</script>
