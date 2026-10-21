<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase;

class BroadcastWorkerAuthorizationTest extends TestCase
{
    public function test_worker_endpoints_fail_closed_when_secret_is_unconfigured(): void
    {
        foreach ([null, ''] as $secret) {
            config(['services.broadcast_worker.token' => $secret]);
            $this->getJson('/api/worker/daily-limit')->assertUnauthorized();
            $this->postJson('/api/worker/opt-out', [])->assertUnauthorized();
            $this->postJson('/api/worker/update-status', [])->assertUnauthorized();
        }
    }

    public function test_worker_rejects_missing_wrong_and_array_tokens(): void
    {
        config(['services.broadcast_worker.token' => 'qa-configured-secret']);
        $this->getJson('/api/worker/daily-limit')->assertUnauthorized();
        $this->getJson('/api/worker/daily-limit?token[]=invalid')->assertUnauthorized();
        $this->withHeader('X-Worker-Token', 'wrong')
            ->postJson('/api/worker/opt-out', [])->assertUnauthorized();
    }

    public function test_configured_worker_token_reaches_validation_without_database_writes(): void
    {
        config(['services.broadcast_worker.token' => 'qa-configured-secret']);
        $this->withHeader('X-Worker-Token', 'qa-configured-secret')
            ->postJson('/api/worker/opt-out', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'type']);
    }
}
