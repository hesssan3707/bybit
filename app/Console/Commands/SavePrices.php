<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Price;
use App\Services\Exchanges\BinanceApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SavePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:save';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save k-line prices for a list of markets from Binance.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to save k-line prices from Binance...');
        Log::info('prices:save command started.');

        $markets = [
            'BTCUSDT', 'ETHUSDT', 'CAKEUSDT', 'ATOMUSDT', 'SOLUSDT', 'ADAUSDT',
            'DOTUSDT', 'DOGEUSDT', 'SHIBUSDT', 'MATICUSDT', 'LTCUSDT', 'LINKUSDT',
            'UNIUSDT', 'AAVEUSDT', 'AVAXUSDT', 'FTMUSDT', 'NEARUSDT'
        ];

        $exchangeService = new BinanceApiService();
        $timeframes = ['1m', '5m', '15m', '1h', '4h', '1d'];

        foreach ($markets as $market) {
            foreach ($timeframes as $timeframe) {
                DB::beginTransaction();
                try {
                    $latestPrice = Price::where('market', $market)->where('timeframe', $timeframe)->orderBy('timestamp', 'desc')->first();
                    $startTime = $latestPrice ? ($latestPrice->timestamp->getTimestamp() * 1000) + 1 : null;

                    $this->info("Fetching k-lines for {$market} on timeframe {$timeframe}...");
                    $klineData = $exchangeService->getKlines($market, $timeframe, 50, $startTime);

                    if (!$klineData['success']) {
                        throw new \Exception($klineData['message']);
                    }

                    $pricesToInsert = [];
                    foreach ($klineData['klines'] as $kline) {
                        $pricesToInsert[] = [
                            'market' => $market,
                            'timeframe' => $timeframe,
                            'price' => $kline[4], // Close price
                            'timestamp' => Carbon::createFromTimestampMs($kline[0]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (!empty($pricesToInsert)) {
                        Price::insert($pricesToInsert);
                        $this->info("Saved " . count($pricesToInsert) . " new k-line prices for {$market} on timeframe {$timeframe}.");
                        Log::info("Saved " . count($pricesToInsert) . " new k-line prices for {$market} on timeframe {$timeframe}.");
                    }

                    // Prune old records, keeping the last 50
                    $idsToKeep = Price::where('market', $market)
                        ->where('timeframe', $timeframe)
                        ->orderBy('timestamp', 'desc')
                        ->take(50)
                        ->pluck('id');

                    Price::where('market', $market)
                        ->where('timeframe', $timeframe)
                        ->whereNotIn('id', $idsToKeep)
                        ->delete();

                    $this->info("Pruned old records for {$market} on timeframe {$timeframe}.");

                    DB::commit();

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Failed to process {$market} on timeframe {$timeframe}: {$e->getMessage()}");
                    Log::error("Failed to process {$market} on timeframe {$timeframe}: {$e->getMessage()}");
                }
            }
        }

        $this->info('Finished saving k-line prices.');
        Log::info('prices:save command finished.');
    }
}
