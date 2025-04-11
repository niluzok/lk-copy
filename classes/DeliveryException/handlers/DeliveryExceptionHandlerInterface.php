<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\handlers;

use app\models\Delivery;

/**
 * Интерфейс для обработчиков сообщений проблемной доставки
 */
interface DeliveryExceptionHandlerInterface
{
    /**
     * Обработка сообщения проблемной доставки
     *
     * @param Delivery $delivery
     * @param string $message Сообщение от КС
     */
    public function handleException(Delivery $delivery, string $message): void;

    /**
     * Получение ID курьерской службы
     *
     * @return int
     */
    public function getCourierId(): int;
}
