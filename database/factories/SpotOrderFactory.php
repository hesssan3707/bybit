<?php

namespace Database\Factories;

use App\Models\SpotOrder;
use App\Models\UserExchange;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpotOrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SpotOrder::class;

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
            'base_coin' => 'BTC',
            'quote_coin' => 'USDT',
            'side' => 'Buy',
            'order_type' => 'Limit',
            'qty' => $this->faker->randomFloat(8, 0.001, 0.1),
            'price' => $this->faker->randomFloat(2, 20000, 30000),
            'time_in_force' => 'GTC',
            'status' => 'New',
        ];
    }
}
