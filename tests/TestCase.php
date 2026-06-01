<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function sanctumToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    protected function withSanctum(User $user): static
    {
        $token = $this->sanctumToken($user);

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }
}
