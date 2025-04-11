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
use app\classes\DeliveryException\monitoring\interfaces\ActionInterface;

/**
 * Команда обработки сообщений проблемной доставки
 */
class HandleExceptionCommand implements HandleExceptionWithOwnerCommandInterface, ActionInterface
{
    const SYSTEM_MANAGER_ID = 3;

    /**
     * @var string|null Сообщение о проблемной доставке
     */
    protected ?string $message = null;

    /**
     * @var int|null ID фазы владельца проблемной доставки
     */
    protected ?int $exceptionOwnerId = null;

    /**
     * @var ExceptionOwnerEnum|null Владельец проблемной доставки
     */
    protected ?ExceptionOwnerEnum $exceptionOwner = null;

    /**
     * @var Delivery|null Модель доставки
     */
    protected ?Delivery $delivery = null;

    /**
     * @var DateTimeInterface|null Дата доставки
     */
    protected ?DateTimeInterface $deliveredDate = null;

    /**
     * @param int|null $userId ID пользователя запускающего команду
     * @param DeliveryExceptionRepository $deliveryExceptionRepository Репозиторий для управления сообщениями проблемной доставки
     * @param CommentRepository $commentRepository Репозиторий для управления комментариями
     */
    public function __construct(
        protected ?int $userId,
        protected DeliveryExceptionRepository $deliveryExceptionRepository,
        protected CommentRepository $commentRepository
    ) {
        $this->userId = $userId ?? Yii::$app->user->id ?? self::SYSTEM_MANAGER_ID;
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
     * Устанавливает владельца проблемной доставки
     *
     * @param ExceptionOwnerEnum $exceptionOwner
     * @return self
     */
    public function setExceptionOwner(ExceptionOwnerEnum $exceptionOwner): self
    {
        $exceptionOwnerPhaseId = DeliveryExceptionFunc::phaseFromExceptionOwner($exceptionOwner);

        $this->exceptionOwnerId = $exceptionOwnerPhaseId;
        $this->exceptionOwner = $exceptionOwner;
        return $this;
    }

    /**
     * Устанавливает дату доставки
     *
     * @param DateTimeInterface $datetime
     * @return self
     */
    public function setDeliveredDate(DateTimeInterface $datetime): self
    {
        $this->deliveredDate = $datetime;
        return $this;
    }

    /**
     * Выполняет команду
     */
    public function run(): void
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $existingDeliveryException = $this->getExistingDeliveryException();

            if ($existingDeliveryException === null) {
                $existingDeliveryException = $this->saveNewDeliveryException();
                $this->delivery->populateRelation('deliveryException', $existingDeliveryException);
            }

            if ($this->getMessage() !== null) {
                if ($this->deliveredDate) {
                    $existingDeliveryException->delivered_ts = Yii::$app->formatter->asSqlDateTime(
                        value: $this->deliveredDate,
                        showNull: true
                    );
                }

                $existingDeliveryException->updateExceptionMessage($this->getMessage(), saveThisModel: false, userId: $this->userId);
            }

            if ($this->exceptionOwner !== null) {
                $this->changeExceptionOwner($this->exceptionOwner, saveDeliveryExceptionModel: false);
            }

            if (!$existingDeliveryException->save()) {
                /** @todo ModelNotValidException  */
                throw new \RuntimeException('DeliveryException was not saved. DeliveryException errors: ' . print_r($existingDeliveryException->errors, true));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
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

    /**
     * Создает и сохраняет новое сообщение проблемной доставки
     *
     * @return DeliveryException
     */
    protected function saveNewDeliveryException(): DeliveryException
    {
        $orderId = $this->delivery->order_id;
        $managerId = $this->userId; // Assuming userId is the manager ID
        // $operatorId = null; // Define or retrieve the operator ID as needed
        $deliveryCourier = $this->delivery->deliveryCourier;

        $deliveryData = [
            'created_ts' => date('Y-m-d H:i:s'),
            'updated_ts' => date('Y-m-d H:i:s'),
            'created_user_id' => $managerId,
            'order_id' => $orderId,
            'send_in_stock_ts' => $this->delivery->send_in_stock_ts,
            'courier_id' => $deliveryCourier->courier_id,
            'tracking_number' => $deliveryCourier->tracking_number,
            'created_order_phase_id' => $this->delivery->orderPhase->id,
            'order_phase_id' => $this->delivery->orderPhase->id,
            'phase_id' => $this->delivery->orderPhase->phase_id,
            'owner' => ExceptionOwnerEnum::Logist->value, /** @todo default. move to rules() */
            // 'operator_id' => $operatorId,
        ];

        return $this->deliveryExceptionRepository->create($deliveryData);
    }

    /**
     * Сохраняет комментарий с сообщением к проблемной доставке
     *
     * @param string $commentText Текст комментария
     * @param DeliveryException $existingDeliveryException Существующее сообщение проблемной доставки
     * @return Comment Модель комментария
     * @throws LkNeologisticsException
     */
    protected function createComment(string $commentText, DeliveryException $existingDeliveryException): Comment
    {
        return $this->commentRepository->create([
            'key' => Comment::KEY_DELIVERY_EXCEPTION,
            'field_id' => $existingDeliveryException->order_id,
            'created_user_id' => $this->userId,
            'content' => $commentText,
        ]);
    }

    /**
     * Изменяет владельца сообщения проблемной доставки
     *
     * @param ExceptionOwnerEnum|null $exceptionOwner Владелец проблемного заказа, ответственный
     */
    protected function changeExceptionOwner(?ExceptionOwnerEnum $exceptionOwner, bool $saveDeliveryExceptionModel = true): void
    {
        /**
         * @todo Убрать при замене механизма хранения владельца эксепшена
         */
        $this->changeExceptionOwnerPhase($exceptionOwner, saveDeliveryExceptionModel: false);
        
        $currentExceptionOwner = $this->delivery->deliveryException->owner;
        if (ExceptionOwnerEnum::from($currentExceptionOwner) == $exceptionOwner) {
            return;
        }
        
        $deliveryException = $this->getExistingDeliveryException();
        $deliveryException->owner = $exceptionOwner->value;
        
        if ($saveDeliveryExceptionModel) {
            $saved = $deliveryException->save(false, ['owner']);
            if (!$saved) {
                throw new \RuntimeException('DeliveryException.owner was not saved. DeliveryException errors: ' + print_r($this->delivery->deliveryException->errors, true));
            }
        }
    }

    /**
     * Смена владельца проблемного заказа в фазах
     *
     * @todo Удалить при переходе на колонку владельца
     *
     * @param   ExceptionOwnerEnum  $exceptionOwner
     *
     * @return  void
     */
    protected function changeExceptionOwnerPhase(ExceptionOwnerEnum $exceptionOwner, bool $saveDeliveryExceptionModel = true): void
    {
        $newPhaseId = DeliveryExceptionFunc::phaseFromExceptionOwner($exceptionOwner);
        $currentPhaseId = $this->delivery->deliveryException->currentPhase->phase_id;
        if ($currentPhaseId == $newPhaseId) {
            return;
        }
        
        $this->saveOwnerExceptionPhase($exceptionOwner, saveDeliveryExceptionModel: $saveDeliveryExceptionModel);
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
     * Получает сообщение о проблемной доставке
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Сохраняет фазу заказа, соответствующую владельцу проблемной доставки
     *
     * @todo Убрать после ухода от использования фаз
     *
     * @param   ExceptionOwnerEnum  $exceptionOwner
     * @param   bool  $saveDeliveryExceptionModel Сохранять ли модель DeliveryException
     *
     * @return  void
     */
    public function saveOwnerExceptionPhase(ExceptionOwnerEnum $exceptionOwner, bool $saveDeliveryExceptionModel = true): void
    {
        $sendOperatorOrLogistDeliveryException = Yii::createObject(SendOperatorOrLogistDeliveryException::class);
        $sendOperatorOrLogistDeliveryException->setParams([
            'order_id' => $this->delivery->order_id,
            'manager_comment' => $this->getMessage(),
            'manager_id' => $this->userId,
        ]);

        $phaseChanged = $sendOperatorOrLogistDeliveryException->createAndCloseParentOrderPhase(DeliveryExceptionFunc::phaseFromExceptionOwner($exceptionOwner));
        
        if ($phaseChanged) {
            $this->delivery->deliveryException->order_phase_id = $sendOperatorOrLogistDeliveryException->getNewOrderPhase()->id;
            $this->delivery->deliveryException->phase_id = $sendOperatorOrLogistDeliveryException->getNewOrderPhase()->phase_id;
            unset($this->delivery->deliveryException->currentPhase);
            unset($this->delivery->orderPhase);
            
            if ($saveDeliveryExceptionModel) {
                $saved = $this->delivery->deliveryException->save();
                if (!$saved) {
                    throw new \RuntimeException('Phase was not saved. DeliveryException errors: ' + print_r($this->delivery->deliveryException->errors, true));
                }
            }
        }
    }
}
