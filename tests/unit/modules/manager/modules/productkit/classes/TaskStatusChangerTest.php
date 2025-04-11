<?php

declare(strict_types=1);

namespace tests\unit;

use Yii;
use Throwable;
use RuntimeException;
use yii\web\Controller;
use yii\web\Response;
use Base\Unit;
use Codeception\Specify;
use Codeception\Stub;
use app\repository\ConsignmentMovementRepository;
use app\modules\manager\modules\productkit\classes\TaskStatusChanger;
use app\models\productkit\ProductKitTaskStatus;
use app\models\productkit\ProductKitTask;
use app\models\Stock as Warehouse;
use app\models\MovingGoods;
use app\models\ConsignmentMovement;
use app\models\BoxSizeType as BoxSizeType;
use app\models\productKit\ProductKitBox as Box;
use app\classes\productMovement\creator\ProductMovementCreator;
use BadMethodCallException;

/**
 * Тесты для класса TaskStatusChanger
 */
class TaskStatusChangerTest extends Unit
{
    use Specify;

    /**
     * @var ProductKitTaskStatus
     */
    private $statusFrom;

    /**
     * @var ProductKitTaskStatus
     */
    private $statusTo;

    /**
     * @var ProductKitTask
     */
    private $task;

    /**
     * @var TaskStatusChanger
     */
    private $taskStatusChanger;

    public function _before()
    {
        $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::UNPROCESSED]);
        $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::IN_PROCESS]);
        $this->task = $this->make(ProductKitTask::class);

        $box1 = $this->make(Box::class, [
            'consignment_id' => 1,
            'pallet_id' => 1,
            'ongoing_product_movement_id' => 1,
        ]);
        $box1->populateRelation('sizeType', $this->make(BoxSizeType::class, ['pcs_per_ca' => 10]));
        $box1->populateRelation('ongoingProductMovement', $this->make(MovingGoods::class));
        
        $box2 = $this->make(Box::class, [
            'consignment_id' => 2,
            'pallet_id' => 2,
            'ongoing_product_movement_id' => 1,
        ]);
        $box2->populateRelation('sizeType', $this->make(BoxSizeType::class, ['pcs_per_ca' => 20]));
        $box2->populateRelation('ongoingProductMovement', $this->make(MovingGoods::class));
        
        $this->task->populateRelation('boxes', [$box1, $box2]);

        $warehouseFrom = $this->make(Warehouse::class, ['name' => 'Warehouse From']);
        $warehouseTo = $this->make(Warehouse::class, ['name' => 'Warehouse To']);
        $this->task->populateRelation('warehouseFrom', $warehouseFrom);
        $this->task->populateRelation('warehouseTo', $warehouseTo);

        $this->mockYiiTransaction();
    }

    private function expectControllerRedirectToFileUpload()
    {
        Yii::$app->controller = $this->makeEmpty(Controller::class, [
            'redirect' => Stub\Expected::once($this->make(Response::class, ['send' => Stub\Expected::once()]))
        ]);
    }

    private function mockProductMovementCreator($createMovementExpectedTimes = 2, $registerExpectedTimes = 2)
    {
        $movingGoodsMock = $this->make(MovingGoods::class, [
            'save' => true, 
            'isRegistered' => true,
        ]);

        $productMovementCreatorMock = $this->make(ProductMovementCreator::class, [
            'create' => Stub\Expected::exactly($createMovementExpectedTimes, function() use (&$movingGoodsMock) { // Ожидается, что сохранение будет вызвано дважды (для двух коробок)
                $movingGoodsMock = $this->make(MovingGoods::class, [
                    'save' => Stub\Expected::once(true), 
                    'isRegistered' => true,
                ]);
                return $movingGoodsMock;
            }),
            'register' => Stub\Expected::exactly($registerExpectedTimes, $movingGoodsMock)
        ]);

        Yii::$container->set(ProductMovementCreator::class, $productMovementCreatorMock);
    }

    private function mockConsignmentMovementRepository($isRegistered = true)
    {
        $consignmentMovementRepositoryMock = $this->make(ConsignmentMovementRepository::class, [
            'findOne' => function() use ($isRegistered) {
                $consignmentMovementMock = $this->make(ConsignmentMovement::class);
                $consignmentMovementMock->populateRelation('movingGoods', $this->make(MovingGoods::class, [
                    'isRegistered' => $isRegistered,
                ]));
                return $consignmentMovementMock;
            }
        ]);

        Yii::$container->set(ConsignmentMovementRepository::class, $consignmentMovementRepositoryMock);
    }

    /**
     * Тестирует метод process для изменения статуса с UNPROCESSED на IN_PROCESS
     * @throws Throwable
     */
    public function testMethodProcessUnprocessedToInProcess()
    {
        $this->specify("должен успешно изменить статус задачи с UNPROCESSED на IN_PROCESS", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::UNPROCESSED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::IN_PROCESS]);
            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для изменения статуса с IN_PROCESS на AWAITING_SHIPMENT
     * @throws Throwable
     */
    public function testMethodProcessInProcessToAwaitingShipment()
    {
        $this->specify("должен успешно изменить статус задачи с IN_PROCESS на AWAITING_SHIPMENT", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::IN_PROCESS]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::AWAITING_SHIPMENT]);
            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для изменения статуса с UNPROCESSED на DECLINED
     * @throws Throwable
     */
    public function testMethodProcessUnprocessedToDeclined()
    {
        $this->specify("должен успешно изменить статус задачи с UNPROCESSED на DECLINED", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::UNPROCESSED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::DECLINED]);
            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для изменения статуса с AWAITING_SHIPMENT на SHIPPED
     * @throws Throwable
     */
    public function testMethodProcessAwaitingShipmentToShipped()
    {
        $this->specify("должен успешно изменить статус задачи с AWAITING_SHIPMENT на SHIPPED и создано перемещеие на транзитный склад", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::AWAITING_SHIPMENT]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::SHIPPED]);

            $this->mockProductMovementCreator(createMovementExpectedTimes: 2, registerExpectedTimes: 0);

            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для изменения статуса с SHIPPED на AWAITING_SHIPMENT
     * @throws Throwable
     */
    public function testMethodProcessShippedToAwaitingShipment()
    {
        $this->specify("должен успешно изменить статус задачи с SHIPPED на AWAITING_SHIPMENT и должно произойти 1 перемещение с оприходованием на конечный склад и 1 перемещение с конечного в начальный склад", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::SHIPPED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::AWAITING_SHIPMENT]);

            $this->mockProductMovementCreator(createMovementExpectedTimes: 2, registerExpectedTimes: 2);
            $this->mockConsignmentMovementRepository();

            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для изменения статуса с SHIPPED на AWAITING_SHIPMENT с ошибкой при создании обратного движения
     * @throws Throwable
     */
    public function testMethodProcessShippedToAwaitingShipmentNotRegisteredException()
    {
        $this->specify("Создать обратное движение товара, если он не был оприходован на конечном складе вызывает исключение", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::SHIPPED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::AWAITING_SHIPMENT]);

            $this->mockProductMovementCreator(createMovementExpectedTimes: 0, registerExpectedTimes: 2);
            $this->mockConsignmentMovementRepository(isRegistered: false);

            $this->expectException(BadMethodCallException::class);

            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->taskStatusChanger->process();
        });
    }

    /**
     * Тестирует метод process для изменения статуса с SHIPPED на ACCEPTED_WITH_DISAGREEMENT
     * @throws Throwable
     */
    public function testMethodProcessShippedToAcceptedWithDisagreement()
    {
        $this->specify("должен успешно изменить статус задачи с SHIPPED на ACCEPTED_WITH_DISAGREEMENT, должно произойти оприходование на конечном складе и редирект на атач файла", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::SHIPPED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::ACCEPTED_WITH_DISAGREEMENT]);

            $this->mockProductMovementCreator(createMovementExpectedTimes: 0, registerExpectedTimes: 2); // Две коробки
            $this->mockConsignmentMovementRepository();

            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->expectControllerRedirectToFileUpload();
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для изменения статуса с SHIPPED на NOT_ACCEPTED
     * @throws Throwable
     */
    public function testMethodProcessShippedToNotAccepted()
    {
        $this->specify("должен успешно изменить статус задачи с SHIPPED на NOT_ACCEPTED и должно произойти 1 перемещение с оприходованием на конечный склад и 1 перемещение с конечного в начальный склад и редирект на страницу атача файла", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::SHIPPED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::NOT_ACCEPTED]);

            $this->mockProductMovementCreator(createMovementExpectedTimes: 2, registerExpectedTimes: 2);
            $this->mockConsignmentMovementRepository();

            $this->expectControllerRedirectToFileUpload();

            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для изменения статуса с SHIPPED на ACCEPTED
     * @throws Throwable
     */
    public function testMethodProcessShippedToAccepted()
    {
        $this->specify("должен успешно изменить статус задачи с SHIPPED на ACCEPTED и вызвать sendProductsToTargetWarehouseAndRegister", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::SHIPPED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::ACCEPTED]);

            $this->mockProductMovementCreator(createMovementExpectedTimes: 0, registerExpectedTimes: 2);
            $this->mockConsignmentMovementRepository();

            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);
            $this->assertTrue($this->taskStatusChanger->process());
        });
    }

    /**
     * Тестирует метод process для неверного изменения статуса
     * @throws Throwable
     */
    public function testMethodProcessInvalidStatusChange()
    {
        $this->specify("должен выбросить исключение при неверном изменении статуса", function () {
            $this->statusFrom = $this->make(ProductKitTaskStatus::class, ['getValue' => ProductKitTaskStatus::UNPROCESSED]);
            $this->statusTo = $this->make(ProductKitTaskStatus::class, ['getValue' => 'COMPLETED']);
            $this->taskStatusChanger = new TaskStatusChanger($this->statusFrom, $this->statusTo, $this->task);

            $this->expectException(RuntimeException::class);
            $this->taskStatusChanger->process();
        });
    }
}
