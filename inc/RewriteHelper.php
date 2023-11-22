<?php

namespace helper;

class RewriteHelper
{
    public static function initActionsAndFilters()
    {
        self::postTypeTaxonomyLinksAction();
        self::removeDefaultDateRewritesFilter();
    }

    // public static function combinePostTaxonomyAction()
    // {
    //     \add_action('registered_taxonomy', function ($taxonomy, $object_type, $args) {
    //         \helper\TaxonomyHelper::taxRewrite($taxonomy, $object_type, $args);
    //     }, 10, 3);

    //     add_rewrite_rule($post_type . '/' . $taxonomy/, 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]&post_type=' . $post_type, 'top');
    // }


    public static function addPostTaxonomiesRewrite($postType, $taxonomies)
    {
        $postTypeObject = \get_post_type_object($postType);
        $taxonomiesObjects = array_map(function ($taxonomy) {
            return \get_taxonomy($taxonomy);
        }, $taxonomies);

        $postTypeRewriteSlug = $postTypeObject->rewrite['slug'];
        $taxonomiesRewriteSlugsString = join('/', array_map(function ($taxonomy) {
            return $taxonomy->rewrite['slug'] . '/' . '(.+?)';
        }, $taxonomiesObjects));

        $taxonomiesRedirectString = join('&', array_map(function ($taxonomy, $i) {
            $_i = $i + 1;
            return "{$taxonomy}=\$matches[{$_i}]";
        }, $taxonomies, array_keys($taxonomies)));

        $regexPaged = "{$postTypeRewriteSlug}/{$taxonomiesRewriteSlugsString}/page/([0-9]{1,})?$";
        $matchesIncrement = count($taxonomies) + 1;
        $redirectPaged = "index.php?post_type={$postType}&{$taxonomiesRedirectString}&paged=\$matches[{$matchesIncrement}]";
        \add_rewrite_rule($regexPaged, $redirectPaged, 'top');

        $regex = "{$postTypeRewriteSlug}/{$taxonomiesRewriteSlugsString}/?$";
        $redirect = "index.php?post_type={$postType}&{$taxonomiesRedirectString}";
        \add_rewrite_rule($regex, $redirect, 'top');
    }

    public static function removeIndependentTaxonomyRewrite($taxonomy)
    {
        $taxonomyObject = \get_taxonomy($taxonomy);

        \add_filter("rewrite_rules_array", function ($rules) use ($taxonomyObject) {
            foreach ($rules as $rule => $rewrite) {
                if (strpos($rule, $taxonomyObject->rewrite["slug"]) === 0) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }

    public static function removeAttachmentRewrite()
    {
        \add_filter("rewrite_rules_array", function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (strpos($rewrite, 'attachment=')) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }

    public static function modifyRewriteArgs($prettyLink, $args)
    {
        $newRewrite = [
            'slug' => $prettyLink,
            'with_front' => false,
        ];

        if ($args['rewrite'] === true || $args['rewrite'] === 1) {
            $args['rewrite'] = $newRewrite;
        } else {
            $args['rewrite'] = array_merge($args['rewrite'], $newRewrite);
        }

        return $args;
    }

    public static function getPostTaxonomiesTermsLink($postType, $taxonomies, $currentTerm, $currentTaxonomy)
    {
        $postTypeObject = \get_post_type_object($postType);
        $taxonomiesObjects = array_map(function ($taxonomy) {
            return \get_taxonomy($taxonomy);
        }, $taxonomies);

        $taxonomyTerms = [];
        foreach ($taxonomies as $taxonomy) {
            $taxonomyTerms[$taxonomy] = $taxonomy == $currentTaxonomy ? $currentTerm->slug : \get_query_var($taxonomy);
        }

        $taxonomiesSlugsString = join('/', array_map(function ($taxonomy) use ($taxonomyTerms) {
            return $taxonomy->rewrite['slug'] . '/' . $taxonomyTerms[$taxonomy->name];
        }, $taxonomiesObjects));

        return \home_url() . '/' . $postTypeObject->rewrite['slug'] . '/' . $taxonomiesSlugsString . '/';
    }

    public static function postTypeTaxonomyLinksAction()
    {
        \add_filter(
            'term_link',
            function ($url, $term, $taxonomy) {
                $taxonomy_obj = \get_taxonomy($taxonomy);

                if ($taxonomy_obj->_builtin || $taxonomy_obj->rewrite === false) {
                    return $url;
                }

                if (in_array(\get_post_type(), $taxonomy_obj->object_type, true)) {
                    $post_type = \get_post_type();
                } else {
                    $post_type = $taxonomy_obj->object_type[0];
                }

                $post_type_obj = \get_post_type_object($post_type);

                $post_type_slug = $post_type_obj->rewrite['slug'];

                if (!empty($post_type_slug)) {
                    $url = \home_url() . '/' . $post_type_slug . '/' . $taxonomy_obj->rewrite['slug'] . '/' . $term->slug . '/';
                }

                return $url;
            },
            10,
            3
        );

        \add_filter(
            'term_link',
            function ($url, $term, $taxonomy) {
                $taxonomyObject = \get_taxonomy($taxonomy);
                $postTypeObject = \get_post_type_object($taxonomyObject->object_type[0]);
                $taxonomies = array_values(array_intersect($postTypeObject->taxonomies, \helper\TaxonomyHelper::getTaxonomies()));

                return self::getPostTaxonomiesTermsLink($postTypeObject->name, $taxonomies, $term, $taxonomy);
            },
            20,
            3
        );
    }

    public static function removeDefaultDateRewritesFilter()
    {
        \add_filter('rewrite_rules_array', function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (strpos($rule, '([0-9]{4})/') === 0) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }
}
