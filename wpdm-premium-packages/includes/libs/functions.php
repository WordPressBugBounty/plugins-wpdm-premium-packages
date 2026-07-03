<?php
// Exit if accessed directly
use WPDM\__\Messages;
use WPDM\__\Query;
use WPDM\__\Session;
use WPDMPP\WPDMPremiumPackage;
use WPDMPP\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get legacy WPDMPremiumPackage instance
 *
 * @return WPDMPremiumPackage
 */
function WPDMPP() {
    global $wpdmpp;

    return $wpdmpp;
}

/**
 * Get new Plugin instance (v7.0.0+ architecture)
 *
 * Provides access to the new PSR-4 service architecture:
 * - wpdmpp_plugin()->cart()     - CartService
 * - wpdmpp_plugin()->order()    - OrderService
 * - wpdmpp_plugin()->coupon()   - CouponService
 * - wpdmpp_plugin()->license()  - LicenseService
 * - wpdmpp_plugin()->product()  - ProductService
 * - wpdmpp_plugin()->customer() - CustomerService
 * - wpdmpp_plugin()->invoice()  - InvoiceService
 * - wpdmpp_plugin()->payment()  - PaymentService
 * - wpdmpp_plugin()->dashboard() - DashboardService (admin only)
 *
 * @since 7.0.0
 * @return Plugin|null
 */
function wpdmpp_plugin(): ?Plugin {
    if (class_exists('\WPDMPP\Core\Plugin')) {
        return Plugin::instance();
    }
    return null;
}

//number of total sales
function wpdmpp_total_purchase( $pid = '' ) {
    global $wpdb;
    if ( ! $pid ) {
        $pid = get_the_ID();
    }
    $pid = (int) $pid;

    $sales = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_orders o
         INNER JOIN {$wpdb->prefix}ahm_order_items oi ON oi.oid = o.order_id
         WHERE oi.pid = %d AND o.payment_status IN ('Completed', 'Expired')",
            $pid
    ) );

    return $sales;
}


//number of total sales
function wpdmpp_total_sales( $uid = '', $pid = '', $sdate = '', $edate = '' ) {
    global $wpdb;

    $pid = (int) $pid;
    $uid = (int) $uid;

    $sdate    = $sdate == '' ? wp_date( "Y-m-01" ) : $sdate;
    $edate    = $edate == '' ? wp_date( "Y-m-d", strtotime( "last day of this month" ) ) : $edate;
    $sdate_ts = strtotime( $sdate );
    $edate_ts = strtotime( $edate );

    if ( $pid > 0 || $uid > 0 ) {
        $conditions = [ "oi.oid = o.order_id", "o.payment_status IN ('Completed', 'Expired')" ];
        $params     = [];

        if ( $pid > 0 ) {
            $conditions[] = "oi.pid = %d";
            $params[]     = $pid;
        }
        if ( $uid > 0 ) {
            $conditions[] = "oi.sid = %d";
            $params[]     = $uid;
        }
        $conditions[] = "o.date >= %d";
        $params[]     = $sdate_ts;
        $conditions[] = "o.date <= %d";
        $params[]     = $edate_ts;

        $where = implode( ' AND ', $conditions );
        $sql   = "SELECT SUM(oi.price * oi.quantity) FROM {$wpdb->prefix}ahm_orders o, {$wpdb->prefix}ahm_order_items oi WHERE {$where}";
        $sales = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    } else {
        $sales = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(o.total) FROM {$wpdb->prefix}ahm_orders o
             WHERE o.payment_status IN ('Completed', 'Expired')
             AND o.date >= %d AND o.date <= %d",
                $sdate_ts, $edate_ts
        ) );
    }

    return number_format( $sales, 2, '.', '' );
}


function wpdmpp_daily_sales( $uid = '', $pid = '', $sdate = '', $edate = '' ) {
    global $wpdb;

    $pid = (int) $pid;
    $uid = (int) $uid;

    $sdate    = $sdate == '' ? wp_date( "Y-m-01" ) : $sdate;
    $edate    = $edate == '' ? wp_date( "Y-m-d", strtotime( "last day of this month" ) ) : $edate;
    $sdate_ts = strtotime( $sdate );
    $edate_ts = strtotime( $edate );

    $conditions = [ "oi.oid = o.order_id", "o.payment_status IN ('Completed', 'Expired')" ];
    $params     = [];

    if ( $pid > 0 ) {
        $conditions[] = "oi.pid = %d";
        $params[]     = $pid;
    }
    if ( $uid > 0 ) {
        $conditions[] = "oi.sid = %d";
        $params[]     = $uid;
    }
    $conditions[] = "o.date >= %d";
    $params[]     = $sdate_ts;
    $conditions[] = "o.date <= %d";
    $params[]     = $edate_ts;

    $where = implode( ' AND ', $conditions );
    $sql   = "SELECT SUM(oi.price * oi.quantity) AS daily_sale, SUM(oi.quantity) AS quantities,
            oi.date, oi.year, oi.month, oi.day
            FROM {$wpdb->prefix}ahm_orders o, {$wpdb->prefix}ahm_order_items oi
            WHERE {$where}
            GROUP BY oi.date";

    $sales = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

    $diff      = date_diff( date_create( $edate ), date_create( $sdate ) )->days;
    $sdata     = array();
    $i         = 0;
    $loop_date = $sdate;
    do {
        $i ++;
        $sdata['sales'][ $loop_date ]      = 0;
        $sdata['quantities'][ $loop_date ] = 0;
        $loop_date                         = wp_date( 'Y-m-d', strtotime( '+1 day', strtotime( $loop_date ) ) );
    } while ( $i <= $diff );

    foreach ( $sales as $sale ) {
        $sdata['sales'][ $sale->date ]      = $sale->daily_sale;
        $sdata['quantities'][ $sale->date ] = $sale->quantities;
    }

    return $sdata;
}

function wpdmpp_top_sellings_products( $uid = '', $sdate = '', $edate = '', $s = 0, $e = 1000 ) {
    global $wpdb;

    // Build query with proper parameterization
    $conditions = [ "o.payment_status IN ('Completed', 'Expired')" ];
    $params     = [];

    // User condition
    if ( $uid > 0 ) {
        $conditions[] = "oi.sid = %d";
        $params[]     = (int) $uid;
    }

    // Date range conditions
    if ( $sdate != '' ) {
        $conditions[] = "o.date >= %d";
        $params[]     = strtotime( $sdate );
    }
    if ( $edate != '' ) {
        $conditions[] = "o.date <= %d";
        $params[]     = strtotime( $edate );
    }

    // Pagination params
    $params[] = (int) $s;
    $params[] = (int) $e;

    $where_clause = implode( ' AND ', $conditions );

    $sql = "
        SELECT oi.pid, SUM(oi.price) AS sales, SUM(oi.quantity) AS quantities
        FROM {$wpdb->prefix}ahm_order_items oi
        INNER JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = oi.oid
        WHERE {$where_clause}
        GROUP BY oi.pid
        ORDER BY quantities DESC
        LIMIT %d, %d
    ";

    $tsp = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

    return $tsp;
}

function wpdmpp_recent_sales( $uid = '', $count = 10 ) {
    global $wpdb;

    $uid   = (int) $uid;
    $count = (int) $count;
    $count = max( 1, min( $count, 100 ) ); // Limit between 1 and 100

    $oi = "{$wpdb->prefix}ahm_order_items";
    $o  = "{$wpdb->prefix}ahm_orders";

    if ( $uid > 0 ) {
        $tsp = $wpdb->get_results( $wpdb->prepare(
                "SELECT {$oi}.pid AS product_id, {$oi}.price,
             ({$oi}.price * {$oi}.quantity) AS total, {$o}.date AS time_stamp,
             {$oi}.date, {$oi}.year, {$oi}.month, {$oi}.day
             FROM {$oi}
             LEFT JOIN {$o} ON {$oi}.oid = {$o}.order_id
             WHERE {$oi}.sid = %d
             AND {$o}.payment_status IN ('Completed', 'Expired')
             ORDER BY {$o}.date DESC
             LIMIT %d",
                $uid, $count
        ) );
    } else {
        $tsp = $wpdb->get_results( $wpdb->prepare(
                "SELECT {$oi}.pid AS product_id, {$oi}.price,
             ({$oi}.price * {$oi}.quantity) AS total, {$o}.date AS time_stamp,
             {$oi}.date, {$oi}.year, {$oi}.month, {$oi}.day
             FROM {$oi}
             LEFT JOIN {$o} ON {$oi}.oid = {$o}.order_id
             WHERE {$o}.payment_status IN ('Completed', 'Expired')
             ORDER BY {$o}.date DESC
             LIMIT %d",
                $count
        ) );
    }

    foreach ( $tsp as &$_tsp ) {
        $_tsp->post_title = get_the_title( $_tsp->product_id );
    }

    return $tsp;
}

function wpdmpp_get_licenses() {
    $pre_licenses = get_wpdmpp_option( 'licenses', array(
            'single'    => array( 'name' => 'Standard', 'description' => '', 'use' => 1 ),
            'extended'  => array( 'name' => 'Extended', 'description' => '', 'use' => 5 ),
            'unlimited' => array( 'name' => 'Unlimited', 'description' => '', 'use' => 99 ),
    ) );
    $pre_licenses = maybe_unserialize( $pre_licenses );

    return $pre_licenses;

}

function get_wpdmpp_option( $name, $default = '', $validate = null ) {
    global $wpdmpp_settings;

    $name = explode( '/', $name );

    if ( ! is_array( $wpdmpp_settings ) ) {
        return $default;
    }

    if ( count( $name ) == 1 ) {
        $value = isset( $wpdmpp_settings[ $name[0] ] ) ? $wpdmpp_settings[ $name[0] ] : $default;
    } else if ( count( $name ) == 2 ) {
        $value = isset( $wpdmpp_settings[ $name[0] ], $wpdmpp_settings[ $name[0] ][ $name[1] ] ) ? $wpdmpp_settings[ $name[0] ][ $name[1] ] : $default;
    } else if ( count( $name ) == 3 ) {
        $value = isset( $wpdmpp_settings[ $name[0] ], $wpdmpp_settings[ $name[0] ][ $name[1] ], $wpdmpp_settings[ $name[0] ][ $name[1] ][ $name[2] ] ) ? $wpdmpp_settings[ $name[0] ][ $name[1] ][ $name[2] ] : $default;
    } else {
        $value = $default;
    }
    if ( $validate !== null ) {
        $value = wpdm_sanitize_var( $value, $validate );
    }

    return $value;
}

function wpdmpp_countries() {
    return array(
            'AF' => 'AFGHANISTAN',
            'AL' => 'ALBANIA',
            'DZ' => 'ALGERIA',
            'AS' => 'AMERICAN SAMOA',
            'AD' => 'ANDORRA',
            'AO' => 'ANGOLA',
            'AI' => 'ANGUILLA',
            'AQ' => 'ANTARCTICA',
            'AG' => 'ANTIGUA AND BARBUDA',
            'AR' => 'ARGENTINA',
            'AM' => 'ARMENIA',
            'AW' => 'ARUBA',
            'AU' => 'AUSTRALIA',
            'AT' => 'AUSTRIA',
            'AZ' => 'AZERBAIJAN',
            'BS' => 'BAHAMAS',
            'BH' => 'BAHRAIN',
            'BD' => 'BANGLADESH',
            'BB' => 'BARBADOS',
            'BY' => 'BELARUS',
            'BE' => 'BELGIUM',
            'BZ' => 'BELIZE',
            'BJ' => 'BENIN',
            'BM' => 'BERMUDA',
            'BT' => 'BHUTAN',
            'BO' => 'BOLIVIA',
            'BA' => 'BOSNIA AND HERZEGOVINA',
            'BW' => 'BOTSWANA',
            'BV' => 'BOUVET ISLAND',
            'BR' => 'BRAZIL',
            'IO' => 'BRITISH INDIAN OCEAN TERRITORY',
            'BN' => 'BRUNEI DARUSSALAM',
            'BG' => 'BULGARIA',
            'BF' => 'BURKINA FASO',
            'BI' => 'BURUNDI',
            'KH' => 'CAMBODIA',
            'CM' => 'CAMEROON',
            'CA' => 'CANADA',
            'CV' => 'CAPE VERDE',
            'KY' => 'CAYMAN ISLANDS',
            'CF' => 'CENTRAL AFRICAN REPUBLIC',
            'TD' => 'CHAD',
            'CL' => 'CHILE',
            'CN' => 'CHINA',
            'CX' => 'CHRISTMAS ISLAND',
            'CC' => 'COCOS (KEELING) ISLANDS',
            'CO' => 'COLOMBIA',
            'KM' => 'COMOROS',
            'CG' => 'CONGO',
            'CD' => 'CONGO, THE DEMOCRATIC REPUBLIC OF THE',
            'CK' => 'COOK ISLANDS',
            'CR' => 'COSTA RICA',
            'CI' => 'COTE DIVOIRE',
            'HR' => 'CROATIA',
            'CU' => 'CUBA',
            'CY' => 'CYPRUS',
            'CZ' => 'CZECH REPUBLIC',
            'DK' => 'DENMARK',
            'DJ' => 'DJIBOUTI',
            'DM' => 'DOMINICA',
            'DO' => 'DOMINICAN REPUBLIC',
            'EC' => 'ECUADOR',
            'EG' => 'EGYPT',
            'SV' => 'EL SALVADOR',
            'GQ' => 'EQUATORIAL GUINEA',
            'ER' => 'ERITREA',
            'EE' => 'ESTONIA',
            'ET' => 'ETHIOPIA',
            'FK' => 'FALKLAND ISLANDS (MALVINAS)',
            'FO' => 'FAROE ISLANDS',
            'FJ' => 'FIJI',
            'FI' => 'FINLAND',
            'FR' => 'FRANCE',
            'GF' => 'FRENCH GUIANA',
            'PF' => 'FRENCH POLYNESIA',
            'TF' => 'FRENCH SOUTHERN TERRITORIES',
            'GA' => 'GABON',
            'GM' => 'GAMBIA',
            'GE' => 'GEORGIA',
            'DE' => 'GERMANY',
            'GH' => 'GHANA',
            'GI' => 'GIBRALTAR',
            'GR' => 'GREECE',
            'GL' => 'GREENLAND',
            'GD' => 'GRENADA',
            'GP' => 'GUADELOUPE',
            'GU' => 'GUAM',
            'GT' => 'GUATEMALA',
            'GN' => 'GUINEA',
            'GW' => 'GUINEA-BISSAU',
            'GY' => 'GUYANA',
            'HT' => 'HAITI',
            'HM' => 'HEARD ISLAND AND MCDONALD ISLANDS',
            'VA' => 'HOLY SEE (VATICAN CITY STATE)',
            'HN' => 'HONDURAS',
            'HK' => 'HONG KONG',
            'HU' => 'HUNGARY',
            'IS' => 'ICELAND',
            'IN' => 'INDIA',
            'ID' => 'INDONESIA',
            'IR' => 'IRAN, ISLAMIC REPUBLIC OF',
            'IQ' => 'IRAQ',
            'IE' => 'IRELAND',
            'IL' => 'ISRAEL',
            'IT' => 'ITALY',
            'JM' => 'JAMAICA',
            'JP' => 'JAPAN',
            'JO' => 'JORDAN',
            'KZ' => 'KAZAKHSTAN',
            'KE' => 'KENYA',
            'KI' => 'KIRIBATI',
            'KP' => 'KOREA, DEMOCRATIC PEOPLE\'S REPUBLIC OF',
            'KR' => 'KOREA, REPUBLIC OF',
            'KW' => 'KUWAIT',
            'KG' => 'KYRGYZSTAN',
            'LA' => 'LAO PEOPLE\'S DEMOCRATIC REPUBLIC',
            'LV' => 'LATVIA',
            'LB' => 'LEBANON',
            'LS' => 'LESOTHO',
            'LR' => 'LIBERIA',
            'LY' => 'LIBYAN ARAB JAMAHIRIYA',
            'LI' => 'LIECHTENSTEIN',
            'LT' => 'LITHUANIA',
            'LU' => 'LUXEMBOURG',
            'MO' => 'MACAO',
            'MK' => 'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF',
            'MG' => 'MADAGASCAR',
            'MW' => 'MALAWI',
            'MY' => 'MALAYSIA',
            'MV' => 'MALDIVES',
            'ML' => 'MALI',
            'MT' => 'MALTA',
            'MH' => 'MARSHALL ISLANDS',
            'MQ' => 'MARTINIQUE',
            'MR' => 'MAURITANIA',
            'MU' => 'MAURITIUS',
            'YT' => 'MAYOTTE',
            'MX' => 'MEXICO',
            'FM' => 'MICRONESIA, FEDERATED STATES OF',
            'MD' => 'MOLDOVA, REPUBLIC OF',
            'MC' => 'MONACO',
            'MN' => 'MONGOLIA',
            'MS' => 'MONTSERRAT',
            'MA' => 'MOROCCO',
            'MZ' => 'MOZAMBIQUE',
            'MM' => 'MYANMAR',
            'NA' => 'NAMIBIA',
            'NR' => 'NAURU',
            'NP' => 'NEPAL',
            'NL' => 'NETHERLANDS',
            'AN' => 'NETHERLANDS ANTILLES',
            'NC' => 'NEW CALEDONIA',
            'NZ' => 'NEW ZEALAND',
            'NI' => 'NICARAGUA',
            'NE' => 'NIGER',
            'NG' => 'NIGERIA',
            'NU' => 'NIUE',
            'NF' => 'NORFOLK ISLAND',
            'MP' => 'NORTHERN MARIANA ISLANDS',
            'NO' => 'NORWAY',
            'OM' => 'OMAN',
            'PK' => 'PAKISTAN',
            'PW' => 'PALAU',
            'PS' => 'PALESTINIAN TERRITORY, OCCUPIED',
            'PA' => 'PANAMA',
            'PG' => 'PAPUA NEW GUINEA',
            'PY' => 'PARAGUAY',
            'PE' => 'PERU',
            'PH' => 'PHILIPPINES',
            'PN' => 'PITCAIRN',
            'PL' => 'POLAND',
            'PT' => 'PORTUGAL',
            'PR' => 'PUERTO RICO',
            'QA' => 'QATAR',
            'RE' => 'REUNION',
            'RO' => 'ROMANIA',
            'RU' => 'RUSSIAN FEDERATION',
            'RW' => 'RWANDA',
            'SH' => 'SAINT HELENA',
            'KN' => 'SAINT KITTS AND NEVIS',
            'LC' => 'SAINT LUCIA',
            'PM' => 'SAINT PIERRE AND MIQUELON',
            'VC' => 'SAINT VINCENT AND THE GRENADINES',
            'WS' => 'SAMOA',
            'SM' => 'SAN MARINO',
            'ST' => 'SAO TOME AND PRINCIPE',
            'SA' => 'SAUDI ARABIA',
            'SN' => 'SENEGAL',
            'CS' => 'SERBIA AND MONTENEGRO',
            'SC' => 'SEYCHELLES',
            'SL' => 'SIERRA LEONE',
            'SG' => 'SINGAPORE',
            'SK' => 'SLOVAKIA',
            'SI' => 'SLOVENIA',
            'SB' => 'SOLOMON ISLANDS',
            'SO' => 'SOMALIA',
            'ZA' => 'SOUTH AFRICA',
            'GS' => 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS',
            'ES' => 'SPAIN',
            'LK' => 'SRI LANKA',
            'SD' => 'SUDAN',
            'SR' => 'SURINAME',
            'SJ' => 'SVALBARD AND JAN MAYEN',
            'SZ' => 'SWAZILAND',
            'SE' => 'SWEDEN',
            'CH' => 'SWITZERLAND',
            'SY' => 'SYRIAN ARAB REPUBLIC',
            'TW' => 'TAIWAN, PROVINCE OF CHINA',
            'TJ' => 'TAJIKISTAN',
            'TZ' => 'TANZANIA, UNITED REPUBLIC OF',
            'TH' => 'THAILAND',
            'TL' => 'TIMOR-LESTE',
            'TG' => 'TOGO',
            'TK' => 'TOKELAU',
            'TO' => 'TONGA',
            'TT' => 'TRINIDAD AND TOBAGO',
            'TN' => 'TUNISIA',
            'TR' => 'TURKEY',
            'TM' => 'TURKMENISTAN',
            'TC' => 'TURKS AND CAICOS ISLANDS',
            'TV' => 'TUVALU',
            'UG' => 'UGANDA',
            'UA' => 'UKRAINE',
            'AE' => 'UNITED ARAB EMIRATES',
            'GB' => 'UNITED KINGDOM',
            'US' => 'UNITED STATES',
            'UM' => 'UNITED STATES MINOR OUTLYING ISLANDS',
            'UY' => 'URUGUAY',
            'UZ' => 'UZBEKISTAN',
            'VU' => 'VANUATU',
            'VE' => 'VENEZUELA',
            'VN' => 'VIET NAM',
            'VG' => 'VIRGIN ISLANDS, BRITISH',
            'VI' => 'VIRGIN ISLANDS, U.S.',
            'WF' => 'WALLIS AND FUTUNA',
            'EH' => 'WESTERN SAHARA',
            'YE' => 'YEMEN',
            'ZM' => 'ZAMBIA',
            'ZW' => 'ZIMBABWE'
    );
}

function wpdmpp_tax_active() {
    global $wpdmpp_settings;

    return isset( $wpdmpp_settings['tax'] ) && isset( $wpdmpp_settings['tax']['enable'] ) ? true : false;
}

function wpdmpp_show_tax() {
    global $wpdmpp_settings;

    return isset( $wpdmpp_settings['tax'] ) && isset( $wpdmpp_settings['tax']['tax_on_cart'] ) ? true : false;
}


//Send notification before delete product
add_action( 'wp_trash_post', 'wpdmpp_notify_product_rejected' );
function wpdmpp_notify_product_rejected( $post_id ) {
    global $post_type;
    if ( $post_type != 'wpdmpro' ) {
        return;
    }

    $post              = get_post( $post_id );
    $post_meta         = get_post_meta( $post_id, "_z_user_review", true );

    if ( $post_meta != "" ):
        $author = get_userdata( $post->post_author );
        $author_email  = $author->user_email;
        $email_subject = "Your product has been rejected.";

        ob_start(); ?>
        <html>
        <head>
            <title>New post at <?php bloginfo( 'name' ) ?></title>
        </head>
        <body>
        <p>
            Hi <?php echo $author->user_firstname ?>,
        </p>

        <p>
            Your product <?php the_title() ?> has not been approved by team.
        </p>
        </body>
        </html>
        <?php
        $message = ob_get_contents();
        ob_end_clean();

        wp_mail( $author_email, $email_subject, $message );
    endif;
}

// Product accept notification email
function wpdmpp_notify_product_accepted( $post_id ) {
    global $post_type;
    if ( $post_type != 'wpdmpro' ) {
        return;
    }

    if ( ( $_POST['post_status'] == 'publish' ) && ( $_POST['original_post_status'] != 'publish' ) ) {
        $post              = get_post( $post_id );
        $post_meta         = get_post_meta( $post_id, "_z_user_review", true );
        if ( $post_meta != "" ):

            $author = get_userdata( $post->post_author );
            $author_email  = $author->user_email;
            $email_subject = "Your post has been published.";

            ob_start(); ?>
            <html>
            <head>
                <title>Your Product Status at <?php bloginfo( 'name' ) ?></title>
            </head>
            <body>
            <p>Hi <?php echo $author->user_firstname ?>,</p>
            <p>Your product <a href="<?php echo get_permalink( $post->ID ) ?>"><?php the_title_attribute() ?></a> has
                been
                published.</p>
            </body>
            </html>
            <?php
            $message = ob_get_clean();

            wp_mail( $author_email, $email_subject, $message );
        endif;
    }
}


/**
 * Calculate pending balance and matured balance of the seller
 *
 * @return array $seller_balances Array of balances. Access using `pending` and `matured`
 * @since 3.8.9
 */
function wpdmpp_seller_balances() {
    global $wpdb, $current_user;
    $current_user = wp_get_current_user();
    $uid          = (int) $current_user->ID;

    $total_sales = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(i.price * i.quantity)
         FROM {$wpdb->prefix}ahm_orders o,
              {$wpdb->prefix}ahm_order_items i,
              {$wpdb->prefix}posts p
         WHERE p.post_author = %d
         AND i.oid = o.order_id
         AND i.pid = p.ID
         AND i.quantity > 0
         AND o.payment_status = 'Completed'",
            $uid
    ) );

    $commission       = wpdmpp_site_commission();
    $total_commission = $total_sales * $commission / 100;
    $total_earning    = $total_sales - $total_commission;

    $total_withdraws = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}ahm_withdraws WHERE uid = %d",
            $uid
    ) );
    $balance         = $total_earning - $total_withdraws;

    //finding matured balance
    $payout_duration = (int) get_option( "wpdmpp_payout_duration" );
    $dt              = $payout_duration * 24 * 60 * 60;
    $matured_time    = time() - $dt;

    $tempbalance = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(i.price * i.quantity)
         FROM {$wpdb->prefix}ahm_orders o,
              {$wpdb->prefix}ahm_order_items i,
              {$wpdb->prefix}posts p
         WHERE p.post_author = %d
         AND i.oid = o.order_id
         AND i.pid = p.ID
         AND i.quantity > 0
         AND o.payment_status = 'Completed'
         AND o.date < %d",
            $uid, $matured_time
    ) );

    $tempbalance     = $tempbalance - ( $tempbalance * $commission / 100 );
    $matured_balance = $tempbalance - $total_withdraws;

    //finding pending balance
    $pending_balance = $balance - $matured_balance;

    $seller_balances            = array();
    $seller_balances['pending'] = $pending_balance;
    $seller_balances['matured'] = $matured_balance;

    return $seller_balances;
}

//for withdraw request
function wpdmpp_withdraw_request() {
    global $wpdb, $current_user;

    $current_user = wp_get_current_user();

    $uid = $current_user->ID;

    if ( isset( $_POST['withdraw'], $_POST['withdraw_amount'] ) && $_POST['withdraw'] == 1 && $_POST['withdraw_amount'] > 0 ) {

        // Check if matured balance is greater than 0
        $seller_balances = wpdmpp_seller_balances();
        if ( $seller_balances['matured'] <= 0 ) {
            if ( wpdm_is_ajax() ) {
                wp_send_json( [ 'success' => false, 'denied' => true, 'msg' => __( 'Request denied. Matured balance is 0!', 'wpdm-premium-packages' ) ] );
            }
            wp_die( 'denied' );
        }
        $payout_method   = wpdm_query_var( 'payout_method', 'txt' );
        $payment_account = wpdm_valueof( WPDMPP()->withdraws->payoutAccounts( $current_user->ID ), $payout_method );
        if ( ! $payment_account || ! $payout_method ) {
            wp_send_json( [
                    'success' => false,
                    'msg'     => __( "Withdrawal Request Failed. No payout option selected!", "wpdm-premium-packages" )
            ] );
        }
        $wpdb->insert(
                "{$wpdb->prefix}ahm_withdraws",
                array(
                        'uid'             => $uid,
                        'date'            => time(),
                        'amount'          => absint( $_POST['withdraw_amount'] ),
                        'payment_method'  => $payout_method,
                        'payment_account' => $payment_account,
                        'status'          => 0
                ),
                array(
                        '%d',
                        '%d',
                        '%f',
                        '%s',
                        '%s',
                        '%d'
                )
        );

        if ( wpdm_is_ajax() ) {
            wp_send_json( [ 'success' => true, 'msg' => __( "Withdrawal Request Sent!", "wpdm-premium-packages" ) ] );
        }
        $return = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : home_url( '/' );
        wp_safe_redirect( $return );
        exit;
    }

}

function wpdmpp_redirect( $url ) {
    if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
    } else {
        echo "<script>location.href='" . esc_url( $url ) . "';</script>";
    }
    exit;
}

function wpdmpp_js_redirect( $url ) {
    echo "&nbsp;Redirecting...<script>location.href='" . esc_url( $url ) . "';</script>";
    exit;
}

function wpdmpp_members_page() {
    $settings = get_option( '_wpdmpp_settings' );

    return isset( $settings['members_page_id'] ) ? get_permalink( $settings['members_page_id'] ) : wpdm_user_dashboard_url( array( 'udb_page' => 'account-credits' ) );
}

function wpdmpp_checkout_return_url( $payment, $order_id = '' ) {
    $gop = wpdmpp_guest_order_page();
    $opu = ! is_user_logged_in() && get_wpdmpp_option( 'guest_download' ) == 1 && $gop != '' ? $gop : wpdmpp_orders_page( $order_id );
    $url = get_wpdmpp_option( "{$payment}/return_url", $opu );
    $url = str_replace( "{{download_page}}", "", $url );

    return $url;
}

function wpdmpp_orders_page( $part = '' ) {
    global $wpdmpp_settings;
    $settings = $wpdmpp_settings;

    $orders_page_id = isset( $settings['orders_page_id'] ) ? (int) $settings['orders_page_id'] : 0;
    $url            = $orders_page_id ? get_permalink( $orders_page_id ) : false;

    // The orders page may be unset, unpublished, or deleted, in which case
    // get_permalink() returns false. Bail with an empty string rather than
    // appending "?{$part}" to a falsy value and producing a bare, scheme-less
    // query string like "?id=ABC" — that is not a valid URL and silently breaks
    // payment gateway success/cancel URLs. An empty return lets callers (and the
    // gateways) treat the unconfigured orders page as the misconfiguration it is.
    if ( ! $url ) {
        return '';
    }

    if ( $part != '' ) {
        if ( strpos( $url, '?' ) ) {
            $url .= "&" . $part;
        } else {
            $url .= "?" . $part;
        }
    }

    $udbpage = get_option( '__wpdm_user_dashboard', 0 );
    if ( (int) $udbpage > 0 && $orders_page_id === (int) $udbpage ) {

        $udbpage = get_permalink( $udbpage );
        $sap     = strstr( $udbpage, '?' ) ? "&udb_page=" : "?udb_page=";
        $url     = $udbpage . $sap . "purchases/orders/";
        if ( $part != '' ) {
            $part = explode( "=", $part );
            $url  = $udbpage . $sap . "purchases/order/" . end( $part ) . "/";
        }
    }

    return $url;
}

function wpdmpp_guest_order_page( $part = '' ) {
    $settings = get_option( '_wpdmpp_settings' );
    $url      = get_permalink( $settings['guest_order_page_id'] );
    if ( ! isset( $settings['guest_download'] ) || $settings['guest_download'] == 0 ) {
        return '';
    }
    if ( $part != '' ) {
        if ( strpos( $url, '?' ) ) {
            $url .= "&" . $part;
        } else {
            $url .= "?" . $part;
        }
    }

    return $url;
}

/**
 * Returns cart page url
 *
 * @param array $params
 *
 * @return false|string
 */
function wpdmpp_cart_page( $params = array() ) {
    global $wpdmpp_settings;
    if ( ! $wpdmpp_settings ) {
        $wpdmpp_settings = get_option( '_wpdmpp_settings' );
    }

    if ( ! (int) wpdm_valueof( $wpdmpp_settings, 'page_id' ) ) {
        return '';
    }

    $url = get_permalink( $wpdmpp_settings['page_id'] );

    if ( ! $url ) {
        return '';
    }

    $url = add_query_arg( $params, $url );

    return esc_url( $url );
}

function wpdmpp_cart_url( $params = array() ) {
    return wpdmpp_cart_page( $params );
}

function wpdmpp_checkout_link( $label = 'Checkout', $class = 'btn btn-info', $params = array() ) {
    $cart_page = wpdmpp_cart_page( $params );
    if ( ! $cart_page ) {
        return '';
    }

    return "<a href='" . $cart_page . "' class='{$class}'>{$label}</a>";
}

function wpdmpp_is_cart_page( $id = null ) {
    $id           = $id ?: get_the_ID();
    $cart_page_id = (int) get_wpdmpp_option( 'page_id' );

    return $cart_page_id === (int) $id ? $cart_page_id : false;
}


function wpdmpp_continue_shopping_url( $args = [] ) {
    return esc_url( add_query_arg( $args, get_wpdmpp_option( 'continue_shopping_url', home_url( '/' ) ) ) );
}


function wpdmpp_save_billing_info() {
    global $current_user;
    $current_user = wp_get_current_user();
    if ( isset( $_POST['__wpdm_store_owner'] ) ) {
        $__wpdm_store_owner = isset( $_POST['__wpdm_store_owner'] ) ? 1 : 0;
    }
    update_user_meta( $current_user->ID, '__wpdm_store_owner', $__wpdm_store_owner );
    if ( isset( $_POST['__wpdm_store'] ) ) {
        $store_data = wpdm_sanitize_array( $_POST['__wpdm_store'] );
        update_user_meta( $current_user->ID, '__wpdm_store', $store_data );
    }
    if ( isset( $_POST['checkout'] ) && isset( $_POST['checkout']['billing'] ) ) {
        $codata = wpdm_sanitize_array( $_POST['checkout'] );
        update_user_meta( $current_user->ID, 'user_billing_shipping', serialize( $codata ) );
    }
}

/**
 * Get the list of purchased items of the current user
 */
function wpdmpp_get_purchased_items() {
    if ( ! isset( $_GET['wpdmppaction'] ) || $_GET['wpdmppaction'] != 'getpurchaseditems' ) {
        return;
    }
    if ( wpdm_query_var( 'user' ) != '' ) {
        $user = wp_signon( array(
                'user_login'    => wpdm_query_var( 'user' ),
                'user_password' => wpdm_query_var( 'pass' )
        ) );
        if ( $user->ID ) {
            wp_set_current_user( $user->ID );
        }
    }
    if ( wpdm_query_var( 'wpdm_access_token' ) != '' ) {
        $at = wpdm_query_var( 'wpdm_access_token' );
        if ( ! $at ) {
            wp_send_json_error( array( 'error' => __( 'Invalid Access Token!', 'wpdm-premium-packages' ) ) );
        }
        $atx = explode( "x", $at );
        $uid = end( $atx );
        $uid = (int) $uid;
        if ( ! $uid ) {
            wp_send_json_error( array( 'error' => __( 'Invalid Access Token!', 'wpdm-premium-packages' ) ) );
        }
        $sat = get_user_meta( $uid, '__wpdm_access_token', true );
        if ( $sat === '' ) {
            wp_send_json_error( array( 'error' => __( 'Invalid Access Token!', 'wpdm-premium-packages' ) ) );
        }
        if ( $sat === $at ) {
            wp_set_current_user( $uid );
        } else {
            wp_send_json_error( array( 'error' => __( 'Invalid Access Token!', 'wpdm-premium-packages' ) ) );
        }
    }
    if ( is_user_logged_in() ) {
        wp_send_json( \WPDMPP\Order\OrderService::instance()->getPurchasedItems() );
    } else {
        wp_send_json_error( array( 'error' => '<a href="https://www.wpdownloadmanager.com/user-dashboard/?redirect_to=[redirect]">' . __( 'You need to login first!', 'wpdm-premium-packages' ) . '</a>' ) );
    }
}

/**
 * Retrienve Site Commissions on User's Sales
 *
 * @param null $uid
 *
 * @return mixed
 */
function wpdmpp_site_commission( $uid = null ) {
    global $current_user;
    $current_user = wp_get_current_user();
    $user         = $current_user;
    if ( $uid ) {
        $user = get_userdata( $uid );
    }
    $role      = array_shift( $user->roles );
    $comission = get_option( "wpdmpp_user_comission" );
    $comission = isset( $comission[ $role ] ) ? (double) $comission[ $role ] : 0;

    return $comission;
}

function wpdmpp_get_user_earning() {

}


function wpdmpp_product_price( $pid, $license = '' ) {
    $base_price  = get_post_meta( $pid, "__wpdm_base_price", true );
    $sales_price = wpdmpp_sales_price( $pid );
    $price       = (double) ( $sales_price ) > 0 && $sales_price < $base_price ? (double) $sales_price : (double) $base_price;

    if ( floatval( $price ) == 0 ) {
        return number_format( 0, 2, ".", "" );
    }

    return number_format( $price, 2, ".", "" );
}

function wpdmpp_is_ajax() {
    if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) &&
         strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest'
    ) {
        return true;
    }

    return false;
}

//delete product from front-end
function wpdmpp_delete_product() {
    if ( is_user_logged_in() && isset( $_GET['dproduct'] ) ) {
        global $current_user;
        $current_user = wp_get_current_user();
        $pid          = intval( $_GET['dproduct'] );
        $pro          = get_post( $pid );

        if ( $current_user->ID == $pro->post_author ) {
            wp_update_post( array( 'ID' => $pid, 'post_status' => 'trash' ) );
            $settings = get_option( '_wpdmpp_settings' );
            if ( $settings['frontend_product_delete_notify'] == 1 ) {
                wp_mail( get_option( 'admin_email' ), "I had to delete a product", "Hi, Sorry, but I had to delete following product for some reason:<br/>{$pro->post_title}", "From: {$current_user->user_email}\r\nContent-type: text/html\r\n\r\n" );
            }
            Session::set( 'dpmsg', __( 'Product Deleted', 'wpdm-premium-packages' ) );
            $return = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : home_url( '/' );
            wp_safe_redirect( $return );
            exit;
        }
    }
}

function wpdmpp_order_completed_mail() {

}

function wpdmpp_head() {
    $wpdmpp_txt = array(
            'cart_button_label'     => get_wpdmpp_option( 'a2cbtn_label', \WPDMPP\UI\Icons::get('basket', 16) . ' ' . __( 'Add To Cart', 'wpdm-premium-packages' ) ),
            'pay_now'               => get_wpdmpp_option( 'cobtn_label', __( 'Complete Purchase', 'wpdm-premium-packages' ) ),
            'checkout_button_label' => get_wpdmpp_option( 'cobtn_label', __( 'Complete Purchase', 'wpdm-premium-packages' ) ),
            'resolve_tracking'      => __( 'Tracking Order...', 'wpdm-premium-packages' ),
            'resolve_success'       => __( 'Order is linked with your account successfully.', 'wpdm-premium-packages' ),
            'resolve_not_found'     => __( 'Order not found!', 'wpdm-premium-packages' ),
            'resolve_error'         => __( 'Something went wrong. Please try again.', 'wpdm-premium-packages' ),
    );

    ?>
    <script>
        var wpdmpp_base_url = '<?php echo plugins_url( '/wpdm-premium-packages/' ); ?>';
        var wpdmpp_currency_sign = '<?php echo wpdmpp_currency_sign(); ?>';
        var wpdmpp_csign_before = '<?php echo wpdmpp_currency_sign_position() == 'before' ? wpdmpp_currency_sign() : ''; ?>';
        var wpdmpp_csign_after = '<?php echo wpdmpp_currency_sign_position() == 'after' ? wpdmpp_currency_sign() : ''; ?>';
        var wpdmpp_currency_code = '<?php echo wpdmpp_currency_code(); ?>';
        var wpdmpp_cart_url = '<?php echo wpdmpp_cart_page(); ?>';

        var wpdmpp_txt = <?php echo json_encode( $wpdmpp_txt ); ?>;

    </script>
    <style>p.wpdmpp-notice {
            margin: 5px;
        }

        .wpbtn-success {
            color: var(--color-success) !important;
            border-color: var(--color-success) !important;
            background: rgba(var(--color-success-rgb), 0.03) !important;
            transition: all ease-in-out 300ms;
        }

        .wpbtn-success:active,
        .wpbtn-success:hover {
            color: var(--color-success-active) !important;
            border-color: var(--color-success-active) !important;
            background: rgba(var(--color-success-rgb), 0.07) !important;
        }
    </style>
    <?php
}

function wpdmpp_admin_footer() {
    global $pagenow;
    if ( $pagenow !== 'edit.php' || wpdm_query_var( 'post_type' ) !== 'wpdmpro' ) {
        return;
    }
    ?>
    <style>
        /* Distinctive style on the WP "Add New"-shaped button — same size & radius, just styled. */
        .wpbtn-pay-link.page-title-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #10b981;
            border-color: #059669;
            color: #ffffff !important;
            text-shadow: none;
            box-shadow: 0 1px 0 #047857;
        }
        .wpbtn-pay-link.page-title-action:hover,
        .wpbtn-pay-link.page-title-action:focus {
            background: #059669;
            border-color: #047857;
            color: #ffffff !important;
            box-shadow: 0 1px 0 #065f46;
        }
        .wpbtn-pay-link.page-title-action:focus { outline: none; box-shadow: 0 0 0 1px #fff, 0 0 0 3px rgba(16, 185, 129, 0.55); }
        .wpbtn-pay-link.page-title-action:active { background: #047857; border-color: #065f46; box-shadow: inset 0 1px 0 #065f46; }
        .wpbtn-pay-link.page-title-action svg    { width: 14px; height: 14px; flex: 0 0 14px; }

        .wpdmpp-pay-link__hint        { color: #64748b; font-size: 12px; margin: 4px 0 0; }
        .wpdmpp-pay-link__link-row    { display: flex; align-items: stretch; gap: 0; }
        .wpdmpp-pay-link__link-row input { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
        .wpdmpp-pay-link__copied      { color: var(--color-success, #10b981); font-weight: 600; }
        #modal-pay-link .modal-dialog { max-width: 520px; }
        #modal-pay-link .form-group   { margin-bottom: 14px; }
        #modal-pay-link .modal-footer { display: flex; gap: 8px; justify-content: flex-end; }
    </style>
    <script>
        jQuery(function ($) {
            var $body  = $('body');
            var BASE   = <?php echo wp_json_encode( home_url( '/' ) ); ?>;
            var STR    = {
                created:    <?php echo wp_json_encode( __( 'Payment link sent successfully.', 'wpdm-premium-packages' ) ); ?>,
                failed:     <?php echo wp_json_encode( __( 'Failed to send payment link.', 'wpdm-premium-packages' ) ); ?>,
                copied:     <?php echo wp_json_encode( __( 'Copied!', 'wpdm-premium-packages' ) ); ?>,
                createBtn:  <?php echo wp_json_encode( __( 'Create Pay Link', 'wpdm-premium-packages' ) ); ?>
            };

            // Inject the toolbar button. Building via DOM API keeps the label safe even if
            // the translation contains apostrophes that would otherwise break a JS string literal.
            // The icon HTML is server-rendered (Lucide SVG via Icons::get) and JSON-encoded.
            var PLINK_ICON = <?php echo wp_json_encode( \WPDMPP\UI\Icons::get( 'credit-card', 14 ) ); ?>;
            $('.page-title-action').first().each(function () {
                $('<button/>', {
                    type:    'button',
                    'class': 'page-title-action wpbtn-pay-link'
                })
                .append($(PLINK_ICON))
                .append($('<span/>').text(STR.createBtn))
                .insertAfter(this);
            });

            // Bind the click manually so we don't depend on Bootstrap's data-toggle scanner
            // running on this generic edit.php page.
            $body.on('click', '.wpbtn-pay-link', function (e) {
                e.preventDefault();
                var $modal = $('#modal-pay-link');
                if (typeof $modal.modal === 'function') {
                    $modal.modal('show');
                } else {
                    // Fallback: plain CSS show.
                    $modal.addClass('in').css({ display: 'block' });
                    if (!$('.modal-backdrop.wpdmpp-pl-bd').length) {
                        $('<div class="modal-backdrop fade in wpdmpp-pl-bd"/>').appendTo('body');
                    }
                }
                setTimeout(function () { $('#plprice').trigger('focus'); }, 100);
            });

            // Manual close fallback (only fires if Bootstrap modal API is missing).
            $body.on('click', '#modal-pay-link [data-dismiss="modal"], .modal-backdrop.wpdmpp-pl-bd', function () {
                var $modal = $('#modal-pay-link');
                if (typeof $modal.modal !== 'function') {
                    $modal.removeClass('in').css({ display: 'none' });
                    $('.modal-backdrop.wpdmpp-pl-bd').remove();
                }
            });

            function buildLink() {
                var price = parseFloat($('#plprice').val());
                if (!(price > 0)) return '';
                var qs = $.param({
                    addtocart: 'dynamic',
                    price:     price,
                    name:      $('#pltitle').val() || '',
                    desc:      $('#pldesc').val() || '',
                    recurring: 0
                });
                return BASE + '?' + qs;
            }

            $body.on('input keyup change', '.pla', function () {
                $('#plink').val(buildLink());
            });

            $body.on('click', '#plcopy', function (e) {
                e.preventDefault();
                var link = $('#plink').val();
                if (!link) return;
                var done = function () {
                    var $hint = $('#plcopy-hint').text(STR.copied).addClass('wpdmpp-pay-link__copied');
                    clearTimeout($hint.data('t'));
                    $hint.data('t', setTimeout(function () { $hint.text('').removeClass('wpdmpp-pay-link__copied'); }, 1800));
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(done, done);
                } else {
                    var $tmp = $('<textarea/>').val(link).appendTo('body').select();
                    try { document.execCommand('copy'); } catch (err) {}
                    $tmp.remove();
                    done();
                }
            });

            $body.on('submit', '#sendplink', function (e) {
                e.preventDefault();
                var $form = $(this);
                // Make sure the link field is current right before submission.
                $('#plink').val(buildLink());
                WPDM.blockUI('#sendplink');
                $.ajax({
                    url:      ajaxurl,
                    method:   'POST',
                    dataType: 'json',
                    data:     $form.serialize()
                }).done(function (res) {
                    var data = (res && res.data) || {};
                    if (res && res.success) {
                        WPDM.notify(data.message || STR.created, 'success', 'top-center', 5000);
                        $('#sendplink')[0].reset();
                        $('#plink').val('');
                        var $modal = $('#modal-pay-link');
                        if (typeof $modal.modal === 'function') {
                            $modal.modal('hide');
                        } else {
                            $modal.removeClass('in').css({ display: 'none' });
                            $('.modal-backdrop.wpdmpp-pl-bd').remove();
                        }
                    } else {
                        WPDM.notify(data.message || STR.failed, 'danger', 'top-center', 6000);
                    }
                }).fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || STR.failed;
                    WPDM.notify(msg, 'danger', 'top-center', 6000);
                }).always(function () {
                    WPDM.unblockUI('#sendplink');
                });
            });
        });
    </script>

    <div class="w3eden">
        <div class="modal fade" tabindex="-1" role="dialog" id="modal-pay-link" aria-labelledby="modal-pay-link-title">
            <div class="modal-dialog" role="document">
                <form method="post" id="sendplink">
                    <?php wp_nonce_field( 'wpdmpp_email_payment_link', '__wpdmpp_payment_link_nonce' ); ?>
                    <input type="hidden" name="action" value="wpdmpp_email_payment_link"/>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="modal-pay-link-title"><?php esc_html_e( 'Create Payment Link', 'wpdm-premium-packages' ); ?></h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Close', 'wpdm-premium-packages' ); ?>"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="plprice"><?php esc_html_e( 'Price', 'wpdm-premium-packages' ); ?> <span class="text-danger">*</span></label>
                                <input required step="0.01" min="0.01" type="number" class="form-control pla" id="plprice" name="price" placeholder="0.00"/>
                            </div>
                            <div class="form-group">
                                <label for="pltitle"><?php esc_html_e( 'Title', 'wpdm-premium-packages' ); ?> <span class="text-danger">*</span></label>
                                <input required type="text" name="name" class="form-control pla" id="pltitle" placeholder="<?php esc_attr_e( 'Reason for payment', 'wpdm-premium-packages' ); ?>"/>
                            </div>
                            <div class="form-group">
                                <label for="pldesc"><?php esc_html_e( 'Description', 'wpdm-premium-packages' ); ?></label>
                                <input type="text" name="desc" class="form-control pla" id="pldesc" placeholder="<?php esc_attr_e( 'Additional details (optional)', 'wpdm-premium-packages' ); ?>"/>
                            </div>

                            <div class="form-group">
                                <label for="plink"><?php esc_html_e( 'Generated Payment Link', 'wpdm-premium-packages' ); ?></label>
                                <div class="wpdmpp-pay-link__link-row">
                                    <input type="text" readonly class="form-control bg-white" id="plink" placeholder="<?php esc_attr_e( 'Enter a price above to generate a link', 'wpdm-premium-packages' ); ?>"/>
                                    &nbsp;<button type="button" id="plcopy" class="btn btn-secondary" title="<?php esc_attr_e( 'Copy', 'wpdm-premium-packages' ); ?>"><?php echo \WPDMPP\UI\Icons::get( 'copy', 12 ); ?></button>
                                </div>
                                <p class="wpdmpp-pay-link__hint" id="plcopy-hint"></p>
                            </div>

                            <hr/>

                            <div class="form-group">
                                <label for="plmsg"><?php esc_html_e( 'Note', 'wpdm-premium-packages' ); ?></label>
                                <textarea id="plmsg" name="msg" class="form-control" rows="3" placeholder="<?php esc_attr_e( 'Write something about this payment request', 'wpdm-premium-packages' ); ?>"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="plemails"><?php esc_html_e( 'Recipient Email', 'wpdm-premium-packages' ); ?></label>
                                <input id="plemails" name="emails" type="email" class="form-control" placeholder="<?php esc_attr_e( 'customer@example.com', 'wpdm-premium-packages' ); ?>"/>
                                <p class="wpdmpp-pay-link__hint"><?php esc_html_e( 'The link and details will be emailed to this address.', 'wpdm-premium-packages' ); ?></p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal"><?php esc_html_e( 'Cancel', 'wpdm-premium-packages' ); ?></button>
                            <button type="submit" class="btn btn-primary" id="send-plink"><?php echo \WPDMPP\UI\Icons::get( 'mail', 14 ); ?> <?php esc_html_e( 'Send Payment Link', 'wpdm-premium-packages' ); ?></button>
                        </div>
                    </div><!-- /.modal-content -->
                </form>
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->
    </div>

    <?php
}


function wpdmpp_delete_frontend_order() {
    if ( ! wp_verify_nonce( $_REQUEST['nonce'], NONCE_KEY ) ) {
        exit( "No naughty business please" );
    }

    $result['type'] = 'failed';
    global $wpdb;
    $order_id = sanitize_text_field( $_REQUEST['order_id'] );
    $uid      = (int) get_current_user_id();

    $ret = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s AND uid = %d",
            $order_id, $uid
    ) );

    if ( $ret ) {
        $ret = $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}ahm_order_items WHERE oid = %s",
                $order_id
        ) );

        if ( $ret ) {
            $result['type'] = 'success';
        }
    }

    if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) {
        wp_send_json( $result );
    } else {
        $return = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : home_url( '/' );
        wp_safe_redirect( $return );
        exit;
    }
}


/**
 * Update Guest Billing Info
 */
function wpdmpp_update_guest_billing() {
    $billinginfo  = array
    (
            'first_name'  => '',
            'last_name'   => '',
            'company'     => '',
            'address_1'   => '',
            'address_2'   => '',
            'city'        => '',
            'postcode'    => '',
            'country'     => '',
            'state'       => '',
            'order_email' => '',
            'email'       => '',
            'phone'       => '',
            'taxid'       => ''
    );
    $sbillinginfo = wpdm_sanitize_array( $_POST['billing'] );
    $billinginfo  = shortcode_atts( $billinginfo, $sbillinginfo );
    $oid          = \WPDM\__\Crypt::decrypt( wpdm_query_var( 'oid' ) );
    \WPDMPP\Order\OrderService::instance()->updateOrder( array( 'billing_info' => maybe_serialize( $billinginfo ) ), $oid );
    wp_die( esc_html__( 'Billing info saved successfully!', 'wpdm-premium-packages' ) );
}

function wpdmpp_recalculate_sales() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ['message' => __( 'Unauthorized', 'wpdm-premium-packages' )] );
    }
    if ( ! isset( $_POST['id'] ) ) {
        return;
    }
    global $wpdb;
    $id = (int) $_POST['id'];

    $data = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(quantity * price) AS sales_amount, SUM(quantity) AS sales_quantity
         FROM {$wpdb->prefix}ahm_order_items oi, {$wpdb->prefix}ahm_orders o
         WHERE oi.oid = o.order_id AND oi.pid = %d AND o.order_status IN ('Completed', 'Expired')",
            $id
    ) );

    update_post_meta( $id, '__wpdm_sales_amount', $data->sales_amount );
    update_post_meta( $id, '__wpdm_sales_count', $data->sales_quantity );
    $data->sales_amount   = wpdmpp_currency_sign() . floatval( $data->sales_amount );
    $data->sales_quantity = intval( $data->sales_quantity );
    wp_send_json( $data );
}

function wpdmpp_sales_price( $pid ) {
    $sales_price        = get_post_meta( $pid, "__wpdm_sales_price", true );
    $sales_price_expire = get_post_meta( $pid, "__wpdm_sales_price_expire", true );
    if ( $sales_price_expire != '' ) {
        $sales_price_expire = strtotime( $sales_price_expire );
        if ( time() > $sales_price_expire && $sales_price_expire > 0 ) {
            $sales_price = 0;
        }
    }

    return number_format( (double) $sales_price, 2, ".", "" );
}

function wpdmpp_sales_price_info( $product_id ) {
    $sales_price_expire = get_post_meta( $product_id, '__wpdm_sales_price_expire', true );
    if ( $sales_price_expire != '' ) {
        $sales_price_expire = strtotime( $sales_price_expire );
    }
    $sales_price_info = $sales_price_expire != '' ? sprintf( __( "Sales price will expire on %s", "wpdm-premium-packages" ), wp_date( get_option( "date_format" ) . " H:i", $sales_price_expire ) ) : __( "This is a discounted price for a limited time", "wpdm-premium-packages" );
    $sales_price_info = apply_filters( "wpdmpp_sales_price_info", $sales_price_info, $product_id, $sales_price_expire );

    return $sales_price_info;

}

/**
 * @param $pid
 *
 * @return string
 */
function wpdmpp_effective_price( $pid ) {
    global $current_user;
    $current_user = wp_get_current_user();
    if ( get_post_type( $pid ) != 'wpdmpro' ) {
        return 0;
    }
    $base_price  = get_post_meta( $pid, "__wpdm_base_price", true );
    $base_price  = $base_price ? (double) $base_price : 0;
    $sales_price = wpdmpp_sales_price( $pid );
    $price       = (double) ( $sales_price ) > 0 ? $sales_price : $base_price;
    $role        = is_user_logged_in() && is_array( $current_user->roles ) && isset( $current_user->roles[0] ) ? $current_user->roles[0] : 'guest';
    $discount    = maybe_unserialize( get_post_meta( $pid, '__wpdm_discount', true ) );
    if ( ! is_array( $discount ) || count( $discount ) == 0 ) {
        return number_format( (float) $price, 2, ".", "" );
    }

    $discount[ $role ] = isset( $discount[ $role ] ) ? $discount[ $role ] : 0;
    $discount[ $role ] = (double) $discount[ $role ];
    $user_discount     = ( ( $price * $discount[ $role ] ) / 100 );
    $price             -= $user_discount;

    if ( ! $price ) {
        $price = 0;
    }

    return number_format( $price, 2, ".", "" );
}

/**
 * @param $pid
 *
 * @return int|mixed
 */
function wpdmpp_role_discount( $pid, $name = false ) {
    global $current_user, $wp_roles;
    $current_user  = wp_get_current_user();
    $role_discount = 0;
    $role_name     = '';
    //$role = ?$current_user->roles[0]:'guest';
    $discount = maybe_unserialize( get_post_meta( $pid, '__wpdm_discount', true ) );

    $roles = $wp_roles->role_names;


    if ( is_user_logged_in() && is_array( $discount ) ) {
        foreach ( $current_user->roles as $role ) {
            if ( isset( $discount[ $role ] ) && $discount[ $role ] > $role_discount ) {
                $role_discount = $discount[ $role ];
                $role_name     = isset( $roles[ $role ] ) ? $roles[ $role ] : $role;
            }
        }
    }
    if ( ! is_user_logged_in() && is_array( $discount ) && isset( $discount['guest'] ) ) {
        $role_discount = $discount['guest'];
    }
    if ( ! is_array( $discount ) || count( $discount ) == 0 ) {
        return 0;
    }

    return $name ? $role_name : $role_discount;
}


function wpdmpp_price_range( $pid ) {
    $pre_licenses = wpdmpp_get_licenses();
    $license_infs = get_post_meta( $pid, "__wpdm_license", true );
    $license_infs = maybe_unserialize( $license_infs );
    $licprices    = array();

    $base_price  = get_post_meta( $pid, "__wpdm_base_price", true );
    $sales_price = wpdmpp_sales_price( $pid );
    $base_price  = intval( $sales_price ) > 0 ? $sales_price : $base_price;

    foreach ( $pre_licenses as $licid => $lic ) {
        if ( isset( $license_infs[ $licid ] ) && $license_infs[ $licid ]['active'] == 1 ) {
            $licprices[] = isset( $license_infs[ $licid ]['price'] ) ? $license_infs[ $licid ]['price'] : $base_price;
        }
    }

    $price_range = wpdmpp_price_format( (float) $base_price, true, true );

    if ( count( $licprices ) > 1 && get_post_meta( $pid, "__wpdm_enable_license", true ) == 1 ) {
        sort( $licprices );
        $fromprice   = $licprices[0];
        $sales_price = wpdmpp_sales_price( $pid );
        if ( $sales_price < $fromprice && $sales_price > 0 ) {
            $fromprice = $sales_price;
        }
        $price_range = wpdmpp_price_format( $fromprice, true, true ) . " &mdash; " . wpdmpp_price_format( end( $licprices ), true, true );
    }

    return $price_range;
}

function wpdmpp_order_id() {
    return Session::get( 'orderid' );
}

function wpdmpp_currency_sign() {
    $settings = get_option( '_wpdmpp_settings' );
    $currency = isset( $settings['currency'] ) ? $settings['currency'] : 'USD';
    $cdata    = \WPDMPP\Core\CurrencyService::getInstance()->getCurrency( $currency );
    $sign     = is_array( $cdata ) ? $cdata['symbol'] : '$';
    $sign     = apply_filters( "wpdmpp_currency_sign", $sign );

    return $sign;
}

function wpdmpp_currency_sign_position() {
    $settings          = get_option( '_wpdmpp_settings' );
    $currency_position = isset( $settings['currency_position'] ) ? $settings['currency_position'] : 'before';

    return $currency_position;
}

function wpdmpp_currency_code() {
    $settings = get_option( '_wpdmpp_settings' );
    $currency = isset( $settings['currency'] ) ? $settings['currency'] : 'USD';
    $currency = apply_filters( "wpdmpp_currency_code", $currency );

    return $currency;
}

/**
 * Validating download request using 'wpdm_onstart_download' WPDM hook
 *
 * @param $package
 *
 * @return mixed
 */
function wpdmpp_validate_download( $package ) {

    $price = wpdmpp_effective_price( $package['ID'] );
    if ( floatval( $price ) > 0 ) {

        // Check The Master Key
        if ( wpdm_query_var( 'masterkey' ) !== '' && WPDMPremiumPackage::authorize_masterkey() ) {
            return $package;
        }

        // Validate Download Key
        //wpdmdd(is_wpdmkey_valid($package['ID'], wpdm_query_var('_wpdmkey')));
        //wpdmdd(get_wpdmpp_option('authorize_masterkey'));
        if ( is_wpdmkey_valid( $package['ID'], wpdm_query_var( '_wpdmkey' ) ) === 1 && (int) get_wpdmpp_option( 'authorize_masterkey' ) === 1 ) {
            return $package;
        }

        if ( (int) Session::get( '__wpdmpp_authorized_download' ) === 1 ) {
            return $package;
        }

        Messages::error( 'You do not have permission to download this file', 1 );

    }

    return $package;

}

function wpdmpp_download_order_note_attachment() {
    global $current_user;
    $current_user = wp_get_current_user();
    if ( ! isset( $_GET['_atcdl'] ) || ! is_user_logged_in() ) {
        return false;
    }
    $key   = \WPDM\__\Crypt::Decrypt( esc_attr( $_GET['_atcdl'] ) );
    $key   = explode( "|||", $key );
    $order = \WPDMPP\Order\OrderService::instance()->getOrder( $key[0] );
    if ( ! $order || ( $order->getUserId() != $current_user->ID && ! current_user_can( 'manage_options' ) ) ) {
        wp_die( 'Unauthorized Access' );
    }
    $order_notes = $order->getNotes();
    $files    = $order_notes['messages'][ $key[1] ]['file'];
    $filename = preg_replace( "/^[0-9]+?wpdm_/", "", wpdm_basename( $key[2] ) );
    if ( in_array( $key[2], $files ) ) {
        wpdm_download_file( UPLOAD_DIR . $key[2], $filename );
        exit;
    }
}

/**
 * Return array of country objects
 * @return array
 */
function wpdmpp_get_countries() {
    global $wpdb;
    $countries = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_country order by country_name" );

    return $countries;
}

/**
 * Return Premium Package Template Directory
 * @return string
 */
function wpdmpp_tpl_dir() {
    return WPDMPP_TPL_DIR;
}

function wpdmpp_email_template_tags( $tags ) {
    $tags["{{orderid}}"]         = array( 'value' => '', 'desc' => 'Order ID' );
    $tags["{{items}}"]           = array( 'value' => '', 'desc' => 'List of purchased items' );
    $tags["{{order_url}}"]       = array( 'value' => '', 'desc' => 'Order URL' );
    $tags["{{guest_order_url}}"] = array( 'value' => '', 'desc' => 'Guest Order URL' );

    return $tags;
}

function wpdmpp_email_templates( $templates ) {
    $templates['purchase-confirmation-guest'] = array(
            'label'   => __( 'Purchase Confirmation - Guest', 'wpdmpro' ),
            'for'     => 'customer',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Thanks For Your Purchase', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'message'    => 'Hello ,<br/>Thanks for your order at [#sitename#].<br/>Your Order ID: [#orderid#]<br/>Purchased Items:<br/>[#items#]<br/>You need to create an account to access your order and to get future updates.<br/>Please click on the following link to create your account:<br/><a class="button green" style="display: block; text-align: center;" href="[#order_url#]">Signup</a>If you already have account simply click the above url and login<br/><br/>Best Regards,<br/>Sales Team<br/><b>[#sitename#]</b>'
            )
    );

    $templates['purchase-confirmation'] = array(
            'label'   => __( 'Purchase Confirmation', 'wpdmpro' ),
            'for'     => 'customer',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Thanks For Your Purchase', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'message'    => 'Hello ,<br/>Thanks for your order at [#sitename#].<br/>Your Order ID: [#orderid#]<br/>Purchased Items:<br/>[#items#]<br/>You can download your purchased item(s) from the following link:<br/><a href="[#order_url#]">[#order_url#]</a><br/><br/>Best Regards,<br/>Sales Team<br/><b>[#sitename#]</b>'
            )
    );

    $templates['subscription-reminder'] = array(
            'label'   => __( 'Subscription Reminder', 'wpdmpro' ),
            'for'     => 'customer',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( '[#sitename#] Subscription Reminder', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'message'    => 'Hello,<br/>Thanks for your continued support.<br/>We\'re sending this message to remind you that, as your subscription is active, your Order# [#orderid#] will be renewed automatically on [#expire_date#]. <br/><br/><strong>Associated Items:</strong><hr/>[#items#]<hr/><br/> <a href="[#order_url#]" style="display: block;text-align: center" class="button">Review Order</a><br/><br/>Best Regards,<br/>Sales Team<br/><b>[#sitename#]</b>'
            )
    );

    $templates['renew-confirmation'] = array(
            'label'   => __( 'Order Renew Confirmation', 'wpdmpro' ),
            'for'     => 'customer',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Order Renewed Successfully', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'message'    => 'Hello,<br/>Thanks for your continued support.<br/>Your Order# [#orderid#] is renewed successfully.<br/>As always, you can download the latest version from the following link:<br/><a href="[#order_url#]">[#order_url#]</a><br/><br/>Best Regards,<br/>Sales Team<br/><b>[#sitename#]</b>'
            )
    );

    $templates['sale-notification'] = array(
            'label'   => __( 'New Sale Notification', 'wpdmpro' ),
            'for'     => 'admin',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Congratulations! You have a sale.', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'to_email'   => get_option( 'admin_email' ),
                    'message'    => 'Hello ,<br/>Congratulations! You have a sale just now.<br/>Order ID: [#orderid#]<br/>Sold Items:<br/>[#items#]<br/>Review Order: [#order_url_admin#]'
            )
    );

    $templates['sale-notification-seller'] = array(
            'label'   => __( 'New Sale Notification', 'wpdmpro' ),
            'for'     => 'seller',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Congratulations! You have a sale.', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'to_email'   => get_option( 'admin_email' ),
                    'message'    => 'Hello ,<br/>Congratulations! You have a sale just now.<br/>Order ID: [#orderid#]<br/>Sold Items:<br/>[#items#]<br/>Review Order: [#order_url_seller#]'
            )
    );

    $templates['os-notification'] = array(
            'label'   => __( 'Order Status Notification', 'wpdmpro' ),
            'for'     => 'customer',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Order ([#orderid#]) Status Changed', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'message'    => 'Hello ,<br/>The order <strong>[#orderid#]</strong> is changed to <strong>[#order_status#]</strong><br/>Review Order: <a class="button button-green green" style="margin: 15px 0;display: block;text-align: center" href="[#order_url#]">Review Order</a><br/><br/>Best Regards,<br/>Sales Team<br/><b>[#sitename#]</b>'
            )
    );

    $templates['order-expire'] = array(
            'label'   => __( 'Order Expiry Notification', 'wpdmpro' ),
            'for'     => 'customer',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Your order is about to expire', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'message'    => 'Hello [#name#],<br/>Your order is about to expire.<br/>Order# [#orderid#]<br/><br/>Purchased Items# [#order_items#]<br/>Please renew your order to get continuous support and updates.<br/><a class="button" href="[#order_url#]">Renew Order</a><br/><br/>Best Regards,<br/>Sales Team<br/><b>[#sitename#]</b>'
            )
    );

    /*$templates['email-saved-cart'] = array(
        'label' => __('Email Saved Cart', 'wpdm-premium-packages'),
        'for' => 'customer',
        'plugin' => 'Premium Packages',
        'default' => array(
            'subject' => __('Someone sent you a cart!', 'wpdm-premium-packages'),
            'from_name' => get_option('blogname'),
            'from_email' => get_option('admin_email'),
            'message' => 'Hello,<br/>Someone sent you a cart from [#sitename#]:<br/>View Cart & Checkout from here:<br/><b><a href="[#carturl#]">[#carturl#]</a></b><br/>Best Regards,<br/>Sales Team<br/><b>[#sitename#]</b>'
        )
    );*/

    $templates['recovered-order-confirmation'] = array(
            'label'   => __( 'Recovered Order Confirmation', 'wpdmpro' ),
            'for'     => 'admin',
            'plugin'  => 'Premium Packages',
            'default' => array(
                    'subject'    => __( 'Congratulation! Order recovered successfully', 'wpdmpro' ),
                    'from_name'  => get_option( 'blogname' ),
                    'from_email' => get_option( 'admin_email' ),
                    'message'    => '<strong>Congratulation!</strong><br/>The following order has been recovered successfully.<br/>Order# {{orderid}}<br/><br/>Purchased Items# {{items}}<br/>Keep up good works!<br/><a style="display:block;text-align: center;margin-top: 15px;" class="button btn button-green" href="{{order_url}}">Review Order</a><br/><br/>Best Regards,<br/>Sales Team<br/><b>{{sitename}}</b>'
            )
    );

    $acre_count        = get_wpdmpp_option( 'acre_count', 0, 'int' );
    $acre_msg_template = [
            1 => 'Hello {{name}},<br/>It looks like you haven’t finished checking out yet. The good news? We saved your cart for you. Go on and complete your order now before your cart expires.<br/>Your cart:<br/>{{items}}<b><a style="display:block;text-align: center;margin-top: 15px;" class="button btn button-green" href="{{checkout_url}}">Checkout Now!</a></b><br/>Best Regards,<br/>Sales Team<br/><b>{{sitename}}</b>',
            2 => 'Hello {{name}},<br/>Thought, you have missed my last email. The good news? We saved your cart for you. Go on and complete your order now before your cart expires.<br/>Your cart:<br/>{{items}}<b><a style="display:block;text-align: center;margin-top: 15px;" class="button btn button-green" href="{{checkout_url}}">Checkout Now!</a></b><br/>Best Regards,<br/>Sales Team<br/><b>{{sitename}}</b>',
    ];
    if ( $acre_count > 0 ) {
        for ( $acre = 1; $acre <= $acre_count; $acre ++ ) {
            $templates[ 'order-recovery-email-' . $acre ] = array(
                    'label'   => sprintf( __( 'Abandoned Order Recovery Email %s', 'wpdm-premium-packages' ), $acre ),
                    'for'     => 'customer',
                    'plugin'  => 'Premium Packages',
                    'default' => array(
                            'subject'    => __( 'Your cart is waiting for you!', 'wpdm-premium-packages' ),
                            'from_name'  => get_option( 'blogname' ),
                            'from_email' => get_option( 'admin_email' ),
                            'message'    => wpdm_valueof( $acre_msg_template, $acre, "Add your abandoned order recovery email content # {$acre}" )
                    )
            );
        }
    }

    return $templates;
}

function wpdmpp_reactivate() {
    return __( "Database error detected. Please try deactivate and then reactivating plugin.", "wpdm-premium-packages" );
}

function wpdmpp_expiry_check() {
    $orderService = \WPDMPP\Order\OrderService::instance();
    $uid    = get_current_user_id();
    $orders = $orderService->getUserOrders( $uid );
    foreach ( $orders as $_order ) {
        $expire_date = $_order->getExpireDate() > 0 ? $_order->getExpireDate() : $_order->getDate() + ( get_wpdmpp_option( 'order_validity_period', 365 ) * 86400 );
        if ( time() > $expire_date && $_order->getOrderStatus() != 'Expired' ) {
            $orderService->updateOrder( array(
                    'order_status'   => 'Expired',
                    'payment_status' => 'Expired',
                    'expire_date'    => $expire_date
            ), $_order->getOrderId() );
        }
    }

}

function wpdmpp_sanitize_alphanum( $id ) {
    return preg_replace( '/[^a-zA-Z0-9 -]/', "", $id );
}

/**
 * @usage Format price
 *
 * @param $price
 *
 * @return string
 */
function wpdmpp_price_format( $price, $currency_sign = true, $thousand_separator = true ) {
    $ts            = $thousand_separator ? get_wpdmpp_option( 'thousand_separator' ) : '';
    $ds            = $thousand_separator ? get_wpdmpp_option( 'decimal_separator' ) : '.';
    $dp            = $thousand_separator ? (int) get_wpdmpp_option( 'decimal_points' ) : 2;
    $currency_sign = $currency_sign === true ? wpdmpp_currency_sign() : $currency_sign;
    $price         = (double) $price;
    $price         = number_format( $price, $dp, $ds, $ts );

    return ( get_wpdmpp_option( 'currency_position', 'before' ) === 'before' ) ? $currency_sign . $price : $price . $currency_sign;
}

function wpdmppdl_encode( $content ) {
    $content = json_encode( $content );
    $content = base64_encode( $content );
    $content = trim( $content, '=' );

    return $content;
}

function wpdmppdl_decode( $cyper ) {
    $jsonstr = base64_decode( $cyper );
    $json    = json_decode( $jsonstr, true );

    return $json;
}

/**
 * @usage Generate ordinal number
 *
 * @param $number
 *
 * @return string
 */
function wpdmpp_ordinal( $number ) {
    $ends = array( 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' );
    if ( ( ( $number % 100 ) >= 11 ) && ( ( $number % 100 ) <= 13 ) ) {
        return $number . 'th';
    } else {
        return $number . $ends[ $number % 10 ];
    }
}

function wpdmpp_forex_rate( $from, $to ) {
    $rate = get_option( "wppm_fx_rate_{$from}_{$to}" );
    if ( is_array( $rate ) && isset( $rate['expire'] ) && $rate['expire'] > time() ) {
        return $rate['rate'];
    }

    $curl = curl_init();

    curl_setopt_array( $curl, [
            CURLOPT_URL            => "https://v6.exchangerate-api.com/v6/e8767d62e2b5de59e1b9093c/pair/{$from}/{$to}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "GET"
    ] );

    $response = curl_exec( $curl );
    $err      = curl_error( $curl );

    curl_close( $curl );
    $response = json_decode( $response );
    update_option( "wppm_fx_rate_{$from}_{$to}", [ 'rate' => $response->conversion_rate, 'expire' => time() + 28800 ] );
}


function wpdmpp_search_products( $keyword = '' ) {
    $keyword = wpdm_query_var( 'search' ) ?: $keyword;
    if ( $keyword ) {
        $query = new Query();
        $query->search( $keyword );
        $query->meta( '__wpdm_base_price', 0, '>' );
        $query->meta_relation( 'AND' );
        $query->process();
        $packages = $query->packages();
        foreach ( $packages as &$package ) {
            $package->license = wpdmpp_product_license_options( $package->ID );
        }
        wp_send_json( [ 'total' => $query->count, 'packages' => $query->packages(), 'q' => $query->params ] );
    }
}


