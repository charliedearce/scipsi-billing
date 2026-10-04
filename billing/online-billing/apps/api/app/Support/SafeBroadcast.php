<?php

namespace App\Support;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * W27: Reverb is a wake-up transport only. Durable API writes must succeed even when
 * the websocket server is down or unreachable.
 */
class SafeBroadcast
{
    /**
     * Dispatch after the current DB transaction commits (if any), and never let
     * transport failures fail the HTTP request.
     */
    public static function dispatchAfterCommit(object $event): void
    {
        $run = static fn () => self::dispatch($event);

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);
        } else {
            $run();
        }
    }

    public static function dispatch(object $event): void
    {
        try {
            event($event);
        } catch (BroadcastException $e) {
            self::log($event, $e);
        } catch (Throwable $e) {
            if (self::isBroadcastTransportFailure($e)) {
                self::log($event, $e);

                return;
            }

            throw $e;
        }
    }

    public static function broadcast(object $event): void
    {
        try {
            broadcast($event);
        } catch (BroadcastException $e) {
            self::log($event, $e);
        } catch (Throwable $e) {
            if (self::isBroadcastTransportFailure($e)) {
                self::log($event, $e);

                return;
            }

            throw $e;
        }
    }

    public static function broadcastAfterCommit(object $event): void
    {
        $run = static fn () => self::broadcast($event);

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);
        } else {
            $run();
        }
    }

    protected static function isBroadcastTransportFailure(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Pusher error')
            || str_contains($message, 'cURL error')
            || str_contains($message, 'Could not resolve host');
    }

    protected static function log(object $event, Throwable $e): void
    {
        Log::warning('Realtime broadcast skipped; durable state already committed.', [
            'event' => $event::class,
            'error' => $e->getMessage(),
        ]);
    }
}
