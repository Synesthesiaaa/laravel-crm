<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserImportRequest;
use App\Models\UserImport;
use App\Services\Imports\UserImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportsController extends Controller
{
    public function __construct(private UserImportService $imports) {}

    public function store(StoreUserImportRequest $request): JsonResponse
    {
        $run = $this->imports->upload($request->user(), $request->file('csv'), $request->boolean('allow_privileged_roles'));

        return response()->json($this->imports->present($run), 202)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, UserImport $userImport): JsonResponse
    {
        $this->authorizeOwner($request, $userImport);

        return response()->json($this->imports->present($userImport))->header('Cache-Control', 'no-store');
    }

    public function start(Request $request, UserImport $userImport): JsonResponse
    {
        $this->authorizeOwner($request, $userImport);

        return response()->json($this->imports->present($this->imports->start($userImport)), 202)->header('Cache-Control', 'no-store');
    }

    public function cancel(Request $request, UserImport $userImport): JsonResponse
    {
        $this->authorizeOwner($request, $userImport);

        return response()->json($this->imports->present($this->imports->cancel($userImport)))->header('Cache-Control', 'no-store');
    }

    public function template(): BinaryFileResponse
    {
        return response()->download(base_path('docs/examples/users-import.csv'), 'users-template.csv', ['Cache-Control' => 'no-store']);
    }

    public function report(Request $request, UserImport $userImport): StreamedResponse
    {
        $this->authorizeOwner($request, $userImport);

        return response()->streamDownload(function () use ($userImport): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['record', 'errors'], ',', '"', '');
            foreach (DB::table('user_import_errors')->where('import_id', $userImport->id)->where('phase', $userImport->phase)->lazyById(500) as $error) {
                fputcsv($stream, [$error->record, $error->errors], ',', '"', '');
            }
            fclose($stream);
        }, 'user-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function authorizeOwner(Request $request, UserImport $run): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless((int) $run->user_id === (int) $request->user()->id, 404);
    }
}
