<?php

namespace helper;

class CapabilityHelper
{
    public static function initActionsAndFilters()
    {
        self::changeWPFormsCapability();
        self::enablePostTagCapabilities();
    }

    public static function addPostTypeCapabilities($capabilityBase, $roleName = 'administrator')
    {
        $role = \get_role($roleName);

        // Meta capabilities
        $role->add_cap('edit_' . $capabilityBase[0]);
        $role->add_cap('read_' . $capabilityBase[0]);
        $role->add_cap('delete_' . $capabilityBase[0]);

        // Primitive capabilities used outside of map_meta_cap():
        $role->add_cap('edit_' . $capabilityBase[1]);
        $role->add_cap('edit_others_' . $capabilityBase[1]);
        $role->add_cap('publish_' . $capabilityBase[1]);
        $role->add_cap('read_private_' . $capabilityBase[1]);

        // Primitive capabilities used within map_meta_cap():
        $role->add_cap('delete_' . $capabilityBase[1]);
        $role->add_cap('delete_private_' . $capabilityBase[1]);
        $role->add_cap('delete_published_' . $capabilityBase[1]);
        $role->add_cap('delete_others_' . $capabilityBase[1]);
        $role->add_cap('edit_private_' . $capabilityBase[1]);
        $role->add_cap('edit_published_' . $capabilityBase[1]);
    }

    public static function addTaxonomyCapabilities($capabilityBase, $roleName = 'administrator')
    {
        $role = \get_role($roleName);

        $role->add_cap('manage_' . $capabilityBase[1]);
        $role->add_cap('edit_' . $capabilityBase[1]);
        $role->add_cap('delete_' . $capabilityBase[1]);
        $role->add_cap('assign_' . $capabilityBase[1]);

        $role->add_cap('manage_' . $capabilityBase[0]);
        $role->add_cap('edit_' . $capabilityBase[0]);
        $role->add_cap('delete_' . $capabilityBase[0]);
        $role->add_cap('assign_' . $capabilityBase[0]);
    }

    public static function changeWPFormsCapability()
    {
        if (!\is_admin()) {
            return false;
        }

        \add_action('init', function () {
            if (!\current_user_can('manage_wpforms')) {
                \add_filter('wpforms_manage_cap', function () {
                    return 'manage_wpforms';
                });
            }
        });
    }

    public static function enablePostTagCapabilities()
    {
        \add_filter('map_meta_cap', function ($required_caps, $cap, $user_id, $args) {
            switch ($cap) {
                case 'manage_post_tags':
                case 'edit_post_tags':
                case 'delete_post_tags':
                case 'assign_post_tags':
                    $required_caps = [$cap];
                    break;
            }

            // print_r($required_caps);
            // print_r($cap);

            return $required_caps;
        }, 10, 4);

        \add_action('admin_init', function () {
            global $wp_taxonomies;
            $wp_taxonomies['post_tag']->hierarchical = true;
            $wp_taxonomies['post_tag']->meta_box_cb = "post_categories_meta_box";
            $wp_taxonomies['post_tag']->meta_box_sanitize_cb = "taxonomy_meta_box_sanitize_cb_checkboxes";
        }, 10);

        \add_action('admin_head', function () {
            echo '<style type="text/css">
                #newpost_tag_parent {
                    display: none;
                }
            </style>';
        });
    }
}
