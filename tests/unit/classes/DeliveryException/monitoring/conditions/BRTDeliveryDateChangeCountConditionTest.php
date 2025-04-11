<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring\conditions;

use Base\Unit;
use Codeception\Specify;
use Codeception\Stub\Expected;
use app\classes\DeliveryException\monitoring\conditions\BRTDeliveryDateChangeCountCondition;
use app\models\Delivery;
use app\models\Comment;
use app\repository\CommentRepository;
use app\classes\DeliveryException\handlers\BRTDeliveryExceptionHandler;
use Yii;
use app\repository\CSMessageRepository;
use app\models\Courier;
use app\enums\courier\CourierServiceMessageTypeEnum;

/**
 * Тесты для класса BRTDeliveryDateChangeCountCondition
 */
class BRTDeliveryDateChangeCountConditionTest extends Unit
{
    use Specify;

    private $messageTexts;

    /**
     * @var Delivery Экземпляр объекта Delivery
     */
    protected Delivery $delivery;

    protected function _before()
    {
        $this->delivery = new Delivery();
        $this->messageTexts = [
            'message1',
            'message2',
            'message3',
        ];
    }

    /**
     * Тест метода check, когда количество уникальных дат меньше требуемого
     */
    public function testMethodCheckWithLessUniqueDates(): void
    {
        $this->specify('Когда количество уникальных дат меньше требуемого, check должен вернуть false', function () {
            
            $comments = [
                $this->make(Comment::class, ['content' => $this->messageTexts[0] . 'il 02.11.2024']),
                $this->make(Comment::class, ['content' => 'Прочий комментарий']),
                $this->make(Comment::class, ['content' => $this->messageTexts[1] . 'il 02.11.2024']),
            ];

            $commentRepository = $this->make(CommentRepository::class, [
                'findAll' => Expected::once($comments),
            ]);

            $csMessageRepository = $this->make(CSMessageRepository::class, [
                'getOnlyMessagesTexts' => Expected::once($this->messageTexts),
            ]);

            $condition = new BRTDeliveryDateChangeCountCondition($this->delivery, 5, $commentRepository, $csMessageRepository);

            expect('check должен вернуть false', $condition->check())->false();
        });
    }

    /**
     * Тест метода check, когда количество уникальных дат равно или больше требуемого
     */
    public function testMethodCheckWithSufficientUniqueDates(): void
    {
        $this->specify('Когда количество уникальных дат равно или больше требуемого, check должен вернуть true', function () {
            $comments = [
                $this->make(Comment::class, ['content' => 'Прочий комментарий']),
                $this->make(Comment::class, ['content' => $this->messageTexts[0] . 'il 02.11.2024']),
                $this->make(Comment::class, ['content' => $this->messageTexts[1] . 'il 03.11.2024']),
                $this->make(Comment::class, ['content' => 'Прочий комментарий']),
                $this->make(Comment::class, ['content' => $this->messageTexts[2] . 'il 04.11.2024']),
            ];

            $commentRepository = $this->make(CommentRepository::class, [
                'findAll' => Expected::once($comments),
            ]);

            $csMessageRepository = $this->make(CSMessageRepository::class, [
                'getOnlyMessagesTexts' => Expected::once($this->messageTexts),
            ]);

            $condition = new BRTDeliveryDateChangeCountCondition($this->delivery, 2, $commentRepository, $csMessageRepository);

            expect('check должен вернуть true', $condition->check())->true();
        });
    }

    /**
     * Тест метода check, когда нет нужных комментариев
     */
    public function testMethodCheckWithNoRelevantComments(): void
    {
        $this->specify('Когда комментарии не содержат нужных строк, check должен вернуть false', function () {
            $comments = [
                $this->make(Comment::class, ['content' => 'Прочий комментарий']),
            ];

            $commentRepository = $this->make(CommentRepository::class, [
                'findAll' => Expected::once($comments),
            ]);

            $csMessageRepository = $this->make(CSMessageRepository::class, [
                'getOnlyMessagesTexts' => Expected::once($this->messageTexts),
            ]);

            $condition = new BRTDeliveryDateChangeCountCondition($this->delivery, 2, $commentRepository, $csMessageRepository);

            expect('check должен вернуть false', $condition->check())->false();
        });
    }
}
