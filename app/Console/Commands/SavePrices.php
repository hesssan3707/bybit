<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Price;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use GuzzleHttp\Client;

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

        $markets = [
            'BTCUSDT', 'ETHUSDT', 'CAKEUSDT', 'ATOMUSDT', 'SOLUSDT', 'ADAUSDT',
            'DOTUSDT', 'DOGEUSDT', 'XRPUSDT', 'LTCUSDT', 'LINKUSDT'
        ];

        $timeframes = ['5m', '15m', '1h', '4h', '1d'];
        $inactiveMarkets = ['AAVEUSDT', 'AVAXUSDT', 'FTMUSDT', 'NEARUSDT','UNIUSDT','SHIBUSDT'];
        $inactiveTimeframes = ['1m'];

        // Fetch latest timestamps for all markets and timeframes at once
        $this->info('Fetching latest timestamps for all markets...');
        $latestTimestamps = Price::select('market', 'timeframe', DB::raw('MAX(timestamp) as max_timestamp'))
            ->whereIn('market', $markets)
            ->whereIn('timeframe', $timeframes)
            ->groupBy('market', 'timeframe')
            ->get()
            ->keyBy(function ($item) {
                return $item->market . '-' . $item->timeframe;
            });
        $this->info('Finished fetching latest timestamps.');

        foreach ($markets as $market) {
            foreach ($timeframes as $timeframe) {
                DB::beginTransaction();
                try {
                    $key = $market . '-' . $timeframe;
                    $latestPrice = $latestTimestamps->get($key);
                    $startTime = null;
                    if ($latestPrice) {
                        $startTime = Carbon::parse($latestPrice->max_timestamp)->getTimestamp() * 1000 + 1;
                    }

                    $this->info("Fetching k-lines for {$market} on timeframe {$timeframe}...");
                    $klineData = $this->getKlines($market, $timeframe, 50, $startTime);

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
                    $errorMessage = "Failed to process {$market} on timeframe {$timeframe}: {$e->getMessage()}";
                    // Check if the exception message contains JSON, which might be the case for API errors
                    $decodedMessage = json_decode($e->getMessage(), true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMessage)) {
                        // If it's a JSON error from our service, log the details
                        $errorMessage .= " | Details: " . print_r($decodedMessage, true);
                    }
                    $this->error($errorMessage);
                    Log::error($errorMessage);
                }
            }
        }

        $this->info('Finished saving k-line prices.');
    }
    private function getKlines(string $symbol, string $interval, int $limit = 50, ?int $startTime = null): array
    {
        try {
            $query = [
                'symbol' => $symbol,
                'interval' => $interval,
            ];

            if ($startTime) {
                $query['startTime'] = $startTime;
            } else {
                $query['limit'] = $limit;
            }

            $client = new Client([
                'timeout' => 30,
                'base_uri' => 'https://api.binance.com',
            ]);
            $response = $client->get('/api/v3/klines', [
                'query' => $query
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'klines' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Binance get klines failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get klines: ' . $e->getMessage(),
            ];
        }
    }
}
