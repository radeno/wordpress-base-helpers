<?php

namespace helper;

require_once "PostTypeHelper.php";
require_once "TaxonomyHelper.php";

class AnyTermsRestController extends \WP_REST_Controller
{
    public function __construct()
    {
        $this->taxonomies = array_values(TaxonomyHelper::getAllRestTaxonomies());
        $this->namespace  = 'sc/v1';
        $this->rest_base  = 'any_terms';
    }

    /**
     * Registers the routes for terms.
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

        \register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'args' => [
                    'id' => [
                        'description' => __('Unique identifier for the term.'),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                    'args'                => [
                        'context' => $this->get_context_param([ 'default' => 'view' ]),
                    ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );
    }

    /**
     * Checks if a request has access to read terms in the specified taxonomy.
     *
     * @since 4.7.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access, otherwise false or WP_Error object.
     */
    public function get_items_permissions_check($request)
    {
        return true;
    }

    /**
     * Retrieves terms associated with a taxonomy.
     *
     * @since 4.7.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items($request)
    {

        // Retrieve the list of registered collection query parameters.
        $registered = $this->get_collection_params();

        /*
         * This array defines mappings between public API query parameters whose
         * values are accepted as-passed, and their internal WP_Query parameter
         * name equivalents (some are the same). Only values which are also
         * present in $registered will be set.
         */
        $parameter_mappings = [
            'exclude'    => 'exclude',
            'include'    => 'include',
            'order'      => 'order',
            'orderby'    => 'orderby',
            'post'       => 'post',
            'hide_empty' => 'hide_empty',
            'per_page'   => 'number',
            'search'     => 'search',
            'slug'       => 'slug',
            'type'       => 'taxonomy',
        ];

        $prepared_args = $request['type'] ?? !empty($request['except_type']) ? array_diff($this->taxonomies, (array)$request['except_type']) : $this->taxonomies;

        /*
         * For each known parameter which is both registered and present in the request,
         * set the parameter's value on the query $prepared_args.
         */
        foreach ($parameter_mappings as $api_param => $wp_param) {
            if (isset($registered[ $api_param ], $request[ $api_param ])) {
                $prepared_args[ $wp_param ] = $request[ $api_param ];
            }
        }

        if (isset($prepared_args['orderby']) && isset($request['orderby'])) {
            $orderby_mappings = [
                'include_slugs' => 'slug__in',
            ];

            if (isset($orderby_mappings[ $request['orderby'] ])) {
                $prepared_args['orderby'] = $orderby_mappings[ $request['orderby'] ];
            }
        }

        if (isset($registered['offset']) && ! empty($request['offset'])) {
            $prepared_args['offset'] = $request['offset'];
        } else {
            $prepared_args['offset'] = ($request['page'] - 1) * $prepared_args['number'];
        }

        if (0 === $request['parent']) {
            // Only query top-level terms.
            $prepared_args['parent'] = 0;
        } else {
            if ($request['parent']) {
                $prepared_args['parent'] = $request['parent'];
            }
        }

        /**
         * Filters get_terms() arguments when querying terms via the REST API.
         *
         * The dynamic portion of the hook name, `$this->taxonomy`, refers to the taxonomy slug.
         *
         * Possible hook names include:
         *
         *  - `rest_category_query`
         *  - `rest_post_tag_query`
         *
         * Enables adding extra arguments or setting defaults for a terms
         * collection request.
         *
         * @since 4.7.0
         *
         * @link https://developer.wordpress.org/reference/functions/get_terms/
         *
         * @param array           $prepared_args Array of arguments for get_terms().
         * @param WP_REST_Request $request       The REST API request.
         */
        $prepared_args = \apply_filters("rest_any_terms_query", $prepared_args, $request);

        if (! empty($prepared_args['post'])) {
            $query_result = \wp_get_object_terms($prepared_args['post'], $this->taxonomies, $prepared_args);

            // Used when calling wp_count_terms() below.
            $prepared_args['object_ids'] = $prepared_args['post'];
        } else {
            $terms_query  = new \WP_Term_Query();
            $query_result = $terms_query->query($prepared_args, ['count' => true]);
        }

        $count_args = $prepared_args;
        unset($count_args['number'], $count_args['offset']);
        $count_args['count']  = true;
        $count_args['fields'] = 'count';

        $terms_count_query = new \WP_Term_Query();
        $total_terms       = $terms_count_query->query($count_args);

        // wp_count_terms() can return a falsy value when the term has no children.
        if (! $total_terms) {
            $total_terms = 0;
        }

        $response = [];

        foreach ($query_result as $term) {
            $data       = $this->prepare_item_for_response($term, $request);
            $response[] = $this->prepare_response_for_collection($data);
        }

        $response = \rest_ensure_response($response);

        // Store pagination values for headers.
        $per_page = (int) $prepared_args['number'];
        $page     = ceil((((int) $prepared_args['offset']) / $per_page) + 1);

        $response->header('X-WP-Total', (int) $total_terms);

        $max_pages = ceil($total_terms / $per_page);

        $response->header('X-WP-TotalPages', (int) $max_pages);

        $base = \add_query_arg(urlencode_deep($request->get_query_params()), \rest_url($this->namespace . '/' . $this->rest_base));
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

    /**
     * Get the term, if the ID is valid.
     *
     * @since 4.7.2
     *
     * @param int $id Supplied ID.
     * @return WP_Term|WP_Error Term object if ID is valid, WP_Error otherwise.
     */
    protected function get_term($id)
    {
        $error = new \WP_Error(
            'rest_term_invalid',
            __('Term does not exist.'),
            [ 'status' => 404 ]
        );

        if ((int) $id <= 0) {
            return $error;
        }

        $term = \get_term((int) $id);
        if (empty($term) || !in_array($term->taxonomy, $this->taxonomies)) {
            return $error;
        }

        return $term;
    }

    /**
     * Checks if a request has access to read or edit the specified term.
     *
     * @since 4.7.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access for the item, otherwise false or WP_Error object.
     */
    public function get_item_permissions_check($request)
    {
        $term = $this->get_term($request['id']);

        if (\is_wp_error($term)) {
            return $term;
        }

        if ('edit' === $request['context'] && ! \current_user_can('edit_term', $term->term_id)) {
            return new \WP_Error(
                'rest_forbidden_context',
                __('Sorry, you are not allowed to edit this term.'),
                [ 'status' => \rest_authorization_required_code() ]
            );
        }

        return true;
    }

    /**
     * Gets a single term from a taxonomy.
     *
     * @since 4.7.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_item($request)
    {
        $term = $this->get_term($request['id']);
        if (\is_wp_error($term)) {
            return $term;
        }

        $response = $this->prepare_item_for_response($term, $request);

        return \rest_ensure_response($response);
    }

    /**
     * Prepares a single term output for response.
     *
     * @since 4.7.0
     *
     * @param WP_Term         $item    Term object.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function prepare_item_for_response($item, $request)
    {
        $taxonomy_obj = \get_taxonomy($item->taxonomy);
        $fields       = $this->get_fields_for_response($request);
        $data         = [];

        if (in_array('id', $fields, true)) {
            $data['id'] = (int) $item->term_id;
        }

        if (in_array('count', $fields, true)) {
            $data['count'] = (int) $item->count;
        }

        if (in_array('description', $fields, true)) {
            $data['description'] = $item->description;
        }

        if (in_array('link', $fields, true)) {
            $data['link'] = \get_term_link($item);
        }

        if (in_array('name', $fields, true)) {
            $data['name'] = $item->name;
        }

        if (in_array('slug', $fields, true)) {
            $data['slug'] = $item->slug;
        }

        if (in_array('taxonomy', $fields, true)) {
            $data['taxonomy'] = $item->taxonomy;
        }

        if ($taxonomy_obj->hierarchical) {
            if (in_array('parent', $fields, true)) {
                $data['parent'] = (int) $item->parent;
            }
        }

        if (in_array('meta', $fields, true)) {
            $meta         = new \WP_REST_Term_Meta_Fields($item->taxonomy);
            $data['meta'] = $meta->get_value($item->term_id, $request);
        }

        $context = ! empty($request['context']) ? $request['context'] : 'view';
        $data    = $this->add_additional_fields_to_object($data, $request);
        $data    = $this->filter_response_by_context($data, $context);

        $response = \rest_ensure_response($data);

        /**
         * Filters a term item returned from the API.
         *
         * The dynamic portion of the hook name, `$this->taxonomy`, refers to the taxonomy slug.
         *
         * Allows modification of the term data right before it is returned.
         *
         * @since 4.7.0
         *
         * @param WP_REST_Response  $response  The response object.
         * @param WP_Term           $item      The original term object.
         * @param WP_REST_Request   $request   Request used to generate the response.
         */
        return apply_filters("rest_prepare_{$item->taxonomy}", $response, $item, $request);
    }

    /**
     * Retrieves the term's schema, conforming to JSON Schema.
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
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'any_term',
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'description' => __('Unique identifier for the term.'),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'embed', 'edit' ],
                    'readonly'    => true,
                ],
                'count' => [
                    'description' => __('Number of published posts for the term.'),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                ],
                'description' => [
                    'description' => __('HTML description of the term.'),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                ],
                'link' => [
                    'description' => __('URL of the term.'),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => [ 'view', 'embed', 'edit' ],
                    'readonly'    => true,
                ],
                'name' => [
                    'description' => __('HTML title for the term.'),
                    'type'        => 'string',
                    'context'     => [ 'view', 'embed', 'edit' ],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'required' => true,
                ],
                'slug' => [
                    'description' => __('An alphanumeric identifier for the term unique to its type.'),
                    'type'        => 'string',
                    'context'     => [ 'view', 'embed', 'edit' ],
                    'arg_options' => [
                        'sanitize_callback' => [ $this, 'sanitize_slug' ],
                    ],
                ],
                'taxonomy' => [
                    'description' => __('Type attribution for the term.'),
                    'type'        => 'string',
                    'context'     => [ 'view', 'embed', 'edit' ],
                    'readonly'    => true,
                ],
            ],
        ];

        $schema['properties']['parent'] = [
            'description' => __('The parent term ID.'),
            'type'        => 'integer',
            'context'     => [ 'view', 'edit' ],
        ];

        $this->schema = $schema;

        return $this->add_additional_fields_schema($this->schema);
    }

    /**
     * Retrieves the query params for collections.
     *
     * @since 4.7.0
     *
     * @return array Collection parameters.
     */
    public function get_collection_params()
    {
        $query_params = parent::get_collection_params();

        $query_params['type'] = [
            'description' => __('Taxonomy types'),
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
            'default' => $this->taxonomies,
        ];

        $query_params['context']['default'] = 'view';

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

        $query_params['offset'] = [
            'description' => __('Offset the result set by a specific number of items.'),
            'type'        => 'integer',
        ];

        $query_params['order'] = [
            'description' => __('Order sort attribute ascending or descending.'),
            'type'        => 'string',
            'default'     => 'asc',
            'enum'        => [
                'asc',
                'desc',
            ],
        ];

        $query_params['orderby'] = [
            'description' => __('Sort collection by term attribute.'),
            'type'        => 'string',
            'default'     => 'name',
            'enum'        => [
                'id',
                'include',
                'name',
                'slug',
                'include_slugs',
                'term_group',
                'description',
                'count',
            ],
        ];

        $query_params['hide_empty'] = [
            'description' => __('Whether to hide terms not assigned to any posts.'),
            'type'        => 'boolean',
            'default'     => false,
        ];

        $query_params['parent'] = [
            'description' => __('Limit result set to terms assigned to a specific parent.'),
            'type'        => 'integer',
        ];

        $query_params['post'] = [
            'description' => __('Limit result set to terms assigned to a specific post.'),
            'type'        => 'integer',
            'default'     => null,
        ];

        $query_params['slug'] = [
            'description' => __('Limit result set to terms with one or more specific slugs.'),
            'type'        => 'array',
            'items'       => [
                'type' => 'string',
            ],
        ];

        /**
         * Filters collection parameters for the terms controller.
         *
         * The dynamic part of the filter `$this->taxonomy` refers to the taxonomy
         * slug for the controller.
         *
         * This filter registers the collection parameter, but does not map the
         * collection parameter to an internal WP_Term_Query parameter.  Use the
         * `rest_{$this->taxonomy}_query` filter to set WP_Term_Query parameters.
         *
         * @since 4.7.0
         *
         * @param array       $query_params JSON Schema-formatted collection parameters.
         * @param WP_Taxonomy $taxonomy     Taxonomy object.
         */
        return \apply_filters("rest_any_term_collection_params", $query_params, $this->taxonomies);
    }
}
