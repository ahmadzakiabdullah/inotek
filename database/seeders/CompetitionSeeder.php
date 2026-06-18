<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryRubricMapping;
use App\Models\CompetitionSession;
use App\Models\Rubric;
use App\Models\RubricItem;
use Illuminate\Database\Seeder;

class CompetitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Sessions
        $activeSession = CompetitionSession::create([
            'name' => 'Semester 1 2026/2027',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        $inactiveSession = CompetitionSession::create([
            'name' => 'Semester 2 2025/2026',
            'is_active' => false,
            'r2_locked' => false,
        ]);

        // 2. Create Categories for Active Session
        $c1 = Category::create([
            'session_id' => $activeSession->id,
            'code' => 'C1',
            'name' => 'Green Technology',
        ]);

        $c2 = Category::create([
            'session_id' => $activeSession->id,
            'code' => 'C2',
            'name' => 'Smart Infrastructure',
        ]);

        $c3 = Category::create([
            'session_id' => $activeSession->id,
            'code' => 'C3',
            'name' => 'Emerging Technology',
        ]);

        $c4 = Category::create([
            'session_id' => $activeSession->id,
            'code' => 'C4',
            'name' => 'Creative Digital & Media',
        ]);

        // 3. Create Rubrics
        $engRubric = Rubric::create([
            'name' => 'Standard Engineering Rubric',
            'description' => 'Used for assessing technical complexity, hardware designs, and eco-innovation solutions.',
        ]);

        $mediaRubric = Rubric::create([
            'name' => 'Digital Media Rubric',
            'description' => 'Focused on user experience, frontend/multimedia aesthetics, and creative design.',
        ]);

        // 4. Create Rubric Items
        // Standard Engineering Rubric Items
        RubricItem::create([
            'rubric_id' => $engRubric->id,
            'criteria_name' => 'Novelty & Innovation',
            'weight' => 0.30,
            'max_points' => 5,
        ]);
        RubricItem::create([
            'rubric_id' => $engRubric->id,
            'criteria_name' => 'Technical Complexity',
            'weight' => 0.30,
            'max_points' => 5,
        ]);
        RubricItem::create([
            'rubric_id' => $engRubric->id,
            'criteria_name' => 'Impact & Commercial Viability',
            'weight' => 0.20,
            'max_points' => 5,
        ]);
        RubricItem::create([
            'rubric_id' => $engRubric->id,
            'criteria_name' => 'Presentation & Demo',
            'weight' => 0.20,
            'max_points' => 5,
        ]);

        // Digital Media Rubric Items
        RubricItem::create([
            'rubric_id' => $mediaRubric->id,
            'criteria_name' => 'Creative Expression',
            'weight' => 0.40,
            'max_points' => 5,
        ]);
        RubricItem::create([
            'rubric_id' => $mediaRubric->id,
            'criteria_name' => 'Technical Execution',
            'weight' => 0.30,
            'max_points' => 5,
        ]);
        RubricItem::create([
            'rubric_id' => $mediaRubric->id,
            'criteria_name' => 'User Experience',
            'weight' => 0.30,
            'max_points' => 5,
        ]);

        // 5. Map Categories to Rubrics
        CategoryRubricMapping::create([
            'category_id' => $c1->id,
            'rubric_id' => $engRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c2->id,
            'rubric_id' => $engRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c3->id,
            'rubric_id' => $engRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c4->id,
            'rubric_id' => $mediaRubric->id,
        ]);
    }
}
