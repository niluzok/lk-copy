<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\commands;

use app\classes\DeliveryException\enums\ExceptionOwnerEnum;

/**
 * Интерфейс команды для установки владельца проблемной доставки
 */
interface SetExceptionOwnerInterface
{
    /**
     * Устанавливает владельца проблемной доставки
     *
     * @param ExceptionOwnerEnum $exceptionOwner
     * @return self
     */
    public function setExceptionOwner(ExceptionOwnerEnum $exceptionOwner): self;

    /**
     * Выполняет команду
     */
    public function run(): void;
}
