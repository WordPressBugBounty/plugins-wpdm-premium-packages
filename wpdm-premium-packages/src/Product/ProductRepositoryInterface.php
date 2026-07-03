<?php
/**
 * Product Repository Interface
 *
 * Defines the contract for product data persistence operations.
 * Products are stored as post meta on wpdmpro post type.
 *
 * @package WPDMPP\Product
 * @since 7.0.0
 */

namespace WPDMPP\Product;

defined('ABSPATH') || exit;

interface ProductRepositoryInterface {

    /**
     * Find a product by its ID
     *
     * @param int $id Product ID
     * @return Product|null
     */
    public function findById(int $id): ?Product;

    /**
     * Find a product by its product code
     *
     * @param string $code Product code
     * @return Product|null
     */
    public function findByCode(string $code): ?Product;

    /**
     * Save product pricing data
     *
     * @param Product $product Product entity
     * @return bool
     */
    public function save(Product $product): bool;

    /**
     * Delete product pricing data (reset to free)
     *
     * @param int $id Product ID
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get all premium products
     *
     * @param array $args Query arguments
     * @return Product[]
     */
    public function findAllPremium(array $args = []): array;

    /**
     * Get products with active sales
     *
     * @param array $args Query arguments
     * @return Product[]
     */
    public function findOnSale(array $args = []): array;

    /**
     * Get products by license type
     *
     * @param string $licenseId License type ID
     * @param array  $args      Query arguments
     * @return Product[]
     */
    public function findByLicenseType(string $licenseId, array $args = []): array;

    /**
     * Get products with price in range
     *
     * @param float $minPrice Minimum price
     * @param float $maxPrice Maximum price
     * @param array $args     Query arguments
     * @return Product[]
     */
    public function findByPriceRange(float $minPrice, float $maxPrice, array $args = []): array;

    /**
     * Count total premium products
     *
     * @return int
     */
    public function countPremium(): int;

    /**
     * Count products on sale
     *
     * @return int
     */
    public function countOnSale(): int;

    /**
     * Check if a product exists
     *
     * @param int $id Product ID
     * @return bool
     */
    public function exists(int $id): bool;

    /**
     * Get products by category
     *
     * @param int|string $category Category ID or slug
     * @param array      $args     Query arguments
     * @return Product[]
     */
    public function findByCategory($category, array $args = []): array;

    /**
     * Search products
     *
     * @param string $keyword Search keyword
     * @param array  $args    Query arguments
     * @return Product[]
     */
    public function search(string $keyword, array $args = []): array;

    /**
     * Get recently added premium products
     *
     * @param int   $limit Number of products
     * @param array $args  Query arguments
     * @return Product[]
     */
    public function findRecent(int $limit = 10, array $args = []): array;

    /**
     * Get best selling products
     *
     * @param int   $limit Number of products
     * @param array $args  Query arguments
     * @return Product[]
     */
    public function findBestSelling(int $limit = 10, array $args = []): array;
}
