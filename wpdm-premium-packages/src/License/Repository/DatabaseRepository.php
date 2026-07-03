<?php
/**
 * License Database Repository Implementation
 *
 * Implements license storage using WordPress database.
 *
 * @package WPDMPP\License\Repository
 * @since 7.0.0
 */

namespace WPDMPP\License\Repository;

use WPDMPP\License\License;
use WPDMPP\License\LicenseRepositoryInterface;

defined('ABSPATH') || exit;

class DatabaseRepository implements LicenseRepositoryInterface {

    /**
     * Licenses table name
     *
     * @var string
     */
    private string $licensesTable;

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
        $this->licensesTable = $wpdb->prefix . 'ahm_licenses';
        $this->ordersTable = $wpdb->prefix . 'ahm_orders';
    }

    /**
     * @inheritDoc
     */
    public function findById(int $id): ?License {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->licensesTable} WHERE id = %d",
            $id
        ));

        if (!$row) {
            return null;
        }

        return License::fromDatabase($row);
    }

    /**
     * @inheritDoc
     */
    public function findByLicenseNo(string $licenseNo): ?License {
        if (empty($licenseNo)) {
            return null;
        }

        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->licensesTable} WHERE licenseno = %s",
            strtoupper(trim($licenseNo))
        ));

        if (!$row) {
            return null;
        }

        return License::fromDatabase($row);
    }

    /**
     * @inheritDoc
     */
    public function findByOrderAndProduct(string $orderId, int $productId): ?License {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->licensesTable} WHERE oid = %s AND pid = %d",
            $orderId,
            $productId
        ));

        if (!$row) {
            return null;
        }

        return License::fromDatabase($row);
    }

    /**
     * @inheritDoc
     */
    public function findByOrder(string $orderId): array {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->licensesTable} WHERE oid = %s ORDER BY id ASC",
            $orderId
        ));

        $licenses = [];
        foreach ($rows as $row) {
            $licenses[] = License::fromDatabase($row);
        }

        return $licenses;
    }

    /**
     * @inheritDoc
     */
    public function findByProduct(int $productId): array {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->licensesTable} WHERE pid = %d ORDER BY id DESC",
            $productId
        ));

        $licenses = [];
        foreach ($rows as $row) {
            $licenses[] = License::fromDatabase($row);
        }

        return $licenses;
    }

    /**
     * @inheritDoc
     */
    public function findByUser(int $userId): array {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT l.* FROM {$this->licensesTable} l
            INNER JOIN {$this->ordersTable} o ON l.oid = o.order_id
            WHERE o.uid = %d
            ORDER BY l.id DESC",
            $userId
        ));

        $licenses = [];
        foreach ($rows as $row) {
            $licenses[] = License::fromDatabase($row);
        }

        return $licenses;
    }

    /**
     * @inheritDoc
     */
    public function findAll(array $args = []): array {
        $defaults = [
            'search' => '',
            'status' => null,
            'product_id' => 0,
            'order_id' => '',
            'expired_only' => false,
            'active_only' => false,
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if (!empty($args['search'])) {
            $search = '%' . $this->db->esc_like($args['search']) . '%';
            $where[] = '(licenseno LIKE %s OR domain LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        if ($args['status'] !== null) {
            $where[] = 'status = %d';
            $values[] = (int) $args['status'];
        }

        if (!empty($args['product_id'])) {
            $where[] = 'pid = %d';
            $values[] = (int) $args['product_id'];
        }

        if (!empty($args['order_id'])) {
            $where[] = 'oid = %s';
            $values[] = $args['order_id'];
        }

        if ($args['expired_only']) {
            $where[] = 'expire_date > 0 AND expire_date < %d';
            $values[] = time();
        }

        if ($args['active_only']) {
            $where[] = '(expire_date = 0 OR expire_date > %d)';
            $values[] = time();
            $where[] = 'status = %d';
            $values[] = License::STATUS_ACTIVE;
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM {$this->licensesTable} WHERE {$whereClause}";
        if (!empty($values)) {
            $countQuery = $this->db->prepare($countQuery, ...$values);
        }
        $total = (int) $this->db->get_var($countQuery);

        // Validate orderby
        $allowedOrderby = ['id', 'licenseno', 'status', 'pid', 'oid', 'activation_date', 'expire_date', 'domain_limit'];
        $orderby = in_array($args['orderby'], $allowedOrderby) ? $args['orderby'] : 'id';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get results
        $query = "SELECT * FROM {$this->licensesTable} WHERE {$whereClause} ORDER BY {$orderby} {$order} LIMIT %d, %d";
        $values[] = (int) $args['offset'];
        $values[] = (int) $args['limit'];

        $rows = $this->db->get_results($this->db->prepare($query, ...$values));

        $licenses = [];
        foreach ($rows as $row) {
            $licenses[] = License::fromDatabase($row);
        }

        return [
            'licenses' => $licenses,
            'total' => $total,
        ];
    }

    /**
     * @inheritDoc
     */
    public function findByDomain(string $domain): array {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);

        // Search in serialized domain field
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->licensesTable} WHERE domain LIKE %s",
            '%' . $this->db->esc_like($domain) . '%'
        ));

        $licenses = [];
        foreach ($rows as $row) {
            $license = License::fromDatabase($row);
            // Verify domain is actually in the array (not just substring match)
            if ($license->hasDomain($domain)) {
                $licenses[] = $license;
            }
        }

        return $licenses;
    }

    /**
     * @inheritDoc
     */
    public function findExpired(): array {
        $now = time();

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->licensesTable}
            WHERE expire_date > 0 AND expire_date < %d
            ORDER BY expire_date DESC",
            $now
        ));

        $licenses = [];
        foreach ($rows as $row) {
            $licenses[] = License::fromDatabase($row);
        }

        return $licenses;
    }

    /**
     * @inheritDoc
     */
    public function findActive(): array {
        $now = time();

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->licensesTable}
            WHERE status = %d
            AND (expire_date = 0 OR expire_date > %d)
            ORDER BY id DESC",
            License::STATUS_ACTIVE,
            $now
        ));

        $licenses = [];
        foreach ($rows as $row) {
            $licenses[] = License::fromDatabase($row);
        }

        return $licenses;
    }

    /**
     * @inheritDoc
     */
    public function save(License $license): bool {
        $data = $license->toDatabase();

        if ($license->getId() !== null) {
            // Update existing license
            $result = $this->db->update(
                $this->licensesTable,
                $data,
                ['id' => $license->getId()],
                $this->getColumnFormats($data),
                ['%d']
            );

            return $result !== false;
        }

        // Insert new license
        $result = $this->db->insert(
            $this->licensesTable,
            $data,
            $this->getColumnFormats($data)
        );

        if ($result) {
            $license->setId($this->db->insert_id);
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): bool {
        $result = $this->db->delete(
            $this->licensesTable,
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function deleteByLicenseNo(string $licenseNo): bool {
        $result = $this->db->delete(
            $this->licensesTable,
            ['licenseno' => strtoupper(trim($licenseNo))],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function licenseNoExists(string $licenseNo, ?int $excludeId = null): bool {
        $licenseNo = strtoupper(trim($licenseNo));

        if ($excludeId !== null) {
            $count = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->licensesTable} WHERE licenseno = %s AND id != %d",
                $licenseNo,
                $excludeId
            ));
        } else {
            $count = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->licensesTable} WHERE licenseno = %s",
                $licenseNo
            ));
        }

        return (int) $count > 0;
    }

    /**
     * @inheritDoc
     */
    public function updateDomains(int $id, array $domains): bool {
        $domains = array_values(array_unique(array_filter($domains)));
        $serialized = !empty($domains) ? serialize($domains) : '';

        $result = $this->db->update(
            $this->licensesTable,
            ['domain' => $serialized],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function updateStatus(int $id, int $status): bool {
        $result = $this->db->update(
            $this->licensesTable,
            ['status' => $status],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function count(array $args = []): int {
        $where = ['1=1'];
        $values = [];

        if (isset($args['status'])) {
            $where[] = 'status = %d';
            $values[] = (int) $args['status'];
        }

        if (!empty($args['product_id'])) {
            $where[] = 'pid = %d';
            $values[] = (int) $args['product_id'];
        }

        if (!empty($args['active_only'])) {
            $now = time();
            $where[] = '(expire_date = 0 OR expire_date > %d)';
            $values[] = $now;
            $where[] = 'status = %d';
            $values[] = License::STATUS_ACTIVE;
        }

        if (!empty($args['expired_only'])) {
            $where[] = 'expire_date > 0 AND expire_date < %d';
            $values[] = time();
        }

        $whereClause = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$this->licensesTable} WHERE {$whereClause}";

        if (!empty($values)) {
            $query = $this->db->prepare($query, ...$values);
        }

        return (int) $this->db->get_var($query);
    }

    /**
     * @inheritDoc
     */
    public function bulkDelete(array $ids): int {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $result = $this->db->query($this->db->prepare(
            "DELETE FROM {$this->licensesTable} WHERE id IN ({$placeholders})",
            ...$ids
        ));

        return $result !== false ? $result : 0;
    }

    /**
     * @inheritDoc
     */
    public function clearDomains(int $id): bool {
        $result = $this->db->update(
            $this->licensesTable,
            ['domain' => ''],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Generate a unique license key
     *
     * @param int $maxAttempts Maximum generation attempts
     * @return string
     */
    public function generateUniqueLicenseNo(int $maxAttempts = 10): string {
        $attempt = 0;

        do {
            $licenseNo = License::generateKey();
            $attempt++;
        } while ($this->licenseNoExists($licenseNo) && $attempt < $maxAttempts);

        return $licenseNo;
    }

    /**
     * Get column formats for database operations
     *
     * @param array $data Data array
     * @return array
     */
    private function getColumnFormats(array $data): array {
        $formats = [];

        $intColumns = ['status', 'pid', 'activation_date', 'expire_date', 'expire_period', 'domain_limit'];

        foreach (array_keys($data) as $key) {
            if (in_array($key, $intColumns)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * Find licenses expiring soon
     *
     * @param int $days Days until expiration
     * @return License[]
     */
    public function findExpiringSoon(int $days = 30): array {
        $now = time();
        $threshold = $now + ($days * 86400);

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->licensesTable}
            WHERE expire_date > %d AND expire_date <= %d
            ORDER BY expire_date ASC",
            $now,
            $threshold
        ));

        $licenses = [];
        foreach ($rows as $row) {
            $licenses[] = License::fromDatabase($row);
        }

        return $licenses;
    }

    /**
     * Get license statistics
     *
     * @return array
     */
    public function getStatistics(): array {
        $now = time();

        $stats = $this->db->get_row($this->db->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = %d THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = %d THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN expire_date > 0 AND expire_date < %d THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN expire_date = 0 OR expire_date > %d THEN 1 ELSE 0 END) as valid
            FROM {$this->licensesTable}",
            License::STATUS_ACTIVE,
            License::STATUS_INACTIVE,
            $now,
            $now
        ), ARRAY_A);

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'active' => (int) ($stats['active'] ?? 0),
            'inactive' => (int) ($stats['inactive'] ?? 0),
            'expired' => (int) ($stats['expired'] ?? 0),
            'valid' => (int) ($stats['valid'] ?? 0),
        ];
    }
}
