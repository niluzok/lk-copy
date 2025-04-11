<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring\conditions;

use Base\Unit;
use Codeception\Specify;
use app\models\Delivery;
use app\models\DeliveryException;
use app\models\Comment;
use app\classes\DeliveryException\monitoring\conditions\CSLastMessageCondition;

/**
 * Тесты для класса CSLastMessageCondition
 */
class CSLastMessageConditionTest extends Unit
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
     * Тест метода check, когда последнее сообщение КС содержит ожидаемое сообщение
     */
    public function testMethodCheckWhenMessageIsFound(): void
    {
        $this->specify('Когда последнее сообщение КС содержит ожидаемое сообщение, check должен вернуть true', function () {
            $lastComment = $this->make(Comment::class, [
                'content' => 'This is a test message with the expected content.',
            ]);
            $deliveryException = $this->make(DeliveryException::class);
            $deliveryException->populateRelation('lastComment', $lastComment);
            $this->delivery->populateRelation('deliveryException', $deliveryException);

            $condition = new CSLastMessageCondition($this->delivery, 'expected content');

            expect('check должен вернуть true', $condition->check())->true();
        });
    }

    /**
     * Тест метода check, когда последнее сообщение КС не содержит ожидаемого сообщения
     */
    public function testMethodCheckWhenMessageIsNotFound(): void
    {
        $this->specify('Когда последнее сообщение КС не содержит ожидаемого сообщения, check должен вернуть false', function () {
            $lastComment = $this->make(Comment::class, [
                'content' => 'This is a test message without the expected content.',
            ]);
            $deliveryException = $this->make(DeliveryException::class);
            $deliveryException->populateRelation('lastComment', $lastComment);
            $this->delivery->populateRelation('deliveryException', $deliveryException);

            $condition = new CSLastMessageCondition($this->delivery, 'missing content');

            expect('check должен вернуть false', $condition->check())->false();
        });
    }

    /**
     * Тест метода check, когда последнего сообщения КС нет
     */
    public function testMethodCheckWhenNoLastComment(): void
    {
        $this->specify('Когда последнее сообщение КС отсутствует, check должен вернуть false', function () {
            $deliveryException = $this->make(DeliveryException::class);
            $deliveryException->populateRelation('lastComment', null);
            $this->delivery->populateRelation('deliveryException', $deliveryException);

            $condition = new CSLastMessageCondition($this->delivery, 'expected content');

            expect('check должен вернуть false', $condition->check())->false();
        });
    }

    /**
     * Тест метода check, когда исключение для доставки отсутствует
     */
    public function testMethodCheckWhenNoDeliveryException(): void
    {
        $this->specify('Когда исключение для доставки отсутствует, check должен вернуть false', function () {
            $this->delivery->populateRelation('deliveryException', null);

            $condition = new CSLastMessageCondition($this->delivery, 'expected content');

            expect('check должен вернуть false', $condition->check())->false();
        });
    }
}
