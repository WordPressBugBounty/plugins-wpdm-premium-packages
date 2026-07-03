<?php
/**
 * Product Entity Class
 *
 * Represents pricing data for a WPDM package (wpdmpro post type).
 * This plugin doesn't create separate products - it adds pricing metadata
 * to existing WordPress Download Manager packages.
 *
 * @package WPDMPP\Product
 * @since 7.0.0
 */

namespace WPDMPP\Product;

defined('ABSPATH') || exit;

class Product {

    /**
     * Post ID (wpdmpro post type)
     *
     * @var int
     */
    private int $id;

    /**
     * Product code (unique identifier)
     *
     * @var string
     */
    private string $productCode = '';

    /**
     * Base price
     *
     * @var float
     */
    private float $basePrice = 0.0;

    /**
     * Sales/promotional price
     *
     * @var float
     */
    private float $salesPrice = 0.0;

    /**
     * Sales price expiration timestamp
     *
     * @var int
     */
    private int $salesPriceExpire = 0;

    /**
     * Pay as you want enabled
     *
     * @var bool
     */
    private bool $payAsYouWant = false;

    /**
     * Price variations (extra gigs) enabled
     *
     * @var bool
     */
    private bool $variationsEnabled = false;

    /**
     * Extra gigs/variations data
     *
     * @var array
     */
    private array $variations = [];

    /**
     * License variations enabled
     *
     * @var bool
     */
    private bool $licenseEnabled = false;

    /**
     * License pricing configuration
     *
     * @var array
     */
    private array $licenses = [];

    /**
     * License key required
     *
     * @var bool
     */
    private bool $licenseKeyRequired = false;

    /**
     * Role-based discounts
     *
     * @var array
     */
    private array $roleDiscounts = [];

    /**
     * Role to assign on purchase
     *
     * @var string
     */
    private string $assignRole = '';

    /**
     * Free downloadable files
     *
     * @var array
     */
    private array $freeDownloads = [];

    /**
     * Product name (post title)
     *
     * @var string
     */
    private string $name = '';

    /**
     * Whether the product data has been loaded
     *
     * @var bool
     */
    private bool $loaded = false;

    /**
     * Constructor
     *
     * @param int $id Product/Package ID
     */
    public function __construct(int $id = 0) {
        $this->id = $id;
        if ($id > 0) {
            $this->load();
        }
    }

    /**
     * Load product data from post meta
     *
     * @return self
     */
    public function load(): self {
        if ($this->id <= 0) {
            return $this;
        }

        $post = get_post($this->id);
        if (!$post || $post->post_type !== 'wpdmpro') {
            return $this;
        }

        $this->name = $post->post_title;
        $this->productCode = (string) get_post_meta($this->id, '__wpdm_product_code', true);
        $this->basePrice = (float) get_post_meta($this->id, '__wpdm_base_price', true);
        $this->salesPrice = (float) get_post_meta($this->id, '__wpdm_sales_price', true);
        $this->salesPriceExpire = (int) get_post_meta($this->id, '__wpdm_sales_price_expire', true);
        $this->payAsYouWant = (bool) get_post_meta($this->id, '__wpdm_pay_as_you_want', true);
        $this->variationsEnabled = (bool) get_post_meta($this->id, '__wpdm_price_variation', true);

        $variations = get_post_meta($this->id, '__wpdm_variation', true);
        $this->variations = is_array($variations) ? $variations : [];

        $this->licenseEnabled = (bool) get_post_meta($this->id, '__wpdm_enable_license', true);

        $licenses = get_post_meta($this->id, '__wpdm_license', true);
        $this->licenses = is_array($licenses) ? $licenses : [];

        $this->licenseKeyRequired = (bool) get_post_meta($this->id, '__wpdm_enable_license_key', true);

        $roleDiscounts = get_post_meta($this->id, '__wpdm_discount', true);
        $this->roleDiscounts = is_array($roleDiscounts) ? $roleDiscounts : [];

        $this->assignRole = (string) get_post_meta($this->id, '__wpdm_assign_role', true);

        $freeDownloads = get_post_meta($this->id, '__wpdm_free_downloads', true);
        $this->freeDownloads = is_array($freeDownloads) ? $freeDownloads : [];

        $this->loaded = true;

        return $this;
    }

    /**
     * Create from array data
     *
     * @param int   $id   Product ID
     * @param array $data Product data
     * @return self
     */
    public static function fromArray(int $id, array $data): self {
        $product = new self();
        $product->id = $id;
        $product->name = $data['name'] ?? '';
        $product->productCode = $data['product_code'] ?? '';
        $product->basePrice = (float) ($data['base_price'] ?? 0);
        $product->salesPrice = (float) ($data['sales_price'] ?? 0);
        $product->salesPriceExpire = (int) ($data['sales_price_expire'] ?? 0);
        $product->payAsYouWant = (bool) ($data['pay_as_you_want'] ?? false);
        $product->variationsEnabled = (bool) ($data['variations_enabled'] ?? false);
        $product->variations = $data['variations'] ?? [];
        $product->licenseEnabled = (bool) ($data['license_enabled'] ?? false);
        $product->licenses = $data['licenses'] ?? [];
        $product->licenseKeyRequired = (bool) ($data['license_key_required'] ?? false);
        $product->roleDiscounts = $data['role_discounts'] ?? [];
        $product->assignRole = $data['assign_role'] ?? '';
        $product->freeDownloads = $data['free_downloads'] ?? [];
        $product->loaded = true;

        return $product;
    }

    // ==================
    // Getters
    // ==================

    public function getId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getProductCode(): string {
        return $this->productCode;
    }

    public function getBasePrice(): float {
        return $this->basePrice;
    }

    public function getSalesPrice(): float {
        return $this->salesPrice;
    }

    public function getSalesPriceExpire(): int {
        return $this->salesPriceExpire;
    }

    public function isPayAsYouWant(): bool {
        return $this->payAsYouWant;
    }

    public function hasVariations(): bool {
        return $this->variationsEnabled && !empty($this->variations);
    }

    public function getVariations(): array {
        return $this->variations;
    }

    public function isLicenseEnabled(): bool {
        return $this->licenseEnabled;
    }

    public function getLicenses(): array {
        return $this->licenses;
    }

    public function isLicenseKeyRequired(): bool {
        return $this->licenseKeyRequired;
    }

    public function getRoleDiscounts(): array {
        return $this->roleDiscounts;
    }

    public function getAssignRole(): string {
        return $this->assignRole;
    }

    public function getFreeDownloads(): array {
        return $this->freeDownloads;
    }

    public function isLoaded(): bool {
        return $this->loaded;
    }

    // ==================
    // Setters
    // ==================

    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function setProductCode(string $code): self {
        $this->productCode = $code;
        return $this;
    }

    public function setBasePrice(float $price): self {
        $this->basePrice = $price;
        return $this;
    }

    public function setSalesPrice(float $price): self {
        $this->salesPrice = $price;
        return $this;
    }

    public function setSalesPriceExpire(int $timestamp): self {
        $this->salesPriceExpire = $timestamp;
        return $this;
    }

    public function setPayAsYouWant(bool $enabled): self {
        $this->payAsYouWant = $enabled;
        return $this;
    }

    public function setVariationsEnabled(bool $enabled): self {
        $this->variationsEnabled = $enabled;
        return $this;
    }

    public function setVariations(array $variations): self {
        $this->variations = $variations;
        return $this;
    }

    public function setLicenseEnabled(bool $enabled): self {
        $this->licenseEnabled = $enabled;
        return $this;
    }

    public function setLicenses(array $licenses): self {
        $this->licenses = $licenses;
        return $this;
    }

    public function setLicenseKeyRequired(bool $required): self {
        $this->licenseKeyRequired = $required;
        return $this;
    }

    public function setRoleDiscounts(array $discounts): self {
        $this->roleDiscounts = $discounts;
        return $this;
    }

    public function setAssignRole(string $role): self {
        $this->assignRole = $role;
        return $this;
    }

    public function setFreeDownloads(array $files): self {
        $this->freeDownloads = $files;
        return $this;
    }

    // ==================
    // Pricing Methods
    // ==================

    /**
     * Check if product is premium (has a price > 0)
     *
     * @return bool
     */
    public function isPremium(): bool {
        return $this->getEffectivePrice() > 0;
    }

    /**
     * Check if sales price is currently active
     *
     * @return bool
     */
    public function isSaleActive(): bool {
        if ($this->salesPrice <= 0) {
            return false;
        }

        // No expiration set means sale is always active
        if ($this->salesPriceExpire <= 0) {
            return true;
        }

        return time() < $this->salesPriceExpire;
    }

    /**
     * Get the current effective price (sale price if active, otherwise base price)
     *
     * @param string|null $licenseId License ID for license-based pricing
     * @return float
     */
    public function getEffectivePrice(?string $licenseId = null): float {
        // License-based pricing
        if ($licenseId !== null && $this->licenseEnabled && !empty($this->licenses[$licenseId])) {
            return $this->getLicensePrice($licenseId);
        }

        // Sale price if active
        if ($this->isSaleActive()) {
            return $this->salesPrice;
        }

        return $this->basePrice;
    }

    /**
     * Get price for a specific license tier
     *
     * @param string $licenseId License ID
     * @return float
     */
    public function getLicensePrice(string $licenseId): float {
        if (!isset($this->licenses[$licenseId])) {
            return $this->basePrice;
        }

        $license = $this->licenses[$licenseId];
        $price = isset($license['price']) ? (float) $license['price'] : $this->basePrice;

        // Check for sale price on license
        if (isset($license['sale_price']) && (float) $license['sale_price'] > 0) {
            $saleExpire = isset($license['sale_expire']) ? (int) $license['sale_expire'] : 0;
            if ($saleExpire <= 0 || time() < $saleExpire) {
                return (float) $license['sale_price'];
            }
        }

        return $price;
    }

    /**
     * Get license information
     *
     * @param string $licenseId License ID
     * @return array|null
     */
    public function getLicenseInfo(string $licenseId): ?array {
        if (!isset($this->licenses[$licenseId])) {
            return null;
        }

        $license = $this->licenses[$licenseId];
        $globalLicenses = $this->getGlobalLicenses();
        $globalInfo = $globalLicenses[$licenseId] ?? [];

        return array_merge($globalInfo, [
            'id' => $licenseId,
            'price' => $this->getLicensePrice($licenseId),
            'name' => $globalInfo['name'] ?? $licenseId,
            'domain_limit' => $license['domain_limit'] ?? ($globalInfo['domain'] ?? 0),
            'validity' => $license['validity'] ?? ($globalInfo['validity'] ?? 0),
        ]);
    }

    /**
     * Get all active licenses for this product
     *
     * @return array
     */
    public function getActiveLicenses(): array {
        if (!$this->licenseEnabled) {
            return [];
        }

        $globalLicenses = $this->getGlobalLicenses();
        $activeLicenses = [];

        foreach ($this->licenses as $licenseId => $licenseData) {
            if (!isset($globalLicenses[$licenseId])) {
                continue;
            }

            $activeLicenses[$licenseId] = $this->getLicenseInfo($licenseId);
        }

        return $activeLicenses;
    }

    /**
     * Get global license types from settings
     *
     * @return array
     */
    private function getGlobalLicenses(): array {
        if (function_exists('wpdmpp_get_licenses')) {
            return wpdmpp_get_licenses();
        }

        $licenses = get_option('_wpdmpp_license', []);
        return is_array($licenses) ? $licenses : [];
    }

    /**
     * Get price range for products with license variations
     *
     * @return array [min, max]
     */
    public function getPriceRange(): array {
        if (!$this->licenseEnabled || empty($this->licenses)) {
            $price = $this->getEffectivePrice();
            return [$price, $price];
        }

        $prices = [];
        foreach ($this->licenses as $licenseId => $licenseData) {
            $prices[] = $this->getLicensePrice($licenseId);
        }

        if (empty($prices)) {
            $price = $this->getEffectivePrice();
            return [$price, $price];
        }

        return [min($prices), max($prices)];
    }

    /**
     * Calculate the cost of selected extra gigs
     *
     * @param array $selectedGigs Array of selected gig IDs
     * @return float
     */
    public function calculateGigsCost(array $selectedGigs): float {
        if (!$this->variationsEnabled || empty($this->variations)) {
            return 0.0;
        }

        $cost = 0.0;

        foreach ($this->variations as $groupIndex => $group) {
            if (!isset($group['options']) || !is_array($group['options'])) {
                continue;
            }

            foreach ($group['options'] as $optionIndex => $option) {
                $gigId = "{$groupIndex}_{$optionIndex}";
                if (in_array($gigId, $selectedGigs) && isset($option['price'])) {
                    $cost += (float) $option['price'];
                }
            }
        }

        return $cost;
    }

    /**
     * Get discount for current user's role
     *
     * @param bool $returnName Whether to return discount name instead of percentage
     * @return float|string|null
     */
    public function getRoleDiscount(bool $returnName = false) {
        if (empty($this->roleDiscounts)) {
            return $returnName ? null : 0.0;
        }

        $user = wp_get_current_user();
        if (!$user->exists()) {
            return $returnName ? null : 0.0;
        }

        foreach ($user->roles as $role) {
            if (isset($this->roleDiscounts[$role])) {
                if ($returnName) {
                    return $role;
                }
                return (float) $this->roleDiscounts[$role];
            }
        }

        return $returnName ? null : 0.0;
    }

    /**
     * Get the effective price after applying role discount
     *
     * @param string|null $licenseId License ID for license-based pricing
     * @return float
     */
    public function getDiscountedPrice(?string $licenseId = null): float {
        $price = $this->getEffectivePrice($licenseId);
        $discount = $this->getRoleDiscount();

        if ($discount > 0) {
            $price = $price - ($price * ($discount / 100));
        }

        return max(0, $price);
    }

    /**
     * Assign role to customer after purchase
     *
     * @param int $userId User ID
     * @return bool
     */
    public function assignRoleToCustomer(int $userId): bool {
        if (empty($this->assignRole) || $this->assignRole === '-1') {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        $user->add_role($this->assignRole);
        return true;
    }

    /**
     * Remove assigned role from customer
     *
     * @param int $userId User ID
     * @return bool
     */
    public function removeRoleFromCustomer(int $userId): bool {
        if (empty($this->assignRole) || $this->assignRole === '-1') {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        $user->remove_role($this->assignRole);
        return true;
    }

    // ==================
    // Conversion Methods
    // ==================

    /**
     * Convert to array for API response
     *
     * @return array
     */
    public function toArray(): array {
        $priceRange = $this->getPriceRange();
        $formattedPrice = $this->formatPrice($this->getEffectivePrice());
        $formattedPriceRange = $priceRange[0] !== $priceRange[1]
            ? $this->formatPrice($priceRange[0]) . ' - ' . $this->formatPrice($priceRange[1])
            : $formattedPrice;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'product_code' => $this->productCode,
            'base_price' => $this->basePrice,
            'sales_price' => $this->salesPrice,
            'sales_price_expire' => $this->salesPriceExpire,
            'sale_active' => $this->isSaleActive(),
            'effective_price' => $this->getEffectivePrice(),
            'discounted_price' => $this->getDiscountedPrice(),
            'formatted_price' => $formattedPrice,
            'price_range' => $priceRange,
            'formatted_price_range' => $formattedPriceRange,
            'is_premium' => $this->isPremium(),
            'pay_as_you_want' => $this->payAsYouWant,
            'variations_enabled' => $this->variationsEnabled,
            'variations' => $this->variations,
            'license_enabled' => $this->licenseEnabled,
            'licenses' => $this->getActiveLicenses(),
            'license_key_required' => $this->licenseKeyRequired,
            'role_discounts' => $this->roleDiscounts,
            'current_role_discount' => $this->getRoleDiscount(),
            'assign_role' => $this->assignRole,
            'free_downloads' => $this->freeDownloads,
        ];
    }

    /**
     * Get data for saving to post meta
     *
     * @return array
     */
    public function toMeta(): array {
        return [
            '__wpdm_product_code' => $this->productCode,
            '__wpdm_base_price' => $this->basePrice,
            '__wpdm_sales_price' => $this->salesPrice,
            '__wpdm_sales_price_expire' => $this->salesPriceExpire,
            '__wpdm_pay_as_you_want' => $this->payAsYouWant ? 1 : 0,
            '__wpdm_price_variation' => $this->variationsEnabled ? 1 : 0,
            '__wpdm_variation' => $this->variations,
            '__wpdm_enable_license' => $this->licenseEnabled ? 1 : 0,
            '__wpdm_license' => $this->licenses,
            '__wpdm_enable_license_key' => $this->licenseKeyRequired ? 1 : 0,
            '__wpdm_discount' => $this->roleDiscounts,
            '__wpdm_assign_role' => $this->assignRole,
            '__wpdm_free_downloads' => $this->freeDownloads,
        ];
    }

    /**
     * Format price with currency
     *
     * @param float $price
     * @return string
     */
    private function formatPrice(float $price): string {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($price, true, true);
        }

        $sign = function_exists('wpdmpp_currency_sign') ? wpdmpp_currency_sign() : '$';
        return $sign . number_format($price, 2);
    }

    // ==================
    // Static Helpers
    // ==================

    /**
     * Check if a package is premium (static helper)
     *
     * @param int $productId Product ID
     * @return bool
     */
    public static function isPremiumProduct(int $productId): bool {
        $product = new self($productId);
        return $product->isPremium();
    }

    /**
     * Get effective price for a product (static helper)
     *
     * @param int         $productId Product ID
     * @param string|null $licenseId License ID
     * @return float
     */
    public static function getProductPrice(int $productId, ?string $licenseId = null): float {
        $product = new self($productId);
        return $product->getEffectivePrice($licenseId);
    }
}
