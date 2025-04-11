<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\commands;

use app\models\Delivery;

/**
 * Интерфейс команды обработки сообщений проблемной доставки
 */
interface HandleExceptionCommandInterface
{
    /**
     * Устанавливает модель доставки
     *
     * @param Delivery $delivery
     * @return self
     */
    public function setDelivery(Delivery $delivery): self;

    /**
     * Устаавливает сообщение КС для сохранения
     *
     * @param   string  $message
     *
     * @return  self
     */
    public function setMessage(string $message): self;

    /**
     * Выполняет команду
     */
    public function run(): void;
}
