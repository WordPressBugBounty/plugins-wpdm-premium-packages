<?php
/**
 * PSR-4 Autoloader for WPDMPP
 *
 * @package WPDMPP
 * @since 7.0.0
 */

namespace WPDMPP;

defined('ABSPATH') || exit;

/**
 * Autoloader class
 */
class Autoloader {

    /**
     * Namespace prefix
     */
    private const PREFIX = 'WPDMPP\\';

    /**
     * Base directory for the namespace prefix
     */
    private static $base_dir;

    /**
     * Register the autoloader
     */
    public static function register(): void {
        self::$base_dir = __DIR__ . '/';
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * Autoload callback
     *
     * @param string $class The fully-qualified class name
     */
    public static function autoload(string $class): void {
        // Check if the class uses our namespace prefix
        $len = strlen(self::PREFIX);
        if (strncmp(self::PREFIX, $class, $len) !== 0) {
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace namespace separators with directory separators
        $file = self::$base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
}

// Register the autoloader
Autoloader::register();
