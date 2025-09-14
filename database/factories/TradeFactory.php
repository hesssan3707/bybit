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
            'symbol' => 'BTC/USDT',
            'trade_id' => $this->faker->uuid,
            'order_id' => $this->faker->uuid,
            'price' => $this->faker->randomFloat(2, 20000, 30000),
            'quantity' => $this->faker->randomFloat(8, 0.001, 0.1),
            'commission' => $this->faker->randomFloat(8, 0.0001, 0.001),
            'commission_asset' => 'USDT',
            'side' => 'buy',
            'taker_or_maker' => 'taker',
            'timestamp' => now(),
        ];
    }
}
