<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Log;

class ValidateActiveExchanges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchanges:validate-active 
                            {--force : Force re-validation even for recently validated exchanges}
                            {--exchange= : Validate specific exchange by name (bybit, binance, bingx)}
                            {--user= : Validate exchanges for specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate all active exchanges and update their access permissions in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting validation of active exchanges...');
        
        $force = $this->option('force');
        $exchangeFilter = $this->option('exchange');
        $userFilter = $this->option('user');
        
        // Build query
        $query = UserExchange::where('is_active', true);
        
        if ($exchangeFilter) {
            $query->where('exchange_name', $exchangeFilter);
        }
        
        if ($userFilter) {
            $query->where('user_id', $userFilter);
        }
        
        // If not forcing, only validate exchanges that haven't been validated recently
        if (!$force) {
            $query->where(function($q) {
                $q->whereNull('last_validation_at')
                  ->orWhere('last_validation_at', '<', now()->subHours(24));
            });
        }
        
        $exchanges = $query->with('user')->get();
        
        if ($exchanges->isEmpty()) {
            $this->info('No exchanges found that need validation.');
            return 0;
        }
        
        $this->info("Found {$exchanges->count()} exchanges to validate");
        
        $progressBar = $this->output->createProgressBar($exchanges->count());
        $progressBar->start();
        
        $successCount = 0;
        $failureCount = 0;
        $results = [];
        
        foreach ($exchanges as $exchange) {
            try {
                $this->validateExchange($exchange);
                $successCount++;
                $results[] = [
                    'status' => 'success',
                    'exchange' => $exchange->exchange_display_name,
                    'user' => $exchange->user->email,
                    'spot_access' => $exchange->fresh()->spot_access ? 'Yes' : 'No',
                    'futures_access' => $exchange->fresh()->futures_access ? 'Yes' : 'No',
                    'ip_access' => $exchange->fresh()->ip_access ? 'Yes' : 'No'
                ];
            } catch (\Exception $e) {
                $failureCount++;
                $results[] = [
                    'status' => 'error',
                    'exchange' => $exchange->exchange_display_name,
                    'user' => $exchange->user->email,
                    'error' => $e->getMessage()
                ];
                
                Log::error('Exchange validation failed', [
                    'exchange_id' => $exchange->id,
                    'exchange_name' => $exchange->exchange_name,
                    'user_exchange_id' => $exchange->id,
                    'user_id' => $exchange->user_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Display results
        $this->info("Validation completed:");
        $this->info("✓ Successful: {$successCount}");
        $this->error("✗ Failed: {$failureCount}");
        
        // Display detailed results table
        if (!empty($results)) {
            $this->newLine();
            $this->table(
                ['Status', 'Exchange', 'User', 'Details'],
                array_map(function($result) {
                    if ($result['status'] === 'success') {
                        $details = "Spot: {$result['spot_access']} | Futures: {$result['futures_access']} | IP: {$result['ip_access']}";
                    } else {
                        $details = "Error: " . substr($result['error'], 0, 50) . '...';
                    }
                    
                    return [
                        $result['status'] === 'success' ? '✓' : '✗',
                        $result['exchange'],
                        $result['user'],
                        $details
                    ];
                }, $results)
            );
        }
        
        $this->newLine();
        $this->info('All validation results have been saved to the database.');
        
        return 0;
    }
    
    /**
     * Validate a single exchange
     */
    private function validateExchange(UserExchange $exchange)
    {
        $this->line("\nValidating {$exchange->exchange_display_name} for {$exchange->user->email}...");
        
        $exchangeService = ExchangeFactory::create(
            $exchange->exchange_name,
            $exchange->api_key,
            $exchange->api_secret
        );
        
        $validation = $exchangeService->validateAPIAccess();
        
        // Log the validation attempt
        Log::info('Exchange validation performed', [
            'exchange_id' => $exchange->id,
            'exchange_name' => $exchange->exchange_name,
            'user_exchange_id' => $exchange->id,
            'user_id' => $exchange->user_id,
            'spot_success' => $validation['spot']['success'] ?? false,
            'futures_success' => $validation['futures']['success'] ?? false,
            'ip_success' => $validation['ip']['success'] ?? false,
            'overall_success' => $validation['overall'] ?? false
        ]);
        
        // Generic handling for validation details
        $this->line("  Validation details:");
        $this->line("    Spot: " . ($validation['spot']['success'] ? 'Success' : 'Failed') . " - " . ($validation['spot']['message'] ?? 'No message'));
        $this->line("    Futures: " . ($validation['futures']['success'] ? 'Success' : 'Failed') . " - " . ($validation['futures']['message'] ?? 'No message'));
        $this->line("    IP: " . ($validation['ip']['success'] ? 'Success' : 'Failed') . " - " . ($validation['ip']['message'] ?? 'No message'));

        // Log extra details if they exist (e.g., account type)
        $spotDetails = $validation['spot']['details'] ?? [];
        if (isset($spotDetails['account_type'])) {
            $this->line("    Account Type: " . $spotDetails['account_type']);
        }
        
        // Update validation results
        $exchange->updateValidationResults($validation);
        
        // Refresh and show final access status
        $exchange->refresh();
        $this->line("  Final access: Spot={$exchange->spot_access}, Futures={$exchange->futures_access}, IP={$exchange->ip_access}");
    }
}