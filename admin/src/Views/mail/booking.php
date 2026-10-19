<?php
/**
 * One transactional email, in the salon's colours.
 *
 * Inline styles and a table-free layout: mail clients strip <style> blocks and
 * float support is uneven, so everything here is simple by design.
 *
 * @var string $title
 * @var string $intro
 * @var list<array{0: string, 1: string}> $rows
 * @var string $actionUrl
 * @var string $actionLabel
 * @var string $outro
 * @var string $salonName
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
</head>
<body style="margin:0;padding:0;background:#faf8f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1d1a18;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf8f5;padding:28px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e7e2dc;border-radius:12px;overflow:hidden;">

    <tr>
        <td style="background:#211d1a;padding:20px 26px;">
            <span style="font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:#c9a173;">
                <?= e($salonName) ?>
            </span>
        </td>
    </tr>

    <tr>
        <td style="padding:28px 26px 8px;">
            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;font-weight:600;"><?= e($title) ?></h1>
            <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#4a443f;"><?= e($intro) ?></p>
        </td>
    </tr>

    <?php if ($rows !== []): ?>
        <tr>
            <td style="padding:0 26px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e7e2dc;">
                    <?php foreach ($rows as [$label, $value]): ?>
                        <tr>
                            <td style="padding:11px 0;border-bottom:1px solid #e7e2dc;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#7b736c;width:42%;">
                                <?= e($label) ?>
                            </td>
                            <td style="padding:11px 0;border-bottom:1px solid #e7e2dc;font-size:14px;color:#1d1a18;text-align:right;">
                                <?= e($value) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </td>
        </tr>
    <?php endif; ?>

    <?php if ($actionUrl !== ''): ?>
        <tr>
            <td style="padding:24px 26px 4px;">
                <a href="<?= e($actionUrl) ?>"
                   style="display:inline-block;padding:12px 20px;background:#7b5a42;color:#ffffff;border-radius:7px;font-size:14px;font-weight:600;text-decoration:none;">
                    <?= e($actionLabel) ?>
                </a>
                <p style="margin:14px 0 0;font-size:12px;line-height:1.6;color:#7b736c;word-break:break-all;">
                    <?= e($actionUrl) ?>
                </p>
            </td>
        </tr>
    <?php endif; ?>

    <tr>
        <td style="padding:18px 26px 26px;">
            <p style="margin:0;font-size:12.5px;line-height:1.7;color:#7b736c;"><?= e($outro) ?></p>
        </td>
    </tr>

    <tr>
        <td style="padding:16px 26px;background:#faf8f5;border-top:1px solid #e7e2dc;font-size:11.5px;color:#7b736c;">
            <?= e($salonName) ?> · This message was sent automatically. Replies go to the salon.
        </td>
    </tr>

</table>
</td></tr>
</table>

</body>
</html>
