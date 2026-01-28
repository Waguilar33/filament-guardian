<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Contracts;

interface PermissionKeyBuilder
{
    public function build(string $action, string $subject, ?string $entity = null): string;

    public function format(string $value): string;

    public function getSeparator(): string;

    public function getCase(): string;

    public function extractSubject(string $permissionKey): string;
}
