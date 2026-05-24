<?php

namespace Bit\Mcp\Tools;

use Bitrix\Main\UserTable;
use CUser;
use PhpMcp\Server\Attributes\McpTool;

class UserTool
{
    #[McpTool(
        name: 'users_get_list',
        description: 'Список пользователей Bitrix24. search — поиск по имени, фамилии, email или логину. departmentId — фильтр по ID отдела. onlyActive — только активные (по умолчанию true).'
    )]
    public function getList(
        int    $limit = 50,
        string $search = '',
        int    $departmentId = 0,
        bool   $onlyActive = true
    ): array {
        $filter = [];

        if ($onlyActive) {
            $filter['=ACTIVE'] = 'Y';
        }

        if ($search !== '') {
            $filter[] = [
                'LOGIC'      => 'OR',
                '%NAME'      => $search,
                '%LAST_NAME' => $search,
                '%EMAIL'     => $search,
                '%LOGIN'     => $search,
            ];
        }

        if ($departmentId > 0) {
            $filter['=UF_DEPARTMENT'] = $departmentId;
        }

        $result = UserTable::getList([
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL', 'ACTIVE', 'WORK_POSITION', 'UF_DEPARTMENT'],
            'filter' => $filter,
            'order'  => ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'],
            'limit'  => $limit,
        ]);

        return $result->fetchAll();
    }

    #[McpTool(
        name: 'users_get_user',
        description: 'Получить пользователя Bitrix24 по ID. Возвращает все основные поля: логин, имя, email, отдел, должность и т.д.'
    )]
    public function getUser(int $id): array
    {
        $result = UserTable::getList([
            'select' => [
                'ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME',
                'EMAIL', 'ACTIVE', 'WORK_POSITION', 'UF_DEPARTMENT',
                'XML_ID', 'DATE_REGISTER',
            ],
            'filter' => ['=ID' => $id],
            'limit'  => 1,
        ]);

        $user = $result->fetch();
        if (!$user) {
            return ['error' => "Пользователь #$id не найден"];
        }

        return $user;
    }

    #[McpTool(
        name: 'users_create',
        description: 'Создать пользователя Bitrix24. fields — JSON-объект с полями пользователя. Обязательные: LOGIN, PASSWORD, EMAIL. Необязательные: NAME, LAST_NAME, SECOND_NAME, ACTIVE (Y/N, по умолчанию Y), WORK_POSITION, GROUP_ID (массив ID групп), UF_DEPARTMENT (массив ID отделов). Пример fields: {"LOGIN":"ivanov","PASSWORD":"Secret123!","EMAIL":"ivanov@company.ru","NAME":"Иван","LAST_NAME":"Иванов","WORK_POSITION":"Менеджер","ACTIVE":"Y"}'
    )]
    public function createUser(string $fields): array
    {
        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $data['ACTIVE'] ??= 'Y';

        $user = new CUser();
        $id   = $user->Add($data);

        if (!$id) {
            return ['error' => $user->LAST_ERROR ?: 'Неизвестная ошибка при создании пользователя'];
        }

        return ['success' => true, 'id' => (int)$id];
    }

    #[McpTool(
        name: 'users_update',
        description: 'Обновить данные пользователя Bitrix24 по ID. fields — JSON-объект только с изменяемыми полями. Пример fields: {"NAME":"Иван","LAST_NAME":"Петров","WORK_POSITION":"Старший менеджер","ACTIVE":"Y","UF_DEPARTMENT":[5]}'
    )]
    public function updateUser(int $id, string $fields): array
    {
        $data = json_decode($fields, true);
        if (!is_array($data)) {
            return ['error' => 'Параметр fields должен быть валидным JSON-объектом'];
        }

        $user   = new CUser();
        $result = $user->Update($id, $data);

        if (!$result) {
            return ['error' => $user->LAST_ERROR ?: 'Неизвестная ошибка при обновлении пользователя'];
        }

        return ['success' => true, 'id' => $id];
    }
}
