<?php

declare(strict_types=1);

use kartik\color\ColorInput;

/**
 * @var string $id
 * @var string $name
 * @var string $url
 */
?>

<style>
    .batch-color-picker-container {
        display: inline-block;
        width: 65px;
        padding: 0;
    }

    #batch-color-picker-cont {
        padding: 6px 0;
        background-color: unset;
        border: none;
    }

    #batch-color-picker-cont .sp-replacer {
        background-color: unset;
    }

    /* всплывающая палитра для выбора цвета */
    .sp-container {
        border: none;
        border-radius: 4px;
    }
</style>

<script type="module">
    import OrderColorPicker from '/js/es-modules/manager/delivery-exception/OrderColorPicker.js';
    import RowsGetter from '/js/es-modules/TableRowIdsGetter.js';
    import ef from '/js/es-modules/form-helpers/element-finder.js';

    const rowsGetter = RowsGetter.getInstance('.grid-view table');

    // Инициализация экземпляров OrderColorPicker на странице
    OrderColorPicker.initOnPageLoad('#order-table', {
        batchColorPickerSelector: '#<?= $id ?>',
        url: '<?= $url ?>',
        getOrderIdsCallback: () => {
            return rowsGetter.getCheckedValues();
        },
        onSuccess: (data) => {
            const orderIdsColorChanged = data.orderIdsColorChanged;
            for (let orderId in orderIdsColorChanged) {
                let color = orderIdsColorChanged[orderId];
                ef.$findRowForOrderId(orderId).attr('style', `background-color: ${color} !important;`);
            }
        },
    });
</script>

<?= ColorInput::widget([
    'id' => $id,
    'name' => $name,
    // 'value' => 'red',
    // 'useNative' => true,
    'showDefaultPalette' => false,
    'options' => ['class' => 'hidden'],
    'containerOptions' => [
        'class' => 'batch-color-picker-container btn btn-default',
    ],
    'pluginOptions' => [
        'showInput' => false,
        'showInitial' => false,
        'showPalette' => true,
        'showPaletteOnly' => true,
        'showSelectionPalette' => true,
        'showAlpha' => false,
        'allowEmpty' => true,
        'hideAfterPaletteSelect' => true,
        'preferredFormat' => 'name',
        'palette' => [
            [
                '#FFB3BA', '#FFDFBA', '#FFFFBA', '#BAFFC9', '#BAE1FF',
            ],
            [
                '#E0BBE4', '#FF9CEE', '#B5EAD7', '#C7CEEA', '#FFDAC1',
            ],
            [
                'transparent',
            ]
        ],
    ],
]) ?>
