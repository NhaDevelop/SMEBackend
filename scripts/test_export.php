<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('role', 'ADMIN')->first();
$token = $user->createToken('script')->plainTextToken;
$authHeader = ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];

$request = \Illuminate\Http\Request::create('/api/admin/reports/export', 'GET', server: $authHeader);
$response = app()->handle($request);
echo "Export Status: " . $response->getStatusCode() . "\n";

$request2 = \Illuminate\Http\Request::create('/api/admin/reports/readiness', 'GET', server: $authHeader);
$response2 = app()->handle($request2);
echo "Readiness Status: " . $response2->getStatusCode() . "\n";
