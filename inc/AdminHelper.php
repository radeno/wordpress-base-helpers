<?php

namespace helper;

require_once 'PostTypeHelper.php';

class AdminHelper
{
    public static function initActionsAndFilters()
    {
        self::reorganizeNavigationAction();
        self::overrideStyleAction();
        self::removeUpdateNotificationAction();
        self::restrictRoleAssignFilter();
        self::sortRolesFilter();
        self::removeMetaBoxesAction();
        self::removeAdminDashboards();
    }

    public static function reorganizeNavigationAction()
    {
        \add_action('admin_menu', function () {
            \add_menu_page(
                __('Menu'),
                __('Menu'),
                'edit_theme_options',
                'nav-menus.php',
                '',
                'dashicons-list-view',
                68
            );
        });

        \add_action('admin_init', function () {
            if (! \current_user_can('edit_others_posts') && !isset($_GET['attachment-filter'])) {
                $_GET['attachment-filter'] = 'mine';
            }
        });

        \add_filter('ajax_query_attachments_args', function ($query = []) {
            if (!\current_user_can('edit_others_posts' && empty($query['uploadedTo'])) && \get_current_screen()->parent_base == 'upload') {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $query['author'] = $user_id;
                }
            }

            return $query;
        }, 10, 1);
    }

    public static function overrideStyleAction()
    {
        \add_action('admin_head', function () {
            echo '<style>
                .rwmb-field:not(:last-of-type) { position: relative; padding: 0 0 12px; margin: 0 0 12px; border-bottom: 1px dashed rgb(210, 210, 210); }
                .rwmb-clone { margin-bottom: 6px; }
                .rwmb-field .select2-container { max-width: 100%; }
            </style>';
        }, 99);
    }

    public static function removeUpdateNotificationAction()
    {
        \add_action('admin_head', function () {
            if (!\current_user_can('update_core')) {
                \remove_action('admin_notices', 'update_nag', 3);
            }
        }, 1);
    }

    public static function removeMetaBoxesAction()
    {
        \add_action('admin_menu', function () {
            if (!\current_user_can('manage_options')) {
                foreach (PostTypeHelper::getAllPostTypes() as $postType) {
                    \remove_meta_box('postcustom', $postType, 'normal');
                }
            }
        });
    }

    public static function restrictRoleAssignFilter()
    {
        \add_filter('editable_roles', function ($all_roles) {
            $user = wp_get_current_user();
            $next_level = 'level_' . ($user->user_level + 1);

            foreach ($all_roles as $name => $role) {
                if (isset($role['capabilities'][$next_level])) {
                    unset($all_roles[$name]);
                }
            }

            if (empty($user->allcaps['create_users_builtin_roles'])) {
                unset($all_roles['subscriber']);
                unset($all_roles['contributor']);
                unset($all_roles['author']);
                unset($all_roles['editor']);
            }

            return $all_roles;
        });
    }

    public static function sortRolesFilter()
    {
        \add_filter('editable_roles', function ($all_roles) {
            uasort(
                $all_roles,
                function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                }
            );

            return $all_roles;
        });
    }

    public static function removeAdminDashboards()
    {
        \add_action('admin_menu', function () {
            \remove_meta_box('dashboard_quick_press', 'dashboard', 'normal'); //Quick Press widget
            \remove_meta_box('dashboard_primary', 'dashboard', 'normal'); //WordPress.com Blog
            \remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
            \remove_meta_box('network_dashboard_right_now', 'dashboard', 'normal');
            \remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        });

        \remove_action('welcome_panel', 'wp_welcome_panel');
    }
}
