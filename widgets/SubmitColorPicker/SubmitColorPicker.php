<?php

declare(strict_types=1);

namespace app\widgets\SubmitColorPicker;

use yii\base\Widget;

/**
 * Виджет. Отображает элемент для выбора цвета из преднастроенной палитры
 * и при выборе цвета отправляет ajax-запрос на заданный url
 */
class SubmitColorPicker extends Widget
{
    /**
     * @var string Идентификатор элемента выбора цвета
     */
    public $id = 'batch-color-picker';

    /**
     * @var string Имя элемента выбора цвета
     */
    public $name = 'color';

    /**
     * @var string URL для отправки данных выбора цвета
     */
    public $url = '';

    /**
     * Выполняет рендеринг виджета
     *
     * @return string Возвращает сгенерированный HTML код виджета
     */
    public function run()
    {
        return $this->render('_submit-color-picker', [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
        ]);
    }
}
