<?php

namespace App\Console\Commands;

use App\Jobs\UploadToYoutubeJob;
use App\Jobs\UploadToSoundCloudJob;
use App\Models\Upload;
use Illuminate\Console\Command;

class DispatchDueUploadsCommand extends Command
{
    protected $signature = 'uploads:dispatch-due';

    protected $description = 'Dispatch scheduled uploads that are due';

    public function handle(): int
    {
        Upload::query()
            ->whereIn('platform', ['youtube', 'soundcloud'])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->chunkById(50, function ($uploads): void {
                foreach ($uploads as $upload) {
                    $upload->forceFill(['status' => 'pending'])->save();

                    if ($upload->platform === 'soundcloud') {
                        UploadToSoundCloudJob::dispatch($upload->id);
                    } else {
                        UploadToYoutubeJob::dispatch($upload->id);
                    }
                }
            });

        return self::SUCCESS;
    }
}
