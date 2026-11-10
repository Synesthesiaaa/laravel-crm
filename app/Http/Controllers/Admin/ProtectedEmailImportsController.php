<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailCampaignRequest;
use App\Models\ProtectedEmailImport;
use App\Services\Email\ProtectedEmailImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProtectedEmailImportsController extends Controller
{
    public function __construct(private ProtectedEmailImportService $imports) {}

    public function store(StoreEmailCampaignRequest $request)
    {
        $run = $this->imports->stage($request->user(), $request->validated(), $request->file('recipient_csv'), $request->file('personal_pdfs', []), $request->file('document_zip'), $request->file('shared_pdfs', []));
        if ($request->expectsJson()) {
            return response()->json($this->imports->present($run), 202)->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('admin.email-campaigns.protected-imports.show', $run)->with('success', 'Upload saved privately. Draft preparation runs in the background; no email has been approved or sent.');
    }

    public function show(Request $request, ProtectedEmailImport $protectedImport): View
    {
        $this->imports->authorize($request->user(), $protectedImport);

        return view('admin.email-campaigns.protected-import', ['run' => $this->imports->present($protectedImport), 'recent' => ProtectedEmailImport::where('user_id', $request->user()->id)->latest()->limit(20)->get()]);
    }

    public function status(Request $request, ProtectedEmailImport $protectedImport): JsonResponse
    {
        $this->imports->authorize($request->user(), $protectedImport);

        return response()->json($this->imports->present($protectedImport))->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, ProtectedEmailImport $protectedImport): JsonResponse
    {
        return response()->json($this->imports->present($this->imports->cancel($request->user(), $protectedImport)))->header('Cache-Control', 'private, no-store');
    }

    public function resume(Request $request, ProtectedEmailImport $protectedImport): JsonResponse
    {
        return response()->json($this->imports->present($this->imports->resume($request->user(), $protectedImport)), 202)->header('Cache-Control', 'private, no-store');
    }
}
