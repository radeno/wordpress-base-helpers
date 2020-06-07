<?php

namespace helper;

class TaxonomyHelper
{
    public static function initActionsAndFilters()
    {
        self::autoSetParentTermsAction();
    }

    public static function getTaxonomies($output = 'names')
    {
        return \get_taxonomies(['_builtin' => false, 'public' => true], $output);
    }

    public static function getAllTaxonomies($output = 'names')
    {
        return array_merge(\get_taxonomies(['_builtin' => false, 'public' => true], $output), self::getTaxonomies($output));
    }

    public static function getAllRestTaxonomies($output = 'names')
    {
        return array_merge(
            \get_taxonomies(['_builtin' => false, 'public' => true, 'show_in_rest' => true], $output),
            \get_taxonomies(['_builtin' => true, 'public' => true, 'show_in_rest' => true], $output)
        );
    }

    public static function autoSetParentTermsAction()
    {

    /**
     * Automatically set/assign parent taxonomy terms to posts
     *
     * This function will automatically set parent taxonomy terms whenever terms are set on a post,
     * with the option to configure specific post types, and/or taxonomies.
     *
     *
     * @param int    $object_id  Object ID.
     * @param array  $terms      An array of object terms.
     * @param array  $tt_ids     An array of term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Whether to append new terms to the old terms.
     * @param array  $old_tt_ids Old array of term taxonomy IDs.
     */
        $autoSetParentTerms = function ($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {

            /**
             * We only want to move forward if there are taxonomies to set
             */
            if (empty($tt_ids)) {
                return false;
            }

            /**
             * Set specific post types to only set parents on.  Set $post_types = FALSE to set parents for ALL post types.
             */
            $post_types = false;
            if ($post_types !== false && ! in_array(\get_post_type($object_id), $post_types)) {
                return false;
            }

            /**
             * Set specific post types to only set parents on.  Set $post_types = FALSE to set parents for ALL post types.
             */
            $tax_types = false;
            if ($tax_types !== false && ! in_array(\get_post_type($object_id), $post_types)) {
                return false;
            }

            foreach ($tt_ids as $tt_id) {
                $parent = \wp_get_term_taxonomy_parent_id($tt_id, $taxonomy);

                if ($parent) {
                    \wp_set_post_terms($object_id, [$parent], $taxonomy, true);
                }
            }
        };

        \add_action('set_object_terms', $autoSetParentTerms, 9999, 6);
    }


    public static function taxonomyRewriteForPostTypes($taxonomy)
    {
        $taxonomyObject = \get_taxonomy($taxonomy);
        if ($taxonomyObject->_builtin || $taxonomyObject->rewrite === false) {
            return;
        }

        foreach ($taxonomyObject->object_type as $postType) {
            $postTypeObject = \get_post_type_object($postType);

            $regex    = sprintf('%s/(.+?)/page/([0-9]{1,})/?$', "{$postTypeObject->rewrite['slug']}/{$taxonomyObject->rewrite['slug']}");
            $redirect = "index.php?post_type={$postType}&{$taxonomy}=\$matches[1]&paged=\$matches[2]";
            \add_rewrite_rule($regex, $redirect, 'top');

            $regex    = sprintf('%s/(.+?)/?$', "{$postTypeObject->rewrite['slug']}/{$taxonomyObject->rewrite['slug']}");
            $redirect = "index.php?post_type={$postType}&{$taxonomy}=\$matches[1]";
            \add_rewrite_rule($regex, $redirect, 'top');
        }
    }
}
