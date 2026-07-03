<?php
/**
 * Order REST API Endpoint
 *
 * Handles order-related REST API operations.
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Order\Order;
use WPDMPP\Order\OrderService;

defined('ABSPATH') || exit;

class OrderEndpoint {

    /**
     * Order service instance
     *
     * @var OrderService
     */
    private OrderService $orderService;

    /**
     * Constructor
     */
    public function __construct() {
        $this->orderService = OrderService::instance();
    }

    /**
     * Register routes
     *
     * @return void
     */
    public function register(): void {
        $namespace = RestApi::API_NAMESPACE;

        // GET /orders - List orders for current user
        register_rest_route($namespace, '/orders', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getOrders'],
            'permission_callback' => [$this, 'checkAuthenticated'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['', 'Processing', 'Completed', 'Expired', 'Cancelled', 'Refunded'],
                    'default' => '',
                ],
                'completed_only' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // GET /orders/{id} - Get single order
        register_rest_route($namespace, '/orders/(?P<id>[a-zA-Z0-9]+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getOrder'],
            'permission_callback' => [$this, 'checkOrderAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /orders/{id}/items - Get order items
        register_rest_route($namespace, '/orders/(?P<id>[a-zA-Z0-9]+)/items', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getOrderItems'],
            'permission_callback' => [$this, 'checkOrderAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /orders/{id}/note - Add note to order (user-facing)
        register_rest_route($namespace, '/orders/(?P<id>[a-zA-Z0-9]+)/note', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'addNote'],
            'permission_callback' => [$this, 'checkOrderAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'note' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'file' => [
                    'type' => 'array',
                    'default' => [],
                    'items' => ['type' => 'string'],
                ],
                'admin' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'seller' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'customer' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // GET /orders/{id}/downloads - Get download links for order
        register_rest_route($namespace, '/orders/(?P<id>[a-zA-Z0-9]+)/downloads', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getDownloads'],
            'permission_callback' => [$this, 'checkOrderAccess'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /purchases - Get purchased items for current user
        register_rest_route($namespace, '/purchases', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getPurchases'],
            'permission_callback' => [$this, 'checkAuthenticated'],
        ]);

        // Admin-only routes
        // GET /admin/orders - List all orders (admin)
        register_rest_route($namespace, '/admin/orders', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetOrders'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                'status' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'payment_status' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'search' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'date_from' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'date_to' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'date',
                    'enum' => ['date', 'order_id', 'total', 'order_status'],
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                    'enum' => ['ASC', 'DESC'],
                ],
            ],
        ]);

        // PUT /admin/orders/{id} - Update order (admin)
        register_rest_route($namespace, '/admin/orders/(?P<id>[a-zA-Z0-9]+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'adminUpdateOrder'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order_status' => [
                    'type' => 'string',
                    'enum' => ['Processing', 'Completed', 'Expired', 'Cancelled', 'Refunded'],
                ],
                'payment_status' => [
                    'type' => 'string',
                    'enum' => ['Processing', 'Completed', 'Failed', 'Refunded', 'Pending'],
                ],
                'transaction_id' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'expire_date' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /admin/orders/{id}/complete - Complete order (admin)
        register_rest_route($namespace, '/admin/orders/(?P<id>[a-zA-Z0-9]+)/complete', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminCompleteOrder'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'send_email' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
        ]);

        // POST /admin/orders/{id}/cancel - Cancel order (admin)
        register_rest_route($namespace, '/admin/orders/(?P<id>[a-zA-Z0-9]+)/cancel', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminCancelOrder'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'reason' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /admin/orders/{id}/refund - Refund order (admin)
        register_rest_route($namespace, '/admin/orders/(?P<id>[a-zA-Z0-9]+)/refund', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminRefundOrder'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'amount' => [
                    'type' => 'number',
                    'minimum' => 0,
                ],
                'reason' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /admin/orders/{id}/note - Add note to order (admin)
        register_rest_route($namespace, '/admin/orders/(?P<id>[a-zA-Z0-9]+)/note', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminAddNote'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'note' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'admin' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'seller' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'customer' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'file' => [
                    'type' => 'array',
                    'default' => [],
                    'items' => ['type' => 'string'],
                ],
            ],
        ]);

        // DELETE /admin/orders/{id} - Delete order (admin)
        register_rest_route($namespace, '/admin/orders/(?P<id>[a-zA-Z0-9]+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'adminDeleteOrder'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /admin/orders/statistics - Get order statistics (admin)
        register_rest_route($namespace, '/admin/orders/statistics', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetStatistics'],
            'permission_callback' => [$this, 'checkAdminCapability'],
        ]);

        // POST /admin/orders/{id}/assign-user - Link order to a user (admin)
        register_rest_route($namespace, '/admin/orders/(?P<id>[a-zA-Z0-9]+)/assign-user', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminAssignUser'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'assignuser' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    /**
     * Get orders for current user
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getOrders(\WP_REST_Request $request): \WP_REST_Response {
        $userId = get_current_user_id();
        $completedOnly = $request->get_param('completed_only');

        $orders = $this->orderService->getUserOrders($userId, $completedOnly);

        $data = [];
        foreach ($orders as $order) {
            $data[] = $this->formatOrderResponse($order);
        }

        return RestApi::success([
            'orders' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Get single order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getOrder(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $order = $this->orderService->getOrder($orderId);

        if (!$order) {
            return RestApi::error(__('Order not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success([
            'order' => $this->formatOrderResponse($order, true),
        ]);
    }

    /**
     * Get order items
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getOrderItems(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $order = $this->orderService->getOrder($orderId);

        if (!$order) {
            return RestApi::error(__('Order not found.', 'wpdm-premium-packages'), 404);
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = $item->toArray();
        }

        return RestApi::success([
            'items' => $items,
            'count' => count($items),
        ]);
    }

    /**
     * Get download links for order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getDownloads(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $order = $this->orderService->getOrder($orderId);

        if (!$order) {
            return RestApi::error(__('Order not found.', 'wpdm-premium-packages'), 404);
        }

        if (!$order->canDownload()) {
            return RestApi::error(__('Downloads not available for this order.', 'wpdm-premium-packages'), 403);
        }

        $downloads = [];
        foreach ($order->getItems() as $item) {
            $urls = $item->getDownloadUrls();
            if (!empty($urls)) {
                $downloads[] = [
                    'product_id' => $item->getProductId(),
                    'product_name' => $item->getProductName(),
                    'files' => $urls,
                ];
            }
        }

        return RestApi::success([
            'downloads' => $downloads,
        ]);
    }

    /**
     * Get purchased items for current user
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getPurchases(\WP_REST_Request $request): \WP_REST_Response {
        $items = $this->orderService->getPurchasedItems();

        return RestApi::success([
            'purchases' => $items,
            'count' => count($items),
        ]);
    }

    /**
     * Add note to order (user-facing)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function addNote(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $note = $request->get_param('note');
        $files = $request->get_param('file') ?: [];

        // Sanitize files array
        $files = array_map('sanitize_file_name', $files);

        $result = $this->orderService->addNote($orderId, $note, [
            'files'    => $files,
            'admin'    => $request->get_param('admin'),
            'seller'   => $request->get_param('seller'),
            'customer' => $request->get_param('customer'),
        ]);

        if ($result === false) {
            return RestApi::error(__('Failed to add note.', 'wpdm-premium-packages'), 500);
        }

        $time = $result['time'];
        $data = $result;

        // Build BEM HTML matching user-order-details.php note markup
        $copy = [];
        if (isset($data['admin']))    $copy[] = 'Admin';
        if (isset($data['seller']))   $copy[] = 'Seller';
        if (isset($data['customer'])) $copy[] = 'Customer';

        // Determine sender label: "Customer" for order owner, "Seller" for admin/seller
        $order = $this->orderService->getOrder($orderId);
        $orderUid = $order ? (int) $order->getUserId() : 0;
        $noteSender = ((int) ($data['uid'] ?? 0) === $orderUid && $orderUid > 0)
            ? __('Customer', 'wpdm-premium-packages')
            : __('Seller', 'wpdm-premium-packages');

        ob_start();
        ?>
        <div class="wpdmpp-od__note">
            <div class="wpdmpp-od__note-body">
                <?php echo strip_tags(wpautop(stripslashes_deep($data['note'])), "<a><strong><b><img><br><p>"); ?>
            </div>
            <?php if (isset($data['file']) && is_array($data['file'])) : ?>
            <div class="wpdmpp-od__note-attachments">
                <?php foreach ($data['file'] as $file) :
                    $aid = \WPDM\__\Crypt::Encrypt($orderId . "|||" . $time . "|||" . $file);
                ?>
                    <a href="<?php echo esc_url(home_url("/?oid=" . $orderId . "&_atcdl=" . $aid)); ?>">
                        <?php echo \WPDMPP\UI\Icons::get('paperclip', 13); ?> <?php echo esc_html($file); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="wpdmpp-od__note-footer">
                <div class="wpdmpp-od__note-meta">
                    <span class="wpdmpp-od__note-meta-item"><?php echo \WPDMPP\UI\Icons::get('pencil', 12); ?> <?php echo esc_html($noteSender); ?></span>
                    <span class="wpdmpp-od__note-meta-item"><?php echo \WPDMPP\UI\Icons::get('clock', 12); ?> <?php echo wp_date(get_option('date_format') . " h:i", $time); ?></span>
                    <?php if (!empty($copy)): ?>
                    <span class="wpdmpp-od__note-meta-item"><?php _e('Sent to:', 'wpdm-premium-packages'); ?> <?php echo esc_html(implode(', ', $copy)); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        return RestApi::success([
            'html' => $html,
        ], __('Note added successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Admin: Get all orders
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetOrders(\WP_REST_Request $request): \WP_REST_Response {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');

        $result = $this->orderService->getOrders([
            'status' => $request->get_param('status'),
            'payment_status' => $request->get_param('payment_status'),
            'user_id' => $request->get_param('user_id'),
            'search' => $request->get_param('search'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]);

        $data = [];
        foreach ($result['orders'] as $order) {
            $data[] = $this->formatOrderResponse($order);
        }

        return RestApi::success([
            'orders' => $data,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($result['total'] / $perPage),
        ]);
    }

    /**
     * Admin: Update order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminUpdateOrder(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $order = $this->orderService->getOrder($orderId);

        if (!$order) {
            return RestApi::error(__('Order not found.', 'wpdm-premium-packages'), 404);
        }

        $updateData = [];

        if ($request->has_param('order_status')) {
            $updateData['order_status'] = $request->get_param('order_status');
        }

        if ($request->has_param('payment_status')) {
            $updateData['payment_status'] = $request->get_param('payment_status');
        }

        if ($request->has_param('transaction_id')) {
            $updateData['trans_id'] = $request->get_param('transaction_id');
        }

        if ($request->has_param('expire_date')) {
            $expireDate = $request->get_param('expire_date');
            $updateData['expire_date'] = !empty($expireDate) ? strtotime($expireDate) : 0;
        }

        if (empty($updateData)) {
            return RestApi::error(__('No data to update.', 'wpdm-premium-packages'), 400);
        }

        $result = $this->orderService->updateOrder($updateData, $orderId);

        if (!$result) {
            return RestApi::error(__('Failed to update order.', 'wpdm-premium-packages'), 500);
        }

        // Reload order
        $order = $this->orderService->getOrder($orderId);

        return RestApi::success([
            'order' => $this->formatOrderResponse($order),
        ], __('Order updated successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Admin: Complete order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminCompleteOrder(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $sendEmail = $request->get_param('send_email');

        $result = $this->orderService->completeOrder($orderId, $sendEmail);

        if (!$result) {
            return RestApi::error(__('Failed to complete order.', 'wpdm-premium-packages'), 500);
        }

        $order = $this->orderService->getOrder($orderId);

        return RestApi::success([
            'order' => $this->formatOrderResponse($order),
        ], __('Order completed successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Admin: Cancel order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminCancelOrder(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $reason = $request->get_param('reason');

        $result = $this->orderService->cancelOrder($orderId, $reason);

        if (!$result) {
            return RestApi::error(__('Failed to cancel order.', 'wpdm-premium-packages'), 500);
        }

        $order = $this->orderService->getOrder($orderId);

        return RestApi::success([
            'order' => $this->formatOrderResponse($order),
        ], __('Order cancelled successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Admin: Refund order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminRefundOrder(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $amount = $request->has_param('amount') ? (float) $request->get_param('amount') : null;
        $reason = $request->get_param('reason');

        $result = $this->orderService->refundOrder($orderId, $amount, $reason);

        if (!$result) {
            return RestApi::error(__('Failed to refund order.', 'wpdm-premium-packages'), 500);
        }

        $order = $this->orderService->getOrder($orderId);

        return RestApi::success([
            'order' => $this->formatOrderResponse($order),
        ], __('Order refunded successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Admin: Add note to order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminAddNote(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');
        $note = wp_kses($request->get_param('note'), [
            'strong' => [], 'b' => [], 'br' => [], 'p' => [], 'hr' => [],
            'a' => ['href' => [], 'title' => []],
        ]);

        $options = [];
        if ($request->get_param('admin'))    $options['admin']    = true;
        if ($request->get_param('seller'))   $options['seller']   = true;
        if ($request->get_param('customer')) $options['customer'] = true;
        $files = $request->get_param('file');
        if (!empty($files)) $options['files'] = array_map('sanitize_text_field', $files);

        $data = $this->orderService->addNote($orderId, $note, $options);

        if ($data === false) {
            return RestApi::error(__('Failed to add note.', 'wpdm-premium-packages'), 500);
        }

        $time = $data['time'];

        // Build admin note HTML (Bootstrap panel markup)
        $copy = [];
        if (isset($data['admin']))    $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Admin &nbsp; ';
        if (isset($data['seller']))   $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Seller &nbsp; ';
        if (isset($data['customer'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Customer &nbsp; ';
        $copyHtml = implode('', $copy);

        ob_start();
        ?>
        <div class="panel panel-default card mb-3">
            <div class="panel-body card-body">
                <?php
                $noteHtml = wpautop(strip_tags(stripcslashes($data['note']), '<a><strong><b><img>'));
                echo preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a target="_blank" href="\1">\1</a>', $noteHtml);
                ?>
            </div>
            <?php if (isset($data['file']) && is_array($data['file'])) { ?>
                <div class="panel-footer card-footer text-right">
                    <?php foreach ($data['file'] as $file) { ?>
                        <a href="#" style="margin-left: 10px"><?php echo \WPDMPP\UI\Icons::get('paperclip', 14); ?> <?php echo esc_html($file); ?></a> &nbsp;
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="panel-footer card-footer text-right">
                <small><em><?php echo \WPDMPP\UI\Icons::get('clock', 12); ?> <?php echo wp_date(get_option('date_format') . ' h:i', $time); ?></em></small>
                <div class="pull-left"><small><em><?php if ($copyHtml !== '') echo 'Copy sent to ' . $copyHtml; ?></em></small></div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        return RestApi::success([
            'html' => $html,
            'time' => $time,
        ], __('Note added successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Admin: Delete order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminDeleteOrder(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');

        $result = $this->orderService->deleteOrder($orderId);

        if (!$result) {
            return RestApi::error(__('Failed to delete order.', 'wpdm-premium-packages'), 500);
        }

        return RestApi::success([], __('Order deleted successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Admin: Get order statistics
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetStatistics(\WP_REST_Request $request): \WP_REST_Response {
        $stats = $this->orderService->getStatistics();

        return RestApi::success([
            'statistics' => $stats,
        ]);
    }

    /**
     * Admin: Link order to a user (assign customer)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminAssignUser(\WP_REST_Request $request): \WP_REST_Response {
        $orderId    = (string) $request->get_param('id');
        $assignuser = trim((string) $request->get_param('assignuser'));

        if ($assignuser === '') {
            return RestApi::error(__('Missing required parameters.', 'wpdm-premium-packages'), 400);
        }

        $u = is_email($assignuser)
            ? get_user_by('email', sanitize_email($assignuser))
            : get_user_by('login', sanitize_user($assignuser));

        if (!is_object($u) || empty($u->ID)) {
            return RestApi::error(__('User Not Found!', 'wpdm-premium-packages'), 404);
        }

        $this->orderService->updateOrder(['uid' => $u->ID], $orderId);

        $wpdmpp_settings = get_option('_wpdmpp_settings');
        $logo_url        = isset($wpdmpp_settings['logo_url']) ? $wpdmpp_settings['logo_url'] : '';
        $logo            = $logo_url !== ''
            ? "<img src='" . esc_url($logo_url) . "' alt='" . esc_attr(get_bloginfo('name')) . "'/>"
            : esc_html(get_bloginfo('name'));

        \WPDMPP\Customer\CustomerService::getInstance()->addCustomer((int) $u->ID);
        \WPDM\__\Email::send('purchase-confirmation', [
            'date'            => wp_date(get_option('date_format'), time()),
            'homeurl'         => home_url('/'),
            'sitename'        => get_bloginfo('name'),
            'order_link'      => "<a href='" . esc_url(wpdmpp_orders_page('id=' . $orderId)) . "'>" . esc_html(wpdmpp_orders_page('id=' . $orderId)) . "</a>",
            'register_link'   => "<a href='" . esc_url(wpdmpp_orders_page('orderid=' . $orderId)) . "'>" . esc_html(wpdmpp_orders_page('orderid=' . $orderId)) . "</a>",
            'name'            => $u->user_login,
            'orderid'         => $orderId,
            'to_email'        => $u->user_email,
            'order_url'       => wpdmpp_orders_page('id=' . $orderId),
            'order_url_admin' => admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $orderId),
            'img_logo'        => $logo,
        ]);

        $display = $u->display_name !== '' ? $u->display_name : $u->user_login;

        return RestApi::success([
            'user' => [
                'id'           => (int) $u->ID,
                'login'        => $u->user_login,
                'display_name' => $display,
                'email'        => $u->user_email,
                'profile_url'  => admin_url('edit.php?post_type=wpdmpro&page=customers&view=profile&id=' . (int) $u->ID),
                'orders_url'   => admin_url('edit.php?post_type=wpdmpro&page=orders&customer=' . (int) $u->ID . '&focus=' . rawurlencode($orderId)),
                'search_icon'  => \WPDMPP\UI\Icons::get('search', 14),
            ],
        ], sprintf(
            /* translators: %s: customer name or email */
            __('Order is linked to %s', 'wpdm-premium-packages'),
            $display
        ));
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function checkAuthenticated(): bool {
        return is_user_logged_in();
    }

    /**
     * Check if user has admin capability
     *
     * @return bool
     */
    public function checkAdminCapability(): bool {
        return current_user_can(WPDMPP_ADMIN_CAP);
    }

    /**
     * Check if user has access to the order
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function checkOrderAccess(\WP_REST_Request $request) {
        $orderId = $request->get_param('id');

        // Admin can access any order
        if (current_user_can(WPDMPP_ADMIN_CAP)) {
            return true;
        }

        // Must be logged in
        if (!is_user_logged_in()) {
            // Check for guest order in session
            if (class_exists('\WPDM\__\Session')) {
                $guestOrder = \WPDM\__\Session::get('guest_order');
                if ($guestOrder === $orderId) {
                    return true;
                }
            }

            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this order.', 'wpdm-premium-packages'),
                ['status' => 401]
            );
        }

        // Check if order belongs to user
        $order = $this->orderService->getOrder($orderId);
        if (!$order) {
            return new \WP_Error(
                'rest_not_found',
                __('Order not found.', 'wpdm-premium-packages'),
                ['status' => 404]
            );
        }

        if ($order->getUserId() !== get_current_user_id()) {
            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this order.', 'wpdm-premium-packages'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Format order for API response
     *
     * @param Order $order        Order object
     * @param bool  $includeItems Include items in response
     * @return array
     */
    private function formatOrderResponse(Order $order, bool $includeItems = false): array {
        $data = $order->toArray();

        // Add formatted prices
        $data['subtotal_formatted'] = $order->getFormattedSubtotal();
        $data['total_formatted'] = $order->getFormattedTotal();

        // Add URLs
        $data['order_url'] = $order->getOrderUrl();
        $data['admin_url'] = current_user_can(WPDMPP_ADMIN_CAP) ? $order->getAdminUrl() : '';

        if (!$includeItems) {
            // Remove detailed items for list view
            unset($data['items']);
        }

        return $data;
    }
}
