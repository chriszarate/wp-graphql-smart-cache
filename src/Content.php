<?php
/**
 * Content
 *
 * @package Wp_Graphql_Persisted_Queries
 */

namespace WPGraphQL\PersistedQueries;

class Content {

	public $type_name = 'graphql_query';

	public static function register() {
		$content = new Content();

		register_post_type(
			$content->type_name,
			[
				'labels'      => [
					'name'          => __( 'GraphQLQueries', 'wp-graphql-persisted-queries' ),
					'singular_name' => __( 'GraphQLQuery', 'wp-graphql-persisted-queries' ),
				],
				'description' => 'Saved GraphQL queries',
				'public'      => true,
				'show_ui'     => true,
				'taxonomies'  => [
					'graphql_persisted_queries',
				],
			]
		);

		register_taxonomy(
			'graphql_persisted',
			$content->type_name,
			[
				'description' => 'Taxonomy for saved GraphQL queries',
			]
		);

		register_taxonomy_for_object_type( 'graphql_persisted', $content->type_name );
	}

	/**
	 * Process request looking for when queryid and query are present.
	 * Save the query and remove it from the request
	 *
	 * @param  array $parsed_body_params Request parameters.
	 * @param  array $request_context An array containing the both body and query params
	 * @return string Updated $parsed_body_params Request parameters.
	 */
	public static function filter_request_data( $parsed_body_params, $request_context ) {

		if ( isset($parsed_body_params['query']) && isset($parsed_body_params['queryId']) ) {
			// save the query
			$content = new Content();
			$content->save( $parsed_body_params['queryId'], $parsed_body_params['query'] );

			// remove it from process body params so graphql-php operation proceeds without conflict.
			unset( $parsed_body_params['query'] );
		}
		return $parsed_body_params;
	}

	/**
	 * Load a persisted query corresponding to a query ID (hash).
	 *
	 * @param  string $query_id Query ID
	 * @return string Query
	 */
	public function get( $query_id ) {
		// Queries are persisted via the custom post type of our type
		$post = get_page_by_path( $query_id, 'OBJECT', $this->type_name );

		if ( empty( $post->post_content ) ) {
			return;
		}

		// Verify the query hash matches the provided query_id
		if ( ! $this->verifyHash( $query_id, $post->post_content ) ) {
			return;
		}

		return $post->post_content;
	}

	/**
	 * Save a query by query ID (hash).
	 *
	 * @param  string $query_id Query string str256 hash
	 */
	public function save( $query_id, $query ) {
		// If have this query id saved already
		if ( ! empty( $this->get( $query_id ) ) ) {
			return;
		}

		// Verify the query hash matches the provided query_id
		if ( ! $this->verifyHash( $query_id, $query ) ) {
			return;
		}

		$query_operation_name = \GraphQL\Utils\AST::getOperationAST(
			\GraphQL\Language\Parser::parse( $query )
		);

		$data = [
			'post_content' => $query,
			'post_name'    => $query_id,
			'post_title'   => $query_operation_name,
			'post_status'  => 'publish',
			'post_type'    => $this->type_name,
		];

		// The post ID on success. The value 0 or WP_Error on failure.
		wp_insert_post( $data );
	}

	/**
	 * Generate query hash for graphql query string
	 *
	 * @param  string Query
	 * @return string $query_id Query string str256 hash
	 *
	 * @throws \GraphQL\Error\SyntaxError
	 */
	public function generateHash( $query ) {
		$ast     = \GraphQL\Language\Parser::parse( $query );
		$printed = \GraphQL\Language\Printer::doPrint( $ast );
		return hash( 'sha256', $printed );
	}

	/**
	 * Verify the query_id matches the query hash
	 *
	 * @param  string $query_id Query string str256 hash
	 * @param  string Query
	 * @return boolean
	 *
	 * @throws \GraphQL\Error\SyntaxError
	 */
	public function verifyHash( $query_id, $query ) {
		return $this->generateHash( $query ) === $query_id;
	}
}
