<?php
use AnswersTelegram\ButtonsTelegram;
use Models\StatusMaker;
use Models\FileWorker;

require_once "vendor/autoload.php";
require_once "env.php";

try {
    $bot = new \TelegramBot\Api\Client(TOKEN_TELEGRAM);
    $botApi = new \TelegramBot\Api\BotApi(TOKEN_TELEGRAM);

    $bot->command('start', function ($message) use ($botApi) {
        ButtonsTelegram::getBase($botApi, $message->getChat()->getId());
    });

    $bot->on(function (\TelegramBot\Api\Types\Update $update) use ($botApi) {
        $message = $update->getMessage();
        if (is_null($message)) return;
        $document = $message->getDocument();
        switch ($message->getText()){
            /*case "Файлы для склеивания":
                StatusMaker::insertOrUpdate($message->getChat()->getId(), "awaiting csv");
                ButtonsTelegram::getButtonsFinishCsv($botApi, $message->getChat()->getId());
                break;
            case "Файл тинькофф":
                StatusMaker::insertOrUpdate($message->getChat()->getId(), "awaiting tin");
                ButtonsTelegram::getButtonsForTinkoff($botApi, $message->getChat()->getId());
                break;*/
            case "Сгенерить":
		$status = StatusMaker::getStatus($message->getChat()->getId());
                if($status === 'final' || empty($status)){
                    $botApi->sendMessage($message->getChat()->getId(), 'Вы не загрузили файлы');
                    break;
                }
                $botApi->sendMessage($message->getChat()->getId(), 'Генерация началась');
                $result = FileWorker::generateReport($message->getChat()->getId());
                if($result['sum'] === 0){
                    if($result['result'] === ''){
                        $botApi->sendMessage($message->getChat()->getId(), 'Все совпадает');
                    } else {
                        $botApi->sendMessage($message->getChat()->getId(), $result['result']);
                    }
                } else {
                    $lengthText = iconv_strlen($result['result']);
                    $pullMessages = [];

                    if($lengthText >= 4096) {
                        while (iconv_strlen($result['result']) >= 4096) {
                            $preparePull = mb_substr($result['result'], 0, 4094);

                            if (preg_match('/(\\n)$/', $preparePull) === 1) {
                                $pullMessages[] = $preparePull;
                            } else {
                                $text = preg_replace('/([а-яА-Я .0-9-]+)$/', "", $preparePull);
                                $lengthText = iconv_strlen($text);
                                $result['result'] = mb_substr($result['result'], $lengthText);
                                $pullMessages[] = $text;
                            }
                        }
                    }
                    $pullMessages[] = $result['result'];
                    foreach ($pullMessages as $value){
                        $botApi->sendMessage($message->getChat()->getId(), $value);
                    }
                }

                $documentCsv = new \CURLFile('Storage/' . $message->getChat()->getId() . '/output/output.csv');
                $botApi->sendDocument($message->getChat()->getId(), $documentCsv);

                $documentTin = new \CURLFile('Storage/' . $message->getChat()->getId() . '/output/output_tin.csv');
                $botApi->sendDocument($message->getChat()->getId(), $documentTin);

                StatusMaker::insertOrUpdate($message->getChat()->getId(), "final");
                FileWorker::deleteFiles($message->getChat()->getId());
                break;
            case "Главное меню":
                ButtonsTelegram::getBase($botApi, $message->getChat()->getId());
                break;
        }

        if(!empty($document)){
            StatusMaker::insertOrUpdate($message->getChat()->getId(), "awaiting");
            $isSave = FileWorker::saveFile($botApi, $message->getChat()->getId(), $document);
            if($isSave){
                $botApi->sendMessage($message->getChat()->getId(), 'Файл загружен');
            } else {
                $botApi->sendMessage($message->getChat()->getId(), 'Файл не загружен, попробуйте снова или обратитесь в тех. поддержку');
            }
        }

    }, function () {
        return true;
    });

    $bot->run();

} catch (\TelegramBot\Api\Exception $e) {
    file_put_contents('log.txt', $e->getMessage());
}
