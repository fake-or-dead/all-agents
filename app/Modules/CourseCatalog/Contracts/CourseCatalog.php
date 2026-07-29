<?php

namespace App\Modules\CourseCatalog\Contracts;

use App\Modules\CourseCatalog\Data\CourseSearch;
use App\Modules\CourseCatalog\Data\CourseSearchResult;
use App\Modules\CourseCatalog\Data\CourseSessionView;
use App\Modules\CourseCatalog\Data\EligibilityContext;

interface CourseCatalog
{
    public function search(CourseSearch $search): CourseSearchResult;

    public function session(string $code, EligibilityContext $context): ?CourseSessionView;
}
