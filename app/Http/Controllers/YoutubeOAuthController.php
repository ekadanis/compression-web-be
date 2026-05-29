<?php

namespace App\Http\Controllers;

use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class YoutubeOAuthController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $googleOAuthService)
    {
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontendRedirect = rtrim((string) config('services.google.frontend_redirect'), '/');

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $user = $this->googleOAuthService->resolveUserFromState((string) $request->string('state'));
            $this->googleOAuthService->exchangeCode($user, (string) $request->string('code'));

            return redirect()->away($frontendRedirect.'?connected=1');
        } catch (Throwable $exception) {
            return redirect()->away($frontendRedirect.'?error='.urlencode($exception->getMessage()));
        }
    }
}
