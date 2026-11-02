<?php

namespace helper;

class FileHelper
{
    private const TIMEOUT       = 10;
    private const MAX_REDIRECTS = 5;

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
        // Redirects are followed here rather than by WordPress, which repeats a
        // HEAD as a GET after a 302 and would download the whole file.
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $response = \wp_remote_head($url, [
                'timeout' => self::TIMEOUT,
                // On a compressed response Content-Length describes the encoded body,
                // not the file, so ask the origin to send the bytes as they are.
                'headers' => ['Accept-Encoding' => 'identity'],
            ]);

            $code     = \wp_remote_retrieve_response_code($response);
            $location = \wp_remote_retrieve_header($response, 'location');
            if ($code < 300 || $code >= 400 || ! is_string($location) || $location === '') {
                break;
            }

            $url = \WP_Http::make_absolute_url($location, $url);
        }

        // An error page carries a Content-Length of its own, so only a 200 speaks about the file.
        if ($code !== 200) {
            return false;
        }

        $length = \wp_remote_retrieve_header($response, 'content-length');

        return is_numeric($length) ? \size_format((int) $length) : false;
    }
}
