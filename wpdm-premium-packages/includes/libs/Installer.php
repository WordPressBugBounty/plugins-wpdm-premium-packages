<?php


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


    class WPDMPP_INSTALLER
    {

        public $table;
        public $columns;

        /**
         * @var float
         */
        private $dbVersion = 589.0;


        function __construct()
        {

        }

	    public static function dbVersion() {
		    $inst = new WPDMPP_INSTALLER();
		    return $inst->dbVersion;
	    }

	    public static function dbUpdateRequired() {
		    return ( WPDMPP_INSTALLER::dbVersion() !== (double) get_option( '__wpdmpp_db_version' ) );
	    }

		public static function updateDB() {
			global $wpdb;
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_orders` (
                      `order_id` varchar(100) NOT NULL,
                      `trans_id` varchar(200) NOT NULL,
                      `title` varchar(255) NOT NULL,
                      `date` int(11) NOT NULL,
                      `expire_date` int(11) NOT NULL,
                      `auto_renew` int(11) NOT NULL DEFAULT '0',
                      `items` text NOT NULL,
                      `cart_data` text NOT NULL,
                      `total` double NOT NULL,
                      `order_status` enum('Pending','Processing','Completed','Cancelled','Expired') NOT NULL,
                      `payment_status` enum('Pending','Processing','Completed','Bonus','Gifted','Cancelled','Refunded','Disputed','Expired') NOT NULL,
                      `uid` int(11) NOT NULL,
                      `ipn` text NOT NULL,
                      `unit_prices` text NOT NULL,
                      `subtotal` double NOT NULL,
                      `discount` double NOT NULL,
                      `tax` float NOT NULL,
                      `order_notes` text CHARACTER SET utf8 COLLATE utf8_bin,
                      `payment_method` varchar(255) DEFAULT NULL,
                      `billing_info` text,
                      `cart_discount` float DEFAULT NULL,
                      `currency` text NOT NULL,
                      `download` int(11) NOT NULL,
                      `IP` varchar(250) NULL,
                      `coupon_discount` float NOT NULL,
                      `coupon_code` VARCHAR(100) NULL,
                      `refund` double NOT NULL DEFAULT '0',
                      PRIMARY KEY (`order_id`)
                    
                    )";
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_order_renews` ( 
                      `ID` INT NOT NULL AUTO_INCREMENT , 
                      `order_id` VARCHAR(80) NOT NULL , 
    				  `total` double NOT NULL,
                      `subscription_id` VARCHAR(200) NOT NULL , 
    				  `ipn` TEXT DEFAULT NULL,
                      `date` INT NOT NULL , 
                      PRIMARY KEY (`ID`)
                    )";

			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_order_items` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `oid` varchar(255) NOT NULL,
                      `pid` varchar(255) NOT NULL,
                      `product_name` text DEFAULT NULL,
                      `license` text,
                      `extra_gigs` text NOT NULL,
                      `quantity` int(11) NOT NULL,
                      `price` double NOT NULL,
                      `status` int(11) NOT NULL,
                      `coupon` varchar(255) DEFAULT NULL,
                      `coupon_discount` float DEFAULT NULL,
                      `role_discount` float DEFAULT NULL,
                      `site_commission` float DEFAULT NULL,
                      `date` date NOT NULL,
                      `year` int(11) NOT NULL,
                      `month` int(11) NOT NULL,
                      `day` int(11) NOT NULL,
                      `sid` int(11) NOT NULL,
                      `cid` int(11) NOT NULL,
                      PRIMARY KEY (`id`)
                    )";


			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_country` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `country_code` varchar(50) DEFAULT NULL,
                      `country_name` varchar(255) DEFAULT NULL,
                      `status` int(11) DEFAULT NULL,
                      PRIMARY KEY (`id`)
                    )";

			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_licenses` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `domain` text NOT NULL,
                      `licenseno` varchar(255) NOT NULL,
                      `status` int(11) NOT NULL,
                      `oid` varchar(100) NOT NULL,
                      `pid` int(11) NOT NULL,
                      `activation_date` int(11) NOT NULL,
                      `expire_date` int(11) NOT NULL,
                      `expire_period` int(11) NOT NULL,
                      `domain_limit` int(11) NOT NULL,
                      PRIMARY KEY (`id`)
                    )";

			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_coupons` (
                      `ID` int(11) NOT NULL AUTO_INCREMENT,
                      `code` varchar(255) NOT NULL,
                      `description` text CHARACTER SET utf8 NOT NULL,
                      `type` enum('percent','fixed') NOT NULL,
                      `discount` double NOT NULL,
                      `min_order_amount` int(11) NOT NULL,
                      `max_order_amount` int(11) NOT NULL,
                      `product` int(11) NOT NULL,
                      `allowed_emails` text NOT NULL,
                      `expire_date` int(11) NOT NULL,
                      `usage_limit` int(11) NOT NULL,
                      `used` int(11) NOT NULL,
                      `auto_apply` INT(1) NOT NULL DEFAULT '0',
                      PRIMARY KEY (`ID`)
                    )";
			$sql[] = "CREATE TABLE IF NOT EXISTS  `{$wpdb->prefix}ahm_refunds` (
                      `ID` int(11) NOT NULL,
                      `order_id` varchar(200) NOT NULL,
                      `amount` double NOT NULL,
                      `reason` text NOT NULL,
                      `date` int(11) NOT NULL
                    )";

			$sql[] = "INSERT IGNORE INTO `{$wpdb->prefix}ahm_country` (`id`, `country_code`, `country_name`, `status`) VALUES
                    (1, 'AD', 'ANDORRA', NULL),
                    (2, 'AE', 'UNITED ARAB EMIRATES', NULL),
                    (3, 'AF', 'AFGHANISTAN', NULL),
                    (4, 'AG', 'ANTIGUA AND BARBUDA', NULL),
                    (5, 'AI', 'ANGUILLA', NULL),
                    (6, 'AL', 'ALBANIA', NULL),
                    (7, 'AM', 'ARMENIA', NULL),
                    (8, 'AN', 'NETHERLANDS ANTILLES', NULL),
                    (9, 'AO', 'ANGOLA', NULL),
                    (10, 'AQ', 'ANTARCTICA', NULL),
                    (11, 'AR', 'ARGENTINA', NULL),
                    (12, 'AS', 'AMERICAN SAMOA', NULL),
                    (13, 'AT', 'AUSTRIA', NULL),
                    (14, 'AU', 'AUSTRALIA', NULL),
                    (15, 'AW', 'ARUBA', NULL),
                    (16, 'AZ', 'AZERBAIJAN', NULL),
                    (17, 'BA', 'BOSNIA AND HERZEGOVINA', NULL),
                    (18, 'BB', 'BARBADOS', NULL),
                    (19, 'BD', 'BANGLADESH', NULL),
                    (20, 'BE', 'BELGIUM', NULL),
                    (21, 'BF', 'BURKINA FASO', NULL),
                    (22, 'BG', 'BULGARIA', NULL),
                    (23, 'BH', 'BAHRAIN', NULL),
                    (24, 'BI', 'BURUNDI', NULL),
                    (25, 'BJ', 'BENIN', NULL),
                    (26, 'BM', 'BERMUDA', NULL),
                    (27, 'BN', 'BRUNEI DARUSSALAM', NULL),
                    (28, 'BO', 'BOLIVIA', NULL),
                    (29, 'BR', 'BRAZIL', NULL),
                    (30, 'BS', 'BAHAMAS', NULL),
                    (31, 'BT', 'BHUTAN', NULL),
                    (32, 'BV', 'BOUVET ISLAND', NULL),
                    (33, 'BW', 'BOTSWANA', NULL),
                    (34, 'BY', 'BELARUS', NULL),
                    (35, 'BZ', 'BELIZE', NULL),
                    (36, 'CA', 'CANADA', NULL),
                    (37, 'CC', 'COCOS (KEELING) ISLANDS', NULL),
                    (38, 'CD', 'CONGO, THE DEMOCRATIC REPUBLIC OF THE', NULL),
                    (39, 'CF', 'CENTRAL AFRICAN REPUBLIC', NULL),
                    (40, 'CG', 'CONGO', NULL),
                    (41, 'CH', 'SWITZERLAND', NULL),
                    (42, 'CI', 'COTE DIVOIRE', NULL),
                    (43, 'CK', 'COOK ISLANDS', NULL),
                    (44, 'CL', 'CHILE', NULL),
                    (45, 'CM', 'CAMEROON', NULL),
                    (46, 'CN', 'CHINA', NULL),
                    (47, 'CO', 'COLOMBIA', NULL),
                    (48, 'CR', 'COSTA RICA', NULL),
                    (49, 'CS', 'SERBIA AND MONTENEGRO', NULL),
                    (50, 'CU', 'CUBA', NULL),
                    (51, 'CV', 'CAPE VERDE', NULL),
                    (52, 'CX', 'CHRISTMAS ISLAND', NULL),
                    (53, 'CY', 'CYPRUS', NULL),
                    (54, 'CZ', 'CZECH REPUBLIC', NULL),
                    (55, 'DE', 'GERMANY', NULL),
                    (56, 'DJ', 'DJIBOUTI', NULL),
                    (57, 'DK', 'DENMARK', NULL),
                    (58, 'DM', 'DOMINICA', NULL),
                    (59, 'DO', 'DOMINICAN REPUBLIC', NULL),
                    (60, 'DZ', 'ALGERIA', NULL),
                    (61, 'EC', 'ECUADOR', NULL),
                    (62, 'EE', 'ESTONIA', NULL),
                    (63, 'EG', 'EGYPT', NULL),
                    (64, 'EH', 'WESTERN SAHARA', NULL),
                    (65, 'ER', 'ERITREA', NULL),
                    (66, 'ES', 'SPAIN', NULL),
                    (67, 'ET', 'ETHIOPIA', NULL),
                    (68, 'FI', 'FINLAND', NULL),
                    (69, 'FJ', 'FIJI', NULL),
                    (70, 'FK', 'FALKLAND ISLANDS (MALVINAS)', NULL),
                    (71, 'FM', 'MICRONESIA, FEDERATED STATES OF', NULL),
                    (72, 'FO', 'FAROE ISLANDS', NULL),
                    (73, 'FR', 'FRANCE', NULL),
                    (74, 'GA', 'GABON', NULL),
                    (75, 'GB', 'UNITED KINGDOM', NULL),
                    (76, 'GD', 'GRENADA', NULL),
                    (77, 'GE', 'GEORGIA', NULL),
                    (78, 'GF', 'FRENCH GUIANA', NULL),
                    (79, 'GH', 'GHANA', NULL),
                    (80, 'GI', 'GIBRALTAR', NULL),
                    (81, 'GL', 'GREENLAND', NULL),
                    (82, 'GM', 'GAMBIA', NULL),
                    (83, 'GN', 'GUINEA', NULL),
                    (84, 'GP', 'GUADELOUPE', NULL),
                    (85, 'GQ', 'EQUATORIAL GUINEA', NULL),
                    (86, 'GR', 'GREECE', NULL),
                    (87, 'GS', 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS', NULL),
                    (88, 'GT', 'GUATEMALA', NULL),
                    (89, 'GU', 'GUAM', NULL),
                    (90, 'GW', 'GUINEA-BISSAU', NULL),
                    (91, 'GY', 'GUYANA', NULL),
                    (92, 'HK', 'HONG KONG', NULL),
                    (93, 'HM', 'HEARD ISLAND AND MCDONALD ISLANDS', NULL),
                    (94, 'HN', 'HONDURAS', NULL),
                    (95, 'HR', 'CROATIA', NULL),
                    (96, 'HT', 'HAITI', NULL),
                    (97, 'HU', 'HUNGARY', NULL),
                    (98, 'ID', 'INDONESIA', NULL),
                    (99, 'IE', 'IRELAND', NULL),
                    (100, 'IL', 'ISRAEL', NULL),
                    (101, 'IN', 'INDIA', NULL),
                    (102, 'IO', 'BRITISH INDIAN OCEAN TERRITORY', NULL),
                    (103, 'IQ', 'IRAQ', NULL),
                    (104, 'IR', 'IRAN, ISLAMIC REPUBLIC OF', NULL),
                    (105, 'IS', 'ICELAND', NULL),
                    (106, 'IT', 'ITALY', NULL),
                    (107, 'JM', 'JAMAICA', NULL),
                    (108, 'JO', 'JORDAN', NULL),
                    (109, 'JP', 'JAPAN', NULL),
                    (110, 'KE', 'KENYA', NULL),
                    (111, 'KG', 'KYRGYZSTAN', NULL),
                    (112, 'KH', 'CAMBODIA', NULL),
                    (113, 'KI', 'KIRIBATI', NULL),
                    (114, 'KM', 'COMOROS', NULL),
                    (115, 'KN', 'SAINT KITTS AND NEVIS', NULL),
                    (116, 'KP', 'KOREA, DEMOCRATIC PEOPLE''S REPUBLIC OF', NULL),
                    (117, 'KR', 'KOREA, REPUBLIC OF', NULL),
                    (118, 'KW', 'KUWAIT', NULL),
                    (119, 'KY', 'CAYMAN ISLANDS', NULL),
                    (120, 'KZ', 'KAZAKHSTAN', NULL),
                    (121, 'LA', 'LAO PEOPLE''S DEMOCRATIC REPUBLIC', NULL),
                    (122, 'LB', 'LEBANON', NULL),
                    (123, 'LC', 'SAINT LUCIA', NULL),
                    (124, 'LI', 'LIECHTENSTEIN', NULL),
                    (125, 'LK', 'SRI LANKA', NULL),
                    (126, 'LR', 'LIBERIA', NULL),
                    (127, 'LS', 'LESOTHO', NULL),
                    (128, 'LT', 'LITHUANIA', NULL),
                    (129, 'LU', 'LUXEMBOURG', NULL),
                    (130, 'LV', 'LATVIA', NULL),
                    (131, 'LY', 'LIBYAN ARAB JAMAHIRIYA', NULL),
                    (132, 'MA', 'MOROCCO', NULL),
                    (133, 'MC', 'MONACO', NULL),
                    (134, 'MD', 'MOLDOVA, REPUBLIC OF', NULL),
                    (135, 'MG', 'MADAGASCAR', NULL),
                    (136, 'MH', 'MARSHALL ISLANDS', NULL),
                    (137, 'MK', 'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF', NULL),
                    (138, 'ML', 'MALI', NULL),
                    (139, 'MM', 'MYANMAR', NULL),
                    (140, 'MN', 'MONGOLIA', NULL),
                    (141, 'MO', 'MACAO', NULL),
                    (142, 'MP', 'NORTHERN MARIANA ISLANDS', NULL),
                    (143, 'MQ', 'MARTINIQUE', NULL),
                    (144, 'MR', 'MAURITANIA', NULL),
                    (145, 'MS', 'MONTSERRAT', NULL),
                    (146, 'MT', 'MALTA', NULL),
                    (147, 'MU', 'MAURITIUS', NULL),
                    (148, 'MV', 'MALDIVES', NULL),
                    (149, 'MW', 'MALAWI', NULL),
                    (150, 'MX', 'MEXICO', NULL),
                    (151, 'MY', 'MALAYSIA', NULL),
                    (152, 'MZ', 'MOZAMBIQUE', NULL),
                    (153, 'NA', 'NAMIBIA', NULL),
                    (154, 'NC', 'NEW CALEDONIA', NULL),
                    (155, 'NE', 'NIGER', NULL),
                    (156, 'NF', 'NORFOLK ISLAND', NULL),
                    (157, 'NG', 'NIGERIA', NULL),
                    (158, 'NI', 'NICARAGUA', NULL),
                    (159, 'NL', 'NETHERLANDS', NULL),
                    (160, 'NO', 'NORWAY', NULL),
                    (161, 'NP', 'NEPAL', NULL),
                    (162, 'NR', 'NAURU', NULL),
                    (163, 'NU', 'NIUE', NULL),
                    (164, 'NZ', 'NEW ZEALAND', NULL),
                    (165, 'OM', 'OMAN', NULL),
                    (166, 'PA', 'PANAMA', NULL),
                    (167, 'PE', 'PERU', NULL),
                    (168, 'PF', 'FRENCH POLYNESIA', NULL),
                    (169, 'PG', 'PAPUA NEW GUINEA', NULL),
                    (170, 'PH', 'PHILIPPINES', NULL),
                    (171, 'PK', 'PAKISTAN', NULL),
                    (172, 'PL', 'POLAND', NULL),
                    (173, 'PM', 'SAINT PIERRE AND MIQUELON', NULL),
                    (174, 'PN', 'PITCAIRN', NULL),
                    (175, 'PR', 'PUERTO RICO', NULL),
                    (176, 'PS', 'PALESTINIAN TERRITORY, OCCUPIED', NULL),
                    (177, 'PT', 'PORTUGAL', NULL),
                    (178, 'PW', 'PALAU', NULL),
                    (179, 'PY', 'PARAGUAY', NULL),
                    (180, 'QA', 'QATAR', NULL),
                    (181, 'RE', 'REUNION', NULL),
                    (182, 'RO', 'ROMANIA', NULL),
                    (183, 'RU', 'RUSSIAN FEDERATION', NULL),
                    (184, 'RW', 'RWANDA', NULL),
                    (185, 'SA', 'SAUDI ARABIA', NULL),
                    (186, 'SB', 'SOLOMON ISLANDS', NULL),
                    (187, 'SC', 'SEYCHELLES', NULL),
                    (188, 'SD', 'SUDAN', NULL),
                    (189, 'SE', 'SWEDEN', NULL),
                    (190, 'SG', 'SINGAPORE', NULL),
                    (191, 'SH', 'SAINT HELENA', NULL),
                    (192, 'SI', 'SLOVENIA', NULL),
                    (193, 'SJ', 'SVALBARD AND JAN MAYEN', NULL),
                    (194, 'SK', 'SLOVAKIA', NULL),
                    (195, 'SL', 'SIERRA LEONE', NULL),
                    (196, 'SM', 'SAN MARINO', NULL),
                    (197, 'SN', 'SENEGAL', NULL),
                    (198, 'SO', 'SOMALIA', NULL),
                    (199, 'SR', 'SURINAME', NULL),
                    (200, 'ST', 'SAO TOME AND PRINCIPE', NULL),
                    (201, 'SV', 'EL SALVADOR', NULL),
                    (202, 'SY', 'SYRIAN ARAB REPUBLIC', NULL),
                    (203, 'SZ', 'SWAZILAND', NULL),
                    (204, 'TC', 'TURKS AND CAICOS ISLANDS', NULL),
                    (205, 'TD', 'CHAD', NULL),
                    (206, 'TF', 'FRENCH SOUTHERN TERRITORIES', NULL),
                    (207, 'TG', 'TOGO', NULL),
                    (208, 'TH', 'THAILAND', NULL),
                    (209, 'TJ', 'TAJIKISTAN', NULL),
                    (210, 'TK', 'TOKELAU', NULL),
                    (211, 'TL', 'TIMOR-LESTE', NULL),
                    (212, 'TM', 'TURKMENISTAN', NULL),
                    (213, 'TN', 'TUNISIA', NULL),
                    (214, 'TO', 'TONGA', NULL),
                    (215, 'TR', 'TURKEY', NULL),
                    (216, 'TT', 'TRINIDAD AND TOBAGO', NULL),
                    (217, 'TV', 'TUVALU', NULL),
                    (218, 'TW', 'TAIWAN, PROVINCE OF CHINA', NULL),
                    (219, 'TZ', 'TANZANIA, UNITED REPUBLIC OF', NULL),
                    (220, 'UA', 'UKRAINE', NULL),
                    (221, 'UG', 'UGANDA', NULL),
                    (222, 'UM', 'UNITED STATES MINOR OUTLYING ISLANDS', NULL),
                    (223, 'US', 'UNITED STATES', NULL),
                    (224, 'UY', 'URUGUAY', NULL),
                    (225, 'UZ', 'UZBEKISTAN', NULL),
                    (226, 'VA', 'HOLY SEE (VATICAN CITY STATE)', NULL),
                    (227, 'VC', 'SAINT VINCENT AND THE GRENADINES', NULL),
                    (228, 'VE', 'VENEZUELA', NULL),
                    (229, 'VG', 'VIRGIN ISLANDS, BRITISH', NULL),
                    (230, 'VI', 'VIRGIN ISLANDS, U.S.', NULL),
                    (231, 'VN', 'VIET NAM', NULL),
                    (232, 'VU', 'VANUATU', NULL),
                    (233, 'WF', 'WALLIS AND FUTUNA', NULL),
                    (234, 'WS', 'SAMOA', NULL),
                    (235, 'YE', 'YEMEN', NULL),
                    (236, 'YT', 'MAYOTTE', NULL),
                    (237, 'ZA', 'SOUTH AFRICA', NULL),
                    (238, 'ZM', 'ZAMBIA', NULL),
                    (239, 'ZW', 'ZIMBABWE', NULL);
                     ";

			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_withdraws` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `uid` int(11) NOT NULL DEFAULT '0',
                      `date` int(11) NOT NULL DEFAULT '0',
                      `amount` double NOT NULL DEFAULT '0',
                      `payment_method` varchar(255) NOT NULL,
                      `payment_account` varchar(255) NOT NULL,
                      `status` int(11) NOT NULL DEFAULT '1',
                      `execution_date` int(11) NOT NULL DEFAULT '0',
                      PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB ";

			// Exchange rates, stored as dated snapshots rather than a single mutable
			// row, so the rate that applied on the day of an order can be recovered and
			// a refresh never rewrites history. One row per pair per fetch.
			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpdmpp_rates` (
                      `id` bigint(20) NOT NULL AUTO_INCREMENT,
                      `base_currency` varchar(8) NOT NULL,
                      `quote_currency` varchar(8) NOT NULL,
                      `rate` decimal(24,12) NOT NULL,
                      `provider` varchar(60) NOT NULL DEFAULT '',
                      `fetched_at` int(11) NOT NULL DEFAULT '0',
                      PRIMARY KEY (`id`),
                      KEY `idx_pair_fetched` (`base_currency`,`quote_currency`,`fetched_at`)
                    ) ENGINE=InnoDB";

			$sql[] = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ahm_acr_emails` (
					  `ID` int(11) NOT NULL AUTO_INCREMENT,
					  `order_id` varchar(100) NOT NULL,
					  `user_id` int(11) NOT NULL,
					  `name` varchar(100) NOT NULL,
					  `email` varchar(255) NOT NULL,
					  `order_date` int(11) NOT NULL,
					  `stage` int(11) NOT NULL,
					  `email_date` int(11) NOT NULL,
					  `activity_log` text NOT NULL,
					  `sent` int(11) NOT NULL,
					  PRIMARY KEY (`ID`),
					  UNIQUE KEY `order_stage` (`order_id`,`stage`)
					) ENGINE=InnoDB";

			foreach ($sql as $qry) {
				$wpdb->query($qry);
			}

			$installer = new WPDMPP_INSTALLER();

			$installer->changeColumn('ahm_coupons', 'ID', 'ID', 'INT(11) NOT NULL AUTO_INCREMENT');
			$installer->addColumn('ahm_withdraws', 'payment_method', 'VARCHAR( 255 ) NOT NULL');
			$installer->addColumn('ahm_coupons', 'used', 'INT NOT NULL');
			$installer->addColumn('ahm_coupons', 'auto_apply', "INT(1) NOT NULL DEFAULT '0'");
			$installer->changeColumn('ahm_order_items', 'pid', 'pid', 'VARCHAR(50) NOT NULL');
			$installer->changeColumn('ahm_order_items', 'variations', 'extra_gigs', 'TEXT NOT NULL');
			$installer->addColumn('ahm_order_items', 'product_type', ' VARCHAR( 255 ) NULL AFTER `pid`');
			$installer->addColumn('ahm_order_items', 'product_name', ' TEXT NULL AFTER `pid`');
			$installer->addColumn('ahm_order_items', 'coupon', 'VARCHAR( 255 ) NULL');
			$installer->addColumn('ahm_order_items', 'coupon_amount', 'FLOAT NULL');
			$installer->addColumn('ahm_order_items', 'site_commission', "FLOAT NOT NULL DEFAULT '0'");
			$installer->addColumn('ahm_order_items', 'extra_gigs', "TEXT NOT NULL AFTER `pid`");
			$installer->addColumn('ahm_order_items', 'role_discount', "FLOAT NOT NULL");
			$installer->addColumn('ahm_order_items', 'date', "DATE NOT NULL");
			$installer->addColumn('ahm_order_items', 'year', "INT NOT NULL");
			$installer->addColumn('ahm_order_items', 'month', "INT NOT NULL");
			$installer->addColumn('ahm_order_items', 'day', "INT NOT NULL");
			$installer->addColumn('ahm_order_items', 'sid', "INT NOT NULL");
			$installer->addColumn('ahm_order_items', 'cid', "INT NOT NULL");
			$installer->addColumn('ahm_order_items', 'license', "TEXT NULL AFTER `pid`");
			$installer->changeColumn('ahm_order_items', 'coupon_amount', 'coupon_discount', "FLOAT NULL DEFAULT NULL");
			$installer->addColumn('ahm_orders', 'IP', "VARCHAR(250) NULL");
			$installer->changeColumn('ahm_orders', 'IP', 'IP', 'VARCHAR(250) NULL');
			$installer->addColumn('ahm_orders', 'refund', "DOUBLE NOT NULL DEFAULT '0'");
			$installer->addColumn('ahm_orders', 'discount', "FLOAT NOT NULL");
			$installer->addColumn('ahm_orders', 'coupon_discount', "FLOAT NOT NULL");
			$installer->addColumn('ahm_orders', 'tax', "FLOAT NOT NULL");
			$installer->addColumn('ahm_orders', 'currency', "TEXT NOT NULL");
			$installer->addColumn('ahm_orders', 'order_notes', "TEXT NOT NULL");
			$installer->addColumn('ahm_orders', 'download', "INT NOT NULL");
			$installer->addColumn('ahm_orders', 'coupon_code', "VARCHAR( 100 ) NOT NULL");
			$installer->addColumn('ahm_orders', 'ipn', "TEXT NOT NULL");
			$installer->addColumn('ahm_orders', 'unit_prices', "TEXT NOT NULL");
			$installer->addColumn('ahm_orders', 'billing_info', "TEXT NOT NULL");
			$installer->addColumn('ahm_orders', 'expire_date', "INT NOT NULL");
			$installer->addColumn('ahm_orders', 'trans_id', "VARCHAR( 200 ) NOT NULL");
			$installer->addColumn('ahm_orders', 'subtotal', "DOUBLE NOT NULL");
			$installer->addColumn('ahm_orders', 'auto_renew', "INT NOT NULL DEFAULT '0'");
			$installer->addColumn('ahm_orders', 'meta_data', "TEXT NOT NULL");
			$installer->addColumn('ahm_order_renews', 'ipn', "TEXT DEFAULT NULL");
			$installer->addColumn('ahm_order_renews', 'total', "DOUBLE NOT NULL");
			$installer->changeColumn('ahm_orders', 'order_status', 'order_status', "ENUM('Pending','Processing','Completed','Cancelled','Expired') NOT NULL");
			$installer->changeColumn('ahm_orders', 'payment_status', 'payment_status', "ENUM('Pending','Processing','Completed','Bonus','Gifted','Cancelled','Refunded','Disputed','Expired')  NOT NULL");

			$installer->addColumn('ahm_licenses', 'domain_limit', "INT NOT NULL DEFAULT '0'");
			$installer->addColumn('ahm_withdraws', 'execution_date', "INT NOT NULL DEFAULT '0'");
			$installer->addColumn('ahm_withdraws', 'payment_account', "VARCHAR( 255 ) NOT NULL");

			// --- Multi-currency reporting (db 587) -------------------------------
			// Money is stored twice: the presentment amount the customer was charged,
			// in the order's own currency, and a base-currency equivalent converted at
			// the rate that applied when the order was placed. Reports sum the base
			// columns, which is the only way a total spanning currencies can be
			// meaningful. The rate is captured per order and never recalculated, so a
			// historical figure does not move when today's rates do.
			$installer->addColumn('ahm_orders', 'currency_code', "VARCHAR(8) NOT NULL DEFAULT ''");
			$installer->addColumn('ahm_orders', 'base_currency', "VARCHAR(8) NOT NULL DEFAULT ''");
			$installer->addColumn('ahm_orders', 'exchange_rate', "DOUBLE NOT NULL DEFAULT '1'");
			$installer->addColumn('ahm_orders', 'base_total', "DOUBLE NOT NULL DEFAULT '0'");

			$installer->addColumn('ahm_order_items', 'base_price', "DOUBLE NOT NULL DEFAULT '0'");
			$installer->addColumn('ahm_order_items', 'base_site_commission', "DOUBLE NOT NULL DEFAULT '0'");

			$installer->addColumn('ahm_order_renews', 'currency_code', "VARCHAR(8) NOT NULL DEFAULT ''");
			$installer->addColumn('ahm_order_renews', 'exchange_rate', "DOUBLE NOT NULL DEFAULT '1'");
			$installer->addColumn('ahm_order_renews', 'base_total', "DOUBLE NOT NULL DEFAULT '0'");

			$installer->addColumn('ahm_withdraws', 'currency_code', "VARCHAR(8) NOT NULL DEFAULT ''");

			$installer->backfillBaseAmounts();

			// Add database indexes for frequently queried columns (performance optimization)
			// ahm_orders indexes
			$installer->addIndex( 'ahm_orders', 'uid' );
			$installer->addIndex( 'ahm_orders', 'date' );
			$installer->addIndex( 'ahm_orders', 'expire_date' );
			$installer->addIndex( 'ahm_orders', 'order_status' );
			$installer->addIndex( 'ahm_orders', 'payment_status' );
			$installer->addCompositeIndex( 'ahm_orders', [ 'uid', 'order_status' ], 'idx_uid_order_status' );
			$installer->addCompositeIndex( 'ahm_orders', [ 'payment_status', 'date' ], 'idx_payment_date' );

			// ahm_order_items indexes
			$installer->addIndex( 'ahm_order_items', 'oid' );
			$installer->addIndex( 'ahm_order_items', 'pid' );
			$installer->addIndex( 'ahm_order_items', 'sid' );
			$installer->addIndex( 'ahm_order_items', 'cid' );
			$installer->addCompositeIndex( 'ahm_order_items', [ 'pid', 'oid' ], 'idx_pid_oid' );

			// ahm_licenses indexes
			$installer->addIndex( 'ahm_licenses', 'licenseno' );
			$installer->addIndex( 'ahm_licenses', 'oid' );
			$installer->addIndex( 'ahm_licenses', 'pid' );
			$installer->addIndex( 'ahm_licenses', 'status' );
			$installer->addCompositeIndex( 'ahm_licenses', [ 'licenseno', 'status' ], 'idx_license_status' );

			// ahm_order_renews indexes
			$installer->addIndex( 'ahm_order_renews', 'order_id' );
			$installer->addIndex( 'ahm_order_renews', 'date' );

			// ahm_coupons indexes
			$installer->addIndex( 'ahm_coupons', 'code' );
			$installer->addIndex( 'ahm_coupons', 'expire_date' );

			// ahm_withdraws indexes
			$installer->addIndex( 'ahm_withdraws', 'uid' );
			$installer->addIndex( 'ahm_withdraws', 'status' );

			// --- Display-only currency (db 589) ----------------------------------
			$installer->normaliseCartsToStoreCurrency();

			update_option( '__wpdmpp_db_version', $installer->dbVersion, false );
		}

        public static function init()
        {
            global $wpdb;

	        self::updateDB();

	        $orders_page = $cart_id = null;

            if (!$wpdb->get_var("select id from {$wpdb->prefix}posts where post_type='page' AND post_content like '%[wpdmpp_cart]%'")) {
                $cart_id = wp_insert_post(array('post_title' => 'Cart', 'post_content' => '[wpdmpp_cart]', 'post_type' => 'page', 'post_status' => 'publish'));
            }
	        if (!$wpdb->get_var("select id from {$wpdb->prefix}posts where post_type='page' AND (post_content like '%[wpdmpp_purchases]%' or post_content like '%[wpdm_user_dashboard%')")) {
		        $orders_page = wp_insert_post(array('post_title' => 'Purchases', 'post_content' => '[wpdmpp_purchases]', 'post_type' => 'page', 'post_status' => 'publish'));
	        }

            if (!empty($cart_id)) $_wpdmpp_settings['page_id'] = $cart_id;
            if (!empty($orders_page)) $_wpdmpp_settings['orders_page_id'] = $orders_page;
            $_wpdmpp_settings['continue_shopping_url'] = site_url('/');
            $_wpdmpp_settings['wpdmpp_after_addtocart_redirect'] = 1;
            $_wpdmpp_settings['PayPal']['enabled'] = 1;
            $_wpdmpp_settings['PayPal']['Paypal_mode'] = 'live';
            if (!empty($orders_page)) $_wpdmpp_settings['PayPal']['return_url'] = get_permalink($orders_page);

            if (!get_option('_wpdmpp_settings')) {
                update_option('_wpdmpp_settings', $_wpdmpp_settings);
            }


            $sub = get_role('subscriber');
            $cus = get_role('wpdmppcustomer');
            if (!$cus) {
                $caps = isset($sub, $sub->capabilities) ? $sub->capabilities : array();
                add_role('wpdmpp_customer', 'Customer', $caps);
            }
			//\WPDM\__\CronJob::create("\WPDM\__\EmailCron", $data, $execute_at);
        }


        /**
         * Seed the base-currency columns for rows written before db 587.
         *
         * Every existing row predates multi-currency, so its amount is already in
         * whatever single currency the store was running - that is exactly what the
         * base amount means, and rate 1.0 is the truthful record of it. Rows are only
         * touched while their base amount is still zero, so this is safe to re-run and
         * never overwrites a rate captured at checkout.
         *
         * Orders whose stored currency differs from the current store currency are
         * deliberately left with a rate of 1.0 rather than being converted at today's
         * rate, which would invent a number nobody was ever charged. They are reported
         * by countLegacyForeignOrders() so an admin can correct them knowingly.
         *
         * @return void
         */
        function backfillBaseAmounts()
        {
            global $wpdb;

            $base = function_exists('wpdmpp_base_currency_code')
                ? wpdmpp_base_currency_code()
                : (function_exists('wpdmpp_currency_code') ? wpdmpp_currency_code() : 'USD');

            $orders = "{$wpdb->prefix}ahm_orders";
            $items  = "{$wpdb->prefix}ahm_order_items";
            $renews = "{$wpdb->prefix}ahm_order_renews";

            // ahm_orders.currency holds a serialised ['sign','code'] pair, so the code
            // is pulled out in PHP rather than with string surgery in SQL.
            $rows = $wpdb->get_results("SELECT order_id, currency FROM {$orders} WHERE currency_code = ''");
            foreach ($rows as $row) {
                $data = maybe_unserialize($row->currency);
                $code = is_array($data) && !empty($data['code']) ? substr((string) $data['code'], 0, 8) : $base;
                $wpdb->update($orders, ['currency_code' => $code], ['order_id' => $row->order_id]);
            }

            $wpdb->query($wpdb->prepare(
                "UPDATE {$orders} SET base_currency = %s WHERE base_currency = ''",
                $base
            ));
            $wpdb->query("UPDATE {$orders} SET base_total = total WHERE base_total = 0 AND total <> 0");

            $wpdb->query("UPDATE {$items} SET base_price = price WHERE base_price = 0 AND price <> 0");
            $wpdb->query("UPDATE {$items} SET base_site_commission = site_commission WHERE base_site_commission = 0 AND site_commission <> 0");

            $wpdb->query("UPDATE {$renews} SET base_total = total WHERE base_total = 0 AND total <> 0");
            $wpdb->query($wpdb->prepare(
                "UPDATE {$renews} r
                    INNER JOIN {$orders} o ON o.order_id = r.order_id
                    SET r.currency_code = COALESCE(NULLIF(o.currency_code, ''), %s)
                  WHERE r.currency_code = ''",
                $base
            ));

            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ahm_withdraws SET currency_code = %s WHERE currency_code = ''",
                $base
            ));
        }

        /**
         * Count orders whose recorded currency is not the base currency but which were
         * backfilled at a rate of 1.0, so their base totals are not trustworthy.
         *
         * @return int
         */
        public static function countLegacyForeignOrders()
        {
            global $wpdb;

            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_orders
                  WHERE currency_code <> '' AND base_currency <> ''
                    AND currency_code <> base_currency
                    AND exchange_rate = 1"
            );
        }

        function addColumn($table, $column, $type_n_default = 'TEXT NOT NULL')
        {
            global $wpdb;
            $result = $wpdb->get_results("SHOW COLUMNS FROM `{$wpdb->prefix}{$table}` LIKE '$column'");
            $exists = count($result) > 0 ? TRUE : FALSE;
            if (!$exists)
                $wpdb->query("ALTER TABLE `{$wpdb->prefix}{$table}` ADD `{$column}` {$type_n_default}");
        }

        function changeColumn($table, $column, $newName, $type_n_default = 'TEXT NOT NULL')
        {
            global $wpdb;
            $result = $wpdb->get_results("SHOW COLUMNS FROM `{$wpdb->prefix}{$table}` LIKE '$newName'");
            $exists = count($result) > 0 ? TRUE : FALSE;
            if (!$exists)
                $wpdb->query("ALTER TABLE `{$wpdb->prefix}{$table}` CHANGE `{$column}` `{$newName}` {$type_n_default}");
        }

        /**
         * Add index to table column if it doesn't exist
         *
         * @param string $table Table name (without prefix)
         * @param string $column Column name to index
         * @param string $index_name Optional custom index name (defaults to idx_{column})
         * @return bool True if index was added, false if it already exists
         */
        function addIndex( $table, $column, $index_name = '' ) {
            global $wpdb;

            $table_name = "{$wpdb->prefix}{$table}";
            $index_name = $index_name ?: "idx_{$column}";

            // Check if index already exists
            $index_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE table_schema = DATABASE()
                 AND table_name = %s
                 AND index_name = %s",
                $table_name, $index_name
            ) );

            if ( ! $index_exists ) {
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX `{$index_name}` (`{$column}`)" );
                return true;
            }

            return false;
        }

        /**
         * Add composite index to table if it doesn't exist
         *
         * @param string $table Table name (without prefix)
         * @param array $columns Array of column names
         * @param string $index_name Index name
         * @return bool True if index was added, false if it already exists
         */
        function addCompositeIndex( $table, $columns, $index_name ) {
            global $wpdb;

            $table_name = "{$wpdb->prefix}{$table}";

            // Check if index already exists
            $index_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE table_schema = DATABASE()
                 AND table_name = %s
                 AND index_name = %s",
                $table_name, $index_name
            ) );

            if ( ! $index_exists ) {
                $columns_str = implode( '`, `', $columns );
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX `{$index_name}` (`{$columns_str}`)" );
                return true;
            }

            return false;
        }
    
        /**
         * Return carts to store-currency amounts.
         *
         * Carts used to hold amounts already converted into whichever currency the
         * shopper had selected, with the code kept beside them. Currency selection is
         * presentation now and the charge is always taken in the store currency, so a
         * cart left holding a converted figure would be read as a store-currency one
         * and billed at the converted number - 37.84 charged as 37.84 EUR rather than
         * the 44.00 EUR it stands for.
         *
         * Each such cart is converted back at the rate between its recorded currency
         * and the store's, and the now-meaningless record removed. Carts with no
         * record were always in the store currency and are left alone.
         *
         * @return void
         */
        function normaliseCartsToStoreCurrency()
        {
            global $wpdb;

            if ( ! class_exists( '\\WPDMPP\\Currency\\PresentmentService' ) ) {
                return;
            }

            $store   = \WPDMPP\Currency\PresentmentService::getInstance()->getStoreCurrency();
            $records = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%\_cart\_currency'"
            );

            foreach ( (array) $records as $record ) {
                $cartId = substr( $record, 0, -9 );
                $code   = strtoupper( (string) get_option( $record, '' ) );
                $items  = get_option( $cartId );

                if ( $code !== '' && $code !== $store && is_array( $items ) && $items ) {
                    $rate = \WPDMPP\Currency\ExchangeRateService::getInstance()->getRate( $store, $code );

                    if ( is_numeric( $rate ) && (float) $rate > 0 ) {
                        foreach ( $items as $pid => $item ) {
                            foreach ( [ 'price', 'role_discount', 'coupon_discount' ] as $field ) {
                                if ( isset( $item[ $field ] ) && is_numeric( $item[ $field ] ) ) {
                                    $items[ $pid ][ $field ] = round( (float) $item[ $field ] / (float) $rate, 2 );
                                }
                            }

                            if ( isset( $item['license']['price'] ) && is_numeric( $item['license']['price'] ) ) {
                                $items[ $pid ]['license']['price'] = round( (float) $item['license']['price'] / (float) $rate, 2 );
                            }
                        }

                        update_option( $cartId, $items, false );
                    }
                }

                delete_option( $record );
            }
        }
}
