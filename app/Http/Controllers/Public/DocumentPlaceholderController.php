<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\DocumentsConsent\Contracts\PublicCourseDocuments;
use Illuminate\Http\Response;

final class DocumentPlaceholderController extends Controller
{
    public function __invoke(string $documentKey, PublicCourseDocuments $documents): Response
    {
        $documents->findByKey($documentKey);

        return response()
            ->view('public.document-unavailable', status: 404)
            ->header('Cache-Control', 'no-store');
    }
}
