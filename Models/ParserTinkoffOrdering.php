<?php


namespace Models;
require_once __DIR__ . "/../vendor/autoload.php";

use Carbon\Carbon;
use ConvertApi\ConvertApi;
use Spatie\PdfToText\Pdf;

class ParserTinkoffOrdering
{
    protected static array $arrayDataTinkoff = [];
    protected static array $minMaxTime = [];

    public function __construct(/*string $fileTinkoffTxt, array $minMaxTime*/){
        /*self::$minMaxTime = $minMaxTime;
        ConvertApi::setApiSecret('dcZNdfySQ5YFxgfF');
        ConvertApi::convert('txt', ['File' => "/Users/mihailpolakov/PhpstormProjects/HandlerFiles/Storage/1766922243.pdf"], 'pdf');
        $result = ConvertApi::convert('txt', [
            'File' => "/Users/mihailpolakov/PhpstormProjects/HandlerFiles/Storage/1766922243.pdf",
        ], 'pdf');
        $fileTinkoffTxt = $result->getFile()->getContents();
        //$fileTinkoffTxt = Pdf::getText("/Users/mihailpolakov/PhpstormProjects/HandlerFiles/Storage/1766922243.pdf", "/usr/local/bin/pdftotext");
        */
        $fileTinkoffTxt = file_get_contents("/Users/mihailpolakov/PhpstormProjects/HandlerFiles/Storage/1766922243.txt");
        if (preg_match('/Выписка по договору/', $fileTinkoffTxt)){
            $this->parseYellowOrdering($fileTinkoffTxt);
        }
    }

    protected function parseYellowOrdering(string $fileTinkoffTxt){
        preg_match_all('/\d{2}[.]\d{2}[.]\d{2}\s{1,3}\d?\d?:?\d?\d?/', $fileTinkoffTxt, $dates);
        preg_match_all('/[+]?\d{1,}\s?\d{0,}\s?\d{0,}[.]\d{2}\s{0,}₽/', $fileTinkoffTxt, $sums);

        $dates = array_reverse($dates[0]);
        $sums = array_reverse(array_slice($sums[0], 6));

        for($item=1; $item < count($dates); $item += 2){
            if(!preg_match('/[+]/', $sums[$item])) continue;

            $sum = (float) preg_replace('/(.)$/', "", str_replace([" ", "+", "₽", "\n"], "", $sums[$item]));

            if(preg_match('/\d{2}:\d{2}/', $dates[$item])){
                $date = preg_replace('/\s{1,}/', " ", $dates[$item]);
            } else {
                $date = preg_replace('/\s{1,}/', "", $dates[$item]) . " 00:00";
            }

            if(!$this->isValidDatetime($date, 'd.m.y H:i')) continue;

            if(empty(self::$arrayDataTinkoff[$date])) $arrayDataTin[$date] = [];
            self::$arrayDataTinkoff[$date][] = $sum;
        }

        var_dump(self::$arrayDataTinkoff);
    }

    public static function getArrayTinkoffData(){
        return self::$arrayDataTinkoff;
    }

    protected function isValidDatetime(string $date, string $fromFormatDate): bool
    {
        foreach (self::$minMaxTime as $range){
            $dateTime = Carbon::createFromFormat('d.m.Y H:i', $date)->format($fromFormatDate);;

            if($range['min'] <= $dateTime && $dateTime <= $range['max']){
                return true;
            }
        }
        return false;
    }
}

$test = new ParserTinkoffOrdering();
