<?php
/**
 * License Entity Class
 *
 * Represents a software license with validation and domain management.
 *
 * @package WPDMPP\License
 * @since 7.0.0
 */

namespace WPDMPP\License;

defined('ABSPATH') || exit;

class License {

    /**
     * License statuses
     */
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    /**
     * Validation result codes
     */
    public const VALID = 'VALID';
    public const INVALID = 'INVALID';
    public const EXPIRED = 'EXPIRED';

    /**
     * Validation error codes
     */
    public const ERROR_NOT_FOUND = 'LICENSE_KEY_NOT_FOUND';
    public const ERROR_ORDER_ISSUE = 'ORDER_ISSUE';
    public const ERROR_ORDER_EXPIRED = 'ORDER_EXPIRED';
    public const ERROR_USAGE_LIMIT = 'USAGE_LIMIT_REACHED';
    public const ERROR_INACTIVE = 'NOT_ACTIVE';
    public const ERROR_INVALID_DOMAIN = 'INVALID_DOMAIN';

    /**
     * License ID
     *
     * @var int|null
     */
    private ?int $id = null;

    /**
     * License key/number
     *
     * @var string
     */
    private string $licenseNo = '';

    /**
     * Registered domains
     *
     * @var array
     */
    private array $domains = [];

    /**
     * License status
     *
     * @var int
     */
    private int $status = self::STATUS_ACTIVE;

    /**
     * Order ID
     *
     * @var string
     */
    private string $orderId = '';

    /**
     * Product ID
     *
     * @var int
     */
    private int $productId = 0;

    /**
     * Activation date (Unix timestamp)
     *
     * @var int
     */
    private int $activationDate = 0;

    /**
     * Expiration date (Unix timestamp, 0 = never)
     *
     * @var int
     */
    private int $expireDate = 0;

    /**
     * Expiration period in days
     *
     * @var int
     */
    private int $expirePeriod = 0;

    /**
     * Domain limit (0 = unlimited)
     *
     * @var int
     */
    private int $domainLimit = 1;

    // =========================================================================
    // GETTERS
    // =========================================================================

    public function getId(): ?int {
        return $this->id;
    }

    public function getLicenseNo(): string {
        return $this->licenseNo;
    }

    public function getDomains(): array {
        return $this->domains;
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function getOrderId(): string {
        return $this->orderId;
    }

    public function getProductId(): int {
        return $this->productId;
    }

    public function getActivationDate(): int {
        return $this->activationDate;
    }

    public function getExpireDate(): int {
        return $this->expireDate;
    }

    public function getExpirePeriod(): int {
        return $this->expirePeriod;
    }

    public function getDomainLimit(): int {
        return $this->domainLimit;
    }

    // =========================================================================
    // SETTERS
    // =========================================================================

    public function setId(?int $id): self {
        $this->id = $id;
        return $this;
    }

    public function setLicenseNo(string $licenseNo): self {
        $this->licenseNo = strtoupper(trim($licenseNo));
        return $this;
    }

    public function setDomains(array $domains): self {
        $this->domains = array_values(array_unique(array_filter($domains)));
        return $this;
    }

    public function setStatus(int $status): self {
        $this->status = $status;
        return $this;
    }

    public function setOrderId(string $orderId): self {
        $this->orderId = $orderId;
        return $this;
    }

    public function setProductId(int $productId): self {
        $this->productId = $productId;
        return $this;
    }

    public function setActivationDate(int $activationDate): self {
        $this->activationDate = $activationDate;
        return $this;
    }

    public function setExpireDate(int $expireDate): self {
        $this->expireDate = $expireDate;
        return $this;
    }

    public function setExpirePeriod(int $expirePeriod): self {
        $this->expirePeriod = $expirePeriod;
        return $this;
    }

    public function setDomainLimit(int $domainLimit): self {
        $this->domainLimit = max(0, $domainLimit);
        return $this;
    }

    // =========================================================================
    // STATUS CHECKS
    // =========================================================================

    /**
     * Check if license is active
     *
     * @return bool
     */
    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if license is expired
     *
     * @return bool
     */
    public function isExpired(): bool {
        if ($this->expireDate === 0) {
            return false; // Never expires
        }
        return $this->expireDate < time();
    }

    /**
     * Check if license has unlimited domains
     *
     * @return bool
     */
    public function hasUnlimitedDomains(): bool {
        return $this->domainLimit === 0;
    }

    /**
     * Check if domain limit is reached
     *
     * @return bool
     */
    public function isDomainLimitReached(): bool {
        if ($this->hasUnlimitedDomains()) {
            return false;
        }
        return count($this->domains) >= $this->domainLimit;
    }

    /**
     * Get remaining domain slots
     *
     * @return int|null Null if unlimited
     */
    public function getRemainingDomainSlots(): ?int {
        if ($this->hasUnlimitedDomains()) {
            return null;
        }
        return max(0, $this->domainLimit - count($this->domains));
    }

    /**
     * Get registered domain count
     *
     * @return int
     */
    public function getDomainCount(): int {
        return count($this->domains);
    }

    // =========================================================================
    // DOMAIN MANAGEMENT
    // =========================================================================

    /**
     * Check if a domain is registered
     *
     * @param string $domain
     * @return bool
     */
    public function hasDomain(string $domain): bool {
        $domain = $this->normalizeDomain($domain);
        return in_array($domain, $this->domains, true);
    }

    /**
     * Add a domain to the license
     *
     * @param string $domain
     * @return bool True if added, false if limit reached or already exists
     */
    public function addDomain(string $domain): bool {
        $domain = $this->normalizeDomain($domain);

        if (empty($domain)) {
            return false;
        }

        // Already registered
        if ($this->hasDomain($domain)) {
            return true;
        }

        // Check limit
        if ($this->isDomainLimitReached()) {
            return false;
        }

        $this->domains[] = $domain;
        return true;
    }

    /**
     * Remove a domain from the license
     *
     * @param string $domain
     * @return bool
     */
    public function removeDomain(string $domain): bool {
        $domain = $this->normalizeDomain($domain);
        $index = array_search($domain, $this->domains, true);

        if ($index === false) {
            return false;
        }

        unset($this->domains[$index]);
        $this->domains = array_values($this->domains); // Re-index
        return true;
    }

    /**
     * Clear all registered domains
     *
     * @return void
     */
    public function clearDomains(): void {
        $this->domains = [];
    }

    /**
     * Normalize domain for comparison
     *
     * @param string $domain
     * @return string
     */
    private function normalizeDomain(string $domain): string {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);
        $domain = rtrim($domain, '/');
        return $domain;
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /**
     * Validate license for a domain
     *
     * @param string $domain Domain to validate
     * @return array ['status' => string, 'error' => string|null]
     */
    public function validate(string $domain = ''): array {
        // Check if active
        if (!$this->isActive()) {
            return [
                'status' => self::INVALID,
                'error' => self::ERROR_INACTIVE,
            ];
        }

        // Check expiration
        if ($this->isExpired()) {
            return [
                'status' => self::EXPIRED,
                'error' => null,
            ];
        }

        // If domain provided, check domain registration
        if (!empty($domain)) {
            $domain = $this->normalizeDomain($domain);

            // Already registered - valid
            if ($this->hasDomain($domain)) {
                return [
                    'status' => self::VALID,
                    'error' => null,
                ];
            }

            // Can register new domain
            if (!$this->isDomainLimitReached()) {
                return [
                    'status' => self::VALID,
                    'error' => null,
                    'can_register' => true,
                ];
            }

            // Domain limit reached
            return [
                'status' => self::INVALID,
                'error' => self::ERROR_USAGE_LIMIT,
            ];
        }

        return [
            'status' => self::VALID,
            'error' => null,
        ];
    }

    // =========================================================================
    // FACTORY METHODS
    // =========================================================================

    /**
     * Create License from database row
     *
     * @param object $row Database row
     * @return self
     */
    public static function fromDatabase($row): self {
        $license = new self();

        $license->id = isset($row->id) ? (int) $row->id : null;
        $license->licenseNo = $row->licenseno ?? '';
        $license->status = isset($row->status) ? (int) $row->status : self::STATUS_ACTIVE;
        $license->orderId = $row->oid ?? '';
        $license->productId = isset($row->pid) ? (int) $row->pid : 0;
        $license->activationDate = isset($row->activation_date) ? (int) $row->activation_date : 0;
        $license->expireDate = isset($row->expire_date) ? (int) $row->expire_date : 0;
        $license->expirePeriod = isset($row->expire_period) ? (int) $row->expire_period : 0;
        $license->domainLimit = isset($row->domain_limit) ? (int) $row->domain_limit : 1;

        // Parse domains
        $domains = $row->domain ?? '';
        if (!empty($domains)) {
            $domains = maybe_unserialize($domains);
            if (is_array($domains)) {
                $license->domains = array_values(array_filter($domains));
            } elseif (is_string($domains) && !empty($domains)) {
                $license->domains = [$domains];
            }
        }

        return $license;
    }

    /**
     * Create a new License
     *
     * @param array $data License data
     * @return self
     */
    public static function create(array $data): self {
        $license = new self();

        if (isset($data['license_no'])) {
            $license->setLicenseNo($data['license_no']);
        } else {
            $license->setLicenseNo(self::generateKey());
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

        if (isset($data['domains'])) {
            $license->setDomains((array) $data['domains']);
        }

        if (isset($data['status'])) {
            $license->setStatus((int) $data['status']);
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
            } else {
                $license->setExpireDate((int) $data['expire_date']);
            }
        }

        if (isset($data['expire_period'])) {
            $license->setExpirePeriod((int) $data['expire_period']);
        }

        return $license;
    }

    /**
     * Generate a random license key
     *
     * @return string
     */
    public static function generateKey(): string {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(substr(uniqid((string) random_int(1000, 9999)), 3, 5));
        }
        return implode('-', $segments);
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    /**
     * Convert to database format
     *
     * @return array
     */
    public function toDatabase(): array {
        $data = [
            'licenseno' => $this->licenseNo,
            'domain' => !empty($this->domains) ? serialize($this->domains) : '',
            'status' => $this->status,
            'oid' => $this->orderId,
            'pid' => $this->productId,
            'activation_date' => $this->activationDate,
            'expire_date' => $this->expireDate,
            'expire_period' => $this->expirePeriod,
            'domain_limit' => $this->domainLimit,
        ];

        return $data;
    }

    /**
     * Convert to array for API responses
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'license_no' => $this->licenseNo,
            'domains' => $this->domains,
            'domain_count' => $this->getDomainCount(),
            'domain_limit' => $this->domainLimit,
            'remaining_slots' => $this->getRemainingDomainSlots(),
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'activation_date' => $this->activationDate,
            'expire_date' => $this->expireDate,
            'expire_period' => $this->expirePeriod,
        ];
    }
}
