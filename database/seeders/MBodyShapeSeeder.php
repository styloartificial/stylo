<?php

namespace Database\Seeders;

use App\Models\MBodyShape;
use Illuminate\Database\Seeder;

class MBodyShapeSeeder extends Seeder
{
    public function run(): void
    {
        $bodyShapes = [
            [
                'title' => 'Hourglass',
                'description' => 'Bahu dan pinggul seimbang dengan pinggang yang lebih kecil.'
            ],
            [
                'title' => 'Pear',
                'description' => 'Pinggul lebih lebar dari bahu, pinggang terdefinisi.'
            ],
            [
                'title' => 'Apple',
                'description' => 'Bahu lebih lebar, badan bagian tengah lebih berisi.'
            ],
            [
                'title' => 'Rectangle',
                'description' => 'Bahu, pinggang, dan pinggul hampir sama lebarnya.'
            ],
            [
                'title' => 'Inverted Triangle',
                'description' => 'Bahu lebih lebar dari pinggul.'
            ],
            [
                'title' => 'Oval',
                'description' => 'Bagian tengah tubuh lebih lebar, bahu dan pinggul lebih sempit.'
            ],
        ];

        foreach ($bodyShapes as $shape) {
            MBodyShape::create($shape);
        }
    }
}