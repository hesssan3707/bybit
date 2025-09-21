<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserExchange;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@trading.local',
            'username' => 'admin@trading.local',
            'email_verified_at' => now(),
            'password' => Hash::make('admin123'),
            'is_active' => true,
            'activated_at' => now(),
            'role' => 'admin',
        ]);

        echo "Created admin user:\n";
        echo "Email: admin@trading.local\n";
        echo "Password: admin123\n\n";

        // Create demo user
        $demoUser = User::create([
            'name' => 'Demo User',
            'email' => 'demo@trading.local',
            'username' => 'demo@trading.local',
            'email_verified_at' => now(),
            'password' => Hash::make('demo123'),
            'is_active' => true,
            'activated_at' => now(),
            'activated_by' => $admin->id,
            'role' => 'user',
        ]);

        echo "Created demo user:\n";
        echo "Email: demo@trading.local\n";
        echo "Password: demo123\n\n";

        // Create sample exchanges for demo user
        $bybitExchange = UserExchange::create([
            'user_id' => $demoUser->id,
            'exchange_name' => 'bybit',
            'api_key' => encrypt('demo_api_key'),
            'api_secret' => encrypt('demo_api_secret'),
            'demo_api_key' => encrypt('demo_bybit_key'),
            'demo_api_secret' => encrypt('demo_bybit_secret'),
            'is_active' => true,
            'is_default' => true,
            'status' => 'approved',
            'activation_requested_at' => now(),
            'activated_at' => now(),
            'activated_by' => $admin->id,
            'user_reason' => 'Demo account for testing',
            'admin_notes' => 'Approved for demo purposes',
        ]);

        $bingxExchange = UserExchange::create([
            'user_id' => $demoUser->id,
            'exchange_name' => 'bingx',
            'api_key' => encrypt('demo_api_key'),
            'api_secret' => encrypt('demo_api_secret'),
            'demo_api_key' => encrypt('demo_bingx_key'),
            'demo_api_secret' => encrypt('demo_bingx_secret'),
            'is_active' => true,
            'is_default' => false,
            'status' => 'approved',
            'activation_requested_at' => now(),
            'activated_at' => now(),
            'activated_by' => $admin->id,
            'user_reason' => 'Demo account for testing',
            'admin_notes' => 'Approved for demo purposes',
        ]);

        $binanceExchange = UserExchange::create([
            'user_id' => $demoUser->id,
            'exchange_name' => 'binance',
            'api_key' => encrypt('demo_api_key'),
            'api_secret' => encrypt('demo_api_secret'),
            'demo_api_key' => encrypt('demo_binance_key'),
            'demo_api_secret' => encrypt('demo_binance_secret'),
            'is_active' => true,
            'is_default' => false,
            'status' => 'approved',
            'activation_requested_at' => now(),
            'activated_at' => now(),
            'activated_by' => $admin->id,
            'user_reason' => 'Demo account for testing',
            'admin_notes' => 'Approved for demo purposes',
        ]);

        // Update demo user's current exchange (Bybit as default)
        $demoUser->update(['current_exchange_id' => $bybitExchange->id]);

        echo "Created demo exchanges for demo user (Bybit, BingX, Binance)\n";
        echo "\nYou can now:\n";
        echo "1. Login as admin to manage users and exchanges\n";
        echo "2. Login as demo user to test trading features\n";
        echo "3. Register new users who will need exchange approval\n\n";
        
        echo "Note: The demo exchange uses fake API credentials.\n";
        echo "For real trading, users need to provide actual exchange API keys.\n";
    }
}