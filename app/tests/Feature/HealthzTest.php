<?php

it('returns healthz response', function (): void {
    $response = $this->getJson('/healthz');

    $response->assertOk()
        ->assertJsonPath('status', 'ok');
});
