<?php
/**
 * The page behind the link we email a customer.
 * Layout v2 — WIZ editorial, not admin-auth. Focus: one thumb, one decision.
 *
 * Structure: hero summary + 2-col grid (main actions | side help) → stacks on mobile.
 * Tokens are 24h, no account. Every tap is 44px+, focus-visible, radiogroup semantics.
 *
 * @var array<string, mixed> $booking
 * @var array{hours:int,has_cut_off:bool,allowed:bool,can_manage:bool,deadline:string,
 *     deadline_label:string,deadline_iso:string,summary:string,reason:string} $window
 * @var \Wiz\Support\ChangePolicy $policy
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

// The day as the customer reads it: Ethiopian calendar, Gregorian beside it.
// The stored value is Gregorian — only the reading changes.
$bookingDate = (string) $booking['booking_date'];
$ethiopian = \Wiz\Support\EthiopianDate::toEthiopian($bookingDate);
$heroDate = $ethiopian === null
    ? $bookingDate
    : \Wiz\Support\EthiopianDate::format($bookingDate, 'long') . ' · ' . date('j M Y', (int) $dateTs);
$heroDayNum = $ethiopian === null ? '–' : (string) $ethiopian['day'];
$heroMonth = $ethiopian === null ? '' : \Wiz\Support\EthiopianDate::MONTHS_SHORT[$ethiopian['month']];
$heroWeekday = $dateTs !== false ? date('D', $dateTs) : '';
// Times: Ethiopian clock first, the 24-hour time the salon sets in brackets.
$startLabel = ethiopian_clock($start);
$endLabel = ethiopian_clock($end);
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
    <!-- Where you are. The salon's own navigation is in the page header above,
         so this bar carries only what belongs to this booking. -->
    <div class="manage-topbar">
        <div class="manage-topbar-left">
            <span class="manage-eyebrow">Your booking</span>
            <span class="manage-ref"><?= e((string) $booking['reference']) ?></span>
        </div>
        <div class="manage-topbar-right">
            <span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
            <?php if ($window['can_manage']): ?>
                <span class="manage-chip">
                    <?= e($window['allowed'] ? $policy->chip() : 'Changes closed') ?>
                </span>
            <?php endif; ?>
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
            <p class="manage-hero-when"><?= e($heroDate) ?> · <?= e($startLabel) ?>–<?= e($endLabel) ?><?php if ($duration) echo ' · ' . $duration . ' min'; ?><?php if ($priceText) echo ' · ' . $priceText; ?></p>
            <p class="manage-hero-policy"><?= e($window['summary']) ?></p>
        </div>
    </header>

    <?php
    // One statement of the rule, before any button that depends on it. The
    // sentence comes from the salon's setting, so a 48-hour window reads as 48.
    $policyTitle = !$window['can_manage']
        ? 'This booking can no longer be changed'
        : (!$window['has_cut_off']
            ? 'Change or cancel any time before your appointment'
            : ($window['allowed']
                ? 'You can still change or cancel this booking'
                : 'The change window has closed'));
    ?>
    <section class="manage-policy <?= $window['allowed'] ? 'is-open' : 'is-closed' ?>">
        <span class="manage-policy__icon" aria-hidden="true"><?= $window['allowed'] ? '✓' : '!' ?></span>
        <div class="manage-policy__text">
            <p class="manage-policy__title"><?= e($policyTitle) ?></p>
            <p class="manage-policy__body">
                <?= e($window['allowed'] ? $window['summary'] : $window['reason']) ?>
            </p>
            <?php if ($window['can_manage'] && $window['has_cut_off']): ?>
                <p class="manage-policy__deadline">
                    <?= $window['allowed'] ? 'Window closes' : 'Window closed' ?>:
                    <strong><?= e($window['deadline']) ?></strong>
                </p>
            <?php endif; ?>
            <a class="manage-policy__link" href="<?= e(site_url('/booking/information')) ?>">
                Read the booking terms — የቦታ ማስያዣ ደንቦች
            </a>
        </div>
    </section>

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
                        <dd><?= e($heroDate) ?> at <?= e($startLabel) ?><?php if ($duration) echo ' · ' . $duration . ' min'; ?></dd>
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

            <?php if ($window['allowed']): ?>
                <section class="manage-card manage-card--action" aria-labelledby="reschedule-heading">
                    <div class="manage-card-head">
                        <h2 id="reschedule-heading">Reschedule</h2>
                        <p class="manage-card-hint">Pick a new day — times appear. Your current slot is freed when you confirm.</p>
                    </div>

                    <?php if (!$hasSlots): ?>
                        <p class="manage-empty">No other times are free in the next two weeks. Call the salon and we’ll find you a time.</p>
                        <p style="margin-top:12px;"><a class="btn btn--ghost" href="<?= e(site_url('/contact')) ?>">Contact the salon</a></p>
                    <?php else: ?>
                        <form method="post" action="/booking/manage/<?= e($token) ?>/reschedule" id="rescheduleForm" class="manage-reschedule-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="date" id="reschedule-date" value="">
                            <input type="hidden" name="time" id="reschedule-time" value="">

                            <!-- Date pills: scrollable row on mobile, grid on desktop -->
                            <fieldset class="manage-fieldset">
                                <legend class="manage-legend">Choose a new date</legend>
                                <p class="manage-scroll-hint" aria-hidden="true">Swipe for more dates →</p>
                                <div class="manage-date-row" role="radiogroup" aria-label="Choose a new date">
                                    <?php foreach ($days as $day): ?>
                                        <?php if ($day['slots'] === []) { continue; } ?>
                                        <?php
                                        $d = (int) strtotime($day['date']);
                                        $dayEc = \Wiz\Support\EthiopianDate::toEthiopian($day['date']);
                                        // Slot labels are rendered here, in PHP, so the page keeps
                                        // one Ethiopian implementation instead of a second one in JS.
                                        $slotLabels = [];
                                        foreach ($day['slots'] as $slot) {
                                            $slotLabels[$slot] = ethiopian_clock($slot);
                                        }
                                        ?>
                                        <button type="button" class="manage-date-pill" data-date="<?= e($day['date']) ?>" data-slots="<?= e(implode(',', $day['slots'])) ?>" data-slot-labels="<?= e((string) json_encode($slotLabels, JSON_UNESCAPED_UNICODE)) ?>" role="radio" aria-checked="false">
                                            <span class="pill-weekday"><?= e(date('D', $d)) ?></span>
                                            <strong class="pill-day"><?= e($dayEc === null ? date('j', $d) : (string) $dayEc['day']) ?></strong>
                                            <span class="pill-month"><?= e($dayEc === null ? date('M', $d) : \Wiz\Support\EthiopianDate::MONTHS_SHORT[$dayEc['month']]) ?></span>
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
            <?php elseif ($canManage): ?>
                <!-- The window has passed: no form is shown, because none would be
                     accepted. Say what happened and where to go instead. -->
                <section class="manage-card manage-card--closed" aria-labelledby="closed-heading">
                    <h2 class="manage-card-title" id="closed-heading">Changing this appointment</h2>
                    <p class="manage-empty" style="margin-top:8px;">
                        The salon can still move it for you — quote your reference
                        <code class="manage-code"><?= e((string) $booking['reference']) ?></code>.
                    </p>
                    <div class="manage-btn-stack">
                        <a class="btn btn--primary btn--block" href="<?= e(site_url('/contact')) ?>">Contact the salon</a>
                        <a class="btn btn--ghost btn--block" href="<?= e(site_url('/booking')) ?>">Book a different time</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="manage-card manage-card--muted">
                    <p class="manage-empty" style="margin:0;">This appointment can’t be changed here (status: <?= e($statusLabel) ?>). If you need help, call the salon.</p>
                    <p style="margin-top:14px;"><a class="btn btn--ghost btn--block" href="<?= e(site_url('/booking')) ?>">Book another time</a></p>
                </section>
            <?php endif; ?>
        </div>

        <!-- Side column: cancel + help + link safety -->
        <aside class="manage-side">
            <?php if ($window['allowed']): ?>
                <section class="manage-card manage-card--soft manage-card--cancel">
                    <h2 class="manage-card-title manage-card-title--sm">Cancel this appointment</h2>
                    <p class="manage-card-hint">
                        The time goes back to the salon and anyone can book it. You can always
                        book again.
                    </p>
                    <form method="post" action="/booking/manage/<?= e($token) ?>/cancel"
                          onsubmit="return confirm('Cancel this appointment? This cannot be undone.');"
                          class="manage-cancel-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--danger-solid btn--block">
                            <span class="btn__icon" aria-hidden="true">✕</span>
                            Cancel appointment
                        </button>
                    </form>
                    <p class="manage-help manage-help--left">
                        Cancelling is immediate. If you only need another time, reschedule instead.
                    </p>
                </section>
            <?php endif; ?>

            <section class="manage-card manage-card--help">
                <h2 class="manage-card-title manage-card-title--sm">Need help?</h2>
                <p>Give your reference <code class="manage-code"><?= e((string) $booking['reference']) ?></code> and we’ll help you change it.</p>
                <div class="manage-help-actions">
                    <?php if (!empty($booking['customer_phone'])): ?>
                        <a class="btn btn--ghost btn--sm" href="https://wa.me/<?= e(preg_replace('/[^\\d]/', '', (string) $booking['customer_phone'])) ?>" target="_blank" rel="noreferrer">WhatsApp</a>
                    <?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(site_url('/contact')) ?>">Contact</a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(site_url('/')) ?>">Home</a>
                </div>
                <p class="manage-link-note">
                    Private link · it expires 24 hours after it was sent, and anyone who has it can
                    manage this booking — don’t forward it. Need it again later?
                    <a href="<?= e(site_url('/booking/recover')) ?>">Find your booking</a>.
                </p>
                <a class="manage-terms-link" href="<?= e(site_url('/booking/information')) ?>">
                    Booking terms and conditions — ደንቦች
                </a>
                <button type="button" class="btn btn--ghost btn--sm btn--dashed" id="copyLinkBtn">Copy link</button>
            </section>

            <p class="manage-foot-link">
                <a href="<?= e(site_url('/booking')) ?>">Book another appointment →</a>
            </p>
        </aside>
    </div>
</div>

<style>
/* ==========================================================================
   The customer's page
   --------------------------------------------------------------------------
   One column that never needs a sideways scroll, then a main/side split when
   there is room for it. Every rule is scoped to .manage-wrap so nothing here
   can reach into the panel, and no width is hard-coded to a desktop.
   ========================================================================== */
.manage-wrap { width: 100%; min-width: 0; }
.manage-wrap *,
.manage-wrap *::before,
.manage-wrap *::after { box-sizing: border-box; }
.manage-wrap img,
.manage-wrap svg { max-width: 100%; height: auto; }
/* A styled display beats the browser's [hidden] rule, so the empty "Move to…"
   preview kept appearing before a time was picked. This restores it. */
.manage-wrap [hidden] { display: none !important; }
.manage-wrap p { overflow-wrap: break-word; }

/* --- where you are ------------------------------------------------------- */
.manage-topbar {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
    gap: 10px 12px; padding: 2px 0 14px;
    border-bottom: 1px solid #e7e2dc; margin-bottom: 16px;
}
.manage-topbar-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; min-width: 0; }
.manage-topbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; min-width: 0; }
.manage-eyebrow {
    font-size: 10.5px; font-weight: 700; letter-spacing: .16em;
    text-transform: uppercase; color: #8a827a;
}
.manage-ref {
    font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #4a443f;
    background: #fff; border: 1px solid #e7e2dc; padding: 5px 10px; border-radius: 999px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.status-badge {
    display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
}
.status--requested { background: #fff7e6; color: #8a5a00; border: 1px solid #ffda8a; }
.status--confirmed { background: #e6f7ed; color: #0a6b2e; border: 1px solid #a8e0b8; }
.status--cancelled { background: #fce8e8; color: #8a1a1a; border: 1px solid #f0b0b0; }
.status--muted     { background: #f2f2f2; color: #666;    border: 1px solid #ddd; }
.manage-chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 999px;
    font-size: 11.5px; font-weight: 600; color: #4a443f;
    background: #f4efe9; border: 1px solid #e7e2dc;
}

/* --- messages ------------------------------------------------------------ */
.manage-alert {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 12px 14px; border-radius: 10px; font-size: 13.5px; line-height: 1.5;
    margin-bottom: 16px;
}
.manage-alert--success { background: #eef6f1; border: 1px solid #cfe4d8; color: #245c3c; }
.manage-alert--error   { background: #fbeeee; border: 1px solid #efd3d3; color: #8a1a1a; }
.manage-alert-icon {
    flex: none; width: 22px; height: 22px; display: grid; place-items: center;
    border-radius: 50%; background: currentColor; color: #fff; font-size: 12px; font-weight: 700;
}

/* --- the appointment itself ---------------------------------------------- */
.manage-hero {
    display: grid; grid-template-columns: 84px minmax(0, 1fr); gap: 16px; align-items: center;
    padding: 16px; background: #fff; border: 1px solid #e7e2dc; border-radius: 14px;
    box-shadow: 0 1px 2px rgba(29,26,24,.04), 0 6px 20px rgba(29,26,24,.05);
}
.manage-hero-date { text-align: center; background: #211d1a; color: #fff; border-radius: 10px; padding: 10px 8px; }
.manage-hero-num { display: block; font: 700 30px/1 var(--font, inherit); }
.manage-hero-meta {
    display: block; margin-top: 4px; font-size: 10.5px; letter-spacing: .08em;
    text-transform: uppercase; color: #cfc6bd;
}
.manage-hero-main { min-width: 0; }
.manage-hero-title { font: 600 18px/1.25 var(--font, inherit); letter-spacing: -.01em; }
.manage-hero-when { margin-top: 6px; font-size: 13px; line-height: 1.5; color: #4a443f; }
.manage-hero-policy { margin-top: 6px; font-size: 12px; line-height: 1.5; color: #8a827a; }

/* --- the window, stated once --------------------------------------------- */
.manage-policy {
    display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 4px 14px;
    align-items: start; padding: 15px 16px; margin-top: 14px;
    border: 1px solid #cfe4d8; background: #f2f8f4; border-radius: 14px;
}
.manage-policy.is-closed { border-color: #efd3d3; background: #fdf4f4; }
.manage-policy__icon {
    grid-row: 1 / span 3; width: 30px; height: 30px; border-radius: 50%;
    display: grid; place-items: center; background: #0a6b2e; color: #fff;
    font-size: 14px; font-weight: 700;
}
.manage-policy.is-closed .manage-policy__icon { background: #9b2f2f; }
.manage-policy__text { min-width: 0; }
.manage-policy__title { font: 650 14.5px/1.35 var(--font, inherit); color: #1c3d2a; }
.manage-policy.is-closed .manage-policy__title { color: #7f2525; }
.manage-policy__body { margin-top: 4px; font-size: 13px; line-height: 1.55; color: #3c4a42; }
.manage-policy.is-closed .manage-policy__body { color: #6b3a3a; }
.manage-policy__deadline {
    margin-top: 8px; font-size: 12.5px; color: #245c3c;
    display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 5px;
}
.manage-policy.is-closed .manage-policy__deadline { color: #8a1a1a; }
.manage-policy__deadline strong { font-weight: 700; }
.manage-policy__link {
    display: block; width: fit-content; margin-top: 10px; font-size: 12.5px; font-weight: 600;
    color: #245c3c; text-decoration: underline; text-underline-offset: 3px;
    min-height: 24px;
}
.manage-policy.is-closed .manage-policy__link { color: #8a1a1a; }

/* --- layout -------------------------------------------------------------- */
.manage-grid { display: grid; grid-template-columns: 1fr; gap: 16px; margin-top: 16px; align-items: start; }
.manage-main, .manage-side { display: grid; gap: 16px; min-width: 0; }
.manage-card {
    background: #fff; border: 1px solid #e7e2dc; border-radius: 14px; padding: 18px;
    min-width: 0; box-shadow: 0 1px 2px rgba(29,26,24,.04), 0 6px 20px rgba(29,26,24,.05);
}
.manage-card-title { font: 650 15px/1.3 var(--font, inherit); letter-spacing: -.01em; }
.manage-card-title--sm { font-size: 14px; }
.manage-card-head h2 { font: 650 15px var(--font, inherit); }
.manage-card-hint { margin-top: 5px; font-size: 12.5px; color: #8a827a; line-height: 1.55; }
.manage-card--action { border-color: #dbe7f7; }
.manage-card--soft { background: #fdfcfa; }
.manage-card--cancel { border-color: #eddcdc; }
.manage-card--closed { border-color: #e7e2dc; background: #fbfaf8; }
.manage-card--muted { background: #fbfaf8; }

/* --- details ------------------------------------------------------------- */
.manage-details { display: grid; gap: 2px; margin-top: 14px; }
.manage-details > div:not(.manage-details-full) {
    display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 16px;
    padding: 10px 0; border-bottom: 1px solid #f1ece6; font-size: 13.5px;
}
.manage-details > div:last-child { border-bottom: 0; }
.manage-details dt { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #8a827a; }
.manage-details dd { min-width: 0; text-align: right; color: #211d1a; font-weight: 500; overflow-wrap: anywhere; }
.manage-details dd a { color: #a63b6e; }
.manage-detail-sub { color: #8a827a; font-weight: 400; margin-left: 6px; }
.manage-details-full { padding: 10px 0; border-bottom: 1px solid #f1ece6; }
.manage-details-full dt { margin-bottom: 4px; }
.manage-details-full dd { text-align: left; font-weight: 400; line-height: 1.55; }

/* --- reschedule ---------------------------------------------------------- */
.manage-reschedule-form { min-width: 0; }
.manage-fieldset { border: 0; padding: 0; margin: 14px 0 0; min-width: 0; }
.manage-legend {
    font: 600 12.5px var(--font, inherit); letter-spacing: .06em; text-transform: uppercase;
    color: #4a443f; margin-bottom: 8px; padding: 0;
}
.manage-legend-sub { font-weight: 400; text-transform: none; letter-spacing: 0; color: #8a827a; margin-left: 6px; }
.manage-scroll-hint { display: block; font-size: 11.5px; color: #9a938c; margin-bottom: 6px; }
.manage-date-row {
    display: flex; gap: 8px; overflow-x: auto; padding: 2px 2px 8px;
    scrollbar-width: thin; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory;
    max-width: 100%;
}
.manage-date-pill {
    scroll-snap-align: start; flex: 0 0 86px; display: flex; flex-direction: column;
    align-items: center; gap: 2px; padding: 10px 6px; background: #fff;
    border: 1px solid #d7d0c8; border-radius: 12px; cursor: pointer; min-height: 88px;
    transition: border-color .15s, background .15s, color .15s, transform .15s;
}
.manage-date-pill:hover { border-color: #a63b6e; transform: translateY(-1px); }
.manage-date-pill.is-selected { background: #211d1a; color: #fff; border-color: #211d1a; box-shadow: 0 4px 12px rgba(33,29,26,.18); }
.manage-date-pill.is-selected .pill-weekday,
.manage-date-pill.is-selected .pill-month,
.manage-date-pill.is-selected .pill-count { color: #cfc6bd; }
.pill-weekday { font-size: 10px; letter-spacing: .06em; text-transform: uppercase; color: #7b736c; }
.pill-day { font: 700 22px/1 var(--font, inherit); }
.pill-month { font-size: 11px; color: #4a443f; }
.pill-count { font-size: 10.5px; color: #9a938c; margin-top: 2px; }
.manage-time-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.manage-time-chip {
    min-width: 64px; min-height: 40px; padding: 9px 12px; text-align: center; background: #fff;
    border: 1px solid #d7d0c8; border-radius: 999px; font-size: 13px; font-weight: 500;
    cursor: pointer; transition: all .15s;
}
.manage-time-chip:hover { border-color: #a63b6e; }
.manage-time-chip.is-active { background: #211d1a; color: #fff; border-color: #211d1a; }
.manage-preview {
    display: flex; gap: 12px; align-items: center; margin-top: 14px; padding: 12px 14px;
    background: #eef6f1; border: 1px solid #cfe4d8; border-radius: 10px; font-size: 13px;
}
.manage-preview-icon {
    flex: none; width: 28px; height: 28px; display: grid; place-items: center;
    border-radius: 50%; background: #0a6b2e; color: #fff; font-weight: 700;
}
.manage-preview strong { display: block; }
.manage-preview span { display: block; font-size: 12px; color: #8a827a; margin-top: 2px; }
.manage-submit { margin-top: 14px; min-height: 46px; font-weight: 700; }
.manage-submit:disabled { opacity: .45; cursor: not-allowed; }

/* --- buttons ------------------------------------------------------------- */
.btn--block { display: block; width: 100%; text-align: center; }
.btn--primary {
    background: #211d1a; border: 1px solid #211d1a; color: #fff;
    min-height: 46px; display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 18px; border-radius: 10px; font-weight: 650; text-decoration: none;
}
.btn--primary:hover { background: #3a342f; border-color: #3a342f; }

/* The cancel button. Deep red fill, white label, a defined edge and a shadow —
   at every state it must read as a button, not as text. The old rule set the
   label and the fill to the same colour, which is why it looked empty. */
.btn--danger-solid {
    background: #9b2f2f; border: 1px solid #7f2525; color: #fff;
    min-height: 48px; display: inline-flex; align-items: center; justify-content: center;
    gap: 8px; padding: 13px 18px; border-radius: 10px;
    font-weight: 700; letter-spacing: .015em; text-decoration: none; cursor: pointer;
    box-shadow: 0 2px 0 #7f2525, 0 8px 18px rgba(155,47,47,.24);
    transition: background .15s, box-shadow .15s, transform .12s;
}
.btn--danger-solid:hover { background: #7f2525; border-color: #6b1f1f; color: #fff; }
.btn--danger-solid:active { transform: translateY(1px); box-shadow: 0 1px 0 #7f2525; }
.btn--danger-solid:focus-visible { outline: 3px solid #9b2f2f; outline-offset: 2px; }
.btn__icon { font-size: 12px; line-height: 1; }
.manage-cancel-form { margin-top: 12px; }

/* --- small print --------------------------------------------------------- */
.manage-empty { font-size: 13px; color: #8a827a; line-height: 1.6; }
.manage-help { font-size: 12px; color: #9a938c; margin-top: 8px; text-align: center; line-height: 1.55; }
.manage-help--left { text-align: left; }
.manage-code { font: 600 12px ui-monospace, monospace;background: #f1ece6; padding: 2px 6px; border-radius: 6px; overflow-wrap: anywhere; }
.manage-help-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.manage-help-actions .btn { min-height: 40px; }
.manage-link-note { margin-top: 12px; font-size: 11.5px; color: #9a938c; line-height: 1.55; }
.manage-terms-link {
    display: inline-block; margin-top: 10px; font-size: 12.5px; font-weight: 600;
    color: #a63b6e; text-decoration: underline; text-underline-offset: 3px;
}
.btn--dashed { border-style: dashed; width: 100%; margin-top: 10px; min-height: 40px; }
.manage-btn-stack { display: grid; gap: 10px; margin-top: 16px; }
.manage-btn-stack .btn { min-height: 46px; display: inline-flex; align-items: center; justify-content: center; }
.manage-foot-link { text-align: center; font-size: 13px; margin-top: 4px; }
.manage-foot-link a { color: #a63b6e; text-decoration: underline; text-underline-offset: 3px; }

/* --- bigger screens ------------------------------------------------------ */
@media (min-width: 900px) {
    .manage-grid { grid-template-columns: minmax(0, 1.6fr) minmax(0, .95fr); }
    .manage-date-row { flex-wrap: wrap; overflow-x: visible; }
    .manage-scroll-hint { display: none; }
}

/* --- phone --------------------------------------------------------------- */
@media (max-width: 899px) {
    .manage-grid { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 680px) {
    .manage-hero { grid-template-columns: 62px minmax(0, 1fr); gap: 12px; padding: 14px; }
    .manage-hero-num { font-size: 24px; }
    .manage-hero-title { font-size: 16.5px; }
    .manage-card { padding: 15px; }
    .manage-policy { padding: 14px; gap: 4px 12px; }
    .manage-policy__icon { width: 26px; height: 26px; font-size: 12px; }
    /* Stacked label/value rows read better than a squeezed two-column row. */
    .manage-details > div:not(.manage-details-full) { flex-direction: column; align-items: flex-start; }
    .manage-details dd { text-align: left; }
    .manage-date-pill { flex: 0 0 78px; min-height: 84px; }
    .manage-topbar { padding-bottom: 12px; }
}
@media (max-width: 400px) {
    .manage-hero { grid-template-columns: minmax(0, 1fr); }
    .manage-hero-date { display: flex; align-items: center; gap: 10px; justify-content: center; padding: 8px; }
    .manage-hero-num { font-size: 22px; }
    .manage-hero-meta { margin-top: 0; }
    .pill-weekday { letter-spacing: 0; }
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
  let selectedDateLabel = '';
  // The first day is selected without scrolling: a customer opening this page
  // wants to read what they booked before they are dropped into a time grid.
  let firstPaint = true;
  function renderTimes(slots, labels) {
    timeGrid.innerHTML = '';
    slots.forEach((t) => {
      // The Ethiopian label is rendered by PHP and read from the pill, so the
      // Ethiopian clock lives in one place. The value posted stays 24-hour.
      const label = (labels && labels[t]) ? labels[t] : t;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'manage-time-chip';
      btn.textContent = label;
      btn.setAttribute('role','radio');
      btn.setAttribute('aria-checked','false');
      btn.addEventListener('click', () => {
        timeGrid.querySelectorAll('.manage-time-chip').forEach(c=>{c.classList.remove('is-active'); c.setAttribute('aria-checked','false');});
        btn.classList.add('is-active'); btn.setAttribute('aria-checked','true');
        timeInput.value = t; previewTime.textContent = label; preview.hidden = false;
        submitBtn.disabled = false; submitBtn.textContent = 'Reschedule to ' + selectedDateLabel + ' at ' + label;
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
      let labels = {};
      try { labels = JSON.parse(pill.getAttribute('data-slot-labels') || '{}'); } catch (err) { labels = {}; }
      dateInput.value = selectedDate; timeInput.value = '';
      const d = pill.querySelector('.pill-day')?.textContent || '';
      const m = pill.querySelector('.pill-month')?.textContent || '';
      const w = pill.querySelector('.pill-weekday')?.textContent || '';
      selectedDateLabel = w + ' ' + d + ' ' + m;
      previewDate.textContent = selectedDateLabel;
      preview.hidden = true; submitBtn.disabled = true; submitBtn.textContent = 'Choose a time';
      renderTimes(slots, labels);
      // scroll the times into view on mobile — but only after the customer has
      // actually chosen a day, never on page load
      if (!firstPaint && window.innerWidth < 860) {
        timeFieldset.scrollIntoView({behavior:'smooth', block:'nearest'});
      }
      firstPaint = false;
    });
  });
  if (datePills[0]) datePills[0].click();
})();
</script>
