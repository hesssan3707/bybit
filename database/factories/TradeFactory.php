<?php

namespace Database\Factories;

use App\Models\Trade;
use App\Models\UserExchange;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Trade::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_exchange_id' => UserExchange::factory(),
            'is_demo' => false,
            'symbol' => 'BTCUSDT',
            'side' => 'Buy',
            'order_type' => 'Market',
            'leverage' => $this->faker->randomFloat(2, 1, 10),
            'qty' => $this->faker->randomFloat(8, 0.001, 0.1),
            'avg_entry_price' => $this->faker->randomFloat(2, 20000, 30000),
            'avg_exit_price' => $this->faker->randomFloat(2, 20000, 30000),
            'pnl' => $this->faker->randomFloat(2, -100, 100),
            'order_id' => $this->faker->uuid,
            'closed_at' => now(),
        ];
    }
}
