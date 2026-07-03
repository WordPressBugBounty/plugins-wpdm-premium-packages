<?php
/**
 * Product Service
 *
 * Main orchestration class for product operations.
 * Provides the primary API for working with product pricing data.
 *
 * @package WPDMPP\Product
 * @since 7.0.0
 */

namespace WPDMPP\Product;

use WPDMPP\Product\Repository\MetaRepository;

defined('ABSPATH') || exit;

class ProductService {

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Product repository
     *
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $repository;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->repository = new MetaRepository();
    }

    /**
     * Get the repository instance
     *
     * @return ProductRepositoryInterface
     */
    public function getRepository(): ProductRepositoryInterface {
        return $this->repository;
    }

    /**
     * Set a custom repository (for testing)
     *
     * @param ProductRepositoryInterface $repository
     * @return void
     */
    public function setRepository(ProductRepositoryInterface $repository): void {
        $this->repository = $repository;
    }

    // ==================
    // Product Retrieval
    // ==================

    /**
     * Get a product by ID
     *
     * @param int $id Product ID
     * @return Product|null
     */
    public function getProduct(int $id): ?Product {
        return $this->repository->findById($id);
    }

    /**
     * Get a product by product code
     *
     * @param string $code Product code
     * @return Product|null
     */
    public function getProductByCode(string $code): ?Product {
        return $this->repository->findByCode($code);
    }

    /**
     * Check if a product exists
     *
     * @param int $id Product ID
     * @return bool
     */
    public function productExists(int $id): bool {
        return $this->repository->exists($id);
    }

    /**
     * Check if a product is premium (has price > 0)
     *
     * @param int $id Product ID
     * @return bool
     */
    public function isPremium(int $id): bool {
        $product = $this->getProduct($id);
        return $product ? $product->isPremium() : false;
    }

    // ==================
    // Pricing
    // ==================

    /**
     * Get the effective price for a product
     *
     * @param int         $productId Product ID
     * @param string|null $licenseId Optional license ID
     * @return float
     */
    public function getPrice(int $productId, ?string $licenseId = null): float {
        $product = $this->getProduct($productId);
        if (!$product) {
            return 0.0;
        }
        return $product->getEffectivePrice($licenseId);
    }

    /**
     * Get the price after applying user role discount
     *
     * @param int         $productId Product ID
     * @param string|null $licenseId Optional license ID
     * @return float
     */
    public function getDiscountedPrice(int $productId, ?string $licenseId = null): float {
        $product = $this->getProduct($productId);
        if (!$product) {
            return 0.0;
        }
        return $product->getDiscountedPrice($licenseId);
    }

    /**
     * Get the price range for a product
     *
     * @param int $productId Product ID
     * @return array [min, max]
     */
    public function getPriceRange(int $productId): array {
        $product = $this->getProduct($productId);
        if (!$product) {
            return [0.0, 0.0];
        }
        return $product->getPriceRange();
    }

    /**
     * Check if a product has an active sale
     *
     * @param int $productId Product ID
     * @return bool
     */
    public function hasActiveSale(int $productId): bool {
        $product = $this->getProduct($productId);
        return $product ? $product->isSaleActive() : false;
    }

    /**
     * Get sale price info
     *
     * @param int $productId Product ID
     * @return array|null
     */
    public function getSaleInfo(int $productId): ?array {
        $product = $this->getProduct($productId);
        if (!$product || !$product->isSaleActive()) {
            return null;
        }

        return [
            'sale_price' => $product->getSalesPrice(),
            'original_price' => $product->getBasePrice(),
            'expires' => $product->getSalesPriceExpire(),
            'expires_formatted' => $product->getSalesPriceExpire() > 0
                ? wp_date(get_option('date_format'), $product->getSalesPriceExpire())
                : null,
            'discount_percent' => $product->getBasePrice() > 0
                ? round((($product->getBasePrice() - $product->getSalesPrice()) / $product->getBasePrice()) * 100)
                : 0,
        ];
    }

    // ==================
    // License Operations
    // ==================

    /**
     * Get license pricing for a product
     *
     * @param int    $productId Product ID
     * @param string $licenseId License ID
     * @return float
     */
    public function getLicensePrice(int $productId, string $licenseId): float {
        $product = $this->getProduct($productId);
        if (!$product) {
            return 0.0;
        }
        return $product->getLicensePrice($licenseId);
    }

    /**
     * Get license information for a product
     *
     * @param int    $productId Product ID
     * @param string $licenseId License ID
     * @return array|null
     */
    public function getLicenseInfo(int $productId, string $licenseId): ?array {
        $product = $this->getProduct($productId);
        if (!$product) {
            return null;
        }
        return $product->getLicenseInfo($licenseId);
    }

    /**
     * Get all active licenses for a product
     *
     * @param int $productId Product ID
     * @return array
     */
    public function getActiveLicenses(int $productId): array {
        $product = $this->getProduct($productId);
        if (!$product) {
            return [];
        }
        return $product->getActiveLicenses();
    }

    /**
     * Check if a product has license variations
     *
     * @param int $productId Product ID
     * @return bool
     */
    public function hasLicenseVariations(int $productId): bool {
        $product = $this->getProduct($productId);
        return $product && $product->isLicenseEnabled() && !empty($product->getLicenses());
    }

    // ==================
    // Extra Gigs (Variations)
    // ==================

    /**
     * Get extra gigs/variations for a product
     *
     * @param int $productId Product ID
     * @return array
     */
    public function getVariations(int $productId): array {
        $product = $this->getProduct($productId);
        if (!$product || !$product->hasVariations()) {
            return [];
        }
        return $product->getVariations();
    }

    /**
     * Calculate the cost of selected gigs
     *
     * @param int   $productId    Product ID
     * @param array $selectedGigs Array of selected gig IDs
     * @return float
     */
    public function calculateGigsCost(int $productId, array $selectedGigs): float {
        $product = $this->getProduct($productId);
        if (!$product) {
            return 0.0;
        }
        return $product->calculateGigsCost($selectedGigs);
    }

    /**
     * Check if a product has extra gigs
     *
     * @param int $productId Product ID
     * @return bool
     */
    public function hasVariations(int $productId): bool {
        $product = $this->getProduct($productId);
        return $product && $product->hasVariations();
    }

    // ==================
    // Role Discounts
    // ==================

    /**
     * Get role discount for current user
     *
     * @param int $productId Product ID
     * @return float Discount percentage
     */
    public function getRoleDiscount(int $productId): float {
        $product = $this->getProduct($productId);
        if (!$product) {
            return 0.0;
        }
        return $product->getRoleDiscount();
    }

    /**
     * Get the role name that gives the discount
     *
     * @param int $productId Product ID
     * @return string|null
     */
    public function getDiscountedRole(int $productId): ?string {
        $product = $this->getProduct($productId);
        if (!$product) {
            return null;
        }
        return $product->getRoleDiscount(true);
    }

    // ==================
    // Customer Role Management
    // ==================

    /**
     * Assign role to customer after purchase
     *
     * @param int $productId Product ID
     * @param int $userId    User ID
     * @return bool
     */
    public function assignCustomerRole(int $productId, int $userId): bool {
        $product = $this->getProduct($productId);
        if (!$product) {
            return false;
        }
        return $product->assignRoleToCustomer($userId);
    }

    /**
     * Remove assigned role from customer
     *
     * @param int $productId Product ID
     * @param int $userId    User ID
     * @return bool
     */
    public function removeCustomerRole(int $productId, int $userId): bool {
        $product = $this->getProduct($productId);
        if (!$product) {
            return false;
        }
        return $product->removeRoleFromCustomer($userId);
    }

    /**
     * Get the role that will be assigned on purchase
     *
     * @param int $productId Product ID
     * @return string|null
     */
    public function getAssignRole(int $productId): ?string {
        $product = $this->getProduct($productId);
        if (!$product) {
            return null;
        }
        $role = $product->getAssignRole();
        return !empty($role) && $role !== '-1' ? $role : null;
    }

    // ==================
    // Product Listings
    // ==================

    /**
     * Get all premium products
     *
     * @param array $args Query arguments
     * @return Product[]
     */
    public function getPremiumProducts(array $args = []): array {
        return $this->repository->findAllPremium($args);
    }

    /**
     * Get products on sale
     *
     * @param array $args Query arguments
     * @return Product[]
     */
    public function getProductsOnSale(array $args = []): array {
        return $this->repository->findOnSale($args);
    }

    /**
     * Get products by license type
     *
     * @param string $licenseId License type ID
     * @param array  $args      Query arguments
     * @return Product[]
     */
    public function getProductsByLicenseType(string $licenseId, array $args = []): array {
        return $this->repository->findByLicenseType($licenseId, $args);
    }

    /**
     * Get products by price range
     *
     * @param float $minPrice Minimum price
     * @param float $maxPrice Maximum price
     * @param array $args     Query arguments
     * @return Product[]
     */
    public function getProductsByPriceRange(float $minPrice, float $maxPrice, array $args = []): array {
        return $this->repository->findByPriceRange($minPrice, $maxPrice, $args);
    }

    /**
     * Get products by category
     *
     * @param int|string $category Category ID or slug
     * @param array      $args     Query arguments
     * @return Product[]
     */
    public function getProductsByCategory($category, array $args = []): array {
        return $this->repository->findByCategory($category, $args);
    }

    /**
     * Search products
     *
     * @param string $keyword Search keyword
     * @param array  $args    Query arguments
     * @return Product[]
     */
    public function searchProducts(string $keyword, array $args = []): array {
        return $this->repository->search($keyword, $args);
    }

    /**
     * Get recently added premium products
     *
     * @param int   $limit Number of products
     * @param array $args  Query arguments
     * @return Product[]
     */
    public function getRecentProducts(int $limit = 10, array $args = []): array {
        return $this->repository->findRecent($limit, $args);
    }

    /**
     * Get best selling products
     *
     * @param int   $limit Number of products
     * @param array $args  Query arguments
     * @return Product[]
     */
    public function getBestSellers(int $limit = 10, array $args = []): array {
        return $this->repository->findBestSelling($limit, $args);
    }

    // ==================
    // Statistics
    // ==================

    /**
     * Get product statistics
     *
     * @return array
     */
    public function getStatistics(): array {
        return [
            'total_premium' => $this->repository->countPremium(),
            'on_sale' => $this->repository->countOnSale(),
        ];
    }

    /**
     * Count premium products
     *
     * @return int
     */
    public function countPremiumProducts(): int {
        return $this->repository->countPremium();
    }

    /**
     * Count products on sale
     *
     * @return int
     */
    public function countProductsOnSale(): int {
        return $this->repository->countOnSale();
    }

    // ==================
    // CRUD Operations
    // ==================

    /**
     * Save product pricing data
     *
     * @param int   $productId Product ID
     * @param array $data      Pricing data
     * @return array ['success' => bool, 'product' => Product|null, 'message' => string]
     */
    public function saveProduct(int $productId, array $data): array {
        if (!$this->repository->exists($productId)) {
            return [
                'success' => false,
                'product' => null,
                'message' => __('Product not found.', 'wpdm-premium-packages'),
            ];
        }

        $product = new Product($productId);

        // Update product data
        if (isset($data['product_code'])) {
            $product->setProductCode(sanitize_text_field($data['product_code']));
        }
        if (isset($data['base_price'])) {
            $product->setBasePrice((float) $data['base_price']);
        }
        if (isset($data['sales_price'])) {
            $product->setSalesPrice((float) $data['sales_price']);
        }
        if (isset($data['sales_price_expire'])) {
            $product->setSalesPriceExpire((int) $data['sales_price_expire']);
        }
        if (isset($data['pay_as_you_want'])) {
            $product->setPayAsYouWant((bool) $data['pay_as_you_want']);
        }
        if (isset($data['variations_enabled'])) {
            $product->setVariationsEnabled((bool) $data['variations_enabled']);
        }
        if (isset($data['variations'])) {
            $product->setVariations((array) $data['variations']);
        }
        if (isset($data['license_enabled'])) {
            $product->setLicenseEnabled((bool) $data['license_enabled']);
        }
        if (isset($data['licenses'])) {
            $product->setLicenses((array) $data['licenses']);
        }
        if (isset($data['license_key_required'])) {
            $product->setLicenseKeyRequired((bool) $data['license_key_required']);
        }
        if (isset($data['role_discounts'])) {
            $product->setRoleDiscounts((array) $data['role_discounts']);
        }
        if (isset($data['assign_role'])) {
            $product->setAssignRole(sanitize_text_field($data['assign_role']));
        }
        if (isset($data['free_downloads'])) {
            $product->setFreeDownloads((array) $data['free_downloads']);
        }

        $saved = $this->repository->save($product);

        if ($saved) {
            do_action('wpdmpp_product_saved', $product);
        }

        return [
            'success' => $saved,
            'product' => $saved ? $product : null,
            'message' => $saved
                ? __('Product saved successfully.', 'wpdm-premium-packages')
                : __('Failed to save product.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Delete product pricing data (reset to free)
     *
     * @param int $productId Product ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteProduct(int $productId): array {
        if (!$this->repository->exists($productId)) {
            return [
                'success' => false,
                'message' => __('Product not found.', 'wpdm-premium-packages'),
            ];
        }

        $deleted = $this->repository->delete($productId);

        if ($deleted) {
            do_action('wpdmpp_product_deleted', $productId);
        }

        return [
            'success' => $deleted,
            'message' => $deleted
                ? __('Product pricing data deleted.', 'wpdm-premium-packages')
                : __('Failed to delete product pricing data.', 'wpdm-premium-packages'),
        ];
    }

    // ==================
    // Purchase Verification
    // ==================

    /**
     * Check if a user has purchased a product
     *
     * @param int $productId Product ID
     * @param int $userId    User ID (0 for current user)
     * @return bool|string Order ID if purchased, false otherwise
     */
    public function hasPurchased(int $productId, int $userId = 0) {
        global $wpdb;

        if ($userId === 0) {
            $userId = get_current_user_id();
        }

        if ($userId <= 0) {
            return false;
        }

        $orderId = $wpdb->get_var($wpdb->prepare(
            "SELECT o.order_id
             FROM {$wpdb->prefix}ahm_orders o
             INNER JOIN {$wpdb->prefix}ahm_order_items oi ON oi.oid = o.order_id
             WHERE o.uid = %d
             AND oi.pid = %d
             AND o.order_status = 'Completed'
             ORDER BY o.date DESC
             LIMIT 1",
            $userId,
            $productId
        ));

        return $orderId ?: false;
    }

    /**
     * Get customer download URL for a purchased product
     *
     * @param int    $productId Product ID
     * @param string $orderId   Order ID
     * @param array  $extras    Extra parameters
     * @return string
     */
    public function getCustomerDownloadURL(int $productId, string $orderId, array $extras = []): string {
        if (class_exists('\WPDMPremiumPackage')) {
            return \WPDMPremiumPackage::customerDownloadURL($productId, $orderId, $extras);
        }

        // Fallback implementation
        $args = array_merge([
            '__wpdmpp' => $productId,
            'oid' => $orderId,
        ], $extras);

        $url = add_query_arg($args, site_url('/'));
        return $url;
    }

    // ==================
    // Global License Types
    // ==================

    /**
     * Get global license types from settings
     *
     * @return array
     */
    public function getGlobalLicenseTypes(): array {
        if (function_exists('wpdmpp_get_licenses')) {
            return wpdmpp_get_licenses();
        }

        $licenses = get_option('_wpdmpp_license', []);
        return is_array($licenses) ? $licenses : [];
    }
}
