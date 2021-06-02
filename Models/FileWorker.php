<?php


namespace Models;


use Carbon\Exceptions\BadComparisonUnitException;
use Carbon\Exceptions\BadFluentConstructorException;
use Carbon\Exceptions\BadFluentSetterException;
use Spatie\PdfToText\Pdf;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;
use TelegramBot\Api\Types\Document;
use Carbon\Carbon;

class FileWorker
{
    public static function saveFile(BotApi $botApi, string $chatId, Document $document): bool
    {
        try {
            $mimeType = $document->getMimeType();
            $fileId = $document->getFileId();
            $fileName = $document->getFileName();
            file_put_contents(__DIR__ . "/../log.txt", $mimeType);
            $folderFiles = null;
            $folderUser = $chatId;
            if(stripos($mimeType, 'csv') || stripos($mimeType, 'comma-separated-values')){
                $folderFiles = "csv";
            } elseif (stripos($mimeType, "pdf") || $mimeType === "application/binary"){
                $folderFiles = "tin";
            } else {
                return false;
            }

            $pathFolderUser = __DIR__ . "/../Storage/" . $folderUser;
            $pathFolderFiles = __DIR__ . "/../Storage/" . $folderUser . "/" . $folderFiles;

            $storageExist = file_exists(__DIR__ . "/../Storage");
            if(!$storageExist) mkdir($storageExist);
            $folderUser = file_exists($pathFolderUser);
            if(!$folderUser) mkdir($pathFolderUser);
            $folderFiles = file_exists($pathFolderFiles);
            if(!$folderFiles) mkdir($pathFolderFiles);

            $dataFile = $botApi->downloadFile($fileId);

            if(is_bool(file_put_contents($pathFolderFiles . "/" . $fileName, $dataFile))){
                return false;
            } else {
                return true;
            }
        } catch (Exception $e){
            file_put_contents(__DIR__ . "/../log.txt", $e->getMessage());
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
        try {

            $folderPathCsv = __DIR__ . "/../Storage/" . $chat_id . "/csv";
            $folderPathTin = __DIR__ . "/../Storage/" . $chat_id . "/tin";
            $folderOutputFiles = __DIR__ . "/../Storage/" . $chat_id . "/output";
            
            mkdir($folderOutputFiles);

            $filesCsv = scandir($folderPathCsv);
            $filePdf = scandir($folderPathTin)[2];

            $arrayDataCsv = [];
            $arrayDataTin = [];
            $headerCsv = [];
            $arrayRangeDate = [];
            $outputCsv = fopen($folderOutputFiles . "/output.csv", "w");
            $outputTin = fopen($folderOutputFiles . "/output_tin.csv", "w");

            $convertedTinFile = Pdf::getText($folderPathTin . "/" . $filePdf);

            $arrayOutput = [
                'sum' => 0,
                'result' => ""
            ];

            $keyIndexRange = 0;
            foreach ($filesCsv as $file){
                if($file === "." || $file === "..") continue;

                $arrayRangeDate[$keyIndexRange] = [
                    'min' => 0,
                    'max' => 0
                ];

                $fopen = fopen($folderPathCsv . "/" . $file, 'r');

                if(is_null($headerCsv)){
                    $header = fgetcsv($fopen);
                    $headerCsv = [$header[2], $header[3], $header[0]];
                } else {
                    fgetcsv($fopen);
                }


                while (($array = fgetcsv($fopen)) != false){
                    file_put_contents(__DIR__ . "/../log1.txt", $array[2] . " " . $array[0]);
                    if(!preg_match('/([.]\d{6})$/', $array[2])) $array[2] .= '.000000';
                    $dateObject = Carbon::createFromFormat('Y-m-d H:i:s.u', $array[2], 'UTC')->setTimezone('Europe/Moscow');
                    $dateString = $dateObject->format('d.m.Y H:i');
                    $dateStringMin = $dateObject->format('d.m.Y H:i');;
                    $dateStringMax = $dateObject->addMinutes(5)->format('d.m.Y H:i');;
                    $sum = round((float) $array[3], 2);

                    if($arrayRangeDate[$keyIndexRange]['min'] === 0 || $arrayRangeDate[$keyIndexRange]['min'] > $dateStringMin){
                        $arrayRangeDate[$keyIndexRange]['min'] = $dateStringMin;
                    }

                    if($arrayRangeDate[$keyIndexRange]['max'] === 0 || $arrayRangeDate[$keyIndexRange]['max'] < $dateStringMax){
                        $arrayRangeDate[$keyIndexRange]['max'] = $dateStringMax;
                    }

                    $arrayDataCsv[] = [$dateString, $sum, $array[0]];
                }

                fclose($fopen);
                $keyIndexRange++;
            }

            fputcsv($outputTin, ["Дата", "Сумма"]);
            $parser = new ParserTinkoffOrdering($convertedTinFile, $arrayRangeDate, $outputTin);
            $arrayDataTin = $parser->getArrayTinkoffData();
            fclose($outputTin);

            usort($arrayDataCsv, function ($a, $b){
                if($a < $b){
                    return -1;
                }
                if($a > $b){
                    return 1;
                }
                return 0;
            });

            fputcsv($outputCsv, $headerCsv);

            foreach ($arrayDataCsv as $data){
                $dateObject = Carbon::createFromFormat('d.m.Y H:i', $data[0]);
                fputcsv($outputCsv, [$data[0], preg_replace('/[.]/', ',', (string)$data[1]), $data[2]]);

                for($minute = 0; $minute <= 5; $minute++){
                    $dateString = $dateObject->format('d.m.Y H:i');
                    if(!empty($arrayDataTin[$dateString])){
                        $sumKey = array_search($data[1], $arrayDataTin[$dateString]);
                        if(!is_bool($sumKey)){
                            unset($arrayDataTin[$dateString][$sumKey]);
                            continue 2;
                        }
                    }
                    $dateObject->addMinutes();
                }

                $arrayOutput['result'] .= $data[0] . " - " . (string)$data[1] . " руб. Нет прихода\n";
                $arrayOutput['sum'] -= $data[1];
            }

            fclose($outputCsv);

            foreach ($arrayDataTin as $key => $value){
                foreach ($value as $sum){
                    $arrayOutput['result'] .= $key . " - " . $sum . " руб. Лишнее зачисление\n";
                    $arrayOutput['sum'] += $sum;
                }
            }

            $arrayOutput['sum'] = round($arrayOutput['sum'], 2);
            $arrayOutput['result'] .= "==========\n" . $arrayOutput['sum'] . " руб Итого разница\n";

            return $arrayOutput;
        } catch (\Exception | BadComparisonUnitException | BadFluentConstructorException | BadFluentSetterException $e){
            file_put_contents(__DIR__ . "/../log.txt", $e->getMessage());
            return [
                'sum' => 0,
                'result' => "Генерация не удалась"
            ];
        }
    }
}