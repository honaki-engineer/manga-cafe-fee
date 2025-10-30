<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('Asia/Tokyo');

use App\FeeCalculator;
use App\MangaCafePlan;

// テストパターン：延長コマと夜間境界の確認
$cases = [
    // 1hパック
    ['P1_延長なし_きっちり', '2025-11-02 10:00:00', '2025-11-02 11:00:00', MangaCafePlan::PACK_1, '延長0 / 夜間0'],
    ['P1_1秒超過_深夜なし', '2025-11-02 10:00:00', '2025-11-02 11:00:01', MangaCafePlan::PACK_1, '延長1 / 夜間0'],
    ['P1_22時またぎ1秒_夜間1コマ', '2025-11-02 21:00:00', '2025-11-02 22:00:01', MangaCafePlan::PACK_1, '延長1 / 夜間1'],
    ['P1_05時端一致_夜間なし', '2025-11-02 04:00:00', '2025-11-02 05:00:01', MangaCafePlan::PACK_1, '延長1 / 夜間0'],
    ['P1_00時端一致_夜間1コマ', '2025-11-02 23:00:00', '2025-11-03 00:10:00', MangaCafePlan::PACK_1, '延長1 / 夜間1'],
    // 3hパック
    ['P3_延長なし_きっちり', '2025-11-02 18:00:00', '2025-11-02 21:00:00', MangaCafePlan::PACK_3, '延長0 / 夜間0'],
    ['P3_日またぎ15分_夜間2コマ', '2025-11-02 20:50:00', '2025-11-03 00:05:00', MangaCafePlan::PACK_3, '延長2 / 夜間2'],
    ['P3_2日またぎ_夜間重複なし', '2025-11-02 22:55:00', '2025-11-04 00:05:00', MangaCafePlan::PACK_3, '延長133 / 夜間32'],
];

// テストパターン：入退店の異常値
$errorCases = [
    ['E1_退店が入店より前', '2025-11-02 10:00:00', '2025-11-02 09:59:59', MangaCafePlan::PACK_1],
    ['E2_退店が入店と同時刻', '2025-11-02 10:00:00', '2025-11-02 10:00:00', MangaCafePlan::PACK_1],
];

// 料金計算インスタンス
$calc = new FeeCalculator();

// テスト実行：延長コマと夜間境界の確認
foreach($cases as [$label, $enter, $leave, $plan, $note]) {
    $enterTime = new DateTimeImmutable($enter);                // 入店
    $leaveTime = new DateTimeImmutable($leave);                // 退店
    $result = $calc->calculate($enterTime, $leaveTime, $plan); // 入店/退店/パックを送る。結果をとる。

    // 結果表示
    echo "============================\n";
    echo "■ {$label}\n";
    echo "入店：{$enter}\n";
    echo "退店：{$leave}\n";
    echo "想定：{$note}\n";
    echo "----------------------------\n";

    $i = 0;
    $len = count($result);
    foreach($result as $k => $v) {
        echo "{$k} : {$v}";
        if(++$i < $len) {echo PHP_EOL;}
    }
    echo "\n";
}

// テスト実行：入退店の異常値（例外は捕捉して表示）
foreach ($errorCases as [$label, $enter, $leave, $plan]) {
    echo "============================\n";
    echo "■ {$label}\n";
    echo "入店：{$enter}\n";
    echo "退店：{$leave}\n";
    echo "想定：InvalidArgumentException\n";
    echo "----------------------------\n";

    try {
        $calc->calculate(new DateTimeImmutable($enter), new DateTimeImmutable($leave), $plan);
        echo "※想定外：例外が発生しませんでした\n";
    } catch(\InvalidArgumentException $e) {
        echo "例外：".$e->getMessage()."\n";
    }
}
