<?php

namespace helper;

class QueryHelper
{
    public static function initActionsAndFilters()
    {
        self::extendOrderbyFieldsFilter();
        self::extendWhereFieldsFilter();
    }

    public static function extendOrderbyFieldsFilter()
    {
        $orderByExtension = function (string $orderby, \WP_Query $wp_query) {
            if (isset($wp_query->query_vars['orderby']) && 'title_last_index' === $wp_query->query_vars['orderby']) {
                global $wpdb;
                $orderby = " SUBSTRING_INDEX({$wpdb->posts}.post_title, ' ', -1 ) ";
            }

            return $orderby;
        };

        \add_filter('posts_orderby', $orderByExtension, 10, 2);
    }

    public static function extendWhereFieldsFilter()
    {
        $whereExtension = function (string $where, \WP_Query $wp_query) {
            if (isset($wp_query->query['post_content_like'])) {
                global $wpdb;
                $whereContent = str_replace("'", "\\'", $wp_query->query['post_content_like']);
                $where .= " AND {$wpdb->posts}.post_content LIKE '{$whereContent}' ";
            }

            return $where;
        };

        \add_filter('posts_where', $whereExtension, 10, 2);
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

    public static function getPostBySlug(string $slug, array $postTypes): ?\WP_Post
    {
        $query = new \WP_Query([
            "post_type"           => $postTypes,
            "post_name__in"       => [$slug],
            "post_status"         => "publish",
            "order"               => "ASC",
            "ignore_sticky_posts" => true,
        ]);

        return $query->posts[0] ?? null;
    }
}
