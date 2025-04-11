<?php

declare(strict_types=1);

namespace app\classes\DeliveryException;

use app\models\Courier;
use app\classes\DeliveryException\handlers\BRTDeliveryExceptionHandler;
use app\classes\DeliveryException\handlers\GenericExceptionHandler;
use app\classes\DeliveryException\handlers\SDADeliveryExceptionHandler;
use app\classes\DeliveryException\handlers\DeliveryExceptionHandlerInterface;
use app\classes\DeliveryException\commands\HandleExceptionWithOwnerCommandInterface;
use app\classes\DeliveryException\handlers\PostATDeliveryExceptionHandler;

/**
 * Фабрика для создания обработчиков сообщений от КС
 *
 * Для каждой КС нужный обработчик
 */
class DeliveryExceptionHandlerFactory
{
    /**
     * @var HandleExceptionWithOwnerCommandInterface
     */
    protected HandleExceptionWithOwnerCommandInterface $handleExceptionCommand;

    /**
     * Конструктор
     *
     * @param HandleExceptionWithOwnerCommandInterface $handleExceptionCommand
     */
    public function __construct(HandleExceptionWithOwnerCommandInterface $handleExceptionCommand)
    {
        $this->handleExceptionCommand = $handleExceptionCommand;
    }

    /**
     * Создание обработчика для курьерской службы
     *
     * @param int $courierId
     * @return DeliveryExceptionHandlerInterface
     */
    public function createHandler(int $courierId): DeliveryExceptionHandlerInterface
    {
        switch ($courierId) {
            case Courier::ID_BRT:
                return new BRTDeliveryExceptionHandler($this->handleExceptionCommand);
            case Courier::ID_SDA:
                return new SDADeliveryExceptionHandler($this->handleExceptionCommand);
            default:
                return new GenericExceptionHandler($courierId, $this->handleExceptionCommand);
        }
    }
}
