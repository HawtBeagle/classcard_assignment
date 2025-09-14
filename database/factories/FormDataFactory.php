<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;        // ✅ import User
use App\Models\FormOption;  // ✅ import FormOption
use App\Models\FormData;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FormData>
 */
class FormDataFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'option_id' => FormOption::factory(),
        ];
    }
}
