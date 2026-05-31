<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Support;

use Bitrix\Main\Result;

/**
 * Fake CRM-фабрика. Хранит FakeItem-ы, реализует getItems с фильтрацией,
 * getItem, getUpdateOperation. Позволяет проверять какие элементы были обновлены.
 */
class FakeFactory
{
    /** @var FakeItem[] */
    private array $items = [];

    /** @var FakeItem[] обновлённые элементы (через getUpdateOperation → launch) */
    private array $updates = [];

    public function addItem(FakeItem $item): void
    {
        $this->items[$item->getId()] = $item;
    }

    public function getItem(int $id): ?FakeItem
    {
        return $this->items[$id] ?? null;
    }

    /**
     * Поддерживает фильтр в формате Bitrix:
     *   '=FIELD'  => value   — равенство
     *   '!=FIELD' => value   — неравенство
     */
    public function getItems(array $params): array
    {
        $filter = $params['filter'] ?? [];

        return array_values(array_filter(
            $this->items,
            fn(FakeItem $item) => $this->matchesFilter($item, $filter),
        ));
    }

    public function getUpdateOperation(FakeItem $item): FakeOperation
    {
        return new FakeOperation($this, $item);
    }

    /** Вызывается из FakeOperation::launch() */
    public function recordUpdate(FakeItem $item): void
    {
        $this->updates[$item->getId()] = $item;
    }

    public function getUpdatedItem(int $id): ?FakeItem
    {
        return $this->updates[$id] ?? null;
    }

    /** @return FakeItem[] */
    public function getUpdates(): array
    {
        return $this->updates;
    }

    // ── фильтрация ────────────────────────────────────────────────────────────

    private function matchesFilter(FakeItem $item, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            [$op, $field] = $this->parseFilterKey((string)$key);
            $actual = $item->get($field);

            $matches = match ($op) {
                '!='    => $actual != $value,
                default => $actual == $value,
            };

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /** Возвращает [оператор, имя_поля] */
    private function parseFilterKey(string $key): array
    {
        if (str_starts_with($key, '!=')) {
            return ['!=', substr($key, 2)];
        }
        if (str_starts_with($key, '=')) {
            return ['=', substr($key, 1)];
        }
        return ['=', $key];
    }
}
