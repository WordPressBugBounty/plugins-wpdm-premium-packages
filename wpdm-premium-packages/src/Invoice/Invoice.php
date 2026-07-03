<?php
/**
 * Invoice Entity Class
 *
 * Represents an invoice generated from order data.
 * Invoices are not stored separately - they are views of orders
 * combined with billing info and company settings.
 *
 * @package WPDMPP\Invoice
 * @since 7.0.0
 */

namespace WPDMPP\Invoice;

defined('ABSPATH') || exit;

class Invoice
{
    /**
     * Invoice number (same as order ID)
     *
     * @var string
     */
    private string $invoiceNumber = '';

    /**
     * Order ID
     *
     * @var string
     */
    private string $orderId = '';

    /**
     * Invoice date (timestamp)
     *
     * @var int
     */
    private int $invoiceDate = 0;

    /**
     * Order date (timestamp)
     *
     * @var int
     */
    private int $orderDate = 0;

    /**
     * Renewal date (timestamp, 0 if not a renewal invoice)
     *
     * @var int
     */
    private int $renewalDate = 0;

    /**
     * Customer ID
     *
     * @var int
     */
    private int $customerId = 0;

    /**
     * Billing information
     *
     * @var array
     */
    private array $billingInfo = [];

    /**
     * Order items
     *
     * @var array
     */
    private array $items = [];

    /**
     * Subtotal
     *
     * @var float
     */
    private float $subtotal = 0.0;

    /**
     * Discount amount
     *
     * @var float
     */
    private float $discount = 0.0;

    /**
     * Coupon discount
     *
     * @var float
     */
    private float $couponDiscount = 0.0;

    /**
     * Tax amount
     *
     * @var float
     */
    private float $tax = 0.0;

    /**
     * Total amount
     *
     * @var float
     */
    private float $total = 0.0;

    /**
     * Currency info
     *
     * @var array
     */
    private array $currency = [];

    /**
     * Payment method
     *
     * @var string
     */
    private string $paymentMethod = '';

    /**
     * Payment status
     *
     * @var string
     */
    private string $paymentStatus = '';

    /**
     * Company info from settings
     *
     * @var array
     */
    private array $companyInfo = [];

    /**
     * Constructor
     *
     * @param array $data Invoice data
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * Create invoice from order
     *
     * @param object|array $order    Order data
     * @param array        $items    Order items
     * @param array        $settings Invoice settings
     * @param int          $renewalDate Optional renewal date for renewal invoices
     * @return self
     */
    public static function fromOrder($order, array $items, array $settings = [], int $renewalDate = 0): self
    {
        $order = (object) $order;

        $invoice = new self();
        $invoice->orderId = $order->order_id ?? '';
        $invoice->invoiceNumber = $order->order_id ?? '';
        $invoice->orderDate = (int) ($order->date ?? 0);
        $invoice->invoiceDate = $renewalDate > 0 ? $renewalDate : time();
        $invoice->renewalDate = $renewalDate;
        $invoice->customerId = (int) ($order->uid ?? 0);

        // Parse billing info
        $billingInfo = $order->billing_info ?? '';
        if (is_string($billingInfo)) {
            $billingInfo = maybe_unserialize($billingInfo);
        }
        $invoice->billingInfo = is_array($billingInfo) ? $billingInfo : [];

        // Financial data
        $invoice->subtotal = (float) ($order->subtotal ?? 0);
        $invoice->discount = (float) ($order->discount ?? 0);
        $invoice->couponDiscount = (float) ($order->coupon_discount ?? 0);
        $invoice->tax = (float) ($order->tax ?? 0);
        $invoice->total = (float) ($order->total ?? 0);

        // Currency
        $currency = $order->currency ?? '';
        if (is_string($currency)) {
            $currency = maybe_unserialize($currency);
        }
        $invoice->currency = is_array($currency) ? $currency : ['sign' => '$'];

        // Payment info
        $invoice->paymentMethod = $order->payment_method ?? '';
        $invoice->paymentStatus = $order->payment_status ?? '';

        // Items
        $invoice->items = $items;

        // Company info from settings
        $invoice->companyInfo = [
            'name' => get_bloginfo('name'),
            'logo' => $settings['invoice_logo'] ?? '',
            'address' => $settings['invoice_company_address'] ?? '',
            'signature' => $settings['signature'] ?? '',
            'thanks_note' => $settings['invoice_thanks'] ?? '',
            'terms' => $settings['invoice_terms_acceptance'] ?? '',
        ];

        return $invoice;
    }

    /**
     * Hydrate from data array
     *
     * @param array $data Invoice data
     */
    private function hydrate(array $data): void
    {
        $this->invoiceNumber = $data['invoice_number'] ?? $data['order_id'] ?? '';
        $this->orderId = $data['order_id'] ?? '';
        $this->invoiceDate = (int) ($data['invoice_date'] ?? time());
        $this->orderDate = (int) ($data['order_date'] ?? 0);
        $this->renewalDate = (int) ($data['renewal_date'] ?? 0);
        $this->customerId = (int) ($data['customer_id'] ?? 0);
        $this->billingInfo = $data['billing_info'] ?? [];
        $this->items = $data['items'] ?? [];
        $this->subtotal = (float) ($data['subtotal'] ?? 0);
        $this->discount = (float) ($data['discount'] ?? 0);
        $this->couponDiscount = (float) ($data['coupon_discount'] ?? 0);
        $this->tax = (float) ($data['tax'] ?? 0);
        $this->total = (float) ($data['total'] ?? 0);
        $this->currency = $data['currency'] ?? ['sign' => '$'];
        $this->paymentMethod = $data['payment_method'] ?? '';
        $this->paymentStatus = $data['payment_status'] ?? '';
        $this->companyInfo = $data['company_info'] ?? [];
    }

    /**
     * Get invoice number
     *
     * @return string
     */
    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    /**
     * Get order ID
     *
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * Get invoice date
     *
     * @return int
     */
    public function getInvoiceDate(): int
    {
        return $this->invoiceDate;
    }

    /**
     * Get formatted invoice date
     *
     * @param string $format Date format (default: WordPress date format)
     * @return string
     */
    public function getFormattedInvoiceDate(string $format = ''): string
    {
        if (!$this->invoiceDate) {
            return '';
        }
        $format = $format ?: get_option('date_format');
        return wp_date($format, $this->invoiceDate);
    }

    /**
     * Get order date
     *
     * @return int
     */
    public function getOrderDate(): int
    {
        return $this->orderDate;
    }

    /**
     * Get formatted order date
     *
     * @param string $format Date format (default: WordPress date format)
     * @return string
     */
    public function getFormattedOrderDate(string $format = ''): string
    {
        if (!$this->orderDate) {
            return '';
        }
        $format = $format ?: get_option('date_format');
        return wp_date($format, $this->orderDate);
    }

    /**
     * Get renewal date
     *
     * @return int
     */
    public function getRenewalDate(): int
    {
        return $this->renewalDate;
    }

    /**
     * Get formatted renewal date
     *
     * @param string $format Date format
     * @return string
     */
    public function getFormattedRenewalDate(string $format = ''): string
    {
        if (!$this->renewalDate) {
            return '';
        }
        $format = $format ?: get_option('date_format');
        return wp_date($format, $this->renewalDate);
    }

    /**
     * Check if this is a renewal invoice
     *
     * @return bool
     */
    public function isRenewalInvoice(): bool
    {
        return $this->renewalDate > 0;
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    /**
     * Get billing info
     *
     * @return array
     */
    public function getBillingInfo(): array
    {
        return $this->billingInfo;
    }

    /**
     * Get billing info field
     *
     * @param string $field Field name
     * @param string $default Default value
     * @return string
     */
    public function getBillingField(string $field, string $default = ''): string
    {
        return $this->billingInfo[$field] ?? $default;
    }

    /**
     * Get customer name from billing info
     *
     * @return string
     */
    public function getCustomerName(): string
    {
        $firstName = $this->getBillingField('first_name');
        $lastName = $this->getBillingField('last_name');
        return trim($firstName . ' ' . $lastName);
    }

    /**
     * Get customer email from billing info
     *
     * @return string
     */
    public function getCustomerEmail(): string
    {
        return $this->getBillingField('order_email') ?: $this->getBillingField('email');
    }

    /**
     * Get formatted billing address
     *
     * @return string
     */
    public function getFormattedBillingAddress(): string
    {
        $parts = [];

        $address1 = $this->getBillingField('address_1');
        $address2 = $this->getBillingField('address_2');
        if ($address1 || $address2) {
            $parts[] = trim($address1 . ' ' . $address2);
        }

        $location = [];
        if ($postcode = $this->getBillingField('postcode')) {
            $location[] = $postcode;
        }
        if ($city = $this->getBillingField('city')) {
            $location[] = $city;
        }
        if ($state = $this->getBillingField('state')) {
            $location[] = $state;
        }
        if ($country = $this->getBillingField('country')) {
            $location[] = $country;
        }

        if (!empty($location)) {
            $parts[] = implode(', ', $location);
        }

        return implode("\n", $parts);
    }

    /**
     * Get items
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get item count
     *
     * @return int
     */
    public function getItemCount(): int
    {
        return count($this->items);
    }

    /**
     * Get subtotal
     *
     * @return float
     */
    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    /**
     * Get discount
     *
     * @return float
     */
    public function getDiscount(): float
    {
        return $this->discount;
    }

    /**
     * Get coupon discount
     *
     * @return float
     */
    public function getCouponDiscount(): float
    {
        return $this->couponDiscount;
    }

    /**
     * Get total discount (discount + coupon)
     *
     * @return float
     */
    public function getTotalDiscount(): float
    {
        return $this->discount + $this->couponDiscount;
    }

    /**
     * Get tax
     *
     * @return float
     */
    public function getTax(): float
    {
        return $this->tax;
    }

    /**
     * Get total
     *
     * @return float
     */
    public function getTotal(): float
    {
        return $this->total;
    }

    /**
     * Get currency sign
     *
     * @return string
     */
    public function getCurrencySign(): string
    {
        return $this->currency['sign'] ?? '$';
    }

    /**
     * Get currency info
     *
     * @return array
     */
    public function getCurrency(): array
    {
        return $this->currency;
    }

    /**
     * Format price with currency
     *
     * @param float $amount Amount to format
     * @return string
     */
    public function formatPrice(float $amount): string
    {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($amount);
        }

        $sign = $this->getCurrencySign();
        $position = function_exists('wpdmpp_currency_sign_position')
            ? wpdmpp_currency_sign_position()
            : 'before';

        $formatted = number_format($amount, 2);

        return $position === 'before'
            ? $sign . $formatted
            : $formatted . $sign;
    }

    /**
     * Get formatted subtotal
     *
     * @return string
     */
    public function getFormattedSubtotal(): string
    {
        return $this->formatPrice($this->subtotal);
    }

    /**
     * Get formatted discount
     *
     * @return string
     */
    public function getFormattedDiscount(): string
    {
        return $this->formatPrice($this->discount);
    }

    /**
     * Get formatted tax
     *
     * @return string
     */
    public function getFormattedTax(): string
    {
        return $this->formatPrice($this->tax);
    }

    /**
     * Get formatted total
     *
     * @return string
     */
    public function getFormattedTotal(): string
    {
        return $this->formatPrice($this->total);
    }

    /**
     * Get payment method
     *
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return str_replace('WPDM_', '', $this->paymentMethod);
    }

    /**
     * Get raw payment method
     *
     * @return string
     */
    public function getRawPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    /**
     * Get payment status
     *
     * @return string
     */
    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    /**
     * Check if payment is completed
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->paymentStatus === 'Completed';
    }

    /**
     * Get company info
     *
     * @return array
     */
    public function getCompanyInfo(): array
    {
        return $this->companyInfo;
    }

    /**
     * Get company name
     *
     * @return string
     */
    public function getCompanyName(): string
    {
        return $this->companyInfo['name'] ?? '';
    }

    /**
     * Get company logo URL
     *
     * @return string
     */
    public function getCompanyLogo(): string
    {
        return $this->companyInfo['logo'] ?? '';
    }

    /**
     * Get company address
     *
     * @return string
     */
    public function getCompanyAddress(): string
    {
        return $this->companyInfo['address'] ?? '';
    }

    /**
     * Get signature image URL
     *
     * @return string
     */
    public function getSignature(): string
    {
        return $this->companyInfo['signature'] ?? '';
    }

    /**
     * Get thanks note
     *
     * @return string
     */
    public function getThanksNote(): string
    {
        return $this->companyInfo['thanks_note'] ?? '';
    }

    /**
     * Get terms and conditions
     *
     * @return string
     */
    public function getTerms(): string
    {
        return $this->companyInfo['terms'] ?? '';
    }

    /**
     * Check if billing info is complete
     *
     * @return bool
     */
    public function hasBillingInfo(): bool
    {
        return !empty($this->billingInfo['first_name'])
            && !empty($this->billingInfo['last_name'])
            && (!empty($this->billingInfo['address_1']) || !empty($this->billingInfo['address_2']));
    }

    /**
     * Get missing billing fields
     *
     * @return array
     */
    public function getMissingBillingFields(): array
    {
        $required = ['first_name', 'last_name', 'address_1', 'city', 'country'];
        $missing = [];

        foreach ($required as $field) {
            if (empty($this->billingInfo[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Get invoice URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        $url = add_query_arg([
            'id' => $this->orderId,
            'wpdminvoice' => '1',
        ], home_url());

        if ($this->renewalDate > 0) {
            $url = add_query_arg('renew', $this->renewalDate, $url);
        }

        return $url;
    }

    /**
     * Get PDF URL
     *
     * @return string
     */
    public function getPdfUrl(): string
    {
        return add_query_arg('wpdminvoice', 'pdf', $this->getUrl());
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'invoice_number' => $this->invoiceNumber,
            'order_id' => $this->orderId,
            'invoice_date' => $this->invoiceDate,
            'invoice_date_formatted' => $this->getFormattedInvoiceDate(),
            'order_date' => $this->orderDate,
            'order_date_formatted' => $this->getFormattedOrderDate(),
            'renewal_date' => $this->renewalDate,
            'renewal_date_formatted' => $this->getFormattedRenewalDate(),
            'is_renewal' => $this->isRenewalInvoice(),
            'customer_id' => $this->customerId,
            'customer_name' => $this->getCustomerName(),
            'customer_email' => $this->getCustomerEmail(),
            'billing_info' => $this->billingInfo,
            'billing_address' => $this->getFormattedBillingAddress(),
            'items' => $this->items,
            'item_count' => $this->getItemCount(),
            'subtotal' => $this->subtotal,
            'subtotal_formatted' => $this->getFormattedSubtotal(),
            'discount' => $this->discount,
            'discount_formatted' => $this->getFormattedDiscount(),
            'coupon_discount' => $this->couponDiscount,
            'tax' => $this->tax,
            'tax_formatted' => $this->getFormattedTax(),
            'total' => $this->total,
            'total_formatted' => $this->getFormattedTotal(),
            'currency' => $this->currency,
            'currency_sign' => $this->getCurrencySign(),
            'payment_method' => $this->getPaymentMethod(),
            'payment_status' => $this->paymentStatus,
            'is_paid' => $this->isPaid(),
            'company_info' => $this->companyInfo,
            'url' => $this->getUrl(),
            'pdf_url' => $this->getPdfUrl(),
        ];
    }

    /**
     * Convert to array for API response
     *
     * @return array
     */
    public function toApiResponse(): array
    {
        return $this->toArray();
    }
}
