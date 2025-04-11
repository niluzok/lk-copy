<?php

declare(strict_types=1);

use yii\helpers\Html;
use kartik\popover\PopoverX;

/**
 * @var $popoverId
 * @var $buttonId
 * @var $placeholderText
 * @var $saveButtonLabel
 * @var $closeButtonLabel
 * @var $textareaId
 * @var $headerIcon
 * @var $headerTitle
 * @var $pjaxContainerId
 * @var $requestUrl
 * @var $textParamName
 * @var $popoverXplacement
 */

?>

<script type = 'module'>
    import RowsGetter from '/js/es-modules/TableRowIdsGetter.js';
    import PopoverWithText from '/js/es-modules/PopoverWithText.js';
    import ef from '/js/es-modules/form-helpers/element-finder.js';
    
    PopoverWithText.initOnPageLoad({
        triggerPopoverBtnSelector: '#<?= $buttonId ?>',
        pjaxContainerSelector: '#<?= $pjaxContainerId ?>',
        requestUrl: '<?= $requestUrl ?>',
        textParamName: '<?= $textParamName ?>',
        messages: {
            emptyField: '<?= Yii::t('app', 'Текстовое поле пусто') ?>',
            errorOccurred: '<?= Yii::t('app', 'Произошла ошибка: {error}') ?>',
        },
        beforeRequest: (addData, abort) => {
            const rowGetter = RowsGetter . getInstance('.gridview table');
            const orderIds = rowGetter . getCheckedValues();

            if (orderIds . length < 1) {
                abort('<?= Yii::t('app', 'Не выбрано ни одной строки') ?>');
            }

            addData({
                ids: orderIds,
                hasEditable: true
            });
        },
        onSuccess: (data) => {
            const orderIdsNoteChanged = data.orderIdsNoteChanged
            for(let orderId in orderIdsNoteChanged) {
                let noteText = orderIdsNoteChanged[orderId]
                ef.$findRowForOrderId(orderId).find('.delivery-exception-note button').text(noteText)
            }

        },
    });
</script>

<?php

PopoverX::begin([
'id' => $popoverId,
'placement' => $popoverXplacement,
'toggleButton' => [
    'label' => Yii::t('app', 'Оставить заметку'),
    'class' => 'btn btn-default',
    'id' => $buttonId
],
'header' => '<i class="' . $headerIcon . '"></i> ' . $headerTitle,
'footer' => Html::button($saveButtonLabel, ['class' => 'send-btn btn btn-success']) .
            Html::button($closeButtonLabel, ['class' => 'btn btn-default', 'data-dismiss' => 'popover-x']),
]);

echo Html::textarea('', '', [
    'id' => $textareaId,
    'class' => 'form-control',
    'rows' => 4,
    'cols' => 50,
    'placeholder' => $placeholderText
]);

echo Html::tag('div', '', [
    'class' => 'popover-error text-danger mt-2',
    'style' => 'display: none;'
]);

PopoverX::end();
