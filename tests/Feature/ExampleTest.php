<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login page returns 200', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
