<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Format
{
    public static function bytes(?int $bytes, int $decimals = 1): string
    {
        if (! $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $decimals).' '.$units[$power];
    }

    public static function duration(?int $seconds): string
    {
        if (! $seconds) {
            return '-';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    public static function date(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->translatedFormat('d M Y H:i');
    }

    public static function fileTypeIcon(string $type): string
    {
        return $type === 'audio' ? 'AUDIO' : 'VIDEO';
    }
}
