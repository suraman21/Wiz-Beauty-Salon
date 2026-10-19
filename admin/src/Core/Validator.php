<?php
declare(strict_types=1);

namespace Wiz\Core;

/**
 * Server-side validation.
 *
 * Rules are declared per field and the first failure per field wins, so a form
 * shows one clear message rather than a wall of them.
 *
 * Supported: required, email, string, integer, numeric, min, max, between,
 * in, date, time, phone, regex.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules  field => "required|max:120"
     * @param array<string, string> $labels field => "Full name"
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = [],
    ) {
    }

    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $label = $this->labels[$field] ?? $field;

            foreach (explode('|', $ruleString) as $rule) {
                $rule = trim($rule);

                if ($rule === '') {
                    continue;
                }

                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

                if (!$this->passes($name, $value, $argument)) {
                    $this->addError($field, $this->message($name, $label, $argument, $value));
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    private function passes(string $rule, mixed $value, ?string $argument): bool
    {
        $string = is_scalar($value) ? trim((string) $value) : '';
        $isEmpty = $value === null || $string === '';

        // Every other rule is skipped when the field is empty and not required.
        if ($rule !== 'required' && $isEmpty) {
            return true;
        }

        return match ($rule) {
            'required' => !$isEmpty,
            'email' => filter_var($string, FILTER_VALIDATE_EMAIL) !== false,
            'string' => is_string($value),
            'integer' => $this->isInteger($string),
            'numeric' => is_numeric($string),
            'min' => $this->compareLength($string, $argument, 'min'),
            'max' => $this->compareLength($string, $argument, 'max'),
            'between' => $this->isBetween($string, $argument),
            'in' => $this->isIn($string, $argument),
            'date' => $this->isDate($string),
            'time' => $this->isTime($string),
            'phone' => $this->isPhone($string),
            'regex' => $argument !== null && @preg_match('/' . str_replace('/', '\\/', $argument) . '/', $string) === 1,
            default => true,
        };
    }

    private function isInteger(string $value): bool
    {
        return $value !== '' && preg_match('/^-?\d+$/', $value) === 1;
    }

    private function compareLength(string $value, ?string $argument, string $mode): bool
    {
        $limit = (int) $argument;
        $length = mb_strlen($value);

        return $mode === 'min' ? $length >= $limit : $length <= $limit;
    }

    private function isBetween(string $value, ?string $argument): bool
    {
        $parts = explode(',', (string) $argument);

        if (count($parts) !== 2) {
            return true;
        }

        $length = mb_strlen($value);

        return $length >= (int) $parts[0] && $length <= (int) $parts[1];
    }

    private function isIn(string $value, ?string $argument): bool
    {
        $allowed = array_map('trim', explode(',', (string) $argument));

        return in_array($value, $allowed, true);
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) === 1;
    }

    private function isPhone(string $value): bool
    {
        // Ethiopian mobile numbers and international format, digits plus + - space.
        return preg_match('/^\+?[0-9][0-9\s\-()]{6,19}$/', $value) === 1;
    }

    private function message(string $rule, string $label, ?string $argument, mixed $value): string
    {
        return match ($rule) {
            'required' => sprintf('%s is required.', $label),
            'email' => sprintf('%s must be a valid email address.', $label),
            'integer' => sprintf('%s must be a whole number.', $label),
            'numeric' => sprintf('%s must be a number.', $label),
            'min' => sprintf('%s must be at least %s characters.', $label, (string) $argument),
            'max' => sprintf('%s may not be longer than %s characters.', $label, (string) $argument),
            'between' => sprintf('%s must be between %s characters.', $label, str_replace(',', ' and ', (string) $argument)),
            'in' => sprintf('%s has an invalid value.', $label),
            'date' => sprintf('%s must be a valid date.', $label),
            'time' => sprintf('%s must be a valid time.', $label),
            'phone' => sprintf('%s must be a valid phone number.', $label),
            'regex' => sprintf('%s has an invalid format.', $label),
            default => sprintf('%s is invalid.', $label),
        };
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
