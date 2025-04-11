<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\handlers;

use app\models\Courier;

/**
 * Обработчик сообщений проблемной доставки для SDA
 * Он должен быть такой же как для BRT со своими проблемными/непроблемными
 * сообщениями
 */
class SDADeliveryExceptionHandler extends BRTDeliveryExceptionHandler
{
    public function getCourierId(): int
    {
        return Courier::ID_SDA;
    }
}
