<?php

namespace App\Services;

use App\Models\SoundCloudAccount;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SoundCloudOAuthService
{
    private const AUTH_URL = 'https://secure.soundcloud.com/authorize';

    private const TOKEN_URL = 'https://secure.soundcloud.com/oauth/token';

    private const API_URL = 'https://api.soundcloud.com';

    public function getAuthUrl(User $user): string
    {
        $clientId = $this->clientId();
        $redirectUri = $this->redirectUri();
        $verifier = $this->makeCodeVerifier();

        $state = Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'issued_at' => now()->timestamp,
            'code_verifier' => $verifier,
        ], JSON_THROW_ON_ERROR));

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'code_challenge' => $this->makeCodeChallenge($verifier),
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);
    }

    public function exchangeCode(User $user, string $code, string $codeVerifier): SoundCloudAccount
    {
        $token = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'code_verifier' => $codeVerifier,
                'code' => $code,
            ]);

        if (! $token->successful()) {
            throw new RuntimeException('Gagal menukar authorization code SoundCloud: '.$token->body());
        }

        $payload = $token->json();
        $profile = $this->fetchProfile((string) $payload['access_token']);

        return SoundCloudAccount::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'soundcloud_user_id' => isset($profile['id']) ? (string) $profile['id'] : null,
                'username' => $profile['username'] ?? null,
                'permalink_url' => $profile['permalink_url'] ?? null,
                'avatar_url' => $profile['avatar_url'] ?? null,
                'access_token' => $payload['access_token'] ?? null,
                'refresh_token' => $payload['refresh_token'] ?? null,
                'expires_at' => isset($payload['expires_in']) ? now()->addSeconds((int) $payload['expires_in']) : now()->addHour(),
                'scopes' => isset($payload['scope']) ? Arr::wrap($payload['scope']) : null,
                'revoked_at' => null,
            ]
        );
    }

    public function disconnect(User $user): void
    {
        $account = $user->soundcloudAccount;

        if (! $account) {
            return;
        }

        try {
            Http::asJson()->post('https://secure.soundcloud.com/sign-out', [
                'access_token' => $account->access_token,
            ]);
        } catch (\Throwable) {
            // Local cleanup should still happen if remote sign-out fails.
        }

        $account->delete();
    }

    public function validAccessToken(SoundCloudAccount $account): string
    {
        if ($account->expires_at && $account->expires_at->greaterThan(now()->addMinute()) && $account->access_token) {
            return $account->access_token;
        }

        if (! $account->refresh_token) {
            throw new RuntimeException('Refresh token SoundCloud tidak tersedia. Hubungkan ulang akun SoundCloud.');
        }

        $token = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $account->refresh_token,
            ]);

        if (! $token->successful()) {
            throw new RuntimeException('Gagal refresh token SoundCloud: '.$token->body());
        }

        $payload = $token->json();

        $account->forceFill([
            'access_token' => $payload['access_token'] ?? $account->access_token,
            'refresh_token' => $payload['refresh_token'] ?? $account->refresh_token,
            'expires_at' => isset($payload['expires_in']) ? now()->addSeconds((int) $payload['expires_in']) : now()->addHour(),
            'scopes' => isset($payload['scope']) ? Arr::wrap($payload['scope']) : $account->scopes,
        ])->save();

        return (string) $account->fresh()->access_token;
    }

    /**
     * @return array{user_id:int,code_verifier:string}
     */
    public function resolveStatePayload(?string $state): array
    {
        if (! $state) {
            throw new RuntimeException('OAuth state SoundCloud tidak tersedia.');
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException('OAuth state SoundCloud tidak valid.');
        }

        $userId = $payload['user_id'] ?? null;
        $verifier = $payload['code_verifier'] ?? null;

        if ((! is_int($userId) && ! ctype_digit((string) $userId)) || ! is_string($verifier) || $verifier === '') {
            throw new RuntimeException('OAuth state SoundCloud tidak lengkap.');
        }

        return [
            'user_id' => (int) $userId,
            'code_verifier' => $verifier,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchProfile(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
            'Accept' => 'application/json; charset=utf-8',
        ])->get(self::API_URL.'/me');

        if (! $response->successful()) {
            throw new RuntimeException('Gagal mengambil profil SoundCloud: '.$response->body());
        }

        return $response->json();
    }

    private function clientId(): string
    {
        $value = config('services.soundcloud.client_id');

        if (! $value) {
            throw new RuntimeException('SOUNDCLOUD_CLIENT_ID belum dikonfigurasi.');
        }

        return (string) $value;
    }

    private function clientSecret(): string
    {
        $value = config('services.soundcloud.client_secret');

        if (! $value) {
            throw new RuntimeException('SOUNDCLOUD_CLIENT_SECRET belum dikonfigurasi.');
        }

        return (string) $value;
    }

    private function redirectUri(): string
    {
        $value = config('services.soundcloud.redirect');

        if (! $value) {
            throw new RuntimeException('SOUNDCLOUD_REDIRECT_URI belum dikonfigurasi.');
        }

        return (string) $value;
    }

    private function makeCodeVerifier(): string
    {
        return Str::random(64);
    }

    private function makeCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
