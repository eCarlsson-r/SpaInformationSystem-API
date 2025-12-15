<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function authenticate()
    {
        $user = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);
        return $user;
    }
}
