<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\enums;

use Yii;

/**
 * Трейт со вспомогательными функциями для ExceptionOwnerEnum
 *
 * @package app\classes\DeliveryException\enums
 */
trait ExceptionOwnerEnumHelperTrait
{
    public static function listForDropdown()
    {
        return self::labels();
    }
}
