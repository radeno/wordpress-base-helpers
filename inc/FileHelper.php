<?php

namespace helper;

class FileHelper
{
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT         = 10;

    /**
     * Size of a remote file, read from the Content-Length of a HEAD request.
     *
     * Blocking network call. Prefer the `filesize` entry of the attachment
     * metadata (WP 6.0+); this is the fallback for attachments that lack it.
     *
     * @param $url
     * @return false|string Human readable size, or false when it cannot be determined.
     */
    public static function getRemoteFileSize(string $url)
    {
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
            return false;
        }

        // Only the path gets escaped; a port or a query string (Azure SAS token)
        // must survive untouched or the request never reaches the file.
        $escapedPath = join('/', array_map('rawurlencode', explode('/', $parsedUrl['path'] ?? '')));

        $escapedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host']
            . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '')
            . $escapedPath
            . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $escapedUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        // On a compressed response Content-Length describes the encoded body,
        // not the file, so ask the origin to send the bytes as they are.
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Encoding: identity']);

        $data         = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // An error page carries a Content-Length of its own, so only a 200 speaks about the file.
        if ($data === false || $responseCode !== 200) {
            return false;
        }

        // FOLLOWLOCATION keeps the headers of every hop; the last block is the 200.
        if (!preg_match_all('/^Content-Length:\s*(\d+)/im', $data, $matches)) {
            return false;
        }

        return \size_format((int)end($matches[1]));
    }
}
