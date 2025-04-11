<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring\conditions;

use Base\Unit;
use Codeception\Specify;
use app\classes\DeliveryException\monitoring\conditions\DeliveryExceptionOwnerCondition;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\models\Delivery;
use app\models\DeliveryException;

/**
 * Тесты для класса DeliveryExceptionOwnerCondition
 */
class DeliveryExceptionOwnerConditionTest extends Unit
{
    use Specify;

    protected Delivery $delivery;

    protected function _before()
    {
        $this->delivery = new Delivery();
    }

    /**
     * Тест метода check, когда владелец исключения совпадает с ожидаемым
     */
    public function testMethodCheckWhenOwnerMatches(): void
    {
        $this->specify('Когда владелец исключения совпадает с ожидаемым, check должен вернуть true', function () {
            $deliveryException = $this->make(DeliveryException::class, [
                'owner' => ExceptionOwnerEnum::Logist,
            ]);
            $this->delivery->populateRelation('deliveryException', $deliveryException);

            $condition = new DeliveryExceptionOwnerCondition($this->delivery, ExceptionOwnerEnum::Logist);

            expect('check должен вернуть true', $condition->check())->true();
        });

        $this->specify('Когда владелец исключения совпадает с ожидаемым, но задан в виде строки, а не Енум, check должен вернуть true', function () {
            $deliveryException = $this->make(DeliveryException::class, [
                'owner' => 'Logist',
            ]);
            $this->delivery->populateRelation('deliveryException', $deliveryException);

            $condition = new DeliveryExceptionOwnerCondition($this->delivery, ExceptionOwnerEnum::Logist);

            expect('check должен вернуть true', $condition->check())->true();
        });
    }

    /**
     * Тест метода check, когда владелец исключения не совпадает с ожидаемым
     */
    public function testMethodCheckWhenOwnerDoesNotMatch(): void
    {
        $this->specify('Когда владелец исключения не совпадает с ожидаемым, check должен вернуть false', function () {
            $deliveryException = $this->make(DeliveryException::class, [
                'owner' => ExceptionOwnerEnum::Operator,
            ]);
            $this->delivery->populateRelation('deliveryException', $deliveryException);

            $condition = new DeliveryExceptionOwnerCondition($this->delivery, ExceptionOwnerEnum::Logist);

            expect('check должен вернуть false', $condition->check())->false();
        });
    }

    /**
     * Тест метода check, когда исключения для доставки нет
     */
    public function testMethodCheckWhenNoDeliveryException(): void
    {
        $this->specify('Когда исключения для доставки нет, check должен вернуть false', function () {
            $this->delivery->populateRelation('deliveryException', null);

            $condition = new DeliveryExceptionOwnerCondition($this->delivery, ExceptionOwnerEnum::Logist);

            expect('check должен вернуть false', $condition->check())->false();
        });
    }
}
