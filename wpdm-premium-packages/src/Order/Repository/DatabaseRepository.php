<?php
/**
 * Database Repository Implementation
 *
 * Implements order storage using WordPress database.
 *
 * @package WPDMPP\Order\Repository
 * @since 7.0.0
 */

namespace WPDMPP\Order\Repository;

use WPDMPP\Order\Order;
use WPDMPP\Order\OrderItem;
use WPDMPP\Order\OrderRepositoryInterface;

defined('ABSPATH') || exit;

class DatabaseRepository implements OrderRepositoryInterface {

    /**
     * Orders table name
     *
     * @var string
     */
    private string $ordersTable;

    /**
     * Order items table name
     *
     * @var string
     */
    private string $itemsTable;

    /**
     * Order renewals table name
     *
     * @var string
     */
    private string $renewsTable;

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private \wpdb $db;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->ordersTable = $wpdb->prefix . 'ahm_orders';
        $this->itemsTable = $wpdb->prefix . 'ahm_order_items';
        $this->renewsTable = $wpdb->prefix . 'ahm_order_renews';
    }

    /**
     * @inheritDoc
     */
    public function find(string $orderId): ?Order {
        if (empty($orderId)) {
            return null;
        }

        // Handle renewal suffix
        if (strpos($orderId, 'renew') !== false) {
            $parts = explode('_', $orderId);
            $orderId = $parts[0];
        }

        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->ordersTable} WHERE order_id = %s OR trans_id = %s",
            $orderId,
            $orderId
        ));

        if (!$row) {
            return null;
        }

        $order = Order::fromDatabase($row);

        // Load items
        $items = $this->getItems($order->getOrderId());
        $order->setItems($items);

        return $order;
    }

    /**
     * @inheritDoc
     */
    public function findByTransactionId(string $transactionId): ?Order {
        if (empty($transactionId)) {
            return null;
        }

        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->ordersTable} WHERE trans_id = %s",
            $transactionId
        ));

        if (!$row) {
            return null;
        }

        $order = Order::fromDatabase($row);

        // Load items
        $items = $this->getItems($order->getOrderId());
        $order->setItems($items);

        return $order;
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?Order {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->ordersTable} WHERE ID = %d",
            $id
        ));

        if (!$row) {
            return null;
        }

        $order = Order::fromDatabase($row);

        // Load items
        $items = $this->getItems($order->getOrderId());
        $order->setItems($items);

        return $order;
    }

    /**
     * @inheritDoc
     */
    public function findByUser(int $userId, bool $completedOnly = false): array {
        if ($completedOnly) {
            $rows = $this->db->get_results($this->db->prepare(
                "SELECT * FROM {$this->ordersTable}
                WHERE uid = %d AND (order_status = 'Completed' OR order_status = 'Expired')
                ORDER BY order_status DESC, date DESC",
                $userId
            ));
        } else {
            $rows = $this->db->get_results($this->db->prepare(
                "SELECT * FROM {$this->ordersTable}
                WHERE uid = %d
                ORDER BY order_status DESC, date DESC",
                $userId
            ));
        }

        $orders = [];
        foreach ($rows as $row) {
            $order = Order::fromDatabase($row);
            $items = $this->getItems($order->getOrderId());
            $order->setItems($items);
            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * @inheritDoc
     */
    public function findAll(array $args = []): array {
        $defaults = [
            'status' => '',
            'payment_status' => '',
            'user_id' => 0,
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($args['status'])) {
            $where[] = 'order_status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['payment_status'])) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }

        if (!empty($args['user_id'])) {
            $where[] = 'uid = %d';
            $values[] = (int) $args['user_id'];
        }

        if (!empty($args['search'])) {
            $search = '%' . $this->db->esc_like($args['search']) . '%';
            $where[] = '(order_id LIKE %s OR trans_id LIKE %s OR title LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        if (!empty($args['date_from'])) {
            $where[] = 'date >= %d';
            $values[] = strtotime($args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $where[] = 'date <= %d';
            $values[] = strtotime($args['date_to']) + 86399; // End of day
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM {$this->ordersTable} WHERE {$whereClause}";
        if (!empty($values)) {
            $countQuery = $this->db->prepare($countQuery, ...$values);
        }
        $total = (int) $this->db->get_var($countQuery);

        // Validate orderby
        $allowedOrderby = ['date', 'order_id', 'total', 'order_status', 'payment_status'];
        $orderby = in_array($args['orderby'], $allowedOrderby) ? $args['orderby'] : 'date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get results
        $query = "SELECT * FROM {$this->ordersTable} WHERE {$whereClause} ORDER BY {$orderby} {$order} LIMIT %d, %d";
        $values[] = (int) $args['offset'];
        $values[] = (int) $args['limit'];

        $rows = $this->db->get_results($this->db->prepare($query, ...$values));

        $orders = [];
        foreach ($rows as $row) {
            $order = Order::fromDatabase($row);
            $items = $this->getItems($order->getOrderId());
            $order->setItems($items);
            $orders[] = $order;
        }

        return [
            'orders' => $orders,
            'total' => $total,
        ];
    }

    /**
     * @inheritDoc
     */
    public function save(Order $order): bool {
        $data = $order->toDatabase();
        $orderId = $order->getOrderId();

        if ($this->exists($orderId)) {
            // Update existing order
            $result = $this->db->update(
                $this->ordersTable,
                $data,
                ['order_id' => $orderId],
                $this->getColumnFormats($data),
                ['%s']
            );

            if ($result === false) {
                return false;
            }
        } else {
            // Insert new order
            $result = $this->db->insert(
                $this->ordersTable,
                $data,
                $this->getColumnFormats($data)
            );

            if (!$result) {
                return false;
            }

            // Set the database ID
            $order->setId($this->db->insert_id);
        }

        // Save order items
        $items = $order->getItems();
        if (!empty($items)) {
            $this->saveItems($orderId, $items);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function update(array $data, string $orderId): bool {
        if (empty($orderId)) {
            return false;
        }

        // Serialize arrays
        foreach (['items', 'currency', 'billing_info', 'cart_data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = serialize($data[$key]);
            }
        }

        $result = $this->db->update(
            $this->ordersTable,
            $data,
            ['order_id' => $orderId],
            $this->getColumnFormats($data),
            ['%s']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $orderId): bool {
        if (empty($orderId)) {
            return false;
        }

        // Delete items first
        $this->deleteItems($orderId);

        // Delete renewals
        $this->db->delete($this->renewsTable, ['order_id' => $orderId], ['%s']);

        // Delete order
        $result = $this->db->delete($this->ordersTable, ['order_id' => $orderId], ['%s']);

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $orderId): bool {
        if (empty($orderId)) {
            return false;
        }

        $count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->ordersTable} WHERE order_id = %s",
            $orderId
        ));

        return (int) $count > 0;
    }

    /**
     * @inheritDoc
     */
    public function getItems(string $orderId): array {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->itemsTable} WHERE oid = %s",
            $orderId
        ), ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $item = OrderItem::fromDatabase($row);
            $items[$item->getProductId()] = $item;
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function saveItems(string $orderId, array $items): bool {
        // Delete existing items first
        $this->deleteItems($orderId);

        foreach ($items as $item) {
            if (!($item instanceof OrderItem)) {
                continue;
            }

            $data = $item->toDatabase();
            $data['oid'] = $orderId;

            $result = $this->db->insert(
                $this->itemsTable,
                $data,
                $this->getItemColumnFormats($data)
            );

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteItems(string $orderId): bool {
        $result = $this->db->query($this->db->prepare(
            "DELETE FROM {$this->itemsTable} WHERE oid = %s",
            $orderId
        ));

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function getMeta(string $orderId, string $key = '') {
        $order = $this->find($orderId);
        if (!$order) {
            return $key ? null : [];
        }

        // Meta is stored in the order table itself for this implementation
        // Future versions could use a separate meta table
        if ($key) {
            return $order->getMeta($key);
        }

        return $order->getAllMeta();
    }

    /**
     * @inheritDoc
     */
    public function updateMeta(string $orderId, string $key, $value): bool {
        // For now, meta is handled within the order entity
        // This could be extended to use a separate meta table
        $order = $this->find($orderId);
        if (!$order) {
            return false;
        }

        $order->setMeta($key, $value);
        return $this->save($order);
    }

    /**
     * @inheritDoc
     */
    public function deleteMeta(string $orderId, string $key): bool {
        $order = $this->find($orderId);
        if (!$order) {
            return false;
        }

        $meta = $order->getAllMeta();
        unset($meta[$key]);
        $order->setAllMeta($meta);

        return $this->save($order);
    }

    /**
     * @inheritDoc
     */
    public function getNotes(string $orderId): array {
        $order = $this->find($orderId);
        return $order ? $order->getNotes() : [];
    }

    /**
     * @inheritDoc
     */
    public function addNote(string $orderId, string $note, string $author = ''): bool {
        $order = $this->find($orderId);
        if (!$order) {
            return false;
        }

        $order->addNote($note, $author);
        return $this->save($order);
    }

    /**
     * @inheritDoc
     */
    public function getRenewals(string $orderId): array {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->renewsTable} WHERE order_id = %s ORDER BY date DESC",
            $orderId
        ));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @inheritDoc
     */
    public function addRenewal(string $orderId, float $total, string $subscriptionId = '', string $invoice = '', int $date = 0): bool {
        $data = [
            'order_id' => $orderId,
            'total' => $total,
            'subscription_id' => $subscriptionId,
            'date' => $date ?: time(),
        ];

        if (!empty($invoice)) {
            $data['ipn'] = $invoice;
        }

        $result = $this->db->insert($this->renewsTable, $data);

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function count(array $args = []): int {
        $where = ['1=1'];
        $values = [];

        if (!empty($args['status'])) {
            $where[] = 'order_status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['payment_status'])) {
            $where[] = 'payment_status = %s';
            $values[] = $args['payment_status'];
        }

        if (!empty($args['user_id'])) {
            $where[] = 'uid = %d';
            $values[] = (int) $args['user_id'];
        }

        $whereClause = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$this->ordersTable} WHERE {$whereClause}";

        if (!empty($values)) {
            $query = $this->db->prepare($query, ...$values);
        }

        return (int) $this->db->get_var($query);
    }

    /**
     * @inheritDoc
     */
    public function getPurchasedItems(int $userId): array {
        if (!$userId) {
            return [];
        }

        $now = time();

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT oi.*, o.order_status, o.date AS order_date, o.expire_date
            FROM {$this->itemsTable} oi
            INNER JOIN {$this->ordersTable} o ON o.order_id = oi.oid
            WHERE o.uid = %d AND o.order_status IN ('Expired', 'Completed')
            ORDER BY o.date DESC",
            $userId
        ));

        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $item = OrderItem::fromDatabase($row);
            $itemData = $item->toArray();

            // Add order info
            $itemData['order_status'] = $row->order_status;
            $itemData['order_date'] = $row->order_date;
            $itemData['expire_date'] = $row->expire_date;
            $itemData['can_download'] = $row->order_status === 'Completed' && ($row->expire_date == 0 || $row->expire_date > $now);

            // Add download URLs if can download
            if ($itemData['can_download']) {
                $itemData['download_urls'] = $item->getDownloadUrls();
            }

            $items[] = $itemData;
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function hasPurchased(int $productId, int $userId): bool {
        if (!$productId || !$userId) {
            return false;
        }

        $count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*)
            FROM {$this->itemsTable} oi
            INNER JOIN {$this->ordersTable} o ON o.order_id = oi.oid
            WHERE oi.pid = %d AND o.uid = %d AND o.order_status IN ('Completed', 'Expired')",
            $productId,
            $userId
        ));

        return (int) $count > 0;
    }

    /**
     * Get column formats for database operations
     *
     * @param array $data Data array
     * @return array
     */
    private function getColumnFormats(array $data): array {
        $formats = [];

        $intColumns = ['uid', 'date', 'expire_date', 'auto_renew', 'download'];
        $floatColumns = ['subtotal', 'coupon_discount', 'cart_discount', 'tax', 'total', 'refund'];

        foreach (array_keys($data) as $key) {
            if (in_array($key, $intColumns)) {
                $formats[] = '%d';
            } elseif (in_array($key, $floatColumns)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * Get column formats for order items
     *
     * @param array $data Data array
     * @return array
     */
    private function getItemColumnFormats(array $data): array {
        $formats = [];

        $intColumns = ['pid', 'quantity', 'sid', 'cid', 'year', 'month', 'day'];
        $floatColumns = ['price', 'coupon_discount', 'role_discount', 'site_commission'];

        foreach (array_keys($data) as $key) {
            if (in_array($key, $intColumns)) {
                $formats[] = '%d';
            } elseif (in_array($key, $floatColumns)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * Get total sales for a product
     *
     * @param int    $productId Product ID
     * @param string $period    Period (today, week, month, year, all)
     * @return float
     */
    public function getProductSales(int $productId, string $period = 'all'): float {
        $where = ['oi.pid = %d', "(o.payment_status = 'Completed' OR o.payment_status = 'Expired')"];
        $values = [$productId];

        switch ($period) {
            case 'today':
                $where[] = 'o.date >= %d';
                $values[] = strtotime('today');
                break;
            case 'week':
                $where[] = 'o.date >= %d';
                $values[] = strtotime('-7 days');
                break;
            case 'month':
                $where[] = 'o.date >= %d';
                $values[] = strtotime('-30 days');
                break;
            case 'year':
                $where[] = 'o.date >= %d';
                $values[] = strtotime('-1 year');
                break;
        }

        $whereClause = implode(' AND ', $where);

        $total = $this->db->get_var($this->db->prepare(
            "SELECT SUM(oi.price * oi.quantity)
            FROM {$this->itemsTable} oi
            INNER JOIN {$this->ordersTable} o ON oi.oid = o.order_id
            WHERE {$whereClause}",
            ...$values
        ));

        return (float) $total;
    }

    /**
     * Get order count by status
     *
     * @return array
     */
    public function getCountByStatus(): array {
        $results = $this->db->get_results(
            "SELECT order_status, COUNT(*) as count FROM {$this->ordersTable} GROUP BY order_status"
        );

        $counts = [
            Order::STATUS_PROCESSING => 0,
            Order::STATUS_COMPLETED => 0,
            Order::STATUS_EXPIRED => 0,
            Order::STATUS_CANCELLED => 0,
            Order::STATUS_REFUNDED => 0,
            Order::STATUS_PENDING => 0,
        ];

        foreach ($results as $row) {
            $counts[$row->order_status] = (int) $row->count;
        }

        return $counts;
    }

    /**
     * Get daily sales data
     *
     * @param int $days Number of days
     * @return array
     */
    public function getDailySales(int $days = 30): array {
        $startDate = strtotime("-{$days} days");

        $results = $this->db->get_results($this->db->prepare(
            "SELECT DATE(FROM_UNIXTIME(date)) as sale_date, SUM(total) as total, COUNT(*) as count
            FROM {$this->ordersTable}
            WHERE date >= %d AND (payment_status = 'Completed' OR payment_status = 'Expired')
            GROUP BY sale_date
            ORDER BY sale_date ASC",
            $startDate
        ));

        $sales = [];
        foreach ($results as $row) {
            $sales[$row->sale_date] = [
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ];
        }

        return $sales;
    }
}
