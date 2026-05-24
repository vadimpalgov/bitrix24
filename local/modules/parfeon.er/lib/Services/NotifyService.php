<?php
namespace Parfeon\Er\Services;

use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use CIMNotify;

class NotifyService
{
    public function send(int $userId, array $params): Result
    {
        $result = new Result();

        if (!Loader::includeModule('im')) {
            return $result->addError(new Error('Модуль мессенджера не установлен.'));
        }

        if ($userId <= 0) {
            return $result->addError(new Error('Не указан ID получателя.'));
        }

        $requestId = (int)($params['requestId'] ?? 0);
        $message = $params['message'] ?? 'Новое системное уведомление';
        $tag = $params['tag'] ?? "REQ|" . $requestId;
        $url = $params['url'] ?? "";

        $arMessageFields = [
            "MESSAGE_TYPE"      => IM_MESSAGE_SYSTEM,
            "TO_USER_ID"        => $userId,
            "FROM_USER_ID"      => (int)($params['fromUserId'] ?? 0),
            "NOTIFY_TYPE"       => IM_NOTIFY_SYSTEM,
            "NOTIFY_MODULE"     => "parfeon.er",
            "NOTIFY_EVENT"      => $params['event'] ?? "default_notify",
            "NOTIFY_TAG"        => $tag,
            "NOTIFY_MESSAGE"    => $this->prepareMessage($message, $url),
            "NOTIFY_MESSAGE_OUT" => $message . ($url ? ": " . $url : ""),
        ];

        // Добавляем дополнительные кнопки или кастомные данные, если они есть
        if (!empty($params['buttons'])) {
            $arMessageFields["NOTIFY_BUTTONS"] = $params['buttons'];
        }

        $notificationId = CIMNotify::Add($arMessageFields);

        if (!$notificationId) {
            return $result->addError(new Error('Ошибка при отправке уведомления через CIMNotify.'));
        }

        $result->setData(['NOTIFICATION_ID' => $notificationId]);

        return $result;
    }

    /**
     * Обертка для формирования текста с ссылкой
     */
    private function prepareMessage(string $text, string $url): string
    {
        if (empty($url)) {
            return $text;
        }
        return $text . " [URL={$url}]Открыть[/URL]";
    }

    /**
     * Метод-хелпер для получения названия сущности (ваш старый функционал)
     * Теперь его можно вызвать перед отправкой, чтобы сформировать message
     */
    public function getTypeName(int $elementId): string
    {
        if ($elementId <= 0 || !Loader::includeModule('iblock')) {
            return 'заявка';
        }

        $res = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['NAME'])->Fetch();
        return $res ? $res['NAME'] : 'заявка';
    }
}