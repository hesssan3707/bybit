<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserExchange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

class UserExchangeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UserExchange::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory()->create()->id,
            'exchange_name' => $this->faker->randomElement(['bybit', 'binance', 'bingx']),
            'api_key' => 'test_api_key_' . $this->faker->uuid,
            'api_secret' => 'test_api_secret_' . $this->faker->uuid,
            'demo_api_key' => 'demo_test_key_' . $this->faker->uuid,
            'demo_api_secret' => 'demo_test_secret_' . $this->faker->uuid,
            'is_demo_active' => true, // Use demo mode for testing
        ];
    }

    public function withExchangeName(string $exchangeName)
    {
        return $this->state(function (array $attributes) use ($exchangeName) {
            return [
                'exchange_name' => $exchangeName,
            ];
        });
    }
}
