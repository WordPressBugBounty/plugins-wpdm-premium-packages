<?php
/**
 * Order Note Template REST API Endpoint
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Admin\Order\OrderNoteTemplateService;

defined('ABSPATH') || exit;

class OrderNoteTemplateEndpoint
{
    private OrderNoteTemplateService $service;

    public function __construct()
    {
        $this->service = OrderNoteTemplateService::getInstance();
    }

    public function register(): void
    {
        $namespace = RestApi::API_NAMESPACE;

        register_rest_route($namespace, '/admin/order-note-templates', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getTemplates'],
                'permission_callback' => [$this, 'checkAdminCapability'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'saveTemplate'],
                'permission_callback' => [$this, 'checkAdminCapability'],
                'args'                => [
                    'name' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'content' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'wp_kses_post',
                    ],
                    'id' => [
                        'type'              => 'string',
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route($namespace, '/admin/order-note-templates/(?P<id>[A-Za-z0-9_\-]+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getTemplate'],
                'permission_callback' => [$this, 'checkAdminCapability'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteTemplate'],
                'permission_callback' => [$this, 'checkAdminCapability'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    public function getTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        return RestApi::success([
            'templates' => $this->service->getTemplates(),
        ]);
    }

    public function getTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $template = $this->service->getTemplate($request->get_param('id'));

        if (!$template) {
            return RestApi::error(__('Template not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success([
            'template' => $template,
        ]);
    }

    public function saveTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $templates = $this->service->saveTemplate(
            $request->get_param('name'),
            $request->get_param('content'),
            $request->get_param('id')
        );

        return RestApi::success([
            'templates' => $templates,
        ], __('Template saved.', 'wpdm-premium-packages'));
    }

    public function deleteTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $templates = $this->service->deleteTemplate($request->get_param('id'));

        return RestApi::success([
            'templates' => $templates,
        ], __('Template deleted.', 'wpdm-premium-packages'));
    }

    public function checkAdminCapability(): bool
    {
        return current_user_can(WPDMPP_ADMIN_CAP);
    }
}
