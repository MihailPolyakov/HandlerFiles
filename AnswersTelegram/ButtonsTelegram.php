<?php


namespace AnswersTelegram;


use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;

class ButtonsTelegram
{

    public static function getBase(BotApi $botApi, string $chatId): void
    {
        $keybord = new ReplyKeyboardMarkup([
            /*["Файлы для склеивания"],
            ["Файл тинькофф"],*/
            ["Сгенерить"]
        ],
            null,
            true);
        $botApi->sendMessage(
                $chatId,
            'Выберете действие',
        null,
    false,
    null,
            $keybord
        );
    }

    public static function getButtonsFinishCsv(BotApi $botApi, string $chatId): void
    {
        $keybord = new ReplyKeyboardMarkup([
            ["Главное меню"]
        ],
        null,
        true
        );
        $botApi->sendMessage(
            $chatId,
            'Загрузите файлы по одному',
            null,
            false,
            null,
            $keybord
        );
    }

    public static function getButtonsForTinkoff(BotApi $botApi, string $chatId): void
    {
        $keybord = new ReplyKeyboardMarkup([
            ["Главное меню"]
        ],
null,
true
        );
        $botApi->sendMessage(
            $chatId,
            'Загрузите файл',
            null,
            false,
            null,
            $keybord
        );
    }
}