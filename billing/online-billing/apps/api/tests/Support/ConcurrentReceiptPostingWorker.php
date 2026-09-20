<?php

use App\Models\User;
use App\Services\Billing\ReceiptPostingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$barrierPath, $readyPath, $actorId, $encodedPayload] = array_slice($argv, 1);

if (config('database.connections.pgsql.database') !== 'online_billing_test') {
    fwrite(STDOUT, json_encode(['status' => 'configuration_error']).PHP_EOL);
    exit(3);
}

config([
    'filesystems.disks.private' => [
        'driver' => 'local',
        'root' => storage_path('framework/testing-concurrent-receipts'),
    ],
]);

file_put_contents($readyPath, 'ready');

while (file_exists($barrierPath)) {
    usleep(1_000);
}

try {
    $actor = User::findOrFail((int) $actorId);
    $receipt = app(ReceiptPostingService::class)->post($actor, json_decode(base64_decode($encodedPayload, true), true, 512, JSON_THROW_ON_ERROR));

    fwrite(STDOUT, json_encode([
        'status' => 'posted',
        'receipt_id' => $receipt->id,
        'receipt_number' => $receipt->receipt_number,
    ], JSON_THROW_ON_ERROR).PHP_EOL);
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode([
        'status' => 'rejected',
        'exception' => $exception::class,
        'errors' => $exception->errors(),
    ], JSON_THROW_ON_ERROR).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'status' => 'rejected',
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR).PHP_EOL);
}
