<?php
/**
 * User: shahnuralam
 * Date: 25/12/18
 * Time: 3:12 PM
 */

namespace WPDMPP\Libs;


use WPDM\__\__;
use WPDM\__\Crypt;
use WPDM\__\Template;
use WPDM\__\Session;
use WPDM\__\TempStorage;
use WPDM\__\UI;
use WPDMPP\Product;

class Cart {

	private $ID;
	private $cartData;
	private $product;
	private $items = null;

	function __construct() {
	}

	/**
	 * Get cart id
	 * @return null|string
	 */
	function getID() {
		$cart_id = null;
		if ( is_user_logged_in() ) {
			$cart_id = get_current_user_id() . "_cart";
		} else {
			$cart_id = Session::deviceID() . "_cart";
		}
		$this->ID = $cart_id;

		return $cart_id;
	}

	/**
	 * Get cart items
	 * @return array|mixed
	 */
	function getItems() {

		$cart_id = $this->getID();

		if ( is_array( $this->items ) && count( $this->items ) > 0 ) {
			return $this->items;
		}

		$cart_data = maybe_unserialize( get_option( $cart_id ) );

		//Transfer cart data from guest id to user id
		if ( is_user_logged_in() && ! $cart_data ) {
			$cart_id   = Session::deviceID() . "_cart";
			$cart_data = maybe_unserialize( get_option( $cart_id ) );
			delete_option( $cart_id );
			$cart_id = get_current_user_id() . "_cart";
			update_option( $cart_id, $cart_data, false );
		}

		$this->items = is_array( $cart_data ) ? $cart_data : [];

		return $this->items;

	}

	function gigsCost( $gigs ) {
		$gigs_cost_total = 0;
		foreach ( $gigs as $gig_id => $gig ) {
			$gigs_cost_total += $gig['option_price'];
		}

		return $gigs_cost_total;
	}

	/**
	 * @param $product_id
	 * @param $selected_gig_ids
	 *
	 * @return array
	 */
	private function processExtraGigs( $product_id, $selected_gig_ids ) {
		if ( ! is_array( $selected_gig_ids ) ) {
			return [ 'found_gigs' => [], 'gigs_cost_total' => 0 ];
		}
		$extra_gigs      = get_post_meta( $product_id, "__wpdm_variation", true );
		$found_gigs      = [];
		$gigs_cost_total = 0;
		foreach ( $extra_gigs as $gigs_group_id => $gigs_group ) {
			foreach ( $gigs_group as $gig_id => $gig ) {
				if ( in_array( $gig_id, $selected_gig_ids ) ) {
					$found_gigs[ $gig_id ] = $gig;
					$gigs_cost_total       += $gig['option_price'];
				}
			}
		}

		return [ 'found_gigs' => $found_gigs, 'gigs_cost_total' => (double) $gigs_cost_total ];
	}

	/**
	 * Add an item to cart
	 *
	 * @param $product_id
	 * @param string $license
	 * @param array $extras
	 *
	 * @return array|mixed
	 */
	function addItem( $product_id, $license = '', $extras = array() ) {
		$cart_id   = $this->getID();
		$cart_data = $this->getItems();

		if ( $this->isLocked() ) {
			return $cart_data;
		}

		$product = new Product( $product_id );

		$quantity = wpdm_valueof( $extras, 'quantity', [ 'validate' => 'int', 'default' => 1 ] );
		$quantity = $quantity > 0 ?: 1;

		if ( isset( $cart_data[ $product_id ] ) ) {
			if ( wpdm_query_var( 'custom_order', 'int' ) === 1 && wpdm_valueof( $cart_data[ $product_id ], 'license/id' ) === wpdm_query_var( 'license' ) ) {
				$quantity += (int) wpdm_valueof( $cart_data[ $product_id ], 'quantity' );
			}
			unset( $cart_data[ $product_id ] );
		}

		$files = [];

		$base_price = $product->getLicensePrice( $license );

		if ( ! $product->payAsYouWant() ) {

			$processed_gigs = $this->processExtraGigs( $product_id, wpdm_valueof( $extras, 'extra_gigs' ) );
			$gigs           = $processed_gigs['found_gigs'];

			$selected_files       = isset( $extras['files'] ) ? $extras['files'] : [];
			$selected_files       = ! is_array( $selected_files ) ? explode( ",", $selected_files ) : $selected_files;
			$selected_file_prices = $product->getFilePrices( $selected_files, $license );
			$files_price          = array_sum( $selected_file_prices );
			$files                = $selected_files;
			$base_price           = $files_price > 0 && $files_price < $base_price ? $files_price : $base_price;

			//Calculate Role Discount
			$gigs_cost             = $processed_gigs['gigs_cost_total'];
			$package_price         = $base_price + $gigs_cost;
			$role_discount_percent = $product->getRoleDiscount();
			$role_discount         = ( $package_price * $role_discount_percent / 100 );

		} else {

			//Condition for as you want to pay
			$iwantopay     = wpdm_valueof( $extras, 'iwantopay', [ 'validate' => 'double' ] );
			$base_price    = $iwantopay > $base_price ? $iwantopay : $base_price;
			$files         = [];
			$role_discount = 0;
			$gigs          = [];

		}

		$cart_item                = [
			'pid'             => $product_id,
			'product_type'    => '',
			'product_name'    => get_the_title( $product_id ),
			'quantity'        => $quantity,
			'extra_gigs'      => $gigs,
			'license'         => $product->getLicenseInfo( $license ),
			'price'           => $base_price,
			'role_discount'   => $role_discount,
			'coupon'          => '',
			'coupon_discount' => 0,
			'files'           => $files
		];
		$cart_item                = apply_filters( "wpdmpp_before_addtocart", $cart_item, $cart_id );
		$cart_data[ $product_id ] = $cart_item;
		update_option( $cart_id, $cart_data, false );
		//wpdmprecho('Add Item:'.$product_id);
		//wpdmprecho($cart_data);
		return $cart_data;

	}

	/**
	 * @param $product_id
	 * @param $name
	 * @param $price
	 * @param array $extras
	 *
	 * @return array|mixed
	 */
	function addDynamicItem( $product_id, $name, $price, $extras = array() ) {

		$cart_id = $this->getID();
		if ( ! isset( $extras['recurring'] ) ) {
			$cart_data = $this->getItems();
			if ( $this->isLocked() ) {
				return $cart_data;
			}
		} else {
			$cart_data = [];
			TempStorage::set( "__rec_{$cart_id}", wpdm_valueof( $extras, 'recurring', [ 'validate' => 'int' ] ) ? 'YES' : 'NO' );
			$this->lockCart();
		}

		if ( isset( $cart_data[ $product_id ] ) ) {
			unset( $cart_data[ $product_id ] );
		}

		$cart_data[ $product_id ] = [
			'pid'             => $product_id,
			'product_type'    => 'dynamic',
			'product_name'    => $name,
			'quantity'        => 1,
			'extra_gigs'      => [],
			'license'         => [],
			'price'           => $price,
			'role_discount'   => 0,
			'coupon'          => '',
			'coupon_discount' => 0,
			'files'           => [],
			'info'            => $extras
		];

		update_option( $cart_id, $cart_data, false );

		return $cart_data;

	}

	function isRecurring() {
		$dynamic_rec = TempStorage::get( "__rec_" . $this->getID() );
		$system_rec  = get_wpdmpp_option( 'auto_renew', 0, 'int' );

		//If recurring period is set then check if dynamically set cart recurring or one-time
		if ( $dynamic_rec === 'NO' ) {
			return false;
		}
		if ( $dynamic_rec === 'YES' ) {
			return true;
		}

		//Use system settings
		return $system_rec;
	}

	function updateItem( $product_id, $updates ) {
		$items = $this->getItems();
		if ( isset( $items[ $product_id ] ) ) {
			foreach ( $updates as $property => $value ) {
				switch ( $property ) {
					case 'quantity':
						$items[ $product_id ]['quantity'] = (int) $value < 1 ? 1 : (int) $value;
						break;
					case 'coupon':
						$discount = CouponCodes::validate_coupon( $value, $product_id );
						if ( $discount ) {
							$items[ $product_id ]['coupon']          = $value;
							$items[ $product_id ]['coupon_discount'] = $discount;
						} else {
							$items[ $product_id ]['coupon']          = '';
							$items[ $product_id ]['coupon_discount'] = 0;
						}
						break;
				}
			}
			$this->items = $items;
			//update_option($this->getID(), $items, false);
		}
	}

	function update() {
		update_option( $this->getID(), $this->getItems(), false );
	}

	function lockCart() {
		TempStorage::set( '__wpdm_cart_locked_' . $this->getID(), 1 );
	}

	function unlockCart() {
		TempStorage::kill( "__rec_" . $this->getID() );
		TempStorage::kill( '__wpdm_cart_locked_' . $this->getID() );
	}

	function isLocked() {
		return (int) TempStorage::get( '__wpdm_cart_locked_' . $this->getID() );
	}

	function applyCoupon( $code ) {
		$this->clearCoupon( $code );
		if ( ! $code ) {
			return;
		}
		$coupon   = CouponCodes::find( $code );
		$discount = CouponCodes::validate_coupon( $code );
		$disc     = $coupon->type === 'percent' ? $coupon->discount . '%' : wpdmpp_price_format( $coupon->discount );
		//$note = $coupon->product > 0 ? sprintf(__('This is a product specific coupon code. %s coupon discount has been applied on %s', WPDMPP_TEXT_DOMAIN), $disc, get_the_title($coupon->product)) : '';
		if ( $discount > 0 ) {
			$coupon_info = array( 'code'            => $code,
			                      'coupon'          => $code,
			                      'discount'        => $discount,
			                      'coupon_discount' => $discount,
			                      'product_id'      => $coupon->product,
			                      'note'            => wpdm_sanitize_var( $coupon->description, "kses" )
			);
			TempStorage::set( WPDMPP()->cart->getID() . "_coupon", $coupon_info );

			return $coupon_info;
		}

		return false;
	}

	/**
	 * @param $product_id
	 *
	 * @return array|mixed
	 */
	function removeItem( $product_id ) {
		$cart_id   = $this->getID();
		$cart_data = $this->getItems();
		if ( isset( $cart_data[ $product_id ] ) ) {
			unset( $cart_data[ $product_id ] );
		}
		if ( count( $cart_data ) > 0 ) {
			update_option( $cart_id, $cart_data, false );
		} else {
			$this->clear();
		}

		return $cart_data;

	}

	function getCoupon( $return = ARRAY_A ) {
		$cart_id     = $this->getID();
		$cart_coupon = TempStorage::get( $cart_id . "_coupon" );
		if ( ! isset( $cart_coupon['code'] ) || $cart_coupon['code'] == '' ) {
			$this->clearCoupon();
			$cart_coupon = null;

			return null;
		}

		return $return !== ARRAY_A ? wpdm_valueof( $cart_coupon, $return ) : $cart_coupon;
	}

	function clearCoupon( $new_code = '' ) {
		$cart_id = $this->getID();
		TempStorage::kill( $cart_id . "_coupon" );
		if ( $new_code ) {
			$cart_data = $this->getItems();
			foreach ( $cart_data as $pid => &$item ) {
				$item['coupon']          = '';
				$item['coupon_discount'] = 0;
				$item['coupon_amount']   = 0;
			}
			$this->items = $cart_data;
			update_option( $cart_id, $cart_data, false );
		}
	}

	function updateCart() {

	}

	function save() {
		$cartdata = $this->getItems();
		$cartinfo = array( 'cartitems' => $cartdata, 'coupon' => $this->getCoupon() );
		$cartinfo = Crypt::encrypt( $cartinfo );
		$id       = uniqid();
		file_put_contents( WPDM_CACHE_DIR . 'saved-cart-' . $id . '.txt', $cartinfo );
		Session::set( 'savedcartid', $id );

		return $id;
	}

	function loadSaved( $saved_cart_id ) {


		$cartfile        = WPDM_CACHE_DIR . '/saved-cart-' . $saved_cart_id . '.txt';
		$saved_cart_data = '';

		if ( file_exists( $cartfile ) ) {
			$saved_cart_data = file_get_contents( $cartfile );
		}
		$saved_cart_data = Crypt::decrypt( $saved_cart_data, true );

		$coupon_data = null;
		if ( is_array( $saved_cart_data ) && count( $saved_cart_data ) > 0 ) {
			//wpdmdd($saved_cart_data);
			if ( isset( $saved_cart_data['cartitems'] ) ) {
				$coupon_data     = $saved_cart_data['coupon'];
				$saved_cart_data = $saved_cart_data['cartitems'];

			}

			$cart_id = $this->getID();
			update_option( $cart_id, $saved_cart_data, false );

			if ( $coupon_data ) {
				$cart_id = wpdmpp_cart_id();
				update_option( $cart_id . "_coupon", $coupon_data );
			}
		}

		return $saved_cart_data;

	}

	function getCartPrice() {
		$cart_items = $this->getItems();
		$total      = 0;
		if ( is_array( $cart_items ) ) {
			foreach ( $cart_items as $pid => $item ) {
				if ( isset( $item['item'] ) ) {
					//Deprecated
					foreach ( $item['item'] as $key => $val ) {
						$role_discount   = wpdm_valueof( $val, 'discount_amount', [ 'validate' => 'int' ] );
						$coupon_discount = wpdm_valueof( $val, 'coupon_amount', [ 'validate' => 'int' ] );
						$item_price = (double)wpdm_valueof($item, 'price', 0);
						$item_prices = (double)wpdm_valueof($val, 'prices', 0);
						//$total += (($item['price'] + $val['prices'] - $role_discount - $coupon_discount)*$item['quantity']);
						$total += ( ( $item_price + $item_prices - $role_discount ) * $val['quantity'] - $coupon_discount );
					}
				} else {
					$role_discount   = wpdm_valueof( $item, 'discount_amount', [ 'validate' => 'int' ] );
					$coupon_discount = wpdm_valueof( $item, 'coupon_amount', [ 'validate' => 'int' ] );
					$item_price = (double)wpdm_valueof($item, 'price', 0);
					$item_prices = (double)wpdm_valueof($item, 'prices', 0);
					$quantity = (int)wpdm_valueof($item, 'quantity', 0);
					//$total += (($item['price'] + $item['prices'] - $role_discount - $coupon_discount)*$item['quantity']);
					$total += ( ( $item_price + $item_prices - $role_discount ) * $quantity - $coupon_discount );
				}
			}
		}

		$total = apply_filters( 'wpdmpp_cart_price', $total );

		return number_format( $total, 2, ".", "" );
	}

	function itemLink( $item, $echo = true ) {
		if ( wpdm_valueof( $item, 'product_type' ) === 'dynamic' ) {
			$item_link = "<strong class='ttip wpdm-product-name' title='" . esc_attr__( 'Dynamic Product', WPDMPP_TEXT_DOMAIN ) . "'>{$item['product_name']}</strong>";
		} else {
			$item_link = '<a target=_blank class="d-block wpdm-product-name" href="' . get_permalink( $item['pid'] ) . '">' . get_the_title( $item['pid'] ) . '</a>';
		}
		if ( ! $echo ) {
			return $item_link;
		}
		echo $item_link;
	}

	function itemThumb( $item, $echo = true, $attrs = [] ) {
		$attrs['class'] = wpdm_valueof( $attrs, 'ckass' ) . " wpdm-cart-thumb";
		if ( wpdm_valueof( $item, 'product_type' ) === 'dynamic' ) {
			$image = wpdm_valueof( $item, 'info/image', [ 'default' => WPDM_BASE_URL . 'assets/images/wpdm.svg' ] );
			$thum  = UI::img( $image, $item['product_name'], $attrs );
		} else {
			$attrs['alt'] = get_the_title( $item['pid'] ) . ' Thumb';
			$thum         = wpdm_thumb( (int) $item['pid'], array( 96, 96 ), false, $attrs );
		}

		if ( ! $echo ) {
			return $thum;
		}
		echo $thum;
	}

	/**
	 * @param $item
	 * @param bool $echo
	 *
	 * @return mixed|void
	 */
	function itemInfo( $item, $echo = true ) {
		$gigs      = wpdm_valueof( $item, 'extra_gigs' );
		$gigs      = maybe_unserialize( $gigs );
		$gig_intro = [];
		if ( is_array( $gigs ) ) {
			foreach ( $gigs as $gig_id => $gig ) {
				$gig_intro[] = wpdm_valueof( $gig, 'option_name' ) . ": <strong>+" . wpdmpp_price_format( wpdm_valueof( $gig, 'option_price' ), true, true ) . "</strong>";
			}
		}
		$gig_intro = count( $gig_intro ) > 0 ? UI::div( implode( ", ", $gig_intro ), "text-info text-small" ) : '';

		$license        = wpdm_valueof( $item, 'license', [ 'default' => [] ] );
		$license        = maybe_unserialize( $license );
		$license        = isset( $license['info'], $license['info']['name'] ) ? UI::div( sprintf( __( "%s License", WPDMPP_TEXT_DOMAIN ), $license['info']['name'] ), "color-purple text-small ttip", [ 'title' => esc_html( $license['info']['description'] ) ] ) : '';
		$desc           = UI::div( wpdm_valueof( $item, 'info/desc', esc_attr__( 'Dynamic Product', WPDMPP_TEXT_DOMAIN ) ), "color-purple text-small ttip" );
		$license        = $license || wpdm_valueof( $item, 'product_type' ) !== 'dynamic' ? $license : $desc;
		$cart_item_info = $license . $gig_intro;
		$cart_item_info = apply_filters( "wpdmpp_cart_item_info", $cart_item_info, $item );

		if ( ! $echo ) {
			return $cart_item_info;
		}
		echo $cart_item_info;
	}

	function getRoleDiscount( $item ) {
		return 0;
	}

	/**
	 * @param $item
	 * @param false $format
	 *
	 * @return double|string
	 */
	function itemCost( $item, $format = false ) {
		$gigs_cost             = $this->gigsCost( $item['extra_gigs'] );
		$cost                  = $item['price'] + $gigs_cost;
		$cost                  = $cost * $item['quantity'];
		$product               = new Product( $item['pid'], $item['product_type'] );
		$role_discount_percent = $product->getRoleDiscount();
		$cost                  -= ( $cost * $role_discount_percent / 100 );
		$cost                  -= $item['coupon_discount'];

		return wpdmpp_price_format( $cost, $format, $format );
	}

	/**
	 * @param false $tax
	 * @param false $format
	 *
	 * @return double|string
	 */
	function cartTotal( $discount = false, $tax = false, $format = false ) {
		$cart_items = $this->getItems();
		$cat_total  = 0;
		foreach ( $cart_items as $item ) {
			$cat_total += $this->itemCost( $item );
		}
		if ( $discount ) {
			$discount  = $this->couponDiscount();
			$cat_total -= $discount;
		}
		if ( $tax && $cat_total > 0 ) {
			$tax       = $this->getTax();
			$cat_total += $tax;
		}

		return wpdmpp_price_format( $cat_total, $format, $format );
	}

	function couponDiscount() {
		$coupon = TempStorage::get( $this->getID() . "_coupon" );

		return wpdm_valueof( $coupon, 'discount', [ 'validate' => 'double' ] );
	}

	function calculateTax( $amount, $country, $state, $save = true ) {
		$tax_total = 0;
		if ( get_wpdmpp_option( 'tax/enable', 0, 'int' ) ) {
			$rate      = wpdmpp_tax_rate( $country, $state );
			$tax_total = ( ( $amount * $rate ) / 100 );
		}
		if ( $save ) {
			TempStorage::set( $this->getID() . '_tax', $tax_total );
		}

		return $tax_total;
	}

	function getTax() {
		return (double) TempStorage::get( $this->getID() . '_tax' );
	}

	/**
	 * Shortcode function for [wpdmpp_cart], Shows Premium Package Cart
	 * @return false|string
	 */
	function render() {
		global $wpdb;
		wpdmpp_calculate_discount();
		$cart_data      = $this->getItems();
		$login_html     = "";
		$payment_html   = "";
		$settings       = get_option( '_wpdmpp_settings' );
		$guest_checkout = ( isset( $settings['guest_checkout'] ) && $settings['guest_checkout'] == 1 ) ? 1 : 0;
		$cart_id        = wpdmpp_cart_id();

		$cart_subtotal = $this->getCartPrice();

		$cart_coupon = $this->getCoupon();

		if ( ! __::valueof( $cart_coupon, 'code' ) ) {
			$cart_coupon = WPDMPP()->couponCodes->auto_coupon( $cart_subtotal );
		}

		$cart_total          = wpdmpp_get_cart_total();
		$cart_tax            = wpdmpp_get_cart_tax();
		$cart_total_with_tax = number_format( array_sum( [ $cart_total, $cart_tax ] ), 2, '.', '' );

		$cart_coupon_discount = isset( $cart_coupon['discount'] ) ? number_format( $cart_coupon['discount'], 2, '.', '' ) : 0.00;

		$Template = new Template();
		$Template->assign( 'guest_checkout', $guest_checkout );
		$Template->assign( 'cart_data', $cart_data );
		$Template->assign( 'cart_subtotal', $cart_subtotal );
		$Template->assign( 'cart_total', $cart_total );
		$Template->assign( 'cart_tax', $cart_tax );
		$Template->assign( 'cart_total_with_tax', $cart_total_with_tax );
		$Template->assign( 'cart_coupon', $cart_coupon );
		$Template->assign( 'settings', $settings );
		$Template->assign( 'cart_coupon_discount', $cart_coupon_discount );

		$checkout_page = get_wpdmpp_option( 'checkout_page_style', '-2col', 'username' );

		return $Template->fetch( "checkout-cart{$checkout_page}/cart.php", WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK );
	}

	function clear() {
		$cart_id = $this->getID();
		$this->unlockCart();
		delete_option( $cart_id );
		TempStorage::kill( $cart_id . "_coupon" );
		$this->items = null;
		if ( Session::get( 'orderid' ) ) {
			Session::set( 'last_order', Session::get( 'orderid' ) );
			Session::clear( 'orderid' );
			Session::clear( 'tax' );
			Session::clear( 'subtotal' );
		}
	}

	function isEmpty() {
		$cart_data = WPDMPP()->cart->getItems();

		return count( $cart_data ) === 0;
	}

	function onUserLogin( $user_login, $user ) {
		$user_cart_id  = $user->ID . "_cart";
		$guest_cart_id = Session::deviceID() . "_cart";
		$cart_data     = maybe_unserialize( get_option( $guest_cart_id ) );
		update_option( $user_cart_id, $cart_data, false );
		delete_option( $guest_cart_id );

		User::processActiveRoles( $user->ID );

	}

	/**
	 * Delete all carts
	 * @return void
	 */
	function clearAll() {
		global $wpdb;
		$this->clear();
		$wpdb->query( "DELETE FROM `{$wpdb->prefix}_options` WHERE `option_name` LIKE '%_cart'" );
		$wpdb->query( "DELETE FROM `{$wpdb->prefix}_options` WHERE `option_name` LIKE '%_coupon'" );
	}

}
