<?php

declare(strict_types=1);

namespace tests\integration\classes\DeliveryException\monitoring\rules;

use app\classes\DeliveryException\monitoring\rules\BRTDeliveryRescheduleCountRule;
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
use app\models\Courier;
use app\repository\CSMessageRepository;
use app\enums\courier\CourierServiceMessageTypeEnum;

/**
 * Интеграционные тесты для правила BRTDeliveryRescheduleCountRule
 */
class BRTDeliveryRescheduleCountRuleTest extends Unit
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

    /**
     * @var CSMessageRepository
     */
    protected CSMessageRepository $csMessageRepository;

    protected function _before()
    {
        parent::_before();

        $this->createDatabaseRecords();

        $this->delivery = $this->tester->grabRecord(Delivery::class, ['order_id' => $this->orderId]);
        $this->deliveryExceptionRepository = Yii::$container->get(DeliveryExceptionRepository::class);
        $this->commentRepository = Yii::$container->get(CommentRepository::class);
        $this->csMessageRepository = Yii::$container->get(CSMessageRepository::class);
    }

    /**
     * Тест для успешного выполнения правила, когда доставка переназначена более 3 раз
     */
    public function testRuleExecutionWhenDeliveryReschedulesMoreThanConfigured(): void
    {
        $this->specify('Правило должно выполняться, если доставка переназначена больше 3 раз', function () {
            // Устанавливаем исключение доставки с владельцем Logist
            $this->tester->haveRecord(DeliveryException::class, [
                'order_id' => $this->orderId,
                'owner' => ExceptionOwnerEnum::Logist,
                'created_user_id' => 3,
            ]);

            // Добавляем 4 переназначения доставки
            $this->createCommentsForReschedules(4);

            $rule = new BRTDeliveryRescheduleCountRule(
                $this->delivery,
                reschedulesCount: 3,
                deliveryExceptionRepository: $this->deliveryExceptionRepository,
                commentRepository: $this->commentRepository,
                csMessageRepository: $this->csMessageRepository,
            );

            // Проверяем выполнение правила
            $rule->evaluate();

            // Проверяем, что DeliveryException обновлен с правильными данными
            $updatedDeliveryException = $this->delivery->getDeliveryException()->one();
            $this->assertNotNull($updatedDeliveryException);
            $this->assertSame(ExceptionOwnerEnum::Logist, ExceptionOwnerEnum::from($updatedDeliveryException->owner));

            // Проверяем, что добавлен комментарий
            $comments = $updatedDeliveryException->getComments()->all();
            $this->assertNotEmpty($comments);
            $this->assertEquals(Yii::t('app', 'Заказ не двигается {days} дня', ['days' => 3]), $updatedDeliveryException->lastComment->content);
        });
    }

    /**
     * Тест, когда количество переназначений доставки меньше 3
     */
    public function testRuleDoesNotTriggerWhenDeliveryReschedulesLessThanConfigured(): void
    {
        $this->specify('Правило не должно срабатывать, если переназначений меньше 3', function () {
            // Устанавливаем исключение доставки с владельцем Logist
            $this->tester->haveRecord(DeliveryException::class, [
                'order_id' => $this->orderId,
                'owner' => ExceptionOwnerEnum::Logist,
                'created_user_id' => 3,
            ]);

            // Добавляем 2 переназначения доставки
            $this->createCommentsForReschedules(2);

            $lastCommentWithRescheduling = $this->delivery->deliveryException->getLastComment()->one()->content;

            $rule = new BRTDeliveryRescheduleCountRule(
                $this->delivery,
                reschedulesCount: 3,
                deliveryExceptionRepository: $this->deliveryExceptionRepository,
                commentRepository: $this->commentRepository,
                csMessageRepository: $this->csMessageRepository,
            );

            // Проверяем, что правило не выполняется
            $rule->evaluate();

            // Проверяем, что новый комментарий не добавился
            $updatedDeliveryException = $this->delivery->getDeliveryException()->one();
            $this->assertEquals($lastCommentWithRescheduling, $updatedDeliveryException->lastComment->content);
        });
    }

    /**
     * Создает комментарии для имитации переназначений даты доставки
     *
     * @param int $uniqueDatesCount Количество уникальных дат
     */
    protected function createCommentsForReschedules(int $uniqueDatesCount): void
    {
        
        for ($i = 0; $i < $uniqueDatesCount; $i++) {
            $date = (new DateTime())->modify("-$i day")->format('d.m.Y');

            $messages = $this->csMessageRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::SetDeliveryDate);
            $randomMessage = $messages[array_rand($messages)];

            $commentText = "{$randomMessage} il $date";
            $this->tester->haveRecord(Comment::class, [
                'content' => $commentText,
                'field_id' => $this->delivery->order_id,
                'key' => Comment::KEY_DELIVERY_EXCEPTION,
                'created_user_id' => 3,
            ]);
        }
    }
}
