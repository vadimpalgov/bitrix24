<?php

declare(strict_types=1);

namespace Parfeon\Er\Tests\Support;

/**
 * Гибкий fake для Bitrix\Crm\Item.
 * Строится через fluent-методы: FakeItem::create(...)->withStage(...)->withField(...).
 */
class FakeItem extends \Bitrix\Crm\Item
{
    private function __construct(int $id, int $entityTypeId)
    {
        $this->fields['ID']             = $id;
        $this->fields['ENTITY_TYPE_ID'] = $entityTypeId;
    }

    public static function create(int $id, int $entityTypeId): static
    {
        return new static($id, $entityTypeId);
    }

    /** Устанавливает стадию; $changed = true имитирует isChangedStageId() */
    public function withStage(string $stage, bool $changed = false): static
    {
        $this->stageId      = $stage;
        $this->stageChanged = $changed;
        return $this;
    }

    public function withField(string $field, mixed $value): static
    {
        $this->fields[$field] = $value;
        return $this;
    }

    public function isChangedStageId(): bool { return $this->stageChanged; }

    public function get(string $field): mixed
    {
        if ($field === 'ID') return $this->getId();
        return $this->fields[$field] ?? null;
    }
}
