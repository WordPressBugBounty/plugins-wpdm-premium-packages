<?php
/**
 * Withdraw Entity
 *
 * Represents a withdrawal/payout request.
 *
 * @package WPDMPP\Payout
 * @since 7.0.0
 */

namespace WPDMPP\Payout;

defined('ABSPATH') || exit;

class Withdraw
{
    /**
     * Withdrawal statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Withdrawal ID
     *
     * @var int
     */
    private int $id = 0;

    /**
     * User ID
     *
     * @var int
     */
    private int $userId = 0;

    /**
     * Withdrawal amount
     *
     * @var float
     */
    private float $amount = 0.0;

    /**
     * Payment method ID
     *
     * @var string
     */
    private string $paymentMethod = '';

    /**
     * Status
     *
     * @var string
     */
    private string $status = self::STATUS_PENDING;

    /**
     * Transaction ID (from payment processor)
     *
     * @var string
     */
    private string $transactionId = '';

    /**
     * Request date
     *
     * @var int
     */
    private int $requestDate = 0;

    /**
     * Processed date
     *
     * @var int
     */
    private int $processedDate = 0;

    /**
     * Note/reason
     *
     * @var string
     */
    private string $note = '';

    /**
     * User object
     *
     * @var \WP_User|null
     */
    private ?\WP_User $user = null;

    /**
     * Create from database row
     *
     * @param object $row Database row
     * @return Withdraw
     */
    public static function fromRow(object $row): Withdraw
    {
        $withdraw = new self();
        $withdraw->id = (int) ($row->id ?? $row->ID ?? 0);
        $withdraw->userId = (int) ($row->uid ?? $row->user_id ?? 0);
        $withdraw->amount = (float) ($row->amount ?? 0);
        $withdraw->paymentMethod = $row->payment_method ?? '';
        $withdraw->status = $row->status ?? self::STATUS_PENDING;
        $withdraw->transactionId = $row->trans_id ?? $row->transaction_id ?? '';
        $withdraw->requestDate = (int) ($row->date ?? $row->request_date ?? 0);
        $withdraw->processedDate = (int) ($row->processed_date ?? 0);
        $withdraw->note = $row->note ?? '';

        // Load user
        if ($withdraw->userId > 0) {
            $withdraw->user = get_user_by('id', $withdraw->userId);
        }

        return $withdraw;
    }

    /**
     * Create from array data
     *
     * @param array $data Withdrawal data
     * @return Withdraw
     */
    public static function create(array $data): Withdraw
    {
        $withdraw = new self();
        $withdraw->id = (int) ($data['id'] ?? 0);
        $withdraw->userId = (int) ($data['uid'] ?? $data['user_id'] ?? 0);
        $withdraw->amount = (float) ($data['amount'] ?? 0);
        $withdraw->paymentMethod = $data['payment_method'] ?? '';
        $withdraw->status = $data['status'] ?? self::STATUS_PENDING;
        $withdraw->transactionId = $data['trans_id'] ?? $data['transaction_id'] ?? '';
        $withdraw->requestDate = (int) ($data['date'] ?? $data['request_date'] ?? time());
        $withdraw->processedDate = (int) ($data['processed_date'] ?? 0);
        $withdraw->note = $data['note'] ?? '';

        // Load user
        if ($withdraw->userId > 0) {
            $withdraw->user = get_user_by('id', $withdraw->userId);
        }

        return $withdraw;
    }

    // Getters

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUser(): ?\WP_User
    {
        return $this->user;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getFormattedAmount(): string
    {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($this->amount);
        }
        return number_format($this->amount, 2);
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => __('Pending', 'wpdm-premium-packages'),
            self::STATUS_PROCESSING => __('Processing', 'wpdm-premium-packages'),
            self::STATUS_COMPLETED => __('Completed', 'wpdm-premium-packages'),
            self::STATUS_REJECTED => __('Rejected', 'wpdm-premium-packages'),
            self::STATUS_CANCELLED => __('Cancelled', 'wpdm-premium-packages'),
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getRequestDate(): int
    {
        return $this->requestDate;
    }

    public function getFormattedRequestDate(): string
    {
        return date(get_option('date_format'), $this->requestDate);
    }

    public function getProcessedDate(): int
    {
        return $this->processedDate;
    }

    public function getFormattedProcessedDate(): string
    {
        if ($this->processedDate <= 0) {
            return '';
        }
        return date(get_option('date_format'), $this->processedDate);
    }

    public function getNote(): string
    {
        return $this->note;
    }

    // Status checks

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canProcess(): bool
    {
        return $this->isPending() || $this->isProcessing();
    }

    // Setters (fluent)

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setProcessedDate(int $date): self
    {
        $this->processedDate = $date;
        return $this;
    }

    public function setNote(string $note): self
    {
        $this->note = $note;
        return $this;
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
            'uid' => $this->userId,
            'amount' => $this->amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'payment_method' => $this->paymentMethod,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'trans_id' => $this->transactionId,
            'date' => $this->requestDate,
            'formatted_date' => $this->getFormattedRequestDate(),
            'processed_date' => $this->processedDate,
            'formatted_processed_date' => $this->getFormattedProcessedDate(),
            'note' => $this->note,
            'user' => $this->user ? [
                'id' => $this->user->ID,
                'name' => $this->user->display_name,
                'email' => $this->user->user_email,
            ] : null,
        ];
    }

    /**
     * Convert to database array for saving
     *
     * @return array
     */
    public function toDatabase(): array
    {
        return [
            'uid' => $this->userId,
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'status' => $this->status,
            'trans_id' => $this->transactionId,
            'date' => $this->requestDate,
            'processed_date' => $this->processedDate,
            'note' => $this->note,
        ];
    }
}
