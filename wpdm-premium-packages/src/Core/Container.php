<?php
/**
 * Service Container
 *
 * Simple dependency injection container for managing services.
 *
 * @package WPDMPP\Core
 * @since 7.0.0
 */

namespace WPDMPP\Core;

defined('ABSPATH') || exit;

class Container {

    /**
     * Registered bindings
     *
     * @var array
     */
    private array $bindings = [];

    /**
     * Singleton instances
     *
     * @var array
     */
    private array $instances = [];

    /**
     * Register a shared/singleton binding
     *
     * @param string   $abstract Service identifier
     * @param callable $factory  Factory function
     */
    public function singleton(string $abstract, callable $factory): void {
        $this->bindings[$abstract] = [
            'factory' => $factory,
            'shared' => true,
        ];
    }

    /**
     * Register a binding
     *
     * @param string   $abstract Service identifier
     * @param callable $factory  Factory function
     */
    public function bind(string $abstract, callable $factory): void {
        $this->bindings[$abstract] = [
            'factory' => $factory,
            'shared' => false,
        ];
    }

    /**
     * Resolve a service
     *
     * @param string $abstract Service identifier
     * @return mixed
     * @throws \Exception If no binding found
     */
    public function get(string $abstract): mixed {
        // Return cached singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check if binding exists
        if (!isset($this->bindings[$abstract])) {
            throw new \Exception("No binding found for: {$abstract}");
        }

        $binding = $this->bindings[$abstract];
        $instance = $binding['factory']($this);

        // Cache if singleton
        if ($binding['shared']) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Check if a service is registered
     *
     * @param string $abstract Service identifier
     * @return bool
     */
    public function has(string $abstract): bool {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Set an instance directly
     *
     * @param string $abstract Service identifier
     * @param mixed  $instance The instance
     */
    public function instance(string $abstract, mixed $instance): void {
        $this->instances[$abstract] = $instance;
    }
}
