<?php
/**
 * Booking terms: every section, every clause, both languages, on one page.
 *
 * The document is edited where it is read. A person changing clause 3.2 can see
 * 3.1 and 3.3 while they do it, which is the whole reason this is a single
 * screen rather than a list of sections that open into a second page.
 *
 * Three placeholders keep the live parts live — {{window}}, {{window_am}} and
 * {{policy}} — and their current values are printed at the top, so staff can see
 * the sentence they are building without previewing the site.
 *
 * @var list<array<string, mixed>> $sections
 * @var array<string, string> $tokens
 * @var \Wiz\Support\ChangePolicy $policy
 * @var string $updatedOn
 * @var string $previewUrl
 */

/**
 * Enough rows to read a clause without scrolling inside the box, and never so
 * many that the page becomes a wall of empty space. Prose is edited by reading
 * it, so the box has to be about as tall as the sentence.
 */
$rowsFor = static function (string $text): int {
    $lines = (int) ceil(mb_strlen($text) / 78);

    return max(3, min(9, $lines));
};

$tokenHelp = [
    '{{window}}' => 'The change window in English — filled from Settings → Booking.',
    '{{window_am}}' => 'The same window in Amharic.',
    '{{policy}}' => 'Your rule as one sentence — the same wording the booking page prints, in English.',
];
?>

<div class="terms-intro">
    <div class="terms-intro__main">
        <p class="terms-intro__text">
            This is the whole of the terms page — the text a customer agrees to when they book.
            English and Amharic are stored together: a clause saved in one language only would show a
            gap to half the customers, so both are required.
        </p>
        <p class="terms-intro__text terms-intro__text--soft">
            Saving publishes immediately. Last edited <strong><?= e($updatedOn) ?></strong>.
            <a href="<?= e($previewUrl) ?>" target="_blank" rel="noopener">Open the live page ↗</a>
        </p>
    </div>

    <div class="terms-tokens">
        <p class="terms-tokens__title">Live text you can drop into any clause</p>
        <dl class="terms-tokens__list">
            <?php foreach ($tokens as $placeholder => $value): ?>
                <div class="terms-tokens__item">
                    <dt><code><?= e($placeholder) ?></code></dt>
                    <dd>
                        <span class="terms-tokens__value"><?= e($value) ?></span>
                        <span class="field__hint"><?= e($tokenHelp[$placeholder] ?? '') ?></span>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <p class="field__hint">
            The window comes from your booking rule, so the terms can never promise a deadline the
            booking form would refuse. Change it in
            <a href="/admin/settings">Settings</a> and every sentence above updates itself.
        </p>
    </div>
</div>

<?php if ($sections === []): ?>
    <div class="card">
        <div class="empty-state empty-state--plain">
            <h2 class="card__title">The terms page is empty</h2>
            <p class="card__sub">
                Until you add a section, the website shows the copy that shipped with it — customers
                are never left without terms. Add a section below to take over the page.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php $sectionNumber = 0; ?>
<?php foreach ($sections as $section): ?>
    <?php
    $sectionNumber++;
    $sectionId = (int) $section['id'];
    $slug = (string) $section['slug'];
    $clauses = is_array($section['clauses'] ?? null) ? $section['clauses'] : [];
    ?>
    <section class="card terms-section" id="<?= e($slug) ?>">
        <header class="terms-section__head">
            <div class="terms-section__identity">
                <span class="terms-section__index" aria-hidden="true"><?= $sectionNumber ?></span>
                <div>
                    <h2 class="card__title">
                        <?= e((string) $section['title_en']) ?>
                        <span class="terms-section__anchor">#<?= e($slug) ?></span>
                    </h2>
                    <p class="card__sub" lang="am"><?= e((string) $section['title_am']) ?></p>
                </div>
            </div>

            <div class="terms-section__tools">
                <form method="post" action="/admin/terms/sections/<?= $sectionId ?>/move" class="order-buttons">
                    <?= csrf_field() ?>
                    <input type="hidden" name="direction" value="up">
                    <button class="icon-btn" type="submit" title="Move this section up"
                            aria-label="Move <?= e((string) $section['title_en']) ?> up">↑</button>
                </form>
                <form method="post" action="/admin/terms/sections/<?= $sectionId ?>/move" class="order-buttons">
                    <?= csrf_field() ?>
                    <input type="hidden" name="direction" value="down">
                    <button class="icon-btn" type="submit" title="Move this section down"
                            aria-label="Move <?= e((string) $section['title_en']) ?> down">↓</button>
                </form>
            </div>
        </header>

        <details class="terms-section__rename">
            <summary>Rename this section</summary>
            <form method="post" action="/admin/terms/sections/<?= $sectionId ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field-row field-row--stack">
                    <div class="field">
                        <label for="title_en_<?= $sectionId ?>">Heading in English</label>
                        <input type="text" id="title_en_<?= $sectionId ?>" name="title_en" required maxlength="160"
                               value="<?= e((string) $section['title_en']) ?>">
                    </div>
                    <div class="field">
                        <label for="title_am_<?= $sectionId ?>">Heading in Amharic</label>
                        <input type="text" id="title_am_<?= $sectionId ?>" name="title_am" required maxlength="160"
                               lang="am" value="<?= e((string) $section['title_am']) ?>">
                    </div>
                </div>
                <div class="form-actions form-actions--start">
                    <button type="submit" class="btn btn--primary btn--sm">Save heading</button>
                    <span class="field__hint">
                        The link <code>#<?= e($slug) ?></code> keeps working — contents links do not break
                        when a heading is reworded.
                    </span>
                </div>
            </form>
        </details>

        <?php if ($clauses === []): ?>
            <p class="terms-section__empty">No clauses yet. The first one is added below.</p>
        <?php else: ?>
            <ol class="terms-clauses">
                <?php foreach ($clauses as $clause): ?>
                    <?php $clauseId = (int) $clause['id']; ?>
                    <li class="terms-clause">
                        <form method="post" action="/admin/terms/clauses/<?= $clauseId ?>" autocomplete="off">
                            <?= csrf_field() ?>
                            <div class="terms-clause__head">
                                <span class="terms-clause__number"><?= $sectionNumber ?>.<?= (int) $clause['sort_order'] ?></span>
                                <div class="terms-clause__tools">
                                    <button class="icon-btn" type="submit" formaction="/admin/terms/clauses/<?= $clauseId ?>/move"
                                            name="direction" value="up" title="Move this clause up"
                                            aria-label="Move clause <?= $sectionNumber ?>.<?= (int) $clause['sort_order'] ?> up">↑</button>
                                    <button class="icon-btn" type="submit" formaction="/admin/terms/clauses/<?= $clauseId ?>/move"
                                            name="direction" value="down" title="Move this clause down"
                                            aria-label="Move clause <?= $sectionNumber ?>.<?= (int) $clause['sort_order'] ?> down">↓</button>
                                </div>
                            </div>

                            <div class="field-row field-row--stack">
                                <div class="field">
                                    <label for="body_en_<?= $clauseId ?>">English</label>
                                    <textarea id="body_en_<?= $clauseId ?>" name="body_en"
                                              rows="<?= $rowsFor((string) $clause['body_en']) ?>" required
                                              maxlength="2000"><?= e((string) $clause['body_en']) ?></textarea>
                                </div>
                                <div class="field">
                                    <label for="body_am_<?= $clauseId ?>">Amharic</label>
                                    <textarea id="body_am_<?= $clauseId ?>" name="body_am"
                                              rows="<?= $rowsFor((string) $clause['body_am']) ?>" required
                                              maxlength="2000" lang="am"><?= e((string) $clause['body_am']) ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions form-actions--start">
                                <button type="submit" class="btn btn--primary btn--sm">Save clause <?= $sectionNumber ?>.<?= (int) $clause['sort_order'] ?></button>
                                <button type="submit" class="btn btn--danger btn--sm"
                                        formaction="/admin/terms/clauses/<?= $clauseId ?>/delete"
                                        onclick="return confirm('Remove this clause from the terms? The rest of the section renumbers itself.');">
                                    Delete
                                </button>
                            </div>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <details class="terms-section__add">
            <summary>Add a clause to “<?= e((string) $section['title_en']) ?>”</summary>
            <form method="post" action="/admin/terms/clauses" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                <div class="field-row field-row--stack">
                    <div class="field">
                        <label for="new_en_<?= $sectionId ?>">English</label>
                        <textarea id="new_en_<?= $sectionId ?>" name="body_en" rows="2" required maxlength="2000"
                                  placeholder="One idea per clause. Short sentences are read; long ones are skipped."></textarea>
                    </div>
                    <div class="field">
                        <label for="new_am_<?= $sectionId ?>">Amharic</label>
                        <textarea id="new_am_<?= $sectionId ?>" name="body_am" rows="2" required maxlength="2000"
                                  lang="am"></textarea>
                    </div>
                </div>
                <div class="form-actions form-actions--start">
                    <button type="submit" class="btn btn--primary btn--sm">Add clause</button>
                </div>
            </form>
        </details>

        <details class="terms-section__danger">
            <summary>Remove this section</summary>
            <form method="post" action="/admin/terms/sections/<?= $sectionId ?>/delete"
                  onsubmit="return confirm('Remove “<?= e((string) $section['title_en']) ?>” and its <?= count($clauses) ?> clause(s) from the terms? This cannot be undone.');">
                <?= csrf_field() ?>
                <p class="field__hint">
                    The heading and everything under it leaves the page. Customers who book after this
                    are no longer shown these clauses, so only remove a section you have replaced.
                </p>
                <button type="submit" class="btn btn--danger btn--sm">Delete section</button>
            </form>
        </details>
    </section>
<?php endforeach; ?>

<section class="card terms-new">
    <h2 class="card__title">Add a section</h2>
    <p class="card__sub">
        A section is one heading in the contents list — “Before you arrive”, “Photography”, “If
        something goes wrong”. It is created empty, and clauses are added underneath it.
    </p>

    <form method="post" action="/admin/terms/sections" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field-row field-row--stack">
            <div class="field">
                <label for="new_section_en">Heading in English</label>
                <input type="text" id="new_section_en" name="title_en" required maxlength="160"
                       placeholder="e.g. Before you arrive">
            </div>
            <div class="field">
                <label for="new_section_am">Heading in Amharic</label>
                <input type="text" id="new_section_am" name="title_am" required maxlength="160" lang="am"
                       placeholder="e.g. ከመምጣትዎ በፊት">
            </div>
        </div>
        <div class="form-actions form-actions--start">
            <button type="submit" class="btn btn--primary">Add section</button>
        </div>
    </form>
</section>

<?php
// Textareas that fit their text.
//
// Prose is not a form field you scroll inside: a clause cut off halfway is the
// one thing an editor must not see. `rows` above is the no-JS floor (it sizes
// for a wide screen); this grows each box to fit at whatever width the screen
// is. If scripting is off, the page is still usable — the boxes scroll instead.
?>
<script>
(function () {
    var boxes = document.querySelectorAll(".terms-clause textarea, .terms-section textarea");

    function fit(box) {
        box.style.height = "auto";
        box.style.height = Math.max(box.scrollHeight, 68) + "px";
    }

    Array.prototype.forEach.call(boxes, function (box) {
        fit(box);
        box.addEventListener("input", function () { fit(box); });
    });

    // A rotated phone changes the measure, and therefore the height.
    window.addEventListener("resize", function () {
        Array.prototype.forEach.call(boxes, fit);
    });
})();
</script>
