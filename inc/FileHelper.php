<?php

namespace helper;

class FileHelper
{
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT         = 10;
    private const MAX_REDIRECTS   = 5;

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
        // cURL would guess a scheme for anything else.
        if (! preg_match('#^https?://[^/]#i', $url)) {
            return false;
        }

        // Escape only what a URL cannot carry as it is, such as spaces or the bytes of a
        // UTF-8 file name, so an already escaped path and a query string (Azure SAS token)
        // reach the origin untouched.
        $escapedUrl = preg_replace_callback('/[\x00-\x20"<>\\\\^`{|}\x7F-\xFF]+/', fn ($matches) => rawurlencode($matches[0]), $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $escapedUrl);
        // Also after a redirect, which cURL would otherwise follow to FTP.
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // cURL keeps every hop a HEAD and reuses the connection to the same host.
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, self::MAX_REDIRECTS);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        // On a compressed response Content-Length describes the encoded body,
        // not the file, so ask the origin to send the bytes as they are.
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Encoding: identity']);

        // An error page carries a Content-Length of its own, so only a 200 speaks about the file.
        if (curl_exec($ch) === false || curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
            return false;
        }

        // The Content-Length of the final hop, or -1 when it sent none.
        $length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T);

        return $length >= 0 ? \size_format($length) : false;
    }
}
