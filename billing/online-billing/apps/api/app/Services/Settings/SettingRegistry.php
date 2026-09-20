<?php

namespace App\Services\Settings;

use InvalidArgumentException;

class SettingRegistry
{
    /**
     * Forbidden substring patterns for deployment secrets and security-sensitive keys.
     */
    protected const FORBIDDEN_PATTERNS = [
        'secret',
        'password',
        'token',
        'credential',
        'private_key',
        'database',
        'redis',
        'smtp',
        'mail_',
        'skysms',
        'app_key',
        'api_key',
    ];

    /**
     * Registry of allowlisted application settings.
     *
     * Format:
     * 'key' => [
     *     'type' => 'string'|'integer'|'boolean'|'decimal'|'json',
     *     'default' => mixed,
     *     'scope' => 'organization'|'location', // 'location' allows location-level override
     *     'description' => string,
     * ]
     */
    protected static array $registry = [
        'organization.display_name' => [
            'type' => 'string',
            'default' => 'SCIPSI Online Billing',
            'scope' => 'organization',
            'description' => 'Display name for organization on portal and receipts',
        ],
        'organization.timezone' => [
            'type' => 'string',
            'default' => 'Asia/Manila',
            'scope' => 'organization',
            'description' => 'System business timezone',
        ],
        'ui.page_size' => [
            'type' => 'integer',
            'default' => 25,
            'scope' => 'organization',
            'description' => 'Default pagination size for tables',
        ],
        'ui.maintenance_notice_enabled' => [
            'type' => 'boolean',
            'default' => false,
            'scope' => 'organization',
            'description' => 'Toggle banner notice for scheduled maintenance',
        ],
        'location.display_name' => [
            'type' => 'string',
            'default' => '',
            'scope' => 'location',
            'description' => 'Location specific branch title',
        ],
        'location.phone' => [
            'type' => 'string',
            'default' => '',
            'scope' => 'location',
            'description' => 'Location contact phone number',
        ],
        'printing.default_printer_profile' => [
            'type' => 'string',
            'default' => 'standard_laser',
            'scope' => 'location',
            'description' => 'Default hardware printer profile for station',
        ],
    ];

    public static function isAllowlisted(string $key): bool
    {
        return isset(static::$registry[$key]);
    }

    public static function isForbiddenSecret(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (static::FORBIDDEN_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function getDefinition(string $key): array
    {
        if (! static::isAllowlisted($key)) {
            throw new InvalidArgumentException("Setting key '{$key}' is not in the allowed registry.");
        }

        return static::$registry[$key];
    }

    public static function all(): array
    {
        return static::$registry;
    }

    public static function validate(string $key, mixed $value, bool $isLocationScope = false): mixed
    {
        if (static::isForbiddenSecret($key)) {
            throw new InvalidArgumentException("Setting key '{$key}' is a deployment secret and cannot be stored in web settings.");
        }

        $def = static::getDefinition($key);

        if ($isLocationScope && $def['scope'] !== 'location') {
            throw new InvalidArgumentException("Setting key '{$key}' does not permit location-level overrides.");
        }

        return match ($def['type']) {
            'string' => is_scalar($value) ? (string) $value : throw new InvalidArgumentException("Value for '{$key}' must be a string."),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : throw new InvalidArgumentException("Value for '{$key}' must be an integer."),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : throw new InvalidArgumentException("Value for '{$key}' must be a boolean."),
            'decimal' => is_numeric($value) ? (string) $value : throw new InvalidArgumentException("Value for '{$key}' must be a numeric decimal."),
            'json' => is_array($value) ? $value : (json_decode((string) $value, true) ?? throw new InvalidArgumentException("Value for '{$key}' must be valid JSON.")),
            default => throw new InvalidArgumentException("Unsupported setting type '{$def['type']}'."),
        };
    }
}
