<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use app\models\Delivery;

/**
 * Класс для проверки, должно ли исключение для доставки существовать или нет
 */
class DeliveryExceptionExistsCondition implements ConditionInterface
{
    /**
     * @var Delivery Экземпляр модели Delivery
     */
    private Delivery $delivery;

    /**
     * @var bool Определяет, должно ли исключение существовать
     */
    private bool $shouldExist;

    /**
     * Конструктор принимает объект доставки и флаг наличия исключения
     *
     * @param Delivery $delivery Объект доставки
     * @param bool $shouldExist Флаг, указывающий, должно ли исключение существовать
     */
    public function __construct(Delivery $delivery, bool $shouldExist)
    {
        $this->delivery = $delivery;
        $this->shouldExist = $shouldExist;
    }

    /**
     * Проверяет, соответствует ли наличие исключения для доставки ожидаемому значению
     *
     * @return bool Возвращает true, если условие выполнено (наличие или отсутствие исключения)
     */
    public function check(): bool
    {
        $exceptionExists = $this->delivery->deliveryException !== null;
        return $this->shouldExist === $exceptionExists;
    }
}
