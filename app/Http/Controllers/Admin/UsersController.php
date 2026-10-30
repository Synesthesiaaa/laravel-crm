<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\UserImport;
use App\Services\CampaignService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected CampaignService $campaignService,
    ) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->orderBy('username')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'campaignOptions' => $this->campaignService->getCampaigns(),
            'userImports' => UserImport::where('user_id', $request->user()->id)->latest()->limit(5)->get()
                ->map(fn (UserImport $run) => app(\App\Services\Imports\UserImportService::class)->present($run))->all(),
            'userImportMaxBytes' => \App\Services\Imports\UserImportService::uploadLimitBytes(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated());

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = User::findOrFail((int) $request->input('id'));
        if (! $this->userService->delete($user, $request->user())) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
