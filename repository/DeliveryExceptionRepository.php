<?php

declare(strict_types=1);

namespace app\repository;

use app\models\CourierServiceMessage;
use app\models\DeliveryException;

/**
 * Репозиторий для управления сообщениями проблемной доставки
 */
class DeliveryExceptionRepository
{
    /**
     * Создает и сохраняет новое сообщение проблемной доставки
     *
     * @param array|null $config Конфигурация для инициализации модели
     * @return DeliveryException
     */
    public function create(array $config = null): DeliveryException
    {
        $deliveryException = new DeliveryException($config);
        if (!$deliveryException->save()) {
            throw new \RuntimeException('Не удалось сохранить DeliveryException: ' . print_r($deliveryException->getErrors(), true));
        }
        return $deliveryException;
    }
}
