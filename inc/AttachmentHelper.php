<?php

namespace helper;

require_once "FileHelper.php";

class AttachmentHelper
{
    public static function getAttachmentObject($attachmentId)
    {
        $originalAttachment = \get_post($attachmentId);
        if (is_null($originalAttachment)) {
            return null;
        }

        $attachment    = clone $originalAttachment;
        $url           = \wp_get_attachment_url($attachmentId);
        $fileSize      = FileHelper::getRemoteFileSize($url);
        $fileExtension = strtoupper(pathinfo($url, PATHINFO_EXTENSION));

        $attachment->url        = $url;
        $attachment->human_size = $fileSize;
        $attachment->extension  = $fileExtension;

        return $attachment;
    }

    public static function getMimeTypes()
    {
        return \get_allowed_mime_types();

        // $onlyDocumentTypes = function ($contentType) {
        //     return strpos($contentType, 'image/') === false && strpos($contentType, 'video/') === false && strpos($contentType, 'audio/') === false;
        // };

        // return array_filter(
        //     array_values(\get_allowed_mime_types()),
        //     $onlyDocumentTypes
        // );
    }

    public static function getMimeTypesByExtensions(array $extensions)
    {
        return array_intersect_key(
            self::getMimeTypes(),
            array_flip($extensions)
        );
    }

    public static function changeUploadsUrlToCdnFilter($originalUrl, $newUrl)
    {
        $replaceUrl = function ($url) use ($originalUrl, $newUrl) {
            return str_replace($originalUrl, $newUrl, $url);
        };

        \add_filter('wp_get_attachment_url', function ($url, $post_id) use ($replaceUrl) {
            return $replaceUrl($url);
        }, 10, 2);

        \add_filter('wp_get_attachment_image_src', function ($image, $attachment_id, $size, $icon) use ($replaceUrl) {
            if (!$image) {
                return $image;
            }

            $image[0] = $replaceUrl($image[0]);
            return $image;
        }, 10, 4);

        \add_filter('wp_calculate_image_srcset', function ($sources, $size_array, $image_src, $image_meta, $attachment_id) use ($replaceUrl) {
            foreach ($sources as $size => $source) {
                $sources[$size]['url'] = $replaceUrl($source['url']);
            }

            return $sources;
        }, 10, 5);
    }
}
