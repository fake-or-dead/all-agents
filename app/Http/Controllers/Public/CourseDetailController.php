<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\CourseCatalog\Contracts\CourseCatalog;
use App\Modules\CourseCatalog\Data\EligibilityContext;
use App\Modules\IdentityAccess\Contracts\ActorResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CourseDetailController extends Controller
{
    public function __invoke(
        Request $request,
        string $courseCode,
        CourseCatalog $catalog,
        ActorResolver $actors,
    ): View {
        $actor = $actors->resolve($request);
        $inputErrors = [];
        $context = new EligibilityContext(
            $this->age($request->query('age'), $inputErrors),
            $this->choice(
                $request->query('category'),
                ['female', 'male', 'monastic'],
                'category',
                $inputErrors,
            ),
            $this->choice(
                $request->query('applicant_type'),
                ['trainee', 'staff'],
                'applicant_type',
                $inputErrors,
            ),
            $actor?->id,
        );

        $view = $catalog->session($courseCode, $context);
        if ($view === null) {
            throw new NotFoundHttpException;
        }

        return view('public.course-detail', [
            'course' => $view,
            'eligibilityInput' => [
                'age' => $request->query('age', ''),
                'category' => $request->query('category', ''),
                'applicant_type' => $request->query('applicant_type', ''),
            ],
            'inputErrors' => $inputErrors,
        ]);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function age(mixed $value, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || preg_match('/^\d{1,3}$/', $value) !== 1 || (int) $value < 1 || (int) $value > 120) {
            $errors['age'] = 'อายุต้องเป็นตัวเลขระหว่าง 1–120 ปี';

            return null;
        }

        return (int) $value;
    }

    /**
     * @param  list<string>  $allowed
     * @param  array<string, string>  $errors
     */
    private function choice(mixed $value, array $allowed, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            $errors[$field] = 'ค่าที่เลือกไม่ถูกต้อง';

            return null;
        }

        return $value;
    }
}
