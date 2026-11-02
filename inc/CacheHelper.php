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
    }
}
