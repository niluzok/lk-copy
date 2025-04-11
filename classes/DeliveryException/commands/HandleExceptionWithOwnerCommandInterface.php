<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\commands;

/**
 * Интерфейс, объединяющий HandleExceptionCommandInterface, SetExceptionOwnerInterface и SetDeliveredDateInterface
 */
interface HandleExceptionWithOwnerCommandInterface extends HandleExceptionCommandInterface, SetExceptionOwnerInterface, SetDeliveredDateInterface
{
}
