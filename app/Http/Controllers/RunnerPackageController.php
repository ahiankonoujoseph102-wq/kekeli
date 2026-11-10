<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\WebApps\HostingPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ZIP file for shared hosting, sent by the Runner in parts of 4 MB (signed requests).
 */
class RunnerPackageController extends Controller
{
    public function store(Request $request, Project $project, HostingPackage $packages): JsonResponse
    {
        abort_unless($project->isWebApp() && $project->app_stack === 'laravel', 404);
        $limits = config('kekeli.backups');

        $data = $request->validate([
            'partie' => ['required', 'integer', 'min:1', 'max:'.$limits['max_parts']],
            'parties' => ['required', 'integer', 'min:1', 'max:'.$limits['max_parts'], 'gte:partie'],
        ]);

        $content = $request->getContent();
        abort_if(strlen($content) > $limits['part_size'], 413);

        return response()->json(['ok' => true, 'complete' => $packages->storePart($project, (int) $data['partie'], (int) $data['parties'], $content)]);
    }
}
