<?php

declare(strict_types=1);

namespace app\repository\Base;

/**
 * Интерфейс репозитория, предоставляющий основные методы для работы с сущностями
 */
interface RepositoryInterface
{
    /**
     * Находит сущность по её идентификатору
     *
     * @param int $id Идентификатор сущности
     * @return mixed Сущность или null, если не найдена
     */
    public function findById(int $id): mixed;

    /**
     * Находит одну сущность по заданному условию
     *
     * @param array $condition Условие для поиска
     * @return mixed Сущность или null, если не найдена
     */
    public function findOne(array $condition): mixed;

    /**
     * Находит все сущности, соответствующие заданному условию
     *
     * @param array $condition Условие для поиска
     * @return array Массив сущностей
     */
    public function findAll(array $condition): array;
}
