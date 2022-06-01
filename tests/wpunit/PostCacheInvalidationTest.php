<?php

class PostCacheInvalidationTest extends \TestCase\WPGraphQLLabs\TestCase\WPGraphQLLabsTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	/**
	 * Test behavior when an auto-draft post is created
	 *
	 * - given:
	 *   - a query for a single pre-existing post is in the cache
	 *   - a query for a list of posts is in the cache
	 *   - a query for contentNodes is in the cache
	 *   - a query for a page is in the cache
	 *   - a query for a list of pages is in the cache
	 *   - a query for a tag is in the cache
	 *   - a query for a list of tags is in the cache
	 *   - a query for a list of users is in the cache
	 *   - a query for the author of the post is in the cache
	 *
	 * - when:
	 *   - a scheduled post is published
	 *
	 * - assert:
	 *   - query for list of posts remains cached
	 *   - query for contentNodes remains cached
	 *   - query for single pre-exising post remains cached
	 *   - query for a page remains cached
	 *   - query for list of pages remains cached
	 *   - query for tag remains cached
	 *   - query for list of tags remains cached
	 *   - query for list of users remains cached
	 *   - query for the author of the post remains cached
	 *
	 *
	 * @throws Exception
	 */
	public function testCreateDraftPostDoesNotInvalidatePostCache() {

		// all queries should be in the cache, non should be empty
		$this->assertEmpty( $this->getEvictedCaches() );

		// create an auto draft post
		self::factory()->post->create([
			'post_status' => 'auto-draft'
		]);

		// after creating an auto-draft post, there should be no caches that were emptied
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	/**
	 * Test behavior when a scheduled post is published
	 *
	 * - given:
	 *   - a query for a single pre-existing post is in the cache
	 *   - a query for a list of posts is in the cache
	 *   - a query for contentNodes is in the cache
	 *   - a query for a page is in the cache
	 *   - a query for a list of pages is in the cache
	 *   - a query for a tag is in the cache
	 *   - a query for a list of tags is in the cache
	 *   - a query for a list of users is in the cache
	 *   - a query for the author of the post is in the cache
	 *
	 * - when:
	 *   - a scheduled post is published
	 *
	 * - assert:
	 *   - query for list of posts is invalidated
	 *   - query for contentNodes is invalidated
	 *   - query for single pre-exising post remains cached
	 *   - query for a page remains cached
	 *   - query for list of pages remains cached
	 *   - query for tag remains cached
	 *   - query for list of tags remains cached
	 *   - query for list of users remains cached
	 *   - query for the author of the post remains cached
	 *
	 * @throws Exception
	 */
	public function testPublishingScheduledPostWithoutAssociatedTerm() {

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the scheduled post
		wp_publish_post( $this->scheduled_post );

		// get the evicted caches
		$emptied_caches = $this->getEvictedCaches();

		// when publishing a scheduled post, the listPost and listContentNode queries should have been cleared
		$this->assertContains( 'listPost', $emptied_caches );
		$this->assertContains( 'listContentNode', $emptied_caches );

		// Ensure that other caches have not been emptied
		$this->assertNotContains( 'listTag', $emptied_caches );
		$this->assertNotContains( 'listCategory', $emptied_caches );

	}

	/**
	 * Test behavior when a scheduled post (that has a category assigned to it) is published
	 *
	 * - given:
	 *   - a query for a single pre-existing post is in the cache
	 *   - a query for a list of posts is in the cache
	 *   - a query for contentNodes is in the cache
	 *   - a query for a page is in the cache
	 *   - a query for a list of pages is in the cache
	 *   - a query for a tag is in the cache
	 *   - a query for a list of tags is in the cache
	 *   - a query for a list of users is in the cache
	 *   - a query for the author of the post is in the cache
	 *
	 * - when:
	 *   - a scheduled post is published
	 *
	 * - assert:
	 *   - query for list of posts is invalidated
	 *   - query for contentNodes is invalidated
	 *   - query for list of categories is invalidated
	 *   - query for single category is invalidated
	 *   - query for single pre-exising post remains cached
	 *   - query for a page remains cached
	 *   - query for list of pages remains cached
	 *   - query for tag remains cached
	 *   - query for list of tags remains cached
	 *   - query for list of users remains cached
	 *   - query for the author of the post remains cached
	 *
	 * @throws Exception
	 */
	public function testPublishingScheduledPostWithCategoryAssigned() {


		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// the single category query should be in the cache
		$this->assertNotEmpty( $this->collection->get( $this->query_results['singleCategory']['cacheKey'] ) );

		// publish the post
		wp_publish_post( $this->scheduled_post_with_category->ID );

		codecept_debug( [ 'empty_after_publish' => wp_get_object_terms( $this->scheduled_post_with_category->ID, 'category' ) ]);

		// get the evicted caches _after_ publish
		$evicted_caches = $this->getEvictedCaches();

		// when publishing a scheduled post with an associated category,
		// the listPost and listContentNode queries should have been cleared
		// but also the listCategory and singleCategory as the termCount
		// needs to be updated on the terms
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'listContentNode', $evicted_caches );
		$this->assertContains( 'listCategory', $evicted_caches );
		$this->assertContains( 'singleCategory', $evicted_caches );


		// we're also asserting that we cleared the "listCategory" cache because
		// a category in the list was updated
		// by being assigned to this post
		$this->assertEmpty( $this->collection->get( $this->query_results['listCategory']['cacheKey'] ) );

		// the single category query should no longer be in the cache because a post was published that
		// was associated with the category
		$this->assertEmpty( $this->collection->get( $this->query_results['singleCategory']['cacheKey'] ) );


		// ensure the other caches remain cached
		$this->assertNotContains( 'singleTag', $evicted_caches );
		$this->assertNotContains( 'listTag', $evicted_caches );

		// Ensure that other caches have not been emptied
		$this->assertNotContains( 'listTag', $evicted_caches );

	}

	// published post is changed to draft
	public function testPublishedPostIsChangedToDraft() {

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'draft'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

	}

	public function testPublishedPostWithCategoryIsChangedToDraft() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'draft',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}


	// published post is changed to private
	public function testPublishPostChangedToPrivate() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'private'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsChangedToPrivate() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'private',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}
	// published post is trashed
	public function testPublishPostIsTrashed() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'trash'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsTrashed() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'trash',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// published post is force deleted
	public function testPublishPostIsForceDeleted() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->published_post->ID, true );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );
		$this->assertContains( 'singlePost', $evicted_caches );
		$this->assertContains( 'listPost', $evicted_caches );
		$this->assertContains( 'singleContentNode', $evicted_caches );
		$this->assertContains( 'singleNodeById', $evicted_caches );
		$this->assertContains( 'singleNodeByUri', $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsForceDeleted() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// force delete the post
		wp_delete_post( $this->published_post->ID, true );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// the post was unpublished, so the singlePost query should be evicted
		$this->assertContains( 'singlePost', $evicted_caches );

		// the post that was unpublished was part of the list, and should be evicted
		$this->assertContains( 'listPost', $evicted_caches );

		// the post was the single content node and should be evicted
		$this->assertContains( 'singleContentNode', $evicted_caches );

		// the post was part of the content node list and should be evicted
		$this->assertContains( 'listContentNode', $evicted_caches );

		// the post was the single node by id and should be evicted
		$this->assertContains( 'singleNodeById', $evicted_caches );

		// the post was the single node by uri and should be evicted
		$this->assertContains( 'singleNodeByUri', $evicted_caches );

		// the post had a category assigned, so the category list should be evicted
		$this->assertContains( 'listCategory', $evicted_caches );

		// the single category should be evicted as its post count has changed
		$this->assertContains( 'singleCategory', $evicted_caches );


		$this->assertNotEmpty( $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'listPage', $non_evicted_caches );

		// no pages were affected, should remain cached
		$this->assertContains( 'singlePage', $non_evicted_caches );

		// no Test Post Type nodes were affected, should remain cached
		$this->assertContains( 'listTestPostType', $non_evicted_caches );
		$this->assertContains( 'singleTestPostType', $non_evicted_caches );

		// no Private Post Type nodes were affected, should remain cached
		$this->assertContains( 'listPrivatePostType', $non_evicted_caches );
		$this->assertContains( 'singlePrivatePostType', $non_evicted_caches );

		// no tag nodes were affected, should remain cached
		$this->assertContains( 'listTag', $non_evicted_caches );
		$this->assertContains( 'singleTag', $non_evicted_caches );

		// no Test Taxonomy term nodes were affected, should remain cached
		$this->assertContains( 'listTestTaxonomyTerm', $non_evicted_caches );
		$this->assertContains( 'singleTestTaxonomyTerm', $non_evicted_caches );

	}

	// delete draft post (doesnt evoke purge action)
	// trashed post is restored



	// page is created as auto draft
	// page is published from draft
	// published page is changed to draft
	// published page is changed to private
	// published page is trashed
	// published page is force deleted
	// delete draft page (doesnt evoke purge action)
	// trashed page is restored



	// publish first post to a user (user->post connection should purge)
	// delete only post of a user (user->post connection should purge)
	// change only post of a user from publish to draft (user->post connection should purge)
	// change post author (user->post connection should purge)


	// update post meta of draft post does not evoke purge action
	// delete post meta of draft post does not evoke purge action
	// update post meta of published post
	// delete post meta of published post


	// post of publicly queryable/show in graphql cpt is created as auto draft
	// post of publicly queryable/show in graphql cpt is published from draft
	// scheduled post of publicly queryable/show in graphql cpt is published
	// published post of publicly queryable/show in graphql cpt is changed to draft
	// published post of publicly queryable/show in graphql cpt is changed to private
	// published post of publicly queryable/show in graphql cpt is trashed
	// published post of publicly queryable/show in graphql cpt is force deleted
	// delete draft post of publicly queryable/show in graphql post type (doesn't evoke purge action)
	// trashed post of publicly queryable/show in graphql post type


	// post of non-gql post type cpt is created as auto draft
	// post of private cpt is published from draft
	// scheduled post of private cpt is published
	// published post of private cpt is changed to draft
	// published post of private cpt is changed to private
	// published post of private cpt is trashed
	// published post of private cpt is force deleted
	// delete draft post of private post type (doesnt evoke purge action)
}
