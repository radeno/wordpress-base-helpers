<?php

namespace helper;

class MenuHelper
{
    public static function initActionsAndFilters()
    {
        self::addClassesToActiveItemAction();
    }

    public static function addClassesToActiveItemAction()
    {
        \add_action('wp_nav_menu_objects', function ($sorted_menu_items, $args) {
            $setActiveItem = function ($item) {
                $item->current_item_ancestor = true;
                $item->current_item_parent = true;
                $item->classes[] = 'current-menu-ancestor';

                return $item;
            };

            $addedParentIds = array_unique(array_map(function ($item) {
                if (!\is_singular()) {
                    return null;
                }

                if ($item->type === 'post_type_archive' && \get_post_type() === $item->object) {
                    return $item->menu_item_parent;
                }

                if ($item->type === 'taxonomy') {
                    $postTermsIds = \wp_get_post_terms(\get_the_id(), $item->object, ['fields' => 'ids']);
                    if (! \is_wp_error($postTermsIds) && in_array($item->object_id, $postTermsIds)) {
                        return $item->menu_item_parent;
                    }
                }

                if ($item->type === 'post_type') {
                    $ancestorsIds = \get_post_ancestors(\get_the_id());
                    if (!empty($ancestorsIds)) {
                        if (in_array((int)$item->object_id, $ancestorsIds)) {
                            return $item->ID;
                        }
                    }
                }
            }, $sorted_menu_items));

            return array_map(function ($item) use ($setActiveItem, $addedParentIds) {
                if (in_array($item->ID, $addedParentIds)) {
                    $item = $setActiveItem($item);
                }

                return $item;
            }, $sorted_menu_items);
        }, 10, 2);
    }
}
