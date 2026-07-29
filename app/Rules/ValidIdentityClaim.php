<?php

namespace App\Rules;

use App\Modules\People\Data\IdentityClaim;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final readonly class ValidIdentityClaim implements ValidationRule
{
    public function __construct(private mixed $identityType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($this->identityType) || ! is_string($value)) {
            $fail('ข้อมูลประจำตัวไม่ถูกต้อง');

            return;
        }

        try {
            IdentityClaim::fromInput($this->identityType, $value);
        } catch (InvalidArgumentException) {
            $fail('ข้อมูลประจำตัวไม่ถูกต้อง');
        }
    }
}
