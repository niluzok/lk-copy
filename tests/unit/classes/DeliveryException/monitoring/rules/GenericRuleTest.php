<?php

declare(strict_types=1);

namespace tests\unit\classes\DeliveryException\monitoring\rules;

use Yii;
use Exception;
use Base\Unit;
use Codeception\Specify;
use Codeception\Stub\Expected;

use app\classes\DeliveryException\monitoring\rules\GenericRule;
use app\classes\DeliveryException\monitoring\interfaces\ConditionInterface;
use app\classes\DeliveryException\monitoring\interfaces\ActionInterface;

/**
 * Тесты для класса GenericRule
 */
class GenericRuleTest extends Unit
{
    use Specify;

    /**
     * @var GenericRule
     */
    protected GenericRule $rule;

    protected function _before()
    {
        $this->rule = new GenericRule();
    }

    // Условие, которое вернёт true
    protected function conditionMockTrue1()
    {
        return $this->makeEmpty(ConditionInterface::class, [
            'check' => Expected::once(true),
        ]);
    }

    // Второе условие, которое вернёт true
    protected function conditionMockTrue2()
    {
        return $this->makeEmpty(ConditionInterface::class, [
            'check' => Expected::once(true),
        ]);
    }

    // Условие, которое вернёт false
    protected function conditionMockFalse()
    {
        return $this->makeEmpty(ConditionInterface::class, [
            'check' => Expected::once(false),
        ]);
    }

    // Условие, которое не должно быть вызвано, так как предыдущее вернуло false
    protected function conditionMockNever()
    {
        return $this->makeEmpty(ConditionInterface::class, [
            'check' => Expected::never(),
        ]);
    }

    /**
     * Тест метода isEnabled с несколькими условиями
     *
     * @return void
     */
    public function testMethodIsEnabled(): void
    {
        $this->specify('Когда все условия истинны, isEnabled должно вернуть true', function () {
            $this->rule->addEnableConditions([
                $this->conditionMockTrue1(),
                $this->conditionMockTrue2(),
            ]);

            expect($this->rule->isEnabled())->true();
        });

        $this->specify('Когда одно из условий ложно, isEnabled должно вернуть false, а последующие условия не должны проверяться', function () {
            $this->rule->addEnableConditions([
                $this->conditionMockTrue1(),
                $this->conditionMockFalse(),
                $this->conditionMockNever(),
            ]);

            expect($this->rule->isEnabled())->false();
        });
    }

    /**
     * Тест метода shouldTrigger с несколькими условиями
     *
     * @return void
     */
    public function testMethodShouldTrigger(): void
    {
        $this->specify('Когда все условия срабатывания истинны, shouldTrigger должно вернуть true', function () {
            $this->rule->addTriggerConditions([
                $this->conditionMockTrue1(),
                $this->conditionMockTrue2(),
            ]);

            expect($this->rule->shouldTrigger())->true();
        });

        $this->specify('Когда одно из условий ложно, shouldTrigger должно вернуть false, а последующие условия не должны проверяться', function () {
            $this->rule->addTriggerConditions([
                $this->conditionMockTrue1(),
                $this->conditionMockFalse(),
                $this->conditionMockNever(),
            ]);

            expect($this->rule->shouldTrigger())->false();
        });
    }

    /**
     * Тест метода evaluate
     *
     * @return void
     */
    public function testMethodEvaluate(): void
    {
        $this->specify('Когда правило включено и должно сработать, действия должны выполниться в рамках транзакции', function () {
            $actionMock = $this->makeEmpty(ActionInterface::class, [
                'run' => Expected::once(),
            ]);

            $this->rule->addEnableCondition($this->conditionMockTrue1());
            $this->rule->addTriggerCondition($this->conditionMockTrue2());
            $this->rule->addAction($actionMock);

            $this->mockYiiTransaction([
                'rollBack' => Expected::never(),
                'commit' => Expected::once(),
            ]);

            $this->rule->evaluate();
        });
    }

    /**
     * Тест метода evaluate с ошибкой и откатом транзакции
     *
     * @return void
     */
    public function testMethodEvaluateWithException(): void
    {
        $this->specify('Когда действие выбрасывает исключение, транзакция должна быть откатана', function () {
            $actionMock = $this->makeEmpty(ActionInterface::class, [
                'run' => Expected::once(function () {
                    throw new Exception("Test exception");
                }),
            ]);

            $this->rule->addEnableConditions([
                $this->conditionMockTrue1(),
                $this->conditionMockTrue2(),
            ]);

            $this->rule->addAction($actionMock);

            $this->mockYiiTransaction([
                'rollBack' => Expected::once(),
                'commit' => Expected::never(),
            ]);

            try {
                $this->rule->evaluate();
            } catch (Exception $e) {
                expect($e->getMessage())->equals('Test exception');
            }
        });

        $this->specify('Когда условие включения ложно, действие не должно выполняться', function () {
            $actionMock = $this->makeEmpty(ActionInterface::class, [
                'run' => Expected::never(),
            ]);

            $this->rule->addEnableConditions([
                $this->conditionMockFalse(),
            ]);

            $this->rule->addAction($actionMock);

            $this->rule->evaluate();
        });

        $this->specify('Когда условие срабатывания ложно, действие не должно выполняться', function () {
            $actionMock = $this->makeEmpty(ActionInterface::class, [
                'run' => Expected::never(),
            ]);

            $this->rule->addEnableConditions([
                $this->conditionMockTrue1(),
            ]);

            $this->rule->addTriggerConditions([
                $this->conditionMockFalse(),
            ]);

            $this->rule->addAction($actionMock);

            $this->rule->evaluate();
        });
    }
}
