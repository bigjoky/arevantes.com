<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeDocument;
use App\Services\Ai\KnowledgeAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KnowledgeSourceController extends Controller
{
    public function show(Request $request, KnowledgeDocument $knowledgeDocument, int $version, KnowledgeAccess $access): Response
    {
        abort_unless($access->allows($request->user(), $knowledgeDocument), 404);
        $snapshot = $knowledgeDocument->versions()->where('version', $version)->firstOrFail();

        return response($snapshot->content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.str($knowledgeDocument->title)->slug().'-v'.$version.'.txt"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
