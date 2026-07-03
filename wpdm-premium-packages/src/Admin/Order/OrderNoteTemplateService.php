<?php
/**
 * Order Note Template Service
 *
 * Manages order note templates for quick insertion of predefined notes.
 *
 * @package WPDMPP\Admin\Order
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Order;

defined('ABSPATH') || exit;

class OrderNoteTemplateService
{
    /**
     * Singleton instance
     *
     * @var OrderNoteTemplateService|null
     */
    private static ?OrderNoteTemplateService $instance = null;

    /**
     * Whether the service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Option key for storing templates
     *
     * @var string
     */
    public const OPTION_KEY = '__wpdmpp_order_note_templates';

    /**
     * Get singleton instance
     *
     * @return OrderNoteTemplateService
     */
    public static function getInstance(): OrderNoteTemplateService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {}

    /**
     * Register hooks
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        add_action('wp_ajax_wpdm_save_order_note_template', [$this, 'ajaxSaveTemplate']);
        add_action('wp_ajax_wpdm_edit_order_note_template', [$this, 'ajaxGetTemplate']);
        add_action('wp_ajax_wpdm_delete_order_note_template', [$this, 'ajaxDeleteTemplate']);
        add_action('wp_ajax_wpdm_get_order_note_templates', [$this, 'ajaxGetTemplates']);
    }

    /**
     * Get all templates
     *
     * @return array
     */
    public function getTemplates(): array
    {
        $templates = get_option(self::OPTION_KEY);

        if ($templates) {
            $templates = json_decode($templates, true);
        }

        if (!is_array($templates)) {
            $templates = [];
        }

        // Normalize template data
        foreach ($templates as $id => &$template) {
            if (is_array($template)) {
                $template['id'] = $id;
                $template['content'] = isset($template['content']) ? stripslashes($template['content']) : '';
            }
        }

        return $templates;
    }

    /**
     * Get a single template
     *
     * @param string $id Template ID
     * @return array|null
     */
    public function getTemplate(string $id): ?array
    {
        $templates = $this->getTemplates();
        return $templates[$id] ?? null;
    }

    /**
     * Save a template
     *
     * @param string $name    Template name
     * @param string $content Template content
     * @param string $id      Optional existing ID for update
     * @return array All templates after save
     */
    public function saveTemplate(string $name, string $content, string $id = ''): array
    {
        $templates = $this->getTemplates();

        // If updating, remove old entry
        if ($id !== '') {
            foreach ($templates as $existingId => $template) {
                if (isset($template['id']) && $template['id'] === $id) {
                    unset($templates[$existingId]);
                }
            }
        }

        // Generate ID from name
        $newId = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $name));

        // Save template
        $templates[$newId] = [
            'id' => $newId,
            'name' => $name,
            'content' => stripslashes($content),
        ];

        update_option(self::OPTION_KEY, json_encode($templates), false);

        return $templates;
    }

    /**
     * Delete a template
     *
     * @param string $id Template ID
     * @return array All templates after delete
     */
    public function deleteTemplate(string $id): array
    {
        $templates = $this->getTemplates();
        unset($templates[$id]);
        update_option(self::OPTION_KEY, json_encode($templates), false);

        return $templates;
    }

    /**
     * AJAX: Get all templates
     */
    public function ajaxGetTemplates(): void
    {
        if (!method_exists('\WPDM\__\__', 'isAuthentic')) {
            wp_send_json_error(['message' => 'Authentication failed.']);
        }

        \WPDM\__\__::isAuthentic('__ontgnonce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);

        wp_send_json($this->getTemplates());
    }

    /**
     * AJAX: Save a template
     */
    public function ajaxSaveTemplate(): void
    {
        if (!wp_verify_nonce(wpdm_query_var('__ontxnonce'), NONCE_KEY) || !current_user_can(WPDM_ADMIN_CAP)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $name = wpdm_query_var('ont/name', 'txt');
        $content = wpdm_query_var('ont/template', 'kses');
        $id = wpdm_query_var('id', 'txt') ?: '';

        $templates = $this->saveTemplate($name, $content, $id);

        wp_send_json($templates);
    }

    /**
     * AJAX: Get a single template for editing
     */
    public function ajaxGetTemplate(): void
    {
        if (!method_exists('\WPDM\__\__', 'isAuthentic')) {
            wp_send_json_error(['message' => 'Authentication failed.']);
        }

        \WPDM\__\__::isAuthentic('__ontednonce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);

        $id = wpdm_query_var('id', 'txt');
        $template = $this->getTemplate($id);

        if ($template) {
            wp_send_json($template);
        } else {
            wp_send_json_error(['message' => 'Template not found.']);
        }
    }

    /**
     * AJAX: Delete a template
     */
    public function ajaxDeleteTemplate(): void
    {
        if (!method_exists('\WPDM\__\__', 'isAuthentic')) {
            wp_send_json_error(['message' => 'Authentication failed.']);
        }

        \WPDM\__\__::isAuthentic('__ontdelnonce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);

        $id = wpdm_query_var('id', 'txt');
        $templates = $this->deleteTemplate($id);

        wp_send_json($templates);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
