<?php
/**
 * Coupon Service
 *
 * Main orchestration class for coupon operations.
 * Provides a facade for all coupon-related business logic.
 *
 * @package WPDMPP\Coupon
 * @since 7.0.0
 */

namespace WPDMPP\Coupon;

use WPDMPP\Coupon\Repository\DatabaseRepository;

defined('ABSPATH') || exit;

class CouponService {

    /**
     * Singleton instance
     *
     * @var CouponService|null
     */
    private static ?CouponService $instance = null;

    /**
     * Coupon repository
     *
     * @var CouponRepositoryInterface
     */
    private CouponRepositoryInterface $repository;

    /**
     * Get singleton instance
     *
     * @return CouponService
     */
    public static function getInstance(): CouponService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param CouponRepositoryInterface|null $repository
     */
    public function __construct(?CouponRepositoryInterface $repository = null) {
        $this->repository = $repository ?? new DatabaseRepository();
    }

    /**
     * Set repository (for testing)
     *
     * @param CouponRepositoryInterface $repository
     * @return void
     */
    public function setRepository(CouponRepositoryInterface $repository): void {
        $this->repository = $repository;
    }

    /**
     * Get repository
     *
     * @return CouponRepositoryInterface
     */
    public function getRepository(): CouponRepositoryInterface {
        return $this->repository;
    }

    /**
     * Find coupon by code
     *
     * @param string $code Coupon code
     * @return Coupon|null
     */
    public function findByCode(string $code): ?Coupon {
        return $this->repository->findByCode($code);
    }

    /**
     * Find coupon by ID
     *
     * @param int $id Coupon ID
     * @return Coupon|null
     */
    public function findById(int $id): ?Coupon {
        return $this->repository->findById($id);
    }

    /**
     * Validate a coupon code
     *
     * @param string     $code       Coupon code
     * @param float      $cartTotal  Cart total amount
     * @param array      $cartItems  Cart items
     * @param string|null $email     Customer email
     * @param int|null   $productId  Product ID (for product-specific validation)
     * @return array ['valid' => bool, 'coupon' => Coupon|null, 'error' => string|null, 'message' => string|null]
     */
    public function validateCoupon(
        string $code,
        float $cartTotal,
        array $cartItems = [],
        ?string $email = null,
        ?int $productId = null
    ): array {
        $coupon = $this->repository->findByCode($code);

        if (!$coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'error' => Coupon::ERROR_NOT_FOUND,
                'message' => __('Coupon not found.', 'wpdm-premium-packages'),
            ];
        }

        $validation = $coupon->validate($cartTotal, $cartItems, $email, $productId);

        if (!$validation['valid']) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'error' => $validation['error'],
                // Prefer the entity's specific message (e.g. "enter your billing
                // email first") over the generic per-error fallback.
                'message' => !empty($validation['message'])
                    ? $validation['message']
                    : $this->getErrorMessage($validation['error'], $coupon),
            ];
        }

        // Calculate discount
        $discount = $coupon->calculateDiscount($cartTotal, $cartItems, $productId);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'error' => null,
            'message' => null,
            'discount' => $discount,
            'discount_formatted' => wpdmpp_currency_sign() . number_format($discount, 2),
        ];
    }

    /**
     * Apply coupon to cart (validate and store in session)
     *
     * @param string     $code       Coupon code
     * @param float      $cartTotal  Cart total
     * @param array      $cartItems  Cart items
     * @param string|null $email     Customer email
     * @return array ['success' => bool, 'message' => string, 'discount' => float|null]
     */
    public function applyCoupon(
        string $code,
        float $cartTotal,
        array $cartItems = [],
        ?string $email = null
    ): array {
        $validation = $this->validateCoupon($code, $cartTotal, $cartItems, $email);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'discount' => null,
            ];
        }

        $coupon = $validation['coupon'];

        // Store in session
        $this->storeCouponInSession($coupon);

        /**
         * Fires when a coupon is applied to the cart
         *
         * @param Coupon $coupon The applied coupon
         * @param float  $discount The discount amount
         */
        do_action('wpdmpp_coupon_applied', $coupon, $validation['discount']);

        return [
            'success' => true,
            'message' => sprintf(
                __('Coupon "%s" applied! You save %s', 'wpdm-premium-packages'),
                $coupon->getCode(),
                $validation['discount_formatted']
            ),
            'discount' => $validation['discount'],
            'coupon' => $coupon->toArray(),
        ];
    }

    /**
     * Remove coupon from cart session
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function removeCoupon(): array {
        $currentCoupon = $this->getAppliedCoupon();

        $this->clearCouponFromSession();

        /**
         * Fires when a coupon is removed from the cart
         *
         * @param Coupon|null $coupon The removed coupon (if any)
         */
        do_action('wpdmpp_coupon_removed', $currentCoupon);

        return [
            'success' => true,
            'message' => __('Coupon removed.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Get currently applied coupon from session
     *
     * @return Coupon|null
     */
    public function getAppliedCoupon(): ?Coupon {
        if (!isset($_SESSION['wpdmpp_coupon_code'])) {
            return null;
        }

        return $this->repository->findByCode($_SESSION['wpdmpp_coupon_code']);
    }

    /**
     * Get applied coupon code from session
     *
     * @return string|null
     */
    public function getAppliedCouponCode(): ?string {
        return $_SESSION['wpdmpp_coupon_code'] ?? null;
    }

    /**
     * Store coupon in session
     *
     * @param Coupon $coupon
     * @return void
     */
    private function storeCouponInSession(Coupon $coupon): void {
        $_SESSION['wpdmpp_coupon_code'] = $coupon->getCode();
    }

    /**
     * Clear coupon from session
     *
     * @return void
     */
    private function clearCouponFromSession(): void {
        unset($_SESSION['wpdmpp_coupon_code']);
    }

    /**
     * Find the best auto-apply coupon for a cart
     *
     * @param float $cartTotal Cart total
     * @param array $cartItems Cart items
     * @return Coupon|null
     */
    public function findBestAutoApplyCoupon(float $cartTotal, array $cartItems = []): ?Coupon {
        $autoApplyCoupons = $this->repository->findAutoApply($cartTotal);

        if (empty($autoApplyCoupons)) {
            return null;
        }

        $bestCoupon = null;
        $bestDiscount = 0;

        // Email-restricted coupons can only auto-apply for a known (logged-in) email
        $email = is_user_logged_in() ? wp_get_current_user()->user_email : null;

        foreach ($autoApplyCoupons as $coupon) {
            $validation = $coupon->validate($cartTotal, $cartItems, $email);
            if (!$validation['valid']) {
                continue;
            }

            $discount = $coupon->calculateDiscount($cartTotal, $cartItems);
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestCoupon = $coupon;
            }
        }

        return $bestCoupon;
    }

    /**
     * Auto-apply the best available coupon to cart
     *
     * @param float $cartTotal Cart total
     * @param array $cartItems Cart items
     * @return array ['success' => bool, 'coupon' => Coupon|null, 'discount' => float|null]
     */
    public function autoApplyCoupon(float $cartTotal, array $cartItems = []): array {
        // Don't auto-apply if a coupon is already applied
        if ($this->getAppliedCoupon() !== null) {
            return [
                'success' => false,
                'coupon' => null,
                'discount' => null,
                'message' => __('A coupon is already applied.', 'wpdm-premium-packages'),
            ];
        }

        $bestCoupon = $this->findBestAutoApplyCoupon($cartTotal, $cartItems);

        if (!$bestCoupon) {
            return [
                'success' => false,
                'coupon' => null,
                'discount' => null,
                'message' => __('No auto-apply coupon available.', 'wpdm-premium-packages'),
            ];
        }

        $discount = $bestCoupon->calculateDiscount($cartTotal, $cartItems);
        $this->storeCouponInSession($bestCoupon);

        /**
         * Fires when a coupon is auto-applied
         *
         * @param Coupon $coupon The auto-applied coupon
         * @param float  $discount The discount amount
         */
        do_action('wpdmpp_coupon_auto_applied', $bestCoupon, $discount);

        return [
            'success' => true,
            'coupon' => $bestCoupon,
            'discount' => $discount,
            'message' => sprintf(
                __('Coupon "%s" automatically applied!', 'wpdm-premium-packages'),
                $bestCoupon->getCode()
            ),
        ];
    }

    /**
     * Calculate discount for the current cart
     *
     * @param float    $cartTotal Cart total
     * @param array    $cartItems Cart items
     * @param int|null $productId Specific product ID
     * @return float
     */
    public function calculateDiscount(float $cartTotal, array $cartItems = [], ?int $productId = null): float {
        $coupon = $this->getAppliedCoupon();

        if (!$coupon) {
            return 0.0;
        }

        // Re-validate
        $validation = $coupon->validate($cartTotal, $cartItems, null, $productId);
        if (!$validation['valid']) {
            // Coupon no longer valid, remove it
            $this->clearCouponFromSession();
            return 0.0;
        }

        return $coupon->calculateDiscount($cartTotal, $cartItems, $productId);
    }

    /**
     * Increment coupon usage after successful order
     *
     * @param string $code Coupon code
     * @return bool
     */
    public function incrementUsage(string $code): bool {
        $result = $this->repository->incrementUsage($code);

        if ($result) {
            /**
             * Fires when coupon usage is incremented
             *
             * @param string $code The coupon code
             */
            do_action('wpdmpp_coupon_usage_incremented', $code);
        }

        return $result;
    }

    // =========================================================================
    // ADMIN METHODS
    // =========================================================================

    /**
     * Create a new coupon
     *
     * @param array $data Coupon data
     * @return array ['success' => bool, 'coupon' => Coupon|null, 'errors' => array]
     */
    public function createCoupon(array $data): array {
        // Check for duplicate code
        $code = strtoupper(trim($data['code'] ?? ''));
        if (empty($code)) {
            return [
                'success' => false,
                'coupon' => null,
                'errors' => ['code' => __('Coupon code is required.', 'wpdm-premium-packages')],
            ];
        }

        if ($this->repository->codeExists($code)) {
            return [
                'success' => false,
                'coupon' => null,
                'errors' => ['code' => __('Coupon code already exists.', 'wpdm-premium-packages')],
            ];
        }

        try {
            $coupon = Coupon::create($data);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'coupon' => null,
                'errors' => ['general' => $e->getMessage()],
            ];
        }

        $saved = $this->repository->save($coupon);

        if (!$saved) {
            return [
                'success' => false,
                'coupon' => null,
                'errors' => ['general' => __('Failed to save coupon.', 'wpdm-premium-packages')],
            ];
        }

        /**
         * Fires when a coupon is created
         *
         * @param Coupon $coupon The created coupon
         */
        do_action('wpdmpp_coupon_created', $coupon);

        return [
            'success' => true,
            'coupon' => $coupon,
            'errors' => [],
        ];
    }

    /**
     * Update an existing coupon
     *
     * @param int   $id   Coupon ID
     * @param array $data Coupon data
     * @return array ['success' => bool, 'coupon' => Coupon|null, 'errors' => array]
     */
    public function updateCoupon(int $id, array $data): array {
        $coupon = $this->repository->findById($id);

        if (!$coupon) {
            return [
                'success' => false,
                'coupon' => null,
                'errors' => ['general' => __('Coupon not found.', 'wpdm-premium-packages')],
            ];
        }

        // Check for duplicate code if code is being changed
        if (isset($data['code'])) {
            $newCode = strtoupper(trim($data['code']));
            if ($newCode !== $coupon->getCode() && $this->repository->codeExists($newCode, $id)) {
                return [
                    'success' => false,
                    'coupon' => null,
                    'errors' => ['code' => __('Coupon code already exists.', 'wpdm-premium-packages')],
                ];
            }
        }

        // Update coupon properties
        $this->updateCouponFromData($coupon, $data);

        $saved = $this->repository->save($coupon);

        if (!$saved) {
            return [
                'success' => false,
                'coupon' => null,
                'errors' => ['general' => __('Failed to update coupon.', 'wpdm-premium-packages')],
            ];
        }

        /**
         * Fires when a coupon is updated
         *
         * @param Coupon $coupon The updated coupon
         */
        do_action('wpdmpp_coupon_updated', $coupon);

        return [
            'success' => true,
            'coupon' => $coupon,
            'errors' => [],
        ];
    }

    /**
     * Delete a coupon
     *
     * @param int $id Coupon ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteCoupon(int $id): array {
        $coupon = $this->repository->findById($id);

        if (!$coupon) {
            return [
                'success' => false,
                'message' => __('Coupon not found.', 'wpdm-premium-packages'),
            ];
        }

        $deleted = $this->repository->delete($id);

        if (!$deleted) {
            return [
                'success' => false,
                'message' => __('Failed to delete coupon.', 'wpdm-premium-packages'),
            ];
        }

        /**
         * Fires when a coupon is deleted
         *
         * @param int    $id     The coupon ID
         * @param Coupon $coupon The deleted coupon
         */
        do_action('wpdmpp_coupon_deleted', $id, $coupon);

        return [
            'success' => true,
            'message' => __('Coupon deleted successfully.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Bulk delete coupons
     *
     * @param array $ids Coupon IDs
     * @return array ['success' => bool, 'deleted' => int, 'message' => string]
     */
    public function bulkDeleteCoupons(array $ids): array {
        if (empty($ids)) {
            return [
                'success' => false,
                'deleted' => 0,
                'message' => __('No coupons selected.', 'wpdm-premium-packages'),
            ];
        }

        $deleted = $this->repository->bulkDelete($ids);

        /**
         * Fires when coupons are bulk deleted
         *
         * @param array $ids     The coupon IDs
         * @param int   $deleted Number of deleted coupons
         */
        do_action('wpdmpp_coupons_bulk_deleted', $ids, $deleted);

        return [
            'success' => true,
            'deleted' => $deleted,
            'message' => sprintf(
                _n('%d coupon deleted.', '%d coupons deleted.', $deleted, 'wpdm-premium-packages'),
                $deleted
            ),
        ];
    }

    /**
     * Get all coupons with filtering
     *
     * @param array $args Query arguments
     * @return array ['coupons' => Coupon[], 'total' => int]
     */
    public function getCoupons(array $args = []): array {
        return $this->repository->findAll($args);
    }

    /**
     * Get active coupons
     *
     * @return Coupon[]
     */
    public function getActiveCoupons(): array {
        return $this->repository->findActive();
    }

    /**
     * Get coupons for a product
     *
     * @param int $productId Product ID
     * @return Coupon[]
     */
    public function getCouponsForProduct(int $productId): array {
        return $this->repository->findByProduct($productId);
    }

    /**
     * Get coupon usage statistics
     *
     * @param string $code Coupon code
     * @return array
     */
    public function getCouponStats(string $code): array {
        return $this->repository->getUsageStats($code);
    }

    /**
     * Generate a unique coupon code
     *
     * @param int    $length Code length
     * @param string $prefix Code prefix
     * @return string
     */
    public function generateCode(int $length = 8, string $prefix = ''): string {
        return $this->repository->generateUniqueCode($length, $prefix);
    }

    /**
     * Get total coupon count
     *
     * @param array $args Filter arguments
     * @return int
     */
    public function getCount(array $args = []): int {
        return $this->repository->count($args);
    }

    /**
     * Get expired coupons
     *
     * @return Coupon[]
     */
    public function getExpiredCoupons(): array {
        return $this->repository->findExpired();
    }

    /**
     * Get exhausted coupons
     *
     * @return Coupon[]
     */
    public function getExhaustedCoupons(): array {
        return $this->repository->findExhausted();
    }

    /**
     * Update coupon properties from data array
     *
     * @param Coupon $coupon
     * @param array  $data
     * @return void
     */
    private function updateCouponFromData(Coupon $coupon, array $data): void {
        if (isset($data['code'])) {
            $coupon->setCode($data['code']);
        }
        if (isset($data['description'])) {
            $coupon->setDescription($data['description']);
        }
        if (isset($data['type'])) {
            $coupon->setType($data['type']);
        }
        if (isset($data['discount'])) {
            $coupon->setDiscount((float) $data['discount']);
        }
        if (isset($data['min_order_amount'])) {
            $coupon->setMinOrderAmount((float) $data['min_order_amount']);
        }
        if (isset($data['max_order_amount'])) {
            $coupon->setMaxOrderAmount((float) $data['max_order_amount']);
        }
        if (isset($data['product_id'])) {
            $coupon->setProductId((int) $data['product_id']);
        }
        if (isset($data['allowed_emails'])) {
            $emails = $data['allowed_emails'];
            if (is_string($emails)) {
                $emails = !empty($emails) ? array_map('trim', explode(',', $emails)) : [];
            }
            $coupon->setAllowedEmails($emails);
        }
        if (isset($data['expire_date'])) {
            if (is_string($data['expire_date']) && !empty($data['expire_date'])) {
                $coupon->setExpireDate(strtotime($data['expire_date']));
            } elseif (is_int($data['expire_date'])) {
                $coupon->setExpireDate($data['expire_date']);
            } else {
                $coupon->setExpireDate(0);
            }
        }
        if (isset($data['usage_limit'])) {
            $coupon->setUsageLimit((int) $data['usage_limit']);
        }
        if (isset($data['auto_apply'])) {
            $coupon->setAutoApply((bool) $data['auto_apply']);
        }
    }

    /**
     * Get human-readable error message for validation error
     *
     * @param string $error Error code
     * @param Coupon $coupon
     * @return string
     */
    private function getErrorMessage(string $error, Coupon $coupon): string {
        switch ($error) {
            case Coupon::ERROR_EXPIRED:
                return __('This coupon has expired.', 'wpdm-premium-packages');

            case Coupon::ERROR_USAGE_LIMIT:
                return __('This coupon has reached its usage limit.', 'wpdm-premium-packages');

            case Coupon::ERROR_MIN_AMOUNT:
                return sprintf(
                    __('Minimum order amount for this coupon is %s.', 'wpdm-premium-packages'),
                    wpdmpp_currency_sign() . number_format($coupon->getMinOrderAmount(), 2)
                );

            case Coupon::ERROR_MAX_AMOUNT:
                return sprintf(
                    __('Maximum order amount for this coupon is %s.', 'wpdm-premium-packages'),
                    wpdmpp_currency_sign() . number_format($coupon->getMaxOrderAmount(), 2)
                );

            case Coupon::ERROR_PRODUCT_NOT_IN_CART:
                return __('This coupon is not valid for items in your cart.', 'wpdm-premium-packages');

            case Coupon::ERROR_EMAIL_NOT_ALLOWED:
                return __('This coupon is not valid for your email address.', 'wpdm-premium-packages');

            case Coupon::ERROR_NOT_FOUND:
            default:
                return __('Invalid coupon code.', 'wpdm-premium-packages');
        }
    }
}
