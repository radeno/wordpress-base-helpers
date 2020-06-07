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

            // Add active class when is single post type and archive is in menu
            $changedParentIds = [];
            $newSortedItems = array_map(function ($item) use ($setActiveItem, &$changedParentIds) {
                if ($item->type === 'post_type_archive' && \get_post_type() === $item->object) {
                    $item = $setActiveItem($item);
                    $changedParentIds[] = $item->menu_item_parent;
                }

                return $item;
            }, $sorted_menu_items);

            // Add all parents of active post type achive
            $newSortedItems = array_map(function ($item) use ($setActiveItem, &$changedParentIds) {
                if (in_array($item->object_id, $changedParentIds)) {
                    $item = $setActiveItem($item);
                }

                return $item;
            }, $newSortedItems);

            // Add active to all parents
            if (\is_singular()) {
                $ancestorsIds = \get_post_ancestors(\get_the_id());

                if (!empty($ancestorsIds)) {
                    $newSortedItems = array_map(function ($item) use ($setActiveItem, $ancestorsIds) {
                        if ($item->type === 'post_type' && in_array((int)$item->object_id, $ancestorsIds)) {
                            $item = $setActiveItem($item);
                        }

                        return $item;
                    }, $newSortedItems);
                }
            }

            return $newSortedItems;
        }, 10, 2);
    }
}
