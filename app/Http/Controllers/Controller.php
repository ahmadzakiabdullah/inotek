<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
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
        // Call the facade directly. A `method_exists(Inertia::class, 'flash')`
        // guard is always false on a facade, which silently sent every toast to
        // an unread `session('toast')` key.
        Inertia::flash('toast', $toast);
    }

    /**
     * Refuse an action: show the message as an error toast and return it as
     * the `error` validation key, so Inertia runs `onError` rather than `onSuccess`.
     */
    protected function backWithError(string $message): RedirectResponse
    {
        $this->toast(['type' => 'error', 'message' => $message]);

        return back()->withErrors(['error' => $message]);
    }

    /**
     * Confirm an action with a success toast.
     */
    protected function backWithSuccess(string $message, ?RedirectResponse $redirect = null): RedirectResponse
    {
        $this->toast(['type' => 'success', 'message' => $message]);

        return $redirect ?? back();
    }
}
