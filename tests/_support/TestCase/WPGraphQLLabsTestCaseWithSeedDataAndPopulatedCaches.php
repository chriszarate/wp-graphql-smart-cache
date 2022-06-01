<?php
namespace TestCase\WPGraphQLLabs\TestCase;

use Exception;
use Tests\WPGraphQL\TestCase\WPGraphQLTestCase;
use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;
use WPGraphQL\Labs\Cache\Collection;

class WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches extends WPGraphQLTestCase {

	/**
	 * @var WP_User
	 */
	public $admin;

	/**
	 * @var Collection
	 */
	public $collection;

	/**
	 * @var WP_Post
	 */
	public $published_post;

	/**
	 * @var WP_Post
	 */
	public $draft_post;

	/**
	 * @var WP_Post
	 */
	public $published_page;

	/**
	 * @var WP_Post
	 */
	public $published_test_post_type;

	/**
	 * @var WP_Post
	 */
	public $draft_test_post_type;

	/**
	 * @var WP_Post;
	 */
	public $published_private_post_type;

	/**
	 * @var WP_Post;
	 */
	public $draft_private_post_type;

	/**
	 * @var WP_Post
	 */
	public $scheduled_post;

	/**
	 * @var WP_Post
	 */
	public $scheduled_post_with_category;

	/**
	 * @var WP_Term
	 */
	public $category;

	/**
	 * @var WP_Term
	 */
	public $test_taxonomy_term;

	/**
	 * @var WP_Term
	 */
	public $private_taxonomy_term;

	/**
	 * @var WP_Post
	 */
	public $mediaItem;

	/**
	 * @var WP_Comment
	 */
	public $comment;

	/**
	 * @var WP_Term
	 */
	public $tag;

	/**
	 * Holds the results of the executed queries. For reference in assertions in tests.
	 *
	 * @var array
	 */
	public $query_results;

	public function setUp(): void {
		parent::setUp();

		/**
		 * Clear the schema
		 */
		$this->clearSchema();

		// prevent default category from being added to posts on creation
		update_option( 'default_category', 0 );

		// enable caching for the whole test suite
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		register_post_type( 'test_post_type', [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'TestPostType',
			'graphql_plural_name' => 'TestPostTypes'
		] );

		register_taxonomy( 'test_taxonomy', [ 'test_post_type' ], [
			'public' => true,
			'show_in_graphql' => true,
			'graphql_single_name' => 'TestTaxonomyTerm',
			'graphql_plural_name' => 'TestTaxonomyTerms'
		] );

		register_post_type( 'private_post_type', [
			'public' => false,
			'show_in_graphql' => true,
			'graphql_single_name' => 'PrivatePostType',
			'graphql_plural_name' => 'PrivatePostTypes'
		] );

		register_taxonomy( 'private_taxonomy', [ 'private_post_type' ], [
			'public' => false,
			'show_in_graphql' => true,
			'graphql_single_name' => 'PrivateTaxonomyTerm',
			'graphql_plural_name' => 'PrivateTaxonomyTerms'
		] );

		$this->_createSeedData();
		$this->_populateCaches();

	}

	public function tearDown(): void {

		// disable caching
		delete_option( 'graphql_cache_section' );

		parent::tearDown();
	}

	public function _createSeedData() {

		// setup access to the Cache Collection class
		$this->collection = new Collection();

		// create an admin user
		$this->admin = self::factory()->user->create_and_get([
			'role' => 'administrator'
		]);

		// create a test tag
		$this->tag = self::factory()->term->create_and_get([
			'taxonomy' => 'post_tag',
			'term' => 'Test Tag'
		]);

		// create a test category
		$this->category = self::factory()->term->create_and_get([
			'taxonomy' => 'category',
			'term' => 'Test Category'
		]);

		$this->test_taxonomy_term = self::factory()->term->create_and_get([
			'taxonomy' => 'test_taxonomy',
			'term' => 'Test Custom Tax Term'
		]);

		$this->private_taxonomy_term = self::factory()->term->create_and_get([
			'taxonomy' => 'private_taxonomy',
			'term' => 'Private Term'
		]);

		// create a published post
		$this->published_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'tax_input' => [
				'post_tag' => [ $this->tag->term_id ],
				'category' => [ $this->category->term_id ]
			],
			'post_author' => $this->admin->ID,
		]);

		$this->scheduled_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'future',
			'post_title' => 'Test Scheduled Post',
			'post_author' => $this->admin,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
		]);

		$this->scheduled_post_with_category = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'future',
			'post_title' => 'Test Scheduled Post',
			'post_author' => $this->admin,
			'post_date' => date( "Y-m-d H:i:s", strtotime( '+1 day' ) ),
			'post_category' => [ $this->category->term_id ]
		]);

		// create a draft post
		$this->draft_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'draft',
			'tax_input' => [
				'post_tag' => [ $this->tag->term_id ],
				'category' => [ $this->category->term_id ]
			],
			'post_author' => $this->admin->ID,
		]);

		// create a published page
		$this->published_page = self::factory()->post->create_and_get([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->published_test_post_type = self::factory()->post->create_and_get([
			'post_type' => 'test_post_type',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->draft_test_post_type = self::factory()->post->create_and_get([
			'post_type' => 'test_post_type',
			'post_status' => 'draft',
			'post_author' => $this->admin->ID,
		]);

		$this->published_private_post_type = self::factory()->post->create_and_get([
			'post_type' => 'private_post_type',
			'post_status' => 'publish',
			'post_author' => $this->admin->ID,
		]);

		$this->draft_private_post_type = self::factory()->post->create_and_get([
			'post_type' => 'private_post_type',
			'post_status' => 'draft',
			'post_author' => $this->admin->ID,
		]);

		$this->assertInstanceOf( \WP_User::class, $this->admin );
		$this->assertInstanceOf( \WP_Post::class, $this->published_post );
		$this->assertInstanceOf( \WP_Post::class, $this->draft_post );
		$this->assertInstanceOf( \WP_Post::class, $this->published_page );
		$this->assertInstanceOf( \WP_Post::class, $this->published_test_post_type );
		$this->assertInstanceOf( \WP_Post::class, $this->draft_test_post_type );
		$this->assertInstanceOf( \WP_Post::class, $this->published_private_post_type );
		$this->assertInstanceOf( \WP_Post::class, $this->draft_private_post_type );
		$this->assertInstanceOf( \WP_Post::class, $this->scheduled_post_with_category );
		$this->assertInstanceOf( \WP_Term::class, $this->category );
		$this->assertInstanceOf( \WP_Term::class, $this->tag );
		$this->assertInstanceOf( \WP_Term::class, $this->test_taxonomy_term );
		$this->assertInstanceOf( \WP_Term::class, $this->private_taxonomy_term );
	}

	public function _populateCaches() {

		// purge all caches to clean up
		$this->collection->purge_all();

		// ensure the caches are empty to start
		$this->assertEmpty( $this->collection->get( 'post' ) );
		$this->assertEmpty( $this->collection->get( 'list:post' ) );

		$this->assertEmpty( $this->collection->get( 'term' ) );
		$this->assertEmpty( $this->collection->get( 'list:term' ) );

		$this->assertEmpty( $this->collection->get( 'user' ) );
		$this->assertEmpty( $this->collection->get( 'list:user' ) );

		$this->assertEmpty( $this->collection->get( 'comment' ) );
		$this->assertEmpty( $this->collection->get( 'list:comment' ) );

		$this->assertEmpty( $this->collection->get( 'nav_menu' ) );
		$this->assertEmpty( $this->collection->get( 'list:nav_menu' ) );

		$this->assertEmpty( $this->collection->get( 'menu_item' ) );
		$this->assertEmpty( $this->collection->get( 'list:menu_item' ) );

		// @todo: settings?

		$this->executeAndCacheQueries();


		$this->assertNotEmpty( $this->query_results['listPost']['cacheKey'] );
		$this->assertNotEmpty( $this->collection->get( 'list:post' ) );

		$this->assertNotEmpty( $this->collection->get( $this->query_results['singlePost']['cacheKey'] ) );

		$this->assertNotEmpty( $this->collection->get( $this->query_results['singleTag']['cacheKey'] ) );

		$this->assertNotEmpty( $this->collection->get( 'list:tag' ) );
		$this->assertNotEmpty( $this->collection->get( $this->query_results['listTag']['cacheKey'] ) );


//		$this->assertNotEmpty( $this->collection->get( 'user' ) );
//		$this->assertNotEmpty( $this->collection->get( 'list:user' ) );
//
//		$this->assertNotEmpty( $this->collection->get( 'comment' ) );
//		$this->assertNotEmpty( $this->collection->get( 'list:comment' ) );
//
//		$this->assertNotEmpty( $this->collection->get( 'nav_menu' ) );
//		$this->assertNotEmpty( $this->collection->get( 'list:nav_menu' ) );
//
//		$this->assertNotEmpty( $this->collection->get( 'menu_item' ) );
//		$this->assertNotEmpty( $this->collection->get( 'list:menu_item' ) );

		// @todo: settings?

	}

	public function getQueries() {

		return [
			'listPost' => [
				'name' => 'listPost',
				'query' => $this->getListPostQuery(),
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'posts.nodes', [
						'__typename' => 'Post',
						'databaseId' => $this->published_post->ID
					])
				],
				'expectedCacheKeys' => [
					'list:post'
				]
			],
			'singlePost' => [
				'name' => 'singlePost',
				'query' => $this->getSinglePostByDatabaseIdQuery(),
				'variables' => [ 'id' => $this->published_post->ID ],
				'assertions' => [
					$this->expectedField( 'post.__typename', 'Post' ),
					$this->expectedField( 'post.databaseId', $this->published_post->ID )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'post', $this->published_post->ID )
				]
			],
			'listPage' => [
				'name' => 'listPage',
				'query' => $this->getListPageQuery(),
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'pages.nodes', [
						'__typename' => 'Page',
						'databaseId' => $this->published_page->ID,
					])
				],
				'expectedCacheKeys' => [
					'list:page'
				]
			],
			'singlePage' => [
				'name' => 'singlePage',
				'query' => $this->getSinglePageByDatabaseIdQuery(),
				'variables' => [ 'id' => $this->published_page->ID ],
				'assertions' => [
					$this->expectedField( 'page.__typename', 'Page' ),
					$this->expectedField( 'page.databaseId', $this->published_page->ID )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'post', $this->published_page->ID ),
				]
			],
			'listTestPostType' => [
				'name' => 'listTestPostType',
				'query' => $this->getListTestPostTypeQuery(),
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'testPostTypes.nodes', [
						'__typename' => 'TestPostType',
						'databaseId' => $this->published_test_post_type->ID,
					])
				],
				'expectedCacheKeys' => [
					'list:testPostType'
				]
			],
			'singleTestPostType' => [
				'name' => 'singleTestPostType',
				'query' => $this->getSingleTestPostTypeByDatabaseIdQuery(),
				'variables' => [ 'id' => $this->published_test_post_type->ID ],
				'assertions' => [
					$this->expectedField( 'testPostType.__typename', 'TestPostType' ),
					$this->expectedField( 'testPostType.databaseId', $this->published_test_post_type->ID )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'post', $this->published_test_post_type->ID )
				]
			],
			'listPrivatePostType' => [
				'name' => 'listPrivatePostType',
				'query' => $this->getListPrivatePostTypeQuery(),
				'variables' => null,
				'assertions' => [
					// the nodes should be empty because it's a private post type
					$this->expectedField( 'privatePostTypes.nodes', [] )
				],
				'expectedCacheKeys' => null,
			],
			'singlePrivatePostType' => [
				'name' => 'singlePrivatePostType',
				'query' => $this->getSinglePrivatePostTypeByDatabaseIdQuery(),
				'variables' => [ 'id' => $this->published_private_post_type->ID ],
				'assertions' => [
					// since it's a private post type, the data should be null
					$this->expectedField( 'privatePostType', self::IS_NULL ),
				],
				'expectedCacheKeys' => null,
			],
			'singleContentNode' => [
				'name' => 'singleContentNode',
				'query' => $this->getSingleContentNodeByDatabaseId(),
				'variables' => [
					'id' => $this->published_post->ID,
				],
				'assertions' => [
					$this->expectedField( 'contentNode.__typename', 'Post' ),
					$this->expectedField( 'contentNode.databaseId', $this->published_post->ID )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'post', $this->published_post->ID ),
				],
			],
			'listContentNode' => [
				'name' => 'listContentNode',
				'query' => $this->getListContentNodeQuery(),
				'variables' => [
					'id' => $this->published_post->ID,
				],
				'assertions' => [
					$this->expectedObject( 'contentNodes.nodes', [
						'__typename' => 'Post',
						'databaseId' => $this->published_post->ID
					]),
					$this->expectedObject( 'contentNodes.nodes', [
						'__typename' => 'Page',
						'databaseId' => $this->published_page->ID
					])
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'post', $this->published_post->ID ),
					'node:' . $this->toRelayId( 'post', $this->published_page->ID ),
				],
			],
			'singleNodeById' => [
				'name' => 'singleNodeById',
				'query' => $this->getSingleNodeByIdQuery(),
				'variables' => [ 'id' => $this->toRelayId( 'post', $this->published_post->ID ) ],
				'assertions' => [
					$this->expectedField( 'node.__typename', 'Post' ),
					$this->expectedField( 'node.databaseId', $this->published_post->ID )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'post', $this->published_post->ID )
				]
			],
			'singleNodeByUri' => [
				'name' => 'singleNodeByUri',
				'query' => $this->getSingleNodeByUriQuery(),
				'variables' => [ 'uri' => get_permalink( $this->published_post->ID ) ],
				'assertions' => [
					$this->expectedField( 'nodeByUri.__typename', 'Post' ),
					$this->expectedField( 'nodeByUri.databaseId', $this->published_post->ID )
				]
			],
			'listTag' => [
				'name' => 'listTag',
				'query' => $this->getListTagQuery(),
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'tags.nodes', [
						'__typename' => 'Tag',
						'databaseId' => $this->tag->term_id,
						'name' => $this->tag->name,
					])
				],
				'expectedCacheKeys' => [
					'list:tag'
				],
			],
			'singleTag' => [
				'name' => 'singleTag',
				'query' => $this->getSingleTagByDatabaseIdQuery(),
				'variables' => [ 'id' => $this->tag->term_id ],
				'assertions' => [
					$this->expectedField( 'tag.__typename', 'Tag' ),
					$this->expectedField( 'tag.databaseId', $this->tag->term_id )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'term', $this->tag->term_id )
				]
			],
			'listCategory' => [
				'name' => 'listCategory',
				'query' => $this->getListCategoryQuery(),
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'categories.nodes', [
						'__typename' => 'Category',
						'databaseId' => $this->category->term_id,
						'name' => $this->category->name,
					])
				],
				'expectedCacheKeys' => [
					'list:category'
				],
			],
			'singleCategory' => [
				'name' => 'singleCategory',
				'query' => $this->getSingleCategoryByDatabaseIdQuery(),
				'variables' => [ 'id' => $this->category->term_id ],
				'assertions' => [
					$this->expectedField( 'category.__typename', 'Category' ),
					$this->expectedField( 'category.databaseId', $this->category->term_id )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'term', $this->category->term_id )
				]
			],
			'singleTestTaxonomyTerm' => [
				'name' => 'singleTestTaxonomyTerm',
				'query' => $this->getSingleTestTaxonomyTermByDatabaseIdQuery(),
				'variables' => [ 'id' => $this->test_taxonomy_term->term_id ],
				'assertions' => [
					$this->expectedField( 'testTaxonomyTerm.__typename', 'TestTaxonomyTerm' ),
					$this->expectedField( 'testTaxonomyTerm.databaseId', $this->test_taxonomy_term->term_id )
				],
				'expectedCacheKeys' => [
					'node:' . $this->toRelayId( 'term', $this->test_taxonomy_term->term_id )
				]
			],
			'listTestTaxonomyTerm' => [
				'name' => 'listTestTaxonomyTerm',
				'query' => $this->getListTestTaxonomyTermsQuery(),
				'variables' => null,
				'assertions' => [
					$this->expectedObject( 'testTaxonomyTerms.nodes', [
						'__typename' => 'TestTaxonomyTerm',
						'databaseId' => $this->test_taxonomy_term->term_id,
						'name' => $this->test_taxonomy_term->name,
					])
				],
				'expectedCacheKeys' => [
					'list:testTaxonomyTerm'
				],
			],
//			@todo: I believe the WPGraphQL Model Layer might have some bugs to fix re: private taxonomies? 🤔
//			'singlePrivateTaxonomyTerm' => [
//				'name' => 'singlePrivateTaxonomyTerm',
//				'query' => $this->getSinglePrivateTaxonomyTermByDatabaseIdQuery(),
//				'variables' => [ 'id' => $this->private_taxonomy_term->term_id ],
//				'assertions' => [
//					$this->expectedField( 'privateTaxonomyTerm', self::IS_NULL ),
//				],
//				'expectedCacheKeys' => []
//			],
//			'listPrivateTaxonomyTerm' => [
//				'name' => 'listPrivateTaxonomyTerm',
//				'query' => $this->getListTestTaxonomyTermsQuery(),
//				'variables' => null,
//				'assertions' => [
//					$this->expectedField( 'privateTaxonomyTerms.nodes', [])
//				],
//				'expectedCacheKeys' => [],
//			],
			// @todo: menus, menuItems, comments, users, settings...
		];
	}

	/**
	 * NOTE: I tried using a dataProvider but data providers run BEFORE setUp and that
	 * caused a lot of other headaches
	 *
	 * @throws Exception
	 */
	public function executeAndCacheQueries() {

		$queries = $this->getQueries();

		foreach ( $queries as $query_name => $testable_query ) {

			$name = $testable_query['name'];
			$query = $testable_query['query'];
			$variables = isset( $testable_query['variables'] ) ? $testable_query['variables'] : null;
			$assertions = $testable_query['assertions'];
			$expectedCacheKeys = isset( $testable_query['expectedCacheKeys'] ) ? $testable_query['expectedCacheKeys'] : [];

			// build the cache key
			$cache_key = $this->collection->build_key( null, $query, isset( $variables ) ? $variables : null );

			// ensure there's no value already cached under the cache key
			$this->assertEmpty( $this->collection->get( $cache_key ) );

			// execute the query
			$actual = graphql( [
				'query'     => $query,
				'variables' => $variables,
			] );

			// ensure the query was successful
			$this->assertQuerySuccessful( $actual, $assertions );

			// ensure the results are now cached under the cache key
			$this->assertNotEmpty( $this->collection->get( $cache_key ) );
			$this->assertSame( $actual, $this->collection->get( $cache_key ) );

			// check any expected cache keys to ensure they're not empty
			if ( ! empty( $expectedCacheKeys ) ) {
				foreach ( $expectedCacheKeys as $expected_cache_key ) {

					$this->assertNotEmpty( $this->collection->get( $expected_cache_key ) );
					$this->assertContains( $cache_key, $this->collection->get( $expected_cache_key ) );
				}
			}

			$this->query_results[ $name ] = [
				'name' => $name,
				'actual'   => $actual,
				'cacheKey' => $cache_key,
				'expectedCacheKeys' => $expectedCacheKeys,
				'query' => $query,
				'variables' => $variables,
			];

		}

		return $this->query_results;
	}

	/**
	 * @return array
	 */
	public function getEvictedCaches() {
		$empty = [];
		if ( ! empty( $this->query_results ) ) {
			foreach ( $this->query_results as $name => $result ) {
				$cache = $this->collection->get( $result['cacheKey'] );
				if ( empty( $cache ) ) {
					$empty[] = $name;
				}
			}
		}

		return $empty;
	}

	/**
	 * @return int[]|string[]
	 */
	public function getNonEvictedCaches() {
		return array_diff( array_keys( $this->query_results ), $this->getEvictedCaches() );
	}

	/**
	 * @return string
	 */
	public function getSinglePostByDatabaseIdQuery() {
		return '
		query GetPost($id:ID!) {
		  post(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPostQuery() {
		return '
		query GetPosts {
		  posts {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSinglePageByDatabaseIdQuery() {
		return '
		query GetPage($id:ID!) {
		  page(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPageQuery() {
		return '
		query GetPages {
		  pages {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleTestPostTypeByDatabaseIdQuery() {
		return '
		query GetTestPostType($id:ID!) {
		  testPostType(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListTestPostTypeQuery() {
		return '
		query GetTestPostTypes {
		  testPostTypes {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSinglePrivatePostTypeByDatabaseIdQuery() {
		return '
		query GetPrivatePostType($id:ID!) {
		  privatePostType(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPrivatePostTypeQuery() {
		return '
		query GetPrivatePostTypes {
		  privatePostTypes {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListContentNodeQuery() {
		return '
		query GetContentNodes {
		  contentNodes {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleContentNodeByDatabaseId() {
		return '
		query GetContentNode($id:ID!) {
		  contentNode(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleNodeByUriQuery() {
		return '
		query GetNodeByUri($uri: String!) {
		  nodeByUri(uri: $uri) {
		    __typename
		    id
		    ... on DatabaseIdentifier {
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleNodeByIdQuery() {
		return '
		query GetNode($id: ID!) {
		  node(id: $id) {
		    __typename
		    id
		    ... on DatabaseIdentifier {
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListTagQuery() {
		return '
		query GetTags {
		  tags {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	/**
	 * @return string
	 */
	public function getSingleTagByDatabaseIdQuery() {
		return '
		query GetTag($id:ID!) {
		  tag(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListCategoryQuery() {
		return '
		query GetCategories {
		  categories {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleCategoryByDatabaseIdQuery() {
		return '
		query GetCategory($id:ID!) {
		  category(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListTestTaxonomyTermsQuery() {
		return '
		query GetTestTaxonomyTerms {
		  testTaxonomyTerms {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleTestTaxonomyTermByDatabaseIdQuery() {
		return '
		query GetTestTaxonomyTerm($id:ID!) {
		  testTaxonomyTerm(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListPrivateTaxonomyTermsQuery() {
		return '
		query GetPrivateTaxonomyTerms {
		  privateTaxonomyTerms {
		    nodes {
		      __typename
		      databaseId
		      name
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSinglePrivateTaxonomyTermByDatabaseIdQuery() {
		return '
		query GetPrivateTaxonomyTerm($id:ID!) {
		  privateTaxonomyTerm(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListMenusQuery() {
		return '
		query GetMenus {
		  menus {
		    nodes {
		      __typename
		      id
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleMenuByDatabaseIdQuery() {
		return '
		query GetMenu($id:ID!) {
		  menu(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListMenuItemsQuery() {
		return '
		query GetMenuItems {
		  menuItems {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleMenuItemByDatabaseIdQuery() {
		return '
		query GetMenuItem($id:ID!) {
		  menuItem(id:$id idType: DATABASE_ID) {
		    __typename
		    databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getUsersQuery() {
		return '
		query GetUsers {
		  users {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getSingleUserByDatabaseIdQuery() {
		return '
		query GetUser($id:ID!) {
		  user(id:$id idType:DATABASE_ID) {
	        __typename
	        databaseId
		  }
		}
		';
	}

	/**
	 * Returns a query for a user by database ID, as well
	 * as the users connected posts
	 *
	 * @return string
	 */
	public function getSingleUserByDatabaseIdWithAuthoredPostsQuery() {

		return '
		query GetUser($id:ID!) {
		  user(id:$id idType:DATABASE_ID) {
	        __typename
	        databaseId
	        posts {
	          nodes {
	            __typename
	            id
	          }
	        }
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getListCommentsQuery() {
		return '
		query GetComments {
		  comments {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	/**
	 * @todo: As of writing this, the comment query doesn't allow
	 *      fetching a single comment by database id
	 *
	 * @return string
	 */
	public function getSingleCommentByGlobalIdQuery() {
		return '
		query GetComment($id:ID!) {
		  comment(id:$id) {
	        __typename
	        databaseId
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getGeneralSettingsQuery() {
		return '
		query GetGeneralSettings {
		  generalSettings {
		    dateFormat
		    description
		    email
		    language
		    startOfWeek
		    timeFormat
		    timezone
		    title
		    url
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getReadingSettingsQuery() {
		return '
		query GetReadingSettings {
		  readingSettings {
		    postsPerPage
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getWritingSettingsQuery() {
		return '
		query GetWritingSettings {
		  writingSettings {
		    defaultCategory
		    defaultPostFormat
		    useSmilies
		  }
		}
		';
	}

	/**
	 * @return string
	 */
	public function getDiscussionSettingsQuery() {
		return '
		query GetDiscussionSettings {
		  defaultCommentStatus
		  defaultPingStatus
		}
		';
	}

	/**
	 * @return string
	 */
	public function getAllSettingsQuery() {
		return '
		query GetAllSettings {
		  allSettings {
		    discussionSettingsDefaultCommentStatus
		    discussionSettingsDefaultPingStatus
		    generalSettingsDateFormat
		    generalSettingsDescription
		    generalSettingsEmail
		    generalSettingsLanguage
		    generalSettingsStartOfWeek
		    generalSettingsTimeFormat
		    readingSettingsPostsPerPage
		    writingSettingsDefaultCategory
		    writingSettingsDefaultPostFormat
		    writingSettingsUseSmilies
		  }
		}
		';
	}

}
