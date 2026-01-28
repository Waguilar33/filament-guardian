<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Spatie\Permission\Contracts\Role;
use Waguilar\FilamentGuardian\Facades\Guardian;

/** @method Role getRecord() */
abstract class BaseViewRole extends ViewRecord
{
    /**
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn (): bool => Guardian::isSuperAdminRole($this->getRecord())),
        ];
    }
}
