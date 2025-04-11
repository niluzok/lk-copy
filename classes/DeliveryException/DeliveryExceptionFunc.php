<?php

declare(strict_types=1);

namespace app\classes\DeliveryException;

use Yii;
use Throwable;
use DateTimeInterface;
use DateTimeImmutable;
use app\models\Phase;
use app\models\form\DeliveryException\SendOperatorOrLogistForm;
use app\models\DeliveryCourier;
use app\models\Courier;
use app\repository\DeliveryRepository;
use app\exception\LkNeologisticsException;
use app\classes\DeliveryException\commands\HandleExceptionOwnerFreezeCommand;
use app\classes\DeliveryException\enums\ExceptionOwnerEnum;
use app\enums\courier\CourierServiceMessageTypeEnum;
use app\repository\cached\CSMessageRepository;
use app\models\Delivery;

/**
 * Класс-хелпер для работы с DeliveryException
 */
class DeliveryExceptionFunc
{
    public const SYSTEM_MANAGER_ID = 3;

    public const EXCEPTION_FOR_OLD = 'Ordine sospeso in transito';

    /**
     * Авто-создание DeliveryException-ов для Delivery с непустым exception
     *
     * @param   ?DateTimeInterface  $dateFrom  Дата начала периода, за который анализируются Delivery
     *                                        Если не задана, то это день месяц назад
     * @param   ?DateTimeInterface  $dateTo    Дата окончания периода, за который анализируются Delivery
     *                                        Если не задана, то ровна последней секунде текущего дня
     */
    public static function createAuto(?DateTimeInterface $startDate = null, ?DateTimeInterface $endDate = null): void
    {
        $startDate = $startDate ?? new DateTimeImmutable(date('Y-m-d 00:00:00', time() - 86400 * 30));
        $endDate = $endDate ?? new DateTimeImmutable(date('Y-m-d 23:59:59'));

        /** @var DeliveryRepository */
        $deliveryRepository = Yii::createObject(DeliveryRepository::class);
        
        $items = $deliveryRepository->queryOrderExceptions($startDate, $endDate);

        foreach ($items as $orderException) {
            $deliveryRepository = Yii::createObject(DeliveryRepository::class);
            $delivery = $deliveryRepository->findById($orderException['order_id']);

            self::createOneSilent($delivery, $orderException['exception']);
        }
    }

    /**
     * Обрабатывает и создает проблемный заказ для 1 доставки
     *
     * @param   Delivery  $delivery
     * @param   string    $message  Сообщение от КС
     *
     * @return  void
     */
    public static function createOneSilent(Delivery $delivery, string $message): void
    {
        try {
            /** @var DeliveryExceptionService */
            $deService = Yii::createObject(DeliveryExceptionService::class);

            /**
             * @todo Убрать. Временный костыль, пока не выделили фазы оператора и логиста в отдельную сущность
             *
             * Если фаза НЕ логист и НЕ оператор, то оунер и фаза замораживаются (принудительно не меняются)
             * */
            if ($delivery->deliveryException && !in_array($delivery->orderPhase->phase_id, Phase::DELIVERY_EXCEPTIONS_ROLES)) {
                $command = Yii::createObject(HandleExceptionOwnerFreezeCommand::class);
                $fabric = Yii::createObject(DeliveryExceptionHandlerFactory::class, [$command]);
    
                /** @var DeliveryExceptionService */
                $deService = Yii::createObject(DeliveryExceptionService::class, [$fabric]);
            }
    
            $deService->processException($delivery, $message);
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'Exception while processing order for delivery exception',
                'order_id' => $delivery->order_id,
                'exception' => $e,
            ]);
        }
    }

    public static function create(array $params): void
    {
        try {
            $form = self::createSendOperatorOrLogistForm($params);

            if (!$form->save()) {
                throw new LkNeologisticsException('SendOperatorForm save error: ' . print_r($form->getErrors(), true));
            }
        } catch (Throwable $e) {
            Yii::error([
                'message' => 'Create DeliveryException error',
                'exception' => $e,
                'params' => $params,
                'tags' => ['order_id' => $params['order_id']]
            ], self::class);
        }
    }

    /**
     * Входит ли исключение в список BRT_NO_PROBLEM_LIST
     *
     * @todo удалить
     *
     * @param $exception
     *
     * @return bool
     */
    public static function isMessageNotAProblem($exception): bool
    {
        if (!empty($exception)) {
            $csMessagesRepository = new CSMessageRepository();
            $noproblemMessages = $csMessagesRepository->getOnlyMessagesTexts(Courier::ID_BRT, CourierServiceMessageTypeEnum::NoProblem);
            
            foreach ($noproblemMessages as $comment) {
                if (str_contains(mb_strtolower((string) $exception), mb_strtolower($comment))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @todo удалить
     *
     * @param array $params
     *
     * @return ?SendOperatorOrLogistForm
     *
     * @throws LkNeologisticsException
     */
    public static function createSendOperatorOrLogistForm(array $params): ?SendOperatorOrLogistForm
    {
        /** @var CSMessageRepository */
        $csMessageRepository = Yii::createObject(CSMessageRepository::class);

        $dc = DeliveryCourier::findOne(['order_id' => $params['order_id']]);
        if ($dc && in_array($dc->courier_id, $csMessageRepository->getSupportedCourierIds())) {
            $model = new SendOperatorOrLogistForm();
            $model->manager_id = Yii::$app->user->id ?? self::SYSTEM_MANAGER_ID;
            $model->manager_comment = $params['exception'];
            $model->orders = $params['order_id'];
            
            return $model;
        }

        return null;
    }

    /**
     * Отдает фазу заказа, соответствующую владельцу исключения доставки
     *
     * @todo Убрать при уходе от использования фаз для этого
     *
     * @param   ExceptionOwnerEnum  $exceptionOwner  Владелец проблемной доставки
     *
     * @return  int
     */
    public static function phaseFromExceptionOwner(ExceptionOwnerEnum $exceptionOwner)
    {
        return ($exceptionOwner === ExceptionOwnerEnum::Logist)
            ? Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST
            : Phase::DELIVERY_EXCEPTION_SEND_OPERATOR
        ;
    }

    public static function exceptionOwnerFromPhaseId(int $phaseId)
    {
        return match ($phaseId) {
            Phase::DELIVERY_EXCEPTION_SEND_OPERATOR => ExceptionOwnerEnum::Operator,
            Phase::DELIVERY_EXCEPTION_PROCESSED_ORDER_SEND_LOGIST => ExceptionOwnerEnum::Logist,
            default => null,
        };
    }

    public static function manualChangePhase(int $orderId, int $phaseId): bool
    {
        /** @var SendOperatorOrLogistDeliveryException */
        $sendOperatorOrLogistDeliveryException = Yii::createObject(SendOperatorOrLogistDeliveryException::class);
        $sendOperatorOrLogistDeliveryException->setParams([
            'order_id' => $orderId,
            'manager_id' => 3,
        ]);

        $phaseChanged = $sendOperatorOrLogistDeliveryException->createAndCloseParentOrderPhase($phaseId);

        return $phaseChanged;
    }
}
