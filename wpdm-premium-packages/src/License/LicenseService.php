<?php
/**
 * License Service
 *
 * Main orchestration class for license operations.
 * Provides a facade for all license-related business logic.
 *
 * @package WPDMPP\License
 * @since 7.0.0
 */

namespace WPDMPP\License;

use WPDMPP\License\Repository\DatabaseRepository;
use WPDMPP\Order\OrderService;

defined('ABSPATH') || exit;

class LicenseService {

    /**
     * Singleton instance
     *
     * @var LicenseService|null
     */
    private static ?LicenseService $instance = null;

    /**
     * License repository
     *
     * @var LicenseRepositoryInterface
     */
    private LicenseRepositoryInterface $repository;

    /**
     * Get singleton instance
     *
     * @return LicenseService
     */
    public static function getInstance(): LicenseService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param LicenseRepositoryInterface|null $repository
     */
    public function __construct(?LicenseRepositoryInterface $repository = null) {
        $this->repository = $repository ?? new DatabaseRepository();
    }

    /**
     * Set repository (for testing)
     *
     * @param LicenseRepositoryInterface $repository
     * @return void
     */
    public function setRepository(LicenseRepositoryInterface $repository): void {
        $this->repository = $repository;
    }

    /**
     * Get repository
     *
     * @return LicenseRepositoryInterface
     */
    public function getRepository(): LicenseRepositoryInterface {
        return $this->repository;
    }

    // =========================================================================
    // LICENSE RETRIEVAL
    // =========================================================================

    /**
     * Find license by ID
     *
     * @param int $id License ID
     * @return License|null
     */
    public function findById(int $id): ?License {
        return $this->repository->findById($id);
    }

    /**
     * Find license by license key
     *
     * @param string $licenseNo License key
     * @return License|null
     */
    public function findByLicenseNo(string $licenseNo): ?License {
        return $this->repository->findByLicenseNo($licenseNo);
    }

    /**
     * Find license by order and product
     *
     * @param string $orderId   Order ID
     * @param int    $productId Product ID
     * @return License|null
     */
    public function findByOrderAndProduct(string $orderId, int $productId): ?License {
        return $this->repository->findByOrderAndProduct($orderId, $productId);
    }

    /**
     * Get all licenses for an order
     *
     * @param string $orderId Order ID
     * @return License[]
     */
    public function getLicensesForOrder(string $orderId): array {
        return $this->repository->findByOrder($orderId);
    }

    /**
     * Get all licenses for a product
     *
     * @param int $productId Product ID
     * @return License[]
     */
    public function getLicensesForProduct(int $productId): array {
        return $this->repository->findByProduct($productId);
    }

    /**
     * Get all licenses for current user
     *
     * @param int|null $userId User ID (defaults to current user)
     * @return License[]
     */
    public function getLicensesForUser(?int $userId = null): array {
        $userId = $userId ?? get_current_user_id();
        if (!$userId) {
            return [];
        }
        return $this->repository->findByUser($userId);
    }

    /**
     * Get all licenses with filtering
     *
     * @param array $args Query arguments
     * @return array ['licenses' => License[], 'total' => int]
     */
    public function getLicenses(array $args = []): array {
        return $this->repository->findAll($args);
    }

    // =========================================================================
    // LICENSE VALIDATION
    // =========================================================================

    /**
     * Validate a license key for a domain
     *
     * @param string $licenseKey License key
     * @param string $domain     Domain to validate
     * @param bool   $registerDomain Whether to register the domain if valid
     * @return array
     */
    public function validateLicense(string $licenseKey, string $domain, bool $registerDomain = true): array {
        $license = $this->repository->findByLicenseNo($licenseKey);

        if (!$license) {
            return [
                'status' => License::INVALID,
                'error' => License::ERROR_NOT_FOUND,
                'message' => __('License key not found.', 'wpdm-premium-packages'),
                'download_url' => '',
            ];
        }

        // Check order status
        if (!$license->getOrderId()) {
            return [
                'status' => License::INVALID,
                'error' => License::ERROR_ORDER_ISSUE,
                'message' => __('No order associated with this license.', 'wpdm-premium-packages'),
                'download_url' => '',
            ];
        }

        $orderResult = $this->validateOrderStatus($license);
        if ($orderResult !== null) {
            return $orderResult;
        }

        // Validate license for domain
        $validation = $license->validate($domain);

        if ($validation['status'] === License::INVALID) {
            return [
                'status' => License::INVALID,
                'error' => $validation['error'],
                'message' => $validation['message'] ?? __('License validation failed.', 'wpdm-premium-packages'),
                'download_url' => '',
            ];
        }

        // If expired, return expired status
        if ($validation['status'] === License::EXPIRED) {
            return [
                'status' => License::EXPIRED,
                'error' => null,
                'message' => __('License has expired.', 'wpdm-premium-packages'),
                'expire_date' => $license->getExpireDate(),
                'download_url' => '',
            ];
        }

        // Register domain if needed and allowed
        $domainRegistered = false;
        if ($registerDomain && !$license->hasDomain($domain)) {
            if ($license->addDomain($domain)) {
                // Update activation date if first activation
                if ($license->getActivationDate() === 0) {
                    $license->setActivationDate(time());
                }
                $this->repository->save($license);
                $domainRegistered = true;

                /**
                 * Fires when a domain is registered to a license
                 *
                 * @param License $license The license
                 * @param string  $domain  The registered domain
                 */
                do_action('wpdmpp_license_domain_registered', $license, $domain);
            }
        }

        // Get order for additional info
        $order = $this->getOrderForLicense($license);
        $downloadUrl = $this->getDownloadUrl($license, $domain, $order);

        return [
            'status' => License::VALID,
            'error' => null,
            'expire_date' => $license->getExpireDate(),
            'activation_date' => $license->getActivationDate(),
            'order_status' => $order ? (($order instanceof \WPDMPP\Order\Order) ? $order->getOrderStatus() : ($order->order_status ?? '')) : '',
            'order_id' => $license->getOrderId(),
            'auto_renew' => $order ? (($order instanceof \WPDMPP\Order\Order) ? (int) $order->getAutoRenew() : (int) ($order->auto_renew ?? 0)) : 0,
            'download_url' => $downloadUrl,
            'domain_registered' => $domainRegistered,
            'domains' => $license->getDomains(),
            'domain_limit' => $license->getDomainLimit(),
            'remaining_slots' => $license->getRemainingDomainSlots(),
        ];
    }

    /**
     * Validate order status for license
     *
     * @param License $license
     * @return array|null Null if order is valid
     */
    private function validateOrderStatus(License $license): ?array {
        $order = $this->getOrderForLicense($license);

        $orderId = ($order instanceof \WPDMPP\Order\Order) ? $order->getOrderId() : ($order->order_id ?? '');
        $orderStatus = ($order instanceof \WPDMPP\Order\Order) ? $order->getOrderStatus() : ($order->order_status ?? '');
        $expireDate = ($order instanceof \WPDMPP\Order\Order) ? $order->getExpireDate() : ($order->expire_date ?? 0);

        if (!$order || ($orderId !== '' && !in_array($orderStatus, ['Completed', 'Expired', 'Gifted']))) {
            return [
                'status' => License::INVALID,
                'error' => License::ERROR_ORDER_ISSUE,
                'message' => __('Order status does not allow license validation.', 'wpdm-premium-packages'),
                'expire_date' => $expireDate,
                'download_url' => '',
            ];
        }

        // Check if expired orders are allowed to keep license valid
        $allowExpired = get_wpdmpp_option('license_key_validity', 0, 'int');
        if ($orderStatus === 'Expired' && !$allowExpired) {
            return [
                'status' => License::INVALID,
                'error' => License::ERROR_ORDER_EXPIRED,
                'message' => __('The order associated with this license has expired.', 'wpdm-premium-packages'),
                'download_url' => '',
            ];
        }

        return null;
    }

    /**
     * Get order for a license
     *
     * @param License $license
     * @return object|null
     */
    private function getOrderForLicense(License $license): ?\WPDMPP\Order\Order {
        if (class_exists('\WPDMPP\Order\OrderService')) {
            return OrderService::instance()->getOrder($license->getOrderId());
        }
        return null;
    }

    /**
     * Get download URL for validated license
     *
     * @param License     $license
     * @param string      $domain
     * @param object|null $order
     * @return string
     */
    private function getDownloadUrl(License $license, string $domain, $order): string {
        if (!$order) {
            return '';
        }
        $orderStatus = ($order instanceof \WPDMPP\Order\Order) ? $order->getOrderStatus() : ($order->order_status ?? '');
        if ($orderStatus !== 'Completed') {
            return '';
        }

        $files = get_post_meta($license->getProductId(), '__wpdm_files', true);
        if (!is_array($files) || empty($files)) {
            return '';
        }

        // Get first file download URL
        if (class_exists('\WPDMPP\WPDMPremiumPackage')) {
            return \WPDMPP\WPDMPremiumPackage::customerDownloadURL(
                $license->getProductId(),
                $license->getOrderId(),
                ['domain' => $domain]
            ) . '&ind=0';
        }

        return '';
    }

    // =========================================================================
    // DOMAIN MANAGEMENT
    // =========================================================================

    /**
     * Add a domain to a license
     *
     * @param string $licenseNo License key
     * @param string $domain    Domain to add
     * @return array ['success' => bool, 'message' => string]
     */
    public function addDomain(string $licenseNo, string $domain): array {
        $license = $this->repository->findByLicenseNo($licenseNo);

        if (!$license) {
            return [
                'success' => false,
                'message' => __('License not found.', 'wpdm-premium-packages'),
            ];
        }

        if ($license->hasDomain($domain)) {
            return [
                'success' => true,
                'message' => __('Domain already registered.', 'wpdm-premium-packages'),
            ];
        }

        if ($license->isDomainLimitReached()) {
            return [
                'success' => false,
                'message' => __('Domain limit reached.', 'wpdm-premium-packages'),
            ];
        }

        $license->addDomain($domain);

        // Set activation date if first domain
        if ($license->getActivationDate() === 0) {
            $license->setActivationDate(time());
        }

        $saved = $this->repository->save($license);

        if ($saved) {
            do_action('wpdmpp_license_domain_registered', $license, $domain);
        }

        return [
            'success' => $saved,
            'message' => $saved
                ? __('Domain added successfully.', 'wpdm-premium-packages')
                : __('Failed to add domain.', 'wpdm-premium-packages'),
            'domains' => $license->getDomains(),
        ];
    }

    /**
     * Remove a domain from a license
     *
     * @param string   $licenseNo License key
     * @param string   $domain    Domain to remove
     * @param int|null $userId    User requesting removal (for ownership check)
     * @return array ['success' => bool, 'message' => string]
     */
    public function removeDomain(string $licenseNo, string $domain, ?int $userId = null): array {
        $license = $this->repository->findByLicenseNo($licenseNo);

        if (!$license) {
            return [
                'success' => false,
                'message' => __('License not found.', 'wpdm-premium-packages'),
            ];
        }

        // Check ownership if user ID provided
        if ($userId !== null && !current_user_can(WPDMPP_ADMIN_CAP)) {
            $order = $this->getOrderForLicense($license);
            if (!$order || (int) $order->uid !== $userId) {
                return [
                    'success' => false,
                    'message' => __('Unauthorized access.', 'wpdm-premium-packages'),
                ];
            }
        }

        if (!$license->hasDomain($domain)) {
            return [
                'success' => false,
                'message' => __('Domain not found on this license.', 'wpdm-premium-packages'),
            ];
        }

        $license->removeDomain($domain);
        $saved = $this->repository->save($license);

        if ($saved) {
            /**
             * Fires when a domain is removed from a license
             *
             * @param License $license The license
             * @param string  $domain  The removed domain
             */
            do_action('wpdmpp_license_domain_removed', $license, $domain);
        }

        return [
            'success' => $saved,
            'message' => $saved
                ? __('Domain removed successfully.', 'wpdm-premium-packages')
                : __('Failed to remove domain.', 'wpdm-premium-packages'),
            'domains' => $license->getDomains(),
        ];
    }

    /**
     * Clear all domains from a license (admin only)
     *
     * @param int $licenseId License ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function clearDomains(int $licenseId): array {
        $license = $this->repository->findById($licenseId);

        if (!$license) {
            return [
                'success' => false,
                'message' => __('License not found.', 'wpdm-premium-packages'),
            ];
        }

        $license->clearDomains();
        $saved = $this->repository->save($license);

        if ($saved) {
            do_action('wpdmpp_license_domains_cleared', $license);
        }

        return [
            'success' => $saved,
            'message' => $saved
                ? __('All domains cleared.', 'wpdm-premium-packages')
                : __('Failed to clear domains.', 'wpdm-premium-packages'),
        ];
    }

    // =========================================================================
    // LICENSE GENERATION
    // =========================================================================

    /**
     * Generate or get license for an order item
     *
     * @param string $orderId   Order ID
     * @param int    $productId Product ID
     * @param int    $domainLimit Domain limit (from order item)
     * @return array ['success' => bool, 'license' => License|null, 'message' => string]
     */
    public function getOrCreateLicense(string $orderId, int $productId, int $domainLimit = 1): array {
        // Check if license already exists
        $existing = $this->repository->findByOrderAndProduct($orderId, $productId);
        if ($existing) {
            return [
                'success' => true,
                'license' => $existing,
                'message' => __('License retrieved.', 'wpdm-premium-packages'),
                'created' => false,
            ];
        }

        // Generate new license
        $licenseNo = $this->repository->generateUniqueLicenseNo();

        /**
         * Filter the generated license key
         *
         * @param string $licenseNo  Generated license key
         * @param string $orderId    Order ID
         * @param int    $productId  Product ID
         * @param int    $domainLimit Domain limit
         */
        $licenseNo = apply_filters('wpdmpp_generate_license_key', $licenseNo, $orderId, $productId, $domainLimit);

        $license = License::create([
            'license_no' => $licenseNo,
            'order_id' => $orderId,
            'product_id' => $productId,
            'domain_limit' => $domainLimit,
            'status' => License::STATUS_ACTIVE,
        ]);

        $saved = $this->repository->save($license);

        if ($saved) {
            /**
             * Fires when a license is created
             *
             * @param License $license The created license
             */
            do_action('wpdmpp_license_created', $license);
        }

        return [
            'success' => $saved,
            'license' => $saved ? $license : null,
            'message' => $saved
                ? __('License created.', 'wpdm-premium-packages')
                : __('Failed to create license.', 'wpdm-premium-packages'),
            'created' => $saved,
        ];
    }

    /**
     * Generate a unique license key
     *
     * @return string
     */
    public function generateLicenseKey(): string {
        return $this->repository->generateUniqueLicenseNo();
    }

    // =========================================================================
    // ADMIN METHODS
    // =========================================================================

    /**
     * Create a new license (admin)
     *
     * @param array $data License data
     * @return array ['success' => bool, 'license' => License|null, 'errors' => array]
     */
    public function createLicense(array $data): array {
        // Generate key if not provided
        if (empty($data['license_no'])) {
            $data['license_no'] = $this->repository->generateUniqueLicenseNo();
        } else {
            // Check for duplicate
            if ($this->repository->licenseNoExists($data['license_no'])) {
                return [
                    'success' => false,
                    'license' => null,
                    'errors' => ['license_no' => __('License key already exists.', 'wpdm-premium-packages')],
                ];
            }
        }

        $license = License::create($data);
        $saved = $this->repository->save($license);

        if ($saved) {
            do_action('wpdmpp_license_created', $license);
        }

        return [
            'success' => $saved,
            'license' => $saved ? $license : null,
            'errors' => $saved ? [] : ['general' => __('Failed to create license.', 'wpdm-premium-packages')],
        ];
    }

    /**
     * Update an existing license (admin)
     *
     * @param int   $id   License ID
     * @param array $data License data
     * @return array ['success' => bool, 'license' => License|null, 'errors' => array]
     */
    public function updateLicense(int $id, array $data): array {
        $license = $this->repository->findById($id);

        if (!$license) {
            return [
                'success' => false,
                'license' => null,
                'errors' => ['general' => __('License not found.', 'wpdm-premium-packages')],
            ];
        }

        // Check for duplicate key if changing
        if (isset($data['license_no'])) {
            $newKey = strtoupper(trim($data['license_no']));
            if ($newKey !== $license->getLicenseNo() && $this->repository->licenseNoExists($newKey, $id)) {
                return [
                    'success' => false,
                    'license' => null,
                    'errors' => ['license_no' => __('License key already exists.', 'wpdm-premium-packages')],
                ];
            }
            $license->setLicenseNo($newKey);
        }

        // Update other fields
        $this->updateLicenseFromData($license, $data);

        $saved = $this->repository->save($license);

        if ($saved) {
            /**
             * Fires when a license is updated
             *
             * @param License $license The updated license
             */
            do_action('wpdmpp_license_updated', $license);
        }

        return [
            'success' => $saved,
            'license' => $saved ? $license : null,
            'errors' => $saved ? [] : ['general' => __('Failed to update license.', 'wpdm-premium-packages')],
        ];
    }

    /**
     * Delete a license (admin)
     *
     * @param int $id License ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteLicense(int $id): array {
        $license = $this->repository->findById($id);

        if (!$license) {
            return [
                'success' => false,
                'message' => __('License not found.', 'wpdm-premium-packages'),
            ];
        }

        $deleted = $this->repository->delete($id);

        if ($deleted) {
            /**
             * Fires when a license is deleted
             *
             * @param int     $id      The license ID
             * @param License $license The deleted license
             */
            do_action('wpdmpp_license_deleted', $id, $license);
        }

        return [
            'success' => $deleted,
            'message' => $deleted
                ? __('License deleted successfully.', 'wpdm-premium-packages')
                : __('Failed to delete license.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Bulk delete licenses (admin)
     *
     * @param array $ids License IDs
     * @return array ['success' => bool, 'deleted' => int, 'message' => string]
     */
    public function bulkDeleteLicenses(array $ids): array {
        if (empty($ids)) {
            return [
                'success' => false,
                'deleted' => 0,
                'message' => __('No licenses selected.', 'wpdm-premium-packages'),
            ];
        }

        $deleted = $this->repository->bulkDelete($ids);

        do_action('wpdmpp_licenses_bulk_deleted', $ids, $deleted);

        return [
            'success' => true,
            'deleted' => $deleted,
            'message' => sprintf(
                _n('%d license deleted.', '%d licenses deleted.', $deleted, 'wpdm-premium-packages'),
                $deleted
            ),
        ];
    }

    /**
     * Activate a license
     *
     * @param int $id License ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function activateLicense(int $id): array {
        $result = $this->repository->updateStatus($id, License::STATUS_ACTIVE);

        if ($result) {
            do_action('wpdmpp_license_activated', $id);
        }

        return [
            'success' => $result,
            'message' => $result
                ? __('License activated.', 'wpdm-premium-packages')
                : __('Failed to activate license.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Deactivate a license
     *
     * @param int $id License ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function deactivateLicense(int $id): array {
        $result = $this->repository->updateStatus($id, License::STATUS_INACTIVE);

        if ($result) {
            do_action('wpdmpp_license_deactivated', $id);
        }

        return [
            'success' => $result,
            'message' => $result
                ? __('License deactivated.', 'wpdm-premium-packages')
                : __('Failed to deactivate license.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Get license statistics
     *
     * @return array
     */
    public function getStatistics(): array {
        return $this->repository->getStatistics();
    }

    /**
     * Get total license count
     *
     * @param array $args Filter arguments
     * @return int
     */
    public function getCount(array $args = []): int {
        return $this->repository->count($args);
    }

    /**
     * Get expired licenses
     *
     * @return License[]
     */
    public function getExpiredLicenses(): array {
        return $this->repository->findExpired();
    }

    /**
     * Get licenses expiring soon
     *
     * @param int $days Days until expiration
     * @return License[]
     */
    public function getExpiringSoonLicenses(int $days = 30): array {
        return $this->repository->findExpiringSoon($days);
    }

    /**
     * Update license properties from data array
     *
     * @param License $license
     * @param array   $data
     * @return void
     */
    private function updateLicenseFromData(License $license, array $data): void {
        if (isset($data['domains'])) {
            $domains = $data['domains'];
            if (is_string($domains)) {
                $domains = array_filter(array_map('trim', explode("\n", str_replace("\r", '', $domains))));
            }
            $license->setDomains((array) $domains);
        }

        if (isset($data['status'])) {
            $license->setStatus((int) $data['status']);
        }

        if (isset($data['order_id'])) {
            $license->setOrderId($data['order_id']);
        }

        if (isset($data['product_id'])) {
            $license->setProductId((int) $data['product_id']);
        }

        if (isset($data['domain_limit'])) {
            $license->setDomainLimit((int) $data['domain_limit']);
        }

        if (isset($data['activation_date'])) {
            if (is_string($data['activation_date']) && !empty($data['activation_date'])) {
                $license->setActivationDate(strtotime($data['activation_date']));
            } else {
                $license->setActivationDate((int) $data['activation_date']);
            }
        }

        if (isset($data['expire_date'])) {
            if (is_string($data['expire_date']) && !empty($data['expire_date'])) {
                $license->setExpireDate(strtotime($data['expire_date']));
            } elseif (is_int($data['expire_date'])) {
                $license->setExpireDate($data['expire_date']);
            } else {
                $license->setExpireDate(0);
            }
        }

        if (isset($data['expire_period'])) {
            $license->setExpirePeriod((int) $data['expire_period']);
        }
    }
}
