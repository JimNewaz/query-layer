<?php

namespace Database\Factories;

use App\Models\Connection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    public function definition(): array
    {
        return fake()->randomElement([
            $this->postgresDefinition(),
            $this->mysqlDefinition(),
        ]);
    }

    public function postgres(): static
    {
        return $this->state(fn (): array => $this->postgresDefinition());
    }

    public function mysql(): static
    {
        return $this->state(fn (): array => $this->mysqlDefinition());
    }

    /**
     * @return array<string, mixed>
     */
    private function postgresDefinition(): array
    {
        return [
            'name' => 'conn-'.str()->ulid()->toBase32(),
            'driver' => 'postgres',
            'host' => fake()->domainName(),
            'port' => 5432,
            'database_name' => 'ecommerce',
            'username' => 'querylayer_ro',
            'password' => 'secret',
            'ssl_mode' => null,
            'status' => 'unverified',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mysqlDefinition(): array
    {
        return [
            'name' => 'conn-'.str()->ulid()->toBase32(),
            'driver' => 'mysql',
            'host' => fake()->domainName(),
            'port' => 3306,
            'database_name' => 'ecommerce',
            'username' => 'querylayer_ro',
            'password' => 'secret',
            'ssl_mode' => null,
            'status' => 'unverified',
        ];
    }
}
