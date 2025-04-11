<?php declare(strict_types=1); 

namespace tests\classes\DeliveryException;

use app\classes\DeliveryException\DeliveryExceptionHandlerFactory;
use app\classes\DeliveryException\handlers\BRTDeliveryExceptionHandler;
use app\classes\DeliveryException\handlers\GenericExceptionHandler;
use app\models\Courier;
use Codeception\Specify;
use Base\Unit;
use app\classes\DeliveryException\handlers\DeliveryExceptionHandlerInterface;
use app\classes\DeliveryException\handlers\SDADeliveryExceptionHandler;
use app\classes\DeliveryException\commands\HandleExceptionCommand;
use Yii;

/**
 * Тестирование класса DeliveryExceptionHandlerFactory
 */
class DeliveryExceptionHandlerFactoryTest extends Unit
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
     * Подготовка тестов
     */
    protected function _before()
    {
        $command = Yii::createObject(HandleExceptionCommand::class);
        $this->factory = new DeliveryExceptionHandlerFactory($command);
    }

    /**
     * Тестирование метода createHandler
     */
    public function testCreateHandler()
    {
        $this->specify("Метод createHandler должен возвращать корректный обработчик для ID курьерской службы BRT", function() {
            $handler = $this->factory->createHandler(Courier::ID_BRT);
            $this->assertInstanceOf(BRTDeliveryExceptionHandler::class, $handler);
            $this->assertInstanceOf(DeliveryExceptionHandlerInterface::class, $handler);
            $this->assertEquals(Courier::ID_BRT, $handler->getCourierId());
        });

        $this->specify("Метод createHandler должен возвращать корректный обработчик для ID курьерской службы SDA", function() {
            $handler = $this->factory->createHandler(Courier::ID_SDA);
            $this->assertInstanceOf(SDADeliveryExceptionHandler::class, $handler);
            $this->assertInstanceOf(DeliveryExceptionHandlerInterface::class, $handler);
            $this->assertEquals(Courier::ID_SDA, $handler->getCourierId());
        });

        $this->specify("Метод createHandler должен возвращать GenericExceptionHandler для других ID курьерской службы", function() {
            $handler = $this->factory->createHandler(999);
            $this->assertInstanceOf(GenericExceptionHandler::class, $handler);
            $this->assertInstanceOf(DeliveryExceptionHandlerInterface::class, $handler);
            $this->assertEquals(999, $handler->getCourierId());
        });
    }
}
