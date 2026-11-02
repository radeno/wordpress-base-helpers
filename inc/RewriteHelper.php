<?php

namespace helper;

class RewriteHelper
{
    public static function initActionsAndFilters()
    {
        self::postTypeTaxonomyLinksAction();
        self::removeDefaultDateRewritesFilter();
    }

    /**
     * Adds /<post type archive>/<taxonomy>/<term>/… rules, built the way core builds
     * the post type archive rules (WP_Post_Type::add_rewrite_rules()) and the
     * taxonomy's own term rules (WP_Taxonomy::add_rewrite_rules()).
     */
    public static function addPostTaxonomiesRewrite($postType, $taxonomies)
    {
        global $wp_rewrite;

        $postTypeObject    = \get_post_type_object($postType);
        $taxonomiesObjects = array_map(fn ($taxonomy) => \get_taxonomy($taxonomy), array_values($taxonomies));

        // The built-in post and page types register rewrite => false, so there is no slug to use.
        $archivePath = self::postTypeArchivePath($postTypeObject);
        if ($archivePath === null || array_filter($taxonomiesObjects, fn ($taxonomy) => ! is_array($taxonomy->rewrite))) {
            return;
        }

        $regex = $archivePath;
        $query = "index.php?post_type={$postType}";
        foreach ($taxonomiesObjects as $i => $taxonomy) {
            $tag    = $taxonomy->hierarchical && $taxonomy->rewrite['hierarchical'] ? '(.+?)' : '([^/]+)';
            $regex .= "/{$taxonomy->rewrite['slug']}/{$tag}";
            $query .= ($taxonomy->query_var ? "&{$taxonomy->query_var}=" : "&taxonomy={$taxonomy->name}&term=") . '$matches[' . ($i + 1) . ']';
        }

        if ($postTypeObject->rewrite['pages']) {
            $page = count($taxonomiesObjects) + 1;
            \add_rewrite_rule("{$regex}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$", "{$query}&paged=\$matches[{$page}]", 'top');
        }
        \add_rewrite_rule("{$regex}/?$", $query, 'top');
    }

    /**
     * The post type archive path that core's rules and get_post_type_archive_link() use,
     * or null when the post type has no resolved rewrite.
     */
    private static function postTypeArchivePath(\WP_Post_Type $postTypeObject): ?string
    {
        global $wp_rewrite;

        // Core resolves rewrite, and adds its rules, only in the admin or with pretty permalinks.
        if (! is_array($postTypeObject->rewrite) || (! \is_admin() && ! $wp_rewrite->using_permalinks())) {
            return null;
        }

        $slug = is_string($postTypeObject->has_archive) && $postTypeObject->has_archive !== '' ? $postTypeObject->has_archive : $postTypeObject->rewrite['slug'];

        return $postTypeObject->rewrite['with_front'] ? substr($wp_rewrite->front, 1) . $slug : $wp_rewrite->root . $slug;
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

    public static function removeRewrite(string $rewriteString)
    {
        \add_filter("rewrite_rules_array", function ($rules) use ($rewriteString) {
            foreach ($rules as $rule => $rewrite) {
                if (strpos($rewrite, $rewriteString) !== false) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }
    public static function removeAttachmentRewrite()
    {
        self::removeRewrite('attachment=');
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

                // The built-in post and page types register rewrite => false.
                $post_type_slug = $post_type_obj->rewrite['slug'] ?? null;

                if (!empty($post_type_slug)) {
                    $url = \home_url() . '/' . $post_type_slug . '/' . $taxonomy_obj->rewrite['slug'] . '/' . $term->slug . '/';
                }

                return $url;
            },
            10,
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
