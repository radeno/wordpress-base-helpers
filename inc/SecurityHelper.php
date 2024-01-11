<?php

namespace helper;

require_once "PostTypeHelper.php";

class SecurityHelper
{
    public static function initActionsAndFilters()
    {
        self::removeOthers();
        self::disableEmojis();
        self::removeCommentsAction();
        self::removeArchiveFeedsAction();
        self::removeTrackbacksAction();
        self::removeDefaultPostTypeSupportsAction();
    }

    public static function removeOthers()
    {
        # Remove RSD
        \remove_action('wp_head', 'rsd_link');
        # Remove WLW
        \remove_action('wp_head', 'wlwmanifest_link');
        # Remove XFN
        \add_filter('avf_profile_head_tag', '__return_false');
        # Remove generator
        \remove_action('wp_head', 'wp_generator');
        \add_filter('the_generator', '__return_null');

        # Disable XML-RPC
        \add_filter('xmlrpc_enabled', '__return_null');
        \add_filter(
            'bloginfo_url',
            function ($output, $show) {
                if ($show == 'pingback_url') {
                    $output = '';
                }

                return $output;
            },
            10,
            2
        );

        // Remove Pingback
        \add_filter('template_redirect', function () {
            header_remove('X-Pingback');
        });
    }

    public static function disableEmojis()
    {
        \remove_action('wp_head', 'print_emoji_detection_script', 7);
        \remove_action('admin_print_scripts', 'print_emoji_detection_script');
        \remove_action('wp_print_styles', 'print_emoji_styles');
        \remove_action('admin_print_styles', 'print_emoji_styles');
        \remove_filter('the_content_feed', 'wp_staticize_emoji');
        \remove_filter('comment_text_rss', 'wp_staticize_emoji');
        \remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        \add_filter('emoji_svg_url', '__return_false');
    }

    public static function removeArchiveFeedsAction()
    {
        \add_filter('rewrite_rules_array', function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (strpos($rule, 'feed') !== false && preg_match('/^(?!(\(?feed))/', $rule)) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });

        \remove_action('wp_head', 'feed_links_extra', 3); // Display the links to the extra feeds such as category feeds
    }

    public static function removeCommentsAction()
    {
        \add_filter('rewrite_rules_array', function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (preg_match('/^.*\/comment-page/', $rule)) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }

    public static function removeTrackbacksAction()
    {
        \add_filter('rewrite_rules_array', function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (preg_match('/^.*\/trackback/', $rule)) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }

    public static function removeDefaultPostTypeSupportsAction()
    {
        \add_action('admin_init', function () {
            \remove_post_type_support('page', 'trackbacks');
            \remove_post_type_support('page', 'comments');

            \remove_post_type_support('post', 'trackbacks');
            \remove_post_type_support('post', 'comments');

            \remove_post_type_support('attachment', 'trackbacks');
            \remove_post_type_support('attachment', 'comments');
        });
    }
}
