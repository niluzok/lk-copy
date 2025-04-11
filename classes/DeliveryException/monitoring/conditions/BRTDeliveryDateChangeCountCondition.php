<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\conditions;

use app\classes\DeliveryException\handlers\BRTDeliveryExceptionHandler;
use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use app\models\Comment;
use app\repository\CommentRepository;
use app\models\Delivery;
use Yii;
use app\models\Courier;
use app\enums\courier\CourierServiceMessageTypeEnum;
use app\repository\CSMessageRepository;

/**
 * Класс для проверки количества переносов доставки
 *
 * Текущая имплементация считает кол-во комментариев для проблемного заказа
 * из списка по которому проставляются новые даты доставки
 * (BRTDeliveryExceptionHandler::messagesToSetDeliveredDateFrom())
 */
class BRTDeliveryDateChangeCountCondition implements ConditionInterface
{
    /**
     * @var Delivery Объект доставки
     */
    private Delivery $delivery;

    /**
     * @var int Минимальное количество уникальных дат
     */
    private int $uniqueDatesCount;

    /**
     * @var CommentRepository Репозиторий для работы с комментариями
     */
    private CommentRepository $commentRepository;

    /**
     * @var CSMessageRepository Репозиторий для работы с сообщениями КС
     */
    private CSMessageRepository $csMessageRepository;

    /**
     * Конструктор принимает объект доставки, минимальное количество уникальных дат и репозиторий комментариев
     *
     * @param Delivery $delivery Объект доставки
     * @param int $uniqueDatesCount Минимальное количество уникальных дат
     * @param CommentRepository $commentRepository Репозиторий комментариев
     */
    public function __construct(Delivery $delivery, int $uniqueDatesCount, CommentRepository $commentRepository, CSMessageRepository $csMessageRepository)
    {
        $this->delivery = $delivery;
        $this->uniqueDatesCount = $uniqueDatesCount;
        $this->commentRepository = $commentRepository;
        $this->csMessageRepository = $csMessageRepository;
    }

    /**
     * Проверяет, что количество уникальных дат комментариев больше или равно заданному значению
     *
     * @return bool Возвращает true, если количество уникальных дат больше или равно минимальному значению
     */
    public function check(): bool
    {
        $comments = $this->commentRepository->findAll([
            'field_id' => $this->delivery->order_id,
            'key' => Comment::KEY_DELIVERY_EXCEPTION,
        ]);

        $filteredComments = $this->filterCommentsWithDeliveryDates($comments);
        $uniqueDates = $this->extractUniqueDates($filteredComments);

        return count($uniqueDates) >= $this->uniqueDatesCount;
    }

    /**
     * Фильтрует комментарии, оставляя только те, которые содержат строки с датами изменения доставки
     *
     * @param Comment[] $comments Массив комментариев
     * @return Comment[] Отфильтрованные комментарии
     */
    protected function filterCommentsWithDeliveryDates(array $comments): array
    {
        $messagesWithDates = $this->csMessageRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::SetDeliveryDate);

        return array_filter($comments, function (Comment $comment) use ($messagesWithDates) {
            foreach ($messagesWithDates as $message) {
                if (strpos($comment->content, $message) !== false) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Извлекает уникальные даты из комментариев
     *
     * @param Comment[] $comments Массив комментариев
     * @return array Уникальные даты в формате ['02.11.2024']
     */
    protected function extractUniqueDates(array $comments): array
    {
        $uniqueDates = [];
        foreach ($comments as $comment) {
            if (preg_match(BRTDeliveryExceptionHandler::DATE_REGEX, $comment->content, $matches)) {
                $date = $matches[0]; // Дата в формате 02.11.2024
                $uniqueDates[$date] = true;
            }
        }
        return array_keys($uniqueDates);
    }
}
