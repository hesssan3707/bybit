<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\UserExchange;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_exchange_id' => UserExchange::factory(),
            'order_id' => $this->faker->uuid,
            'order_link_id' => $this->faker->uuid,
            'symbol' => 'BTC/USDT',
            'entry_price' => $this->faker->randomFloat(4, 20000, 30000),
            'tp' => $this->faker->randomFloat(4, 30000, 40000),
            'sl' => $this->faker->randomFloat(4, 10000, 20000),
            'steps' => 1,
            'expire_minutes' => $this->faker->optional(0.7)->randomElement([15, 30, 60, 120]),
            'status' => 'pending',
            'side' => 'buy',
            'amount' => $this->faker->randomFloat(8, 0.001, 0.1),
        ];
    }
}
