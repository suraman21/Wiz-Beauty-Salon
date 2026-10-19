<?php
/**
 * Email design studio — cloned from Bulbula.et's staff screen.
 *
 * The editor is GrapesJS; it exports inlined HTML plus the project JSON, and the
 * server re-validates both before a revision can exist. The preview below is a
 * null-origin iframe with a strict CSP, so a draft can never run in this origin.
 *
 * @var string $key
 * @var list<array{key: string, label: string, revision_id: int}> $catalog
 * @var list<array<string, mixed>> $revisions
 * @var int $activeId
 * @var array<string, mixed>|null $selectedRevision
 * @var array{subject: string, html: string, text: string} $initial
 * @var string $requiredToken
 * @var bool $isCode
 */

$subject = (string) ($selectedRevision['subject_template'] ?? $initial['subject']);
$html = (string) ($selectedRevision['html_template'] ?? $initial['html']);
$text = (string) ($selectedRevision['text_template'] ?? $initial['text']);
$project = json_decode((string) ($selectedRevision['project_json'] ?? ''), true);
$data = json_encode(
    ['html' => $html, 'project' => $project],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
$labels = array_column($catalog, 'label', 'key');
$typeLabel = (string) ($labels[$key] ?? $key);
?>

<section class="b-email-page">
    <div class="b-email-page__intro">
        <div>
            <span class="b-badge b-badge--orange">Administrator workspace</span>
            <h2>Email design studio</h2>
            <p>Choose a message, design its layout, save a draft and publish only when you are happy. No email is sent by this screen.</p>
        </div>
        <a class="btn btn--ghost" href="/admin/settings">Email settings</a>
    </div>

    <div class="b-email-page__layout">
        <aside class="b-email-page__catalog" aria-label="Email types">
            <h3>Choose an email</h3>
            <?php foreach ($catalog as $item): ?>
                <a class="b-email-page__type<?= $item['key'] === $key ? ' is-active' : '' ?>"
                   href="/admin/email-templates?type=<?= rawurlencode($item['key']) ?>"
                   <?= $item['key'] === $key ? 'aria-current="page"' : '' ?>>
                    <span><?= e($item['label']) ?></span>
                    <small><?= $item['revision_id'] > 0 ? 'Published' : 'Default' ?></small>
                </a>
            <?php endforeach; ?>
        </aside>

        <div class="b-email-page__main">
            <header class="b-email-page__header">
                <div>
                    <p class="b-email-page__eyebrow">Selected message</p>
                    <h3><?= e($typeLabel) ?></h3>
                    <p>
                        <?= $activeId > 0 ? 'Published revision #' . $activeId : 'Using the built-in email until a design is published.' ?>
                        <?php if ($selectedRevision !== null): ?>Editing saved revision #<?= (int) $selectedRevision['id'] ?>.<?php endif; ?>
                    </p>
                </div>
                <button id="email-preview" type="button" class="btn btn--ghost">Preview with sample content</button>
            </header>

            <div class="b-email-page__notice">
                Design freely, but keep <strong><?= e($requiredToken) ?></strong> in both the visual design and the plain-text version.
                <?php if ($isCode): ?>
                    Also include <strong>{{minutes}}</strong> in both; these values are filled in when a real email is sent.
                <?php else: ?>
                    This placeholder supplies the event details.
                <?php endif; ?>
                Use only HTTPS links to the salon's own site.
            </div>

            <script id="email-template-data" type="application/json"><?= $data ?></script>
            <div id="wiz-email-editor" aria-label="Drag and drop email layout editor"></div>

            <div id="email-preview-wrap" class="b-email-page__preview" hidden>
                <h4>Sample preview — not an actual email</h4>
                <iframe id="email-preview-frame" title="Sample email design" sandbox="" referrerpolicy="no-referrer"></iframe>
            </div>

            <form id="email-save-form" action="/admin/email-templates/save" method="post" class="b-email-page__form">
                <?= csrf_field() ?>
                <input type="hidden" name="template_key" value="<?= e($key) ?>">
                <input id="email-html" type="hidden" name="html">
                <input id="email-project" type="hidden" name="project">

                <div>
                    <label for="email-subject">Email subject</label>
                    <input id="email-subject" class="input" name="subject" required maxlength="180" value="<?= e($subject) ?>">
                    <small>Use {{subject}} to keep the event's standard subject.</small>
                </div>

                <div>
                    <label for="email-plain">Plain-text version (for accessibility and text-only inboxes)</label>
                    <textarea id="email-plain" name="text" class="input" rows="6" required maxlength="12000"><?= e($text) ?></textarea>
                </div>

                <div class="b-email-page__actions">
                    <button type="submit" class="btn btn--primary">Save new draft</button>
                    <p id="email-editor-status" role="status" aria-live="polite"></p>
                </div>
            </form>

            <?php if ($revisions !== []): ?>
                <section class="b-email-page__revisions">
                    <h4>Saved revisions</h4>
                    <p>Publishing changes future sends only. Publish an older revision to roll back.</p>
                    <div class="b-email-page__revision-list">
                        <?php foreach ($revisions as $row): ?>
                            <div class="b-email-page__revision">
                                <a href="/admin/email-templates?type=<?= rawurlencode($key) ?>&amp;revision=<?= (int) $row['id'] ?>">Version <?= (int) $row['version'] ?></a>
                                <small><?= e((string) $row['created_at']) ?><?= (int) $row['id'] === $activeId ? ' · LIVE' : ' · draft / archived' ?></small>
                                <?php if ((int) $row['id'] !== $activeId): ?>
                                    <form method="post" action="/admin/email-templates/publish">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="template_key" value="<?= e($key) ?>">
                                        <input type="hidden" name="revision_id" value="<?= (int) $row['id'] ?>">
                                        <button class="btn btn--ghost" type="submit">Publish this version</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>
