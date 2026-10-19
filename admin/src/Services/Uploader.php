<?php
declare(strict_types=1);

namespace Wiz\Services;

/**
 * Image uploads for the gallery and service pages.
 *
 * The rules are deliberately narrow:
 *
 * - the file type is decided by reading the bytes (finfo), never by the name or
 *   the Content-Type the browser claimed;
 * - the stored name is random, so a crafted filename can never land on disk;
 * - only JPEG, PNG and WebP are accepted;
 * - the extension comes from the detected type, so "photo.php.jpg" is stored as
 *   a .jpg with random letters in front of it;
 * - PHP execution inside the upload directory is denied by the server config.
 *
 * The original bytes are kept as-is: re-encoding would strip more, but it also
 * recompresses the owner's photos, and these images are hers, not ours.
 */
final class Uploader
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly string $directory,
        private readonly string $publicPrefix,
        private readonly int $maxBytes = 5_242_880,
    ) {
    }

    /**
     * @param array<string, mixed> $file one entry from $_FILES
     * @return array{ok: bool, path?: string, url?: string, error?: string}
     */
    public function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $this->uploadError($error)];
        }

        $tmp = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';

        if ($tmp === '' || !is_file($tmp)) {
            return ['ok' => false, 'error' => 'The file did not arrive. Please try again.'];
        }

        $size = (int) ($file['size'] ?? filesize($tmp));

        if ($size <= 0) {
            return ['ok' => false, 'error' => 'That file is empty.'];
        }

        if ($size > $this->maxBytes) {
            return [
                'ok' => false,
                'error' => sprintf('Images must be smaller than %d MB.', (int) round($this->maxBytes / 1_048_576)),
            ];
        }

        $mime = $this->detectMime($tmp);

        if (!isset(self::ALLOWED[$mime])) {
            return ['ok' => false, 'error' => 'Choose a JPG, PNG or WebP image.'];
        }

        // A file that claims an image type but does not decode as one is not an
        // image, whatever finfo says about the first few bytes.
        if (@getimagesize($tmp) === false) {
            return ['ok' => false, 'error' => 'That file is not a readable image.'];
        }

        if (!$this->ensureDirectory()) {
            return ['ok' => false, 'error' => 'The upload folder is not writable. Ask an administrator.'];
        }

        $name = bin2hex(random_bytes(12)) . '.' . self::ALLOWED[$mime];
        $target = rtrim($this->directory, '/') . '/' . $name;

        $moved = is_uploaded_file($tmp)
            ? move_uploaded_file($tmp, $target)
            : rename($tmp, $target);

        if (!$moved) {
            return ['ok' => false, 'error' => 'The image could not be saved. Please try again.'];
        }

        @chmod($target, 0644);

        return [
            'ok' => true,
            'path' => $name,
            'url' => $this->url($name),
        ];
    }

    /**
     * Remove a previously stored image. Anything that is not a plain filename
     * inside our own directory is ignored.
     */
    public function delete(?string $name): void
    {
        if ($name === null || $name === '') {
            return;
        }

        $safe = basename($name);

        if ($safe !== $name) {
            return;
        }

        $path = rtrim($this->directory, '/') . '/' . $safe;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function url(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        return rtrim($this->publicPrefix, '/') . '/' . basename($name);
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function ensureDirectory(): bool
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        return is_dir($this->directory) && is_writable($this->directory);
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is too large for the server.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose an image first.',
            default => 'The upload failed. Please try again.',
        };
    }
}
