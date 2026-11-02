<?php

namespace helper;

class AnyTermsRestController extends \WP_REST_Terms_Controller
{
    /** @var string[] Taxonomies exposed by this controller. */
    protected array $taxonomies;

    public function __construct()
    {
        // 'category' is a representative real taxonomy so inherited core methods
        // that dereference get_taxonomy($this->taxonomy) keep working.
        parent::__construct('category');

        $this->taxonomies = array_values(TaxonomyHelper::getAllRestTaxonomies());
        $this->namespace  = 'sc/v1';
        $this->rest_base  = 'any_terms';
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
     * The parent only checks the representative taxonomy, so check every requested one.
     */
    #[\Override]
    public function get_items_permissions_check($request)
    {
        foreach ($this->requested_taxonomies($request) as $taxonomy) {
            if ('edit' === $request['context'] && ! \current_user_can(\get_taxonomy($taxonomy)->cap->edit_terms)) {
                return new \WP_Error(
                    'rest_forbidden_context',
                    __('Sorry, you are not allowed to edit terms in this taxonomy.'),
                    [ 'status' => \rest_authorization_required_code() ]
                );
            }
        }

        if (! empty($request['post'])) {
            $post = \get_post($request['post']);
            if (! $post) {
                return new \WP_Error('rest_post_invalid_id', __('Invalid post ID.'), [ 'status' => 400 ]);
            }
            if (! \is_post_publicly_viewable($post) && ! \current_user_can('read_post', $post->ID)) {
                return new \WP_Error(
                    'rest_forbidden_context',
                    __('Sorry, you are not allowed to view terms for this post.'),
                    [ 'status' => \rest_authorization_required_code() ]
                );
            }
        }

        return true;
    }

    #[\Override]
    public function get_items($request)
    {
        // Excluding every taxonomy leaves nothing, but an empty taxonomy list
        // would make get_terms() search all of them, private ones included.
        if (! $this->requested_taxonomies($request)) {
            $response = \rest_ensure_response([]);
            $response->header('X-WP-Total', '0');
            $response->header('X-WP-TotalPages', '0');

            return $response;
        }

        // Core has no query hook like the posts controller, so widen its
        // representative-taxonomy query for the duration of this request.
        $widen = function (array $prepared_args) use ($request) {
            $prepared_args['taxonomy'] = $this->requested_taxonomies($request);

            // Core reads a post's terms from the representative taxonomy only.
            if (! empty($prepared_args['post'])) {
                $prepared_args['object_ids'] = $prepared_args['post'];
                unset($prepared_args['post']);
            }

            /**
             * Filters get_terms() arguments of an any-terms collection request.
             *
             * @param array            $prepared_args Array of arguments for get_terms().
             * @param \WP_REST_Request $request       The REST API request.
             */
            return \apply_filters('rest_any_terms_query', $prepared_args, $request);
        };

        \add_filter("rest_{$this->taxonomy}_query", $widen, PHP_INT_MAX);
        try {
            $response = parent::get_items($request);
        } finally {
            \remove_filter("rest_{$this->taxonomy}_query", $widen, PHP_INT_MAX);
        }

        // Core builds the pagination links from the representative taxonomy's route.
        if ($response instanceof \WP_REST_Response && isset($response->get_headers()['Link'])) {
            $from  = \rest_url(\rest_get_route_for_taxonomy_items($this->taxonomy));
            $to    = \rest_url("{$this->namespace}/{$this->rest_base}");
            $links = array_map(fn (string $link) => str_replace($from, $to, $link), (array) $response->get_headers()['Link']);
            $response->header('Link', implode(', ', $links));
        }

        return $response;
    }

    private function requested_taxonomies(\WP_REST_Request $request): array
    {
        return $request['type'] ?: array_values(array_diff($this->taxonomies, $request['except_type'] ?? []));
    }

    #[\Override]
    protected function get_term($id)
    {
        // The parent only accepts the representative taxonomy.
        $term = (int) $id > 0 ? \get_term((int) $id) : null;
        if (! $term instanceof \WP_Term || ! in_array($term->taxonomy, $this->taxonomies, true)) {
            return new \WP_Error('rest_term_invalid', __('Term does not exist.'), [ 'status' => 404 ]);
        }

        return $term;
    }

    /**
     * Renders each term with its own taxonomy's controller, so the output matches /wp/v2.
     */
    #[\Override]
    public function prepare_item_for_response($item, $request)
    {
        $controller = \get_taxonomy($item->taxonomy)->get_rest_controller();
        if (! $controller instanceof \WP_REST_Terms_Controller) {
            return parent::prepare_item_for_response($item, $request);
        }

        // any_terms has never returned _links unless asked.
        if (! isset($request['_fields']) && ! $request->has_param('_embed')) {
            $request = clone $request;
            $request->set_param('_fields', array_diff($controller->get_fields_for_response($request), ['_links']));
        }

        return $controller->prepare_item_for_response($item, $request);
    }

    /**
     * Union of every exposed taxonomy's schema; the first definition of a property wins.
     */
    #[\Override]
    public function get_item_schema()
    {
        if (! $this->schema) {
            $properties = [];
            foreach ($this->taxonomies as $taxonomy) {
                $properties += \get_taxonomy($taxonomy)->get_rest_controller()?->get_item_schema()['properties'] ?? [];
            }

            $this->schema = [
                '$schema'    => 'http://json-schema.org/draft-04/schema#',
                'title'      => 'any_term',
                'type'       => 'object',
                'properties' => $properties,
            ];
        }

        return $this->add_additional_fields_schema($this->schema);
    }

    #[\Override]
    public function get_collection_params()
    {
        // Parent (WP_REST_Terms_Controller) supplies the full term collection
        // params for the representative 'category' taxonomy — context, exclude,
        // include, order, orderby, hide_empty, parent, post, slug. We only add
        // the multi-taxonomy selectors on top.
        $query_params = parent::get_collection_params();

        $query_params['type'] = [
            'description' => __('Limit results to one or more taxonomies.'),
            'type'        => 'array',
            'items'       => [ 'type' => 'string', 'enum' => $this->taxonomies ],
        ];

        $query_params['except_type'] = [
            'description' => __('Exclude one or more taxonomies from the results.'),
            'type'        => 'array',
            'items'       => [ 'type' => 'string', 'enum' => $this->taxonomies ],
            'default'     => [],
        ];

        // 'category' is hierarchical, so the parent omits offset; re-add it since
        // the aggregated set also spans non-hierarchical taxonomies.
        $query_params['offset'] ??= [
            'description' => __('Offset the result set by a specific number of items.'),
            'type'        => 'integer',
        ];

        /**
         * Filters collection parameters for the any-terms controller.
         *
         * @param array    $query_params JSON Schema-formatted collection parameters.
         * @param string[] $taxonomies   Taxonomies handled by the controller.
         */
        return \apply_filters("rest_any_term_collection_params", $query_params, $this->taxonomies);
    }
}
