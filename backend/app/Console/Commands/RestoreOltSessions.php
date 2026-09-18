<?php

namespace App\Console\Commands;

use App\Models\Olt;
use App\Services\OltSessionService;
use Illuminate\Console\Command;

class RestoreOltSessions extends Command
{
    protected $signature = 'olts:restore-sessions';
    protected $description = 'Restore OLT SSH sessions requested before the session service restarted';

    public function handle(OltSessionService $sessions): int
    {
        Olt::query()->where('session_requested', true)->each(function (Olt $olt) use ($sessions): void {
            try { $sessions->start($olt); } catch (\Throwable $exception) { $this->warn("{$olt->name}: {$exception->getMessage()}"); }
        });
        return self::SUCCESS;
    }
}
