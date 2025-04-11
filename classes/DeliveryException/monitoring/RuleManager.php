<?php

declare(strict_types=1);

namespace app\classes\DeliveryException\monitoring;

use app\classes\DeliveryException\monitoring\interfaces\RuleInterface;

/**
 * RuleManager управляет коллекцией правил для мониторинга проблемных заказов,
 * добавляет правила, запускает все правила
 */
class RuleManager
{
    /**
     * @var RuleInterface[] Массив правил, которыми управляет менеджер
     */
    private array $rules = [];

    /**
     * Добавляет новое правило в менеджер
     *
     * @param RuleInterface $rule Правило для добавления
     * @return self
     */
    public function addRule(RuleInterface $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    /**
     * Добавляет несколько правил в менеджер
     *
     * @param RuleInterface[] $rules Массив правил для добавления
     * @return self
     */
    public function addRules(array $rules): self
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
        return $this;
    }

    /**
     * Выполняет все правила, которые включены и удовлетворяют условиям срабатывания
     *
     * @return void
     */
    public function evaluateAll(): void
    {
        foreach ($this->rules as $rule) {
            if ($rule->isEnabled() && $rule->shouldTrigger()) {
                $rule->evaluate();
            }
        }
    }
}
