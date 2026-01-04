<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDatabaseSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired sessions from database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get session lifetime in minutes from config
        $lifetime = config('session.lifetime', 120);

        // Calculate the expiration timestamp
        $expiration = now()->subMinutes($lifetime)->timestamp;

        // Delete expired sessions from database
        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('last_activity', '<', $expiration)
            ->delete();

        $this->info("Cleaned up {$deleted} expired session(s) from database.");

        return 0;
    }
}
