<?php

namespace App\Services;

use RuntimeException;

/** Google API の失敗。メッセージに個人情報（宛先・本文）を含めない。 */
class GoogleCalendarException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
