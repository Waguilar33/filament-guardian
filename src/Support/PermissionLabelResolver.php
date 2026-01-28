<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Illuminate\Support\Str;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder as PermissionKeyBuilderContract;

final class PermissionLabelResolver
{
    public function __construct(
        private PermissionKeyBuilderContract $keyBuilder,
    ) {}

    /**
     * Get the full human-readable label for a permission.
     *
     * Priority: translation file > config label > Str::headline()
     */
    public function getLabel(string $permissionKey): string
    {
        // 1. Check for translation (highest priority for multi-language)
        $translationKey = 'filament-guardian::filament-guardian.custom.' . $permissionKey;
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        // 2. Check for config label
        /** @var array<string, string> $customPermissions */
        $customPermissions = config('filament-guardian.custom_permissions', []);

        if (isset($customPermissions[$permissionKey])) {
            return $customPermissions[$permissionKey];
        }

        // 3. Try to parse as action:subject (for resource permissions)
        $parts = $this->parsePermissionKey($permissionKey);

        if ($parts === null) {
            return Str::headline($permissionKey);
        }

        $action = $this->getActionLabel($permissionKey);
        $subject = Str::headline($parts['subject']);

        return "{$action} {$subject}";
    }

    /**
     * Get the action label from a permission key.
     * Example: "ViewAny:User" -> "View Any"
     */
    public function getActionLabel(string $permissionKey): string
    {
        $parts = $this->parsePermissionKey($permissionKey);

        if ($parts === null) {
            return Str::headline($permissionKey);
        }

        $action = $parts['action'];

        // Check for translation
        $translationKey = 'filament-guardian::filament-guardian.actions.' . Str::camel($action);
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return Str::headline($action);
    }

    /**
     * Get a short action-only label for display in resource sections.
     * Example: "ViewAny:User" -> "View Any"
     */
    public function getShortLabel(string $permissionKey): string
    {
        return $this->getActionLabel($permissionKey);
    }

    /**
     * Get the raw action part of a permission key.
     * Example: "ViewAny:User" -> "ViewAny"
     */
    public function getAction(string $permissionKey): ?string
    {
        $parts = $this->parsePermissionKey($permissionKey);

        return $parts['action'] ?? null;
    }

    /**
     * Get the raw subject part of a permission key.
     * Example: "ViewAny:User" -> "User"
     */
    public function getSubject(string $permissionKey): ?string
    {
        $parts = $this->parsePermissionKey($permissionKey);

        return $parts['subject'] ?? null;
    }

    /**
     * Parse a permission key into action and subject parts.
     *
     * @return array{action: string, subject: string}|null
     */
    private function parsePermissionKey(string $permissionKey): ?array
    {
        $separator = $this->keyBuilder->getSeparator();

        if ($separator === '') {
            return null;
        }

        $parts = explode($separator, $permissionKey, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'action' => $parts[0],
            'subject' => $parts[1],
        ];
    }
}
