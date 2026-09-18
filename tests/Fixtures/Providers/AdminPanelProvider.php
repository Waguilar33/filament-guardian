<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(FilamentGuardianPlugin::make());
    }
}
