<?php

namespace App\Services;

use App\Models\User;
use App\Models\YoutubeAccount;
use Google\Client;
use Google\Service\Oauth2;
use Google\Service\YouTube;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class GoogleOAuthService
{
    public function getAuthUrl(User $user): string
    {
        $client = $this->makeClient();
        $client->setState($this->encryptStatePayload([
            'user_id' => $user->id,
            'issued_at' => now()->timestamp,
        ]));

        return $client->createAuthUrl();
    }

    public function exchangeCode(User $user, string $code): YoutubeAccount
    {
        $existing = $user->youtubeAccount;

        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('Gagal menukar authorization code: '.$token['error']);
        }

        $client->setAccessToken($token);

        $oauth = new Oauth2($client);
        $profile = $oauth->userinfo->get();

        $youtube = new YouTube($client);
        $channels = $youtube->channels->listChannels('snippet', ['mine' => true, 'maxResults' => 1]);
        $channel = $channels->getItems()[0] ?? null;

        return YoutubeAccount::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'google_email' => $profile->email,
                'channel_id' => $channel?->getId(),
                'channel_title' => $channel?->getSnippet()?->getTitle(),
                'access_token' => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? $existing?->refresh_token,
                'expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : now()->addHour(),
                'scopes' => Arr::wrap($token['scope'] ?? config('services.google.youtube_scopes', [])),
                'revoked_at' => null,
            ]
        );
    }

    public function disconnect(User $user): void
    {
        $account = $user->youtubeAccount;

        if (! $account) {
            return;
        }

        try {
            $client = $this->makeClient($account);
            $client->revokeToken($account->access_token);
        } catch (\Throwable) {
            // keep local cleanup even if remote revoke fails
        }

        $account->delete();
    }

    public function authorizedClient(YoutubeAccount $account): Client
    {
        $client = $this->makeClient($account);

        if ($client->isAccessTokenExpired() && $account->refresh_token) {
            $token = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);

            if (! isset($token['error'])) {
                $account->forceFill([
                    'access_token' => $token['access_token'] ?? $account->access_token,
                    'expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : $account->expires_at,
                ])->save();

                $client = $this->makeClient($account->fresh());
            }
        }

        return $client;
    }

    public function resolveUserFromState(?string $state): User
    {
        if (! $state) {
            throw new RuntimeException('OAuth state tidak tersedia.');
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException('OAuth state tidak valid.');
        }

        $userId = $payload['user_id'] ?? null;

        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            throw new RuntimeException('OAuth state tidak memiliki user yang valid.');
        }

        return User::query()->findOrFail((int) $userId);
    }

    private function makeClient(?YoutubeAccount $account = null): Client
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');

        if (! $clientId || ! $clientSecret || ! $redirectUri) {
            throw new RuntimeException('Google OAuth environment belum lengkap.');
        }

        $client = new Client();
        $client->setApplicationName(config('app.name'));
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $youtubeScopes = config('services.google.youtube_scopes', []);
        $defaultScopes = ['openid', 'profile', 'email'];
        $client->setScopes(array_merge($defaultScopes, $youtubeScopes));

        if ($account) {
            $client->setAccessToken([
                'access_token' => $account->access_token,
                'refresh_token' => $account->refresh_token,
                'expires_in' => $account->expires_at ? max(0, now()->diffInSeconds($account->expires_at, false)) : 0,
                'created' => now()->timestamp,
            ]);
        }

        return $client;
    }

    private function encryptStatePayload(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
