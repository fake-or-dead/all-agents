<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class MaxUtf8Bytes implements ValidationRule
{
    public function __construct(private int $maximum) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > $this->maximum) {
            $fail("รหัสผ่านต้องมีขนาดไม่เกิน {$this->maximum} ไบต์");
        }
    }
}
