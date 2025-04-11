<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring\rules;

use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use app\classes\DeliveryException\monitoring\interfaces\ActionInterface;
use app\classes\DeliveryException\monitoring\interfaces\RuleInterface;
use Yii;
use Exception;

/**
 * Класс обобщенного правила, которое обладает следующими составляющими, которые
 * можно динамически настраивать:
 *
 * - enableConditions - Условие актуальности правила, при котором оно включается
 *   и проверяются triggerConditions
 * - triggerConditions - условия успешного срабатывания правила, при их выполнении
 *   запускаются actions
 * - actions - действия, команды, выполяемые если все условия истинны
 */
class GenericRule implements RuleInterface
{
    /**
     * @var ConditionInterface[] Условия для включения правила
     */
    private array $enableConditions = [];

    /**
     * @var ConditionInterface[] Условия для срабатывания правила и запуска actions
     */
    private array $triggerConditions = [];

    /**
     * @var ActionInterface[] Действия, которые выполняются при срабатывании правила
     */
    private array $actions = [];

    /**
     * Добавляет условие для включения правила
     *
     * @param ConditionInterface $condition Условие
     * @return self
     */
    public function addEnableCondition(ConditionInterface $condition): self
    {
        $this->enableConditions[] = $condition;
        return $this;
    }

    /**
     * Добавляет несколько условий для включения правила
     *
     * @param ConditionInterface[] $conditions Массив условий
     * @return self
     */
    public function addEnableConditions(array $conditions): self
    {
        foreach ($conditions as $condition) {
            $this->addEnableCondition($condition);
        }
        return $this;
    }

    /**
     * Добавляет условие для срабатывания правила
     *
     * @param ConditionInterface $condition Условие
     * @return self
     */
    public function addTriggerCondition(ConditionInterface $condition): self
    {
        $this->triggerConditions[] = $condition;
        return $this;
    }

    /**
     * Добавляет несколько условий для срабатывания правила
     *
     * @param ConditionInterface[] $conditions Массив условий
     * @return self
     */
    public function addTriggerConditions(array $conditions): self
    {
        foreach ($conditions as $condition) {
            $this->addTriggerCondition($condition);
        }
        return $this;
    }

    /**
     * Добавляет действие, которое выполняется при срабатывании правила
     *
     * @param ActionInterface $action Действие
     * @return self
     */
    public function addAction(ActionInterface $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    /**
     * Добавляет несколько действий, которые выполняются при срабатывании правила
     *
     * @param ActionInterface[] $actions Массив действий
     * @return self
     */
    public function addActions(array $actions): self
    {
        foreach ($actions as $action) {
            $this->addAction($action);
        }
        return $this;
    }

    /**
     * Проверяет, включено ли правило
     *
     * @return bool Возвращает true, если все условия для включения правила выполнены
     */
    public function isEnabled(): bool
    {
        foreach ($this->enableConditions as $condition) {
            if (!$condition->check()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Проверяет, должно ли правило сработать
     *
     * @return bool Возвращает true, если все условия для срабатывания правила выполнены
     */
    public function shouldTrigger(): bool
    {
        foreach ($this->triggerConditions as $condition) {
            if (!$condition->check()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Выполняет все действия, если правило включено и условия срабатывания выполнены
     * Операции выполняются в транзакции для обеспечения атомарности действий
     *
     * @return void
     */
    public function evaluate(): void
    {
        if ($this->isEnabled() && $this->shouldTrigger()) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                foreach ($this->actions as $action) {
                    $action->run();
                }
                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }
}
