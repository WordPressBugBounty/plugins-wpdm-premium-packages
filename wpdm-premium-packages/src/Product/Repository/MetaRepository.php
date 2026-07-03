<?php
/**
 * Product Meta Repository
 *
 * Implements product data persistence using WordPress post meta.
 * Products are stored as metadata on wpdmpro post type.
 *
 * @package WPDMPP\Product\Repository
 * @since 7.0.0
 */

namespace WPDMPP\Product\Repository;

use WPDMPP\Product\Product;
use WPDMPP\Product\ProductRepositoryInterface;

defined('ABSPATH') || exit;

class MetaRepository implements ProductRepositoryInterface {

    /**
     * Cache of loaded products
     *
     * @var array
     */
    private array $cache = [];

    /**
     * Find a product by its ID
     *
     * @param int $id Product ID
     * @return Product|null
     */
    public function findById(int $id): ?Product {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'wpdmpro') {
            return null;
        }

        $product = new Product($id);
        if (!$product->isLoaded()) {
            return null;
        }

        $this->cache[$id] = $product;
        return $product;
    }

    /**
     * Find a product by its product code
     *
     * @param string $code Product code
     * @return Product|null
     */
    public function findByCode(string $code): ?Product {
        global $wpdb;

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '__wpdm_product_code'
             AND meta_value = %s
             LIMIT 1",
            $code
        ));

        if (!$id) {
            return null;
        }

        return $this->findById((int) $id);
    }

    /**
     * Save product pricing data
     *
     * @param Product $product Product entity
     * @return bool
     */
    public function save(Product $product): bool {
        $id = $product->getId();
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'wpdmpro') {
            return false;
        }

        $meta = $product->toMeta();

        foreach ($meta as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        // Update cache
        $this->cache[$id] = $product;

        return true;
    }

    /**
     * Delete product pricing data (reset to free)
     *
     * @param int $id Product ID
     * @return bool
     */
    public function delete(int $id): bool {
        $metaKeys = [
            '__wpdm_product_code',
            '__wpdm_base_price',
            '__wpdm_sales_price',
            '__wpdm_sales_price_expire',
            '__wpdm_pay_as_you_want',
            '__wpdm_price_variation',
            '__wpdm_variation',
            '__wpdm_enable_license',
            '__wpdm_license',
            '__wpdm_enable_license_key',
            '__wpdm_discount',
            '__wpdm_assign_role',
            '__wpdm_free_downloads',
        ];

        foreach ($metaKeys as $key) {
            delete_post_meta($id, $key);
        }

        unset($this->cache[$id]);

        return true;
    }

    /**
     * Get all premium products
     *
     * @param array $args Query arguments
     * @return Product[]
     */
    public function findAllPremium(array $args = []): array {
        $defaults = [
            'post_type' => 'wpdmpro',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '__wpdm_base_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
        ];

        $args = $this->mergeArgs($defaults, $args);
        return $this->executeQuery($args);
    }

    /**
     * Get products with active sales
     *
     * @param array $args Query arguments
     * @return Product[]
     */
    public function findOnSale(array $args = []): array {
        global $wpdb;

        // Get products with sales price > 0 and not expired
        $now = time();
        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id
                 AND pm_sale.meta_key = '__wpdm_sales_price'
             LEFT JOIN {$wpdb->postmeta} pm_expire ON p.ID = pm_expire.post_id
                 AND pm_expire.meta_key = '__wpdm_sales_price_expire'
             WHERE p.post_type = 'wpdmpro'
             AND p.post_status = 'publish'
             AND CAST(pm_sale.meta_value AS DECIMAL(10,2)) > 0
             AND (
                 pm_expire.meta_value IS NULL
                 OR pm_expire.meta_value = ''
                 OR CAST(pm_expire.meta_value AS UNSIGNED) = 0
                 OR CAST(pm_expire.meta_value AS UNSIGNED) > %d
             )",
            $now
        );

        $limit = $args['posts_per_page'] ?? -1;
        $offset = $args['offset'] ?? 0;

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        $ids = $wpdb->get_col($sql);

        $products = [];
        foreach ($ids as $id) {
            $product = $this->findById((int) $id);
            if ($product && $product->isSaleActive()) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * Get products by license type
     *
     * @param string $licenseId License type ID
     * @param array  $args      Query arguments
     * @return Product[]
     */
    public function findByLicenseType(string $licenseId, array $args = []): array {
        global $wpdb;

        // Find products where license is enabled and has the specific license type
        $sql = "SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_enabled ON p.ID = pm_enabled.post_id
                    AND pm_enabled.meta_key = '__wpdm_enable_license'
                    AND pm_enabled.meta_value = '1'
                INNER JOIN {$wpdb->postmeta} pm_license ON p.ID = pm_license.post_id
                    AND pm_license.meta_key = '__wpdm_license'
                WHERE p.post_type = 'wpdmpro'
                AND p.post_status = 'publish'";

        $ids = $wpdb->get_col($sql);

        $products = [];
        foreach ($ids as $id) {
            $product = $this->findById((int) $id);
            if ($product) {
                $licenses = $product->getLicenses();
                if (isset($licenses[$licenseId])) {
                    $products[] = $product;
                }
            }
        }

        // Apply limit
        $limit = $args['posts_per_page'] ?? -1;
        if ($limit > 0) {
            $products = array_slice($products, 0, $limit);
        }

        return $products;
    }

    /**
     * Get products with price in range
     *
     * @param float $minPrice Minimum price
     * @param float $maxPrice Maximum price
     * @param array $args     Query arguments
     * @return Product[]
     */
    public function findByPriceRange(float $minPrice, float $maxPrice, array $args = []): array {
        $defaults = [
            'post_type' => 'wpdmpro',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '__wpdm_base_price',
                    'value' => $minPrice,
                    'compare' => '>=',
                    'type' => 'DECIMAL(10,2)',
                ],
                [
                    'key' => '__wpdm_base_price',
                    'value' => $maxPrice,
                    'compare' => '<=',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
        ];

        $args = $this->mergeArgs($defaults, $args);
        return $this->executeQuery($args);
    }

    /**
     * Count total premium products
     *
     * @return int
     */
    public function countPremium(): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 AND pm.meta_key = '__wpdm_base_price'
             WHERE p.post_type = 'wpdmpro'
             AND p.post_status = 'publish'
             AND CAST(pm.meta_value AS DECIMAL(10,2)) > 0"
        );
    }

    /**
     * Count products on sale
     *
     * @return int
     */
    public function countOnSale(): int {
        global $wpdb;

        $now = time();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id
                 AND pm_sale.meta_key = '__wpdm_sales_price'
             LEFT JOIN {$wpdb->postmeta} pm_expire ON p.ID = pm_expire.post_id
                 AND pm_expire.meta_key = '__wpdm_sales_price_expire'
             WHERE p.post_type = 'wpdmpro'
             AND p.post_status = 'publish'
             AND CAST(pm_sale.meta_value AS DECIMAL(10,2)) > 0
             AND (
                 pm_expire.meta_value IS NULL
                 OR pm_expire.meta_value = ''
                 OR CAST(pm_expire.meta_value AS UNSIGNED) = 0
                 OR CAST(pm_expire.meta_value AS UNSIGNED) > %d
             )",
            $now
        ));
    }

    /**
     * Check if a product exists
     *
     * @param int $id Product ID
     * @return bool
     */
    public function exists(int $id): bool {
        $post = get_post($id);
        return $post && $post->post_type === 'wpdmpro';
    }

    /**
     * Get products by category
     *
     * @param int|string $category Category ID or slug
     * @param array      $args     Query arguments
     * @return Product[]
     */
    public function findByCategory($category, array $args = []): array {
        $defaults = [
            'post_type' => 'wpdmpro',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '__wpdm_base_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
            'tax_query' => [
                [
                    'taxonomy' => 'wpdmcategory',
                    'field' => is_numeric($category) ? 'term_id' : 'slug',
                    'terms' => $category,
                ],
            ],
        ];

        $args = $this->mergeArgs($defaults, $args);
        return $this->executeQuery($args);
    }

    /**
     * Search products
     *
     * @param string $keyword Search keyword
     * @param array  $args    Query arguments
     * @return Product[]
     */
    public function search(string $keyword, array $args = []): array {
        $defaults = [
            'post_type' => 'wpdmpro',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => $keyword,
            'meta_query' => [
                [
                    'key' => '__wpdm_base_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
        ];

        $args = $this->mergeArgs($defaults, $args);
        return $this->executeQuery($args);
    }

    /**
     * Get recently added premium products
     *
     * @param int   $limit Number of products
     * @param array $args  Query arguments
     * @return Product[]
     */
    public function findRecent(int $limit = 10, array $args = []): array {
        $defaults = [
            'post_type' => 'wpdmpro',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '__wpdm_base_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
        ];

        $args = $this->mergeArgs($defaults, $args);
        return $this->executeQuery($args);
    }

    /**
     * Get best selling products
     *
     * @param int   $limit Number of products
     * @param array $args  Query arguments
     * @return Product[]
     */
    public function findBestSelling(int $limit = 10, array $args = []): array {
        global $wpdb;

        // Get product IDs sorted by total sales from order items
        $sql = $wpdb->prepare(
            "SELECT oi.pid, SUM(oi.quantity) as total_sold
             FROM {$wpdb->prefix}ahm_order_items oi
             INNER JOIN {$wpdb->prefix}ahm_orders o ON oi.oid = o.order_id
             INNER JOIN {$wpdb->posts} p ON oi.pid = p.ID
             WHERE o.payment_status = 'Completed'
             AND p.post_type = 'wpdmpro'
             AND p.post_status = 'publish'
             GROUP BY oi.pid
             ORDER BY total_sold DESC
             LIMIT %d",
            $limit
        );

        $results = $wpdb->get_results($sql);

        $products = [];
        foreach ($results as $row) {
            $product = $this->findById((int) $row->pid);
            if ($product && $product->isPremium()) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * Execute WP_Query and return Product objects
     *
     * @param array $args Query arguments
     * @return Product[]
     */
    private function executeQuery(array $args): array {
        $query = new \WP_Query($args);
        $posts = $query->get_posts();

        // Prime meta cache for better performance
        if (!empty($posts)) {
            $ids = wp_list_pluck($posts, 'ID');
            update_postmeta_cache($ids);
        }

        $products = [];
        foreach ($posts as $post) {
            $product = $this->findById($post->ID);
            if ($product) {
                $products[] = $product;
            }
        }

        wp_reset_postdata();

        return $products;
    }

    /**
     * Merge query arguments with defaults
     *
     * @param array $defaults Default arguments
     * @param array $args     User arguments
     * @return array
     */
    private function mergeArgs(array $defaults, array $args): array {
        // Handle meta_query merging specially
        if (isset($args['meta_query']) && isset($defaults['meta_query'])) {
            $args['meta_query'] = array_merge($defaults['meta_query'], $args['meta_query']);
        }

        // Handle tax_query merging specially
        if (isset($args['tax_query']) && isset($defaults['tax_query'])) {
            $args['tax_query'] = array_merge($defaults['tax_query'], $args['tax_query']);
        }

        return array_merge($defaults, $args);
    }

    /**
     * Clear the internal cache
     *
     * @return void
     */
    public function clearCache(): void {
        $this->cache = [];
    }
}
