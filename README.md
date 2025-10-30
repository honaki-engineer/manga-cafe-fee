# 問１：マンガ喫茶の料金計算処理を実装してください

## 概要

マンガ喫茶の料金計算を PHP で実装しています。  
※ 選考用に一時的に Public としています。選考終了後は Private に戻します。

---

## 目次

- [概要](#概要)
- [使用技術](#使用技術)
- [ディレクトリ構成](#ディレクトリ構成)
- [セットアップ手順](#セットアップ手順)
- [CLI確認手順](#CLI確認手順)
- [テスト](#テスト)
- [要件の満たし方](#要件の満たし方)
  
---

## 使用技術

- PHP 8.2
- PHPUnit 11.x（テスト）
- Composer 2.x（autoload設定・scripts管理）  
  
※ 動作確認: macOS（Linux でも同等に動作想定）

---

## ディレクトリ構成

```txt
manga-cafe-fee/
├── src/
│   ├── FeeCalculator.php           # 料金計算ロジック本体
│   └── MangaCafePlan.php           # コース定義と料金テーブル
├── tests/
│   └── FeeCalculatorSmokeTest.php  # 機能・境界テスト
├── vendor/                         # composer install で生成
├── run.php                         # CLI実行
├── composer.json                   # autoload設定・スクリプト
├── phpunit.xml                     # テスト設定
└── README.md
```

---

## セットアップ手順

```bash
git clone https://github.com/honaki-engineer/manga-cafe-fee.git manga-cafe-fee
cd manga-cafe-fee
composer install
```

---

## CLI確認手順

```bash
composer run
```
出力例：
```markdown
============================
■ P3_日またぎ15分_夜間2コマ
入店：2025-11-02 20:50:00
退店：2025-11-03 00:05:00
想定：延長2 / 夜間2
----------------------------
基本（税抜） : 800
延長合計（税抜） : 200
深夜割増（税抜、延長にのみ） : 30
税抜合計 : 1030
消費税（四捨五入） : 103
税込合計 : 1133
```
  
※ `composer.json` の `scripts` に以下を定義しています:
- `composer run`  … `php run.php`
- `composer test` … `phpunit`

### 補足

直接実行も可：
```bash
php run.php
```

---

## テスト

```bash
composer test
```
出力例：  
```markdown
PHPUnit 11.5.42 by Sebastian Bergmann and contributors.

Fee Calculator Smoke
 ✔ Basic and edges with P1_延長なし_きっちり
 ✔ Basic and edges with P3_日またぎ15分_夜間2コマ
OK (6 tests, 6 assertions)
```

---

## 要件の満たし方

- **実装言語：PHP**
  - PHP 8.2 で実装（`DateTimeImmutable` を使用）。

- **入力**
  - 入店日時：`DateTimeImmutable`
  - 退店日時：`DateTimeImmutable`
  - コース種別：`enum App\MangaCafePlan`（1h/3h/5h/8h）

- **料金表（税抜）**
  - 1時間：500円 / 3時間：800円 / 5時間：1,500円 / 8時間：1,900円（`MangaCafePlan::packFeeExcl()`）
  - 延長：**10分ごと100円**（`EXTENSION_UNIT_SECONDS=600`／**切り上げ**で算定）

- **延長の発生条件**
  - コース時間を**1秒でも超過**したら延長対象（`ceil(overSec / 600)` でコマ数算定）

- **深夜割増（延長にのみ適用）**
  - 時間帯：**22:00〜翌5:00**
  - その時間帯に**1秒でもかかった延長コマ**を **+15%** 加算  
    （半開区間 `[22:00,24:00)` と `[00:00,05:00)` として判定／**延長コマのみ**対象）

- **税込・税抜の算出**
  - 税率 **10%**、**四捨五入**で消費税を算出  
  - 返却は **金額内訳の配列**（`基本（税抜）/延長合計（税抜）/深夜割増（税抜、延長にのみ）/税抜合計/消費税（四捨五入）/税込合計`）

- **その他仕様**
  - **日付またぎ**でも延長コマの夜間判定は**重複加算しない**（コマ単位でオーバーラップ判定）
  - **退店 ≤ 入店** は `InvalidArgumentException` をスロー
  - タイムゾーンは **Asia/Tokyo**（`phpunit.xml` / `run.php`）
