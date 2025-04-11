<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring\conditions;

use Base\Unit;
use Codeception\Specify;
use app\models\Delivery;
use app\models\DeliveryException;
use app\classes\DeliveryException\monitoring\conditions\DeliveryExceptionExistsCondition;

/**
 * Тесты для класса DeliveryExceptionExistsCondition
 */
class DeliveryExceptionExistsConditionTest extends Unit
{
    use Specify;

    /**
     * @var Delivery Экземпляр объекта Delivery
     */
    protected Delivery $delivery;

    protected function _before()
    {
        $this->delivery = new Delivery();
    }

    /**
     * Тест метода check когда исключение для доставки должно существовать
     */
    public function testMethodCheckWhenExceptionShouldExist(): void
    {
        $this->specify('Когда deliveryException не null, и shouldExist установлено в true, check должен вернуть true', function () {
            $this->delivery->populateRelation('deliveryException', new DeliveryException());
            $condition = new DeliveryExceptionExistsCondition($this->delivery, true);

            expect('check должен вернуть true', $condition->check())->true();
        });
    }

    /**
     * Тест метода check когда исключение для доставки не должно существовать
     */
    public function testMethodCheckWhenExceptionShouldNotExist(): void
    {
        $this->specify('Когда deliveryException равно null, и shouldExist установлено в false, check должен вернуть true', function () {
            $this->delivery->populateRelation('deliveryException', null);
            $condition = new DeliveryExceptionExistsCondition($this->delivery, false);

            expect('check должен вернуть true', $condition->check())->true();
        });
    }

    /**
     * Тест метода check когда исключение для доставки существует, но не должно
     */
    public function testMethodCheckWhenExceptionExistsButShouldNot(): void
    {
        $this->specify('Когда deliveryException не null, и shouldExist установлено в false, check должен вернуть false', function () {
            $this->delivery->populateRelation('deliveryException', new DeliveryException());
            $condition = new DeliveryExceptionExistsCondition($this->delivery, false);

            expect('check должен вернуть false', $condition->check())->false();
        });
    }

    /**
     * Тест метода check когда исключение для доставки не существует, но должно
     */
    public function testMethodCheckWhenNoExceptionButShouldExist(): void
    {
        $this->specify('Когда deliveryException равно null, и shouldExist установлено в true, check должен вернуть false', function () {
            $this->delivery->populateRelation('deliveryException', null);
            $condition = new DeliveryExceptionExistsCondition($this->delivery, true);

            expect('check должен вернуть false', $condition->check())->false();
        });
    }
}
