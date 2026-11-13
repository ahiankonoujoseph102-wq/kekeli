<?php

namespace App\Projects;

/**
 * A small reader and writer of « tar » archives (formats ustar, GNU and pax, the ones made by the « tar » command), to
 * build the cleaned archive of a version without any external program.
 */
class TarArchive
{
    /**
     * Regular files of an archive, with their content and their permissions.
     *
     * @return \Generator<string, array{0: string, 1: int}>
     */
    public static function entries(string $tar): \Generator
    {
        $offset = 0;
        $length = strlen($tar);
        $longName = null;

        while ($offset + 512 <= $length) {
            $header = substr($tar, $offset, 512);
            if (trim($header, "\0") === '') {
                break;
            }

            $name = rtrim(substr($header, 0, 100), "\0");
            $mode = octdec(trim(substr($header, 100, 8), "\0 ") ?: '644');
            $size = (int) octdec(trim(substr($header, 124, 12), "\0 ") ?: '0');
            $type = $header[156];
            $prefix = rtrim(substr($header, 345, 155), "\0");
            if (substr($header, 257, 5) === 'ustar' && $prefix !== '') {
                $name = $prefix.'/'.$name;
            }

            $content = substr($tar, $offset + 512, $size);
            $offset += 512 + (int) (ceil($size / 512) * 512);

            if ($type === 'L') {
                $longName = rtrim($content, "\0");

                continue;
            }
            if ($type === 'x') {
                if (preg_match('/^\d+ path=(.*)$/m', $content, $match)) {
                    $longName = $match[1];
                }

                continue;
            }
            if ($longName !== null) {
                $name = $longName;
                $longName = null;
            }

            if ($type === '0' || $type === "\0") {
                yield $name => [$content, (int) $mode & 0777];
            }
        }
    }

    /** One regular file in the « ustar » format (with a GNU long name before it when the path is long). */
    public static function entry(string $path, string $content, int $mode = 0644): string
    {
        $out = '';
        if (strlen($path) > 99) {
            $out .= self::header('././@LongLink', strlen($path) + 1, 'L', 0644).self::padded($path."\0");
        }

        return $out.self::header(substr($path, 0, 99), strlen($content), '0', $mode ?: 0644).self::padded($content);
    }

    private static function header(string $name, int $size, string $type, int $mode): string
    {
        $header = str_pad($name, 100, "\0")
            .sprintf('%07o', $mode)."\0"
            .sprintf('%07o', 1000)."\0"
            .sprintf('%07o', 1000)."\0"
            .sprintf('%011o', $size)."\0"
            .sprintf('%011o', 1767225600)."\0"
            .str_repeat(' ', 8)
            .$type
            .str_repeat("\0", 100)
            ."ustar\0".'00'
            .str_pad('kekeli', 32, "\0")
            .str_pad('kekeli', 32, "\0")
            .str_repeat("\0", 8 + 8 + 155 + 12);
        $sum = array_sum(array_map('ord', str_split($header)));

        return substr_replace($header, sprintf('%06o', $sum)."\0 ", 148, 8);
    }

    private static function padded(string $content): string
    {
        $rest = strlen($content) % 512;

        return $content.($rest ? str_repeat("\0", 512 - $rest) : '');
    }
}
