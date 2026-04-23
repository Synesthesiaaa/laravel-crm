<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentCallDisposition;
use App\Models\User;
use App\Repositories\CampaignRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, CampaignRepository $campaigns): JsonResponse
    {
        $q = trim($request->get('q', ''));
        $campaign = session('campaign');

        if (strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $groups = [];

        // Search disposition records (leads)
        $records = AgentCallDisposition::where('campaign_code', $campaign)
            ->where(function ($query) use ($q) {
                $query->where('phone_number', 'like', "%{$q}%")
                    ->orWhere('agent', 'like', "%{$q}%")
                    ->orWhere('disposition_code', 'like', "%{$q}%");
                if (is_numeric($q)) {
                    $query->orWhere('lead_pk', (int) $q);
                }
            })
            ->limit(5)
            ->get();

        if ($records->isNotEmpty()) {
            $groups[] = [
                'label' => 'Agent Call Records',
                'items' => $records->map(fn ($r) => [
                    'title' => $r->lead_pk ?? $r->phone_number ?? '—',
                    'subtitle' => "Agent: {$r->agent} · {$r->called_at?->format('Y-m-d')}",
                    'url' => route('admin.agent-records.index'),
                ])->values()->all(),
            ];
        }

        // Search users (Super Admin only)
        if (auth()->user()?->isSuperAdmin()) {
            $users = User::where('username', 'like', "%{$q}%")
                ->orWhere('full_name', 'like', "%{$q}%")
                ->limit(4)
                ->get();

            if ($users->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Users',
                    'items' => $users->map(fn ($u) => [
                        'title' => $u->full_name ?? $u->username,
                        'subtitle' => $u->role,
                        'url' => route('admin.users.index'),
                    ])->values()->all(),
                ];
            }
        }

        // Quick navigation results
        $navLinks = [
            'dashboard' => ['title' => 'Dashboard',         'url' => route('dashboard'),                 'keywords' => ['dash', 'home']],
            'records' => ['title' => 'Call History',       'url' => route('records.index'),             'keywords' => ['call', 'history', 'record']],
            'agent' => ['title' => 'Agent Screen',       'url' => route('agent.index'),               'keywords' => ['agent', 'softphone', 'dial']],
            'admin' => ['title' => 'Admin Dashboard',    'url' => route('admin.dashboard'),           'keywords' => ['admin', 'manage', 'mgt']],
            'users' => ['title' => 'User Access',        'url' => route('admin.users.index'),         'keywords' => ['user', 'access', 'staff']],
            'campaigns' => ['title' => 'Campaigns',          'url' => route('admin.campaigns.index'),     'keywords' => ['campaign']],
            'disposition' => ['title' => 'Agent Call Records', 'url' => route('admin.agent-records.index'), 'keywords' => ['disposition', 'disp', 'agent call']],
            'extraction' => ['title' => 'Data Extraction',    'url' => route('admin.extraction.index'),   'keywords' => ['extract', 'export', 'csv']],
        ];

        $navMatches = [];
        foreach ($navLinks as $nav) {
            foreach ($nav['keywords'] as $kw) {
                if (str_contains(strtolower($kw), strtolower($q)) || str_contains(strtolower($nav['title']), strtolower($q))) {
                    $navMatches[] = ['title' => $nav['title'], 'subtitle' => null, 'url' => $nav['url']];

                    break;
                }
            }
        }

        if ($navMatches) {
            $groups[] = ['label' => 'Navigation', 'items' => array_slice($navMatches, 0, 4)];
        }

        return response()->json(['groups' => $groups]);
    }
}
