<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\commands;

use app\classes\DeliveryException\enums\ExceptionOwnerEnum;

/**
 * Команда обработки сообщений проблемной доставки с заморозкой (не изменяется)
 * владельца проблемной доставки
 */
class HandleExceptionOwnerFreezeCommand extends HandleExceptionCommand
{
    protected function changeExceptionOwner(?ExceptionOwnerEnum $exceptionOwner, bool $saveDeliveryExceptionModel = true): void
    {
        ;
    }
}
