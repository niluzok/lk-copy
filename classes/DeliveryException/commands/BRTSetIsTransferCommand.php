<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\commands;

use Yii;
use DateTimeInterface;
use yii\db\Exception as LkNeologisticsException;
use app\models\DeliveryException;
use app\models\Delivery;
use app\repository\DeliveryExceptionRepository;
use app\repository\CommentRepository;
use app\models\Comment;
use app\classes\DeliveryException\SendOperatorOrLogistDeliveryException;
use app\classes\DeliveryException\commands\HandleExceptionWithOwnerCommandInterface;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\classes\DeliveryException\DeliveryExceptionFunc;
use app\exception\ModelNotValidException;

/**
 * Команда для изменения delivery_exception.is_transfer признака
 *
 * @todo возможно под удаление весь фукционнал is_transfer
 */
class BRTSetIsTransferCommand implements HandleExceptionCommandInterface
{
    /**
     * @var Delivery|null Модель доставки
     */
    protected ?Delivery $delivery = null;
    
    /**
     * @var string|null Сообщение о проблемной доставке
     */
    protected ?string $message = null;
    
    protected bool $newIsTransfer;

    /**
     * @param int|null $userId ID пользователя запускающего команду
     * @param DeliveryExceptionRepository $deliveryExceptionRepository Репозиторий для управления сообщениями проблемной доставки
     * @param CommentRepository $commentRepository Репозиторий для управления комментариями
     */
    public function __construct(
        protected DeliveryExceptionRepository $deliveryExceptionRepository,
    ) {
    }

    /**
     * Устанавливает модель доставки
     *
     * @param Delivery $delivery
     * @return self
     */
    public function setDelivery(Delivery $delivery): self
    {
        $this->delivery = $delivery;
        return $this;
    }

    /**
     * Задает сообщение от КС
     *
     * @return self
     */
    public function setMessage($message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Устанавливает новое значение для is_transfer
     *
     * @param bool $newIsTransfer
     * @return self
     */
    public function setIsTransfer(bool $newIsTransfer): self
    {
        $this->newIsTransfer = $newIsTransfer;
        return $this;
    }

    /**
     * Выполняет команду
     */
    public function run(): void
    {
        $existingDeliveryException = $this->getExistingDeliveryException();

        if ($existingDeliveryException !== null) {
            $existingDeliveryException->is_transfer = $this->newIsTransfer;

            if (!$existingDeliveryException->save(false, ['is_transfer'])) {
                ModelNotValidException::throwErrors($existingDeliveryException);
            }
        }
    }

    /**
     * Получает существующее сообщение проблемной доставки
     *
     * @return DeliveryException|null
     */
    protected function getExistingDeliveryException(): ?DeliveryException
    {
        return $this->delivery->deliveryException;
    }
}
