<?php
/**
 * Order Repository Interface
 *
 * Defines the contract for order storage operations.
 *
 * @package WPDMPP\Order
 * @since 7.0.0
 */

namespace WPDMPP\Order;

defined('ABSPATH') || exit;

interface OrderRepositoryInterface {

    /**
     * Find an order by ID
     *
     * @param string $orderId Order ID (e.g., WPDMPP65A4B3C2)
     * @return Order|null
     */
    public function find(string $orderId): ?Order;

    /**
     * Find an order by transaction ID
     *
     * @param string $transactionId Transaction ID from payment gateway
     * @return Order|null
     */
    public function findByTransactionId(string $transactionId): ?Order;

    /**
     * Find an order by database ID
     *
     * @param int $id Database primary key
     * @return Order|null
     */
    public function findById(int $id): ?Order;

    /**
     * Get all orders for a user
     *
     * @param int  $userId        User ID
     * @param bool $completedOnly Only return completed/expired orders
     * @return Order[]
     */
    public function findByUser(int $userId, bool $completedOnly = false): array;

    /**
     * Get orders with pagination
     *
     * @param array $args Query arguments
     * @return array ['orders' => Order[], 'total' => int]
     */
    public function findAll(array $args = []): array;

    /**
     * Save an order (insert or update)
     *
     * @param Order $order Order to save
     * @return bool
     */
    public function save(Order $order): bool;

    /**
     * Update order data
     *
     * @param array  $data    Data to update
     * @param string $orderId Order ID
     * @return bool
     */
    public function update(array $data, string $orderId): bool;

    /**
     * Delete an order
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function delete(string $orderId): bool;

    /**
     * Check if order exists
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function exists(string $orderId): bool;

    /**
     * Get order items
     *
     * @param string $orderId Order ID
     * @return OrderItem[]
     */
    public function getItems(string $orderId): array;

    /**
     * Save order items
     *
     * @param string      $orderId Order ID
     * @param OrderItem[] $items   Order items
     * @return bool
     */
    public function saveItems(string $orderId, array $items): bool;

    /**
     * Delete order items
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function deleteItems(string $orderId): bool;

    /**
     * Get order meta
     *
     * @param string $orderId Order ID
     * @param string $key     Meta key (empty for all)
     * @return mixed
     */
    public function getMeta(string $orderId, string $key = '');

    /**
     * Update order meta
     *
     * @param string $orderId Order ID
     * @param string $key     Meta key
     * @param mixed  $value   Meta value
     * @return bool
     */
    public function updateMeta(string $orderId, string $key, $value): bool;

    /**
     * Delete order meta
     *
     * @param string $orderId Order ID
     * @param string $key     Meta key
     * @return bool
     */
    public function deleteMeta(string $orderId, string $key): bool;

    /**
     * Get order notes
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getNotes(string $orderId): array;

    /**
     * Add order note
     *
     * @param string $orderId Order ID
     * @param string $note    Note content
     * @param string $author  Note author
     * @return bool
     */
    public function addNote(string $orderId, string $note, string $author = ''): bool;

    /**
     * Get renewal history
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getRenewals(string $orderId): array;

    /**
     * Add renewal record
     *
     * @param string $orderId        Order ID
     * @param float  $total          Renewal total
     * @param string $subscriptionId Subscription ID
     * @param string $invoice        Invoice reference
     * @return bool
     */
    public function addRenewal(string $orderId, float $total, string $subscriptionId = '', string $invoice = '', int $date = 0): bool;

    /**
     * Get total orders count
     *
     * @param array $args Filter arguments
     * @return int
     */
    public function count(array $args = []): int;

    /**
     * Get purchased items for a user
     *
     * @param int $userId User ID
     * @return array
     */
    public function getPurchasedItems(int $userId): array;

    /**
     * Check if user has purchased a product
     *
     * @param int $productId Product ID
     * @param int $userId    User ID
     * @return bool
     */
    public function hasPurchased(int $productId, int $userId): bool;
}
