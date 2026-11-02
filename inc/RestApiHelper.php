<?php

namespace helper;

class RestApiHelper
{
    public static function initActionsAndFilters()
    {
        self::addFeaturedImage();
        self::addStickyProperty();
        self::addStickyQueryParam();
        self::addAnyPostsRoute();
        self::addAnyTermsRoute();
    }

    /**
     * @deprecated 1.5.0 The posts controllers support modified_after since WordPress 5.7.
     */
    public static function addModifiedAfterFilter()
    {
        \_deprecated_function(__METHOD__, '1.5.0');
    }

    public static function addFeaturedImage()
    {
        $featuredImage = function ($object, $field_name, $request) {
            // $object only carries the requested fields, so featured_media is absent under _fields.
            $featuredMediaId = \get_post_thumbnail_id($object['id']);
            if ($featuredMediaId) {
                $img = \wp_get_attachment_metadata($featuredMediaId);
                if ($img === false) {
                    return null;
                }

                if (!empty($img['sizes'])) {
                    foreach ($img['sizes'] as $size => $meta) {
                        $attachmentImageSrc = \wp_get_attachment_image_src(
                            $featuredMediaId,
                            $size
                        );
                        $img['sizes'][$size]['source_url'] = $attachmentImageSrc[0] ?? null;
                    }
                }

                if (isset($img['url'])) {
                    $img['full_url'] = $img['url'];
                }

                unset($img['image_meta']);
                return $img;
            }
            return null;
        };

        \add_action('rest_api_init', function () use ($featuredImage) {
            $postTypes = array_filter(PostTypeHelper::getAllPostTypes(), function (
                $postType
            ) {
                return \post_type_supports($postType, 'thumbnail');
            });

            \register_rest_field(array_merge($postTypes, ['any_post']), 'featured_media_image', [
                'get_callback'    => $featuredImage,
                'update_callback' => null,
                'schema'          => null
            ]);
        });
    }

    public static function addStickyProperty()
    {
        $isSticky = function ($object, $field_name, $request) {
            return \is_sticky($object['id']);
        };

        \add_action('rest_api_init', function () use ($isSticky) {
            $postTypes = array_filter(PostTypeHelper::getPostTypes(), function (
                $postType
            ) {
                return \post_type_supports($postType, 'sticky_posts');
            });

            \register_rest_field($postTypes, 'sticky', [
                'get_callback'    => $isSticky,
                'update_callback' => null,
                'schema'          => null
            ]);
        });
    }

    public static function addStickyQueryParam()
    {
        \add_action('rest_api_init', function () {
            $postTypes = array_filter(PostTypeHelper::getPostTypes(), function (
                $postType
            ) {
                return \post_type_supports($postType, 'sticky_posts');
            });

            foreach ($postTypes as $postType) {
                \add_filter(
                    "rest_{$postType}_collection_params",
                    function (array $query_params, \WP_Post_Type $post_type) {
                        $query_params['sticky'] = [
                            'description' => __('Limit result set to items that are sticky.'),
                            'type'        => 'boolean',
                        ];

                        return $query_params;
                    },
                    10,
                    2
                );
            }
        });
    }

    public static function addAnyPostsRoute()
    {
        \add_action('rest_api_init', function () {
            $anyPosts = new AnyPostsRestController();
            $anyPosts->register_routes();
        });
    }

    public static function addAnyTermsRoute()
    {
        \add_action('rest_api_init', function () {
            $anyPosts = new AnyTermsRestController();
            $anyPosts->register_routes();
        });
    }
}
