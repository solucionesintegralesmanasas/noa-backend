<?php

namespace App\Utils;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class Logger
{
    /**
     * Get the request ID.
     */
    private static function getRequestId(): ?string
    {
        return request()->header('X-Request-ID') ?? uniqid();
    }

    /**
     * Log an error message.
     */
    public static function error(string $message, ?\Throwable $exception = null, array $context = []): void
    {
        $user = Auth::user();
        $route = Route::current();

        $logContext = array_merge([
            'exception' => $exception ? get_class($exception) : 'N/A',
            'message' => $exception ? $exception->getMessage() : 'N/A',
            'file' => $exception ? $exception->getFile() : 'N/A',
            'line' => $exception ? $exception->getLine() : 'N/A',
            'request_id' => self::getRequestId(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'route_name' => $route?->getName(),
            'route_parameters' => $route?->parameters() ?? [],
            'request_data' => request()->except(['password', 'password_confirmation', 'token']),
            'trace' => $exception ? $exception->getTraceAsString() : 'N/A',
        ], $context);

        Log::error($message, $logContext);
    }

    /**
     * Log a warning message.
     */
    public static function warning(string $message, ?\Throwable $exception = null, array $context = []): void
    {
        $logContext = $context;

        if ($exception) {
            $logContext = array_merge([
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ], $context);
        }

        Log::warning($message, $logContext);
    }

    /**
     * Log an info message.
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    /**
     * Log a debug message.
     */
    public static function debug(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }

    /**
     * Log a critical message.
     */
    public static function critical(string $message, ?\Throwable $exception = null, array $context = []): void
    {
        $user = Auth::user();
        $route = Route::current();

        $logContext = array_merge([
            'exception' => $exception ? get_class($exception) : 'N/A',
            'message' => $exception ? $exception->getMessage() : 'N/A',
            'file' => $exception ? $exception->getFile() : 'N/A',
            'line' => $exception ? $exception->getLine() : 'N/A',
            'request_id' => self::getRequestId(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'route_name' => $route?->getName(),
            'route_parameters' => $route?->parameters() ?? [],
            'request_data' => request()->except(['password', 'password_confirmation', 'token']),
            'trace' => $exception ? $exception->getTraceAsString() : 'N/A',
        ], $context);

        Log::critical($message, $logContext);
    }

    /**
     * Log an emergency message.
     */
    public static function emergency(string $message, ?\Throwable $exception = null, array $context = []): void
    {
        $user = Auth::user();
        $route = Route::current();

        $logContext = array_merge([
            'exception' => $exception ? get_class($exception) : 'N/A',
            'message' => $exception ? $exception->getMessage() : 'N/A',
            'file' => $exception ? $exception->getFile() : 'N/A',
            'line' => $exception ? $exception->getLine() : 'N/A',
            'request_id' => self::getRequestId(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'route_name' => $route?->getName(),
            'route_parameters' => $route?->parameters() ?? [],
            'request_data' => request()->except(['password', 'password_confirmation', 'token']),
            'trace' => $exception ? $exception->getTraceAsString() : 'N/A',
        ], $context);

        Log::emergency($message, $logContext);
    }
}
