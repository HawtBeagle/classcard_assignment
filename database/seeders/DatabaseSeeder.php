<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\FormData;
use App\Models\FormOption;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create 5 tenants
        User::factory(50)->create()->each(function ($user) {
            $options = FormOption::factory(10)->create();
            foreach ($options as $option) {
                FormData::factory()->create([
                    'user_id' => $user->id,
                    'option_id' => $option->id
                ]);
            }
        });
    }
    
}
