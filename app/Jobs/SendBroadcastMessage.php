<?php

namespace App\Jobs;

use App\Models\Broadcast\BroadcastLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendBroadcastMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public BroadcastLog $log) {}

    public function handle(): void
    {
        $this->log->update([
            'status' => 'queued',
            'attempt_count' => $this->log->attempt_count + 1,
        ]);

        try {
            $workerUrl = rtrim(config('services.broadcast_worker.url', env('BROADCAST_WORKER_URL')), '/');

            if (!$workerUrl) {
                throw new \RuntimeException('BROADCAST_WORKER_URL is not configured.');
            }

            $response = Http::timeout(30)
                ->post("{$workerUrl}/api/broadcast/send", [
                    'phone' => $this->log->phone,
                    'message' => $this->log->rendered_message,
                    'campaign_id' => $this->log->campaign_id,
                    'log_id' => $this->log->id,
                    'provider' => $this->log->provider,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                $this->log->update([
                    'status' => $data['status'] ?? 'submitted',
                    'provider_message_id' => $data['message_id'] ?? $this->log->provider_message_id,
                    'sent_at' => now(),
                    'error_message' => $data['error'] ?? null,
                ]);
            } else {
                $errorBody = $response->body();
                $this->log->update([
                    'status' => 'failed',
                    'error_message' => 'Worker responded with status: ' . $response->status() . ($errorBody ? ' - ' . $errorBody : ''),
                ]);
            }
        } catch (\Throwable $e) {
            $this->log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('broadcast')->error('Broadcast send failed', [
                'log_id' => $this->log->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->log->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::channel('broadcast')->error('Broadcast send failed', [
            'log_id' => $this->log->id,
            'campaign_id' => $this->log->campaign_id,
            'error' => $exception->getMessage(),
        ]);

        if ($this->log->campaign) {
            $this->log->campaign->update([
                'total_failed' => $this->log->campaign->logs()->where('status', 'failed')->count(),
            ]);

            $this->log->campaign->checkCompletion();
        }
    }
}
