<?php

namespace helper;

class FileHelper
{
    public static function initActionsAndFilters()
    {
        self::addAllowedContentTypes();
    }

    public static function addAllowedContentTypes()
    {
        \add_filter('upload_mimes', function ($contentTypes) {
            $contentTypes['xml'] = 'application/xml';
            $contentTypes['asice'] = 'application/vnd. etsi.asic-e+zip';

            return $contentTypes;
        });
    }

    /**
     * @param $url
     * @return false|string
     */
    public static function getRemoteFileSize(string $url)
    {
        $parsedUrl      = parse_url($url);
        $domain         = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        $escapedUrlPath = join('/', array_map('rawurlencode', explode('/', str_replace($domain, '', $url))));
        $escapedUrl     = $domain . $escapedUrlPath;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $escapedUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        // $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        $data = curl_exec($ch);

        curl_close($ch);
        if ($data === false) {
            return false;
        }

        $contentLength = 0;
        if (preg_match('/Content-Length: (\d+)/i', $data, $matches)) {
            $contentLength = (int)$matches[1];
        }

        return \size_format($contentLength);
    }
}
