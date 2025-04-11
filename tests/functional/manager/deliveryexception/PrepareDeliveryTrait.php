<?php 

declare(strict_types=1); 

namespace tests\functional\manager\deliveryexception;

use app\models\Courier;
use app\models\Client;
use app\models\Order;
use app\models\OrderAddress;
use app\models\Delivery;
use app\models\StatDelivery;
use app\models\DeliveryCourier;
use app\models\OrderClient;
use app\models\OrderDelivery;
use app\models\StatOrder;
use FunctionalTester;

/**
 * Вспомогательный трейт для функциональных тестов DeliverException
 */
trait PrepareDeliveryTrait
{
    protected $orderId;
    protected $orderId2;
    protected $courierId;

    /**
     * Создание заказа и доставки и всех связаных сущностей без ексепшена
     *
     * @param   FunctionalTester  $I  
     */
    protected function createDeliveryDbRecords(FunctionalTester $I, int $courierId = Courier::ID_BRT)
    {
        if(!Courier::find()->where(['id' => $courierId])->exists()) {
            $I->haveRecord(Courier::class, [
                'id' => $courierId,
                'name' => 'Courier',
                'code' => 'CR',
            ]);
        }

        $this->courierId = $courierId;

        // Creating Client records
        $clientId = $I->haveRecord(Client::class, [
            // 'id' => 1,
            'name' => 'client-name',
            'token' => 'client-token',
        ]);

        // Creating Order records
        $this->orderId = $I->haveRecord(Order::class, [
            // 'id' => 1,
            'external_id' => $clientId,
            'client_id' => $clientId,
        ]);
        $this->orderId2 = $I->haveRecord(Order::class, [
            'external_id' => $this->orderId - 1,
            'client_id' => $clientId,
        ]);

        // Creating OrderAddress records
        $I->haveRecord(OrderAddress::class, [
            'order_id' => $this->orderId,
            'country' => 'IT',
            'street' => 'Street',
            'zip_code' => 123456,
            'city' => 'City',
        ]);
        $I->haveRecord(OrderAddress::class, [
            'order_id' => $this->orderId2,
            'country' => 'IT',
            'street' => 'Street',
            'zip_code' => 123456,
            'city' => 'City',
        ]);

        // Creating Delivery records
        $I->haveRecord(Delivery::class, [
            'order_id' => $this->orderId,
            'created_ts' => '2024-06-10 12:00:00',
            'delivery_status' => Delivery::STATUS_IN_STOCK,
        ]);
        $I->haveRecord(Delivery::class, [
            'order_id' => $this->orderId2,
            'created_ts' => '2024-06-10 12:00:00',
            'delivery_status' => Delivery::STATUS_RETURN,
        ]);

        // Creating StatDelivery records
        $I->haveRecord(StatDelivery::class, [
            'order_id' => $this->orderId,
            'delivery_status' => Delivery::STATUS_IN_STOCK,
            'operator_status' => null,
        ]);
        $I->haveRecord(StatDelivery::class, [
            'order_id' => $this->orderId2,
            'delivery_status' => Delivery::STATUS_RETURN,
            'operator_status' => null,
        ]);

        // Creating DeliveryCourier records
        $I->haveRecord(DeliveryCourier::class, [
            'order_id' => $this->orderId,
            'courier_id' => $courierId,
        ]);
        $I->haveRecord(DeliveryCourier::class, [
            'order_id' => $this->orderId2,
            'courier_id' => $courierId,
        ]);

        // Creating OrderClient records
        $I->haveRecord(OrderClient::class, [
            'order_id' => $this->orderId,
        ]);
        $I->haveRecord(OrderClient::class, [
            'order_id' => $this->orderId2,
        ]);

        // Creating OrderDelivery records
        $I->haveRecord(OrderDelivery::class, [
            'order_id' => $this->orderId,
            'payment_method' => 1,
        ]);
        $I->haveRecord(OrderDelivery::class, [
            'order_id' => $this->orderId2,
            'payment_method' => 2,
        ]);

        // Creating StatOrder records
        $I->haveRecord(StatOrder::class, [
            'order_id' => $this->orderId,
            'country' => 'IT',
            'operator_id' => 2,
        ]);
        $I->haveRecord(StatOrder::class, [
            'order_id' => $this->orderId2,
            'country' => 'IT',
            'operator_id' => 2,
        ]);
    }
}