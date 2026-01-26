<?php

use App\Models\User;
use Illuminate\Support\Carbon;

it('serializes model dates in asia manila timezone', function () {
    config(['app.timezone' => 'Asia/Manila']);

    $date = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC');

    $user = new User;
    $user->created_at = $date;
    $user->last_login_at = $date;

    $serialized = $user->toArray();

    expect($serialized['created_at'])->toStartWith('2026-01-01T');
    expect($serialized['created_at'])->toEndWith('+08:00');
    expect($serialized['last_login_at'])->toStartWith('2026-01-01T');
    expect($serialized['last_login_at'])->toEndWith('+08:00');
});
