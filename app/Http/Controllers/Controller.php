<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    /**
     * Flash a toast message for the next Inertia page.
     *
     * @param  array{type: string, message: string}  $toast
     */
    protected function toast(array $toast): void
    {
        if (method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', $toast);

            return;
        }

        session()->flash('toast', $toast);
    }
}
