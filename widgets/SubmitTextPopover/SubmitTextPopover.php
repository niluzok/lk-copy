<?php

declare(strict_types=1);

namespace app\widgets\SubmitTextPopover;

use yii\base\Widget;
use yii\helpers\Html;
use Yii;
use kartik\popover\PopoverX;

class SubmitTextPopover extends Widget
{
    public $popoverId = 'submit-text-popover';
    public $buttonId = 'submit-text-popover-btn';
    public $placeholderText = 'Текст заметки...';
    public $saveButtonLabel = 'Сохранить';
    public $closeButtonLabel = 'Закрыть';
    public $textareaId = 'popover-textarea';
    public $headerIcon = 'glyphicon glyphicon-edit';
    public $headerTitle = 'Оставить заметку';
    public $pjaxContainerId;
    public $requestUrl;
    public $textParamName = 'note';
    public $popoverXplacement = PopoverX::ALIGN_AUTO_BOTTOM;

    public function init()
    {
        parent::init();

        if ($this->placeholderText === null) {
            $this->placeholderText = Yii::t('app', 'Текст заметки...');
        }

        if ($this->saveButtonLabel === null) {
            $this->saveButtonLabel = Yii::t('app', 'Сохранить');
        }

        if ($this->closeButtonLabel === null) {
            $this->closeButtonLabel = Yii::t('app', 'Закрыть');
        }

        if ($this->headerIcon === null) {
            $this->headerIcon = 'glyphicon glyphicon-edit';
        }

        if ($this->headerTitle === null) {
            $this->headerTitle = Yii::t('app', 'Оставить заметку');
        }

        if ($this->pjaxContainerId === null) {
            throw new \Exception('pjaxContainerId is required.');
        }

        if ($this->requestUrl === null) {
            throw new \Exception('requestUrl is required.');
        }
    }

    public function run()
    {
        return $this->render('submit-text-popover', [
            'popoverId' => $this->popoverId,
            'buttonId' => $this->buttonId,
            'placeholderText' => $this->placeholderText,
            'saveButtonLabel' => $this->saveButtonLabel,
            'closeButtonLabel' => $this->closeButtonLabel,
            'textareaId' => $this->textareaId,
            'headerIcon' => $this->headerIcon,
            'headerTitle' => $this->headerTitle,
            'pjaxContainerId' => $this->pjaxContainerId,
            'requestUrl' => $this->requestUrl,
            'textParamName' => $this->textParamName,
            'popoverXplacement' => $this->popoverXplacement,
        ]);
    }
}
