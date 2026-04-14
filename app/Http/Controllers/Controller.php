<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

abstract class Controller
{
    /**
     * Render an admin Blade fragment (resources/views/admin/inline-*.blade.php) inside Inertia.
     */
    protected function inertiaAdmin(string $inlineView, array $data, string $title): Response
    {
        return Inertia::render('Admin/Bridge', [
            'title' => $title,
            'markup' => view($inlineView, $data)->render(),
        ]);
    }
}
