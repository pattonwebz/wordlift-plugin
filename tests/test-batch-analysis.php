<?php
/**
 * Tests: Batch Analysis Service.
 *
 * @since   3.14.0
 * @package Wordlift
 */

/**
 * Define the {@link Wordlift_Batch_Analysis_Service_Test} class.
 *
 * @since   3.14.0
 * @package Wordlift
 */
class Wordlift_Batch_Analysis_Service_Test extends Wordlift_Unit_Test_Case {

	private $http_filter_stage;
	private $analyze_sim_response;
	private $query_sim_response;
	private $analyzed_request;

	/**
	 * The {@link Wordlift_Batch_Analysis_Service} to test.
	 *
	 * @since  3.14.2
	 * @access private
	 * @var \Wordlift_Batch_Analysis_Service $batch_service The {@link Wordlift_Batch_Analysis_Service} to test.
	 */
	private $batch_service;

	/**
	 * @inheritdoc
	 */
	function setUp() {
		parent::setUp();

		$this->batch_service = new Wordlift_Batch_Analysis_Service( new Wordlift(), Wordlift_Configuration_Service::get_instance() );

	}

	/**
	 * Test post id insertion.
	 *
	 * @since 3.14.0
	 */
	public function test_adding_and_query() {

		$batch_service = new Wordlift_Batch_Analysis_Service( new Wordlift(), Wordlift_Configuration_Service::get_instance() );

		// Test single post addition.
		$batch_service->enqueue_for_analysis( 1, 'default' );

		// test that only one post is returned.
		$queue = $batch_service->waiting_for_analysis();
		$this->assertEquals( 1, count( $queue ) );
		$this->assertEquals( 1, $queue[1]['id'] );
		$this->assertEquals( 'default', $queue[1]['link'] );

		// Test multiple post addition.
		$batch_service->enqueue_for_analysis( array( 3, 4 ), 'no' );

		// test that only one post is returned.
		$queue = $batch_service->waiting_for_analysis();
		$this->assertEquals( 3, count( $queue ) );
		$this->assertEquals( 3, $queue[3]['id'] );
		$this->assertEquals( 'no', $queue[3]['link'] );

		$this->assertEquals( 4, $queue[4]['id'] );
		$this->assertEquals( 'no', $queue[4]['link'] );
	}

	/**
	 * Simulate response
	 *
	 */
	public function simulate_response( $preempt, $request, $url ) {

		if ( 'analyze' == $this->http_filter_stage ) {
			$this->http_filter_stage = 'query';
			$this->analyzed_request  = $request;

			return $this->analyze_sim_response;
		} else {
			return $this->query_sim_response;
		}
	}

	/**
	 * Test sending analysis request and handling response.
	 *
	 * @since 3.14.0
	 */
	public function test_send_analyze_request() {
		$batch_service = new Wordlift_Batch_Analysis_Service( new Wordlift(), Wordlift_Configuration_Service::get_instance() );

		add_filter( 'pre_http_request', array(
			$this,
			'simulate_response',
		), 10, 3 );

		/*
		 * Test behavior with non existing posts.
		 * No message should be sent, queues should be cleared.
		 */
		$this->http_filter_stage    = 'analyze';
		$this->analyze_sim_response = array();
		$this->query_sim_response   = array();
		$this->analyzed_request     = null;
		wp_clear_scheduled_hook( 'wl_batch_analyze' );

		$batch_service->enqueue_for_analysis( 1, 'default' );

		// Test just queue entry/exit.
		$batch_service->batch_analyze();

		$queue = $batch_service->waiting_for_analysis();
		$this->assertEmpty( $queue );

		$queue = $batch_service->waiting_for_response();
		$this->assertEmpty( $queue );

		// No event should be scheduled
		$this->assertFalse( wp_next_scheduled( 'wl_batch_analyze' ) );

		// No message was sent
		$this->assertEquals( 'analyze', $this->http_filter_stage );

		/*
		 * Handling the waiting queue should send an analyze request to the server
		 * and move the item from the waiting to the processing queue.
		 */

		$post_id = $this->factory->post->create( array(
			'post_type'    => 'post',
			'post_content' => 'test content',
			'post_title'   => 'test post',
			'post_status'  => 'publish',
		) );

		$this->http_filter_stage    = 'analyze';
		$this->analyze_sim_response = array();
		$this->query_sim_response   = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array( 'content' => 'analyzed' ) ),
		);
		$this->analyzed_request     = null;
		wp_clear_scheduled_hook( 'wl_batch_analyze' );

		$batch_service->enqueue_for_analysis( $post_id, 'default' );

		// Test just queue entry/exit.
		$batch_service->batch_analyze();

		$queue = $batch_service->waiting_for_analysis();
		$this->assertEmpty( $queue );

		$queue = $batch_service->waiting_for_response();
		$this->assertEmpty( $queue );

		// No event should be scheduled
		$this->assertFalse( wp_next_scheduled( 'wl_batch_analyze' ) );

		$analyze_request = json_decode( $this->analyzed_request['body'] );
		$this->assertEquals( 'test content', $analyze_request->content );
		$this->assertEquals( $post_id, $analyze_request->id );
		$this->assertEquals( 'default', $analyze_request->links );
		$this->assertEquals( 'en', $analyze_request->contentLanguage );
		$this->assertEquals( 'local', $analyze_request->scope );
		// $this->assertEquals( '', $analyze_request->version ); TBD

		$post = get_post( $post_id );
		$this->assertEquals( 'analyzed', $post->post_content );

		/*
		 * Handling the response not ready case for the query.
		 */

		$post_id = $this->factory->post->create( array(
			'post_type'    => 'post',
			'post_content' => 'test content',
			'post_title'   => 'test post',
			'post_status'  => 'publish',
		) );

		$this->http_filter_stage    = 'analyze';
		$this->analyze_sim_response = array();
		$this->query_sim_response   = array(
			'response' => array( 'code' => 500 ),
			'body'     => json_encode( array( 'content' => 'analyzed' ) ),
		);
		$this->analyzed_request     = null;
		wp_clear_scheduled_hook( 'wl_batch_analyze' );

		$batch_service->enqueue_for_analysis( $post_id, 'default' );

		// Test just queue entry/exit.
		$batch_service->batch_analyze();

		// The query failing, sends the request into the waiting queue.
		$queue = $batch_service->waiting_for_analysis();
		$this->assertEquals( 1, count( $queue ) );
		$this->assertEquals( $post_id, $queue[ $post_id ]['id'] );
		$this->assertEquals( 'default', $queue[ $post_id ]['link'] );

		$queue = $batch_service->waiting_for_response();
		$this->assertEmpty( $queue );

		// An event should be scheduled
		$this->assertNotFalse( wp_next_scheduled( 'wl_batch_analyze' ) );

		// No response, no change in content.
		$post = get_post( $post_id );
		$this->assertEquals( 'test content', $post->post_content );

	}

	function test_submit_auto_selected_posts() {

		$result_1 = $this->batch_service->submit_auto_selected_posts();

		// A post that must fall into the auto selection.
		$post_1 = $this->factory->post->create( array(
			'post_status' => 'publish',
		) );

		// A post that should not be analyzed because the status is `draft`.
		$post_2 = $this->factory->post->create( array(
			'post_status' => 'draft',
		) );

		// A post that should not be analyzed because it has an annotation.
		$post_3 = $this->factory->post->create( array(
			'post_status'  => 'publish',
			'post_content' => '<span id="urn:enhancement-xyz" class="class" itemid="itemid">Lorem Ipsum</span>',
		) );

		// A post that should not be analyzed because it has been analyzed already.
		$post_4 = $this->factory->post->create( array(
			'post_status' => 'publish',
		) );
		update_post_meta( $post_4, Wordlift_Batch_Analysis_Service::STATE_META_KEY, Wordlift_Batch_Analysis_Service::STATE_SUCCESS );

		// A page that must fall into the auto selection.
		$post_5 = $this->factory->post->create( array(
			'post_status' => 'publish',
			'post_type'   => 'page',
		) );

		// A post that should not be analyzed because it's an entity.
		$post_6 = $this->factory->post->create( array(
			'post_status' => 'publish',
			'post_type'   => 'entity',
		) );

		$result_2 = $this->batch_service->submit_auto_selected_posts();

		// We expect 2 submitted posts/pages.
		$this->assertEquals( 2, $result_2, 'Expect to submit only 2 posts/page.' );

		// Check that the state has been set.
		$this->assertEquals( Wordlift_Batch_Analysis_Service::STATE_SUBMIT, $this->batch_service->get_state( $post_1 ) );
		$this->assertEquals( Wordlift_Batch_Analysis_Service::STATE_SUBMIT, $this->batch_service->get_state( $post_5 ) );

		// Check the other states.
		$this->assertEmpty( $this->batch_service->get_state( $post_2 ) );
		$this->assertEmpty( $this->batch_service->get_state( $post_3 ) );
		$this->assertEquals( Wordlift_Batch_Analysis_Service::STATE_SUCCESS, $this->batch_service->get_state( $post_4 ) );
		$this->assertEmpty( $this->batch_service->get_state( $post_6 ) );

	}

}
