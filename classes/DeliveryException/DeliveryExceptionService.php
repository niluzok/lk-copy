<?php

declare(strict_types=1);

namespace app\classes\DeliveryException;

use app\models\Delivery;
use app\models\Courier;
use Yii;
use app\classes\DeliveryException\handlers\DeliveryExceptionHandlerInterface;
use app\models\DeliveryException;

/**
 * Сервис для обработки сообщений проблемной доставки
 */
class DeliveryExceptionService
{
    /**
     * @var DeliveryExceptionHandlerInterface[] Массив обработчиков сообщений проблемной доставки
     */
    private $handlers = [];

    /**
     * @var DeliveryExceptionHandlerFactory Фабрика для создания обработчиков сообщений проблемной доставки
     */
    private $factory;

    /**
     * Конструктор
     *
     * @param DeliveryExceptionHandlerFactory $factory
     */
    public function __construct(DeliveryExceptionHandlerFactory $factory)
    {
        $this->factory = $factory;
        
        $this->registerCourierHandler($this->factory->createHandler(Courier::ID_BRT));
        $this->registerCourierHandler($this->factory->createHandler(Courier::ID_SDA));

        $this->registerCourierHandler($this->factory->createHandler(Courier::ID_POST_AT));
        $this->registerCourierHandler($this->factory->createHandler(Courier::ID_FD_BRT));
        $this->registerCourierHandler($this->factory->createHandler(Courier::ID_CORREOS));
        $this->registerCourierHandler($this->factory->createHandler(Courier::ID_GLS_ES));
        $this->registerCourierHandler($this->factory->createHandler(Courier::ID_LOGPOINT));
    }

    /**
     * Регистрация обработчика сообщений проблемной доставки для курьера
     *
     * @param DeliveryExceptionHandlerInterface $handler
     */
    public function registerCourierHandler(DeliveryExceptionHandlerInterface $handler): void
    {
        $courierId = $handler->getCourierId();
        $this->handlers[$courierId] = $handler;
    }

    /**
     * Проверяет, было ли сообщение уже обработано
     *
     * @param Delivery $delivery
     * @param string $message Сообщение от КС
     *
     * @return bool|null Возвращает true, если сообщение уже обработано, иначе null
     */
    protected function isAlreadyProcessed(Delivery $delivery, string $message): ?bool
    {
        /** @var DeliveryException */
        $existingDeliveryException = $delivery->deliveryException;

        //  Если по крону одно и тоже сообщение обрабатывается
        if ($existingDeliveryException && $existingDeliveryException->isTheSameAs($message)) {
            return true;
        }

        return false;
    }

    /**
     * Обработка сообщения проблемной доставки
     *
     * @param Delivery $delivery
     * @param string $message Сообщение от КС
     */
    public function processException(Delivery $delivery, string $message): void
    {
        if ($this->isAlreadyProcessed($delivery, $message)) {
            return;
        }

        if (!$delivery->deliveryCourier) {
            Yii::error("No DeliveryCourier exist for order #{$delivery->order_id}", __METHOD__);
            return;
        }

        $courierId = $delivery->deliveryCourier->courier_id;
        if ($this->isCourierHandlerRegistered($courierId)) {
            $this->handlers[$courierId]->handleException($delivery, $message);
        } else {
            Yii::error("No delivery exception handler found for courier ID: $courierId", __METHOD__);
        }
    }

    /**
     * Проверяет зарегистрирован ли обработчик для заданной курьерки
     *
     * @param   int   $courierId  Ид КС
     *
     * @return  bool
     */
    public function isCourierHandlerRegistered(int $courierId): bool
    {
        return isset($this->handlers[$courierId]);
    }
}
