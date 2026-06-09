<?php

namespace App\Services;

use App\Models\Upload;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class YoutubeUploadService
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
        private readonly UploadSourceResolver $uploadSourceResolver,
    ) {
    }

    /**
     * @return array{external_id: string, url: string, metadata: array<string, mixed>|null}
     */
    public function upload(Upload $upload): array
    {
        $upload->loadMissing(['user.youtubeAccount', 'uploadable']);

        $account = $upload->user->youtubeAccount;

        if (! $account) {
            throw new RuntimeException('Akun YouTube belum terhubung.');
        }

        $sourceType = class_basename($upload->uploadable_type) === 'File' ? 'file' : 'compression';
        $source = $this->uploadSourceResolver->resolve($upload->user, $sourceType, $upload->uploadable_id);

        if (! file_exists($source['path'])) {
            throw new RuntimeException('Source video tidak ditemukan.');
        }

        $client = $this->googleOAuthService->authorizedClient($account);
        $youtube = new YouTube($client);

        $snippet = new VideoSnippet();
        $snippet->setTitle($upload->title);
        $snippet->setDescription($upload->description ?? '');
        $snippet->setCategoryId($upload->category_id ?: '22');
        $snippet->setTags($upload->tags ?: []);

        $status = new VideoStatus();
        $status->setPrivacyStatus($upload->visibility);

        if ($upload->scheduled_at) {
            $status->setPublishAt($upload->scheduled_at->copy()->utc()->toAtomString());
            $status->setPrivacyStatus('private');
        }

        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        $client->setDefer(true);

        /** @var RequestInterface $insertRequest */
        $insertRequest = $youtube->videos->insert('snippet,status', $video);
        $chunkSize = (int) env('YOUTUBE_UPLOAD_CHUNK_SIZE', 8 * 1024 * 1024);
        $media = new MediaFileUpload($client, $insertRequest, $source['mime_type'], '', true, $chunkSize);

        $handle = fopen($source['path'], 'rb');
        if ($handle === false) {
            throw new RuntimeException('Gagal membaca source video untuk upload.');
        }

        $fileSize = filesize($source['path']) ?: 0;

        if ($fileSize <= 0) {
            throw new RuntimeException('Ukuran source video tidak valid untuk upload YouTube.');
        }

        $media->setFileSize($fileSize);

        $uploadedBytes = 0;
        $response = null;

        try {
            while (! $response && ! feof($handle)) {
                $upload->refresh();

                if ($upload->cancel_requested_at || $upload->status === 'cancelled') {
                    throw new RuntimeException('Upload dibatalkan.');
                }

                $chunk = fread($handle, $chunkSize);

                if ($chunk === false) {
                    throw new RuntimeException('Gagal membaca potongan file saat upload ke YouTube.');
                }

                if ($chunk === '') {
                    continue;
                }

                $uploadedBytes += strlen($chunk);
                $response = $media->nextChunk($chunk);

                $upload->forceFill([
                    'progress' => $fileSize > 0 ? min(99, (int) floor(($uploadedBytes / $fileSize) * 100)) : 0,
                ])->save();
            }
        } finally {
            fclose($handle);
            $client->setDefer(false);
        }

        $videoId = $this->extractVideoId($response);

        if (! $videoId) {
            throw new RuntimeException('YouTube tidak mengembalikan video id.');
        }

        return [
            'external_id' => $videoId,
            'url' => 'https://www.youtube.com/watch?v='.$videoId,
            'metadata' => $this->normalizeMetadata($response),
        ];
    }

    public function deleteVideo(Upload $upload, ?string $videoId = null): void
    {
        $upload->loadMissing('user.youtubeAccount');

        $account = $upload->user->youtubeAccount;

        if (! $account) {
            throw new RuntimeException('Akun YouTube belum terhubung.');
        }

        $id = $videoId ?: $upload->external_id;

        if (! $id) {
            return;
        }

        $client = $this->googleOAuthService->authorizedClient($account);
        $youtube = new YouTube($client);
        $youtube->videos->delete($id);
    }

    private function extractVideoId(mixed $response): ?string
    {
        if (is_array($response)) {
            return isset($response['id']) ? (string) $response['id'] : null;
        }

        if (is_object($response) && method_exists($response, 'getId')) {
            return $response->getId();
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(mixed $response): ?array
    {
        if ($response === null) {
            return null;
        }

        $json = json_encode($response);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
