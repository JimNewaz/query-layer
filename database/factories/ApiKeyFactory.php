<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'key_prefix' => 'ql_'.Str::random(4),
            'key_hash' => hash('sha256', 'ql_'.Str::ulid()->toBase32()),
            'scopes' => ['connections' => '*'],
        ];
    }
}
