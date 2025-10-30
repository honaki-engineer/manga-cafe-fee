<?php
declare(strict_types=1);

namespace App;

enum MangaCafePlan: int
{
    // パック定義（秒数）
    case PACK_1 = 3600;  // 1時間
    case PACK_3 = 10800; // 3時間
    case PACK_5 = 18000; // 5時間
    case PACK_8 = 28800; // 8時間

    // パック料金（税抜）
    public function packFeeExcl(): int
    {
        return match($this) {
            self::PACK_1 => 500,
            self::PACK_3 => 800,
            self::PACK_5 => 1500,
            self::PACK_8 => 1900,
        };
    }

    // パック定義の value（秒数）を返す
    public function seconds(): int
    {
        return $this->value;
    }
}
