<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\enums;

use Yii;

/**
 * Енум для обозначения владельца проблемной доставки
 *
 * Содержит возможные роли пользователей, которые могут быть владельцами
 * проблемной доставки
 *
 * @package app\classes\DeliveryException\enums
 */
enum ExceptionOwnerEnum: string
{
    use ExceptionOwnerEnumHelperTrait;
    
    /**
     * Логист
     *
     * @var string
     */
    case Logist = 'Logist';

    /**
     * Оператор
     *
     * @var string
     */
    case Operator = 'Operator';

    public static function labels()
    {
        return [
            ExceptionOwnerEnum::Logist->value => Yii::t('app', 'Логист'),
            ExceptionOwnerEnum::Operator->value => Yii::t('app', 'Оператор'),
        ];
    }

    /**
     * Возвращает значение переведенное на текущий язык пользователя
     *
     * @return  string
     */
    public function getTranslation()
    {
        return self::labels()[$this->value];
    }
}
