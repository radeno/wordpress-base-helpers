<?php

namespace helper;

class RewriteHelper
{
    /** @var array<string, array<string, true>> Post types that have a /<archive>/<taxonomy>/<term>/ rule, by taxonomy. */
    private static array $postTaxonomyRewrites = [];

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

        if (count($taxonomiesObjects) === 1) {
            self::$postTaxonomyRewrites[$taxonomiesObjects[0]->name][$postType] = true;
        }
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

    /**
     * A term's path segment, as get_term_link() builds it.
     */
    private static function termPath(\WP_Term $term): string
    {
        if (empty(\get_taxonomy($term->taxonomy)->rewrite['hierarchical'])) {
            return $term->slug;
        }

        $ancestors = array_reverse(\get_ancestors($term->term_id, $term->taxonomy, 'taxonomy'));

        return implode('/', [...array_map(fn ($id) => \get_term($id, $term->taxonomy)->slug, $ancestors), $term->slug]);
    }

    /**
     * @param array<string, string> $termPaths Term path by taxonomy, in URL order.
     */
    private static function postTaxonomiesLink(string $archivePath, array $termPaths): string
    {
        $path = $archivePath;
        foreach ($termPaths as $taxonomy => $termPath) {
            $path .= '/' . \get_taxonomy($taxonomy)->rewrite['slug'] . '/' . $termPath;
        }

        return \home_url(\user_trailingslashit($path, 'category'));
    }

    public static function removeIndependentTaxonomyRewrite($taxonomy)
    {
        $taxonomyObject = \get_taxonomy($taxonomy);

        \add_filter("rewrite_rules_array", function ($rules) use ($taxonomyObject) {
            foreach ($rules as $rule => $rewrite) {
                if (str_starts_with($rule, $taxonomyObject->rewrite["slug"])) {
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
                if (str_contains($rewrite, $rewriteString)) {
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
        $termPaths = [];
        foreach ($taxonomies as $taxonomy) {
            $termPaths[$taxonomy] = $taxonomy == $currentTaxonomy ? self::termPath($currentTerm) : \get_query_var(\get_taxonomy($taxonomy)->query_var ?: $taxonomy);
        }

        return self::postTaxonomiesLink((string) self::postTypeArchivePath(\get_post_type_object($postType)), $termPaths);
    }

    public static function postTypeTaxonomyLinksAction()
    {
        \add_filter(
            'term_link',
            function ($url, $term, $taxonomy) {
                global $wp_rewrite;

                $taxonomyObject = \get_taxonomy($taxonomy);
                if ($taxonomyObject->_builtin || ! $wp_rewrite->using_permalinks()) {
                    return $url;
                }

                // Only post types that addPostTaxonomiesRewrite() added a rule for, so the link resolves.
                $postTypes = array_values(array_filter($taxonomyObject->object_type, fn ($postType) => isset(self::$postTaxonomyRewrites[$taxonomy][$postType])));
                if (! $postTypes) {
                    return $url;
                }

                $postType = in_array(\get_post_type(), $postTypes, true) ? \get_post_type() : $postTypes[0];

                return self::postTaxonomiesLink(self::postTypeArchivePath(\get_post_type_object($postType)), [$taxonomy => self::termPath($term)]);
            },
            10,
            3
        );
    }

    public static function removeDefaultDateRewritesFilter()
    {
        \add_filter('rewrite_rules_array', function ($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (str_starts_with($rule, '([0-9]{4})/')) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }
}
