<?php

declare(strict_types=1);

namespace TimeBank\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    private function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): static
    {
        $instance = new static($data, $rules);
        $instance->validate();
        return $instance;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value    = $this->data[$field] ?? null;

            foreach ($ruleList as $rule) {
                [$ruleName, $ruleParam] = $this->parseRule($rule);

                $error = match ($ruleName) {
                    'required'  => $this->validateRequired($field, $value),
                    'email'     => $this->validateEmail($field, $value),
                    'min'       => $this->validateMin($field, $value, (float) $ruleParam),   // (int) made min:0.25 into min:0
                    'max'       => $this->validateMax($field, $value, (float) $ruleParam),
                    'maxlen'    => $this->validateMaxLength($field, $value, (int) $ruleParam),
                    'numeric'   => $this->validateNumeric($field, $value),
                    'confirmed' => $this->validateConfirmed($field, $value),
                    'unique'    => $this->validateUnique($field, $value, $ruleParam),
                    'in'        => $this->validateIn($field, $value, $ruleParam),
                    'url'       => $this->validateUrl($field, $value),
                    'integer'   => $this->validateInteger($field, $value),
                    'alpha'     => $this->validateAlpha($field, $value),
                    'alpha_num' => $this->validateAlphaNum($field, $value),
                    default     => null,
                };

                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break; // Stop at first error for this field
                }
            }
        }
    }

    private function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        return [$parts[0], $parts[1] ?? null];
    }

    private function validateRequired(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return $this->fieldLabel($field) . ' is required.';
        }
        return null;
    }

    private function validateEmail(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null; // Only validate if provided; use required for presence
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->fieldLabel($field) . ' must be a valid email address.';
        }
        return null;
    }

    private function validateMin(string $field, mixed $value, float $min): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            if ((float) $value < $min) {
                return $this->fieldLabel($field) . ' must be at least ' . $min . '.';
            }
        } else {
            if (mb_strlen((string) $value) < $min) {
                return $this->fieldLabel($field) . ' must be at least ' . $min . ' characters.';
            }
        }
        return null;
    }

    private function validateMax(string $field, mixed $value, float $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            if ((float) $value > $max) {
                return $this->fieldLabel($field) . ' must not exceed ' . $max . '.';
            }
        } else {
            if (mb_strlen((string) $value) > $max) {
                return $this->fieldLabel($field) . ' must not exceed ' . $max . ' characters.';
            }
        }
        return null;
    }

    /**
     * Length only, for text that may look like a number (phone, ZIP). max:
     * compares numeric strings as numbers, so a ZIP of 98368 "exceeded" 20.
     */
    private function validateMaxLength(string $field, mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (mb_strlen((string) $value) > $max) {
            return $this->fieldLabel($field) . ' must not exceed ' . $max . ' characters.';
        }
        return null;
    }

    private function validateNumeric(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return $this->fieldLabel($field) . ' must be a number.';
        }
        return null;
    }

    private function validateInteger(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return $this->fieldLabel($field) . ' must be a whole number.';
        }
        return null;
    }

    private function validateConfirmed(string $field, mixed $value): ?string
    {
        $confirmValue = $this->data[$field . '_confirmation'] ?? null;
        if ($value !== $confirmValue) {
            return $this->fieldLabel($field) . ' confirmation does not match.';
        }
        return null;
    }

    /**
     * Rule format: unique:table:column
     * Optionally: unique:table:column:tenant_id_value
     * Or pass tenant_id as the 4th segment to scope uniqueness per tenant.
     */
    private function validateUnique(string $field, mixed $value, ?string $params): ?string
    {
        if ($value === null || $value === '' || $params === null) {
            return null;
        }

        $parts     = explode(':', $params);
        $table     = $parts[0] ?? '';
        $column    = $parts[1] ?? $field;
        $tenantId  = $parts[2] ?? null;
        $exceptId  = $parts[3] ?? null; // optional: exclude this row ID

        if (empty($table) || empty($column)) {
            return null;
        }

        $sql    = "SELECT id FROM `{$table}` WHERE `{$column}` = ?";
        $binds  = [$value];

        if ($tenantId !== null) {
            $sql   .= ' AND tenant_id = ?';
            $binds[] = $tenantId;
        }

        if ($exceptId !== null) {
            $sql   .= ' AND id != ?';
            $binds[] = $exceptId;
        }

        $row = DB::fetch($sql, $binds);

        if ($row) {
            return $this->fieldLabel($field) . ' is already taken.';
        }

        return null;
    }

    private function validateIn(string $field, mixed $value, ?string $params): ?string
    {
        if ($value === null || $value === '' || $params === null) {
            return null;
        }
        $allowed = explode(',', $params);
        if (!in_array((string) $value, $allowed, true)) {
            return $this->fieldLabel($field) . ' must be one of: ' . implode(', ', $allowed) . '.';
        }
        return null;
    }

    private function validateUrl(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return $this->fieldLabel($field) . ' must be a valid URL.';
        }
        return null;
    }

    private function validateAlpha(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!ctype_alpha((string) $value)) {
            return $this->fieldLabel($field) . ' may only contain letters.';
        }
        return null;
    }

    private function validateAlphaNum(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!ctype_alnum((string) $value)) {
            return $this->fieldLabel($field) . ' may only contain letters and numbers.';
        }
        return null;
    }

    private function fieldLabel(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }
}
