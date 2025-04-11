<?php declare(strict_types=1); 

namespace tests\classes\DeliveryException;

use app\classes\DeliveryException\DeliveryExceptionService;
use app\classes\DeliveryException\DeliveryExceptionHandlerFactory;
use app\models\Delivery;
use app\models\Courier;
use Yii;
use Codeception\Specify;
use Codeception\Stub;
use Base\Unit;
use app\classes\DeliveryException\handlers\DeliveryExceptionHandlerInterface;
use app\models\DeliveryCourier;
use Codeception\Stub\Expected;

/**
 * Тестирование класса DeliveryExceptionService
 */
class DeliveryExceptionServiceTest extends Unit
{
    use Specify;

    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var DeliveryExceptionHandlerFactory
     */
    protected $factory;

    /**
     * @var DeliveryExceptionService
     */
    protected $service;

    /**
     * @var Delivery
     */
    protected $delivery;

    /**
     * Подготовка общих моков
     */
    protected function _before()
    {
        $this->factory = $this->make(DeliveryExceptionHandlerFactory::class, [
            'createHandler' => function($courierId) {
                return $this->makeEmpty(DeliveryExceptionHandlerInterface::class, [
                    'getCourierId' => $courierId,
                    'handleException' => null,
                ]);
            }
        ]);

        $this->service = new DeliveryExceptionService($this->factory);

        $this->delivery = $this->make(Delivery::class, [
            'exception' => 'Some delivery exception',
        ]);

        $this->delivery->populateRelation('deliveryCourier', new DeliveryCourier([
            'courier_id' => Courier::ID_BRT
        ]));
    }

    /**
     * Получение значения приватного или защищенного свойства
     *
     * @param object $object
     * @param string $property
     * @return mixed
     */
    protected function getObjectAttribute($object, $property)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    protected function mockLogComponent()
    {
        Yii::configure(Yii::$app, [
            'components' => [
                'log' => [
                    'class' => 'yii\log\Dispatcher',
                    'targets' => [
                        new class extends \yii\log\Target 
                        {
                            public function init()
                            {
                                parent::init();
                                $this->setLevels(['error']);
                            }
                            public function export() {}
                        }, 
                    ],
                ],
            ],
        ]);
    }

    /**
     * Тестирование конструктора
     */
    public function testConstructor()
    {
        $this->specify("Конструктор должен регистрировать обработчики для всех курьерских служб: все ключи курьерских служб должны присутствовать в массиве обработчиков", function() {
            // Конструктор уже был вызван в _before
            $handlers = $this->getObjectAttribute($this->service, 'handlers');

            $this->assertArrayHasKey(Courier::ID_BRT, $handlers);
            $this->assertArrayHasKey(Courier::ID_SDA, $handlers);
            $this->assertArrayHasKey(Courier::ID_FD_BRT, $handlers);
            $this->assertArrayHasKey(Courier::ID_CORREOS, $handlers);
            $this->assertArrayHasKey(Courier::ID_GLS_ES, $handlers);
            $this->assertArrayHasKey(Courier::ID_LOGPOINT, $handlers);
            $this->assertArrayHasKey(Courier::ID_POST_AT, $handlers);
        });
    }

    /**
     * Тестирование метода registerCourierHandler
     */
    public function testRegisterCourierHandler()
    {
        $this->specify("Метод registerCourierHandler должен регистрировать обработчик для курьерской службы: добавляет обработчик в массив обработчиков", function() {
            $handler = $this->factory->createHandler(Courier::ID_BRT);
            $this->service->registerCourierHandler($handler);

            $handlers = $this->getObjectAttribute($this->service, 'handlers');

            $this->assertArrayHasKey(Courier::ID_BRT, $handlers);
            $this->assertSame($handler, $handlers[Courier::ID_BRT]);
            $this->tester->assertInstanceOf(DeliveryExceptionHandlerInterface::class, $handler);
        });
    }

    /**
     * Тестирование метода processException
     */
    public function testProcessException()
    {
        $this->specify("Метод processException должен обрабатывать сообщения проблемной доставки, если обработчик найден: вызывается метод handleException у соответствующего обработчика", function() {
            $handler = $this->factory->createHandler(Courier::ID_BRT);

            $handler = Stub::update($handler, [
                'handleException' => Expected::once()
            ]);

            $this->service->registerCourierHandler($handler);
            $this->service->processException($this->delivery, 'message');
        });

        $this->specify("Метод processException должен логировать ошибку, если обработчик не найден: сообщение об ошибке должно содержать ID курьерской службы", function() {
            $delivery = $this->make(Delivery::class);
            $delivery->populateRelation('deliveryCourier', new DeliveryCourier([
                'courier_id' => 999, // Неизвестный ID курьерской службы
            ]));

            $this->mockLogComponent();

            $this->service->processException($delivery, 'Some delivery exception');

            $this->markTestIncomplete();

            // $logMessages = Yii::$app->log->targets[0]->messages;
            // $this->assertNotEmpty($logMessages);

            // $message = $logMessages[0][0];
            // $this->assertStringContainsString('No delivery exception handler found for courier ID: 999', $message);
        });
    }
}
