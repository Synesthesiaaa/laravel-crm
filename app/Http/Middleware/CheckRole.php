<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }
        $all = [];
        foreach ($roles as $r) {
            $all = array_merge($all, array_map('trim', explode(',', $r)));
        }
        foreach ($all as $role) {
            if ($role !== '' && $request->user()->role === $role) {
                return $next($request);
            }
        }
        abort(403, 'Unauthorized.');
    }
}
