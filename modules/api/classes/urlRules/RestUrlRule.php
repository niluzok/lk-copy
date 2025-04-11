<?php

namespace app\modules\api\classes\urlRules;

use yii\rest\UrlRule as BaseRestUrlRule;
use app\modules\api\classes\urlRules\UrlRule;

/**
 * {@inheritdoc}
 *
 * Класс для автосоздания правил маршрутизации. Основной код в yii\rest\UrlRule
 *
 * Переопределяем методы для обработки случая, когда к апи обращаются по
 * ендпоинту с поддомеом api, без ../api/.. через который работает стандартный
 * раутинг модуля api.
 *
 * api.domain/onec/controller/action
 * вместо
 * domain/api/onec/controller/action
 *
 * Примечание: правила роутинга кэшируются и часть ключа кэша это сериализованные правила.
 * Для от домена не зависят правила и в обоих случаях будет использован один и тот же
 * ключ кэша – поэтому в правилах должны быть оба правила и с /api/ и без
 *
 * ! Важно ! Если код правил изменился, а сами правила(мармруты) нет, то будет использована старая
 * закэшированная версия правил. Чтобы обновить, надо чтобы изменилась структура
 * конфигов правил. Проще всего для этого изменить "ключ" правила в кофиге или
 * в urlManager->addRules()
 *
 * Кэш используется в [[yii\web\UrlManager::buildRules()]]
 *
 * @see https://leadgidwebvork.monday.com/boards/5160193477/pulses/6372712392
 *
 */
class RestUrlRule extends BaseRestUrlRule
{
    public $ruleConfig = [
        'class' => UrlRule::class,
    ];

    /**
     * Массив экшенов (их id), которые вручную созданы в ActiveController.
     * Для них создается маршрут для случая, когда "api" в домене, а не в пути
     *
     * @var array
     */
    public array $extraActions = [];

    /**
     * {@inheritdoc}
     *
     * Переопределяем метод. Добавлены помеченые строчки, чтобы создаваемые правила
     * искали совпадение в урле без api/
     *
     * @return  array
     */
    protected function createRules()
    {
        $only = array_flip($this->only);
        $except = array_flip($this->except);
        $patterns = $this->extraPatterns + $this->patterns;
        $rules = [];
        foreach ($this->controller as $urlName => $controller) {
            $prefix = trim($this->prefix . '/' . $urlName, '/');
            foreach ($patterns as $pattern => $action) {
                if (!isset($except[$action]) && (empty($only) || isset($only[$action]))) {
                    $rules[$urlName][] = $this->createRule($pattern, $prefix, $controller . '/' . $action);

                    // === Добавлено ===
                    // Добавляем правило для случая урла без /api/
                    $urlNameNoApiInUrl = str_replace('api/', '', $urlName);
                    $prefixNoApiInUrl = str_replace('api/', '', $prefix);

                    $rule = $this->createRule($pattern, $prefixNoApiInUrl, $controller . '/' . $action);
                    $rule->forApiInHostName = true;
                    $rules[$urlNameNoApiInUrl][] = $rule;
                    // =================
                }
            }

            // === Добавлено ===
            // Для экшенов с ид, указанных в массиве [[$this->extraActions]],
            // Создать правила для случая, когда слово "api" в домене, а не в url
            if (API_WORD_IN_HOSTNAME && $this->extraActions) {
                foreach ($this->extraActions as $action) {
                    $urlNameNoApiInUrl = str_replace('api/', '', $controller);
                    $urlNameNoApiInUrl .= "/{$action}";
                    $prefixNoApiInUrl = $urlNameNoApiInUrl;

                    $rule = $this->createRule($pattern, $prefixNoApiInUrl, $controller . '/' . $action);
                    $rule->forApiInHostName = true;
                    $rules[$urlNameNoApiInUrl][] = $rule;
                }
            }
            // =================
        }

        return $rules;
    }
}
