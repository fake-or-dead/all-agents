<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\CourseCatalog\Contracts\CourseCatalog;
use App\Modules\CourseCatalog\Data\EligibilityContext;
use App\Modules\IdentityAccess\Contracts\ApplicantIdentityResolver;
use App\Support\ThaiDateFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CourseDetailController extends Controller
{
    public function show(
        string $courseCode,
        CourseCatalog $catalog,
        ThaiDateFormatter $dates,
    ): View {
        return $this->view($courseCode, $catalog, $dates, new EligibilityContext(null, null, null, null), [], [
            'age' => '',
            'category' => '',
            'applicant_type' => '',
        ]);
    }

    public function assess(
        Request $request,
        string $courseCode,
        CourseCatalog $catalog,
        ApplicantIdentityResolver $identities,
        ThaiDateFormatter $dates,
    ): Response {
        $identity = $identities->resolve($request);
        $inputErrors = [];
        $context = new EligibilityContext(
            $this->age($request->input('age'), $inputErrors),
            $this->choice(
                $request->input('category'),
                ['female', 'male', 'monastic'],
                'category',
                $inputErrors,
            ),
            $this->choice(
                $request->input('applicant_type'),
                ['trainee', 'staff'],
                'applicant_type',
                $inputErrors,
            ),
            $identity?->personId,
        );

        $view = $this->view($courseCode, $catalog, $dates, $context, $inputErrors, [
            'age' => $request->input('age', ''),
            'category' => $request->input('category', ''),
            'applicant_type' => $request->input('applicant_type', ''),
        ]);

        return response()
            ->view($view->name(), $view->getData())
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * @param  array<string, string>  $inputErrors
     * @param  array{age: mixed, category: mixed, applicant_type: mixed}  $eligibilityInput
     */
    private function view(
        string $courseCode,
        CourseCatalog $catalog,
        ThaiDateFormatter $dates,
        EligibilityContext $context,
        array $inputErrors,
        array $eligibilityInput,
    ): View {
        $view = $catalog->session($courseCode, $context);
        if ($view === null) {
            throw new NotFoundHttpException;
        }

        return view('public.course-detail', [
            'course' => $view,
            'displayDates' => [
                'starts_on' => $dates->date(
                    (string) $view->session['starts_on'],
                    (string) $view->session['timezone'],
                ),
                'ends_on' => $dates->date(
                    (string) $view->session['ends_on'],
                    (string) $view->session['timezone'],
                ),
                'registration_opens_at' => $dates->dateTime(
                    (string) $view->session['registration_opens_at'],
                    (string) $view->session['timezone'],
                ),
                'registration_closes_at' => $dates->dateTime(
                    (string) $view->session['registration_closes_at'],
                    (string) $view->session['timezone'],
                ),
            ],
            'eligibilityInput' => $eligibilityInput,
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
