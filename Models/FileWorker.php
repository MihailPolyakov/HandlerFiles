<?php


namespace Models;


use Spatie\PdfToText\Pdf;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;
use TelegramBot\Api\Types\Document;

class FileWorker
{
    public static function saveFile(BotApi $botApi, string $chatId, Document $document): bool
    {
        try {
            $mimeType = $document->getMimeType();
            $fileId = $document->getFileId();
            $fileName = $document->getFileName();

            $folderFiles = null;
            $folderUser = $chatId;
            if(stripos($mimeType, 'csv') || stripos($mimeType, 'comma-separated-values')){
                $folderFiles = "csv";
            } elseif (stripos($mimeType, "pdf")){
                $folderFiles = "tin";
            } else {
                return false;
            }

            $pathFolderUser = __DIR__ . "/../Storage/" . $folderUser;
            $pathFolderFiles = __DIR__ . "/../Storage/" . $folderUser . "/" . $folderFiles;
            $folderUser = file_exists($pathFolderUser);
            if(!$folderUser) mkdir($pathFolderUser);
            $folderFiles = file_exists($pathFolderFiles);
            if(!$folderFiles) mkdir($pathFolderFiles);

            $dataFile = $botApi->downloadFile($fileId);

            if($folderFiles === "tin"){
                $dataFile = base64_decode($dataFile);
            }

            if(is_bool(file_put_contents($pathFolderFiles . "/" . $fileName, $dataFile))){
                file_put_contents(__DIR__ . "/../log.txt", $dataFile);
                return false;
            } else {
                return true;
            }
        } catch (Exception $e){
            return false;
        }

    }

    public static function deleteFiles(string $chat_id): void
    {
        self::deleteDir(__DIR__ . "/../Storage/" . $chat_id);
    }

    private static function deleteDir(string $dir): void
    {
        if(is_file($dir)){
            unlink($dir);
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file){
            if($file === "." || $file === "..") continue;
            self::deleteDir($dir . "/" . $file);
        }
        rmdir($dir);
    }

    public static function generateReport(string $chat_id): array
    {
        $folderPathCsv = __DIR__ . "/../Storage/" . $chat_id . "/csv";
        $folderPathTin = __DIR__ . "/../Storage/" . $chat_id . "/tin";
        $folderOutputFiles = __DIR__ . "/../Storage/" . $chat_id . "/output";
        mkdir($folderOutputFiles);

        $filesCsv = scandir($folderPathCsv);
        $fileTxt = scandir($folderPathTin)[2];

        $arrayDataCsv = [];
        $headerCsv = false;
        $outputCsv = fopen($folderOutputFiles . "/output.csv", "w");
        $outputTin = fopen($folderOutputFiles . "/output_tin.csv", "w");

        foreach ($filesCsv as $file){
            if($file === "." || $file === "..") continue;

            $fopen = fopen($folderPathCsv . "/" . $file, 'r');

            if(!$headerCsv){
                $header = fgetcsv($fopen);
                $header = [$header[2], $header[3]];
                fputcsv($outputCsv, $header);
                $headerCsv = true;
            } else {
                fgetcsv($fopen);
            }

            while (($array = fgetcsv($fopen)) != false){
                $dateArray = date_parse($array[2] . " +3 hour");
                $sum = round((float) $array[3], 2);

                $day = $dateArray['day'] >= 10 ? (string) $dateArray['day'] : "0" . (string) $dateArray['day'];
                $month = $dateArray['month'] >= 10 ? (string) $dateArray['month'] : "0" . (string) $dateArray['month'];
                $year = (string) $dateArray['year'];
                $dateString =  $day . "." . $month . "." . $year;
                fputcsv($outputCsv, [$dateString, $array[3]]);
                if(empty($arrayDataCsv[$dateString])) $arrayDataCsv[$dateString] = [];

                $arrayDataCsv[$dateString][] = $sum;
            }

            fclose($fopen);
        }

        fclose($outputCsv);


        //$fileTxt = file_get_contents($folderPathTin . "/" . $fileTxt);
        $fileTxt = Pdf::getText($folderPathTin . "/" . $fileTxt);
        preg_match_all('/\d{2}[.]\d{2}[.]\d{4}\s{1,}\d{2}:\d{2}/', $fileTxt, $dateMatch);
        preg_match_all('/\d{1,}\s?\d{0,},\d{2}/', $fileTxt, $sumMatch);
        $dates = $dateMatch[0];
        $sums = array_slice($sumMatch[0], 3);

        $arrayOutput = [
            'sum' => 0,
            'result' => ""
        ];

        fputcsv($outputTin, ["Дата", "Сумма"]);
        for($item = 1; $item < count($dates); $item += 2){
            preg_match('/\d{2}[.]\d{2}[.]\d{4}/', $dates[$item], $match);
            $date = $match[0];
            $sum = (float) preg_replace('/,/', ".", preg_replace('/\s/', '', $sums[$item]));

            fputcsv($outputTin, [$dates[$item], $sum]);

            $text = $date . " - " . $sum . " руб. Лишнее зачисление\n";
            if(empty($arrayDataCsv[$date])){
                $arrayOutput['result'] .= $text;
                $arrayOutput['sum'] += $sum;
                continue;
            }

            $indexSum = array_search($sum, $arrayDataCsv[$date]);
            if(is_bool($indexSum)){
                $arrayOutput['result'] .= $text;
                $arrayOutput['sum'] += $sum;
                continue;
            } else {
                unset($arrayDataCsv[$date][$indexSum]);
            }
        }

        fclose($outputTin);

        foreach ($arrayDataCsv as $key => $value){
            foreach ($value as $sum){
                $arrayOutput['result'] .= $key . " - " . $sum . " руб. Нет прихода\n";
                $arrayOutput['sum'] -= $sum;
            }
        }

        $arrayOutput['sum'] = round($arrayOutput['sum'], 2);
        $arrayOutput['result'] .= "==========\n" . $arrayOutput['sum'] . " руб Итого разница";

        return $arrayOutput;
    }
}