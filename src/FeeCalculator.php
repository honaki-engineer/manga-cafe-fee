<?php
declare(strict_types=1);

namespace App;

use DateInterval;
use DateTimeImmutable;

final class FeeCalculator
{
    // 計算の定数
    private const EXTENSION_UNIT_SECONDS  = 600;  // 延長は10分単位
    private const EXTENSION_UNIT_FEE_EXCL = 100;  // 10分ごと100円（税抜）
    private const NIGHT_RATE              = 0.15; // 深夜割増 +15%
    private const TAX_RATE                = 0.10; // 消費税10%
    private const NIGHT_START_HOUR        = 22;   // 22:00（深夜割増スタート時間）
    private const NIGHT_END_HOUR          = 5;    // 翌05:00（深夜割増エンド時間）

    // メイン計算
    public function calculate(
        DateTimeImmutable $enter, // 入店
        DateTimeImmutable $leave, // 退店
        MangaCafePlan $plan       // 選択したパック
    ): array {
        if($leave <= $enter) {
            throw new \InvalidArgumentException('退店は入店より後である必要があります。');
        }

        // 基本料金
        $base = $plan->packFeeExcl();

        // 経過・超過（1秒でも超過で延長対象）
        $elapsed = $leave->getTimestamp() - $enter->getTimestamp(); // 滞在時間
        $overSec = max(0, $elapsed - $plan->seconds());             // 超過確認

        // 延長コマ（10分単位：切り上げ）
        $extBlocks = $overSec > 0 ? (int)ceil($overSec / self::EXTENSION_UNIT_SECONDS) : 0; // 延長のコマ数（10分単位）
        $extExcl   = $extBlocks * self::EXTENSION_UNIT_FEE_EXCL;                            // 延長料金（税抜）の合計

        // 延長が発生していない場合は、深夜判定を行わずに即返す
        if($extBlocks === 0) {
            $totalExcl = $base;
            $tax       = (int)round($totalExcl * self::TAX_RATE);
            $totalIncl = $totalExcl + $tax;

            return [
                '基本（税抜）'                => $base,
                '延長合計（税抜）'             => 0,
                '深夜割増（税抜、延長にのみ）'   => 0,
                '税抜合計'                   => $totalExcl,
                '消費税（四捨五入）'           => $tax,
                '税込合計'                   => $totalIncl,
            ];
        }

        // 深夜割増計算（延長コマのみ）
        $nightBlocks = 0;
        if($extBlocks > 0) {
            $extStart = $enter->add(new DateInterval('PT' . $plan->seconds() . 'S')); // 延長が始まる時刻（パック終了時刻）
            $extEnd   = $leave;
            for($i = 0; $i < $extBlocks; $i++) {
                $blockStart = $extStart->add(new DateInterval('PT' . ($i * self::EXTENSION_UNIT_SECONDS) . 'S')); // ある延長コマの開始時刻
                $blockEnd   = $blockStart->add(new DateInterval('PT' . self::EXTENSION_UNIT_SECONDS . 'S'));      // ある延長コマの終了時刻

                if($blockStart >= $extEnd) break;            // 退店後開始なら打ち切り（例：基本料金~21:55 : 延長退店21:58なら深夜割増なしにする）
                if($blockEnd > $extEnd) $blockEnd = $extEnd; // 退店前に延長突入
                if($blockEnd <= $blockStart) continue;       // 念のため防御

                // 延長コマが22:00〜翌5:00に1秒でもかかっていれば深夜割増対象にする（= true）
                if($this->blockTouchesNight($blockStart, $blockEnd)) {
                    $nightBlocks++;
                }
            }
        }
        $nightSurchargeExcl = (int)round($nightBlocks * self::EXTENSION_UNIT_FEE_EXCL * self::NIGHT_RATE); // '深夜割増（税抜、延長にのみ）

        // 合計
        $totalExcl = $base + $extExcl + $nightSurchargeExcl;
        $tax       = (int)round($totalExcl * self::TAX_RATE); // 税は四捨五入
        $totalIncl = $totalExcl + $tax;

        return [
            '基本（税抜）'              => $base,
            '延長合計（税抜）'           => $extExcl,
            '深夜割増（税抜、延長にのみ）' => $nightSurchargeExcl,
            '税抜合計'                  => $totalExcl,
            '消費税（四捨五入）'          => $tax,
            '税込合計'                  => $totalIncl,
        ];
    }

    // 延長コマが22:00〜翌5:00に1秒でもかかっていれば深夜割増対象にする（= true）
    private function blockTouchesNight(DateTimeImmutable $start, DateTimeImmutable $end): bool // $start = 延長コマ開始時間、$end = 延長コマ終了時間
    {
        if($end <= $start) return false; // 念のためのガード

        // 判定対象日範囲：startの前日 〜 endの翌日
        $startMidnight = $start->setTime(0, 0, 0)->modify('-1 day'); // 例）00:15 のように「深夜0時台」に延長コマがある場合、夜間帯は「前日22:00〜当日05:00」なので「前日」も判定対象に含める必要がある
        $endMidnight   = $end->setTime(0, 0, 0)->modify('+1 day');   // 例）23:55 のように「深夜直前」に延長コマがある場合、夜間帯は「当日22:00〜翌日05:00」なので「翌日」も判定対象に含める必要がある

        // endの翌日00:00は含めない（<）ことで、翌日の夜間帯を重複生成しない
        for($currentDate = $startMidnight; $currentDate < $endMidnight; $currentDate = $currentDate->modify('+1 day')) {
            $dayMidnight  = $currentDate;                   // 当日 00:00
            $nextMidnight = $currentDate->modify('+1 day'); // 翌日 00:00

            // 当日 22:00-24:00
            $n1Start = $dayMidnight->setTime(self::NIGHT_START_HOUR, 0, 0);
            $n1End   = $nextMidnight; // 24:00 = 翌日 00:00

            // 翌日 00:00-05:00
            $n2Start = $nextMidnight;
            $n2End   = $nextMidnight->setTime(self::NIGHT_END_HOUR, 0, 0);

            // この延長コマが、深夜帯に1秒でもかかっているか判定
            if($this->overlaps($start, $end, $n1Start, $n1End)) return true; // 延長コマの終了 > 22:00 & 延長コマの開始 < 24:00
            if($this->overlaps($start, $end, $n2Start, $n2End)) return true; // 延長コマの終了 > 00:00 & 延長コマの開始 < 05:00
        }
        return false;
    }

    private function overlaps(
        DateTimeImmutable $aStart,
        DateTimeImmutable $aEnd,
        DateTimeImmutable $bStart,
        DateTimeImmutable $bEnd
    ): bool {
        // この延長コマが、深夜帯に1秒でもかかっているか判定
        return $aStart->getTimestamp() < $bEnd->getTimestamp()
            && $bStart->getTimestamp() < $aEnd->getTimestamp();
    }
}
