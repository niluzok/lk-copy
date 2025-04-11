<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\interfaces;

/**
 * Интерфейс для действия, которое выполняется при срабатывании правила
 */
interface ActionInterface
{
    /**
     * Выполняет действие
     *
     * @return void
     */
    public function run(): void;
}
