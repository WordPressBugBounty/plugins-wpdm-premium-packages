<?php
/**
 * Currency Service
 *
 * Provides currency data, formatting, and utilities.
 *
 * @package WPDMPP\Core
 * @since 7.0.0
 */

namespace WPDMPP\Core;

defined('ABSPATH') || exit;

class CurrencyService {

    /**
     * Singleton instance
     *
     * @var CurrencyService|null
     */
    private static ?CurrencyService $instance = null;

    /**
     * Cached currencies array
     *
     * @var array|null
     */
    private ?array $currencies = null;

    /**
     * Get singleton instance
     *
     * @return CurrencyService
     */
    public static function getInstance(): CurrencyService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {}

    /**
     * Get all currencies
     *
     * @return array Array of currency data indexed by currency code
     */
    public function getCurrencies(): array {
        if ($this->currencies !== null) {
            return $this->currencies;
        }

        $currencies = [
            'USD' => ['numeric_code' => 840, 'code' => 'USD', 'name' => 'United States dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'EUR' => ['numeric_code' => 978, 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'fraction_name' => 'Cent', 'decimals' => 2],
            'GBP' => ['numeric_code' => 826, 'code' => 'GBP', 'name' => 'British pound', 'symbol' => '£', 'fraction_name' => 'Penny', 'decimals' => 2],
            'AED' => ['numeric_code' => 784, 'code' => 'AED', 'name' => 'United Arab Emirates dirham', 'symbol' => 'د.إ', 'fraction_name' => 'Fils', 'decimals' => 2],
            'AFN' => ['numeric_code' => 971, 'code' => 'AFN', 'name' => 'Afghan afghani', 'symbol' => '؋', 'fraction_name' => 'Pul', 'decimals' => 2],
            'ALL' => ['numeric_code' => 8, 'code' => 'ALL', 'name' => 'Albanian lek', 'symbol' => 'L', 'fraction_name' => 'Qintar', 'decimals' => 2],
            'AMD' => ['numeric_code' => 51, 'code' => 'AMD', 'name' => 'Armenian dram', 'symbol' => 'դdelays.', 'fraction_name' => 'Luma', 'decimals' => 2],
            'ANG' => ['numeric_code' => 532, 'code' => 'ANG', 'name' => 'Netherlands Antillean guilder', 'symbol' => 'ƒ', 'fraction_name' => 'Cent', 'decimals' => 2],
            'AOA' => ['numeric_code' => 973, 'code' => 'AOA', 'name' => 'Angolan kwanza', 'symbol' => 'Kz', 'fraction_name' => 'Cêntimo', 'decimals' => 2],
            'ARS' => ['numeric_code' => 32, 'code' => 'ARS', 'name' => 'Argentine peso', 'symbol' => '$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'AUD' => ['numeric_code' => 36, 'code' => 'AUD', 'name' => 'Australian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'AWG' => ['numeric_code' => 533, 'code' => 'AWG', 'name' => 'Aruban florin', 'symbol' => 'ƒ', 'fraction_name' => 'Cent', 'decimals' => 2],
            'AZN' => ['numeric_code' => 944, 'code' => 'AZN', 'name' => 'Azerbaijani manat', 'symbol' => 'AZN', 'fraction_name' => 'Qəpik', 'decimals' => 2],
            'BAM' => ['numeric_code' => 977, 'code' => 'BAM', 'name' => 'Bosnia and Herzegovina convertible mark', 'symbol' => 'КМ', 'fraction_name' => 'Fening', 'decimals' => 2],
            'BBD' => ['numeric_code' => 52, 'code' => 'BBD', 'name' => 'Barbadian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'BDT' => ['numeric_code' => 50, 'code' => 'BDT', 'name' => 'Bangladeshi taka', 'symbol' => '৳', 'fraction_name' => 'Paisa', 'decimals' => 2],
            'BGN' => ['numeric_code' => 975, 'code' => 'BGN', 'name' => 'Bulgarian lev', 'symbol' => 'лв', 'fraction_name' => 'Stotinka', 'decimals' => 2],
            'BHD' => ['numeric_code' => 48, 'code' => 'BHD', 'name' => 'Bahraini dinar', 'symbol' => 'ب.د', 'fraction_name' => 'Fils', 'decimals' => 3],
            'BIF' => ['numeric_code' => 108, 'code' => 'BIF', 'name' => 'Burundian franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 2],
            'BMD' => ['numeric_code' => 60, 'code' => 'BMD', 'name' => 'Bermudian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'BND' => ['numeric_code' => 96, 'code' => 'BND', 'name' => 'Brunei dollar', 'symbol' => '$', 'fraction_name' => 'Sen', 'decimals' => 2],
            'BOB' => ['numeric_code' => 68, 'code' => 'BOB', 'name' => 'Bolivian boliviano', 'symbol' => 'Bs.', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'BRL' => ['numeric_code' => 986, 'code' => 'BRL', 'name' => 'Brazilian real', 'symbol' => 'R$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'BSD' => ['numeric_code' => 44, 'code' => 'BSD', 'name' => 'Bahamian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'BTN' => ['numeric_code' => 64, 'code' => 'BTN', 'name' => 'Bhutanese ngultrum', 'symbol' => 'BTN', 'fraction_name' => 'Chertrum', 'decimals' => 2],
            'BWP' => ['numeric_code' => 72, 'code' => 'BWP', 'name' => 'Botswana pula', 'symbol' => 'P', 'fraction_name' => 'Thebe', 'decimals' => 2],
            'BYR' => ['numeric_code' => 974, 'code' => 'BYR', 'name' => 'Belarusian ruble', 'symbol' => 'Br', 'fraction_name' => 'Kapyeyka', 'decimals' => 2],
            'BZD' => ['numeric_code' => 84, 'code' => 'BZD', 'name' => 'Belize dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'CAD' => ['numeric_code' => 124, 'code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'CDF' => ['numeric_code' => 976, 'code' => 'CDF', 'name' => 'Congolese franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 2],
            'CHF' => ['numeric_code' => 756, 'code' => 'CHF', 'name' => 'Swiss franc', 'symbol' => 'Fr', 'fraction_name' => 'Rappen', 'decimals' => 2],
            'CLP' => ['numeric_code' => 152, 'code' => 'CLP', 'name' => 'Chilean peso', 'symbol' => '$', 'fraction_name' => 'Centavo', 'decimals' => 0],
            'CNY' => ['numeric_code' => 156, 'code' => 'CNY', 'name' => 'Chinese yuan', 'symbol' => '元', 'fraction_name' => 'Fen', 'decimals' => 2],
            'COP' => ['numeric_code' => 170, 'code' => 'COP', 'name' => 'Colombian peso', 'symbol' => '$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'CRC' => ['numeric_code' => 188, 'code' => 'CRC', 'name' => 'Costa Rican colón', 'symbol' => '₡', 'fraction_name' => 'Céntimo', 'decimals' => 2],
            'CUC' => ['numeric_code' => 931, 'code' => 'CUC', 'name' => 'Cuban convertible peso', 'symbol' => '$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'CUP' => ['numeric_code' => 192, 'code' => 'CUP', 'name' => 'Cuban peso', 'symbol' => '$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'CVE' => ['numeric_code' => 132, 'code' => 'CVE', 'name' => 'Cape Verdean escudo', 'symbol' => 'Esc', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'CZK' => ['numeric_code' => 203, 'code' => 'CZK', 'name' => 'Czech koruna', 'symbol' => 'Kč', 'fraction_name' => 'Haléř', 'decimals' => 2],
            'DJF' => ['numeric_code' => 262, 'code' => 'DJF', 'name' => 'Djiboutian franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 0],
            'DKK' => ['numeric_code' => 208, 'code' => 'DKK', 'name' => 'Danish krone', 'symbol' => 'kr', 'fraction_name' => 'Øre', 'decimals' => 2],
            'DOP' => ['numeric_code' => 214, 'code' => 'DOP', 'name' => 'Dominican peso', 'symbol' => '$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'DZD' => ['numeric_code' => 12, 'code' => 'DZD', 'name' => 'Algerian dinar', 'symbol' => 'د.ج', 'fraction_name' => 'Centime', 'decimals' => 2],
            'EGP' => ['numeric_code' => 818, 'code' => 'EGP', 'name' => 'Egyptian pound', 'symbol' => '£', 'fraction_name' => 'Piastre', 'decimals' => 2],
            'ERN' => ['numeric_code' => 232, 'code' => 'ERN', 'name' => 'Eritrean nakfa', 'symbol' => 'Nfk', 'fraction_name' => 'Cent', 'decimals' => 2],
            'ETB' => ['numeric_code' => 230, 'code' => 'ETB', 'name' => 'Ethiopian birr', 'symbol' => 'ETB', 'fraction_name' => 'Santim', 'decimals' => 2],
            'FJD' => ['numeric_code' => 242, 'code' => 'FJD', 'name' => 'Fijian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'FKP' => ['numeric_code' => 238, 'code' => 'FKP', 'name' => 'Falkland Islands pound', 'symbol' => '£', 'fraction_name' => 'Penny', 'decimals' => 2],
            'GEL' => ['numeric_code' => 981, 'code' => 'GEL', 'name' => 'Georgian lari', 'symbol' => 'ლ', 'fraction_name' => 'Tetri', 'decimals' => 2],
            'GHS' => ['numeric_code' => 936, 'code' => 'GHS', 'name' => 'Ghanaian cedi', 'symbol' => '₵', 'fraction_name' => 'Pesewa', 'decimals' => 2],
            'GIP' => ['numeric_code' => 292, 'code' => 'GIP', 'name' => 'Gibraltar pound', 'symbol' => '£', 'fraction_name' => 'Penny', 'decimals' => 2],
            'GMD' => ['numeric_code' => 270, 'code' => 'GMD', 'name' => 'Gambian dalasi', 'symbol' => 'D', 'fraction_name' => 'Butut', 'decimals' => 2],
            'GNF' => ['numeric_code' => 324, 'code' => 'GNF', 'name' => 'Guinean franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 0],
            'GTQ' => ['numeric_code' => 320, 'code' => 'GTQ', 'name' => 'Guatemalan quetzal', 'symbol' => 'Q', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'GYD' => ['numeric_code' => 328, 'code' => 'GYD', 'name' => 'Guyanese dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'HKD' => ['numeric_code' => 344, 'code' => 'HKD', 'name' => 'Hong Kong dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'HNL' => ['numeric_code' => 340, 'code' => 'HNL', 'name' => 'Honduran lempira', 'symbol' => 'L', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'HRK' => ['numeric_code' => 191, 'code' => 'HRK', 'name' => 'Croatian kuna', 'symbol' => 'kn', 'fraction_name' => 'Lipa', 'decimals' => 2],
            'HTG' => ['numeric_code' => 332, 'code' => 'HTG', 'name' => 'Haitian gourde', 'symbol' => 'G', 'fraction_name' => 'Centime', 'decimals' => 2],
            'HUF' => ['numeric_code' => 348, 'code' => 'HUF', 'name' => 'Hungarian forint', 'symbol' => 'Ft', 'fraction_name' => 'Fillér', 'decimals' => 2],
            'IDR' => ['numeric_code' => 360, 'code' => 'IDR', 'name' => 'Indonesian rupiah', 'symbol' => 'Rp', 'fraction_name' => 'Sen', 'decimals' => 2],
            'ILS' => ['numeric_code' => 376, 'code' => 'ILS', 'name' => 'Israeli new sheqel', 'symbol' => '₪', 'fraction_name' => 'Agora', 'decimals' => 2],
            'INR' => ['numeric_code' => 356, 'code' => 'INR', 'name' => 'Indian rupee', 'symbol' => '₹', 'fraction_name' => 'Paisa', 'decimals' => 2],
            'IQD' => ['numeric_code' => 368, 'code' => 'IQD', 'name' => 'Iraqi dinar', 'symbol' => 'ع.د', 'fraction_name' => 'Fils', 'decimals' => 3],
            'IRR' => ['numeric_code' => 364, 'code' => 'IRR', 'name' => 'Iranian rial', 'symbol' => '﷼', 'fraction_name' => 'Dinar', 'decimals' => 2],
            'IRT' => ['numeric_code' => 364, 'code' => 'IRT', 'name' => 'Iranian Toman', 'symbol' => '﷼', 'fraction_name' => 'Rial', 'decimals' => 2],
            'ISK' => ['numeric_code' => 352, 'code' => 'ISK', 'name' => 'Icelandic króna', 'symbol' => 'kr', 'fraction_name' => 'Eyrir', 'decimals' => 0],
            'JMD' => ['numeric_code' => 388, 'code' => 'JMD', 'name' => 'Jamaican dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'JOD' => ['numeric_code' => 400, 'code' => 'JOD', 'name' => 'Jordanian dinar', 'symbol' => 'د.ا', 'fraction_name' => 'Piastre', 'decimals' => 3],
            'JPY' => ['numeric_code' => 392, 'code' => 'JPY', 'name' => 'Japanese yen', 'symbol' => '¥', 'fraction_name' => 'Sen', 'decimals' => 0],
            'KES' => ['numeric_code' => 404, 'code' => 'KES', 'name' => 'Kenyan shilling', 'symbol' => 'Sh', 'fraction_name' => 'Cent', 'decimals' => 2],
            'KGS' => ['numeric_code' => 417, 'code' => 'KGS', 'name' => 'Kyrgyzstani som', 'symbol' => 'KGS', 'fraction_name' => 'Tyiyn', 'decimals' => 2],
            'KHR' => ['numeric_code' => 116, 'code' => 'KHR', 'name' => 'Cambodian riel', 'symbol' => '៛', 'fraction_name' => 'Sen', 'decimals' => 2],
            'KMF' => ['numeric_code' => 174, 'code' => 'KMF', 'name' => 'Comorian franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 0],
            'KPW' => ['numeric_code' => 408, 'code' => 'KPW', 'name' => 'North Korean won', 'symbol' => '₩', 'fraction_name' => 'Chŏn', 'decimals' => 2],
            'KRW' => ['numeric_code' => 410, 'code' => 'KRW', 'name' => 'South Korean won', 'symbol' => '₩', 'fraction_name' => 'Jeon', 'decimals' => 0],
            'KWD' => ['numeric_code' => 414, 'code' => 'KWD', 'name' => 'Kuwaiti dinar', 'symbol' => 'د.ك', 'fraction_name' => 'Fils', 'decimals' => 3],
            'KYD' => ['numeric_code' => 136, 'code' => 'KYD', 'name' => 'Cayman Islands dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'KZT' => ['numeric_code' => 398, 'code' => 'KZT', 'name' => 'Kazakhstani tenge', 'symbol' => '〒', 'fraction_name' => 'Tiyn', 'decimals' => 2],
            'LAK' => ['numeric_code' => 418, 'code' => 'LAK', 'name' => 'Lao kip', 'symbol' => '₭', 'fraction_name' => 'Att', 'decimals' => 2],
            'LBP' => ['numeric_code' => 422, 'code' => 'LBP', 'name' => 'Lebanese pound', 'symbol' => 'ل.ل', 'fraction_name' => 'Piastre', 'decimals' => 2],
            'LKR' => ['numeric_code' => 144, 'code' => 'LKR', 'name' => 'Sri Lankan rupee', 'symbol' => 'Rs', 'fraction_name' => 'Cent', 'decimals' => 2],
            'LRD' => ['numeric_code' => 430, 'code' => 'LRD', 'name' => 'Liberian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'LSL' => ['numeric_code' => 426, 'code' => 'LSL', 'name' => 'Lesotho loti', 'symbol' => 'L', 'fraction_name' => 'Sente', 'decimals' => 2],
            'LYD' => ['numeric_code' => 434, 'code' => 'LYD', 'name' => 'Libyan dinar', 'symbol' => 'ل.د', 'fraction_name' => 'Dirham', 'decimals' => 3],
            'MAD' => ['numeric_code' => 504, 'code' => 'MAD', 'name' => 'Moroccan dirham', 'symbol' => 'Dh', 'fraction_name' => 'Centime', 'decimals' => 2],
            'MDL' => ['numeric_code' => 498, 'code' => 'MDL', 'name' => 'Moldovan leu', 'symbol' => 'L', 'fraction_name' => 'Ban', 'decimals' => 2],
            'MGA' => ['numeric_code' => 969, 'code' => 'MGA', 'name' => 'Malagasy ariary', 'symbol' => 'MGA', 'fraction_name' => 'Iraimbilanja', 'decimals' => 2],
            'MKD' => ['numeric_code' => 807, 'code' => 'MKD', 'name' => 'Macedonian denar', 'symbol' => 'ден', 'fraction_name' => 'Deni', 'decimals' => 2],
            'MMK' => ['numeric_code' => 104, 'code' => 'MMK', 'name' => 'Myanma kyat', 'symbol' => 'K', 'fraction_name' => 'Pya', 'decimals' => 2],
            'MNT' => ['numeric_code' => 496, 'code' => 'MNT', 'name' => 'Mongolian tögrög', 'symbol' => '₮', 'fraction_name' => 'Möngö', 'decimals' => 2],
            'MOP' => ['numeric_code' => 446, 'code' => 'MOP', 'name' => 'Macanese pataca', 'symbol' => 'P', 'fraction_name' => 'Avo', 'decimals' => 2],
            'MRO' => ['numeric_code' => 478, 'code' => 'MRO', 'name' => 'Mauritanian ouguiya', 'symbol' => 'UM', 'fraction_name' => 'Khoums', 'decimals' => 2],
            'MUR' => ['numeric_code' => 480, 'code' => 'MUR', 'name' => 'Mauritian rupee', 'symbol' => '₨', 'fraction_name' => 'Cent', 'decimals' => 2],
            'MVR' => ['numeric_code' => 462, 'code' => 'MVR', 'name' => 'Maldivian rufiyaa', 'symbol' => 'ރ.', 'fraction_name' => 'Laari', 'decimals' => 2],
            'MWK' => ['numeric_code' => 454, 'code' => 'MWK', 'name' => 'Malawian kwacha', 'symbol' => 'MK', 'fraction_name' => 'Tambala', 'decimals' => 2],
            'MXN' => ['numeric_code' => 484, 'code' => 'MXN', 'name' => 'Mexican peso', 'symbol' => '$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'MYR' => ['numeric_code' => 458, 'code' => 'MYR', 'name' => 'Malaysian ringgit', 'symbol' => 'RM', 'fraction_name' => 'Sen', 'decimals' => 2],
            'MZN' => ['numeric_code' => 943, 'code' => 'MZN', 'name' => 'Mozambican metical', 'symbol' => 'MTn', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'NAD' => ['numeric_code' => 516, 'code' => 'NAD', 'name' => 'Namibian dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'NGN' => ['numeric_code' => 566, 'code' => 'NGN', 'name' => 'Nigerian naira', 'symbol' => '₦', 'fraction_name' => 'Kobo', 'decimals' => 2],
            'NIO' => ['numeric_code' => 558, 'code' => 'NIO', 'name' => 'Nicaraguan córdoba', 'symbol' => 'C$', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'NOK' => ['numeric_code' => 578, 'code' => 'NOK', 'name' => 'Norwegian krone', 'symbol' => 'kr', 'fraction_name' => 'Øre', 'decimals' => 2],
            'NPR' => ['numeric_code' => 524, 'code' => 'NPR', 'name' => 'Nepalese rupee', 'symbol' => '₨', 'fraction_name' => 'Paisa', 'decimals' => 2],
            'NZD' => ['numeric_code' => 554, 'code' => 'NZD', 'name' => 'New Zealand dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'OMR' => ['numeric_code' => 512, 'code' => 'OMR', 'name' => 'Omani rial', 'symbol' => 'ر.ع.', 'fraction_name' => 'Baisa', 'decimals' => 3],
            'PAB' => ['numeric_code' => 590, 'code' => 'PAB', 'name' => 'Panamanian balboa', 'symbol' => 'B/.', 'fraction_name' => 'Centésimo', 'decimals' => 2],
            'PEN' => ['numeric_code' => 604, 'code' => 'PEN', 'name' => 'Peruvian nuevo sol', 'symbol' => 'S/.', 'fraction_name' => 'Céntimo', 'decimals' => 2],
            'PGK' => ['numeric_code' => 598, 'code' => 'PGK', 'name' => 'Papua New Guinean kina', 'symbol' => 'K', 'fraction_name' => 'Toea', 'decimals' => 2],
            'PHP' => ['numeric_code' => 608, 'code' => 'PHP', 'name' => 'Philippine peso', 'symbol' => '₱', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'PKR' => ['numeric_code' => 586, 'code' => 'PKR', 'name' => 'Pakistani rupee', 'symbol' => '₨', 'fraction_name' => 'Paisa', 'decimals' => 2],
            'PLN' => ['numeric_code' => 985, 'code' => 'PLN', 'name' => 'Polish złoty', 'symbol' => 'zł', 'fraction_name' => 'Grosz', 'decimals' => 2],
            'PYG' => ['numeric_code' => 600, 'code' => 'PYG', 'name' => 'Paraguayan guaraní', 'symbol' => '₲', 'fraction_name' => 'Céntimo', 'decimals' => 0],
            'QAR' => ['numeric_code' => 634, 'code' => 'QAR', 'name' => 'Qatari riyal', 'symbol' => 'ر.ق', 'fraction_name' => 'Dirham', 'decimals' => 2],
            'RON' => ['numeric_code' => 946, 'code' => 'RON', 'name' => 'Romanian leu', 'symbol' => 'L', 'fraction_name' => 'Ban', 'decimals' => 2],
            'RSD' => ['numeric_code' => 941, 'code' => 'RSD', 'name' => 'Serbian dinar', 'symbol' => 'дин.', 'fraction_name' => 'Para', 'decimals' => 2],
            'RUB' => ['numeric_code' => 643, 'code' => 'RUB', 'name' => 'Russian ruble', 'symbol' => 'руб.', 'fraction_name' => 'Kopek', 'decimals' => 2],
            'RWF' => ['numeric_code' => 646, 'code' => 'RWF', 'name' => 'Rwandan franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 0],
            'SAR' => ['numeric_code' => 682, 'code' => 'SAR', 'name' => 'Saudi riyal', 'symbol' => 'ر.س', 'fraction_name' => 'Hallallah', 'decimals' => 2],
            'SBD' => ['numeric_code' => 90, 'code' => 'SBD', 'name' => 'Solomon Islands dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'SCR' => ['numeric_code' => 690, 'code' => 'SCR', 'name' => 'Seychellois rupee', 'symbol' => '₨', 'fraction_name' => 'Cent', 'decimals' => 2],
            'SDG' => ['numeric_code' => 938, 'code' => 'SDG', 'name' => 'Sudanese pound', 'symbol' => '£', 'fraction_name' => 'Piastre', 'decimals' => 2],
            'SEK' => ['numeric_code' => 752, 'code' => 'SEK', 'name' => 'Swedish krona', 'symbol' => 'kr', 'fraction_name' => 'Öre', 'decimals' => 2],
            'SGD' => ['numeric_code' => 702, 'code' => 'SGD', 'name' => 'Singapore dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'SHP' => ['numeric_code' => 654, 'code' => 'SHP', 'name' => 'Saint Helena pound', 'symbol' => '£', 'fraction_name' => 'Penny', 'decimals' => 2],
            'SLL' => ['numeric_code' => 694, 'code' => 'SLL', 'name' => 'Sierra Leonean leone', 'symbol' => 'Le', 'fraction_name' => 'Cent', 'decimals' => 2],
            'SOS' => ['numeric_code' => 706, 'code' => 'SOS', 'name' => 'Somali shilling', 'symbol' => 'Sh', 'fraction_name' => 'Cent', 'decimals' => 2],
            'SRD' => ['numeric_code' => 968, 'code' => 'SRD', 'name' => 'Surinamese dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'STD' => ['numeric_code' => 678, 'code' => 'STD', 'name' => 'São Tomé and Príncipe dobra', 'symbol' => 'Db', 'fraction_name' => 'Cêntimo', 'decimals' => 2],
            'SVC' => ['numeric_code' => 222, 'code' => 'SVC', 'name' => 'Salvadoran colón', 'symbol' => '₡', 'fraction_name' => 'Centavo', 'decimals' => 2],
            'SYP' => ['numeric_code' => 760, 'code' => 'SYP', 'name' => 'Syrian pound', 'symbol' => '£', 'fraction_name' => 'Piastre', 'decimals' => 2],
            'SZL' => ['numeric_code' => 748, 'code' => 'SZL', 'name' => 'Swazi lilangeni', 'symbol' => 'L', 'fraction_name' => 'Cent', 'decimals' => 2],
            'THB' => ['numeric_code' => 764, 'code' => 'THB', 'name' => 'Thai baht', 'symbol' => '฿', 'fraction_name' => 'Satang', 'decimals' => 2],
            'TJS' => ['numeric_code' => 972, 'code' => 'TJS', 'name' => 'Tajikistani somoni', 'symbol' => 'ЅМ', 'fraction_name' => 'Diram', 'decimals' => 2],
            'TMM' => ['numeric_code' => 0, 'code' => 'TMM', 'name' => 'Turkmenistani manat', 'symbol' => 'm', 'fraction_name' => 'Tennesi', 'decimals' => 2],
            'TND' => ['numeric_code' => 788, 'code' => 'TND', 'name' => 'Tunisian dinar', 'symbol' => 'د.ت', 'fraction_name' => 'Millime', 'decimals' => 3],
            'TOP' => ['numeric_code' => 776, 'code' => 'TOP', 'name' => 'Tongan paʻanga', 'symbol' => 'T$', 'fraction_name' => 'Seniti', 'decimals' => 2],
            'TRY' => ['numeric_code' => 949, 'code' => 'TRY', 'name' => 'Turkish lira', 'symbol' => 'TL', 'fraction_name' => 'Kuruş', 'decimals' => 2],
            'TTD' => ['numeric_code' => 780, 'code' => 'TTD', 'name' => 'Trinidad and Tobago dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'TWD' => ['numeric_code' => 901, 'code' => 'TWD', 'name' => 'New Taiwan dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'TZS' => ['numeric_code' => 834, 'code' => 'TZS', 'name' => 'Tanzanian shilling', 'symbol' => 'Sh', 'fraction_name' => 'Cent', 'decimals' => 2],
            'UAH' => ['numeric_code' => 980, 'code' => 'UAH', 'name' => 'Ukrainian hryvnia', 'symbol' => '₴', 'fraction_name' => 'Kopiyka', 'decimals' => 2],
            'UGX' => ['numeric_code' => 800, 'code' => 'UGX', 'name' => 'Ugandan shilling', 'symbol' => 'Sh', 'fraction_name' => 'Cent', 'decimals' => 0],
            'UYU' => ['numeric_code' => 858, 'code' => 'UYU', 'name' => 'Uruguayan peso', 'symbol' => '$', 'fraction_name' => 'Centésimo', 'decimals' => 2],
            'UZS' => ['numeric_code' => 860, 'code' => 'UZS', 'name' => 'Uzbekistani som', 'symbol' => 'UZS', 'fraction_name' => 'Tiyin', 'decimals' => 2],
            'VEF' => ['numeric_code' => 937, 'code' => 'VEF', 'name' => 'Venezuelan bolívar', 'symbol' => 'Bs F', 'fraction_name' => 'Céntimo', 'decimals' => 2],
            'VND' => ['numeric_code' => 704, 'code' => 'VND', 'name' => 'Vietnamese đồng', 'symbol' => '₫', 'fraction_name' => 'Hào', 'decimals' => 0],
            'VUV' => ['numeric_code' => 548, 'code' => 'VUV', 'name' => 'Vanuatu vatu', 'symbol' => 'Vt', 'fraction_name' => '', 'decimals' => 0],
            'WST' => ['numeric_code' => 882, 'code' => 'WST', 'name' => 'Samoan tala', 'symbol' => 'T', 'fraction_name' => 'Sene', 'decimals' => 2],
            'XAF' => ['numeric_code' => 950, 'code' => 'XAF', 'name' => 'Central African CFA franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 0],
            'XCD' => ['numeric_code' => 951, 'code' => 'XCD', 'name' => 'East Caribbean dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
            'XOF' => ['numeric_code' => 952, 'code' => 'XOF', 'name' => 'West African CFA franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 0],
            'XPF' => ['numeric_code' => 953, 'code' => 'XPF', 'name' => 'CFP franc', 'symbol' => 'Fr', 'fraction_name' => 'Centime', 'decimals' => 0],
            'YER' => ['numeric_code' => 886, 'code' => 'YER', 'name' => 'Yemeni rial', 'symbol' => '﷼', 'fraction_name' => 'Fils', 'decimals' => 2],
            'ZAR' => ['numeric_code' => 710, 'code' => 'ZAR', 'name' => 'South African rand', 'symbol' => 'R', 'fraction_name' => 'Cent', 'decimals' => 2],
            'ZMK' => ['numeric_code' => 894, 'code' => 'ZMK', 'name' => 'Zambian kwacha', 'symbol' => 'ZK', 'fraction_name' => 'Ngwee', 'decimals' => 2],
            'ZWR' => ['numeric_code' => 0, 'code' => 'ZWR', 'name' => 'Zimbabwean dollar', 'symbol' => '$', 'fraction_name' => 'Cent', 'decimals' => 2],
        ];

        /**
         * Filter the currencies list
         *
         * @param array $currencies Array of currency data
         */
        $this->currencies = apply_filters('wpdmpp_currencies', $currencies);

        return $this->currencies;
    }

    /**
     * Get currency data by code
     *
     * @param string $code Currency code (e.g., 'USD')
     * @return array|null Currency data or null if not found
     */
    public function getCurrency(string $code): ?array {
        if (empty($code)) {
            $code = 'USD';
        }

        $currencies = $this->getCurrencies();
        return $currencies[$code] ?? null;
    }

    /**
     * Get currency symbol
     *
     * @param string $code Currency code
     * @return string Currency symbol or the code if not found
     */
    public function getCurrencySymbol(string $code = ''): string {
        if (empty($code)) {
            $code = $this->getDefaultCurrencyCode();
        }

        $currency = $this->getCurrency($code);
        return $currency['symbol'] ?? $code;
    }

    /**
     * Get currency name
     *
     * @param string $code Currency code
     * @return string Currency name or empty string if not found
     */
    public function getCurrencyName(string $code): string {
        $currency = $this->getCurrency($code);
        return $currency['name'] ?? '';
    }

    /**
     * Get number of decimal places for a currency
     *
     * @param string $code Currency code
     * @return int Number of decimal places
     */
    public function getDecimals(string $code = ''): int {
        if (empty($code)) {
            $code = $this->getDefaultCurrencyCode();
        }

        $currency = $this->getCurrency($code);
        return (int) ($currency['decimals'] ?? 2);
    }

    /**
     * Get the default currency code from settings
     *
     * @return string Currency code
     */
    public function getDefaultCurrencyCode(): string {
        return get_wpdmpp_option('currency', 'USD');
    }

    /**
     * Get the default currency symbol
     *
     * @return string Currency symbol
     */
    public function getDefaultCurrencySymbol(): string {
        return $this->getCurrencySymbol($this->getDefaultCurrencyCode());
    }

    /**
     * Format a price with currency
     *
     * @param float  $amount Amount to format
     * @param string $code   Currency code (optional, uses default if empty)
     * @param bool   $includeSymbol Whether to include currency symbol
     * @return string Formatted price
     */
    public function formatPrice(float $amount, string $code = '', bool $includeSymbol = true): string {
        if (empty($code)) {
            $code = $this->getDefaultCurrencyCode();
        }

        $currency = $this->getCurrency($code);
        $decimals = $currency['decimals'] ?? 2;
        $symbol = $currency['symbol'] ?? $code;

        // Format the number
        $formatted = number_format($amount, $decimals, '.', ',');

        if (!$includeSymbol) {
            return $formatted;
        }

        // Get symbol position from settings
        $symbolPosition = get_wpdmpp_option('currency_position', 'left');

        // Apply symbol position
        switch ($symbolPosition) {
            case 'right':
                return $formatted . $symbol;
            case 'left_space':
                return $symbol . ' ' . $formatted;
            case 'right_space':
                return $formatted . ' ' . $symbol;
            case 'left':
            default:
                return $symbol . $formatted;
        }
    }

    /**
     * Generate HTML select dropdown for currencies
     *
     * @param string $selected Selected currency code
     * @param string $name     Form field name
     * @param string $id       Element ID (optional)
     * @param string $class    CSS class (optional)
     * @return string HTML select element
     */
    public function getCurrencyDropdown(
        string $selected = '',
        string $name = 'currency',
        string $id = '',
        string $class = 'form-control wpdmpp-currency-dropdown'
    ): string {
        if (empty($selected)) {
            $selected = $this->getDefaultCurrencyCode();
        }

        if (empty($id)) {
            $id = $name;
        }

        $currencies = $this->getCurrencies();
        $html = sprintf(
            '<select class="%s" name="%s" id="%s">',
            esc_attr($class),
            esc_attr($name),
            esc_attr($id)
        );

        foreach ($currencies as $code => $data) {
            $html .= sprintf(
                '<option value="%s"%s>%s (%s)</option>',
                esc_attr($code),
                selected($selected, $code, false),
                esc_html($data['name']),
                esc_html($data['symbol'])
            );
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Render currency dropdown (echo output)
     *
     * @param string $selected Selected currency code
     * @param string $name     Form field name
     * @param string $id       Element ID (optional)
     * @param string $class    CSS class (optional)
     */
    public function renderCurrencyDropdown(
        string $selected = '',
        string $name = 'currency',
        string $id = '',
        string $class = 'form-control wpdmpp-currency-dropdown'
    ): void {
        echo $this->getCurrencyDropdown($selected, $name, $id, $class);
    }

    /**
     * Check if a currency code is valid
     *
     * @param string $code Currency code to check
     * @return bool True if valid
     */
    public function isValidCurrency(string $code): bool {
        $currencies = $this->getCurrencies();
        return isset($currencies[$code]);
    }

    /**
     * Get a list of currency codes only
     *
     * @return array Array of currency codes
     */
    public function getCurrencyCodes(): array {
        return array_keys($this->getCurrencies());
    }

    /**
     * Get currencies for a specific region/payment gateway
     *
     * @param string $gateway Gateway identifier (e.g., 'paypal', 'stripe')
     * @return array Filtered currencies supported by the gateway
     */
    public function getGatewayCurrencies(string $gateway): array {
        $allCurrencies = $this->getCurrencies();

        // PayPal supported currencies
        $paypalCurrencies = [
            'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS',
            'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB',
            'SGD', 'SEK', 'CHF', 'THB', 'USD'
        ];

        // Stripe has broader support, include most currencies
        $stripeCurrencies = [
            'USD', 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG',
            'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
            'BSD', 'BWP', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC',
            'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD',
            'FKP', 'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL',
            'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES',
            'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD',
            'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR',
            'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR',
            'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON',
            'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL',
            'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TOP', 'TRY', 'TTD', 'TWD',
            'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF',
            'XCD', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW'
        ];

        $supported = match ($gateway) {
            'paypal' => $paypalCurrencies,
            'stripe' => $stripeCurrencies,
            default => array_keys($allCurrencies)
        };

        /**
         * Filter gateway-supported currencies
         *
         * @param array  $supported     Supported currency codes
         * @param string $gateway       Gateway identifier
         * @param array  $allCurrencies All available currencies
         */
        $supported = apply_filters('wpdmpp_gateway_currencies', $supported, $gateway, $allCurrencies);

        return array_intersect_key($allCurrencies, array_flip($supported));
    }

    /**
     * Convert amount between currencies (placeholder for future implementation)
     *
     * @param float  $amount   Amount to convert
     * @param string $from     Source currency code
     * @param string $to       Target currency code
     * @return float|null Converted amount or null if conversion not available
     */
    public function convertCurrency(float $amount, string $from, string $to): ?float {
        // This is a placeholder for future currency conversion implementation
        // Could integrate with external API like exchangeratesapi.io

        if ($from === $to) {
            return $amount;
        }

        /**
         * Filter for currency conversion
         *
         * @param float|null $converted Converted amount (null if not converted)
         * @param float      $amount    Original amount
         * @param string     $from      Source currency
         * @param string     $to        Target currency
         */
        return apply_filters('wpdmpp_convert_currency', null, $amount, $from, $to);
    }
}
