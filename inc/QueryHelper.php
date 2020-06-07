<?php

namespace helper;

class QueryHelper
{
    public static function initActionsAndFilters()
    {
        self::extendOrderbyFieldsAction();
    }

    public static function extendOrderbyFieldsAction()
    {
        $orderByExtension = function ($orderby, $wp_query) {
            if (isset($wp_query->query_vars['orderby']) && 'title_last_index' === $wp_query->query_vars['orderby']) {
                global $wpdb;
                $orderby = " SUBSTRING_INDEX({$wpdb->posts}.post_title, ' ', -1 ) ";
            }

            return $orderby;
        };

        \add_filter('posts_orderby', $orderByExtension, 10, 2);
    }

    public static function getAllPosts($postType, array $queryArgs = [])
    {
        return new \WP_Query(
            array_merge(
                [
                    'post_type'      => $postType,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                ],
                $queryArgs
            )
        );
    }

    public static function getAllPostsIds(string $postType, array $queryArgs = [])
    {
        return self::getAllPosts($postType, array_merge(['fields' => 'ids'], $queryArgs));
    }

    public static function getPostsByIds($ids, $queryArgs = [])
    {
        return new \WP_Query(
            array_merge(
                [
                    'post_type'           => 'any',
                    'post__in'            => $ids ?? [0],
                    'posts_per_page'      => -1,
                    'post_status'         => 'publish',
                    'order'               => 'ASC',
                    'orderby'             => 'post__in',
                    'ignore_sticky_posts' => true,
                ],
                $queryArgs
            )
        );
    }
}
