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
        \add_filter('wp_nav_menu_objects', function ($sorted_menu_items, $args) {
            if (!\is_singular()) {
                return $sorted_menu_items;
            }

            $setParentActiveItem = function ($item) {
                $item->current_item_ancestor = true;
                $item->current_item_parent = true;
                $item->classes[] = 'current-menu-ancestor';

                return $item;
            };

            $setCurrentActiveItem = function ($item) {
                $item->classes[] = 'current-menu-item';

                return $item;
            };

            $postId = \get_the_id();
            $addedParentIds = [];
            $addedCurrentIds = [];
            foreach ($sorted_menu_items as $item) {
                if ($item->type === 'post_type_archive' && \get_post_type() === $item->object) {
                    $addedCurrentIds[] = $item->ID;
                    $addedParentIds[] = $item->menu_item_parent;
                    continue;
                }

                if ($item->type === 'taxonomy') {
                    $postTermsIds = \wp_get_post_terms($postId, $item->object, ['fields' => 'ids']);
                    if (! \is_wp_error($postTermsIds) && in_array($item->object_id, $postTermsIds)) {
                        $addedCurrentIds[] = $item->ID;
                        $addedParentIds[] = $item->menu_item_parent;
                        continue;
                    }
                }

                if ($item->type === 'post_type') {
                    $ancestorsIds ??= \get_post_ancestors($postId);
                    if (in_array((int)$item->object_id, $ancestorsIds)) {
                        $addedCurrentIds[] = $item->ID;
                        $addedParentIds[] = $item->menu_item_parent;
                        continue;
                    }
                }
            }

            return array_map(function ($item) use ($setParentActiveItem, $setCurrentActiveItem, $addedParentIds, $addedCurrentIds) {
                if (in_array($item->ID, $addedParentIds)) {
                    $item = $setParentActiveItem($item);
                }
                if (in_array($item->ID, $addedCurrentIds)) {
                    $item = $setCurrentActiveItem($item);
                }

                return $item;
            }, $sorted_menu_items);
        }, 10, 2);
    }
}
