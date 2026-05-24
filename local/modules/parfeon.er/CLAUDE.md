# parfeon.er — модуль заявок сотрудников

Bitrix24-модуль управления заявками сотрудников с многоуровневым согласованием.  
Поддерживает несколько типов заявок (отпуск, отсутствие и др.) с индивидуальными планами согласования на каждого пользователя.  
Автор: Vadim Palgov (parfeon.dev). Версия: 0.1.0 (2026-01-17).

---

## Типы заявок

Модуль рассчитан на работу с несколькими типами заявок пользователей. На данный момент реализованы:

- **Заявка на отпуск** — с проверкой дат и записью в график отсутствий
- **Заявка на отсутствие** — без специфических проверок дат

Тип заявки хранится в поле `UF_CRM_10_TYPE` (привязка к элементу инфоблока-справочника). Логика обработки общая для всех типов; тип-специфичные проверки (даты отпуска) управляются настройками модуля.

---

## Архитектура

Модуль работает поверх трёх CRM Смарт-процессов (динамических типов), настраиваемых в admin-панели:

| Сущность | Код настройки | Описание |
|---|---|---|
| **EmployeeRequest (ER)** | `ER_SMART_PROCESS_ID` | Заявка сотрудника (основная) |
| **Approvers (AP)** | `AP_SMART_PROCESS_ID` | Задача согласующего (дочерняя) |
| **ApprovalProfile (ALP)** | `ALP_SMART_PROCESS_ID` | Профиль согласования (справочник) |

Пространство имён: `Parfeon\Er`.

---

## Структура файлов

```
employee.requests/
├── include.php                         # Регистрирует Container в ServiceLocator
├── options.php                         # Страница настроек в админке
├── .settings.php                       # Регистрирует сервисы (CreateApprovers, NotifyService)
├── install/
│   ├── index.php                       # Класс employee_requests (установка/удаление)
│   └── version.php
├── lang/ru/install/index.php
└── lib/
    ├── Container.php                   # Кастомный DI-контейнер CRM
    ├── Factory/
    │   ├── EmployeeRequestFactory.php  # Фабрика для ER
    │   └── ApproversFactory.php        # Фабрика для AP
    ├── Mapping/
    │   ├── EmployeeRequest.php         # Константы полей UF_CRM_10_*
    │   └── Approvers.php              # Константы полей UF_CRM_11_*
    ├── Operation/
    │   ├── EmployeeRequest/            # Actions для ER
    │   │   ├── CreateName.php
    │   │   ├── DayCountChecker.php
    │   │   ├── DayStartChecker.php
    │   │   ├── DepartmentManagerResolver.php
    │   │   ├── HRManagersResolver.php
    │   │   ├── ApproverResolver.php
    │   │   ├── ChangeStatus.php
    │   │   └── AddingAbsencesSchedule.php
    │   └── Approvers/                  # Actions для AP
    │       ├── ChangeStatus.php
    │       └── SendNotify.php
    └── Services/
        ├── CreateApprovers.php         # Создание AP-элементов
        └── NotifyService.php           # Отправка IM-уведомлений
```

---

## Компоненты

### Container (`lib/Container.php`)

Расширяет `Bitrix\Crm\Service\Container`. Переопределяет `getFactory()`: читает из настроек модуля ID смарт-процессов и подставляет кастомные фабрики вместо стандартных. Регистрируется в `include.php` под ключом `crm.service.container`.

```php
// Маппинг: ключ настройки → класс фабрики
'ER_SMART_PROCESS_ID' => EmployeeRequestFactory::class
'AP_SMART_PROCESS_ID' => ApproversFactory::class
```

### EmployeeRequestFactory (`lib/Factory/EmployeeRequestFactory.php`)

Фабрика ER-элементов. Добавляет actions в операции CRM:

**`getAddOperation`** (создание заявки):

| Момент | Action | Описание |
|---|---|---|
| BEFORE_SAVE | `CreateName` | Формирует заголовок «{Тип} от {Имя}» |
| BEFORE_SAVE | `DayCountChecker` * | Проверка минимальной длительности отпуска |
| BEFORE_SAVE | `DayStartChecker` * | Проверка срока подачи до начала отпуска |
| BEFORE_SAVE | `DepartmentManagerResolver` | Заполняет руководителей по иерархии отдела |
| BEFORE_SAVE | `HRManagersResolver` | Заполняет HR-менеджеров из подразделения «HR» |
| AFTER_SAVE | `ApproverResolver` | Создаёт AP-элементы для согласующих |

\* включается настройкой `LA_ENABLE_MIN_DAYS_CHECK = Y`

**`getUpdateOperation`** (обновление заявки):

- Всегда: `DayCountChecker` (если включён), `AddingAbsencesSchedule`
- Если стадия == `ER_START_STATUS`: повторно запускает `DepartmentManagerResolver`, `HRManagersResolver`, `ApproverResolver`
- Если стадия == `ER_APPROVE_STATUS` или `ER_REJECT_STATUS`: запускает `ChangeStatus` (уведомление сотруднику)

### ApproversFactory (`lib/Factory/ApproversFactory.php`)

Фабрика AP-элементов.

- `getAddOperation` → AFTER_SAVE: `SendNotify` — уведомляет согласующего
- `getUpdateOperation` → BEFORE_SAVE: `ChangeStatus` — синхронизирует статус родительской ER

---

## Смарт-процесс «Профили согласования» (ApprovalProfile)

> Проектируется. Код настройки: `ALP_SMART_PROCESS_ID`.

### Назначение

Профиль согласования — это запись-шаблон, которая описывает **какую стадию** нужно пройти согласующему при обработке заявки **конкретного типа**. Набор профилей для пользователя определяет его персональный план согласования.

### Поля элемента профиля

| Поле | Тип в Bitrix | Описание |
|---|---|---|
| **Тип согласования** | Привязка к элементам инфоблоков и смарт-процессов | Тип заявки, к которому относится профиль (например, «Заявка на отпуск»). Ссылается на элемент того же инфоблока-справочника, что и `UF_CRM_10_TYPE` у ER. |
| **Стадия согласования** | Привязка к справочникам CRM | Стадия смарт-процесса AP, которую согласующий должен выставить по данному профилю. Ссылается на стадию смарт-процесса Approvers. |

### Связь с пользователями

Каждому пользователю назначается свой профиль согласования **под каждый тип заявки** через пользовательское поле (`UF_*`) на сущности «Пользователь». Таким образом:

- для типа «Заявка на отпуск» у пользователя будет поле, ссылающееся на соответствующий ALP-элемент;
- для типа «Заявка на отсутствие» — другое поле с другим ALP-элементом;
- при добавлении нового типа заявок — добавляется новое пользовательское поле.

Это позволяет гибко назначать разные планы согласования разным сотрудникам без изменения общей логики модуля.

### Планируемое использование в коде

При создании AP-элементов (`Services\CreateApprovers`) для конкретного согласующего будет:
1. Определяться тип заявки из ER (`UF_CRM_10_TYPE`)
2. Читаться пользовательское поле согласующего, соответствующее данному типу
3. По полученному ID профиля загружаться ALP-элемент
4. Из поля «Стадия согласования» профиля извлекаться целевая стадия AP
5. AP-элемент создаваться с этой стадией (вместо единой начальной стадии)

---

## Operations / Actions

### EmployeeRequest\CreateName
Формирует `title` элемента: получает имя пользователя из `UserTable`, название типа через `NotifyService::getTypeName()`.  
Шаблон: `«{ТипЗаявки} от {Имя Фамилия}»`.

### EmployeeRequest\DayCountChecker
Проверяет: `(DATE_END - DATE_START) >= LA_MIN_DAYS` дней. Возвращает ошибку при нарушении.

### EmployeeRequest\DayStartChecker
Проверяет: `DATE_START >= сегодня + LA_MIN_DAYS_BEFORE_START` дней. Использует `ray()` для отладки (см. известные проблемы).

### EmployeeRequest\DepartmentManagerResolver
Определяет всех руководителей по цепочке иерархии подразделений:
1. Получает `UF_DEPARTMENT[0]` создателя заявки
2. Через `Bitrix\HumanResources` поднимается вверх по дереву узлов
3. Собирает `defaultHeadRole`-сотрудников всех узлов
4. Записывает в `UF_CRM_10_HEAD_OF_DEPARTMENT`

Требует модули `intranet`, `humanresources`.

### EmployeeRequest\HRManagersResolver
Ищет подразделение с именем `'HR'` через `CIBlockSection`, собирает всех активных пользователей этих подразделений, записывает в `UF_CRM_10_HR`.

### EmployeeRequest\ApproverResolver
Вызывает `Services\CreateApprovers::create($item)`. Точка входа в логику создания согласующих.

### EmployeeRequest\ChangeStatus
Срабатывает когда **стадия ER изменилась** на APPROVE/REJECT. Формирует текст уведомления и вызывает `NotifyService::send()` для `ASSIGNED_BY_ID` заявки.

### EmployeeRequest\AddingAbsencesSchedule
На стадии APPROVE создаёт элемент в инфоблоке `absence` (тип `structure`) — запись об отсутствии сотрудника с датами из заявки.

### Approvers\SendNotify
После создания AP-элемента отправляет уведомление согласующему (`ASSIGNED_BY_ID`) через `NotifyService`.

### Approvers\ChangeStatus
Когда **стадия AP изменилась**:

- **→ REJECT**: требует заполненного поля `REASON_FOR_REJECTION`, переводит родительскую ER в `ER_REJECT_STATUS`.
- **→ APPROVE**: проверяет все соседние AP-элементы (siblings) того же родителя:
  - Если любой в REJECT → переводит ER в REJECT
  - Если все **не-HR** согласующие одобрили **И** хотя бы один **HR** одобрил → переводит ER в APPROVE
  - Иначе — ждёт

> HR-подразделение сейчас захардкожено как ID=40 в методе `getHrDepartmentId()`.

---

## Services

### CreateApprovers (`lib/Services/CreateApprovers.php`)

Создаёт AP-элементы для согласующих. Список формируется из трёх источников ER-элемента:
1. `UF_CRM_10_PROJECT_MANAGERS` — руководители проекта (задаются вручную)
2. `UF_CRM_10_HEAD_OF_DEPARTMENT` — руководители по иерархии
3. `UF_CRM_10_HR` — HR-менеджеры

Для каждого `userId` проверяет существование AP-элемента (по `ASSIGNED_BY_ID` + `PARENT_ID`), при отсутствии создаёт. Данные копируются через `EmployeeRequest::MAPPING`.

### NotifyService (`lib/Services/NotifyService.php`)

Отправляет системные IM-уведомления через `CIMNotify::Add()`. Поддерживает URL-ссылку в тексте. Метод `getTypeName(int $elementId)` получает название типа заявки из инфоблока по ID элемента.

---

## Маппинг полей

### EmployeeRequest (CRM type 10, `UF_CRM_10_*`)

| Константа | Поле |
|---|---|
| `PROJECT_MANAGERS` | `UF_CRM_10_PROJECT_MANAGERS` |
| `HEAD_OF_DEPARTMENT` | `UF_CRM_10_HEAD_OF_DEPARTMENT` |
| `HR_MANAGERS` | `UF_CRM_10_HR` |
| `TYPE` | `UF_CRM_10_TYPE` |
| `DESCRIPTION` | `UF_CRM_10_DESCRIPTION` |
| `REASON_FOR_REJECTION` | `UF_CRM_10_REASON_FOR_REJECTION` |
| `DATE_START` | `UF_CRM_10_DATE_START` |
| `DATE_END` | `UF_CRM_10_DATE_END` |

`MAPPING` — используется для копирования TYPE, DESCRIPTION, DATE_START, DATE_END из ER в AP при создании.

### Approvers (CRM type 11, `UF_CRM_11_*`)

TYPE, DESCRIPTION, REASON_FOR_REJECTION, DATE_START, DATE_END.

### ApprovalProfile (CRM type N, `UF_CRM_N_*`) — проектируется

| Константа (будущая) | Поле | Тип Bitrix |
|---|---|---|
| `APPROVAL_TYPE` | `UF_CRM_N_TYPE` | Привязка к элементам инфоблоков и смарт-процессов |
| `APPROVAL_STAGE` | `UF_CRM_N_STAGE` | Привязка к справочникам CRM |

> `N` — номер CRM-типа, будет известен после создания смарт-процесса.

### Пользовательские поля на сущности «Пользователь» — проектируются

Под каждый тип заявок на пользователе создаётся отдельное UF-поле с привязкой к ALP-элементу.  
Именование конвенции: `UF_APPROVAL_PROFILE_{TYPE_CODE}` (уточняется при реализации).

---

## Настройки модуля (`options.php`)

Страница: **Настройки → employee.requests**.

### Вкладка «Настройки согласования»

| Ключ | Описание |
|---|---|
| `ER_SMART_PROCESS_ID` | ID смарт-процесса заявок |
| `ER_START_STATUS` | Начальная стадия ER (пересоздаёт согласующих) |
| `ER_APPROVE_STATUS` | Стадия одобрения ER |
| `ER_REJECT_STATUS` | Стадия отклонения ER |
| `AP_SMART_PROCESS_ID` | ID смарт-процесса согласующих |
| `AP_START_STATUS` | Начальная стадия AP |
| `AP_APPROVE_STATUS` | Стадия одобрения AP |
| `AP_REJECT_STATUS` | Стадия отклонения AP |
| `ALP_SMART_PROCESS_ID` | ID смарт-процесса профилей согласования *(проектируется)* |
| `HR_DEPARTMENT_ID` | ID подразделения HR-сотрудников (используется в логике «хотя бы один HR») *(не реализовано, сейчас захардкожено как 40)* |
| `LA_ADD_ALL_MANAGERS` | Включать всех руководителей по цепочке иерархии |
| `LA_EXCLUDE_DIRECTOR` | Исключить генерального директора |
| `LA_FORCE_DIRECTOR_IF_MANAGER` | Добавлять директора, если заявитель сам руководитель |

> `LA_ADD_ALL_MANAGERS`, `LA_EXCLUDE_DIRECTOR`, `LA_FORCE_DIRECTOR_IF_MANAGER` сохраняются, но логика их применения в `DepartmentManagerResolver` пока не реализована.

### Вкладка «Параметры отпуска»

| Ключ | Описание | По умолчанию |
|---|---|---|
| `LA_ENABLE_MIN_DAYS_CHECK` | Включить проверки дат | `N` |
| `LA_MIN_DAYS` | Минимальная длительность отпуска (дней) | `14` |
| `LA_MIN_DAYS_BEFORE_START` | За сколько дней до начала нужно подать заявку | `7` |

---

## Полный жизненный цикл заявки

```
1. Сотрудник создаёт ER-элемент
        ↓
2. BEFORE_SAVE: CreateName → автозаголовок
        ↓
3. BEFORE_SAVE: DayCountChecker / DayStartChecker (если включены)
        ↓
4. BEFORE_SAVE: DepartmentManagerResolver → HEAD_OF_DEPARTMENT
        ↓
5. BEFORE_SAVE: HRManagersResolver → HR_MANAGERS
        ↓
6. AFTER_SAVE: ApproverResolver → CreateApprovers.create()
        ↓
7. Для каждого согласующего: создаётся AP-элемент
        ↓
8. AFTER_SAVE (AP): SendNotify → IM-уведомление согласующему
        ↓
9. Согласующий переводит свой AP в APPROVE или REJECT
        ↓
10. BEFORE_SAVE (AP): ChangeStatus (AP)
    ├── REJECT: ER → ER_REJECT_STATUS
    └── APPROVE: если все не-HR одобрили И хотя бы один HR одобрил → ER → ER_APPROVE_STATUS
        ↓
11. BEFORE_SAVE (ER): ChangeStatus (ER) → IM-уведомление сотруднику
        ↓
12. AFTER_SAVE (ER): AddingAbsencesSchedule → запись в инфоблок 'absence'
```

---

## Известные проблемы

- **`ray()` в продакшне**: отладочные вызовы `ray()` присутствуют в `DepartmentManagerResolver`, `DayStartChecker`, `HRManagersResolver`, `CreateApprovers`, `Approvers\ChangeStatus`. Необходимо удалить перед релизом.
- **Захардкоженный ID HR-отдела**: в `Approvers\ChangeStatus::getHrDepartmentId()` возвращается константа `40`. Нужно добавить настройку `HR_DEPARTMENT_ID` в `options.php` и читать значение через `Option::get()`.
- **Дублирующийся код в `CreateApprovers::getUserIds()`**: после цикла по `$headOfDepartments` есть устаревший блок `if ($headOfDepartment)` с необъявленной переменной — dead code, вызывает PHP notice.
- **Настройки иерархии не применяются**: `LA_ADD_ALL_MANAGERS`, `LA_EXCLUDE_DIRECTOR`, `LA_FORCE_DIRECTOR_IF_MANAGER` сохраняются, но не используются в `DepartmentManagerResolver`.
- **`ApproversFactory::getItems()`**: закомментированные строки с `ray()` — мусор.
