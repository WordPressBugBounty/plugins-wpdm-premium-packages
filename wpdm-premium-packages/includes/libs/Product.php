<?php

namespace WPDMPP;

class Product
{
    private $ID;
    private $type;
    private $customerRole = -1;
    private $basePrice = -1;
    private $salesPrice = -1;
    private $payAsYouWant = null;
    public $licenseEnabled = null;
    public $licenses = [];
    public $extraGigs = [];
    public $roleDiscount = null;
    public $discountedRole = null;

    function __construct($ID, $type = '')
    {
        $this->ID = $ID;
        $this->type = $type;
    }

	function getName($ID = null)
	{
		$_ID = $ID ?: $this->ID;
		return get_the_title($_ID);
	}

    function basePrice()
    {
	    $this->salesPrice();
        $this->basePrice = $this->basePrice > 0 ? $this->basePrice : (double)get_post_meta($this->ID, '__wpdm_base_price', true);
        return $this->salesPrice > 0 ? $this->salesPrice : $this->basePrice;
    }

	function salesPrice($pid = null)
	{
		if(!$pid && $this->salesPrice > 0) return $this->salesPrice;
		$pid = $pid ?: $this->ID;
		$sales_price = get_post_meta($pid, "__wpdm_sales_price", true);
		$sales_price_expire = get_post_meta($pid, "__wpdm_sales_price_expire", true);
		if ($sales_price_expire != '') {
			$sales_price_expire = strtotime($sales_price_expire);
			if (time() > $sales_price_expire && $sales_price_expire > 0) $sales_price = 0;
		}
		$this->salesPrice = number_format((double)$sales_price, 2, ".", "");
		return $this->salesPrice;
	}

    function payAsYouWant()
    {
        $this->payAsYouWant = $this->payAsYouWant !== null ? $this->payAsYouWant : (int)get_post_meta($this->ID, '__wpdm_pay_as_you_want', true);
        return $this->payAsYouWant;
    }

    function getActiveLicenses()
    {
        $license_prices = get_post_meta($this->ID, "__wpdm_license", true);
        $license_prices = maybe_unserialize($license_prices);
         ;
        $this->licenseEnabled = $this->licenseEnabled !== null ? $this->licenseEnabled : (int)get_post_meta($this->ID, "__wpdm_enable_license", true);
        $active_license = [];
        $all_licenses = wpdmpp_get_licenses();
        if(is_array($license_prices)) {
            foreach ($license_prices as $license_id => $licnese_info) {
                if ((int)$licnese_info['active'] === 1) {
                    $licnese_price = isset($licnese_info['price']) ? $licnese_info['price'] : $this->basePrice();
                    $license_info = $all_licenses[$license_id];
                    $active_license[$license_id] = ['id' => $license_id, 'price' => $licnese_price, 'info' => $license_info];
                }
            }
        }

        return $this->licenseEnabled ? $active_license : [];

    }

    function getFilePrices($file_ids, $license = '')
    {
        $fileinfo = get_post_meta($this->ID, '__wpdm_fileinfo', true);
        $fileinfo = maybe_unserialize($fileinfo);
        $this->licenseEnabled = $this->licenseEnabled !== null ? $this->licenseEnabled : (int)get_post_meta($this->ID, "__wpdm_enable_license", true);
        $file_prices = [];
        if (count($file_ids) > 0 && $file_ids[0] != '' && is_array($fileinfo)) {
            foreach ($file_ids as $findx) {
                $file_prices[$findx] = $fileinfo[$findx]['price'];
                if ($this->licenseEnabled === 1 && $license != '' && $fileinfo[$findx]['license_price'][$license] > 0) {
                    $file_prices[$findx] = wpdm_valueof($fileinfo, "{$findx}/license_price/{$license}");
                }
            }
        }
        return $file_prices;
    }

    function getLicenseInfo($license)
    {
        $licenses = $this->getActiveLicenses();
        $license_info = wpdm_valueof($licenses, $license);
        if(!is_array($license_info)) $license_info = [];
        return $license_info;
    }

    function getLicensePrice($license)
    {
        $licenses = $this->getActiveLicenses();
        $price = wpdm_valueof($licenses, "{$license}/price");
        return $price ?: $this->basePrice();

    }

    function getExtraGigs()
    {
        $allGigs = get_post_meta($this->ID,"__wpdm_variation",true);

    }

    function gigsCost($gigs)
    {
        $gigs = maybe_unserialize($gigs);
        if(!is_array($gigs)) return 0;
        $gigs_cost_total = 0;
        foreach ($gigs as $gig_id => $gig) {
            $gigs_cost_total += $gig['option_price'];
        }
        return $gigs_cost_total;
    }

    /**
     * @param false $name
     * @return array|int|mixed|string
     */
    function getRoleDiscount($name = false)
    {

        if($this->type === 'dynamic') {
            return $name ? [ 'role' => '', 'discount' => 0 ] : 0;
        }

        if($this->roleDiscount === null) {
            global $wp_roles;
            $role_discount = 0;
            $role_name = '';

            $discount = maybe_unserialize(get_post_meta($this->ID, '__wpdm_discount', true));

            if (!is_array($discount) || count($discount) == 0) return $name ? '' : 0;

            $roles = $wp_roles->role_names;

            if (is_user_logged_in() && is_array($discount)) {
                $current_user = wp_get_current_user();
                foreach ($current_user->roles as $role) {
                    if (isset($discount[$role]) && $discount[$role] > $role_discount) {
                        $role_discount = $discount[$role];
                        $role_name = isset($roles[$role]) ? $roles[$role] : $role;
                    }
                }
            }
            if (!is_user_logged_in() && is_array($discount) && isset($discount['guest'])) $role_discount = $discount['guest'];

            $this->roleDiscount = (double)$role_discount;
            $this->discountedRole = $role_name;

        }

        return $name ? [ 'role' => $this->discountedRole, 'discount' => (double)$this->roleDiscount ] : (double)$this->roleDiscount;
    }

    function customerRole()
    {
        $this->customerRole = $this->customerRole !== -1 ? $this->customerRole : get_post_meta($this->ID, '__wpdm_assign_role', true);
        return $this->customerRole;
    }

    function assignRole($customer)
    {
        $role = $this->customerRole();
        if(!$role) return;
        $user = new \WP_User($customer);
        $user->add_role($role);

    }
    function removeRole($customer)
    {
        $role = $this->customerRole();
        if(!$role) return;
        $user = new \WP_User($customer);
        $user->remove_role($role);
    }
}
