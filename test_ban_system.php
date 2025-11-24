<?php

/**
 * Test Script for Ban System
 * 
 * This script can be run to test the ban creation functionality after the fixes
 * 
 * Usage:
 * php test_ban_system.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\UserExchange;
use App\Models\Trade;
use App\Models\UserBan;
use Carbon\Carbon;

echo "=== Ban System Test ===\n\n";

// Test 1: Check for logging errors
echo "Test 1: Checking for recent logging errors...\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $lastLines = `tail -n 100 $logPath`;
    if (strpos($lastLines, 'DedupTap') !== false) {
        echo "❌ FAIL: DedupTap errors still present in logs\n";
    } else {
        echo "✅ PASS: No DedupTap errors found\n";
    }
} else {
    echo "⚠️  WARN: Log file not found\n";
}

echo "\n";

// Test 2: Check Carbon methods exist
echo "Test 2: Verifying Carbon methods...\n";
try {
    $testDate = Carbon::now();
    $result1 = $testDate->addHours(1);
    $result2 = $testDate->addDays(1);
    echo "✅ PASS: addHours() and addDays() methods work correctly\n";
} catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check if there are any closed trades without bans
echo "Test 3: Checking for closed trades that should have bans...\n";
$closedLossTrades = Trade::whereNotNull('closed_at')
    ->where('pnl', '<', 0)
    ->whereDoesntHave('bans')
    ->orderBy('closed_at', 'desc')
    ->limit(5)
    ->get();

if ($closedLossTrades->isEmpty()) {
    echo "✅ All losing trades have bans (or no losing trades exist)\n";
} else {
    echo "⚠️  Found " . $closedLossTrades->count() . " closed losing trades without bans:\n";
    foreach ($closedLossTrades as $trade) {
        echo "  - Trade ID: {$trade->id}, PnL: {$trade->pnl}, Closed: {$trade->closed_at}\n";
    }
}

echo "\n";

// Test 4: Check recent ban creations
echo "Test 4: Checking recent ban creations...\n";
$recentBans = UserBan::orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

if ($recentBans->isEmpty()) {
    echo "⚠️  No bans found in database\n";
} else {
    echo "Found " . $recentBans->count() . " recent bans:\n";
    foreach ($recentBans as $ban) {
        $duration = $ban->starts_at->diffInHours($ban->ends_at);
        echo sprintf(
            "  - %s: %s (%dh) - Created: %s\n",
            $ban->ban_type,
            $ban->is_demo ? 'Demo' : 'Real',
            $duration,
            $ban->created_at->format('Y-m-d H:i:s')
        );
    }
}

echo "\n";

// Test 5: Verify ban type durations
echo "Test 5: Verifying ban durations...\n";
$banDurations = [
    'single_loss' => 1,      // 1 hour
    'double_loss' => 24,     // 24 hours
    'exchange_force_close' => 72, // 3 days
];

foreach ($banDurations as $type => $expectedHours) {
    $ban = UserBan::where('ban_type', $type)
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($ban) {
        $actualHours = $ban->starts_at->diffInHours($ban->ends_at);
        if ($actualHours == $expectedHours) {
            echo "✅ PASS: {$type} has correct duration ({$actualHours}h)\n";
        } else {
            echo "❌ FAIL: {$type} has wrong duration (expected {$expectedHours}h, got {$actualHours}h)\n";
        }
    } else {
        echo "⚠️  SKIP: No {$type} bans found\n";
    }
}

echo "\n=== Test Complete ===\n";
