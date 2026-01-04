<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $watchers = DB::table('users')
                ->where('role', 'watcher')
                ->get();

            foreach ($watchers as $user) {
                $email = (string) ($user->email ?? '');
                $username = (string) ($user->username ?? '');

                $cleanEmail = preg_replace('/^watcher\s*-\s*/i', '', $email);
                $cleanUsername = preg_replace('/^watcher\s*-\s*/i', '', $username);

                $newEmail = $cleanEmail !== '' ? 'investor-' . $cleanEmail : $email;
                $newUsername = $cleanUsername !== '' ? 'investor-' . $cleanUsername : $username;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => $newEmail,
                        'username' => $newUsername,
                        'role' => 'investor',
                    ]);
            }
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'investor') DEFAULT 'user'");

        if (!Schema::hasTable('investor_wallets')) {
            Schema::create('investor_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('investor_user_id');
                $table->string('currency', 16)->default('USDT');
                $table->decimal('balance', 24, 8)->default(0);
                $table->timestamps();

                $table->foreign('investor_user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->unique(['investor_user_id', 'currency']);
            });
        }

        DB::table('users')
            ->where('role', 'investor')
            ->orderBy('id')
            ->chunk(100, function ($users) {
                $now = now();
                $rows = [];

                foreach ($users as $user) {
                    $exists = DB::table('investor_wallets')
                        ->where('investor_user_id', $user->id)
                        ->where('currency', 'USDT')
                        ->exists();

                    if (!$exists) {
                        $rows[] = [
                            'investor_user_id' => $user->id,
                            'currency' => 'USDT',
                            'balance' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('investor_wallets')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'watcher') DEFAULT 'user'");

        DB::transaction(function () {
            $investors = DB::table('users')
                ->where('role', 'investor')
                ->get();

            foreach ($investors as $user) {
                $email = (string) ($user->email ?? '');
                $username = (string) ($user->username ?? '');

                $cleanEmail = preg_replace('/^investor\s*-\s*/i', '', $email);
                $cleanUsername = preg_replace('/^investor\s*-\s*/i', '', $username);

                $newEmail = $cleanEmail !== '' ? 'watcher-' . $cleanEmail : $email;
                $newUsername = $cleanUsername !== '' ? 'watcher-' . $cleanUsername : $username;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => $newEmail,
                        'username' => $newUsername,
                        'role' => 'watcher',
                    ]);
            }
        });
    }
};

