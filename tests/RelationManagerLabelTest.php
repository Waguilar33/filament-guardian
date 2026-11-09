<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Waguilar\FilamentGuardian\Support\PermissionKeyBuilder;
use Waguilar\FilamentGuardian\Support\PermissionResolver;
use Waguilar\FilamentGuardian\Tests\Fixtures\Models\User;
use Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers\StaticLabelRelationManager;
use Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers\TranslatedLabelRelationManager;
use Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers\UnlabelledRelationManager;

function relationManagerLabel(string $rmClass, array $managed = []): string
{
    $panel = Filament::getPanel('admin');
    $resolver = new PermissionResolver($panel, $panel->getAuthGuard(), app(PermissionKeyBuilder::class));

    return (new ReflectionMethod($resolver, 'resolveRelationManagerLabel'))
        ->invoke($resolver, $rmClass, User::class, $managed, false, 'User');
}

it('labels a relation manager from its getPluralModelLabel() in the current locale', function () {
    app('translator')->addLines(['fixtures.terms' => 'Terms'], 'en');
    app('translator')->addLines(['fixtures.terms' => 'Términos'], 'es');

    app()->setLocale('en');
    expect(relationManagerLabel(TranslatedLabelRelationManager::class))->toBe('Terms');

    app()->setLocale('es');
    expect(relationManagerLabel(TranslatedLabelRelationManager::class))->toBe('Términos');
});

it('prefers the config label over getPluralModelLabel()', function () {
    $managed = [TranslatedLabelRelationManager::class => ['label' => 'Configured']];

    expect(relationManagerLabel(TranslatedLabelRelationManager::class, $managed))->toBe('Configured');
});

it('labels a relation manager from its static $pluralModelLabel', function () {
    expect(relationManagerLabel(StaticLabelRelationManager::class))->toBe('Identificaciones');
});

it('falls back to the headline of the related model', function () {
    expect(relationManagerLabel(UnlabelledRelationManager::class))->toBe('Users');
});
