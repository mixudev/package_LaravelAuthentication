<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Local QR Code renderer for TOTP provisioning URIs.
 */
final class QrCodeGenerator
{
    private const GF256_POLY = 0x11D; // x^8 + x^4 + x^3 + x^2 + 1

    /** @var array<int, int> */
    private static array $exp = [];
    /** @var array<int, int> */
    private static array $log = [];
    private static bool $gfInit = false;

    /**
     * Generate SVG string for the given text.
     */
    public static function svg(string $text, int $size = 220, int $margin = 4): string
    {
        $options = self::options(QRCode::OUTPUT_MARKUP_SVG, $size, $margin, [
            'outputBase64' => false,
        ]);

        $svg = (string) (new QRCode($options))->render($text);

        $svg = (string) preg_replace('/\A<\?xml[^>]*\?>\s*/', '', $svg);

        return (string) preg_replace(
            '/(<svg\b[^>]*>)/',
            '$1<rect width="100%" height="100%" fill="#ffffff"/>',
            $svg,
            1
        );
    }

    /**
     * Generate a safe data URI representing the QR code.
     *
    * Prefer a PNG raster for broad mobile scanner compatibility.
     */
    public static function dataUri(string $text, int $size = 220, int $margin = 4): string
    {
        if (function_exists('imagepng')) {
            return self::pngDataUri($text, $size, $margin);
        }

        $svg = self::svg($text, $size, $margin);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Render the QR matrix to a PNG data URI using GD, scaling each module
     * into a block so the raster has enough resolution for reliable scanning.
     */
    public static function pngDataUri(string $text, int $size = 300, int $margin = 4): string
    {
        if (!function_exists('imagepng')) {
            return self::dataUriSvgFallback($text, $size, $margin);
        }

        $options = self::options(QRCode::OUTPUT_IMAGE_PNG, $size, $margin, [
            'outputBase64' => false,
            'returnResource' => true,
        ]);
        $image = (new QRCode($options))->render($text);

        if (!($image instanceof \GdImage)) {
            return self::dataUriSvgFallback($text, $size, $margin);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        return $png === ''
            ? self::dataUriSvgFallback($text, $size, $margin)
            : 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function options(string $outputType, int $size, int $margin, array $overrides = []): QROptions
    {
        return new QROptions(array_merge([
            'outputType' => $outputType,
            'outputBase64' => true,
            'eccLevel' => QRCode::ECC_M,
            'quietzoneSize' => max(4, $margin),
            'scale' => max(6, (int) floor($size / 40)),
        ], $overrides));
    }

    protected static function dataUriSvgFallback(string $text, int $size, int $margin): string
    {
        $svg = self::svg($text, $size, $margin);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * @return array<int, array<int, bool>>
     */
    public static function encodeToMatrix(string $data): array
    {
        self::initGf();

        $length = strlen($data);
        // Capacity table for ECC Level M:
        $capacities = [
            1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84,
            6 => 106, 7 => 122, 8 => 152, 9 => 180, 10 => 213,
            11 => 251, 12 => 287, 13 => 331, 14 => 362
        ];

        $version = 1;
        foreach ($capacities as $ver => $cap) {
            if ($length <= $cap) {
                $version = $ver;
                break;
            }
            $version = $ver;
        }

        // Generate QR codewords
        $bitBuffer = [];
        // Mode indicator: 0100 (Byte mode)
        self::appendBits($bitBuffer, 0b0100, 4);
        // Character count indicator (8 bits for versions 1-9, 16 bits for >=10)
        $charCountBits = $version < 10 ? 8 : 16;
        self::appendBits($bitBuffer, $length, $charCountBits);

        // Data bytes
        for ($i = 0; $i < $length; $i++) {
            self::appendBits($bitBuffer, ord($data[$i]), 8);
        }

        // Total capacity in data codewords for version/ECC M
        $totalDataCodewords = self::getDataCodewordCount($version);
        $totalBits = $totalDataCodewords * 8;

        // Terminator (up to 4 zero bits)
        $terminatorBits = min(4, $totalBits - count($bitBuffer));
        self::appendBits($bitBuffer, 0, $terminatorBits);

        // Pad to byte boundary
        while (count($bitBuffer) % 8 !== 0) {
            $bitBuffer[] = 0;
        }

        // Pad bytes (0xEC, 0x11 alternating)
        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        while (count($bitBuffer) < $totalBits) {
            self::appendBits($bitBuffer, $padBytes[$padIndex % 2], 8);
            $padIndex++;
        }

        // Convert bit buffer to data codewords
        $dataCodewords = [];
        for ($i = 0; $i < count($bitBuffer); $i += 8) {
            $val = 0;
            for ($b = 0; $b < 8; $b++) {
                $val = ($val << 1) | $bitBuffer[$i + $b];
            }
            $dataCodewords[] = $val;
        }

        // Generate error correction blocks and interleave
        $finalCodewords = self::generateAndInterleaveEc($version, $dataCodewords);

        // Construct matrix
        $dimension = 17 + ($version * 4);
        /** @var array<int, array<int, bool>> $matrix */
        $matrix = array_fill(0, $dimension, array_fill(0, $dimension, false));
        /** @var array<int, array<int, bool>> $isFunction */
        $isFunction = array_fill(0, $dimension, array_fill(0, $dimension, false));

        // Place function patterns
        self::placeFinderPatterns($matrix, $isFunction, $dimension);
        self::placeAlignmentPatterns($matrix, $isFunction, $version, $dimension);
        self::placeTimingPatterns($matrix, $isFunction, $dimension);
        self::reserveFormatAndVersion($matrix, $isFunction, $dimension, $version);

        // Place data bits in zig-zag path
        self::placeDataBits($matrix, $isFunction, $dimension, $finalCodewords);

        // Apply optimal mask (evaluate mask 0)
        self::applyMaskAndFormat($matrix, $isFunction, $dimension, $version);

        /** @var array<int, array<int, bool>> $cleanMatrix */
        $cleanMatrix = [];
        for ($y = 0; $y < $dimension; $y++) {
            $cleanMatrix[$y] = [];
            for ($x = 0; $x < $dimension; $x++) {
                $cleanMatrix[$y][$x] = (bool) ($matrix[$y][$x] ?? false);
            }
        }

        return $cleanMatrix;
    }

    private static function initGf(): void
    {
        if (self::$gfInit) {
            return;
        }

        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);

        $val = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $val;
            self::$exp[$i + 255] = $val;
            self::$log[$val] = $i;
            $val <<= 1;
            if ($val & 0x100) {
                $val ^= self::GF256_POLY;
            }
        }

        self::$gfInit = true;
    }

    private static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    /**
     * @param array<int> $bitBuffer
     */
    private static function appendBits(array &$bitBuffer, int $value, int $numBits): void
    {
        for ($i = $numBits - 1; $i >= 0; $i--) {
            $bitBuffer[] = ($value >> $i) & 1;
        }
    }

    private static function getDataCodewordCount(int $version): int
    {
        // Total data codewords for Level M
        $table = [
            1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86,
            6 => 108, 7 => 124, 8 => 154, 9 => 182, 10 => 216,
            11 => 254, 12 => 290, 13 => 334, 14 => 365
        ];
        return $table[$version] ?? 16;
    }

    private static function getEcCodewordsPerBlock(int $version): int
    {
        // EC codewords per block for Level M
        $table = [
            1 => 10, 2 => 16, 3 => 26, 4 => 18, 5 => 24,
            6 => 16, 7 => 18, 8 => 22, 9 => 22, 10 => 26,
            11 => 30, 12 => 22, 13 => 22, 14 => 24
        ];
        return $table[$version] ?? 10;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private static function getBlockStructure(int $version): array
    {
        // [num_blocks_g1, data_cw_g1, num_blocks_g2, data_cw_g2] for Level M
        $table = [
            1 => [1, 16, 0, 0],
            2 => [1, 28, 0, 0],
            3 => [1, 44, 0, 0],
            4 => [2, 32, 0, 0],
            5 => [2, 43, 0, 0],
            6 => [4, 27, 0, 0],
            7 => [4, 31, 0, 0],
            8 => [2, 38, 2, 39],
            9 => [3, 36, 2, 37],
            10 => [4, 43, 1, 44],
            11 => [1, 50, 4, 51],
            12 => [6, 36, 2, 37],
            13 => [8, 37, 1, 38],
            14 => [4, 40, 5, 41],
        ];
        return $table[$version] ?? [1, 16, 0, 0];
    }

    /**
     * @param array<int> $dataCodewords
     * @return array<int>
     */
    private static function generateAndInterleaveEc(int $version, array $dataCodewords): array
    {
        $ecPerBlock = self::getEcCodewordsPerBlock($version);
        $blockStructure = self::getBlockStructure($version);
        $numG1  = $blockStructure[0];
        $dataG1 = $blockStructure[1];
        $numG2  = $blockStructure[2];
        $dataG2 = $blockStructure[3];

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        for ($b = 0; $b < $numG1; $b++) {
            $block = array_slice($dataCodewords, $offset, $dataG1);
            $offset += $dataG1;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::calculateRs($block, $ecPerBlock);
        }

        for ($b = 0; $b < $numG2; $b++) {
            $block = array_slice($dataCodewords, $offset, $dataG2);
            $offset += $dataG2;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::calculateRs($block, $ecPerBlock);
        }

        $result = [];
        $maxDataLen = max($dataG1, $dataG2);
        $totalBlocks = count($dataBlocks);

        for ($i = 0; $i < $maxDataLen; $i++) {
            for ($b = 0; $b < $totalBlocks; $b++) {
                if ($i < count($dataBlocks[$b])) {
                    $result[] = $dataBlocks[$b][$i];
                }
            }
        }

        for ($i = 0; $i < $ecPerBlock; $i++) {
            for ($b = 0; $b < $totalBlocks; $b++) {
                $result[] = $ecBlocks[$b][$i];
            }
        }

        return $result;
    }

    /**
     * @param array<int> $data
     * @return array<int>
     */
    private static function calculateRs(array $data, int $numEc): array
    {
        $gen = [1];
        for ($i = 0; $i < $numEc; $i++) {
            $next = [0];
            $factor = self::$exp[$i];
            foreach ($gen as $coef) {
                $next[] = self::gfMul($coef, $factor);
            }
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
            }
            $gen = $next;
        }

        $remainder = array_fill(0, $numEc, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;
            for ($i = 0; $i < $numEc; $i++) {
                $genVal = isset($gen[$i + 1]) ? $gen[$i + 1] : 0;
                $remainder[$i] ^= self::gfMul($genVal, $factor);
            }
        }

        return $remainder;
    }

    /**
     * @param array<int, array<int, bool>> $matrix
     * @param array<int, array<int, bool>> $isFunction
     */
    private static function placeFinderPatterns(array &$matrix, array &$isFunction, int $size): void
    {
        $positions = [[0, 0], [$size - 7, 0], [0, $size - 7]];
        foreach ($positions as [$px, $py]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $mx = $px + $x;
                    $my = $py + $y;
                    if ($mx >= 0 && $mx < $size && $my >= 0 && $my < $size) {
                        $isFunction[$my][$mx] = true;
                        if ($x >= 0 && $x <= 6 && $y >= 0 && $y <= 6) {
                            $isBlack = ($x === 0 || $x === 6 || $y === 0 || $y === 6 || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4));
                            $matrix[$my][$mx] = $isBlack;
                        } else {
                            $matrix[$my][$mx] = false;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array<int, array<int, bool>> $matrix
     * @param array<int, array<int, bool>> $isFunction
     */
    private static function placeAlignmentPatterns(array &$matrix, array &$isFunction, int $version, int $size): void
    {
        if ($version < 2) {
            return;
        }

        $alignTable = [
            2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
            6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42],
            9 => [6, 26, 46], 10 => [6, 28, 50], 11 => [6, 30, 54],
            12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66]
        ];

        $coords = $alignTable[$version] ?? [];
        foreach ($coords as $cy) {
            foreach ($coords as $cx) {
                if (($cx === 6 && $cy === 6) || ($cx === 6 && $cy === $coords[count($coords) - 1] && $cy > $size - 10) || ($cx === $coords[count($coords) - 1] && $cx > $size - 10 && $cy === 6)) {
                    continue;
                }

                if ($isFunction[$cy][$cx]) {
                    continue;
                }

                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $mx = $cx + $x;
                        $my = $cy + $y;
                        $isFunction[$my][$mx] = true;
                        $isBlack = (abs($x) === 2 || abs($y) === 2 || ($x === 0 && $y === 0));
                        $matrix[$my][$mx] = $isBlack;
                    }
                }
            }
        }
    }

    /**
     * @param array<int, array<int, bool>> $matrix
     * @param array<int, array<int, bool>> $isFunction
     */
    private static function placeTimingPatterns(array &$matrix, array &$isFunction, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            if (!$isFunction[6][$i]) {
                $isFunction[6][$i] = true;
                $matrix[6][$i] = ($i % 2 === 0);
            }
            if (!$isFunction[$i][6]) {
                $isFunction[$i][6] = true;
                $matrix[$i][6] = ($i % 2 === 0);
            }
        }

        $matrix[$size - 8][8] = true;
        $isFunction[$size - 8][8] = true;
    }

    /**
     * @param array<int, array<int, bool>> $matrix
     * @param array<int, array<int, bool>> $isFunction
     */
    private static function reserveFormatAndVersion(array &$matrix, array &$isFunction, int $size, int $version): void
    {
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $isFunction[8][$i] = true;
                $isFunction[$i][8] = true;
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $isFunction[8][$i] = true;
        }
        for ($i = $size - 7; $i < $size; $i++) {
            $isFunction[$i][8] = true;
        }
    }

    /**
     * @param array<int, array<int, bool>> $matrix
     * @param array<int, array<int, bool>> $isFunction
     * @param array<int> $codewords
     */
    private static function placeDataBits(array &$matrix, array &$isFunction, int $size, array $codewords): void
    {
        $bitIndex = 0;
        $totalBits = count($codewords) * 8;

        $right = $size - 1;
        $up = true;

        while ($right > 0) {
            if ($right === 6) {
                $right--;
            }

            $yRange = $up ? range($size - 1, 0, -1) : range(0, $size - 1);

            foreach ($yRange as $y) {
                for ($col = 0; $col < 2; $col++) {
                    $x = $right - $col;
                    if (!$isFunction[$y][$x]) {
                        if ($bitIndex < $totalBits) {
                            $cw = $codewords[$bitIndex >> 3];
                            $bit = ($cw >> (7 - ($bitIndex & 7))) & 1;
                            $matrix[$y][$x] = ($bit === 1);
                            $bitIndex++;
                        } else {
                            $matrix[$y][$x] = false;
                        }
                    }
                }
            }

            $up = !$up;
            $right -= 2;
        }
    }

    /**
     * @param array<int, array<int, bool>> $matrix
     * @param array<int, array<int, bool>> $isFunction
     */
    private static function applyMaskAndFormat(array &$matrix, array &$isFunction, int $size, int $version): void
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$isFunction[$y][$x]) {
                    if (($x + $y) % 2 === 0) {
                        $matrix[$y][$x] = !$matrix[$y][$x];
                    }
                }
            }
        }

        $formatBits = 0x5412;

        $bits = [];
        for ($i = 0; $i < 15; $i++) {
            $bits[] = ($formatBits >> (14 - $i)) & 1;
        }

        $coords1 = [
            [0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8],
            [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0]
        ];
        for ($i = 0; $i < 15; $i++) {
            [$x, $y] = $coords1[$i];
            $matrix[$y][$x] = ($bits[$i] === 1);
        }

        $coords2 = [
            [8, $size - 1], [8, $size - 2], [8, $size - 3], [8, $size - 4],
            [8, $size - 5], [8, $size - 6], [8, $size - 7],
            [$size - 8, 8], [$size - 7, 8], [$size - 6, 8], [$size - 5, 8],
            [$size - 4, 8], [$size - 3, 8], [$size - 2, 8], [$size - 1, 8]
        ];
        for ($i = 0; $i < 15; $i++) {
            [$x, $y] = $coords2[$i];
            $matrix[$y][$x] = ($bits[$i] === 1);
        }
    }
}
