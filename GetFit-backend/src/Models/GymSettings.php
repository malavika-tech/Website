<?php

namespace App\Models;

use App\Core\Database;

/**
 * GymSettings Domain Model
 * Manages key-value configuration settings for GetFit.
 */
class GymSettings
{
    /**
     * Get a single setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $db = Database::getInstance();
        $val = $db->fetchColumn('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
        return $val !== false && $val !== null ? $val : $default;
    }

    /**
     * Get all settings as an associative array [key => value].
     */
    public static function getAll(): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT setting_key, setting_value FROM settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Set or update a single setting.
     */
    public static function set(string $key, string $value): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?',
            [$key, $value, $value]
        );
    }

    /**
     * Set multiple settings at once.
     *
     * @param array<string, string> $settings
     */
    public static function setMultiple(array $settings): void
    {
        $db = Database::getInstance();
        foreach ($settings as $key => $value) {
            $db->execute(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?',
                [$key, (string)$value, (string)$value]
            );
        }
    }

    /**
     * Check if new member registrations are currently allowed.
     */
    public static function isRegistrationAllowed(): bool
    {
        $val = self::get('allow_registrations', '1');
        return $val === '1' || $val === true || $val === 1;
    }

    /**
     * Get the maximum allowed members.
     */
    public static function getMaxMembers(): int
    {
        return (int)self::get('max_members', 500);
    }

    /**
     * Check if the gym has reached capacity.
     */
    public static function isCapacityReached(): bool
    {
        $db = Database::getInstance();
        $currentCount = (int)$db->fetchColumn('SELECT COUNT(*) FROM members');
        return $currentCount >= self::getMaxMembers();
    }
}
