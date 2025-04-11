<?php declare(strict_types=1);

use app\classes\DeliveryException\DeliveryExceptionFunc;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use Base\Unit;
use app\models\Order;
use app\models\Client;
use app\models\Delivery;
use app\models\OrderDelivery;
use app\models\StatDelivery;
use app\models\StatOrder;
use app\models\OrderAddress;
use app\models\DeliveryCourier;
use app\models\Courier;
use app\models\CourierServiceMessage;
use app\enums\courier\CourierServiceMessageTypeEnum;

/**
 * @see https://leadgidwebvork.monday.com/boards/3354701471/pulses/7324745675
 */
class OrderAndDeliveryCreateThenCreateDeliveryExceptionTest extends Unit
{
    public Order $order;
    /**
     * @var \IntegrationTester
     */
    protected $tester;
    
    protected function _before()
    {
        $this->tester->haveRecord(Client::class, [
            'id' => 1,
            'name' => 'client-name',
            'token' => 'client-token',
        ]);

        $orderId = $this->tester->haveRecord(Order::class, [
            'id' => 1,
            'external_id' => '1',
            'client_id' => 1,
            'country' => 'IT',
        ]);

        $this->order = $this->tester->grabRecord(Order::class, ['id' => $orderId]);

        $this->tester->haveRecord(OrderDelivery::class, [
            'order_id' => $orderId,
            'payment_method' => 1,
        ]);

        $this->tester->haveRecord(OrderAddress::class, [
            'order_id'   => $orderId,
            'country'    => 'IT',
            'street'     => '123 Example St',
            'house'      => '12B',
            'apartment'  => '3A',
            'zip_code'   => '12345',
            'city'       => 'Rome',
        ]);

        $this->createInitialPhases();
    }

    protected function _after()
    {
    }

    // tests
    public function testRun()
    {
        // Непроблемное сообщение - отдать логисту
        $messageForLogist = $this->noProblemMessageFromDB();
        
        // Владелец и фаза должны стать Логисту
        DeliveryExceptionFunc::createOneSilent($this->order->delivery, $messageForLogist);

        $this->assertEquals(ExceptionOwnerEnum::Logist->value, $this->order->delivery->deliveryException->owner);
        $this->assertEquals(DeliveryExceptionFunc::phaseFromExceptionOwner(ExceptionOwnerEnum::Logist), $this->order->delivery->deliveryException->getCurrentPhase()->one()->phase_id);

        $phases = array_map(function($op) { return $op->phase_id; }, $this->order->getOrderPhases()->all());

        $this->assertEquals([
            34, 
            37, 
            101,  // Logist
        ], $phases);

        // Новое сообщение - проблемное
        $messageForOperator = $this->problemMessageFromDB();

        // Должно уйти Оператору
        DeliveryExceptionFunc::createOneSilent($this->order->delivery, $messageForOperator);

        $phases = array_map(function($op) { return $op->phase_id; }, $this->order->getOrderPhases()->all());

        $this->assertEquals([
            34, 
            37, 
            101,  // Logist
            63,   // Operator
        ], $phases);
    }

    public function createInitialPhases()
    {
        // $this->order->saveOrder(3);
        Delivery::create($this->order);
        $this->order->delivery->delivery_status = 'in_stock';
        $this->order->delivery->save(false, ['delivery_status']);
        StatDelivery::createByDelivery($this->order->delivery);
        StatOrder::createByOrder($this->order);
        $this->order->delivery->setInStockTs($this->order->delivery->created_ts);

        $this->tester->haveRecord(DeliveryCourier::class, [
            'order_id' => $this->order->id,
            'courier_id' => Courier::ID_BRT,
        ]);

    }

    protected function problemMessageFromDB(int $i = 0)
    {
        return CourierServiceMessage::find()->where([
            'type' => CourierServiceMessageTypeEnum::Problem->value,
            'courier_id' => Courier::ID_BRT,
        ])->offset($i)->one()->message;
    }

    protected function noProblemMessageFromDB(int $i = 0)
    {
        return CourierServiceMessage::find()->where([
            'type' => CourierServiceMessageTypeEnum::NoProblem->value,
            'courier_id' => Courier::ID_BRT,
        ])->offset($i)->one()->message;
    }
}