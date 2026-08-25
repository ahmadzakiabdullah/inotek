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

        $c6 = Category::create([
            'session_id' => $activeSession->id,
            'code' => 'C6',
            'name' => 'Open Innovation',
        ]);

        $c5 = Category::create([
            'session_id' => $activeSession->id,
            'code' => 'C5',
            'name' => 'Industry Design Project (IDP)',
        ]);

        // 3. Create Rubrics
        $standardRubric = Rubric::create([
            'name' => 'Standard Rubric',
            'description' => 'Official standard rubric for categories C1, C2, C3, C4, and C6. Total weight is intentionally 105%.',
        ]);

        $officialItems = [
            ['section' => 'A', 'code' => 'A1', 'criteria_name' => 'Project Functionality', 'description' => 'Grading Scale: [LO2, PO3]', 'weight' => 0.30, 'scale_descriptions' => [0 => 'No effort to show functionality at all', 1 => 'Not functioning but showed video recording', 2 => 'Demonstrated but not functioning properly', 3 => 'Demonstrated the functionality but not as expected', 4 => 'Demonstrated the functionality up to the expectation', 5 => 'Demonstrated the functionality beyond the expectation']],
            ['section' => 'A', 'code' => 'A2', 'criteria_name' => 'Project Innovation and Quality', 'description' => 'Grading Scale: [LO8, PO10] EA2', 'weight' => 0.30, 'scale_descriptions' => [0 => 'Poor/No innovation', 1 => 'Very basic innovation', 2 => 'Moderate quality and innovation', 3 => 'Good innovation with room for improvement', 4 => 'High quality innovation', 5 => 'Outstanding and highly advanced innovation']],
            ['section' => 'A', 'code' => 'A3', 'criteria_name' => 'Sustainability, Social and Environmental Impact', 'description' => 'Grading Scale: [LO6, PO7] EA4', 'weight' => 0.10],
            ['section' => 'A', 'code' => 'A4', 'criteria_name' => 'Ability to Address Social Design Criteria', 'description' => 'Grading Scale: [LO5, PO6]', 'weight' => 0.10],
            ['section' => 'A', 'code' => 'A5', 'criteria_name' => 'Potential Application', 'description' => 'Grading Scale: [LO6, PO7]', 'weight' => 0.05],
            ['section' => 'B', 'code' => 'B1', 'criteria_name' => 'Pitching Skill', 'description' => 'Presenter Appearance & Professionalism', 'weight' => 0.10],
            ['section' => 'B', 'code' => 'B2', 'criteria_name' => 'Project Poster / Material', 'description' => 'Evaluate the quality and clarity of the project poster/materials.', 'weight' => 0.05],
            ['section' => 'C', 'code' => 'C1', 'criteria_name' => 'Bonus', 'description' => 'Marketability or Ready to Be Commercialized', 'weight' => 0.05],
        ];

        foreach ($officialItems as $item) {
            RubricItem::create(array_merge($item, [
                'rubric_id' => $standardRubric->id,
                'max_points' => 5,
            ]));
        }

        $idpRubric = Rubric::create([
            'name' => 'IDP Rubric',
            'description' => 'Specific rubric for Category C5 (IDP). Total weight is 100%.',
        ]);

        foreach ([
            ['section' => 'A', 'code' => 'A1', 'criteria_name' => 'Project Design and Functionality', 'description' => 'WP1, WP2'],
            ['section' => 'A', 'code' => 'A2', 'criteria_name' => 'Project Complexity and Quality', 'description' => 'WP1, WP2'],
            ['section' => 'A', 'code' => 'A3', 'criteria_name' => 'Social, Health, Safety, Legal and Cultural Issues', 'description' => 'WP2'],
            ['section' => 'A', 'code' => 'A4', 'criteria_name' => 'Sustainability and Marketability Potential', 'description' => 'WP6'],
            ['section' => 'B', 'code' => 'B1', 'criteria_name' => 'WP2 - Presentation', 'description' => 'Apply ethical principles and commit to the professional ethics and responsibilities and norms of engineering practices'],
        ] as $item) {
            RubricItem::create(array_merge($item, [
                'rubric_id' => $idpRubric->id,
                'weight' => 0.20,
                'max_points' => 5,
            ]));
        }

        $diplomaRubric = Rubric::create([
            'name' => 'Diploma Rubric',
            'description' => 'Specific rubric for diploma-level projects. Total weight is 100%.',
        ]);

        foreach ([
            ['section' => 'A', 'code' => 'A1', 'criteria_name' => 'Product Functionality', 'description' => '[CLO1, PO3] DP1, 3, 4', 'weight' => 0.25],
            ['section' => 'A', 'code' => 'A2', 'criteria_name' => 'Project Complexity and Quality', 'description' => '[CLO2, PO4] DP1, 3, 4', 'weight' => 0.30],
            ['section' => 'A', 'code' => 'A3', 'criteria_name' => 'Sustainable Design', 'description' => '[CLO4, PO7] DP1, 3, 4', 'weight' => 0.20],
            ['section' => 'A', 'code' => 'A4', 'criteria_name' => 'Marketability / Business Opportunity', 'description' => '[CLO8, PO11]', 'weight' => 0.10],
            ['section' => 'B', 'code' => 'B1', 'criteria_name' => 'Appearance and Readiness', 'description' => '[CLO5, PO8]', 'weight' => 0.05],
            ['section' => 'B', 'code' => 'B2', 'criteria_name' => 'Presentation Skills Related to Well-Defined Engineering Activities', 'description' => '[CLO7, PO10] NA2, 3', 'weight' => 0.05],
            ['section' => 'B', 'code' => 'B3', 'criteria_name' => 'Q&A Related to Well-Defined Engineering Activities', 'description' => '[CLO7, PO10] NA2, 3', 'weight' => 0.05],
        ] as $item) {
            RubricItem::create(array_merge($item, [
                'rubric_id' => $diplomaRubric->id,
                'max_points' => 5,
            ]));
        }

        // 5. Map Categories to Rubrics
        CategoryRubricMapping::create([
            'category_id' => $c1->id,
            'rubric_id' => $standardRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c2->id,
            'rubric_id' => $standardRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c3->id,
            'rubric_id' => $standardRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c4->id,
            'rubric_id' => $standardRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c6->id,
            'rubric_id' => $standardRubric->id,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $c5->id,
            'rubric_id' => $idpRubric->id,
        ]);
    }
}
