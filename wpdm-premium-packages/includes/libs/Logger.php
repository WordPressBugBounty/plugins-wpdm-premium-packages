<?php
/**
 * Logger Class for WPDM Premium Packages
 *
 * Provides consistent error logging throughout the plugin.
 *
 * @package WPDMPP
 * @since 6.3.0
 */

namespace WPDMPP\Libs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	/**
	 * Log levels
	 */
	const EMERGENCY = 'emergency';
	const ALERT     = 'alert';
	const CRITICAL  = 'critical';
	const ERROR     = 'error';
	const WARNING   = 'warning';
	const NOTICE    = 'notice';
	const INFO      = 'info';
	const DEBUG     = 'debug';

	/**
	 * Log level priority (lower = more severe)
	 */
	private static $level_priority = [
		self::EMERGENCY => 0,
		self::ALERT     => 1,
		self::CRITICAL  => 2,
		self::ERROR     => 3,
		self::WARNING   => 4,
		self::NOTICE    => 5,
		self::INFO      => 6,
		self::DEBUG     => 7,
	];

	/**
	 * Log directory path
	 *
	 * @var string
	 */
	private static $log_dir = null;

	/**
	 * Maximum log file size in bytes (5MB default)
	 *
	 * @var int
	 */
	private static $max_file_size = 5242880;

	/**
	 * Maximum number of log files to keep
	 *
	 * @var int
	 */
	private static $max_files = 5;

	/**
	 * Whether logging is enabled
	 *
	 * @var bool|null
	 */
	private static $enabled = null;

	/**
	 * Minimum log level to record
	 *
	 * @var string|null
	 */
	private static $min_level = null;

	/**
	 * Initialize the logger
	 */
	private static function init() {
		if ( self::$log_dir === null ) {
			$upload_dir    = wp_upload_dir();
			self::$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'wpdmpp-logs/';

			// Create log directory if it doesn't exist
			if ( ! file_exists( self::$log_dir ) ) {
				wp_mkdir_p( self::$log_dir );

				// Add .htaccess to protect logs
				$htaccess = self::$log_dir . '.htaccess';
				if ( ! file_exists( $htaccess ) ) {
					file_put_contents( $htaccess, "deny from all\n" );
				}

				// Add index.php for extra protection
				$index = self::$log_dir . 'index.php';
				if ( ! file_exists( $index ) ) {
					file_put_contents( $index, "<?php\n// Silence is golden.\n" );
				}
			}
		}

		if ( self::$enabled === null ) {
			self::$enabled = get_wpdmpp_option( 'enable_error_logging', 1 );
		}

		if ( self::$min_level === null ) {
			self::$min_level = get_wpdmpp_option( 'log_level', self::ERROR );
		}
	}

	/**
	 * Check if a log level should be recorded
	 *
	 * @param string $level Log level to check
	 * @return bool
	 */
	private static function should_log( $level ) {
		self::init();

		if ( ! self::$enabled ) {
			return false;
		}

		$level_priority     = self::$level_priority[ $level ] ?? 7;
		$min_level_priority = self::$level_priority[ self::$min_level ] ?? 3;

		return $level_priority <= $min_level_priority;
	}

	/**
	 * Get the current log file path
	 *
	 * @return string
	 */
	private static function get_log_file() {
		self::init();
		return self::$log_dir . 'wpdmpp-' . wp_date( 'Y-m-d' ) . '.log';
	}

	/**
	 * Rotate logs if necessary
	 */
	private static function maybe_rotate_logs() {
		$log_file = self::get_log_file();

		// Check if current log file exceeds max size
		if ( file_exists( $log_file ) && filesize( $log_file ) >= self::$max_file_size ) {
			$timestamp    = wp_date( 'Y-m-d-His' );
			$rotated_file = self::$log_dir . 'wpdmpp-' . $timestamp . '.log';
			rename( $log_file, $rotated_file );
		}

		// Clean up old log files
		self::cleanup_old_logs();
	}

	/**
	 * Remove old log files exceeding the max count
	 */
	private static function cleanup_old_logs() {
		$files = glob( self::$log_dir . 'wpdmpp-*.log' );

		if ( $files && count( $files ) > self::$max_files ) {
			// Sort by modification time (oldest first)
			usort( $files, function ( $a, $b ) {
				return filemtime( $a ) - filemtime( $b );
			} );

			// Remove oldest files
			$to_remove = count( $files ) - self::$max_files;
			for ( $i = 0; $i < $to_remove; $i++ ) {
				@unlink( $files[ $i ] );
			}
		}
	}

	/**
	 * Format the log message
	 *
	 * @param string $level   Log level
	 * @param string $message Log message
	 * @param array  $context Additional context
	 * @return string
	 */
	private static function format_message( $level, $message, $context = [] ) {
		$timestamp = wp_date( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );

		// Get caller information
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 );
		$caller    = $backtrace[3] ?? $backtrace[2] ?? [];
		$file      = isset( $caller['file'] ) ? basename( $caller['file'] ) : 'unknown';
		$line      = $caller['line'] ?? 0;
		$function  = $caller['function'] ?? 'unknown';

		// Build the log entry
		$entry = sprintf(
			"[%s] [%s] [%s:%d] [%s] %s",
			$timestamp,
			$level_upper,
			$file,
			$line,
			$function,
			$message
		);

		// Add context if provided
		if ( ! empty( $context ) ) {
			// Sanitize sensitive data
			$context = self::sanitize_context( $context );
			$entry  .= ' | Context: ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		// Add user info if available
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$entry .= ' | User: ' . $user_id;
		}

		// Add request info for errors
		if ( in_array( $level, [ self::ERROR, self::CRITICAL, self::EMERGENCY ], true ) ) {
			$entry .= ' | URL: ' . ( $_SERVER['REQUEST_URI'] ?? 'CLI' );
		}

		return $entry . "\n";
	}

	/**
	 * Sanitize context data to remove sensitive information
	 *
	 * @param array $context Context data
	 * @return array
	 */
	private static function sanitize_context( $context ) {
		$sensitive_keys = [
			'password',
			'pass',
			'pwd',
			'secret',
			'token',
			'api_key',
			'apikey',
			'credit_card',
			'card_number',
			'cvv',
			'cc_number',
		];

		foreach ( $context as $key => $value ) {
			$key_lower = strtolower( $key );

			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $key_lower, $sensitive ) !== false ) {
					$context[ $key ] = '***REDACTED***';
					break;
				}
			}

			// Recursively sanitize nested arrays
			if ( is_array( $value ) ) {
				$context[ $key ] = self::sanitize_context( $value );
			}
		}

		return $context;
	}

	/**
	 * Write a log entry
	 *
	 * @param string $level   Log level
	 * @param string $message Log message
	 * @param array  $context Additional context
	 * @return bool
	 */
	private static function log( $level, $message, $context = [] ) {
		if ( ! self::should_log( $level ) ) {
			return false;
		}

		self::maybe_rotate_logs();

		$log_file = self::get_log_file();
		$entry    = self::format_message( $level, $message, $context );

		// Write to file
		$result = error_log( $entry, 3, $log_file );

		// Also log critical errors to WordPress error log
		if ( in_array( $level, [ self::EMERGENCY, self::ALERT, self::CRITICAL ], true ) ) {
			error_log( '[WPDMPP] ' . $entry );
		}

		return $result;
	}

	/**
	 * System is unusable
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function emergency( $message, $context = [] ) {
		self::log( self::EMERGENCY, $message, $context );
	}

	/**
	 * Action must be taken immediately
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function alert( $message, $context = [] ) {
		self::log( self::ALERT, $message, $context );
	}

	/**
	 * Critical conditions
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function critical( $message, $context = [] ) {
		self::log( self::CRITICAL, $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function error( $message, $context = [] ) {
		self::log( self::ERROR, $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function warning( $message, $context = [] ) {
		self::log( self::WARNING, $message, $context );
	}

	/**
	 * Normal but significant events
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function notice( $message, $context = [] ) {
		self::log( self::NOTICE, $message, $context );
	}

	/**
	 * Interesting events
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function info( $message, $context = [] ) {
		self::log( self::INFO, $message, $context );
	}

	/**
	 * Detailed debug information
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public static function debug( $message, $context = [] ) {
		self::log( self::DEBUG, $message, $context );
	}

	/**
	 * Log an exception
	 *
	 * @param \Throwable $exception The exception to log
	 * @param string     $level     Log level (default: error)
	 * @param array      $context   Additional context
	 */
	public static function exception( $exception, $level = self::ERROR, $context = [] ) {
		$context['exception_class'] = get_class( $exception );
		$context['file']            = $exception->getFile();
		$context['line']            = $exception->getLine();
		$context['trace']           = $exception->getTraceAsString();

		if ( $exception->getPrevious() ) {
			$context['previous'] = $exception->getPrevious()->getMessage();
		}

		self::log( $level, $exception->getMessage(), $context );
	}

	/**
	 * Log a payment-related event
	 *
	 * @param string $gateway Gateway name
	 * @param string $message Log message
	 * @param array  $context Additional context
	 * @param string $level   Log level (default: info)
	 */
	public static function payment( $gateway, $message, $context = [], $level = self::INFO ) {
		$context['gateway'] = $gateway;
		self::log( $level, '[Payment] ' . $message, $context );
	}

	/**
	 * Log an order-related event
	 *
	 * @param string $order_id Order ID
	 * @param string $message  Log message
	 * @param array  $context  Additional context
	 * @param string $level    Log level (default: info)
	 */
	public static function order( $order_id, $message, $context = [], $level = self::INFO ) {
		$context['order_id'] = $order_id;
		self::log( $level, '[Order] ' . $message, $context );
	}

	/**
	 * Get all log files
	 *
	 * @return array
	 */
	public static function get_log_files() {
		self::init();

		$files = glob( self::$log_dir . 'wpdmpp-*.log' );
		$logs  = [];

		if ( $files ) {
			foreach ( $files as $file ) {
				$logs[] = [
					'name'     => basename( $file ),
					'path'     => $file,
					'size'     => filesize( $file ),
					'modified' => filemtime( $file ),
				];
			}

			// Sort by modification time (newest first)
			usort( $logs, function ( $a, $b ) {
				return $b['modified'] - $a['modified'];
			} );
		}

		return $logs;
	}

	/**
	 * Get log file contents
	 *
	 * @param string $filename Log filename
	 * @param int    $lines    Number of lines to return (0 = all)
	 * @return string|false
	 */
	public static function get_log_contents( $filename, $lines = 100 ) {
		self::init();

		$file = self::$log_dir . basename( $filename );

		if ( ! file_exists( $file ) ) {
			return false;
		}

		if ( $lines === 0 ) {
			return file_get_contents( $file );
		}

		// Get last N lines efficiently
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return false;
		}

		$buffer     = [];
		$line_count = 0;

		while ( ! feof( $handle ) ) {
			$line = fgets( $handle );
			if ( $line !== false ) {
				$buffer[] = $line;
				$line_count++;

				// Keep buffer at max size
				if ( $line_count > $lines ) {
					array_shift( $buffer );
				}
			}
		}

		fclose( $handle );

		return implode( '', $buffer );
	}

	/**
	 * Clear a specific log file
	 *
	 * @param string $filename Log filename
	 * @return bool
	 */
	public static function clear_log( $filename ) {
		self::init();

		$file = self::$log_dir . basename( $filename );

		if ( file_exists( $file ) ) {
			return @unlink( $file );
		}

		return false;
	}

	/**
	 * Clear all log files
	 *
	 * @return int Number of files deleted
	 */
	public static function clear_all_logs() {
		self::init();

		$files   = glob( self::$log_dir . 'wpdmpp-*.log' );
		$deleted = 0;

		if ( $files ) {
			foreach ( $files as $file ) {
				if ( @unlink( $file ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Get log directory path
	 *
	 * @return string
	 */
	public static function get_log_dir() {
		self::init();
		return self::$log_dir;
	}
}
