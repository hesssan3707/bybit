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
        // Keep sessions for:
        // 1. Specific protected user IDs
        // 2. Crawler/Bot user agents (if they need to be preserved, though typically we want to delete them if old)
        //    Wait, the user said: "never delete specific sessions, for example sessions related to crawlers."
        //    So we must identifying crawlers by User-Agent or IP and protect them.
        
        $protectedUserIds = [1];
        
        // List of crawler keywords to identify and PROTECT
        $crawlerKeywords = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex'
        ];

        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('last_activity', '<', $expiration)
            ->where(function ($query) use ($protectedUserIds, $crawlerKeywords) {
                 // We WANT to delete if:
                 // 1. user_id is NOT in protected list AND
                 // 2. user_agent does NOT contain crawler keywords
                 
                 $query->where(function ($q) use ($protectedUserIds) {
                     $q->whereNull('user_id')
                       ->orWhereNotIn('user_id', $protectedUserIds);
                 });
                 
                 // Apply User-Agent protection check
                 // We delete ONLY if it does NOT match any crawler keyword
                 foreach ($crawlerKeywords as $keyword) {
                     $query->where('user_agent', 'not like', "%{$keyword}%");
                 }
            })
            ->delete();

        $this->info("Cleaned up {$deleted} expired session(s) from database.");

        return 0;
    }
}
