<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\RotatingFileHandler;

class DedupTap
{
    /**
     * Attach a deduplication handler to file-based loggers to suppress duplicates.
     */
    public function __invoke(Logger $logger): void
    {
        $handlers = $logger->getHandlers();
        foreach ($handlers as $index => $handler) {
            if ($handler instanceof RotatingFileHandler) {
                // Configure store path, level, and TTL from environment
                $store = storage_path('logs/.deduplication-store');
                $level = env('LOG_DEDUP_LEVEL', 'warning');
                $ttl = (int) env('LOG_DEDUP_TTL', 300); // seconds

                // Wrap the existing rotating file handler with a deduplication handler
                $handlers[$index] = new DeduplicationHandler($handler, $store, $level, $ttl, true);
                $logger->setHandlers($handlers);
                break;
            }
        }
    }
}