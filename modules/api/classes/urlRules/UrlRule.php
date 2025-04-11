<?php

namespace app\modules\api\classes\urlRules;

use yii\web\UrlRule as YiiUrlRule;

/**
 * Класс простого урл-правила. К yii-шному классу добавлятся свойство, хранящее
 * призак, что это правило предназначается для случая с "api" в домене
 */
class UrlRule extends YiiUrlRule
{
    /**
     * Правило предназачено только для случая, когда домен начинается на api.*
     * или api_, а в пути /api/ нет
     *
     * @var bool
     */
    public bool $forApiInHostName = false;
    
    public function parseRequest($manager, $request)
    {
        // Разрешаем маршрут, если:
        // 1. Домен без api и правило для случая, когда api в uri
        // 2. Домен с "api", и правило для случая, когда api в домене, а не в пути
        if ($this->forApiInHostName && API_WORD_IN_HOSTNAME) {
            return parent::parseRequest($manager, $request);
        } elseif (!$this->forApiInHostName && !API_WORD_IN_HOSTNAME) {
            return parent::parseRequest($manager, $request);
        }

        return false;
    }
}
