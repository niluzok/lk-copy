<?php

declare(strict_types=1);

namespace tests\integration\classes\DeliveryException\monitoring\rules;

use app\classes\DeliveryException\monitoring\rules\NoMessagesFromCSRule;
use app\models\Delivery;
use app\models\Comment;
use app\repository\CommentRepository;
use app\repository\DeliveryExceptionRepository;
use Base\Unit;
use DateTime;
use Yii;
use Codeception\Specify;
use app\models\DeliveryException;

/**
 * Интеграционные тесты для правила NoMessagesFromCSRule
 */
class NoMessagesFromCSRuleTest extends Unit
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
     * Тест для успешного выполнения правила, когда прошло 3 дня и сообщений от КС нет
     */
    public function testRuleExecutionWhenNoMessagesFromCS(): void
    {
        $this->specify('Правило должно выполняться, если прошло 3 рабочих дня и нет сообщений от КС', function () {
            // Устанавливаем дату отправки на склад за 4 рабочих дня до текущей
            $this->delivery->in_stock_ts = (new DateTime('now'))->modify('-4 weekdays')->format('Y-m-d');
            $this->delivery->save();

            // Создаем правило
            $rule = new NoMessagesFromCSRule(
                $this->delivery,
                days: 3,
                deliveryExceptionRepository: $this->deliveryExceptionRepository,
                commentRepository: $this->commentRepository
            );


            // Проверяем выполнение правила
            $rule->evaluate();

            // Проверяем, что добавлен комментарий с сообщением "Заказ завис в транзите"
            $lastComment = $this->delivery->deliveryException->lastComment;
            $this->assertEquals(Yii::t('app', 'Заказ завис в транзите'), $lastComment->content);
            $this->assertEquals(NoMessagesFromCSRule::SYSTEM_USER_ID, $lastComment->created_user_id);
        });
    }

    /**
     * Тест для случая, когда сообщения от КС уже существуют
     */
    public function testRuleDoesNotExecuteWhenMessagesFromCSExist(): void
    {
        $this->specify('Правило не должно срабатывать, если сообщения от КС уже существуют', function () {
            // Устанавливаем дату отправки на склад за 4 рабочих дня до текущей
            $this->delivery->in_stock_ts = (new DateTime('now'))->modify('-4 weekdays')->format('Y-m-d');
            $this->delivery->save();

            // Создаем уже существующий проблемный
            $this->tester->haveRecord(DeliveryException::class, [
                'order_id' => $this->orderId,
                'created_user_id' => 3,
            ]);

            // Добавляем сообщение от КС
            $this->tester->haveRecord(Comment::class, [
                'field_id' => $this->delivery->order_id,
                'key' => Comment::KEY_DELIVERY_EXCEPTION,
                'content' => 'Test message from CS',
                'created_user_id' => 3,
            ]);

            // Создаем правило
            $rule = new NoMessagesFromCSRule(
                $this->delivery,
                days: 3,
                deliveryExceptionRepository: $this->deliveryExceptionRepository,
                commentRepository: $this->commentRepository
            );

            // Проверяем выполнение правила
            $rule->evaluate();

            // Проверяем, что новое сообщение не добавлено
            $comments = $this->delivery->deliveryException->getComments()->all();
            $this->assertCount(1, $comments); // Only the previously added message exists
            $this->assertEquals('Test message from CS', end($comments)->content);
        });
    }
}
