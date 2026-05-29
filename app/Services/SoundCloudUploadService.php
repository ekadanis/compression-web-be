<?php

namespace App\Services;

use App\Models\Upload;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use RuntimeException;

class SoundCloudUploadService
{
    private const API_URL = 'https://api.soundcloud.com';

    public function __construct(
        private readonly SoundCloudOAuthService $soundCloudOAuthService,
        private readonly SoundCloudSourceResolver $sourceResolver,
    ) {
    }

    /**
     * @return array{external_id: string, url: string|null, metadata: array<string,mixed>|null}
     */
    public function upload(Upload $upload): array
    {
        $upload->loadMissing(['user.soundcloudAccount', 'uploadable']);

        $account = $upload->user->soundcloudAccount;

        if (! $account) {
            throw new RuntimeException('Akun SoundCloud belum terhubung.');
        }

        $sourceType = class_basename($upload->uploadable_type) === 'File' ? 'file' : 'compression';
        $source = $this->sourceResolver->resolve($upload->user, $sourceType, $upload->uploadable_id);

        if (! file_exists($source['path'])) {
            throw new RuntimeException('Source audio tidak ditemukan.');
        }

        $fileSize = filesize($source['path']) ?: 0;

        if ($fileSize <= 0) {
            throw new RuntimeException('Ukuran source audio tidak valid untuk upload SoundCloud.');
        }

        $accessToken = $this->soundCloudOAuthService->validAccessToken($account);
        $client = new Client(['timeout' => 0]);
        $stream = Utils::tryFopen($source['path'], 'r');
        $lastProgress = -1;

        try {
            $response = $client->post(self::API_URL.'/tracks', [
                'headers' => [
                    'Authorization' => 'OAuth '.$accessToken,
                    'Accept' => 'application/json; charset=utf-8',
                ],
                'multipart' => $this->multipartPayload($upload, $source, $stream),
                'progress' => function (int $downloadTotal, int $downloadedBytes, int $uploadTotal, int $uploadedBytes) use ($upload, $fileSize, &$lastProgress): void {
                    $total = $uploadTotal > 0 ? $uploadTotal : $fileSize;

                    if ($total <= 0 || $uploadedBytes <= 0) {
                        return;
                    }

                    $progress = min(99, max(1, (int) floor(($uploadedBytes / $total) * 100)));

                    if ($progress !== $lastProgress) {
                        $lastProgress = $progress;
                        $upload->forceFill(['progress' => $progress])->save();
                    }
                },
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Upload SoundCloud gagal: '.$exception->getMessage(), previous: $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('SoundCloud tidak mengembalikan response JSON valid.');
        }

        $trackId = $decoded['id'] ?? null;

        if (! $trackId) {
            throw new RuntimeException('SoundCloud tidak mengembalikan track id.');
        }

        return [
            'external_id' => (string) $trackId,
            'url' => isset($decoded['permalink_url']) ? (string) $decoded['permalink_url'] : null,
            'metadata' => $decoded,
        ];
    }

    /**
     * @param resource $stream
     * @param array{file_name:string,mime_type:string} $source
     * @return array<int,array<string,mixed>>
     */
    private function multipartPayload(Upload $upload, array $source, mixed $stream): array
    {
        $payload = [
            ['name' => 'track[title]', 'contents' => $upload->title],
            ['name' => 'track[sharing]', 'contents' => $upload->visibility === 'public' ? 'public' : 'private'],
            [
                'name' => 'track[asset_data]',
                'contents' => $stream,
                'filename' => $source['file_name'],
                'headers' => ['Content-Type' => $source['mime_type']],
            ],
        ];

        if ($upload->description) {
            $payload[] = ['name' => 'track[description]', 'contents' => $upload->description];
        }

        if ($upload->tags) {
            $payload[] = ['name' => 'track[tag_list]', 'contents' => implode(' ', $upload->tags)];
        }

        $genre = $upload->metadata['genre'] ?? null;

        if (is_string($genre) && $genre !== '') {
            $payload[] = ['name' => 'track[genre]', 'contents' => $genre];
        }

        return $payload;
    }
}
