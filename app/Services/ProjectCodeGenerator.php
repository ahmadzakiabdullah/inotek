<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Project;

class ProjectCodeGenerator
{
    /**
     * Generate the next unused code while holding the category row lock.
     * Call this from inside a database transaction.
     */
    public function next(int $sessionId, int $categoryId): string
    {
        $category = Category::whereKey($categoryId)->lockForUpdate()->firstOrFail();
        $number = 1;

        do {
            $code = $category->code.'-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $number++;
        } while (Project::where('session_id', $sessionId)->where('pcode', $code)->exists());

        return $code;
    }
}
