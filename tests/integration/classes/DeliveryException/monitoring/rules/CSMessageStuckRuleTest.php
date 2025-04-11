<?php

declare(strict_types=1);

namespace tests\integration\classes\DeliveryException\monitoring\rules;

use app\classes\DeliveryException\monitoring\rules\CSMessageStuckRule;
use app\models\Delivery;
use app\models\DeliveryException;
use app\models\Comment;
use app\repository\CommentRepository;
use app\repository\DeliveryExceptionRepository;
use Base\Unit;
use DateTime;
use Yii;
use Codeception\Specify;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use DateTimeInterface;

/**
 * Интеграционные тесты для класса CSMessageStuckRule
 */
class CSMessageStuckRuleTest extends Unit
{
    use Specify;
    use RulesDbPrepareFixture;

    /**
     * @var Delivery
     */
    protected Delivery $delivery;

    /**
     * @var DeliveryExceptionRepository
     */
    protected DeliveryExceptionRepository $deliveryExceptionRepository;

    /**
     * @var CommentRepository
     */
    protected CommentRepository $commentRepository;

    protected function _before()
    {
        parent::_before();

        $this->createDatabaseRecords();

        $this->delivery = $this->tester->grabRecord(Delivery::class, ['order_id' => $this->orderId]);
        $this->deliveryExceptionRepository = Yii::$container->get(DeliveryExceptionRepository::class);
        $this->commentRepository = Yii::$container->get(CommentRepository::class);
    }

    /**
     * Тест для успешного выполнения правила с различными типами $dateFrom
     */
    public function testRuleExecutionForAllDateFromTypes(): void
    {
        $this->specify('Правило должно успешно выполняться для $dateFrom типа DateTimeInterface', function () {
            // 1. Тип DateTimeInterface
            $this->resetDeliveryException(existingMessage: 'This is a test message containing the expected message.');
            $dateFromDateTime = new DateTime('now - 10 days');
            $this->runRule($dateFromDateTime);
            $this->checkDeliveryExceptionAndComments(
                owner: ExceptionOwnerEnum::Logist,
                lastComment: Yii::t('app', 'Cтатус не обновлен'),
                commentsCount: 2
            );
        });

        $this->specify('Правило должно успешно выполняться для $dateFrom типа string', function () {
            // 2. Тип string
            $this->resetDeliveryException(existingMessage: 'This is a test message containing the expected message.');
            $dateFromString = (new DateTime('now'))->modify('-10 days')->format('Y-m-d');
            $this->runRule($dateFromString);
            $this->checkDeliveryExceptionAndComments(
                owner: ExceptionOwnerEnum::Logist,
                lastComment: Yii::t('app', 'Cтатус не обновлен'),
                commentsCount: 2
            );
        });

        $this->specify('Правило должно успешно выполняться для $dateFrom типа callable, возвращающего строку', function () {
            // 3. Callable, возвращающий дату
            $this->resetDeliveryException(existingMessage: 'This is a test message containing the expected message.');
            $dateFromCallable = fn() => (new DateTime('now'))->modify('-10 days')->format('Y-m-d');
            $this->runRule($dateFromCallable);
            $this->checkDeliveryExceptionAndComments(
                owner: ExceptionOwnerEnum::Logist,
                lastComment: Yii::t('app', 'Cтатус не обновлен'),
                commentsCount: 2
            );
        });
    }

    /**
     * Тест для случая, когда условия не выполнены
     */
    public function testRuleDoesNotExecuteWhenConditionsNotMet(): void
    {
        $this->specify('Правило не должно выполняться, если условия не выполнены', function () {
            // Устанавливаем комментарий, который не содержит ожидаемого сообщения от КС
            $this->tester->haveRecord(Comment::class, [
                'key' => Comment::KEY_DELIVERY_EXCEPTION,
                'field_id' => $this->orderId,
                'created_user_id' => 3,
                'content' => 'This is a test message without the expected message.',
                'created_ts' => (new DateTime('now'))->format('Y-m-d H:i:s'),
            ]);

            // Используем callable для $dateFrom, который вернет дату 10 дней назад
            $dateFrom = fn() => (new DateTime('now'))->modify('-10 days')->format('Y-m-d');

            // Создаем экземпляр правила
            $rule = new CSMessageStuckRule(
                $this->delivery,
                'expected message',  // Ожидаемое сообщение
                $dateFrom,  // Callable для получения даты
                5,  // Количество рабочих дней
                $this->deliveryExceptionRepository,
                $this->commentRepository
            );

            // Проверяем, что правило не выполняется, так как условия не выполнены
            $rule->evaluate();

            // Проверяем, что DeliveryException не обновлен
            $updatedDeliveryException = $this->delivery->getDeliveryException()->one();
            $this->assertNull($updatedDeliveryException);
        });
    }

    /**
     * Тест для проверки, что повторное выполнение правила не добавляет одинаковый комментарий
     */
    public function testSubsequentRuleDoesNotAddSameComment(): void
    {
        $this->specify('Повторное выполнение правила не добавляет еще один такой же комментарий', function () {
            $this->resetDeliveryException(existingMessage: 'This is a test message containing the expected message.');
            $dateFromDateTime = new DateTime('now - 10 days');
            
            // Первый запуск правила
            $this->runRule($dateFromDateTime);
            $this->checkDeliveryExceptionAndComments(
                owner: ExceptionOwnerEnum::Logist,
                lastComment: Yii::t('app', 'Cтатус не обновлен'),
                commentsCount: 2
            );

            // Второй запуск правила
            $this->runRule($dateFromDateTime);
            
            // Проверяем, что количество комментариев не увеличилось
            $this->checkDeliveryExceptionAndComments(
                owner: ExceptionOwnerEnum::Logist,
                lastComment: Yii::t('app', 'Cтатус не обновлен'),
                commentsCount: 2 // Количество должно остаться тем же, что и после первого запуска
            );
        });
    }


    protected function resetDeliveryException(string $existingMessage)
    {
        Comment::deleteAll([]);
        DeliveryException::deleteAll([]);
        unset($this->delivery->deliveryException);

        // Устанавливаем комментарий, содержащий ожидаемое сообщение от КС
        $this->tester->haveRecord(Comment::class, [
            'key' => Comment::KEY_DELIVERY_EXCEPTION,
            'field_id' => $this->orderId,
            'created_user_id' => 3,
            'content' => $existingMessage,
            'created_ts' => (new DateTime('now'))->format('Y-m-d H:i:s'),
        ]);

        // Создаем уже существующий проблемный
        $this->tester->haveRecord(DeliveryException::class, [
            'order_id' => $this->orderId,
            'created_user_id' => 3,
        ]);
    }

    /**
     * Выполняет правило CSMessageStuckRule и проверяет результат
     *
     * @param DateTimeInterface|string|callable $dateFrom Дата, с которой считаются рабочие дни
     * @return void
     */
    protected function runRule(DateTimeInterface|string|callable $dateFrom): void
    {
        // Создаем экземпляр правила
        $rule = new CSMessageStuckRule(
            $this->delivery,
            'Expected Message',  // Ожидаемое сообщение
            $dateFrom,  // Дата или callable для получения даты
            5,  // Количество рабочих дней
            $this->deliveryExceptionRepository,
            $this->commentRepository
        );

        // Проверяем выполнение правила
        $rule->evaluate();
    }

    protected function checkDeliveryExceptionAndComments(ExceptionOwnerEnum $owner, string $lastComment, ?int $commentsCount = null)
    {
        // Проверяем, что DeliveryException обновлен с ожидаемыми данными
        $updatedDeliveryException = $this->delivery->getDeliveryException()->one();
        $this->assertNotNull($updatedDeliveryException);
        $this->assertSame($owner, ExceptionOwnerEnum::from($updatedDeliveryException->owner));

        // Проверяем, что добавлен комментарий с сообщением о проблемной доставке
        $comments = $updatedDeliveryException->getComments()->all();
        $this->assertNotEmpty($comments);
        $this->assertEquals($lastComment, $updatedDeliveryException->lastComment->content);
        $this->assertEquals(1, $updatedDeliveryException->lastComment->created_user_id);
        
        if($commentsCount) {
            $this->assertCount($commentsCount, $comments);
        }
    }
}
