<?php

namespace App\Logging;

use Monolog\Logger;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\RotatingFileHandler;

class DedupTap
{
    /**
     * Attach a deduplication handler to file-based loggers to suppress duplicates.
     */
    public function __invoke(IlluminateLogger $logger): void
    {
        $handlers = $logger->getLogger()->getHandlers();
        foreach ($handlers as $index => $handler) {
            if ($handler instanceof RotatingFileHandler) {
                // Configure store path, level, and TTL from environment
                // More aggressive deduplication to reduce log file size
                $store = storage_path('logs/.deduplication-store');
                $level = env('LOG_DEDUP_LEVEL', 'info'); // Changed to 'info' to dedupe more logs
                $ttl = (int) env('LOG_DEDUP_TTL', 3600); // Increased to 1 hour (3600 seconds)

                // Wrap the existing rotating file handler with a deduplication handler
                $handlers[$index] = new DeduplicationHandler($handler, $store, $level, $ttl, true);
                $logger->getLogger()->setHandlers($handlers);
                break;
            }
        }
    }
}