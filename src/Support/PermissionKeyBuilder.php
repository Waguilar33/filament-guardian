<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder as PermissionKeyBuilderContract;

final class PermissionKeyBuilder implements PermissionKeyBuilderContract
{
    private const VALID_CASES = [
        'snake',
        'kebab',
        'pascal',
        'camel',
        'upper_snake',
        'lower_snake',
    ];

    public function __construct(
        private readonly string $separator = ':',
        private readonly string $case = 'pascal',
    ) {
        if (! in_array($this->case, self::VALID_CASES, true)) {
            throw new InvalidArgumentException(
                "Invalid case '{$this->case}'. Valid options: " . implode(', ', self::VALID_CASES)
            );
        }
    }

    public function build(string $action, string $subject, ?string $entity = null): string
    {
        return $this->format($action) . $this->separator . $this->format($subject);
    }

    public function format(string $value): string
    {
        return match ($this->case) {
            'snake' => Str::snake($value),
            'kebab' => Str::kebab($value),
            'pascal' => Str::studly($value),
            'camel' => Str::camel($value),
            'upper_snake' => Str::upper(Str::snake($value)),
            'lower_snake' => Str::lower(Str::snake($value)),
        };
    }

    public function getSeparator(): string
    {
        return $this->separator;
    }

    public function getCase(): string
    {
        return $this->case;
    }

    public function extractSubject(string $permissionKey): string
    {
        if ($this->separator === '') {
            return $permissionKey;
        }

        $parts = explode($this->separator, $permissionKey, 2);

        return $parts[1] ?? $permissionKey;
    }
}
