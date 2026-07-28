<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\CourseCatalog\Contracts\CourseCatalog;
use App\Modules\CourseCatalog\Data\CourseSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CourseCatalogController extends Controller
{
    public function __invoke(Request $request, CourseCatalog $catalog): View
    {
        $search = $this->searchFrom($request);

        return view('public.course-catalog', [
            'filters' => [
                'year' => $request->query('year', ''),
                'month' => $request->query('month', ''),
                'course_type' => $request->query('course_type', ''),
                'center' => $request->query('center', ''),
            ],
            'search' => $search,
            'result' => $catalog->search($search),
        ]);
    }

    private function searchFrom(Request $request): CourseSearch
    {
        $errors = [];
        $year = $this->integerFilter($request->query('year'), 2020, 2100, 'year', $errors);
        $month = $this->integerFilter($request->query('month'), 1, 12, 'month', $errors);
        $courseType = $this->keyFilter($request->query('course_type'), 'course_type', $errors);
        $center = $this->keyFilter($request->query('center'), 'center', $errors);

        return new CourseSearch($year, $month, $courseType, $center, $errors);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function integerFilter(
        mixed $value,
        int $minimum,
        int $maximum,
        string $field,
        array &$errors,
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            ! is_string($value)
            || preg_match('/^\d+$/', $value) !== 1
            || (int) $value < $minimum
            || (int) $value > $maximum
        ) {
            $errors[$field] = 'ค่าตัวกรองไม่ถูกต้อง';

            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function keyFilter(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || preg_match('/^[a-z0-9-]{1,32}$/', $value) !== 1) {
            $errors[$field] = 'ค่าตัวกรองไม่ถูกต้อง';

            return null;
        }

        return $value;
    }
}
