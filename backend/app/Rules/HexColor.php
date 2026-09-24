<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Extracted from what was an inline regex in UpdateSettingsRequest —
 * any future field that accepts a color (widget accent color, future
 * theming options) reuses this instead of re-deriving the same regex.
 */
class HexColor implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            $fail('The :attribute must be a valid 6-digit hex color (e.g. #1A2B3C).');
        }
    }
}
