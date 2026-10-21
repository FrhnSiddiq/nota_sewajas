<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use App\Models\ActivityLog;

class ActivityLogFileService
{
    private string $directory = 'logs/activities';
    private string $filename = 'activity.log';

    public function write(ActivityLog $log): void
    {
        $timestamp = $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $user = optional($log->user)->name ?? 'System';
        $action = $log->action ?? '-';
        $description = str_replace("\n", ' ', $log->description ?? '-');
        $branch = optional($log->branch)->name ?? '-';
        $ip = $log->ip_address ?? '-';
        $modelType = $log->model_type ?? '-';
        $modelId = $log->model_id ?? '-';

        $line = sprintf(
            "[%s] | %s | %s | %s | Branch: %s | IP: %s | Model: %s (ID: %s)%s",
            $timestamp,
            $user,
            $action,
            $description,
            $branch,
            $ip,
            $modelType,
            $modelId,
            PHP_EOL
        );

        Storage::append($this->directory . '/' . $this->filename, $line);
    }

    public function getLogPath(): string
    {
        return storage_path('app/' . $this->directory . '/' . $this->filename);
    }

    public function getLogContent(): string
    {
        $path = $this->getLogPath();

        if (!file_exists($path)) {
            return '';
        }

        return file_get_contents($path);
    }

    public function backfill(): int
    {
        $path = $this->getLogPath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $existingLines = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        $existingKeys = [];

        foreach ($existingLines as $line) {
            if (preg_match('/^\[(.+?)\] \| (.+?) \| (.+?) \| (.+?) \| Branch: (.+?) \| IP: (.+?) \| Model: (.+?) \(ID: (.+?)\)$/', $line, $matches)) {
                $key = $matches[1] . '|' . $matches[2] . '|' . $matches[3] . '|' . $matches[8];
                $existingKeys[$key] = true;
            }
        }

        $logs = ActivityLog::orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'created_at', 'action', 'description', 'ip_address', 'model_type', 'model_id', 'user_id', 'branch_id']);

        $written = 0;

        foreach ($logs as $log) {
            $timestamp = $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
            $user = optional($log->user)->name ?? 'System';
            $action = $log->action ?? '-';
            $description = str_replace("\n", ' ', $log->description ?? '-');
            $branch = optional($log->branch)->name ?? '-';
            $ip = $log->ip_address ?? '-';
            $modelType = $log->model_type ?? '-';
            $modelId = $log->model_id ?? '-';

            $key = $timestamp . '|' . $user . '|' . $action . '|' . $modelId;

            if (isset($existingKeys[$key])) {
                continue;
            }

            $line = sprintf(
                "[%s] | %s | %s | %s | Branch: %s | IP: %s | Model: %s (ID: %s)",
                $timestamp,
                $user,
                $action,
                $description,
                $branch,
                $ip,
                $modelType,
                $modelId
            );

            file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
            $existingKeys[$key] = true;
            $written++;
        }

        return $written;
    }
}
