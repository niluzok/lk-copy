<?php 

declare(strict_types=1);

namespace tests\unit\repository;

use Base\Unit;
use app\repository\CSMessageRepository;
use app\models\Courier;
use app\models\CourierServiceMessage;
use app\enums\courier\CourierServiceMessageTypeEnum;
use Codeception\Specify;

/**
 * Тестирование класса CSMessageRepository
 */
class CSMessageRepositoryTest extends Unit
{
    use Specify;

    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var CSMessageRepository
     */
    protected $repository;

    /**
     * Подготовка тестов
     */
    protected function _before()
    {
        $this->repository = new CSMessageRepository();

        // $this->tester->haveRecord(Client::class, [
        //     'id' => 1,
        //     'name' => 'Client1',
        //     'token' => 'Client1',
        // ]);

        // $this->tester->haveRecord(Order::class, [
        //     'id' => 1,
        //     'external_id' => 1,
        //     'client_id' => 1,
        // ]);

    }

    /**
     * Тестирование метода create
     */
    public function testCreate()
    {
        $this->specify("Метод create должен создавать и сохранять новое сообщение курьерской службы", function() {
            // Создаем курьера
            $courier = new Courier(['name' => 'Test Courier', 'code' => 'test_courier']);
            $this->assertTrue($courier->save());

            $config = [
                'courier_id' => $courier->id, 
                'message' => 'Test message', 
                'type' => CourierServiceMessageTypeEnum::Unknown->value
            ];

            $courierServiceMessage = $this->repository->create($config);

            $this->assertInstanceOf(CourierServiceMessage::class, $courierServiceMessage);
            $this->assertNotNull($courierServiceMessage->id);
            $this->assertEquals($courier->id, $courierServiceMessage->courier_id);
        });

        $this->specify("Метод create должен выбрасывать исключение при ошибке сохранения", function() {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/Не удалось сохранить/');

            // Неправильный config, чтобы вызвать ошибку
            $config = [
                'courier_id' => null, 
                'message' => null, 
                'type' => null
            ];

            $this->repository->create($config);
        });
    }

    /**
     * Тестирование метода findAllByCourier
     */
    public function testfindAllByCourier()
    {
        $this->specify("Курьер имеет сообщения", function() {
            // Создаем курьера
            $courier = new Courier(['name' => 'Test Courier', 'code' => 'test_courier']);
            $this->assertTrue($courier->save());

            // Создаем сообщения для курьера
            $message1 = new CourierServiceMessage([
                'courier_id' => $courier->id,
                'message' => 'First Message',
                'type' => CourierServiceMessageTypeEnum::Problem->value,
            ]);

            $this->assertTrue($message1->save());

            $message2 = new CourierServiceMessage([
                'courier_id' => $courier->id,
                'message' => 'Second Message',
                'type' => CourierServiceMessageTypeEnum::NoProblem->value,
            ]);
    
            $this->assertTrue($message2->save());

            $result = $this->repository->findAllByCourier($courier->id);

            // Проверяем результат
            $this->assertIsArray($result);
            $this->assertCount(2, $result);
        });

        $this->specify("Курьер не имеет сообщения", function() {
            // Создаем курьера
            $courier = new Courier(['name' => 'Empty Courier', 'code' => 'empty_courier']);
            $this->assertTrue($courier->save());
    
            $result = $this->repository->findAllByCourier($courier->id);

            // Проверяем результат
            $this->assertIsArray($result);
            $this->assertCount(0, $result);
        });
    }
}
