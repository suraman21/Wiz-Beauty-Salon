<?php
declare(strict_types=1);

namespace Wiz\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Wiz\Core\Database;

/**
 * Versioned, administrator-published email designs.
 *
 * Cloned from Bulbula.et: a design is saved as a new revision, never as a
 * change to what is live. Publishing points `email_template_active` at one
 * revision; rolling back is publishing an older one. Sending renders whatever
 * is active, or the caller's own fallback if the row is missing, the markup is
 * unsafe, a placeholder is left over, or the tables do not exist yet.
 *
 * Nothing here evaluates code: placeholders are a fixed list, values are
 * inserted with `strtr`, and HTML values are escaped before substitution.
 */
final class EmailTemplateService
{
    /** The only placeholders a design may use. */
    private const TOKENS = ['subject', 'message', 'code', 'minutes', 'name'];

    private const UNSAFE = '~<(script|iframe|object|embed|form|svg|meta|link|base)\b|\bon[a-z]+\s*=|(?:javascript|data|vbscript)\s*:|url\s*\(|@import~i';

    public function __construct(
        private readonly Database $db,
        private readonly string $appUrl = '',
    ) {
    }

    /**
     * @return list<array{key: string, label: string, revision_id: int}>
     */
    public function list(): array
    {
        $rows = $this->db->all('SELECT template_key, revision_id FROM email_template_active');
        $active = array_column($rows, 'revision_id', 'template_key');

        $out = [];

        foreach (EmailTemplateCatalog::LABELS as $key => $label) {
            $out[] = [
                'key' => $key,
                'label' => $label,
                'revision_id' => (int) ($active[$key] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revisions(string $key): array
    {
        EmailTemplateCatalog::required($key);

        return $this->db->all(
            'SELECT id, version, subject_template, html_template, text_template, project_json, created_by, created_at
               FROM email_template_revisions
              WHERE template_key = :key
              ORDER BY version DESC
              LIMIT 30',
            [':key' => $key],
        );
    }

    public function activeId(string $key): int
    {
        EmailTemplateCatalog::required($key);

        $row = $this->db->first(
            'SELECT revision_id FROM email_template_active WHERE template_key = :key',
            [':key' => $key],
        );

        return (int) ($row['revision_id'] ?? 0);
    }

    public function saveDraft(string $key, string $subject, string $html, string $text, string $projectJson, int $actor): int
    {
        EmailTemplateCatalog::required($key);

        if ($actor < 1
            || strlen($projectJson) > 300_000
            || strlen($html) > 120_000
            || strlen($subject) > 180
            || strlen($text) > 12_000) {
            throw new InvalidArgumentException('The email design is too large.');
        }

        $project = json_decode($projectJson, true, 40);

        if (!is_array($project)) {
            throw new InvalidArgumentException('The editor project is invalid.');
        }

        // The editor reloads this JSON in an administrator's browser; it must
        // never carry executable payloads, even inside a draft.
        if (preg_match(self::UNSAFE, $projectJson) === 1) {
            throw new InvalidArgumentException('The editor project contains unsafe content.');
        }

        $subject = trim($subject);
        $text = trim($text);
        $html = trim($html);

        if ($subject === '' || $text === '' || $html === '') {
            throw new InvalidArgumentException('Subject, design, and plain-text version are required.');
        }

        if (preg_match('/[\r\n]/', $subject) === 1) {
            throw new InvalidArgumentException('Subject must occupy one line.');
        }

        $this->validateTokens($key, $subject, $html, $text);

        if (preg_match(self::UNSAFE, $html) === 1) {
            throw new InvalidArgumentException('Active content and unsafe URLs are not allowed in email designs.');
        }

        $this->checkMarkup($html);
        $html = $this->cleanHtml($html);
        $this->validateTokens($key, $subject, $html, $text);
        $this->checkMarkup($html);

        return $this->db->transaction(function (Database $db) use ($key, $subject, $html, $text, $projectJson, $actor): int {
            $version = (int) ($db->scalar(
                'SELECT MAX(version) AS v FROM email_template_revisions WHERE template_key = :key',
                [':key' => $key],
            ) ?? 0) + 1;

            return $db->insert('email_template_revisions', [
                'template_key' => $key,
                'version' => $version,
                'subject_template' => $subject,
                'html_template' => $html,
                'text_template' => $text,
                'project_json' => $projectJson,
                'created_by' => $actor,
            ]);
        });
    }

    public function publish(string $key, int $revisionId): void
    {
        EmailTemplateCatalog::required($key);

        $row = $this->db->first(
            'SELECT id FROM email_template_revisions WHERE id = :id AND template_key = :key',
            [':id' => $revisionId, ':key' => $key],
        );

        if ($row === null) {
            throw new InvalidArgumentException('Select a saved design for this email type.');
        }

        // Used for both first publication and rollback. Atomic for live sends.
        $this->db->execute(
            'INSERT INTO email_template_active (template_key, revision_id) VALUES (:key, :id)
             ON DUPLICATE KEY UPDATE revision_id = VALUES(revision_id)',
            [':key' => $key, ':id' => $revisionId],
        );
    }

    /**
     * @param array<string, string> $values
     * @param array{subject: string, html: string, text: string} $fallback
     * @return array{subject: string, html: string, text: string}
     */
    public function render(string $key, array $values, array $fallback): array
    {
        EmailTemplateCatalog::required($key);

        try {
            $row = $this->db->first(
                'SELECT r.subject_template, r.html_template, r.text_template
                   FROM email_template_active a
                   JOIN email_template_revisions r
                     ON r.id = a.revision_id AND r.template_key = a.template_key
                  WHERE a.template_key = :key',
                [':key' => $key],
            );
        } catch (PDOException $e) {
            // A host may serve new PHP before the additive migration has run.
            if ((string) $e->getCode() !== '42S02' && !str_contains(strtolower($e->getMessage()), 'no such table')) {
                throw $e;
            }

            return $fallback;
        }

        if ($row === null) {
            return $fallback;
        }

        $values['subject'] ??= $fallback['subject'] ?? '';
        $values['message'] ??= $fallback['text'] ?? '';

        $tokens = [];

        foreach ($values as $name => $value) {
            if (!in_array($name, self::TOKENS, true)) {
                continue;
            }

            $tokens['{{' . $name . '}}'] = (string) $value;
        }

        $subject = strtr((string) $row['subject_template'], $tokens);
        $text = strtr((string) $row['text_template'], $tokens);
        $escaped = array_map(
            static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $tokens,
        );
        $html = strtr((string) $row['html_template'], $escaped);

        $required = '{{' . EmailTemplateCatalog::required($key) . '}}';

        if (!isset($tokens[$required])
            || str_contains($subject, '{{')
            || str_contains($text, '{{')
            || str_contains($html, '{{')
            || strlen($subject) > 250) {
            return $fallback;
        }

        if (EmailTemplateCatalog::isCode($key)) {
            $html .= '<p style="font-size:12px;color:#7d726b">This is a one-time code. Never share it with anyone.</p>';
            $text .= "\nNever share this code with anyone.";
        }

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }

    private function validateTokens(string $key, string ...$fields): void
    {
        foreach ($fields as $field) {
            preg_match_all('/{{([^{}]+)}}/', $field, $matches);

            foreach ($matches[1] as $name) {
                if (!in_array($name, self::TOKENS, true)) {
                    throw new InvalidArgumentException('Unknown placeholder: ' . mb_substr($name, 0, 40));
                }
            }

            if (str_contains($field, '{{') && preg_match('/{{[^{}]*}}/', $field) !== 1) {
                throw new InvalidArgumentException('Malformed placeholder.');
            }
        }

        $required = '{{' . EmailTemplateCatalog::required($key) . '}}';

        if (!str_contains($fields[1], $required) || !str_contains($fields[2], $required)) {
            throw new InvalidArgumentException('The HTML and plain-text versions must contain ' . $required . '.');
        }

        if (EmailTemplateCatalog::isCode($key)
            && (!str_contains($fields[1], '{{minutes}}') || !str_contains($fields[2], '{{minutes}}'))) {
            throw new InvalidArgumentException('Code emails must include {{minutes}} in both versions.');
        }
    }

    private function cleanHtml(string $html): string
    {
        if (!class_exists(HTMLPurifier::class)) {
            throw new RuntimeException('HTML Purifier is not installed.');
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('URI.AllowedSchemes', ['https' => true]);
        $config->set('URI.DefaultScheme', 'https');
        $config->set('HTML.ForbiddenElements', ['script', 'iframe', 'object', 'embed', 'form', 'svg', 'input', 'video', 'audio']);

        $result = (new HTMLPurifier($config))->purify($html);

        if (trim($result) === '') {
            throw new InvalidArgumentException('Email design has no safe content.');
        }

        return $result;
    }

    private function checkMarkup(string $html): void
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $doc->loadHTML('<html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $host = strtolower((string) parse_url($this->appUrl, PHP_URL_HOST));

        foreach ($doc->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if (str_contains($attribute->value, '{{')) {
                    throw new InvalidArgumentException('Placeholders are allowed only in visible text, not attributes.');
                }

                if (!in_array($attribute->name, ['href', 'src'], true)) {
                    continue;
                }

                $url = parse_url($attribute->value);

                if (!is_array($url)
                    || strtolower((string) ($url['scheme'] ?? '')) !== 'https'
                    || strtolower((string) ($url['host'] ?? '')) !== $host) {
                    throw new InvalidArgumentException('Links and images must use the salon’s own HTTPS address.');
                }
            }
        }
    }
}
