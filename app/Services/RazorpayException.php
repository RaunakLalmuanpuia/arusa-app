<?php

namespace App\Services;

use RuntimeException;

/**
 * Any failure talking to Razorpay: an API error response or a network problem.
 */
class RazorpayException extends RuntimeException {}
