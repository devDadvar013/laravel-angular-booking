<?php

namespace App\Exceptions;

use Exception;

class BookingConflictException extends Exception
{
    public function __construct(string $resourceId)
    {
        parent::__construct(
            "در بازه زمانی درخواستی، منبع \"{$resourceId}\" قبلاً رزرو شده است. لطفاً زمان دیگری انتخاب کنید."
        );
    }
}
