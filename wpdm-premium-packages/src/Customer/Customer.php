<?php
/**
 * Customer Entity Class
 *
 * Represents a customer who has made purchases in the system.
 * Customers are WordPress users with the 'wpdmpp_customer' role.
 *
 * @package WPDMPP\Customer
 * @since 7.0.0
 */

namespace WPDMPP\Customer;

defined('ABSPATH') || exit;

class Customer
{
    /**
     * Customer role name
     */
    public const ROLE = 'wpdmpp_customer';

    /**
     * Meta keys
     */
    public const META_TOTAL_SPENT = '__wpdmpp_total_spent';
    public const META_LAST_LOGIN = '__wpdm_last_login_time';

    /**
     * Customer ID (WordPress user ID)
     *
     * @var int
     */
    private int $id;

    /**
     * WordPress user object
     *
     * @var \WP_User|null
     */
    private ?\WP_User $user = null;

    /**
     * Customer email
     *
     * @var string
     */
    private string $email = '';

    /**
     * Customer first name
     *
     * @var string
     */
    private string $firstName = '';

    /**
     * Customer last name
     *
     * @var string
     */
    private string $lastName = '';

    /**
     * Customer display name
     *
     * @var string
     */
    private string $displayName = '';

    /**
     * Registration date
     *
     * @var string
     */
    private string $registeredDate = '';

    /**
     * Total spent
     *
     * @var float
     */
    private float $totalSpent = 0.0;

    /**
     * Last login timestamp
     *
     * @var int
     */
    private int $lastLogin = 0;

    /**
     * Customer orders cache
     *
     * @var array|null
     */
    private ?array $orders = null;

    /**
     * Constructor
     *
     * @param int|\WP_User|null $user User ID or WP_User object
     */
    public function __construct($user = null)
    {
        if ($user instanceof \WP_User) {
            $this->user = $user;
            $this->id = $user->ID;
            $this->hydrateFromUser($user);
        } elseif (is_numeric($user) && $user > 0) {
            $this->id = (int) $user;
            $this->loadUser();
        } else {
            $this->id = 0;
        }
    }

    /**
     * Create customer from user ID
     *
     * @param int $userId User ID
     * @return self
     */
    public static function fromId(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Create customer from WP_User object
     *
     * @param \WP_User $user WordPress user object
     * @return self
     */
    public static function fromUser(\WP_User $user): self
    {
        return new self($user);
    }

    /**
     * Create customer from email
     *
     * @param string $email Email address
     * @return self|null
     */
    public static function fromEmail(string $email): ?self
    {
        $user = get_user_by('email', $email);
        if (!$user) {
            return null;
        }
        return new self($user);
    }

    /**
     * Create customer from data array
     *
     * @param array $data Customer data
     * @return self
     */
    public static function create(array $data): self
    {
        $customer = new self();

        $customer->id = (int) ($data['id'] ?? $data['ID'] ?? 0);
        $customer->email = sanitize_email($data['email'] ?? '');
        $customer->firstName = sanitize_text_field($data['first_name'] ?? '');
        $customer->lastName = sanitize_text_field($data['last_name'] ?? '');
        $customer->displayName = sanitize_text_field($data['display_name'] ?? '');
        $customer->registeredDate = $data['registered_date'] ?? '';
        $customer->totalSpent = (float) ($data['total_spent'] ?? 0);
        $customer->lastLogin = (int) ($data['last_login'] ?? 0);

        return $customer;
    }

    /**
     * Load user from ID
     */
    private function loadUser(): void
    {
        if ($this->id <= 0) {
            return;
        }

        $this->user = get_user_by('id', $this->id);
        if ($this->user) {
            $this->hydrateFromUser($this->user);
        }
    }

    /**
     * Hydrate from WP_User object
     *
     * @param \WP_User $user WordPress user object
     */
    private function hydrateFromUser(\WP_User $user): void
    {
        $this->email = $user->user_email;
        $this->firstName = $user->first_name;
        $this->lastName = $user->last_name;
        $this->displayName = $user->display_name;
        $this->registeredDate = $user->user_registered;

        // Load meta
        $this->totalSpent = (float) get_user_meta($this->id, self::META_TOTAL_SPENT, true);
        $this->lastLogin = (int) get_user_meta($this->id, self::META_LAST_LOGIN, true);
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get WordPress user object
     *
     * @return \WP_User|null
     */
    public function getUser(): ?\WP_User
    {
        return $this->user;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get first name
     *
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * Get last name
     *
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Get full name
     *
     * @return string
     */
    public function getFullName(): string
    {
        $name = trim($this->firstName . ' ' . $this->lastName);
        return $name ?: $this->displayName;
    }

    /**
     * Get display name
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Get registration date
     *
     * @return string
     */
    public function getRegisteredDate(): string
    {
        return $this->registeredDate;
    }

    /**
     * Get registration timestamp
     *
     * @return int
     */
    public function getRegisteredTimestamp(): int
    {
        return $this->registeredDate ? strtotime($this->registeredDate) : 0;
    }

    /**
     * Get formatted registration date
     *
     * @param string $format Date format (default: WordPress date format)
     * @return string
     */
    public function getFormattedRegisteredDate(string $format = ''): string
    {
        if (!$this->registeredDate) {
            return '';
        }

        $format = $format ?: get_option('date_format');
        return date_i18n($format, strtotime($this->registeredDate));
    }

    /**
     * Get total spent
     *
     * @return float
     */
    public function getTotalSpent(): float
    {
        return $this->totalSpent;
    }

    /**
     * Get formatted total spent
     *
     * @return string
     */
    public function getFormattedTotalSpent(): string
    {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($this->totalSpent);
        }
        return number_format($this->totalSpent, 2);
    }

    /**
     * Set total spent
     *
     * @param float $amount Total amount
     * @return self
     */
    public function setTotalSpent(float $amount): self
    {
        $this->totalSpent = $amount;
        return $this;
    }

    /**
     * Get last login timestamp
     *
     * @return int
     */
    public function getLastLogin(): int
    {
        return $this->lastLogin;
    }

    /**
     * Get formatted last login
     *
     * @param string $format Date format (default: WordPress date + time format)
     * @return string
     */
    public function getFormattedLastLogin(string $format = ''): string
    {
        if (!$this->lastLogin) {
            return __('Never', 'wpdm-premium-packages');
        }

        $format = $format ?: get_option('date_format') . ' ' . get_option('time_format');
        return date_i18n($format, $this->lastLogin);
    }

    /**
     * Set last login
     *
     * @param int $timestamp Unix timestamp
     * @return self
     */
    public function setLastLogin(int $timestamp): self
    {
        $this->lastLogin = $timestamp;
        return $this;
    }

    /**
     * Get avatar URL
     *
     * @param int $size Avatar size in pixels
     * @return string
     */
    public function getAvatarUrl(int $size = 96): string
    {
        return get_avatar_url($this->id, ['size' => $size]);
    }

    /**
     * Get avatar HTML
     *
     * @param int $size Avatar size in pixels
     * @return string
     */
    public function getAvatar(int $size = 96): string
    {
        return get_avatar($this->id, $size);
    }

    /**
     * Check if user is a customer (has customer role)
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        if (!$this->user) {
            return false;
        }
        return in_array(self::ROLE, $this->user->roles, true);
    }

    /**
     * Check if this is a valid loaded customer
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->id > 0 && $this->user instanceof \WP_User;
    }

    /**
     * Add customer role to user
     *
     * @return bool
     */
    public function addCustomerRole(): bool
    {
        if (!$this->user) {
            return false;
        }

        if (!$this->isCustomer()) {
            $this->user->add_role(self::ROLE);
            return true;
        }

        return false;
    }

    /**
     * Remove customer role from user
     *
     * @return bool
     */
    public function removeCustomerRole(): bool
    {
        if (!$this->user) {
            return false;
        }

        if ($this->isCustomer()) {
            $this->user->remove_role(self::ROLE);
            return true;
        }

        return false;
    }

    /**
     * Get customer roles
     *
     * @return array
     */
    public function getRoles(): array
    {
        return $this->user ? $this->user->roles : [];
    }

    /**
     * Check if customer has capability
     *
     * @param string $capability Capability to check
     * @return bool
     */
    public function can(string $capability): bool
    {
        return $this->user ? $this->user->has_cap($capability) : false;
    }

    /**
     * Get customer profile URL (admin)
     *
     * @return string
     */
    public function getAdminProfileUrl(): string
    {
        return admin_url('admin.php?page=wpdmpp-customers&id=' . $this->id);
    }

    /**
     * Get customer edit URL (WordPress admin)
     *
     * @return string
     */
    public function getEditUrl(): string
    {
        return get_edit_user_link($this->id);
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'display_name' => $this->displayName,
            'registered_date' => $this->registeredDate,
            'registered_date_formatted' => $this->getFormattedRegisteredDate(),
            'total_spent' => $this->totalSpent,
            'total_spent_formatted' => $this->getFormattedTotalSpent(),
            'last_login' => $this->lastLogin,
            'last_login_formatted' => $this->getFormattedLastLogin(),
            'avatar_url' => $this->getAvatarUrl(),
            'is_customer' => $this->isCustomer(),
            'roles' => $this->getRoles(),
        ];
    }

    /**
     * Convert to array for API response
     *
     * @return array
     */
    public function toApiResponse(): array
    {
        $data = $this->toArray();
        $data['admin_profile_url'] = $this->getAdminProfileUrl();
        return $data;
    }

    /**
     * Convert to minimal array (for lists)
     *
     * @return array
     */
    public function toMinimalArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->getFullName(),
            'avatar_url' => $this->getAvatarUrl(48),
        ];
    }
}
