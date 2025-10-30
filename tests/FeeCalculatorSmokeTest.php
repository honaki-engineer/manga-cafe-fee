<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use App\FeeCalculator;
use App\MangaCafePlan;

final class FeeCalculatorSmokeTest extends TestCase
{
    // 型付きのプロパティ宣言
    private FeeCalculator $calc;

    // 初期化メソッド
    protected function setUp(): void
    {
        $this->calc = new FeeCalculator();
    }

    // 日本語のキー名を英語に変換
    private function mapJpToEn(array $jp): array
    {
        $map = [
            '基本（税抜）'              => 'base',
            '延長合計（税抜）'           => 'extExcl',
            '深夜割増（税抜、延長にのみ）' => 'nightSurchargeExcl',
            '税抜合計'                  => 'totalExcl',
            '消費税（四捨五入）'          => 'tax',
            '税込合計'                  => 'totalIncl',
        ];
        $en = [];
        foreach($map as $jpKey => $enKey) {
            $en[$enKey] = $jp[$jpKey];
        }
        return $en;
    }

    // テスト説明を分かりやすく表示しながら、cases() のデータセットごとに繰り返し実行する設定
    #[TestDox('Basic and edges with $caseLabel')]
    #[DataProvider('cases')]

    // テスト本体
    public function test_basic_and_edges(
        string $caseLabel,    // TestDox 時に使用
        string $enter,        // 入店
        string $leave,        // 退店
        MangaCafePlan $plan,  // パック
        array|string $expectedList   // テストで期待する結果
    ): void {
        // 例外ケース
        if($expectedList === 'exception') {
            $this->expectException(\InvalidArgumentException::class);
        }

        // 1ケースずつ実行される本体
        $actualJp = $this->calc->calculate(new DateTimeImmutable($enter), new DateTimeImmutable($leave), $plan);
        $actual   = $this->mapJpToEn($actualJp);

        // 期待値の定義
        [$base, $ext, $night, $totalExcl, $tax, $totalIncl] = $expectedList;
        $expected = [
            'base'               => $base,
            'extExcl'            => $ext,
            'nightSurchargeExcl' => $night,
            'totalExcl'          => $totalExcl,
            'tax'                => $tax,
            'totalIncl'          => $totalIncl,
        ];

        // 値・型とも完全一致
        $this->assertSame($expected, $actual);
    }

    // テストケース
    public static function cases(): array
    {
        return [
            // 延長コマと夜間境界の確認
            // 1hパック
            ['P1_延長なし_きっちり', '2025-11-02 10:00:00', '2025-11-02 11:00:00', MangaCafePlan::PACK_1, [500, 0, 0, 500, 50, 550]],
            ['P1_1秒超過_深夜なし', '2025-11-02 10:00:00', '2025-11-02 11:00:01', MangaCafePlan::PACK_1, [500, 100, 0, 600, 60, 660]],
            ['P1_22時またぎ1秒_夜間1コマ','2025-11-02 21:00:00', '2025-11-02 22:00:01', MangaCafePlan::PACK_1, [500, 100, 15, 615, 62, 677]],
            ['P1_05時端一致_夜間なし','2025-11-02 04:00:00', '2025-11-02 05:00:01', MangaCafePlan::PACK_1, [500, 100, 0, 600, 60, 660]],
            ['P1_00時端一致_夜間1コマ', '2025-11-02 23:00:00', '2025-11-03 00:10:00', MangaCafePlan::PACK_1, [500, 100, 15, 615, 62, 677]],
            // 3hパック
            ['P3_延長なし_きっちり', '2025-11-02 18:00:00', '2025-11-02 21:00:00', MangaCafePlan::PACK_3, [800, 0, 0, 800, 80, 880]],
            ['P3_日またぎ15分_夜間2コマ', '2025-11-02 20:50:00', '2025-11-03 00:05:00', MangaCafePlan::PACK_3, [800, 200, 30, 1030, 103, 1133]],
            ['P3_2日またぎ_夜間重複カウントなし', '2025-11-02 22:55:00', '2025-11-04 00:05:00', MangaCafePlan::PACK_3, [800, 13300, 480, 14580, 1458, 16038]],
            // 入退店の異常値
            ['E1_退店が入店より前', '2025-11-02 10:00:00', '2025-11-02 09:59:59', MangaCafePlan::PACK_1, 'exception'],
            ['E2_退店が入店と同時刻','2025-11-02 10:00:00', '2025-11-02 10:00:00', MangaCafePlan::PACK_1, 'exception'],
        ];
    }
}
