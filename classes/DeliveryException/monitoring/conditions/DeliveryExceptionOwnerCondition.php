<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\models\Delivery;

/**
 * Класс для проверки владельца исключения доставки
 *
 * Проверяет, что владелец исключения соответствует заданному значению из ExceptionOwnerEnum
 */
class DeliveryExceptionOwnerCondition implements ConditionInterface
{
    private Delivery $delivery;
    private ExceptionOwnerEnum $owner;

    public function __construct(Delivery $delivery, ExceptionOwnerEnum $owner)
    {
        $this->delivery = $delivery;
        $this->owner = $owner;
    }

    public function check(): bool
    {
        if ($this->delivery->deliveryException === null) {
            return false;
        }

        $currentOwner = $this->delivery->deliveryException->owner;

        if (is_string($currentOwner)) {
            $currentOwner = ExceptionOwnerEnum::from($currentOwner);
        }

        return $currentOwner === $this->owner;
    }
}
