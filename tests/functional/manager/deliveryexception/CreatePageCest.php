<?php 

declare(strict_types=1);

namespace tests\functional\manager\deliveryexception;

use Page\Functional\deliveryexception\CreatePage;
use app\models\DeliveryException;
use app\models\Phase;
use tests\fixtures\UserFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthAssignmentFixture;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\models\Courier;
use app\models\OrderPhase;
use \FunctionalTester;

class CreatePageCest
{
    use PrepareDeliveryTrait;

    public function _fixtures()
    {
        return [
            'users' => UserFixture::class,
            'auth_item' => AuthItemFixture::class,
            'auth_item_child' => AuthItemChildFixture::class,
            'auth_assignment' => AuthAssignmentFixture::class,
        ];
    }
    
    public function _before(FunctionalTester $I)
    {
        $I->amLoggedInAsManager($I);
    }

    protected function _couriersData()
    {
        return [
            ['courierId' => Courier::ID_SDA, 'courierName' => 'SDA', 'isRegistered' => true],
            ['courierId' => Courier::ID_BRT, 'courierName' => 'BRT', 'isRegistered' => true],
            ['courierId' => Courier::ID_CORREOS, 'courierName' => 'CORREOS', 'isRegistered' => false],
            ['courierId' => Courier::ID_MATKAHUOLTO, 'courierName' => 'MATKAHUOLTO', 'isRegistered' => false],
        ];
    }

    /**
     * @dataProvider _couriersData
     */
    public function createExceptionForLogist(FunctionalTester $I, \Codeception\Example $example)
    {
        $I->wantToTest("При ручном создании проблемного заказа для логистов, проставляется владелец проблемного заказа Логист, КС {$example['courierName']}");

        $this->createDeliveryDbRecords($I, $example['courierId']);

        $systemOrderPhaseId = $I->haveRecord(OrderPhase::class, [
            'order_id' => $this->orderId, 
            'parent_id' => null, 
            'phase_id' => Phase::SYSTEM_PHASE_ID, 
        ]);

        $I->haveRecord(OrderPhase::class, [
            'order_id' => $this->orderId, 
            'parent_id' => $systemOrderPhaseId, 
            'phase_id' => Phase::SEND_IN_STOCK, 
        ]);

        $I->amOnPage(CreatePage::$URL);
        
        $I->fillField(CreatePage::$managerCommentSelector, 'Test manager comment');
        $I->selectOption(CreatePage::$exceptionOwnerSelector, ExceptionOwnerEnum::Logist->value);
        $I->fillField(CreatePage::$ordersSelector, $this->orderId);
        $I->click(CreatePage::$submitButtonSelector);

        $I->seeResponseCodeIsSuccessful();

        if(!$example['isRegistered']) {
            $I->dontSeeRecord(DeliveryException::class, [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $I->seeRecord(DeliveryException::class, [
            'order_id' => $this->orderId,
            'owner' => ExceptionOwnerEnum::Logist,
        ]);

        $I->seeRecord(OrderPhase::class, [
            'order_id' => $this->orderId,
            'phase_id' => Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST,
        ]);

        $deliveryException = $I->grabRecord(DeliveryException::class, [
            'order_id' => $this->orderId,
        ]);

        $I->assertEquals(Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST, $deliveryException->currentPhase->phase_id);
    }

    /**
     * @dataProvider _couriersData
     */
    public function createExceptionForOperator(\FunctionalTester $I, \Codeception\Example $example)
    {
        $I->wantToTest("При ручном создании проблемного заказа для логистов, проставляется владелец проблемного заказа Оператор, КС {$example['courierName']}");

        $this->createDeliveryDbRecords($I, $example['courierId']);

        $systemOrderPhaseId = $I->haveRecord(OrderPhase::class, [
            'order_id' => $this->orderId, 
            'parent_id' => null, 
            'phase_id' => Phase::SYSTEM_PHASE_ID, 
        ]);

        $I->haveRecord(OrderPhase::class, [
            'order_id' => $this->orderId, 
            'parent_id' => $systemOrderPhaseId, 
            'phase_id' => Phase::SEND_IN_STOCK, 
        ]);

        $I->amOnPage(CreatePage::$URL);
        
        $I->fillField(CreatePage::$managerCommentSelector, 'Test manager comment');
        $I->selectOption(CreatePage::$exceptionOwnerSelector, ExceptionOwnerEnum::Operator->value);
        $I->fillField(CreatePage::$ordersSelector, $this->orderId);
        $I->click(CreatePage::$submitButtonSelector);

        $I->seeResponseCodeIsSuccessful();

        if(!$example['isRegistered']) {
            $I->dontSeeRecord(DeliveryException::class, [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $I->seeRecord(DeliveryException::class, [
            'order_id' => $this->orderId,
            'owner' => ExceptionOwnerEnum::Operator,
        ]);

        $I->seeRecord(OrderPhase::class, [
            'order_id' => $this->orderId,
            'phase_id' => Phase::DELIVERY_EXCEPTION_SEND_OPERATOR,
        ]);

        $deliveryException = $I->grabRecord(DeliveryException::class, [
            'order_id' => $this->orderId,
        ]);

        $I->assertEquals(Phase::DELIVERY_EXCEPTION_SEND_OPERATOR, $deliveryException->currentPhase->phase_id);
    }
}
