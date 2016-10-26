<?php

function dt2RA_ru($dt)
{
    if ($dt == '0000-00-00 00:00:00' || $dt === null) {
        return '-';
    }

    $dt = date_parse($dt);
    $dt = mktime($dt['hour'], $dt['minute'], $dt['second'], $dt['month'], $dt['day'], $dt['year']);

    $timeStr = date('H:i', $dt);
    switch (date('Ymd')) {
        case date('Ymd', $dt):
            return 'Сегодня ' . $timeStr;
        case date('Ymd', $dt + 24 * 3600):
            return 'Вчера ' . $timeStr;
        case date('Ymd', $dt + 2 * 24 * 3600):
            return 'Позавчера ' . $timeStr;
        case date('Ymd', $dt - 24 * 3600):
            return 'Завтра ' . $timeStr;
        case date('Ymd', $dt - 2 * 24 * 3600):
            return 'Послезавтра ' . $timeStr;
    }
    return date('d/m/Y H:i', $dt);
}
function number2text_ru($number, $decimals = 0, $rod = false)
{
    if (($number / 1000000) >= 1) {
        $i    = $decimals == 0 ? round($number / 1000000) : number_format($number / 1000000, $decimals, ',', ' ');
        $text = $rod ? plural_ru_rod($i, "миллиона", "миллионов") : plural_ru($i, "миллиона", "миллионов", "миллионов");

        return $i . " " . $text;
    } elseif (($number / 1000) >= 1) {
        $i    = $decimals == 0 ? round($number / 1000) : number_format($number / 1000, $decimals, ',', ' ');
        $text = $rod ? plural_ru_rod($i, "тысячи", "тысяч") : plural_ru($i, "тысяча", "тысячи", "тысяч");

        return $i . " " . $text;
    } else {
        return ceil($number) . "";
    }
}
function plural_ru($i, $textIf1Not11, $textIf2t4Not10t20, $textElse)
{
    if ($i % 10 == 1 && $i % 100 != 11) {
        return $textIf1Not11;
    } elseif ($i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 10 || $i % 100 >= 20)) {
        return $textIf2t4Not10t20;
    } else {
        return $textElse;
    }
}
function plural_ru_rod($i, $textIf1Not11, $textElse)
{
    if ($i % 10 == 1 && $i % 100 != 11) {
        return $textIf1Not11;
    } else {
        return $textElse;
    }
}
function month2RA_ru($month)
{
    switch ($month) {
        case '01':
            return 'январь';
        case '02':
            return 'февраль';
        case '03':
            return 'март';
        case '04':
            return 'апрель';
        case '05':
            return 'май';
        case '06':
            return 'июнь';
        case '07':
            return 'июль';
        case '08':
            return 'август';
        case '09':
            return 'сентябрь';
        case '10':
            return 'октябрь';
        case '11':
            return 'ноябрь';
        case '12':
            return 'декабрь';
        default:
            trigger_error('Unknown month: ' . $month);
    }
    return '';
}

/**
 * Возвращает сумму прописью
 * @author runcore
 * @uses morph(...)
 */
function num2str($num)
{
    $nul     = 'ноль';
    $ten     = array(
        array('', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
        array('', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
    );
    $a20     = array(
        'десять',
        'одиннадцать',
        'двенадцать',
        'тринадцать',
        'четырнадцать',
        'пятнадцать',
        'шестнадцать',
        'семнадцать',
        'восемнадцать',
        'девятнадцать'
    );
    $tens    = array(
        2 => 'двадцать',
        'тридцать',
        'сорок',
        'пятьдесят',
        'шестьдесят',
        'семьдесят',
        'восемьдесят',
        'девяносто'
    );
    $hundred = array(
        '',
        'сто',
        'двести',
        'триста',
        'четыреста',
        'пятьсот',
        'шестьсот',
        'семьсот',
        'восемьсот',
        'девятьсот'
    );
    $unit    = array( // Units
        array('копейка', 'копейки', 'копеек', 1),
        array('рубль', 'рубля', 'рублей', 0),
        array('тысяча', 'тысячи', 'тысяч', 1),
        array('миллион', 'миллиона', 'миллионов', 0),
        array('миллиард', 'милиарда', 'миллиардов', 0),
    );

    list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($num)));
    $out = array();
    if (intval($rub) > 0) {
        foreach (str_split($rub, 3) as $uk => $v) { // by 3 symbols
            if (!intval($v)) {
                continue;
            }
            $uk     = sizeof($unit) - $uk - 1; // unit key
            $gender = $unit[$uk][3];
            list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
            // mega-logic
            $out[] = $hundred[$i1]; # 1xx-9xx
            if ($i2 > 1) {
                $out[] = $tens[$i2] . ' ' . $ten[$gender][$i3];
            } # 20-99
            else {
                $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3];
            } # 10-19 | 1-9
            // units without rub & kop
            if ($uk > 1) {
                $out[] = plural_ru($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
            }
        }
    } else {
        $out[] = $nul;
    }
    $out[] = plural_ru(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
    $out[] = $kop . ' ' . plural_ru($kop, $unit[0][0], $unit[0][1], $unit[0][2]); // kop
    return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
}
