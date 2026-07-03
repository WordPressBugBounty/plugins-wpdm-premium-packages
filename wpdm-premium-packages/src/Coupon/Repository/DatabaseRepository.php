<?php
/**
 * Coupon Database Repository Implementation
 *
 * Implements coupon storage using WordPress database.
 *
 * @package WPDMPP\Coupon\Repository
 * @since 7.0.0
 */

namespace WPDMPP\Coupon\Repository;

use WPDMPP\Coupon\Coupon;
use WPDMPP\Coupon\CouponRepositoryInterface;

defined('ABSPATH') || exit;

class DatabaseRepository implements CouponRepositoryInterface {

    /**
     * Coupons table name
     *
     * @var string
     */
    private string $couponsTable;

    /**
     * Orders table name
     *
     * @var string
     */
    private string $ordersTable;

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
        $this->couponsTable = $wpdb->prefix . 'ahm_coupons';
        $this->ordersTable = $wpdb->prefix . 'ahm_orders';
    }

    /**
     * @inheritDoc
     */
    public function findByCode(string $code): ?Coupon {
        if (empty($code)) {
            return null;
        }

        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->couponsTable} WHERE code = %s",
            strtoupper(trim($code))
        ));

        if (!$row) {
            return null;
        }

        return Coupon::fromDatabase($row);
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?Coupon {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->couponsTable} WHERE ID = %d",
            $id
        ));

        if (!$row) {
            return null;
        }

        return Coupon::fromDatabase($row);
    }

    /**
     * @inheritDoc
     */
    public function findAll(array $args = []): array {
        $defaults = [
            'search' => '',
            'type' => '',
            'product_id' => 0,
            'active_only' => false,
            'auto_apply' => null,
            'orderby' => 'ID',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($args['search'])) {
            $search = '%' . $this->db->esc_like($args['search']) . '%';
            $where[] = '(code LIKE %s OR description LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        if (!empty($args['product_id'])) {
            $where[] = 'product = %d';
            $values[] = (int) $args['product_id'];
        }

        if ($args['active_only']) {
            $now = time();
            $where[] = '(expire_date = 0 OR expire_date > %d)';
            $values[] = $now;
            $where[] = '(usage_limit = 0 OR used < usage_limit)';
        }

        if ($args['auto_apply'] !== null) {
            $where[] = 'auto_apply = %d';
            $values[] = (int) $args['auto_apply'];
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM {$this->couponsTable} WHERE {$whereClause}";
        if (!empty($values)) {
            $countQuery = $this->db->prepare($countQuery, ...$values);
        }
        $total = (int) $this->db->get_var($countQuery);

        // Validate orderby
        $allowedOrderby = ['ID', 'code', 'discount', 'expire_date', 'used', 'usage_limit'];
        $orderby = in_array($args['orderby'], $allowedOrderby) ? $args['orderby'] : 'ID';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get results
        $query = "SELECT * FROM {$this->couponsTable} WHERE {$whereClause} ORDER BY {$orderby} {$order} LIMIT %d, %d";
        $values[] = (int) $args['offset'];
        $values[] = (int) $args['limit'];

        $rows = $this->db->get_results($this->db->prepare($query, ...$values));

        $coupons = [];
        foreach ($rows as $row) {
            $coupons[] = Coupon::fromDatabase($row);
        }

        return [
            'coupons' => $coupons,
            'total' => $total,
        ];
    }

    /**
     * @inheritDoc
     */
    public function findActive(): array {
        $now = time();

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->couponsTable}
            WHERE (expire_date = 0 OR expire_date > %d)
            AND (usage_limit = 0 OR used < usage_limit)
            ORDER BY ID DESC",
            $now
        ));

        $coupons = [];
        foreach ($rows as $row) {
            $coupons[] = Coupon::fromDatabase($row);
        }

        return $coupons;
    }

    /**
     * @inheritDoc
     */
    public function findAutoApply(float $cartTotal): array {
        $now = time();

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->couponsTable}
            WHERE auto_apply = 1
            AND min_order_amount <= %f
            AND (max_order_amount = 0 OR max_order_amount >= %f)
            AND (expire_date = 0 OR expire_date > %d)
            AND (usage_limit = 0 OR used < usage_limit)
            ORDER BY discount DESC",
            $cartTotal,
            $cartTotal,
            $now
        ));

        $coupons = [];
        foreach ($rows as $row) {
            $coupons[] = Coupon::fromDatabase($row);
        }

        return $coupons;
    }

    /**
     * @inheritDoc
     */
    public function findByProduct(int $productId): array {
        $now = time();

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->couponsTable}
            WHERE (product = %d OR product = 0)
            AND (expire_date = 0 OR expire_date > %d)
            AND (usage_limit = 0 OR used < usage_limit)
            ORDER BY product DESC, discount DESC",
            $productId,
            $now
        ));

        $coupons = [];
        foreach ($rows as $row) {
            $coupons[] = Coupon::fromDatabase($row);
        }

        return $coupons;
    }

    /**
     * @inheritDoc
     */
    public function save(Coupon $coupon): bool {
        $data = $coupon->toDatabase();

        if ($coupon->getId() !== null) {
            // Update existing coupon
            $result = $this->db->update(
                $this->couponsTable,
                $data,
                ['ID' => $coupon->getId()],
                $this->getColumnFormats($data),
                ['%d']
            );

            return $result !== false;
        }

        // Insert new coupon
        $result = $this->db->insert(
            $this->couponsTable,
            $data,
            $this->getColumnFormats($data)
        );

        if ($result) {
            $coupon->setId($this->db->insert_id);
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): bool {
        $result = $this->db->delete(
            $this->couponsTable,
            ['ID' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function deleteByCode(string $code): bool {
        $result = $this->db->delete(
            $this->couponsTable,
            ['code' => strtoupper(trim($code))],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function codeExists(string $code, ?int $excludeId = null): bool {
        $code = strtoupper(trim($code));

        if ($excludeId !== null) {
            $count = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->couponsTable} WHERE code = %s AND ID != %d",
                $code,
                $excludeId
            ));
        } else {
            $count = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->couponsTable} WHERE code = %s",
                $code
            ));
        }

        return (int) $count > 0;
    }

    /**
     * @inheritDoc
     */
    public function incrementUsage(string $code): bool {
        $result = $this->db->query($this->db->prepare(
            "UPDATE {$this->couponsTable} SET used = used + 1 WHERE code = %s",
            strtoupper(trim($code))
        ));

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function getOrdersByCoupon(string $code): array {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->ordersTable} WHERE coupon_code = %s ORDER BY date DESC",
            $code
        ));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @inheritDoc
     */
    public function count(array $args = []): int {
        $where = ['1=1'];
        $values = [];

        if (!empty($args['active_only'])) {
            $now = time();
            $where[] = '(expire_date = 0 OR expire_date > %d)';
            $values[] = $now;
            $where[] = '(usage_limit = 0 OR used < usage_limit)';
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $whereClause = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$this->couponsTable} WHERE {$whereClause}";

        if (!empty($values)) {
            $query = $this->db->prepare($query, ...$values);
        }

        return (int) $this->db->get_var($query);
    }

    /**
     * @inheritDoc
     */
    public function getUsageStats(string $code): array {
        $coupon = $this->findByCode($code);
        if (!$coupon) {
            return [];
        }

        // Get orders using this coupon
        $orders = $this->getOrdersByCoupon($code);

        $totalDiscountGiven = 0.0;
        $totalOrderValue = 0.0;
        $orderCount = count($orders);

        foreach ($orders as $order) {
            $totalDiscountGiven += (float) $order->coupon_discount;
            $totalOrderValue += (float) $order->total;
        }

        return [
            'coupon' => $coupon->toArray(),
            'times_used' => $coupon->getUsed(),
            'remaining_uses' => $coupon->hasUsageLimit() ? $coupon->getRemainingUses() : null,
            'order_count' => $orderCount,
            'total_discount_given' => $totalDiscountGiven,
            'total_order_value' => $totalOrderValue,
            'average_order_value' => $orderCount > 0 ? $totalOrderValue / $orderCount : 0,
        ];
    }

    /**
     * Get expired coupons
     *
     * @return Coupon[]
     */
    public function findExpired(): array {
        $now = time();

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->couponsTable}
            WHERE expire_date > 0 AND expire_date < %d
            ORDER BY expire_date DESC",
            $now
        ));

        $coupons = [];
        foreach ($rows as $row) {
            $coupons[] = Coupon::fromDatabase($row);
        }

        return $coupons;
    }

    /**
     * Get exhausted coupons (usage limit reached)
     *
     * @return Coupon[]
     */
    public function findExhausted(): array {
        $rows = $this->db->get_results(
            "SELECT * FROM {$this->couponsTable}
            WHERE usage_limit > 0 AND used >= usage_limit
            ORDER BY ID DESC"
        );

        $coupons = [];
        foreach ($rows as $row) {
            $coupons[] = Coupon::fromDatabase($row);
        }

        return $coupons;
    }

    /**
     * Bulk delete coupons
     *
     * @param array $ids Coupon IDs
     * @return int Number of deleted coupons
     */
    public function bulkDelete(array $ids): int {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $result = $this->db->query($this->db->prepare(
            "DELETE FROM {$this->couponsTable} WHERE ID IN ({$placeholders})",
            ...$ids
        ));

        return $result !== false ? $result : 0;
    }

    /**
     * Get column formats for database operations
     *
     * @param array $data Data array
     * @return array
     */
    private function getColumnFormats(array $data): array {
        $formats = [];

        $intColumns = ['min_order_amount', 'max_order_amount', 'product', 'expire_date', 'usage_limit', 'used', 'auto_apply'];
        $floatColumns = ['discount'];

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
     * Generate a unique coupon code
     *
     * @param int    $length Code length
     * @param string $prefix Code prefix
     * @return string
     */
    public function generateUniqueCode(int $length = 8, string $prefix = ''): string {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $code = $prefix;
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $attempt++;
        } while ($this->codeExists($code) && $attempt < $maxAttempts);

        return $code;
    }
}
