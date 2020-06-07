<?php

namespace helper;

require_once "PostTypeHelper.php";
require_once "TaxonomyHelper.php";
require_once "AnyPostsRestController.php";
require_once "AnyTermsRestController.php";

function rest_is_field_included($field, $fields)
{
    if (in_array($field, $fields, true)) {
        return true;
    }
    foreach ($fields as $accepted_field) {
        // Check to see if $field is the parent of any item in $fields.
        // A field "parent" should be accepted if "parent.child" is accepted.
        if (strpos($accepted_field, "$field.") === 0) {
            return true;
        }
        // Conversely, if "parent" is accepted, all "parent.child" fields should
        // also be accepted.
        if (strpos($field, "$accepted_field.") === 0) {
            return true;
        }
    }
    return false;
}

class RestApiHelper
{
    public static function initActionsAndFilters()
    {
        self::addModifiedAfterFilter();
        self::addFeaturedImage();
        self::addStickyProperty();
        self::addStickyQueryParam();
        self::removeLinks();
        self::addAnyPostsRoute();
        self::addAnyTermsRoute();
    }

    public static function addModifiedAfterFilter()
    {
        \add_action('rest_api_init', function () {
            foreach (PostTypeHelper::getAllPostTypes() as $postType) {
                \add_filter(
                    "rest_{$postType}_collection_params",
                    function (array $query_params, \WP_Post_Type $post_type) {
                        $query_params['modified_after'] = [
                            'description' => __('Limit response to posts modified after a given ISO8601 compliant date.'),
                            'type'        => 'string',
                            'format'      => 'date-time'
                        ];
                        return $query_params;
                    },
                    10,
                    2
                );

                \add_filter(
                    "rest_${postType}_query",
                    function (array $args, \WP_REST_Request $request) {
                        if (isset($request['modified_after']) && !isset($request['after'])) {
                            $args['date_query'][0]['after'] = $request['modified_after'];
                            $args['date_query'][0]['column'] = 'post_modified';
                        }
                        return $args;
                    },
                    10,
                    2
                );
            }
        });
    }

    public static function addFeaturedImage()
    {
        $featuredImage = function ($object, $field_name, $request) {
            if ($object['featured_media']) {
                $img = \wp_get_attachment_metadata($object['featured_media']);
                foreach ($img['sizes'] as $size => $meta) {
                    $attachmentImageSrc = \wp_get_attachment_image_src(
                        $object['featured_media'],
                        $size
                    );
                    $img['sizes'][$size]['source_url'] = $attachmentImageSrc[0] ?? null;
                }

                if ($img['url']) {
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

    public static function removeLinks()
    {
        \add_action('rest_api_init', function () {
            $types = array_merge(PostTypeHelper::getAllPostTypes(), TaxonomyHelper::getAllTaxonomies());
            foreach ($types as $type) {
                \add_filter(
                    "rest_prepare_{$type}",
                    function ($data, $post, $request) {
                        foreach ($data->get_links() as $_linkKey => $_linkVal) {
                            $data->remove_link($_linkKey);
                        }

                        return $data;
                    },
                    1,
                    3
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
