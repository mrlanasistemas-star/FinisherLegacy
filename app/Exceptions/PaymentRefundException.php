<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An admin refund that could not be completed (the order is not
 * refundable, the gateway rejected it, or the gateway did not confirm it).
 * Admin-only: its message is shown to staff as-is.
 */
class PaymentRefundException extends RuntimeException {}
