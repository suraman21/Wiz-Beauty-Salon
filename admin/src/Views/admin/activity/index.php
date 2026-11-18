<?php
/**
 * Audit trail.
 *
 * @var list<array<string, mixed>> $entries
 * @var \Wiz\Support\Paginator $paginator
 */

$entityLabel = static function (string $type, mixed $id): string {
    $name = match ($type) {
        'user' => 'Team member',
        'booking' => 'Booking',
        'service' => 'Service',
        'category' => 'Category',
        'review' => 'Review',
        'gallery' => 'Gallery photo',
        'message' => 'Message',
        'settings' => 'Settings',
        default => ucfirst(str_replace('_', ' ', $type)),
    };

    return $id === null ? $name : $name . ' #' . (int) $id;
};
?>

<?php if ($entries === []): ?>
    <section class="card empty-state">
        <h2 class="card__title">Nothing recorded yet</h2>
        <p class="card__sub">Sign-ins and changes appear here as soon as they happen.</p>
    </section>
<?php else: ?>
    <div class="card card--flush">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Person</th>
                        <th scope="col">What happened</th>
                        <th scope="col">Record</th>
                        <th scope="col">Address</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="nowrap"><?= e(full_date(isset($entry['created_at']) ? (string) $entry['created_at'] : null)) ?></td>
                        <td><?= e($entry['user_name'] ?? 'System') ?></td>
                        <td><?= e(activity_label((string) $entry['action'])) ?></td>
                        <td class="cell-sub"><?= e($entityLabel((string) $entry['entity_type'], $entry['entity_id'] ?? null)) ?></td>
                        <td class="cell-sub"><?= e($entry['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $path = '/admin/activity';
    $query = [];
    require __DIR__ . '/../../partials/pagination.php';
    ?>
<?php endif; ?>
