<?php

declare(strict_types=1);

namespace tests\integration\classes\DeliveryException\monitoring\rules;

use app\models\Courier;
use app\models\Client;
use app\models\Order;
use app\models\OrderAddress;
use app\models\OrderPhase;
use app\models\Phase;
use app\models\Delivery;
use app\models\DeliveryCourier;
use app\models\OrderClient;
use app\models\OrderDelivery;
use app\models\StatOrder;
use app\models\StatDelivery;

/**
 * Трейт для подготовки записей в базе данных, необходимых для тестов правил
 */
trait RulesDbPrepareFixture
{
    /**
     * @var int $courierId ID курьера
     */
    protected int $courierId;

    /**
     * @var int $orderId ID первого заказа
     */
    protected int $orderId;

    /**
     * @var int $orderId2 ID второго заказа
     */
    protected int $orderId2;

    protected function createDatabaseRecords(): void
    {
        $this->courierId = $this->tester->haveRecord(Courier::class, [
            'name' => 'Courier',
            'code' => 'CR',
        ]);

        $clientId = $this->tester->haveRecord(Client::class, [
            'name' => 'client-name',
            'token' => 'client-token',
        ]);

        $this->orderId = $this->tester->haveRecord(Order::class, [
            'external_id' => $clientId,
            'client_id' => $clientId,
        ]);

        $this->orderId2 = $this->tester->haveRecord(Order::class, [
            'external_id' => $this->orderId - 1,
            'client_id' => $clientId,
        ]);

        $this->tester->haveRecord(OrderAddress::class, [
            'order_id' => $this->orderId,
            'country' => 'IT',
            'street' => 'Street',
            'zip_code' => 123456,
            'city' => 'City',
        ]);

        $this->tester->haveRecord(OrderAddress::class, [
            'order_id' => $this->orderId2,
            'country' => 'IT',
            'street' => 'Street',
            'zip_code' => 123456,
            'city' => 'City',
        ]);

        $systemOrderPhaseId = $this->tester->haveRecord(OrderPhase::class, [
            'order_id' => $this->orderId,
            'phase_id' => Phase::SYSTEM_PHASE_ID,
            'parent_id' => null,
        ]);

        $this->tester->haveRecord(OrderPhase::class, [
            'order_id' => $this->orderId,
            'phase_id' => Phase::SEND_IN_STOCK,
            'parent_id' => $systemOrderPhaseId,
        ]);

        $this->tester->haveRecord(Delivery::class, [
            'order_id' => $this->orderId,
            'created_ts' => '2024-06-10 12:00:00',
            'delivery_status' => Delivery::STATUS_IN_STOCK,
        ]);

        $this->tester->haveRecord(Delivery::class, [
            'order_id' => $this->orderId2,
            'created_ts' => '2024-06-10 12:00:00',
            'delivery_status' => Delivery::STATUS_IN_STOCK,
        ]);

        $this->tester->haveRecord(DeliveryCourier::class, [
            'order_id' => $this->orderId,
            'courier_id' => Courier::ID_BRT,
        ]);

        $this->tester->haveRecord(DeliveryCourier::class, [
            'order_id' => $this->orderId2,
            'courier_id' => Courier::ID_BRT,
        ]);

        $this->tester->haveRecord(OrderClient::class, [
            'order_id' => $this->orderId,
        ]);

        $this->tester->haveRecord(OrderClient::class, [
            'order_id' => $this->orderId2,
        ]);

        $this->tester->haveRecord(OrderDelivery::class, [
            'order_id' => $this->orderId,
            'payment_method' => 1,
        ]);

        $this->tester->haveRecord(OrderDelivery::class, [
            'order_id' => $this->orderId2,
            'payment_method' => 2,
        ]);

        $this->tester->haveRecord(StatOrder::class, [
            'order_id' => $this->orderId,
            'country' => 'IT',
            'operator_id' => 2,
        ]);

        $this->tester->haveRecord(StatOrder::class, [
            'order_id' => $this->orderId2,
            'country' => 'IT',
            'operator_id' => 2,
        ]);

        $this->tester->haveRecord(StatDelivery::class, [
            'order_id' => $this->orderId,
            'delivery_status' => Delivery::STATUS_IN_STOCK,
            'operator_status' => null,
        ]);

        $this->tester->haveRecord(StatDelivery::class, [
            'order_id' => $this->orderId2,
            'delivery_status' => Delivery::STATUS_IN_STOCK,
            'operator_status' => null,
        ]);
    }
}
