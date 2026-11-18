<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgentDataMasterRequest;
use App\Services\AgentDataMasterAccessService;
use App\Services\AgentDataMasterService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AgentDataMasterController extends Controller
{
    public function index(AgentDataMasterRequest $request, AgentDataMasterAccessService $access, AgentDataMasterService $records): View|Response
    {
        $campaign = (string) $request->session()->get('campaign');
        $forms = $access->allowedForms($campaign);
        $filters = $request->validated();
        $type = $filters['type'] ?? $forms->first()->form_code;
        $form = $forms->firstWhere('form_code', $type);
        if (! $form) {
            throw ValidationException::withMessages(['type' => 'Choose a form permitted for this campaign.']);
        }
        $filters['type'] = $type;
        $ownOnly = $access->settings($campaign)['own_submissions_only'];

        try {
            $page = $records->page($form, $request->user(), $ownOnly, $filters);
        } catch (QueryException $exception) {
            report($exception);

            return response()->view('agent.data-master-unavailable', compact('forms', 'type'), 503);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 503) {
                throw $exception;
            }

            return response()->view('agent.data-master-unavailable', compact('forms', 'type'), 503);
        }

        return view('agent.data-master', [
            'campaignName' => (string) $request->session()->get('campaign_name', $campaign),
            'forms' => $forms,
            'type' => $type,
            'ownOnly' => $ownOnly,
            'filters' => $filters,
            ...$page,
        ]);
    }
}
