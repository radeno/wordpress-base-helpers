<?php

namespace helper;

class AnyPostsRestController extends \WP_REST_Posts_Controller
{
    /** @var string[] Post types exposed by this controller. */
    protected array $post_types;

    public function __construct()
    {
        // 'post' is a representative real type so inherited core methods that
        // dereference get_post_type_object($this->post_type) keep working.
        parent::__construct('post');

        $this->post_types = array_values(PostTypeHelper::getAllRestPostTypes());
        $this->namespace  = 'sc/v1';
        $this->rest_base  = 'any_posts';
    }

    /**
     * Registers the read-only routes; the parent would also register write routes.
     */
    #[\Override]
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
                        'description' => __('Unique identifier for the object.'),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                    'args'                => [
                        'context'  => $this->get_context_param([ 'default' => 'view' ]),
                        'password' => [
                            'description' => __('The password for the post if it is password protected.'),
                            'type'        => 'string',
                        ],
                    ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );
    }

    /**
     * The parent only checks the representative type, so check every requested one.
     */
    #[\Override]
    public function get_items_permissions_check($request)
    {
        if ('edit' !== $request['context']) {
            return true;
        }

        foreach ($this->requested_post_types($request) as $postType) {
            if (! \current_user_can(\get_post_type_object($postType)->cap->edit_posts)) {
                return new \WP_Error(
                    'rest_forbidden_context',
                    __('Sorry, you are not allowed to edit posts in this post type.'),
                    [ 'status' => \rest_authorization_required_code() ]
                );
            }
        }

        return true;
    }

    #[\Override]
    public function get_items($request)
    {
        // Excluding every type leaves nothing, but an empty post_type would
        // make WP_Query fall back to 'post'.
        if (! $this->requested_post_types($request)) {
            $response = \rest_ensure_response([]);
            $response->header('X-WP-Total', '0');
            $response->header('X-WP-TotalPages', '0');

            return $response;
        }

        $response = parent::get_items($request);

        // Core builds the pagination links from the representative 'post' route.
        if ($response instanceof \WP_REST_Response && isset($response->get_headers()['Link'])) {
            $from  = \rest_url(\rest_get_route_for_post_type_items($this->post_type));
            $to    = \rest_url("{$this->namespace}/{$this->rest_base}");
            $links = array_map(fn (string $link) => str_replace($from, $to, $link), (array) $response->get_headers()['Link']);
            $response->header('Link', implode(', ', $links));
        }

        return $response;
    }

    #[\Override]
    protected function prepare_items_query($prepared_args = [], $request = null)
    {
        $prepared_args['post_type'] = $this->requested_post_types($request);

        // The parent only builds tax_query for the representative type's taxonomies.
        $handled = \get_object_taxonomies($this->post_type);
        foreach (TaxonomyHelper::getAllRestTaxonomies('objects') as $taxonomy) {
            if (in_array($taxonomy->name, $handled, true)) {
                continue;
            }
            $base = $taxonomy->rest_base ?: $taxonomy->name;
            foreach ([$base => 'IN', "{$base}_exclude" => 'NOT IN'] as $param => $operator) {
                if (! empty($request[$param])) {
                    $prepared_args['tax_query'][] = [
                        'taxonomy'         => $taxonomy->name,
                        'field'            => 'term_id',
                        'terms'            => $request[$param],
                        'include_children' => false,
                        'operator'         => $operator,
                    ];
                }
            }
        }
        if (! empty($request['tax_relation']) && ! empty($prepared_args['tax_query'])) {
            $prepared_args['tax_query']['relation'] = $request['tax_relation'];
        }

        /**
         * Filters the WP_Query arguments of an any-posts collection request.
         *
         * @param array           $prepared_args Array of arguments for WP_Query.
         * @param WP_REST_Request $request       The REST API request.
         */
        return parent::prepare_items_query(\apply_filters('rest_any_posts_query', $prepared_args, $request), $request);
    }

    private function requested_post_types(\WP_REST_Request $request): array
    {
        return $request['type'] ?: array_values(array_diff($this->post_types, $request['except_type'] ?? []));
    }

    #[\Override]
    protected function get_post($id)
    {
        // The parent only accepts the representative type.
        $post = (int) $id > 0 ? \get_post((int) $id) : null;
        if (! $post || ! in_array($post->post_type, $this->post_types, true)) {
            return new \WP_Error('rest_post_invalid_id', __('Invalid post ID.'), [ 'status' => 404 ]);
        }

        return $post;
    }

    /**
     * Renders each post with its own type's controller, so the output matches /wp/v2.
     */
    #[\Override]
    public function prepare_item_for_response($item, $request)
    {
        $controller = \get_post_type_object($item->post_type)->get_rest_controller();
        if (! $controller instanceof \WP_REST_Posts_Controller) {
            return parent::prepare_item_for_response($item, $request);
        }

        // any_posts has never returned _links unless asked; building them costs a query per post.
        if (! isset($request['_fields']) && ! $request->has_param('_embed')) {
            $request = clone $request;
            $request->set_param('_fields', array_diff($controller->get_fields_for_response($request), ['_links']));
        }

        return $controller->prepare_item_for_response($item, $request);
    }

    /**
     * Union of every exposed type's schema; the first definition of a property wins.
     */
    #[\Override]
    public function get_item_schema()
    {
        if (! $this->schema) {
            $properties = [];
            foreach ($this->post_types as $postType) {
                $properties += \get_post_type_object($postType)->get_rest_controller()?->get_item_schema()['properties'] ?? [];
            }

            $this->schema = [
                '$schema'    => 'http://json-schema.org/draft-04/schema#',
                'title'      => 'any_post',
                'type'       => 'object',
                'properties' => $properties,
            ];
        }

        return $this->add_additional_fields_schema($this->schema);
    }

    #[\Override]
    public function get_collection_params()
    {
        $query_params = parent::get_collection_params();

        // Multi-type selectors (specific to this aggregate controller). All the
        // standard post params — author, dates, order/orderby, status, sticky,
        // search and search_columns (WP 6.1+) — come from the parent verbatim.
        $query_params['type'] = [
            'description' => __('Limit results to one or more post types.'),
            'type'        => 'array',
            'items'       => [ 'type' => 'string', 'enum' => $this->post_types ],
        ];

        $query_params['except_type'] = [
            'description' => __('Exclude one or more post types from the results.'),
            'type'        => 'array',
            'items'       => [ 'type' => 'string', 'enum' => $this->post_types ],
            'default'     => [],
        ];

        // The representative 'post' type lacks page-attributes, so re-add
        // menu_order filtering/ordering, which is meaningful across the set.
        $query_params['menu_order'] = [
            'description' => __('Limit result set to posts with a specific menu_order value.'),
            'type'        => 'integer',
        ];
        if (! in_array('menu_order', $query_params['orderby']['enum'], true)) {
            $query_params['orderby']['enum'][] = 'menu_order';
        }

        // 'post' is not hierarchical, so the parent omits parent filters; the
        // aggregate spans hierarchical types (e.g. page), so re-add them.
        $query_params['parent'] ??= [
            'description' => __('Limit result set to items with particular parent IDs.'),
            'type'        => 'array',
            'items'       => [ 'type' => 'integer' ],
            'default'     => [],
        ];
        $query_params['parent_exclude'] ??= [
            'description' => __('Limit result set to all items except those of a particular parent ID.'),
            'type'        => 'array',
            'items'       => [ 'type' => 'integer' ],
            'default'     => [],
        ];

        // The parent only registers the representative type's taxonomies; add
        // term filters for every REST taxonomy so any type can be filtered.
        $taxonomies = TaxonomyHelper::getAllRestTaxonomies('objects');

        if (! empty($taxonomies)) {
            $query_params['tax_relation'] = [
                'description' => __('Limit result set based on relationship between multiple taxonomies.'),
                'type'        => 'string',
                'enum'        => [ 'AND', 'OR' ],
            ];
        }

        foreach ($taxonomies as $taxonomy) {
            $base = $taxonomy->rest_base ?: $taxonomy->name;

            $query_params[ $base ] = [
                /* translators: %s: Taxonomy name. */
                'description' => sprintf(__('Limit result set to all items that have the specified term assigned in the %s taxonomy.'), $base),
                'type'        => 'array',
                'items'       => [ 'type' => 'integer' ],
                'default'     => [],
            ];

            $query_params[ $base . '_exclude' ] = [
                /* translators: %s: Taxonomy name. */
                'description' => sprintf(__('Limit result set to all items except those that have the specified term assigned in the %s taxonomy.'), $base),
                'type'        => 'array',
                'items'       => [ 'type' => 'integer' ],
                'default'     => [],
            ];
        }

        /**
         * Filter collection parameters for the any-posts controller.
         *
         * @param array    $query_params JSON Schema-formatted collection parameters.
         * @param string[] $post_types   Post types handled by the controller.
         */
        return \apply_filters("rest_any_post_collection_params", $query_params, $this->post_types);
    }
}
