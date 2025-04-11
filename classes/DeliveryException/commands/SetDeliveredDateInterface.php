<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\commands;

use DateTimeInterface;

/**
 * Интерфейс для команды с установкой даты доставки для проблемной доставки
 */
interface SetDeliveredDateInterface
{
    /**
     * Устанавливает дату доставки
     *
     * @param DateTime $deliveredDate
     * @return self
     */
    public function setDeliveredDate(DateTimeInterface $deliveredDate): self;

    /**
     * Выполняет команду
     */
    public function run(): void;
}
