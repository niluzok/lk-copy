<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\interfaces;

/**
 * Интерфейс для условия выполнения правила
 */
interface ConditionInterface
{
    /**
     * Проверяет выполнение условия
     *
     * @return bool Возвращает true, если условие выполнено
     */
    public function check(): bool;
}
