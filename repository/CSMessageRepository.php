<?php

declare(strict_types=1);

namespace app\repository;

use app\models\CourierServiceMessage;
use app\repository\Base\AbstractARRepository;
use app\enums\courier\CourierServiceMessageTypeEnum;

/**
 * Репозиторий для хранения сообщений курьерской службы по типам сообщений
 */
class CSMessageRepository extends AbstractARRepository
{
    protected function getModelClass(): string
    {
        return CourierServiceMessage::class;
    }

    /**
     * Возвращает массив АР-моделей сообщений курьерской службы
     *
     * @param int $courierServiceId
     * @param CourierServiceMessageTypeEnum|null $type
     * @return array
     */
    public function findAllByCourier(int $courierServiceId, ?CourierServiceMessageTypeEnum $type = null): array
    {
        $query = CourierServiceMessage::find()
            ->andWhere(['courier_id' => $courierServiceId]);

        if ($type) {
            $query->andWhere(['type' => $type->value]);
        }

        return $query->indexBy('id')->all();
    }

    /**
     * Возвращает массив поддерживаемых идентификаторов курьерских служб
     *
     * @return array
     */
    public function getSupportedCourierIds(): array
    {
        return CourierServiceMessage::find()
            ->select(['courier_id'])
            ->distinct()
            ->column();
    }

    /**
     * Проверяет наличие сообщений указанной курьерской службы
     *
     * @param int $courierServiceId
     * @return bool
     */
    public function hasCourierMessages(int $courierServiceId): bool
    {
        return in_array($courierServiceId, self::getSupportedCourierIds());
    }

    /**
     * Возвращает только тексты сообщения от курьерской службы - всех или указанного типа
     *
     * @param int $courierServiceId
     * @param CourierServiceMessageTypeEnum|null $type
     * @return array
     */
    public function getOnlyMessagesTexts(int $courierServiceId, ?CourierServiceMessageTypeEnum $type = null): array
    {
        $messagesAR = $this->findAllByCourier($courierServiceId, $type);

        return array_values(array_map(fn($m) => $m->message, $messagesAR));
    }
}
