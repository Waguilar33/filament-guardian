<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Illuminate\Console\Command;

class FilamentGuardianCommand extends Command
{
    public $signature = 'filament-guardian';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
