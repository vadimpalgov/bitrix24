<?php

declare(strict_types=1);

namespace {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
}

// ── Bitrix\Main ───────────────────────────────────────────────────────────────

namespace Bitrix\Main {

    class Error
    {
        public function __construct(private string $message, private string $code = '0') {}
        public function getMessage(): string { return $this->message; }
        public function getCode(): string    { return $this->code; }
    }

    class Result
    {
        private array $errors = [];

        public function isSuccess(): bool { return empty($this->errors); }

        public function addError(Error $error): static
        {
            $this->errors[] = $error;
            return $this;
        }

        public function getErrors(): array { return $this->errors; }

        public function getErrorMessages(): array
        {
            return array_map(fn(Error $e) => $e->getMessage(), $this->errors);
        }
    }
}

// ── Bitrix\Main\Config ────────────────────────────────────────────────────────

namespace Bitrix\Main\Config {

    class Option
    {
        private static array $store = [];

        public static function get(string $module, string $name, string $default = ''): string
        {
            return self::$store[$module][$name] ?? $default;
        }

        public static function set(string $module, string $name, string $value): void
        {
            self::$store[$module][$name] = $value;
        }

        public static function reset(): void { self::$store = []; }
    }
}

// ── Bitrix\Main\DI ────────────────────────────────────────────────────────────

namespace Bitrix\Main\DI {

    class ServiceLocator
    {
        private static ?self $instance = null;
        private array $services = [];

        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }

        public function get(string $id): mixed
        {
            return $this->services[$id] ?? null;
        }

        public function has(string $id): bool
        {
            return isset($this->services[$id]);
        }

        public function addInstance(string $id, mixed $service): void
        {
            $this->services[$id] = $service;
        }

        public static function reset(): void { self::$instance = null; }
    }
}

// ── Bitrix\Crm ────────────────────────────────────────────────────────────────

namespace Bitrix\Crm {

    class Item
    {
        protected array  $fields       = [];
        protected string $stageId      = '';
        protected bool   $stageChanged = false;

        public function getId(): int              { return (int)($this->fields['ID'] ?? 0); }
        public function getEntityTypeId(): int    { return (int)($this->fields['ENTITY_TYPE_ID'] ?? 0); }
        public function getStageId(): string      { return $this->stageId; }
        public function isChangedStageId(): bool  { return $this->stageChanged; }
        public function getAssignedById(): mixed  { return $this->fields['ASSIGNED_BY_ID'] ?? null; }
        public function getCreatedBy(): mixed     { return $this->fields['CREATED_BY'] ?? null; }
        public function hasField(string $f): bool { return array_key_exists($f, $this->fields); }

        public function setStageId(string $stage): static
        {
            $this->stageId      = $stage;
            $this->stageChanged = true;
            return $this;
        }

        public function get(string $field): mixed
        {
            if ($field === 'ID') return $this->getId();
            return $this->fields[$field] ?? null;
        }

        public function set(string $field, mixed $value): static
        {
            $this->fields[$field] = $value;
            return $this;
        }
    }
}

// ── Bitrix\Crm\Service ────────────────────────────────────────────────────────

namespace Bitrix\Crm\Service {

    class Container
    {
        private static ?self $instance  = null;
        private static array $factories = [];

        public static function getInstance(): static
        {
            return self::$instance ??= new static();
        }

        public function getFactory(int $entityTypeId): ?object
        {
            return self::$factories[$entityTypeId] ?? null;
        }

        public static function setFactory(int $entityTypeId, object $factory): void
        {
            self::$factories[$entityTypeId] = $factory;
        }

        public static function reset(): void
        {
            self::$instance  = null;
            self::$factories = [];
        }

        public function getTypeByEntityTypeId(int $entityTypeId): mixed { return null; }

        public static function getIdentifierByClassName(string $class, array $args = []): string
        {
            return $class . implode('_', $args);
        }
    }
}

// ── Bitrix\Crm\Service\Operation ─────────────────────────────────────────────

namespace Bitrix\Crm\Service\Operation {

    abstract class Action
    {
        abstract public function process(\Bitrix\Crm\Item $item): \Bitrix\Main\Result;
    }
}

// ── Bitrix\Crm\Service\Factory ────────────────────────────────────────────────

namespace Bitrix\Crm\Service\Factory {

    class Dynamic {}
}
