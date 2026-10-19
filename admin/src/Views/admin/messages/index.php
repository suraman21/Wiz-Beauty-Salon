<?php
/**
 * The contact inbox.
 *
 * @var list<array<string, mixed>> $messages
 * @var bool $unreadOnly
 * @var int $unread
 * @var int $total
 */
?>

<div class="toolbar toolbar--filters">
    <p class="toolbar__count">
        <?php if ($unread === 0): ?>
            No unread messages
        <?php elseif ($unread === 1): ?>
            1 message waiting for a reply
        <?php else: ?>
            <?= (int) $unread ?> messages waiting for a reply
        <?php endif; ?>
    </p>

    <div class="filters">
        <a class="filter-chip <?= $unreadOnly ? '' : 'is-active' ?>" href="/admin/messages">All <?= (int) $total ?></a>
        <a class="filter-chip <?= $unreadOnly ? 'is-active' : '' ?>" href="/admin/messages?filter=unread">Unread <?= (int) $unread ?></a>
    </div>

    <?php if ($unread > 0): ?>
        <form method="post" action="/admin/messages/read-all" class="filters--right">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--sm">Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($messages === []): ?>
    <div class="card">
        <div class="empty-state">
            <p class="empty-state__title">
                <?= $unreadOnly ? 'Nothing unread' : 'No messages yet' ?>
            </p>
            <p class="empty-state__text">
                When someone uses the form on the contact page, their note appears here.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="message-list">
        <?php foreach ($messages as $message): ?>
            <?php $isUnread = (int) $message['is_read'] !== 1; ?>
            <article class="message <?= $isUnread ? 'message--unread' : '' ?>">
                <header class="message__head">
                    <div>
                        <p class="message__name">
                            <?php if ($isUnread): ?><span class="message__dot" aria-label="Unread"></span><?php endif; ?>
                            <?= e($message['name']) ?>
                        </p>
                        <p class="message__meta">
                            <?php if (!empty($message['email'])): ?>
                                <a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a>
                            <?php endif; ?>
                            <?php if (!empty($message['phone'])): ?>
                                <a href="tel:<?= e($message['phone']) ?>"><?= e($message['phone']) ?></a>
                            <?php endif; ?>
                            <span>·</span>
                            <?= e(full_date($message['created_at'] ?? null)) ?>
                        </p>
                    </div>

                    <div class="message__actions">
                        <form method="post" action="/admin/messages/<?= (int) $message['id'] ?>/toggle">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <?= $isUnread ? 'Mark as read' : 'Mark as unread' ?>
                            </button>
                        </form>

                        <form method="post" action="/admin/messages/<?= (int) $message['id'] ?>/delete"
                              onsubmit="return confirm('Delete this message? This cannot be undone.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm message__delete">Delete</button>
                        </form>
                    </div>
                </header>

                <p class="message__body"><?= nl2br(e($message['body'])) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <?php
    $path = '/admin/messages';
    require __DIR__ . '/../../partials/pagination.php';
    ?>
<?php endif; ?>
