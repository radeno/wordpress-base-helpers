<?php

namespace helper;

require_once "PostTypeHelper.php";
require_once "TaxonomyHelper.php";

if (!function_exists("rest_is_field_included")) {
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
}

class AnyPostsRestController extends \WP_REST_Controller
{
    public function __construct()
    {
        $this->post_types = array_values(PostTypeHelper::getAllRestPostTypes());
        $this->namespace  = 'sc/v1';
        $this->rest_base  = 'any_posts';
    }

    /**
     * Registers the routes for posts.
     *
     * @since 4.7.0
     *
     * @see register_rest_route()
     */
    public function register_routes()
    {
        \register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
                    'args'                => $this->get_collection_params(),
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        $schema        = $this->get_item_schema();
        $get_item_args = [
            'context' => $this->get_context_param([ 'default' => 'view' ]),
        ];

        if (isset($schema['properties']['password'])) {
            $get_item_args['password'] = [
                'description' => __('The password for the post if it is password protected.'),
                'type'        => 'string',
            ];
        }

        \register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'args' => [
                    'id' => [
                        'description' => __('Unique identifier for the object.'),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                    'args'                => $get_item_args,
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );
    }

    /**
     * Checks if a given request has access to read posts.
     *
     * @since 4.7.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
     */
    public function get_items_permissions_check($request)
    {
        return true;
    }

    /**
     * Retrieves a collection of posts.
     *
     * @since 4.7.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items($request)
    {

        // Ensure a search string is set in case the orderby is set to 'relevance'.
        if (! empty($request['orderby']) && 'relevance' === $request['orderby'] && empty($request['search'])) {
            return new \WP_Error(
                'rest_no_search_term_defined',
                __('You need to define a search term to order by relevance.'),
                [ 'status' => 400 ]
            );
        }

        // Ensure an include parameter is set in case the orderby is set to 'include'.
        if (! empty($request['orderby']) && 'include' === $request['orderby'] && empty($request['include'])) {
            return new \WP_Error(
                'rest_orderby_include_missing_include',
                __('You need to define an include parameter to order by include.'),
                [ 'status' => 400 ]
            );
        }

        // Retrieve the list of registered collection query parameters.
        $registered = $this->get_collection_params();
        $args       = [];

        /*
         * This array defines mappings between public API query parameters whose
         * values are accepted as-passed, and their internal WP_Query parameter
         * name equivalents (some are the same). Only values which are also
         * present in $registered will be set.
         */
        $parameter_mappings = [
            'author'         => 'author__in',
            'author_exclude' => 'author__not_in',
            'exclude'        => 'post__not_in',
            'include'        => 'post__in',
            'menu_order'     => 'menu_order',
            'offset'         => 'offset',
            'order'          => 'order',
            'orderby'        => 'orderby',
            'page'           => 'paged',
            'parent'         => 'post_parent__in',
            'parent_exclude' => 'post_parent__not_in',
            'search'         => 's',
            'slug'           => 'post_name__in',
            'status'         => 'post_status',
            'type'           => 'post_type',
        ];

        /*
         * For each known parameter which is both registered and present in the request,
         * set the parameter's value on the query $args.
         */
        foreach ($parameter_mappings as $api_param => $wp_param) {
            if (isset($registered[ $api_param ], $request[ $api_param ])) {
                $args[ $wp_param ] = $request[ $api_param ];
            }
        }

        // Check for & assign any parameters which require special handling or setting.
        $args['date_query'] = [];

        if (isset($registered['before'], $request['before'])) {
            $args['date_query'][] = [
                'before' => $request['before'],
                'column' => 'post_date',
            ];
        }

        if (isset($registered['modified_before'], $request['modified_before'])) {
            $args['date_query'][] = [
                'before' => $request['modified_before'],
                'column' => 'post_modified',
            ];
        }

        if (isset($registered['after'], $request['after'])) {
            $args['date_query'][] = [
                'after'  => $request['after'],
                'column' => 'post_date',
            ];
        }

        if (isset($registered['modified_after'], $request['modified_after'])) {
            $args['date_query'][] = [
                'after'  => $request['modified_after'],
                'column' => 'post_modified',
            ];
        }

        // Ensure our per_page parameter overrides any provided posts_per_page filter.
        if (isset($registered['per_page'])) {
            $args['posts_per_page'] = $request['per_page'];
        }

        if (isset($registered['sticky'], $request['sticky'])) {
            $sticky_posts = \get_option('sticky_posts', []);
            if (! is_array($sticky_posts)) {
                $sticky_posts = [];
            }
            if ($request['sticky']) {
                /*
                 * As post__in will be used to only get sticky posts,
                 * we have to support the case where post__in was already
                 * specified.
                 */
                $args['post__in'] = $args['post__in'] ? array_intersect($sticky_posts, $args['post__in']) : $sticky_posts;

                /*
                 * If we intersected, but there are no post ids in common,
                 * WP_Query won't return "no posts" for post__in = array()
                 * so we have to fake it a bit.
                 */
                if (! $args['post__in']) {
                    $args['post__in'] = [ 0 ];
                }
            } elseif ($sticky_posts) {
                /*
                 * As post___not_in will be used to only get posts that
                 * are not sticky, we have to support the case where post__not_in
                 * was already specified.
                 */
                $args['post__not_in'] = array_merge($args['post__not_in'], $sticky_posts);
            }
        }

        // Force the post_type argument, since it's not a user input variable.
        $args['post_type'] = $request['type'] ?? !empty($request['except_type']) ? array_diff($this->post_types, (array)$request['except_type']) : $this->post_types;

        /**
         * Filters the query arguments for a request.
         *
         * Enables adding extra arguments or setting defaults for a post collection request.
         *
         * @since 4.7.0
         *
         * @link https://developer.wordpress.org/reference/classes/wp_query/
         *
         * @param array           $args    Key value array of query var to query value.
         * @param WP_REST_Request $request The request used.
         */
        $args       = \apply_filters("rest_any_posts_query", $args, $request);
        $query_args = $this->prepare_items_query($args, $request);

        $taxonomies = TaxonomyHelper::getAllRestTaxonomies('objects');

        if (! empty($request['tax_relation'])) {
            $query_args['tax_query'] = [ 'relation' => $request['tax_relation'] ];
        }

        foreach ($taxonomies as $taxonomy) {
            $base        = ! empty($taxonomy->rest_base) ? $taxonomy->rest_base : $taxonomy->name;
            $tax_exclude = $base . '_exclude';

            if (! empty($request[ $base ])) {
                $query_args['tax_query'][] = [
                    'taxonomy'         => $taxonomy->name,
                    'field'            => 'term_id',
                    'terms'            => $request[ $base ],
                    'include_children' => false,
                ];
            }

            if (! empty($request[ $tax_exclude ])) {
                $query_args['tax_query'][] = [
                    'taxonomy'         => $taxonomy->name,
                    'field'            => 'term_id',
                    'terms'            => $request[ $tax_exclude ],
                    'include_children' => false,
                    'operator'         => 'NOT IN',
                ];
            }
        }

        $posts_query  = new \WP_Query();
        $query_result = $posts_query->query($query_args);

        // Allow access to all password protected posts if the context is edit.
        if ('edit' === $request['context']) {
            \add_filter('post_password_required', '__return_false');
        }

        $posts = [];

        foreach ($query_result as $post) {
            if (! $this->check_read_permission($post)) {
                continue;
            }

            $data    = $this->prepare_item_for_response($post, $request);
            $posts[] = $this->prepare_response_for_collection($data);
        }

        // Reset filter.
        if ('edit' === $request['context']) {
            remove_filter('post_password_required', '__return_false');
        }

        $page        = (int) $query_args['paged'];
        $total_posts = $posts_query->found_posts;

        if ($total_posts < 1) {
            // Out-of-bounds, run the query again without LIMIT for total count.
            unset($query_args['paged']);

            $count_query = new \WP_Query();
            $count_query->query($query_args);
            $total_posts = $count_query->found_posts;
        }

        $max_pages = ceil($total_posts / (int) $posts_query->query_vars['posts_per_page']);

        if ($page > $max_pages && $total_posts > 0) {
            return new \WP_Error(
                'rest_post_invalid_page_number',
                __('The page number requested is larger than the number of pages available.'),
                [ 'status' => 400 ]
            );
        }

        $response = \rest_ensure_response($posts);

        $response->header('X-WP-Total', (int) $total_posts);
        $response->header('X-WP-TotalPages', (int) $max_pages);

        $request_params = $request->get_query_params();
        $base           = \add_query_arg(urlencode_deep($request_params), \rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base)));

        if ($page > 1) {
            $prev_page = $page - 1;

            if ($prev_page > $max_pages) {
                $prev_page = $max_pages;
            }

            $prev_link = \add_query_arg('page', $prev_page, $base);
            $response->link_header('prev', $prev_link);
        }
        if ($max_pages > $page) {
            $next_page = $page + 1;
            $next_link = \add_query_arg('page', $next_page, $base);

            $response->link_header('next', $next_link);
        }

        return $response;
    }

    protected function get_post($id)
    {
        $error = new \WP_Error(
            'rest_post_invalid_id',
            __('Invalid post ID.'),
            [ 'status' => 404 ]
        );

        if ((int) $id <= 0) {
            return $error;
        }

        $post = \get_post((int) $id);
        if (empty($post) || empty($post->ID) || !in_array($post->post_type, $this->post_types)) {
            return $error;
        }

        return $post;
    }

    public function get_item_permissions_check($request)
    {
        $post = $this->get_post($request['id']);
        if (\is_wp_error($post)) {
            return $post;
        }

        if ('edit' === $request['context'] && $post && ! $this->check_update_permission($post)) {
            return new \WP_Error(
                'rest_forbidden_context',
                __('Sorry, you are not allowed to edit this post.'),
                [ 'status' => \rest_authorization_required_code() ]
            );
        }

        if ($post && ! empty($request['password'])) {
            // Check post password, and return error if invalid.
            if (! hash_equals($post->post_password, $request['password'])) {
                return new \WP_Error(
                    'rest_post_incorrect_password',
                    __('Incorrect post password.'),
                    [ 'status' => 403 ]
                );
            }
        }

        // Allow access to all password protected posts if the context is edit.
        if ('edit' === $request['context']) {
            \add_filter('post_password_required', '__return_false');
        }

        if ($post) {
            return $this->check_read_permission($post);
        }

        return true;
    }

    public function can_access_password_content($post, $request)
    {
        if (empty($post->post_password)) {
            // No filter required.
            return false;
        }

        // Edit context always gets access to password-protected posts.
        if ('edit' === $request['context']) {
            return true;
        }

        // No password, no auth.
        if (empty($request['password'])) {
            return false;
        }

        // Double-check the request password.
        return hash_equals($post->post_password, $request['password']);
    }

    public function get_item($request)
    {
        $post = $this->get_post($request['id']);
        if (\is_wp_error($post)) {
            return $post;
        }

        $data     = $this->prepare_item_for_response($post, $request);
        $response = \rest_ensure_response($data);

        $response->link_header('alternate', \get_permalink($post->ID), [ 'type' => 'text/html' ]);

        return $response;
    }

    /**
     * Determines the allowed query_vars for a get_items() response and prepares
     * them for WP_Query.
     *
     * @since 4.7.0
     *
     * @param array           $prepared_args Optional. Prepared WP_Query arguments. Default empty array.
     * @param WP_REST_Request $request       Optional. Full details about the request.
     * @return array Items query arguments.
     */
    protected function prepare_items_query($prepared_args = [], $request = null)
    {
        $query_args = [];

        foreach ($prepared_args as $key => $value) {
            /**
             * Filters the query_vars used in get_items() for the constructed query.
             *
             * The dynamic portion of the hook name, `$key`, refers to the query_var key.
             *
             * @since 4.7.0
             *
             * @param string $value The query_var value.
             */
            $query_args[ $key ] = \apply_filters("rest_query_var-{$key}", $value); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
        }

        $query_args['ignore_sticky_posts'] = true;

        // Map to proper WP_Query orderby param.
        if (isset($query_args['orderby']) && isset($request['orderby'])) {
            $orderby_mappings = [
                'id'            => 'ID',
                'include'       => 'post__in',
                'slug'          => 'post_name',
                'include_slugs' => 'post_name__in',
            ];

            if (isset($orderby_mappings[ $request['orderby'] ])) {
                $query_args['orderby'] = $orderby_mappings[ $request['orderby'] ];
            }
        }

        return $query_args;
    }

    /**
     * Checks the post_date_gmt or modified_gmt and prepare any post or
     * modified date for single post output.
     *
     * @since 4.7.0
     *
     * @param string      $date_gmt GMT publication time.
     * @param string|null $date     Optional. Local publication time. Default null.
     * @return string|null ISO8601/RFC3339 formatted datetime.
     */
    protected function prepare_date_response($date_gmt, $date = null)
    {
        // Use the date if passed.
        if (isset($date)) {
            return \mysql_to_rfc3339($date);
        }

        // Return null if $date_gmt is empty/zeros.
        if ('0000-00-00 00:00:00' === $date_gmt) {
            return null;
        }

        // Return the formatted datetime.
        return \mysql_to_rfc3339($date_gmt);
    }

    /**
     * Determines validity and normalizes the given status parameter.
     *
     * @since 4.7.0
     *
     * @param string       $post_status Post status.
     * @param WP_Post_Type $post_type   Post type.
     * @return string|WP_Error Post status or WP_Error if lacking the proper permission.
     */
    protected function handle_status_param($post_status, $post_type)
    {
        switch ($post_status) {
            case 'draft':
            case 'pending':
                break;
            case 'private':
                if (! \current_user_can($post_type->cap->publish_posts)) {
                    return new \WP_Error(
                        'rest_cannot_publish',
                        __('Sorry, you are not allowed to create private posts in this post type.'),
                        [ 'status' => rest_authorization_required_code() ]
                    );
                }
                break;
            case 'publish':
            case 'future':
                if (! \current_user_can($post_type->cap->publish_posts)) {
                    return new \WP_Error(
                        'rest_cannot_publish',
                        __('Sorry, you are not allowed to publish posts in this post type.'),
                        [ 'status' => rest_authorization_required_code() ]
                    );
                }
                break;
            default:
                if (! \get_post_status_object($post_status)) {
                    $post_status = 'draft';
                }
                break;
        }

        return $post_status;
    }

    /**
     * Checks if a given post type can be viewed or managed.
     *
     * @since 4.7.0
     *
     * @param WP_Post_Type|string $post_type Post type name or object.
     * @return bool Whether the post type is allowed in REST.
     */
    protected function check_is_post_type_allowed($post_type)
    {
        if (! is_object($post_type)) {
            $post_type = \get_post_type_object($post_type);
        }

        if (! empty($post_type) && ! empty($post_type->show_in_rest)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if a post can be read.
     *
     * Correctly handles posts with the inherit status.
     *
     * @since 4.7.0
     *
     * @param WP_Post $post Post object.
     * @return bool Whether the post can be read.
     */
    public function check_read_permission($post)
    {
        $post_type = \get_post_type_object($post->post_type);
        if (! $this->check_is_post_type_allowed($post_type)) {
            return false;
        }

        // Is the post readable?
        if ('publish' === $post->post_status || \current_user_can($post_type->cap->read_post, $post->ID)) {
            return true;
        }

        $post_status_obj = \get_post_status_object($post->post_status);
        if ($post_status_obj && $post_status_obj->public) {
            return true;
        }

        // Can we read the parent if we're inheriting?
        if ('inherit' === $post->post_status && $post->post_parent > 0) {
            $parent = \get_post($post->post_parent);
            if ($parent) {
                return $this->check_read_permission($parent);
            }
        }

        /*
         * If there isn't a parent, but the status is set to inherit, assume
         * it's published (as per get_post_status()).
         */
        if ('inherit' === $post->post_status) {
            return true;
        }

        // TODO: This is hack, we need to remap permissions
        $current_user = \wp_get_current_user();
        if (isset($current_user->allcaps[$post_type->cap->read_post]) && $current_user->allcaps[$post_type->cap->read_post]) {
            return true;
        }

        return false;
    }

    /**
     * Prepares a single post output for response.
     *
     * @since 4.7.0
     *
     * @param WP_Post         $post    Post object.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function prepare_item_for_response($post, $request)
    {
        $GLOBALS['post'] = $post;

        \setup_postdata($post);

        $fields = $this->get_fields_for_response($request);

        // Base fields for every post.
        $data = [];

        if (\rest_is_field_included('id', $fields)) {
            $data['id'] = $post->ID;
        }

        if (\rest_is_field_included('date', $fields)) {
            $data['date'] = $this->prepare_date_response($post->post_date_gmt, $post->post_date);
        }

        if (\rest_is_field_included('date_gmt', $fields)) {
            /*
            * For drafts, `post_date_gmt` may not be set, indicating that the date
            * of the draft should be updated each time it is saved (see #38883).
            * * In this case, shim the value based on the `post_date` field
            * with the site's timezone offset applied.
            */
            if ('0000-00-00 00:00:00' === $post->post_date_gmt) {
                $post_date_gmt = get_gmt_from_date($post->post_date);
            } else {
                $post_date_gmt = $post->post_date_gmt;
            }
            $data['date_gmt'] = $this->prepare_date_response($post_date_gmt);
        }

        if (\rest_is_field_included('guid', $fields)) {
            $data['guid'] = [
                /** This filter is documented in wp-includes/post-template.php */
                'rendered' => apply_filters('get_the_guid', $post->guid, $post->ID),
                'raw'      => $post->guid,
            ];
        }

        if (\rest_is_field_included('modified', $fields)) {
            $data['modified'] = $this->prepare_date_response($post->post_modified_gmt, $post->post_modified);
        }

        if (\rest_is_field_included('modified_gmt', $fields)) {
            /*
            * For drafts, `post_modified_gmt` may not be set (see `post_date_gmt` comments
            * above). In this case, shim the value based on the `post_modified` field
            * with the site's timezone offset applied.
            */
            if ('0000-00-00 00:00:00' === $post->post_modified_gmt) {
                $post_modified_gmt = gmdate('Y-m-d H:i:s', strtotime($post->post_modified) - (\get_option('gmt_offset') * 3600));
            } else {
                $post_modified_gmt = $post->post_modified_gmt;
            }
            $data['modified_gmt'] = $this->prepare_date_response($post_modified_gmt);
        }

        if (\rest_is_field_included('password', $fields)) {
            $data['password'] = $post->post_password;
        }

        if (\rest_is_field_included('slug', $fields)) {
            $data['slug'] = $post->post_name;
        }

        if (\rest_is_field_included('status', $fields)) {
            $data['status'] = $post->post_status;
        }

        if (\rest_is_field_included('type', $fields)) {
            $data['type'] = $post->post_type;
        }

        if (\rest_is_field_included('link', $fields)) {
            $data['link'] = get_permalink($post->ID);
        }

        if (\post_type_supports($post->post_type, 'title')) {
            if (\rest_is_field_included('title', $fields)) {
                $data['title'] = [];
            }
            if (\rest_is_field_included('title.raw', $fields)) {
                $data['title']['raw'] = $post->post_title;
            }
            if (\rest_is_field_included('title.rendered', $fields)) {
                \add_filter('protected_title_format', [ $this, 'protected_title_format' ]);

                $data['title']['rendered'] = \get_the_title($post->ID);

                \remove_filter('protected_title_format', [ $this, 'protected_title_format' ]);
            }
        }

        $has_password_filter = false;

        if ($this->can_access_password_content($post, $request)) {
            // Allow access to the post, permissions already checked before.
            \add_filter('post_password_required', '__return_false');

            $has_password_filter = true;
        }

        if (\post_type_supports($post->post_type, 'editor')) {
            if (\rest_is_field_included('content', $fields)) {
                $data['content'] = [];
            }
            if (\rest_is_field_included('content.raw', $fields)) {
                $data['content']['raw'] = $post->post_content;
            }
            if (\rest_is_field_included('content.rendered', $fields)) {
                /** This filter is documented in wp-includes/post-template.php */
                $data['content']['rendered'] = \post_password_required($post) ? '' : \apply_filters('the_content', $post->post_content);
            }
            if (\rest_is_field_included('content.protected', $fields)) {
                $data['content']['protected'] = (bool) $post->post_password;
            }
            if (\rest_is_field_included('content.block_version', $fields)) {
                $data['content']['block_version'] = block_version($post->post_content);
            }
        }

        if (\post_type_supports($post->post_type, 'excerpt')) {
            if (\rest_is_field_included('excerpt', $fields)) {
                /** This filter is documented in wp-includes/post-template.php */
                $excerpt = \apply_filters('get_the_excerpt', $post->post_excerpt, $post);

                /** This filter is documented in wp-includes/post-template.php */
                $excerpt = \apply_filters('the_excerpt', $excerpt);

                $data['excerpt'] = [
                    'raw'       => $post->post_excerpt,
                    'rendered'  => \post_password_required($post) ? '' : $excerpt,
                    'protected' => (bool) $post->post_password,
                ];
            }
        }

        if ($has_password_filter) {
            // Reset filter.
            \remove_filter('post_password_required', '__return_false');
        }

        if (\post_type_supports($post->post_type, 'author')) {
            if (\rest_is_field_included('author', $fields)) {
                $data['author'] = (int) $post->post_author;
            }
        }

        if (\post_type_supports($post->post_type, 'thumbnail')) {
            if (\rest_is_field_included('featured_media', $fields)) {
                $data['featured_media'] = (int) \get_post_thumbnail_id($post->ID);
            }
        }

        if (\is_post_type_hierarchical($post->post_type)) {
            if (\rest_is_field_included('parent', $fields)) {
                $data['parent'] = (int) $post->post_parent;
            }
        }

        if (\post_type_supports($post->post_type, 'page-attributes')) {
            if (\rest_is_field_included('menu_order', $fields)) {
                $data['menu_order'] = (int) $post->menu_order;
            }
        }

        if (\post_type_supports($post->post_type, 'comments')) {
            if (\rest_is_field_included('comment_status', $fields)) {
                $data['comment_status'] = $post->comment_status;
            }

            if (\rest_is_field_included('ping_status', $fields)) {
                $data['ping_status'] = $post->ping_status;
            }
        }

        if (\post_type_supports($post->post_type, 'sticky_posts')) {
            if (\rest_is_field_included('sticky', $fields)) {
                $data['sticky'] = is_sticky($post->ID);
            }
        }

        if (\post_type_supports($post->post_type, 'page-attributes')) {
            if (\rest_is_field_included('template', $fields)) {
                $template = \get_page_template_slug($post->ID);
                if ($template) {
                    $data['template'] = $template;
                } else {
                    $data['template'] = '';
                }
            }
        }

        if (\post_type_supports($post->post_type, 'post-formats')) {
            if (\rest_is_field_included('format', $fields)) {
                $data['format'] = \get_post_format($post->ID);

                // Fill in blank post format.
                if (empty($data['format'])) {
                    $data['format'] = 'standard';
                }
            }
        }

        if (\post_type_supports($post->post_type, 'custom-fields')) {
            if (\rest_is_field_included('meta', $fields)) {
                $meta         = new \WP_REST_Post_Meta_Fields($post->post_type);
                $data['meta'] = $meta->get_value($post->ID, $request);
            }
        }

        $taxonomies = \get_object_taxonomies($post, 'objects');

        foreach ($taxonomies as $taxonomy) {
            $base = ! empty($taxonomy->rest_base) ? $taxonomy->rest_base : $taxonomy->name;

            if (\rest_is_field_included($base, $fields)) {
                $terms         = \get_the_terms($post, $taxonomy->name);
                $data[ $base ] = $terms ? array_values(\wp_list_pluck($terms, 'term_id')) : [];
            }
        }

        $permalink_template_requested = \rest_is_field_included('permalink_template', $fields);
        $generated_slug_requested     = \rest_is_field_included('generated_slug', $fields);

        if ($permalink_template_requested || $generated_slug_requested) {
            if (! function_exists('get_sample_permalink')) {
                require_once ABSPATH . 'wp-admin/includes/post.php';
            }

            $sample_permalink = \get_sample_permalink($post->ID, $post->post_title, '');

            if ($permalink_template_requested) {
                $data['permalink_template'] = $sample_permalink[0];
            }

            if ($generated_slug_requested) {
                $data['generated_slug'] = $sample_permalink[1];
            }
        }

        $context = ! empty($request['context']) ? $request['context'] : 'view';
        $data    = $this->add_additional_fields_to_object($data, $request);
        $data    = $this->filter_response_by_context($data, $context);

        // Wrap the data in a response object.
        $response = \rest_ensure_response($data);

        /**
         * Filters the post data for a response.
         *
         * The dynamic portion of the hook name, `$this->post_type`, refers to the post type slug.
         *
         * @since 4.7.0
         *
         * @param WP_REST_Response $response The response object.
         * @param WP_Post          $post     Post object.
         * @param WP_REST_Request  $request  Request object.
         */
        return \apply_filters("rest_prepare_{$post->post_type}", $response, $post, $request);
    }

    /**
     * Overwrites the default protected title format.
     *
     * By default, WordPress will show password protected posts with a title of
     * "Protected: %s", as the REST API communicates the protected status of a post
     * in a machine readable format, we remove the "Protected: " prefix.
     *
     * @since 4.7.0
     *
     * @return string Protected title format.
     */
    public function protected_title_format()
    {
        return '%s';
    }

    /**
     * Retrieves the post's schema, conforming to JSON Schema.
     *
     * @since 4.7.0
     *
     * @return array Item schema data.
     */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }

        $schema = [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title'   => 'any_post',
            'type'    => 'object',
            // Base properties for every Post.
            'properties' => [
                'date' => [
                    'description' => __("The date the object was published, in the site's timezone."),
                    'type'        => [ 'string', 'null' ],
                    'format'      => 'date-time',
                    'context'     => [ 'view', 'edit', 'embed' ],
                ],
                'date_gmt' => [
                    'description' => __('The date the object was published, as GMT.'),
                    'type'        => [ 'string', 'null' ],
                    'format'      => 'date-time',
                    'context'     => [ 'view', 'edit' ],
                ],
                'guid' => [
                    'description' => __('The globally unique identifier for the object.'),
                    'type'        => 'object',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                    'properties'  => [
                        'raw' => [
                            'description' => __('GUID for the object, as it exists in the database.'),
                            'type'        => 'string',
                            'context'     => [ 'edit' ],
                            'readonly'    => true,
                        ],
                        'rendered' => [
                            'description' => __('GUID for the object, transformed for display.'),
                            'type'        => 'string',
                            'context'     => [ 'view', 'edit' ],
                            'readonly'    => true,
                        ],
                    ],
                ],
                'id' => [
                    'description' => __('Unique identifier for the object.'),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'edit', 'embed' ],
                    'readonly'    => true,
                ],
                'link' => [
                    'description' => __('URL to the object.'),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => [ 'view', 'edit', 'embed' ],
                    'readonly'    => true,
                ],
                'modified' => [
                    'description' => __("The date the object was last modified, in the site's timezone."),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                ],
                'modified_gmt' => [
                    'description' => __('The date the object was last modified, as GMT.'),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                ],
                'slug' => [
                    'description' => __('An alphanumeric identifier for the object unique to its type.'),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit', 'embed' ],
                    'arg_options' => [
                        'sanitize_callback' => [ $this, 'sanitize_slug' ],
                    ],
                ],
                'status' => [
                    'description' => __('A named status for the object.'),
                    'type'        => 'string',
                    'enum'        => array_keys(\get_post_stati([ 'internal' => false ])),
                    'context'     => [ 'view', 'edit' ],
                ],
                'type' => [
                    'description' => __('Type of Post for the object.'),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit', 'embed' ],
                    'readonly'    => true,
                ],
                'password' => [
                    'description' => __('A password to protect access to the content and excerpt.'),
                    'type'        => 'string',
                    'context'     => [ 'edit' ],
                ],
            ],
        ];

        $schema['properties']['permalink_template'] = [
            'description' => __('Permalink template for the object.'),
            'type'        => 'string',
            'context'     => [ 'edit' ],
            'readonly'    => true,
        ];

        $schema['properties']['generated_slug'] = [
            'description' => __('Slug automatically generated from the object title.'),
            'type'        => 'string',
            'context'     => [ 'edit' ],
            'readonly'    => true,
        ];

        $schema['properties']['parent'] = [
            'description' => __('The ID for the parent of the object.'),
            'type'        => 'integer',
            'context'     => [ 'view', 'edit' ],
        ];

        $post_type_attributes = [
            'title',
            'editor',
            'author',
            'excerpt',
            'thumbnail',
            'comments',
            'revisions',
            'page-attributes',
            'post-formats',
            'custom-fields',
        ];
        $fixed_schemas = [
            'post' => [
                'title',
                'editor',
                'author',
                'excerpt',
                'thumbnail',
                'comments',
                'revisions',
                'post-formats',
                'custom-fields',
            ],
            'page' => [
                'title',
                'editor',
                'author',
                'excerpt',
                'thumbnail',
                'comments',
                'revisions',
                'page-attributes',
                'custom-fields',
            ],
            'attachment' => [
                'title',
                'author',
                'comments',
                'revisions',
                'custom-fields',
            ],
        ];
        foreach ($post_type_attributes as $attribute) {
            switch ($attribute) {

                case 'title':
                    $schema['properties']['title'] = [
                        'description' => __('The title for the object.'),
                        'type'        => 'object',
                        'context'     => [ 'view', 'edit', 'embed' ],
                        'arg_options' => [
                            'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
                            'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
                        ],
                        'properties' => [
                            'raw' => [
                                'description' => __('Title for the object, as it exists in the database.'),
                                'type'        => 'string',
                                'context'     => [ 'edit' ],
                            ],
                            'rendered' => [
                                'description' => __('HTML title for the object, transformed for display.'),
                                'type'        => 'string',
                                'context'     => [ 'view', 'edit', 'embed' ],
                                'readonly'    => true,
                            ],
                        ],
                    ];
                    break;

                case 'editor':
                    $schema['properties']['content'] = [
                        'description' => __('The content for the object.'),
                        'type'        => 'object',
                        'context'     => [ 'view', 'edit' ],
                        'arg_options' => [
                            'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
                            'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
                        ],
                        'properties' => [
                            'raw' => [
                                'description' => __('Content for the object, as it exists in the database.'),
                                'type'        => 'string',
                                'context'     => [ 'edit' ],
                            ],
                            'rendered' => [
                                'description' => __('HTML content for the object, transformed for display.'),
                                'type'        => 'string',
                                'context'     => [ 'view', 'edit' ],
                                'readonly'    => true,
                            ],
                            'block_version' => [
                                'description' => __('Version of the content block format used by the object.'),
                                'type'        => 'integer',
                                'context'     => [ 'edit' ],
                                'readonly'    => true,
                            ],
                            'protected' => [
                                'description' => __('Whether the content is protected with a password.'),
                                'type'        => 'boolean',
                                'context'     => [ 'view', 'edit', 'embed' ],
                                'readonly'    => true,
                            ],
                        ],
                    ];
                    break;

                case 'author':
                    $schema['properties']['author'] = [
                        'description' => __('The ID for the author of the object.'),
                        'type'        => 'integer',
                        'context'     => [ 'view', 'edit', 'embed' ],
                    ];
                    break;

                case 'excerpt':
                    $schema['properties']['excerpt'] = [
                        'description' => __('The excerpt for the object.'),
                        'type'        => 'object',
                        'context'     => [ 'view', 'edit', 'embed' ],
                        'arg_options' => [
                            'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
                            'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
                        ],
                        'properties' => [
                            'raw' => [
                                'description' => __('Excerpt for the object, as it exists in the database.'),
                                'type'        => 'string',
                                'context'     => [ 'edit' ],
                            ],
                            'rendered' => [
                                'description' => __('HTML excerpt for the object, transformed for display.'),
                                'type'        => 'string',
                                'context'     => [ 'view', 'edit', 'embed' ],
                                'readonly'    => true,
                            ],
                            'protected' => [
                                'description' => __('Whether the excerpt is protected with a password.'),
                                'type'        => 'boolean',
                                'context'     => [ 'view', 'edit', 'embed' ],
                                'readonly'    => true,
                            ],
                        ],
                    ];
                    break;

                case 'thumbnail':
                    $schema['properties']['featured_media'] = [
                        'description' => __('The ID of the featured media for the object.'),
                        'type'        => 'integer',
                        'context'     => [ 'view', 'edit', 'embed' ],
                    ];
                    break;

                case 'comments':
                    $schema['properties']['comment_status'] = [
                        'description' => __('Whether or not comments are open on the object.'),
                        'type'        => 'string',
                        'enum'        => [ 'open', 'closed' ],
                        'context'     => [ 'view', 'edit' ],
                    ];
                    $schema['properties']['ping_status'] = [
                        'description' => __('Whether or not the object can be pinged.'),
                        'type'        => 'string',
                        'enum'        => [ 'open', 'closed' ],
                        'context'     => [ 'view', 'edit' ],
                    ];
                    break;

                case 'page-attributes':
                    $schema['properties']['menu_order'] = [
                        'description' => __('The order of the object in relation to other object of its type.'),
                        'type'        => 'integer',
                        'context'     => [ 'view', 'edit' ],
                    ];
                    break;

                case 'post-formats':
                    // Get the native post formats and remove the array keys.
                    $formats = array_values(\get_post_format_slugs());

                    $schema['properties']['format'] = [
                        'description' => __('The format for the object.'),
                        'type'        => 'string',
                        'enum'        => $formats,
                        'context'     => [ 'view', 'edit' ],
                    ];
                    break;

                case 'custom-fields':
                    // $meta                         = new \WP_REST_Post_Meta_Fields('post');
                    // $schema['properties']['meta'] = $meta->get_field_schema();
                    break;

            }
        }

        $schema['properties']['sticky'] = [
            'description' => __('Whether or not the object should be treated as sticky.'),
            'type'        => 'boolean',
            'context'     => [ 'view', 'edit' ],
        ];

        $schema['properties']['template'] = [
            'description' => __('The theme file to use to display the object.'),
            'type'        => 'string',
            'context'     => [ 'view', 'edit' ],
            'arg_options' => [
                'validate_callback' => [ $this, 'check_template' ],
            ],
        ];

        $taxonomies = TaxonomyHelper::getAllRestTaxonomies('objects');

        foreach ($taxonomies as $taxonomy) {
            $base                          = ! empty($taxonomy->rest_base) ? $taxonomy->rest_base : $taxonomy->name;
            $schema['properties'][ $base ] = [
                /* translators: %s: Taxonomy name. */
                'description' => sprintf(__('The terms assigned to the object in the %s taxonomy.'), $taxonomy->name),
                'type'        => 'array',
                'items'       => [
                    'type' => 'integer',
                ],
                'context' => [ 'view', 'edit' ],
            ];
        }

        $this->schema = $schema;
        return $this->add_additional_fields_schema($this->schema);
    }

    /**
     * Retrieves the query params for the posts collection.
     *
     * @since 4.7.0
     *
     * @return array Collection parameters.
     */
    public function get_collection_params()
    {
        $query_params = parent::get_collection_params();

        $query_params['context']['default'] = 'view';

        $query_params['type'] = [
            'description' => __('Post types'),
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
            'default' => $this->post_types,
        ];

        $query_params['after'] = [
            'description' => __('Limit response to posts published after a given ISO8601 compliant date.'),
            'type'        => 'string',
            'format'      => 'date-time',
        ];

        $query_params['author'] = [
            'description' => __('Limit result set to posts assigned to specific authors.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'integer',
            ],
            'default' => [],
        ];
        $query_params['author_exclude'] = [
            'description' => __('Ensure result set excludes posts assigned to specific authors.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'integer',
            ],
            'default' => [],
        ];

        $query_params['before'] = [
            'description' => __('Limit response to posts published before a given ISO8601 compliant date.'),
            'type'        => 'string',
            'format'      => 'date-time',
        ];

        $query_params['exclude'] = [
            'description' => __('Ensure result set excludes specific IDs.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'integer',
            ],
            'default' => [],
        ];

        $query_params['include'] = [
            'description' => __('Limit result set to specific IDs.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'integer',
            ],
            'default' => [],
        ];

        $query_params['menu_order'] = [
                'description' => __('Limit result set to posts with a specific menu_order value.'),
                'type'        => 'integer',
            ];

        $query_params['offset'] = [
            'description' => __('Offset the result set by a specific number of items.'),
            'type'        => 'integer',
        ];

        $query_params['order'] = [
            'description' => __('Order sort attribute ascending or descending.'),
            'type'        => 'string',
            'default'     => 'desc',
            'enum'        => [ 'asc', 'desc' ],
        ];

        $query_params['orderby'] = [
            'description' => __('Sort collection by object attribute.'),
            'type'        => 'string',
            'default'     => 'date',
            'enum'        => [
                'author',
                'date',
                'id',
                'include',
                'modified',
                'parent',
                'relevance',
                'slug',
                'include_slugs',
                'title',
            ],
        ];

        $query_params['orderby']['enum'][] = 'menu_order';

        $query_params['parent'] = [
            'description' => __('Limit result set to items with particular parent IDs.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'integer',
            ],
            'default' => [],
        ];
        $query_params['parent_exclude'] = [
            'description' => __('Limit result set to all items except those of a particular parent ID.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'integer',
            ],
            'default' => [],
        ];

        $query_params['slug'] = [
            'description' => __('Limit result set to posts with one or more specific slugs.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
            'sanitize_callback' => 'wp_parse_slug_list',
        ];

        $query_params['status'] = [
            'default'     => 'publish',
            'description' => __('Limit result set to posts assigned one or more statuses.'),
            'type'        => 'array',
            'items'       => [
                'enum' => array_merge(array_keys(\get_post_stati()), [ 'any' ]),
                'type' => 'string',
            ]
        ];

        $taxonomies = TaxonomyHelper::getAllRestTaxonomies('objects');

        if (! empty($taxonomies)) {
            $query_params['tax_relation'] = [
                'description' => __('Limit result set based on relationship between multiple taxonomies.'),
                'type'        => 'string',
                'enum'        => [ 'AND', 'OR' ],
            ];
        }

        foreach ($taxonomies as $taxonomy) {
            $base = ! empty($taxonomy->rest_base) ? $taxonomy->rest_base : $taxonomy->name;

            $query_params[ $base ] = [
                /* translators: %s: Taxonomy name. */
                'description' => sprintf(__('Limit result set to all items that have the specified term assigned in the %s taxonomy.'), $base),
                'type'        => 'array',
                'items'       => [
                    'type' => 'integer',
                ],
                'default' => [],
            ];

            $query_params[ $base . '_exclude' ] = [
                /* translators: %s: Taxonomy name. */
                'description' => sprintf(__('Limit result set to all items except those that have the specified term assigned in the %s taxonomy.'), $base),
                'type'        => 'array',
                'items'       => [
                    'type' => 'integer',
                ],
                'default' => [],
            ];
        }

        $query_params['sticky'] = [
            'description' => __('Limit result set to items that are sticky.'),
            'type'        => 'boolean',
        ];

        /**
         * Filter collection parameters for the posts controller.
         *
         * The dynamic part of the filter `$this->post_type` refers to the post
         * type slug for the controller.
         *
         * This filter registers the collection parameter, but does not map the
         * collection parameter to an internal WP_Query parameter. Use the
         * `rest_{$this->post_type}_query` filter to set WP_Query parameters.
         *
         * @since 4.7.0
         *
         * @param array        $query_params JSON Schema-formatted collection parameters.
         * @param WP_Post_Type $post_type    Post type object.
         */
        return \apply_filters("rest_any_post_collection_params", $query_params, $this->post_types);
    }

    /**
     * Adds the values from additional fields to a data object.
     *
     * @since 4.7.0
     *
     * @param array           $prepared Prepared response array.
     * @param WP_REST_Request $request  Full details about the request.
     * @return array Modified data object with additional fields.
     */
    protected function add_additional_fields_to_object($prepared, $request)
    {
        $additional_fields = $this->get_additional_fields($prepared['type']);
        $requested_fields  = $this->get_fields_for_response($request);
        foreach ($additional_fields as $field_name => $field_options) {
            if (! $field_options['get_callback']) {
                continue;
            }
            if (! in_array($field_name, $requested_fields, true)) {
                continue;
            }
            $prepared[ $field_name ] = call_user_func($field_options['get_callback'], $prepared, $field_name, $request, $this->get_object_type());
        }
        return $prepared;
    }
}
