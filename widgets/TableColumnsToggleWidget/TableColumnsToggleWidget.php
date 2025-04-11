<?php

declare(strict_types=1);

namespace app\widgets\TableColumnsToggleWidget;

use yii\base\Widget;

/**
 * Рендерит и инициализирует элемент для управления видимостью колонок таблицы
 * Рендерит звездоску по нажатии на которую отображается выпадающий список с чекбоксами
 * для каждой колонки таблицы.
 *
 * Стили поумолчанию выравнивают контрол по правому краю через
 * css position: absolute, right: 0 относительно контейнера с position absolute или relative
 * По умолчанию, предполагается, что таблица обернута в такой элемент .gridview-wrapper
 * Тоесть в этом контейнере сначала должен рендерится этот виджет , за ним griview,
 * оба обернуты в этот контейнер
 */
class TableColumnsToggleWidget extends Widget
{
    /**
     * Ключ для хранения настроек колонок в хранилище браузера.
     * По сути идентификатор для какой таблицы настройки
     */
    public string $storageKey = 'tableColumnsConfig';
    /**
     * Селектор таблицы для которой элемент должен настраивать колонки
     */
    public string $tableSelector = '.grid-view table';
    /**
     * Селектор контейера с дропдауном с чекбоксами для астройки видимости колонок
     */
    public string $checkboxContainerSelector = '#columns-dropdown';
    /**
     * Использовать ли стили по-молчанию
     */
    public bool $useDefaultStyles = true;
    
    /**
     * По умолчанию, пока нет сохраненых настроек, прятать колонки с этими индексами
     */
    public array $defaultHideColumnsIds = [];
    
    public function run()
    {
        return $this->render('table-columns-toggle', [
            'storageKey' => $this->storageKey,
            'tableSelector' => $this->tableSelector,
            'checkboxContainerSelector' => $this->checkboxContainerSelector,
            'useDefaultStyles' => $this->useDefaultStyles,
            'defaultHideColumnsIds' => $this->defaultHideColumnsIds,
        ]);
    }
}
