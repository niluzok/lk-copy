<?php 

declare(strict_types=1); 

namespace tests\functional\manager\deliveryexception;

use app\models\Comment;
use app\models\Delivery;
use app\models\StatDelivery;
use Page\Functional\deliveryexception\IndexPage;
use tests\fixtures\UserFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthAssignmentFixture;
use app\models\DeliveryException;
use FunctionalTester;

class IndexPageCest
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
        $this->createDeliveryDbRecords($I);

        $I->haveRecord(DeliveryException::class, [
            'created_user_id' => 3,
            'order_id' => $this->orderId,
        ]);
        $I->haveRecord(DeliveryException::class, [
            'created_user_id' => 3,
            'order_id' => $this->orderId2,
        ]);
    }

    public function deliveryStatusReturnShown(FunctionalTester $I)
    {
        /**
         * @see https://leadgidwebvork.monday.com/boards/3354701471/pulses/6529781440
         */
        $I->wantToTest('На странице Index заказы в статусе Возврат не отображаются, если установлена причина недоставки');

        Delivery::updateAll(['reason_nondelivery_id' => 1]);
        StatDelivery::updateAll(['reason_nondelivery_id' => 1]);
        
        $I->amOnPage(IndexPage::$URL);
        $I->fillField(IndexPage::$dateDeliveryRangeInput, '2024-06-01 - 2024-06-30');
        $I->click('button[type="submit"]', '.filter-form');

        $I->seeGridViewNotEmpty($I);

        $I->dontSeeInAnyGridViewCell($I, (string)$this->orderId2);
    }

    public function deliveryStatusReturnHidden(FunctionalTester $I)
    {
        /**
         * @see https://leadgidwebvork.monday.com/boards/3354701471/pulses/6529781440
         */
        $I->wantToTest('На странице Index заказы в статусе Возврат отображаются, если причина недоставки пустая');

        Delivery::updateAll(['reason_nondelivery_id' => null]);
        StatDelivery::updateAll(['reason_nondelivery_id' => null]);

        $I->amOnPage(IndexPage::$URL);
        $I->fillField(IndexPage::$dateDeliveryRangeInput, '2024-06-01 - 2024-06-30');

        $I->click('button[type="submit"]', '.filter-form');

        $I->seeInAnyGridViewCell($I, (string)$this->orderId2);
    }

    public function severalEqualCommentsAreShown(FunctionalTester $I)
    {
        /**
         * @see https://leadgidwebvork.monday.com/boards/3354701439/pulses/7341816798
         */
        $I->wantToTest('На странице Index отображаются все комментарии к проблемному заказу, даже одинаковые');
        
        $CSMessagesSequence = [
            'IN CONSEGNA',
            'DA CONSEGNARE',
            'IN CONSEGNA',
            'DA CONSEGNARE',
            'IN CONSEGNA',
            'RIFIUTO PER COLLO DANNEGGIATO',
            'IN CONSEGNA',
            'RIFIUTO PER COLLO DANNEGGIATO',
        ];

        foreach ($CSMessagesSequence as $CSMessage) {
            $I->haveRecord(Comment::class, [
                'key' => 'delivery-exception',
                'field_id' => $this->orderId,
                'created_user_id' => 3,
                'created_ts' => '2024-09-12 10:41:02',
                'content' => $CSMessage,
            ]);
        }

        $I->amOnPage(IndexPage::$URL);

        $exception = $I->grabTextFrom('table tbody tr:nth-child(1) .delivery-exception-exception');

        // grabTextFrom возвращает слипшуюся строку 3: IN CONSEGNA3: DA CONSEGNARE3: IN CONSEGNA3: DA CONSEGNARE3: IN CONSEGNA3: RIFIUTO PER COLLO DANNEGGIATO3: IN CONSEGNA3: RIFIUTO PER COLLO DANNEGGIATO
        $I->assertEquals('3: ' . implode('3: ', $CSMessagesSequence), str_replace(' 3', '3', $exception));
    }
}