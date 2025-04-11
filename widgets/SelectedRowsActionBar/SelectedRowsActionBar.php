<?php

declare(strict_types=1);

namespace app\widgets\SelectedRowsActionBar;

use yii\base\Widget;
use yii\bootstrap\Html;

/**
 * Класс виджета для отображения панели действий при выборе строк
 */
class SelectedRowsActionBar extends Widget
{
    /**
     * @var string ID элемента
     */
    public $id = 'action-bar';

    /**
     * @var string Класс CSS для панели действий
     */
    public $class = 'action-bar';
    
    /**
     * @var string Цвет фона панели
     */
    public $bgColor;

    /**
     * Запускает виджет
     */
    public static function begin($config = [])
    {
        parent::begin($config);

        if (!isset($config['bgColor'])) {
            $config['bgColor'] = '#f8f9fa';
        }

        echo Html::beginTag('div', [
            'id' => $config['id'],
            'class' => $config['class'],
            'style' => "background-color: {$config['bgColor']};",
        ]);
    }
    
    /**
     * Завершает виджет
     */
    public static function end()
    {
        echo '</div>';
        parent::end();
    }

    public function run($config = [])
    {
        echo $this->render('_selected-rows-action-bar');
    }
}
