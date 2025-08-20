<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Http;

class TestNetworkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'network:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests network connectivity to external services.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('--- Testing Network Connectivity ---');

        // Test Google
        $this->line('1. Testing connection to https://www.google.com...');
        try {
            $response = Http::timeout(10)->get('https://www.google.com');
            if ($response->successful()) {
                $this->info('SUCCESS: Successfully connected to Google.');
            } else {
                $this->warn('FAILED: Received a non-successful status code: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('ERROR: Failed to connect to Google. Error: ' . $e->getMessage());
        }

        $this->line(''); // Newline for spacing

        // Test Bybit API
        $this->line('2. Testing connection to Bybit API (https://api.bybit.com)...');
        try {
            $bybitUrl = 'https://api.bybit.com/v5/market/time';
            $response = Http::timeout(10)->get($bybitUrl);
            $this->info('SUCCESS: Successfully connected to Bybit API.');
            $this->line('Status Code: ' . $response->status());
            $this->line('Response Body: ' . $response->body());
        } catch (\Exception $e) {
            $this->error('ERROR: Failed to connect to Bybit API. Error: ' . $e->getMessage());
        }

        $this->info('--- Test Complete ---');
        return 0;
    }
}
