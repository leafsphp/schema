<?php

namespace App\Models;

/**
 * Tiny model used to exercise model-based seeding (Schema::seed with a
 * `model` key calls Model::__seeder() to build each row).
 */
class Pet
{
    public static function __seeder(): array
    {
        return [
            'name' => 'pet-' . bin2hex(random_bytes(4)),
        ];
    }
}
