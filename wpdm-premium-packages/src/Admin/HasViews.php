<?php
/**
 * HasViews Trait
 *
 * Provides view rendering functionality for admin services.
 *
 * @package WPDMPP\Admin
 * @since 7.0.0
 */

namespace WPDMPP\Admin;

defined('ABSPATH') || exit;

trait HasViews
{
    /**
     * Get the path to a view file
     *
     * @param string $view View name (without .php extension)
     * @return string Full path to the view file
     */
    protected function getViewPath(string $view): string
    {
        $class = new \ReflectionClass($this);
        $dir = dirname($class->getFileName());
        return $dir . '/views/' . $view . '.php';
    }

    /**
     * Render a view file with data
     *
     * @param string $view View name (without .php extension)
     * @param array $data Data to extract and make available to the view
     * @return void
     */
    protected function renderView(string $view, array $data = []): void
    {
        $path = $this->getViewPath($view);
        if (file_exists($path)) {
            extract($data, EXTR_SKIP);
            include $path;
        }
    }

    /**
     * Get view content as string
     *
     * @param string $view View name (without .php extension)
     * @param array $data Data to extract and make available to the view
     * @return string View content
     */
    protected function getViewContent(string $view, array $data = []): string
    {
        ob_start();
        $this->renderView($view, $data);
        return ob_get_clean();
    }

    /**
     * Check if a view file exists
     *
     * @param string $view View name (without .php extension)
     * @return bool
     */
    protected function viewExists(string $view): bool
    {
        return file_exists($this->getViewPath($view));
    }
}
