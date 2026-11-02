<?php

namespace helper;

class AdminHelper
{
    /**
     * Control over the whole site and its accounts: settings, plugins, themes, core updates,
     * unfiltered uploads and markup, and other users; on a single site edit_users reaches an
     * administrator's password too. Only a user who holds such a capability may hand it out
     * (restrictRoleAssignFilter()) or see and manage the accounts that hold it (protectSiteAdminsFilter()).
     */
    private const SITE_ADMIN_CAPABILITIES = [
        'manage_options',
        'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins',
        'switch_themes', 'install_themes', 'update_themes', 'delete_themes', 'edit_themes',
        'update_core', 'edit_files',
        'unfiltered_upload', 'unfiltered_html',
        'create_users', 'edit_users', 'promote_users', 'remove_users', 'delete_users',
    ];

    public static function initActionsAndFilters()
    {
        self::reorganizeNavigationAction();
        self::overrideStyleAction();
        self::removeUpdateNotificationAction();
        self::restrictRoleAssignFilter();
        self::protectSiteAdminsFilter();
        self::sortRolesFilter();
        self::removeMetaBoxesAction();
        self::removeAdminDashboards();
        self::addLastModifiedColumn();
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

        // Users who cannot edit others' posts see only their own files in the Media
        // Library. The media modal in the editor keeps showing every file.
        \add_action('load-upload.php', function () {
            if (! \current_user_can('edit_others_posts')) {
                // List mode.
                \add_action('pre_get_posts', function (\WP_Query $query) {
                    if ($query->is_main_query()) {
                        $query->set('author', \get_current_user_id());
                    }
                });
            }
        });

        \add_filter('ajax_query_attachments_args', function ($query = []) {
            // Grid mode loads over admin-ajax.php, which has no screen, so tell it
            // apart from the media modal by the page that sent the request.
            $referer = \wp_get_referer();
            if (
                $referer
                && parse_url($referer, PHP_URL_PATH) === parse_url(\admin_url('upload.php'), PHP_URL_PATH)
                && ! \current_user_can('edit_others_posts')
            ) {
                $query['author'] = \get_current_user_id();
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

            foreach ($all_roles as $name => $role) {
                if (self::outranks($role['capabilities'], $user)) {
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

    /**
     * Keeps the accounts that outrank the current user off the Users screen, and keeps them
     * from being edited, given another role or deleted, also by URL or through the REST API.
     */
    public static function protectSiteAdminsFilter()
    {
        \add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
            if (in_array($cap, ['edit_user', 'promote_user', 'delete_user', 'remove_user'], true) && ! empty($args[0])) {
                $user   = \get_userdata($user_id);
                $target = \get_userdata((int) $args[0]);
                if ($user && $target && self::outranks($target->allcaps, $user)) {
                    $caps[] = 'do_not_allow';
                }
            }

            return $caps;
        }, 10, 4);

        \add_filter('users_list_table_query_args', function ($args) {
            $withheld = self::withheldSiteAdminCapabilities(\wp_get_current_user());
            if ($withheld) {
                $args['capability__not_in'] = [...(array) ($args['capability__not_in'] ?? []), ...$withheld];
            }

            return $args;
        });

        // The role links above the table.
        \add_filter('views_users', function ($views) {
            foreach (\wp_roles()->roles as $name => $role) {
                if (self::outranks($role['capabilities'], \wp_get_current_user())) {
                    unset($views[$name]);
                }
            }

            return $views;
        });
    }

    /**
     * The site-admin capabilities $user is not granted. Granted capabilities are compared, not
     * current_user_can(): DISALLOW_FILE_EDIT and the like deny some of them even to administrators.
     *
     * @return string[]
     */
    private static function withheldSiteAdminCapabilities(\WP_User $user): array
    {
        return array_values(array_filter(self::SITE_ADMIN_CAPABILITIES, fn ($capability) => empty($user->allcaps[$capability])));
    }

    /**
     * Whether $capabilities, a role's or an account's, grant a site-admin capability that $user lacks.
     */
    private static function outranks(array $capabilities, \WP_User $user): bool
    {
        return array_any(self::withheldSiteAdminCapabilities($user), fn ($capability) => ! empty($capabilities[$capability]));
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

        // Core hooks the panel in wp-admin/includes/admin-filters.php, which loads after plugins.
        \add_action('admin_init', function () {
            \remove_action('welcome_panel', 'wp_welcome_panel');
        });
    }

    public static function addLastModifiedColumn() {
        // Register Modified Date Column for both posts & pages
        $modifiedColumnRegister = function ($columns) {
            $columns["modified"] = __("Last Modified");
            return $columns;
        };

        $modifiedColumnDisplay = function ($column_name, $post_id) {
            if ($column_name !== "modified") {
                return;
            }

            $author = \get_the_modified_author();
            echo \esc_html(\get_the_modified_date() . " " . \get_the_modified_time());
            echo "<br /><small>" . \esc_html__("by") . " <strong>" . (empty($author) ? \esc_html__("UNKNOWN") : \esc_html($author)) . "</strong></small>";
        };

        $modifiedColumnRegisterSortable = function ($columns) {
            $columns["modified"] = "modified";
            return $columns;
        };

        \add_filter("manage_pages_columns", $modifiedColumnRegister);
        \add_filter("manage_posts_columns", $modifiedColumnRegister);
        \add_action("manage_posts_custom_column", $modifiedColumnDisplay, 10, 2);
        \add_action("manage_pages_custom_column", $modifiedColumnDisplay, 10, 2);

        \add_action("admin_init", function () use ($modifiedColumnRegisterSortable) {
            foreach (PostTypeHelper::getAllPostTypes() as $postType) {
                \add_filter("manage_edit-{$postType}_sortable_columns", $modifiedColumnRegisterSortable);
            }
        });
    }
}
