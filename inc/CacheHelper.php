<?php

namespace helper;

class CacheHelper
{
    public static function initActionsAndFilters()
    {
        self::clearCacheAction();
    }

    public static function clearCacheAction()
    {
        \add_action('update_option_sticky_posts', function () {
            \wp_cache_delete('alloptions', 'options');
        });

        \add_action('save_post', function ($post_id) {
            if (class_exists('\WP_REST_Cache')) {
                \wp_cache_flush();
            }
        });
    }
}
