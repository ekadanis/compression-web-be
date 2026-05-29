<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SoundCloudOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class SoundCloudOAuthController extends Controller
{
    public function __construct(private readonly SoundCloudOAuthService $soundCloudOAuthService)
    {
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontendRedirect = rtrim((string) config('services.soundcloud.frontend_redirect'), '/');

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $state = $this->soundCloudOAuthService->resolveStatePayload((string) $request->string('state'));
            $user = User::query()->findOrFail($state['user_id']);

            $this->soundCloudOAuthService->exchangeCode($user, (string) $request->string('code'), $state['code_verifier']);

            return redirect()->away($frontendRedirect.'?connected=1');
        } catch (Throwable $exception) {
            return redirect()->away($frontendRedirect.'?error='.urlencode($exception->getMessage()));
        }
    }
}
