<?php

namespace helper;

class ContentTypeHelper
{
    public static function registerTaxonomyToPostType($taxonomy, $postType)
    {
        \register_taxonomy_for_object_type($taxonomy, $postType);

        global $wp_post_types;

        if (!in_array($taxonomy, $wp_post_types[$postType]->taxonomies)) {
            $wp_post_types[$postType]->taxonomies[] = $taxonomy;
        }
    }
}
