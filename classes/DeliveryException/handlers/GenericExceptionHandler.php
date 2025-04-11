<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\handlers;

use app\models\Delivery;
use app\classes\DeliveryException\commands\HandleExceptionWithOwnerCommandInterface;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;

/**
 * Общий обработчик сообщений проблемной доставки для курьерских служб для которых
 * еще нет бизнес процесса работы с проблемными
 *
 * Только добавляет сообщение
 */
class GenericExceptionHandler implements DeliveryExceptionHandlerInterface
{
    protected HandleExceptionWithOwnerCommandInterface $command;

    /**
     * @var int ID курьерской службы
     */
    private int $courierId;

    /**
     * Конструктор
     *
     * @param int $courierId
     * @param HandleExceptionWithOwnerCommandInterface $command
     */
    public function __construct(int $courierId, HandleExceptionWithOwnerCommandInterface $command)
    {
        $this->courierId = $courierId;
        $this->command = $command;
    }

    /**
     * Обработка сообщения проблемной доставки
     *
     * @param Delivery $delivery
     * @param string $message Сообщение от КС
     */
    public function handleException(Delivery $delivery, string $message): void
    {
        $this->command
            ->setDelivery($delivery)
            ->setMessage($message)
            ->setExceptionOwner(ExceptionOwnerEnum::Operator)
            ->run();
    }

    /**
     * Получение ID курьерской службы
     *
     * @return int
     */
    public function getCourierId(): int
    {
        return $this->courierId;
    }
}
