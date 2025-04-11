<?php

declare(strict_types=1);

namespace app\repository\Base;

use app\repository\Base\RepositoryInterface;
use yii\db\ActiveRecord;

/**
 * @template T of ActiveRecord
 */
abstract class AbstractARRepository implements RepositoryInterface
{
    /**
     * @return class-string<T>
     */
    abstract protected function getModelClass(): string;

    /**
     * @param int $id
     * @return T|null
     */
    public function findById(int $id): ?ActiveRecord
    {
        $modelClass = $this->getModelClass();
        return $modelClass::findOne($id);
    }

    /**
     * @param array $config
     * @return T|null
     */
    public function findOne(array $config = []): ?ActiveRecord
    {
        $modelClass = $this->getModelClass();
        return $modelClass::findOne($config);
    }

    /**
     * @param array $config
     * @return T[]
     */
    public function findAll(array $config = []): array
    {
        $modelClass = $this->getModelClass();
        return $modelClass::findAll($config);
    }

    /**
     * @param array $config
     * @return T|null
     */
    public function create(array $config): ?ActiveRecord
    {
        $modelClass = $this->getModelClass();
        $model = new $modelClass($config);

        if (!$model->save()) {
            throw new \RuntimeException('Не удалось сохранить ' . $modelClass . ': ' . print_r($model->getErrors(), true));
        }
        return $model;
    }

    /**
     * Удаляет запись по её идентификатору
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $modelClass = $this->getModelClass();
        $model = $modelClass::findOne($id);

        return $model !== null ? $model->delete() !== false : false;
    }
}
