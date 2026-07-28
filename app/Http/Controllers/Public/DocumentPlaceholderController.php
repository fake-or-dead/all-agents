<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DocumentPlaceholderController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('public.document-unavailable', status: 404)
            ->header('Cache-Control', 'no-store');
    }
}
