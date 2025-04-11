<?php 

declare(strict_types=1); 

namespace tests\classes\DeliveryException;

use app\classes\DeliveryCourier\GetStatus\BrtExceptionStatusDictionary;
use Yii;
use Base\Unit;
use Codeception\Specify;
use DateTimeImmutable;
use app\classes\DeliveryException\DeliveryExceptionFunc;
use app\models\StatDelivery;
use app\models\Operator;
use app\models\OrderPhase;
use app\models\Order;
use app\models\OrderAddress;
use app\models\Delivery;
use app\models\OrderClient;
use app\models\OrderDelivery;
use app\models\StatOrder;
use app\models\Client;
use app\models\DeliveryException;
use app\models\DeliveryCourier;
use app\models\Courier;
use app\models\Phase;
use app\models\Comment;
use app\models\NeedCallbackOrder;
use app\classes\DeliveryException\commands\HandleExceptionForceOwnerCommand;
use app\classes\DeliveryException\DeliveryExceptionHandlerFactory;
use app\classes\DeliveryException\DeliveryExceptionService;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\enums\courier\CourierServiceMessageTypeEnum;
use app\models\CourierServiceMessage;
use app\repository\CSMessageRepository;

/**
 * Тесты для класса DeliveryExceptionFunc
 */
class DeliveryExceptionFuncTest extends Unit
{
    use Specify;

    protected $orderId;
    protected $orderId2;
    protected $courierId;
    protected $statDeliveryMock;
    protected $orderPhaseMock;
    protected $operatorMock;

    protected CSMessageRepository $csMessageRepository;

    protected $orderPhaseId = null;
    protected $phaseId = null;
    protected ?ExceptionOwnerEnum $exceptionOwner = null;

    protected function _before()
    {
        parent::_before();

        $this->mockUser();

        $this->createDatabaseRecords();

        $this->statDeliveryMock = $this->makeEmpty(StatDelivery::class);
        $this->orderPhaseMock = $this->makeEmpty(OrderPhase::class);
        $this->operatorMock = $this->makeEmpty(Operator::class);

        $this->csMessageRepository = new CSMessageRepository;
    }

    /**
     * Тестирует метод createAuto
     *
     * Проверка, что исключения, не создаются, если нет записей StatDelivery 
     * с непустым [[exception]]
     */
    public function testMethodCreateAutoNoExceptionWhenEmptyStatDeliveryException()
    {
        $this->specify('Не заполняем поле с ексепшеном в Delivery и StatDelivery - проблемная доставка не создается', function () {
            $this->runCreateAuto();
            
            $this->tester->dontSeeRecord(DeliveryException::class);
        });
    }

    /**
     * Тестирует метод createAuto
     * 
     * Проверяет, что заказ пропадает из очереди на прозвон (NeedCallbackOrder)
     */
    public function testMethodPhaseChangeDoesNotCreateNeedCallbackOrder()
    {
        $message = $this->problemMessageFromDB();
        
        $this->resetAndPrepareDb($message);

        $this->haveExceptionOwner(ExceptionOwnerEnum::Logist);

        $this->specify('NeedCallbackOrder удаляется если фаза изменена', function() {
            $this->tester->seeRecord(NeedCallbackOrder::class, ['order_id' => $this->orderId]);
            $this->runCreateAuto();
            $this->tester->dontSeeRecord(NeedCallbackOrder::class, ['order_id' => $this->orderId]);
        });
    }
    
    /**
     * Тестирует метод createAuto
     * 
     * Если сообщений курьерки нет в бд, то проблемная доставка не создается
     */
    public function testMethodCreateAutoDoNotCreateIfWrongCourier()
    {
        $this->specify('Исключения создаются если DeliveryCourier->courier_id из списка', function () {
            $supportedCSIds = $this->csMessageRepository->getSupportedCourierIds();

            foreach ($supportedCSIds as $courierId) {
                $message = $this->problemMessageFromDB(0, $courierId);
                $this->resetAndPrepareDb($message);

                DeliveryCourier::updateAll(['courier_id' => $courierId]);
                $this->runCreateAuto();
                
                $this->tester->seeRecord(DeliveryException::class);
            }
        });

        $message = $this->problemMessageFromDB();
        $this->resetAndPrepareDb($message);

        $this->specify('Исключения не создаются если DeliveryCourier->courier_id не из списка', function() {
            DeliveryCourier::updateAll(['courier_id' => Courier::ID_MATKAHUOLTO]);
            $this->runCreateAuto();

            $this->tester->dontSeeRecord(DeliveryException::class);
        });
    }

    public function testDeliveryTsIsSet()
    {
        $this->specify('Для эксепшенов без специального значения дата доставки не проставляется', function () {
            $this->resetAndPrepareDb($this->problemMessageFromDB());

            $this->runCreateAuto();
            $deliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $this->orderId]);

            $this->assertNull($deliveryException->delivered_ts);
        });

        /**
         * @see https://leadgidwebvork.monday.com/boards/3354701439/pulses/6828250014
         */
        $this->specify('Для эксепшенов из списка, проставляется дата доставки', function () {
            $messagesWithDeliveryDate = $this->csMessageRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::SetDeliveryDate);

            foreach ($messagesWithDeliveryDate as $exceptionMessage) {
                $message = $exceptionMessage . ' 10.06.2024';
                $this->resetAndPrepareDb($message);

                $this->runCreateAuto();
                $deliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $this->orderId]);

                $this->assertEquals('2024-06-10 00:00:00', $deliveryException->delivered_ts);
            }

            $this->specify('Если повторный такой же ексепшн, но с другой датой, то добавляется новый комментарий', function() use ($exceptionMessage) {
                $message = $exceptionMessage . ' 15.06.2024';
                $this->haveExceptionMessageInDelivery($message);

                $this->runCreateAuto();

                $deliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $this->orderId]);

                $this->seeCommentForDeliveryException($message);
                $this->assertEquals('2024-06-15 00:00:00', $deliveryException->delivered_ts);
                $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST);
            });

            $this->specify('Если владелец проблемного уже ОПЕРАТОР', function() use ($exceptionMessage) {
                $lastDeliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $this->orderId]);
                $lastMessage = $lastDeliveryException->managerComment->content;
                $lastDeliveredTs = $lastDeliveryException->delivered_ts;

                $message = $exceptionMessage . ' 11.11.2024';
                $this->haveExceptionMessageInDelivery($message);
                $this->haveExceptionOwner(ExceptionOwnerEnum::Operator);

                $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_SEND_OPERATOR);

                $this->runCreateAuto();

                $this->specify('дата не меняется – комментарий старый и дата доставки тоже не изменилась', function() use ($lastMessage, $lastDeliveredTs) {
                    $deliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $this->orderId]);
                    
                    // $this->seeCommentForDeliveryException($lastMessage);
                    $this->assertEquals($lastDeliveredTs, $deliveryException->delivered_ts);
                });
            });
        });
    }

    /**
     * Тестирует сброс признака is_transfer
     */
    public function testIsTransferReseting()
    {
        $this->resetAndPrepareDb($this->problemMessageFromDB(), orderId: $this->orderId);
        $this->resetAndPrepareDb($this->noProblemMessageFromDB(), orderId: $this->orderId2);

        $this->runCreateAuto();

        DeliveryException::updateAll(['is_transfer' => 1]);

        $this->haveExceptionMessageInDelivery($this->problemMessageFromDB(2), orderId: $this->orderId);
        $this->haveExceptionMessageInDelivery($this->noProblemMessageFromDB(2), orderId: $this->orderId2);

        $this->runCreateAuto();

        $this->specify('Если сообщение от КС в списке проблемных, то признак is_transfer не сбрасывается', function() {
            $this->tester->seeRecord(DeliveryException::class, [
                'order_id' => $this->orderId,
                'is_transfer' => 1,
            ]);
        });

        $this->specify('Если сообщение от КС в списке НЕпроблемных, то признак is_transfer сбрасывается', function() {
            $this->tester->seeRecord(DeliveryException::class, [
                'order_id' => $this->orderId2,
                'is_transfer' => 0,
            ]);
        });
    }

    /**
     * Если это вручную созданное исключение с фазой Отправлено логисту, 
     * проставляется фаза логиста
     *  @todo нужен ли? какой код это тестирует?
     */
    public function testOrderPhaseManualLogist()
    {
        $this->specify('Если это вручную созданное исключение с фазой Отправлено логисту, проставляется фаза логиста', function () {
            $this->resetAndPrepareDb($this->problemMessageFromDB());

            $command = Yii::createObject(HandleExceptionForceOwnerCommand::class, [ExceptionOwnerEnum::Logist]);
            $fabric = Yii::createObject(DeliveryExceptionHandlerFactory::class, [$command]);
            $deService = Yii::createObject(DeliveryExceptionService::class, [$fabric]);

            $delivery = $this->tester->grabRecord(Delivery::class, [
                'order_id' => $this->orderId,
            ]);

            $deService->processException($delivery, $this->problemMessageFromDB());

            $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST);
        });
    }

    /* =========================== *
     * === Тесты по регламенту === *
     * =========================== */

    /**
     * Тестирует метод createAuto
     *
     * Проверка, что сообщения, которые должны игнорироваться игнорируются
     * @todo Проверка что методы не запускаются
     */
    public function testMethodCreateAutoIgnoresMessagesFromIgnoreList_0()
    {
        $this->specify('0) Сообщения полностью игнорируются, не участвуют в подсистеме проблемных заказов', function() {
            $this->specify('0.1 Сообщение в списке игнора IGNORE_LIST', function () {
                $ignoreMessagesList = $this->csMessageRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::Ignore);
                foreach ($ignoreMessagesList as $exceptionToIgnore) {
                    $this->resetAndPrepareDb($exceptionToIgnore);
                    
                    $this->runCreateAuto();
                    
                    $this->tester->dontSeeRecord(DeliveryException::class, ['order_id' => $this->orderId]);
                }
            });
            
            $this->specify('0.3 Если эксепшн уже был, и сообщение такое же как предыдущее', function() {
                $noProblemMessages = $this->csMessageRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::NoProblem);
                $problemMessages = $this->csMessageRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::Problem);

                foreach ([...Phase::DELIVERY_EXCEPTIONS_ROLES, Phase::DELIVERY_EXCEPTION_CALLBACK_RE_DELIVERY] as $rolePhaseId) {
                    foreach ([$this->noProblemMessageFromDB(), $this->problemMessageFromDB()] as $exceptionMessage) {
                        $exceptionMessage .= ' ' . uniqid();
                        // Игнорируем, тк логика другая. см предыдущий пункт 0.2
                        // if($rolePhaseId == Phase::DELIVERY_EXCEPTION_SEND_OPERATOR && in_array($exceptionMessage, DeliveryExceptionFunc::BRT_NO_PROBLEM_LIST)) {
                        //     continue;
                        // }

                        $exceptionOwner = DeliveryExceptionFunc::exceptionOwnerFromPhaseId($rolePhaseId);

                        $this->resetAndPrepareDb(message: $exceptionMessage, exceptionOwner: $exceptionOwner, phaseId: $rolePhaseId);
                        $this->haveDeliveryException($exceptionMessage);

                        $this->runCreateAuto();
        
                        $this->seeCommentForDeliveryException($exceptionMessage);
                        $this->seeOrderPhase($rolePhaseId);
                        $this->seeOrderPhasesCount(2);
                    }
                }
            });
        });
    }

    /**
     * Тест метода createAuto по регламенту для 1) Если по заказу еще не было эксепшенов
     * @todo Если исключение любое незначимое, то создается 2 фазы заказа: 
     * 1 - Автоматическая родительская системная фаза, 
     * 2 - актуальная фаза по умолчанию DELIVERY_EXCEPTION_SEND_OPERATOR
     * 
     * @see https://leadgidwebvork.monday.com/boards/3354701439/pulses/6529612274/posts/3292219685
     */
    public function testNoExceptionsBefore_1()
    {
        $this->specify('1) Если по заказу еще не было эксепшенов, ', function () {

            $this->specify('Если фаза НЕ в Phase::DELIVERY_EXCEPTIONS_ROLES', function() {
                $this->specify('То фаза закрывается, создается операторская', function() {
                    $exceptionMessage = $this->problemMessageFromDB();
                    $this->resetAndPrepareDb(message: $exceptionMessage, phaseId: Phase::DELIVERY_EXCEPTION_CALLBACK_RE_DELIVERY);
                    
                    $this->runCreateAuto();

                    $this->seeCommentForDeliveryException($exceptionMessage);
                    $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_SEND_OPERATOR);
                    $this->seeOrderPhasesCount(3);
                });
            });
        });
    }

    /**
     * Тест метода createAuto по регламенту для 2) Если по заказу уже были эксепшены, 
     * но с другим текстом
     * @see https://leadgidwebvork.monday.com/boards/3354701439/pulses/6529612274/posts/3292219685
     */
    public function testExceptionExists_2()
    {
        $this->specify('2) Если по заказу уже был эксепшн', function() {
            
            $this->specify("2.1 и текущая фаза ЛОГИСТ и ексепшн из списка BRT_NO_PROBLEM_LIST", function() {
                $noProblemMessagesList = $this->csMessageRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::NoProblem);
                foreach ($noProblemMessagesList as $newExceptionMessage) {
                    $newExceptionMessage .= ' ' . uniqid();

                    $initialMessage = $this->problemMessageFromDB();
                    $this->resetAndPrepareDb(message: $initialMessage);
                    $this->haveDeliveryException($initialMessage);
                    
                    $this->haveExceptionOwner(ExceptionOwnerEnum::Logist);
                    $this->haveExceptionMessageInDelivery($newExceptionMessage);

                    $this->runCreateAuto();
                    
                    $this->specify("({$newExceptionMessage}), то комментарий добавляется {$newExceptionMessage}, фаза остается ЛОГИСТ", function() use ($newExceptionMessage) {
                        $this->seeCommentForDeliveryException($newExceptionMessage);
                        $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST);
                        $this->seeOrderPhasesCount(2);
                    });
                }
            });
            
            $this->specify('2.3 Если текущая фаза любая из DELIVERY_EXCEPTIONS_ROLES', function() {
                foreach (Phase::DELIVERY_EXCEPTIONS_ROLES as $rolePhase) {

                    $this->specify('и сообщение НЕ из списка BRT_NO_PROBLEM_LIST (тоесть проблемное)', function() {

                        $initialMessage = $this->problemMessageFromDB();
                        $this->resetAndPrepareDb(message: $initialMessage, exceptionOwner: ExceptionOwnerEnum::Logist);
                        $this->haveDeliveryException($initialMessage);

                        $newExceptionMessage = $this->problemMessageFromDB(2);
                        $this->haveExceptionMessageInDelivery($newExceptionMessage);

                        $this->runCreateAuto();

                        $this->specify('то фаза в любом случае устанавливается = ОПЕРАТОР', function() use ($newExceptionMessage) {
                            $this->seeCommentForDeliveryException($newExceptionMessage);
                            $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_SEND_OPERATOR);
                            $this->seeOrderPhasesCount(3);
                        });
                    });
                }
            });

            $this->specify('2.4 Если фаза НЕ в Phase::DELIVERY_EXCEPTIONS_ROLES', function() {
                $initialMessage = $this->problemMessageFromDB();
                $this->resetAndPrepareDb(message: $initialMessage, phaseId: Phase::DELIVERY_EXCEPTION_CALLBACK_RE_DELIVERY);
                $this->haveDeliveryException($initialMessage);
                
                $newExceptionMessage = $this->problemMessageFromDB(2);
                $this->haveExceptionMessageInDelivery($newExceptionMessage, ['order_id' => $this->orderId]);

                $this->runCreateAuto();
                
                $this->specify('то комментарий добавляется, а фаза остается прежняя', function() use ($newExceptionMessage) {
                    $this->seeCommentForDeliveryException($newExceptionMessage);
                    $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_CALLBACK_RE_DELIVERY);
                    $this->seeOrderPhasesCount(2);
                });
            });
        });
    }

    /* ================================= *
     * === Конец Тесты по регламенту === *
     * ================================= */

    /**
     * @see https://leadgidwebvork.monday.com/boards/3354701439/pulses/7341816798
     */
    public function testSeveralInConsegnaMessages()
    {
        $this->specify('Тест для БРТ, когда приходит несколько "in consegna" сообщений вперемежку с другими - каждый раз должна быть создана запись in consegna', function() {
            $CSmessagesSequence = [
                'IN CONSEGNA',   // НА ДОСТАВКЕ                , no problem => owner=Logist
                'DA CONSEGNARE', // ДОЛЖНА БЫТЬ ДОСТАВЛЕНА     , no problem => owner=Logist
                'IN CONSEGNA',   // НА ДОСТАВКЕ                , no problem => owner=Logist
                'DA CONSEGNARE', // ДОЛЖНА БЫТЬ ДОСТАВЛЕНА     , no problem => owner=Logist
                'IN CONSEGNA',   // НА ДОСТАВКЕ (owner=логист) , no problem => owner=Logist
                'RIFIUTO PER COLLO DANNEGGIATO', // Отказ из-за поврежденного посылки, problem! => (owner=оператор)
                'IN CONSEGNA',   // НА ДОСТАВКЕ , no problem, (owner untouched =оператор)
                'RIFIUTO PER COLLO DANNEGGIATO', // Отказ из-за поврежденного посылки, problem! => (owner=оператор)
            ];      

            $CSmessagesToGetToDeliveryTable = array_intersect($CSmessagesSequence, BrtExceptionStatusDictionary::STATUSES_IT);

            foreach ($CSmessagesToGetToDeliveryTable as $CSmessage) {
                $this->haveExceptionMessageInDelivery($CSmessage);
                $this->runCreateAuto();
            }

            $commentsInDb = $this->loadCommentTexts();

            $this->assertEquals([
                'IN CONSEGNA',   // НА ДОСТАВКЕ                , no problem => owner=Logist
                'DA CONSEGNARE', // ДОЛЖНА БЫТЬ ДОСТАВЛЕНА     , no problem => owner=Logist
                'IN CONSEGNA',   // НА ДОСТАВКЕ                , no problem => owner=Logist
                'DA CONSEGNARE', // ДОЛЖНА БЫТЬ ДОСТАВЛЕНА     , no problem => owner=Logist
                'IN CONSEGNA',   // НА ДОСТАВКЕ (owner=логист) , no problem => owner=Logist
                'RIFIUTO PER COLLO DANNEGGIATO', // Отказ из-за поврежденного посылки, problem! => (owner=оператор)
                'IN CONSEGNA',   // НА ДОСТАВКЕ , no problem, (owner untouched =оператор)
                'RIFIUTO PER COLLO DANNEGGIATO', // Отказ из-за поврежденного посылки, problem! => (owner=оператор)
            ], $commentsInDb);

            $this->seeOrderPhase(Phase::DELIVERY_EXCEPTION_SEND_OPERATOR);
            $this->seeExceptionOwner(ExceptionOwnerEnum::Operator);
        });
    }

    protected function loadCommentTexts()
    {
        return Comment::find()
                ->where([
                    'key' => 'delivery-exception',
                    'field_id' => $this->orderId,
                ])
                ->select(['content'])
                ->orderBy(['id' => SORT_ASC])
                ->column()
            ;
    }

    protected function haveExceptionMessageInDelivery(string $message, $orderId = null): void
    {
        $orderId ??= $this->orderId;

        Delivery::updateAll(['exception' => $message], ['order_id' => $orderId]);
        StatDelivery::updateAll(['exception' => $message], ['order_id' => $orderId]);
    }

    protected function haveDeliveryException($exceptionMessage, $orderId = null, ?ExceptionOwnerEnum $exceptionOwner = null, $orderPhaseId = null, $phaseId = null) 
    {
        $orderId ??= $this->orderId;
        $orderPhaseId ??= $this->orderPhaseId;
        $phaseId ??= $this->phaseId;
        $exceptionOwner ??= $this->exceptionOwner;

        $commentId = $this->tester->haveRecord(Comment::class, [
            'key' => 'delivery-exception',
            'field_id' => $orderId,
            'content' => $exceptionMessage,
        ]);

        $this->tester->haveRecord(DeliveryException::class, [
            'order_id' => $orderId, 
            'exception' => $exceptionMessage, 
            'created_user_id' => 3,
            'manager_comment_id' => $commentId,
            'order_phase_id' => $orderPhaseId,
            'phase_id' => $phaseId,
            'owner' => $exceptionOwner->value,
        ]);
    }

    protected function runCreateAuto()
    {
        DeliveryExceptionFunc::createAuto(
            startDate: new DateTimeImmutable('2024-06-01 00:00:00'),
            endDate: new DateTimeImmutable('2024-06-30 23:59:59'),
        );
    }

    protected function deleteDeliveryExceptions($orderId = null)
    {
        $orderId ??= $this->orderId;

        DeliveryException::deleteAll(['order_id' => $orderId]);
        Comment::deleteAll(['field_id' => $orderId, 'key' => 'delivery-exception']);
        $this->resetOrderPhases($orderId);
    }

    protected function seeCommentForDeliveryException(string $comment)
    {
        $this->specify("Последний комментарий со значением = '{$comment}'", function() use ($comment) {
            $deliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $this->orderId]);
            
            $this->assertNotNull($deliveryException, 'Ошибка: Проблемного заказа не существует');
            $this->assertNotNull($deliveryException->managerComment, 'Ошибка: Нет комментариев к проблемному заказу');
            $this->assertEquals($comment, $deliveryException->managerComment->content);
        });
    }

    protected function seeOrderPhase($phaseId, $orderId = null, $ignoreDeliveryExceptionPhaseAttrCheck = false)
    {
        $orderId ??= $this->orderId;

        $currentOrderPhase = OrderPhase::find()->where(['order_id' => $orderId])->orderBy(['id' => SORT_DESC])->one();
        $lastPhaseName = $this->getPhaseName($currentOrderPhase->phase_id);
        $expectedPhaseName = $this->getPhaseName($phaseId);

        $this->assertEquals($phaseId, $currentOrderPhase->phase_id, "Последняя фаза для заказа ({$lastPhaseName}) не ровна ожидаемой ({$expectedPhaseName})");

        // Проверка полей фаз в модели ексепшена. Не нужно при новом учете ответственных
        if(!$ignoreDeliveryExceptionPhaseAttrCheck) {
            $deliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $this->orderId]);
            $this->assertEquals($phaseId, $deliveryException->phase_id, 'Не соответствует нужной фазе поле delivery_exception.phase_id');
            $this->assertEquals($currentOrderPhase->id, $deliveryException->order_phase_id, 'Не соответствует нужной фазе поле delivery_exception.order_phase_id');
        }
    }

    protected function seeOrderPhasesCount($expectedCount, $orderId = null)
    {
        $orderId ??= $this->orderId;
        $realOrderPhaseCount = OrderPhase::find()->where(['order_id' => $orderId])->count();
        $this->assertEquals($expectedCount, $realOrderPhaseCount, "Количество записей OrderPhase в бд ({$realOrderPhaseCount}) для заданного заказа не соответствует ожидаемому ({$expectedCount})");
    }

    protected function haveExceptionOwner(ExceptionOwnerEnum $exceptionOwner, $orderId = null, $phaseId = null)
    {
        $orderId ??= $this->orderId;

        $phaseId ??= DeliveryExceptionFunc::phaseFromExceptionOwner($exceptionOwner);

        $currentOrderPhase = OrderPhase::find()->where(['order_id' => $orderId])->orderBy(['id' => SORT_DESC])->one();
        $this->exceptionOwner = $exceptionOwner;

        $deliveryException = $this->tester->grabRecord(DeliveryException::class, ['order_id' => $orderId]);

        if($deliveryException) {
            $deliveryException->owner = $exceptionOwner;
            $deliveryException->save(false, ['owner']);
        }

        if($currentOrderPhase) {
            $currentOrderPhase->phase_id = $phaseId;
            $saved1 = $currentOrderPhase->save(false, ['phase_id']);

            if($deliveryException) {
                $deliveryException->phase_id = $phaseId;
                $saved2 = $deliveryException->save(false, ['phase_id']);
            }
            // $deliveryException->order_phase_id;
            return $currentOrderPhase->id;
        }


        // Если фаз еще не было то создается родительская системная и к ней уже
        // целевая фаза. Так сейчас. Возможно надо избавиться
        $parentOrderPhaseId = $this->tester->haveRecord(OrderPhase::class, [ 
            'order_id' => $orderId,
            'phase_id' => Phase::SYSTEM_PHASE_ID,
        ]);

        $orderPhaseId = $this->tester->haveRecord(OrderPhase::class, [
            'order_id' => $orderId,
            'phase_id' => $phaseId,
            'parent_id' => $parentOrderPhaseId,
        ]);

        if($deliveryException) {
            $deliveryException->order_phase_id = $orderPhaseId;
            $deliveryException->phase_id = $phaseId;
            $saved2 = $deliveryException->save(false, ['phase_id']);
        }

        return $orderPhaseId;
    }

    protected function seeExceptionOwner(ExceptionOwnerEnum $exceptionOwner, $orderId = null)
    {
        $orderId ??= $this->orderId;

        $this->tester->seeRecord(DeliveryException::class, [
            'order_id' => $orderId,
            'owner' => $exceptionOwner,
        ]);
    }

    protected function getPhaseName($phaseId)
    {
        $phases = [
            Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST => 'ЛОГИСТ',
            Phase::DELIVERY_EXCEPTION_SEND_OPERATOR => 'ОПЕРАТОР',
            Phase::DELIVERY_EXCEPTION_CALLBACK_RE_DELIVERY => 'Повторная доставка'
        ];

        return $phases[$phaseId];
    }

    protected function resetOrderPhases($orderId = null)
    {
        $orderId ??= $this->orderId;

        OrderPhase::deleteAll(['order_id' => $orderId]);

        $currentOrderPhase = OrderPhase::find()->select(['id'])->where(['order_id' => $orderId])->orderBy(['id' => SORT_DESC])->scalar();
        if($currentOrderPhase) {
            DeliveryException::updateAll(['order_phase_id' => $currentOrderPhase], ['order_id' => $orderId]);
        }
    }

    protected function resetAndPrepareDb($message, ?ExceptionOwnerEnum $exceptionOwner = null, $orderId = null, $phaseId = null)
    {
        $orderId ??= $this->orderId;
        $exceptionOwner ??= ExceptionOwnerEnum::Logist;

        $phaseId ??= DeliveryExceptionFunc::phaseFromExceptionOwner($exceptionOwner);

        $this->deleteDeliveryExceptions($orderId);
        $this->haveExceptionMessageInDelivery($message, $orderId);
        
        $orderPhaseId = $this->haveExceptionOwner($exceptionOwner, $orderId, $phaseId);
        $this->orderPhaseId = $orderPhaseId;
        $this->phaseId = $phaseId;
    }

    protected function problemMessageFromDB(int $i = 0, int $courierId = Courier::ID_BRT)
    {
        return CourierServiceMessage::find()->where([
            'type' => CourierServiceMessageTypeEnum::Problem->value,
            'courier_id' => $courierId,
        ])->offset($i)->one()->message;
    }

    protected function noProblemMessageFromDB(int $i = 0, int $courierId = Courier::ID_BRT)
    {
        return CourierServiceMessage::find()->where([
            'type' => CourierServiceMessageTypeEnum::NoProblem->value,
            'courier_id' => $courierId,
        ])->offset($i)->one()->message;
    }

    protected function createDatabaseRecords()
    {
        $this->courierId = Courier::ID_BRT;
        // $this->tester->haveRecord(Courier::class, [
        //     'name' => 'Courier',
        //     'code' => 'CR',
        // ]);

        // $this->tester->haveRecord(CourierServiceMessage::class, [
        //     'courier_id' => Courier::ID_BRT,
        //     'message' => self::PROBLEM_MESSAGE,
        //     'type' => CourierServiceMessageTypeEnum::Problem->value,
        // ]);

        // $this->tester->haveRecord(CourierServiceMessage::class, [
        //     'courier_id' => Courier::ID_BRT,
        //     'message' => self::NO_PROBLEM_MESSAGE,
        //     'type' => CourierServiceMessageTypeEnum::NoProblem->value,
        // ]);

        // Creating Client records
        $clientId = $this->tester->haveRecord(Client::class, [
            // 'id' => 1,
            'name' => 'client-name',
            'token' => 'client-token',
        ]);

        // Creating Order records
        $this->orderId = $this->tester->haveRecord(Order::class, [
            // 'id' => 1,
            'external_id' => $clientId,
            'client_id' => $clientId,
        ]);
        $this->orderId2 = $this->tester->haveRecord(Order::class, [
            'external_id' => $this->orderId - 1,
            'client_id' => $clientId,
        ]);

        // Creating OrderAddress records
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

        // Creating Delivery records
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

        // Creating DeliveryCourier records
        $this->tester->haveRecord(DeliveryCourier::class, [
            'order_id' => $this->orderId,
            'courier_id' => Courier::ID_BRT, // Любая из списка DeliveryExceptionFunc::DELIVERY_COURIER_LIST
        ]);
        $this->tester->haveRecord(DeliveryCourier::class, [
            'order_id' => $this->orderId2,
            'courier_id' => Courier::ID_BRT, // Любая из списка DeliveryExceptionFunc::DELIVERY_COURIER_LIST
        ]);

        // Creating OrderClient records
        $this->tester->haveRecord(OrderClient::class, [
            'order_id' => $this->orderId,
        ]);
        $this->tester->haveRecord(OrderClient::class, [
            'order_id' => $this->orderId2,
        ]);

        // Creating OrderDelivery records
        $this->tester->haveRecord(OrderDelivery::class, [
            'order_id' => $this->orderId,
            'seller_id' => 1,
            'payment_method' => 1,
        ]);
        $this->tester->haveRecord(OrderDelivery::class, [
            'order_id' => $this->orderId2,
            'seller_id' => 1,
            'payment_method' => 2,
        ]);

        // Creating StatOrder records
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

        // Creating StatDelivery records
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

        $this->tester->haveRecord(NeedCallbackOrder::class, [
            'order_id' => $this->orderId,
            'created_user_id' => 3,
            'order_phase_id' => 0,
        ]);
    }
}
