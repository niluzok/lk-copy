<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException;

use app\classes\DeliveryException\commands\HandleExceptionCommand;
use app\models\DeliveryException;
use app\models\Delivery;
use app\models\Comment;
use app\repository\DeliveryExceptionRepository;
use app\repository\CommentRepository;
use Codeception\Specify;
use Codeception\Stub\Expected;
use Base\Unit;
use Yii;
use app\classes\DeliveryException\SendOperatorOrLogistDeliveryException;
use app\models\OrderPhase;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\models\Phase;

/**
 * Тестирование класса HandleExceptionCommand
 */
class HandleExceptionCommandTest extends Unit
{
    use Specify;

    /**
     * @var HandleExceptionCommand
     */
    protected $command;

    /**
     * @var Delivery
     */
    protected $deliveryMock;

    /**
     * @var DeliveryExceptionRepository
     */
    protected $repositoryMock;

    /**
     * @var CommentRepository
     */
    protected $commentRepositoryMock;

    /**
     * Подготовка тестов
     */
    protected function _before()
    {
        parent::_before();
        
        Yii::$container->set(
            SendOperatorOrLogistDeliveryException::class,
            $this->make(SendOperatorOrLogistDeliveryException::class, [
                'createAndCloseParentOrderPhase' => function () { return true; },
                'getNewOrderPhase' => function() { return new class 
                    {
                        public $id = 1;
                        public $phase_id = 1;
                    }; 
                },
            ])
        );
    }

    /**
     * Тестирование метода run
     */
    public function testRun()
    {
        $this->specify("Метод run должен работать корректно в транзакции", function() {
            $this->prepareCommand();

            $this->mockYiiTransaction([
                'commit' => Expected::once(),
                'rollBack' => Expected::never(),
            ]);

            $this->command->setExceptionOwner(ExceptionOwnerEnum::Logist);
            $this->command->run();
        });

        $this->specify("Метод run должен откатить транзакцию при ошибке", function() {
            $this->prepareCommand(errorExpected: true);

            $this->mockYiiTransaction([
                'commit' => Expected::never(),
                'rollBack' => Expected::once(),
            ]);

            $this->repositoryMock = $this->make(DeliveryExceptionRepository::class, [
                'create' => function() {
                    throw new \RuntimeException('Error creating delivery exception');
                }
            ]);

            $this->command = new HandleExceptionCommand(
                1,
                $this->repositoryMock,
                $this->commentRepositoryMock
            );
            $this->command->setDelivery($this->deliveryMock);
            $this->command->setExceptionOwner(ExceptionOwnerEnum::Logist);

            $this->expectException(\RuntimeException::class);
            $this->command->run();
        });
    }

    protected function prepareCommand(bool $errorExpected = false)
    {
        $this->deliveryMock = $this->make(Delivery::class, [
            'order_id' => 1,
            'send_in_stock_ts' => '2023-07-30 12:00:00',
        ]);
        $this->deliveryMock->populateRelation('deliveryCourier', (object)['courier_id' => 1, 'tracking_number' => '123456']);
        $this->deliveryMock->populateRelation('deliveryException', null);
        $this->deliveryMock->populateRelation('orderPhase', $this->make(OrderPhase::class, [
            'phase_id' => 34,
        ]));

        $deliveryException = $this->make(DeliveryException::class, [
            'save' => $errorExpected ? Expected::never(true) : Expected::once(true),
            'owner' => ExceptionOwnerEnum::Logist->value,
        ]);

        $deliveryException->populateRelation('currentPhase', new class {
            public $phase_id = 1;
        });

        $this->repositoryMock = $this->make(DeliveryExceptionRepository::class, [
            'create' => $errorExpected ? Expected::never(true) : Expected::once($deliveryException),
        ]);

        $this->commentRepositoryMock = $this->make(CommentRepository::class, [
            'create' => $this->make(Comment::class, [
                'save' => true
            ])
        ]);

        $this->command = new HandleExceptionCommand(
            1,
            $this->repositoryMock,
            $this->commentRepositoryMock
        );

        $this->command->setDelivery($this->deliveryMock);
    }
}
