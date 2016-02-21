<?php

/**
 * Test Co-Authors Plus' REST API
 */
global $wp_version;
if (version_compare($wp_version, '4.4', '>=')) {

    class Test_API extends CoAuthorsPlus_TestCase {

        protected $server;

        public function setUp() {
            parent::setUp();

            $this->logout();
            /** @var WP_REST_Server $wp_rest_server */
            global $wp_rest_server;
            $this->server = $wp_rest_server = new WP_Test_Spy_REST_Server;
            do_action( 'rest_api_init' );
        }

        public function tearDown() {
            parent::tearDown();

            /** @var WP_REST_Server $wp_rest_server */
            global $wp_rest_server;
            $wp_rest_server = null;
        }

        public function testSearchWithoutAuthentication() {
            $response = $this->get_request_response('POST', 'search', array( 'q' => 'foo' ));
            $this->assertEquals( 403, $response->get_status() );
            $this->assertErrorResponse( 'rest_forbidden', $response );
        }

        public function testSearchAuthenticatedWithoutPermission() {
            wp_set_current_user( $this->subscriber );
            $response = $this->get_request_response('POST', 'search', array( 'q' => 'foo' ));
            $this->assertEquals( 403, $response->get_status() );
            $this->assertErrorResponse( 'rest_forbidden', $response );
        }

        public function testSearchAuthenticatedWithPermission() {
            wp_set_current_user( 1 );
            $response = $this->get_request_response('POST', 'search', array( 'q' => 'foo' ) );
            $this->assertEquals( 200, $response->get_status() );
        }

        public function testSearchResults() {
            wp_set_current_user( 1 );
            $response = $this->get_request_response('POST', 'search', array( 'q' => 'tor' ) );
            $this->assertEquals( 200, $response->get_status() );
            $data = $response->get_data();
            $this->assertEquals( 2, count( $data['coauthors'] ) );
        }

        public function testExistingAuthorsValid() {
            wp_set_current_user( 1 );
            $response = $this->get_request_response('POST', 'search', array(  'q' => 'tor', 'existing_authors' => array(  'contributor1' ) ) );
            $this->assertEquals( 200, $response->get_status() );
            $data = $response->get_data();
            $this->assertEquals( 1, count( $data['coauthors'] ) );

            $response = $this->get_request_response('POST', 'search', array(  'q' => 'tor', 'existing_authors' => array(  'contributor1', 'editor2' ) ) );
            $this->assertEquals( 200, $response->get_status() );
            $data = $response->get_data();
            $this->assertEquals( 0, count( $data['coauthors'] ) );
        }

        public function testPostAuthorsAdmin()
        {
            wp_set_current_user( 1 );
            $response = $this->get_request_response('POST', 'post/' . $this->author1_post1,
                array(  'coauthors' => array( 'author1', 'editor2' ) ) );
            $this->assertEquals( 200, $response->get_status() );
            $data = $response->get_data();
            $this->assertEquals( 'Post authors updated.',  $data[0] );

            $coauthors = get_coauthors( $this->author1_post1 );
            $this->assertEquals( array( $this->author1, $this->editor1 ), wp_list_pluck( $coauthors, 'ID' ) );
        }

        public function testPostAuthorsAuthor()
        {
            wp_set_current_user( $this->author1 );
            $response = $this->get_request_response('POST', 'post/' . $this->author1_post1,
                array(  'coauthors' => array( 'author1', 'editor2' ) ) );
            $this->assertEquals( 403, $response->get_status() );
        }

        public function testPostAuthorsAppend()
        {
            wp_set_current_user( 1 );
            $response = $this->get_request_response('POST', 'post/' . $this->author1_post1,
                array(  'coauthors' => array( 'author1') ) );
            $this->assertEquals( 200, $response->get_status() );
            $coauthors = get_coauthors( $this->author1_post1 );
            $this->assertEquals( array( $this->author1), wp_list_pluck( $coauthors, 'ID' ) );

            wp_set_current_user( 1 );
            $response = $this->get_request_response('POST', 'post/' . $this->author1_post1,
                array(  'coauthors' => array( 'editor2'), 'append' => true ) );
            $this->assertEquals( 200, $response->get_status() );
            $coauthors = get_coauthors( $this->author1_post1 );
            $this->assertEquals( array( $this->author1, $this->editor1), wp_list_pluck( $coauthors, 'ID' ) );
        }

        public function testPostAuthorsUnauthorized()
        {
            wp_set_current_user( $this->editor1 );
            $response = $this->get_request_response('POST', 'post/' . $this->author1_post1,
                array(  'coauthors' => array( 'author1', 'editor2' ) ) );
            $this->assertEquals( 200, $response->get_status() );
            $coauthors = get_coauthors( $this->author1_post1 );
            $this->assertEquals( array( $this->author1, $this->editor1 ), wp_list_pluck( $coauthors, 'ID' ) );
        }

        public function testPostAuthorsDelete()
        {
            global $coauthors_plus;

            wp_set_current_user( 1 );
            $coauthors_plus->add_coauthors( $this->author1_post1, array('author1', 'subscriber1'));

            $response = $this->get_request_response('DELETE', 'post/' . $this->author1_post1,
                array(  'coauthors' => array( 'subscriber1' ) ) );

            $this->assertEquals( 200, $response->get_status() );
            $coauthors = get_coauthors( $this->author1_post1 );
            $this->assertEquals( array( $this->author1 ) , wp_list_pluck( $coauthors, 'ID' ) );
        }

        public function testPostAuthorsEmptyDeleteAll()
        {
            global $coauthors_plus;

            wp_set_current_user( 1 );
            $coauthors_plus->add_coauthors( $this->author1_post1, array('author1', 'subscriber1'));

            $response = $this->get_request_response('DELETE', 'post/' . $this->author1_post1,
                array(  'coauthors' => array( 'subscriber1', 'author1' ) ) );

            $this->assertEquals( 200, $response->get_status() );
            $coauthors = get_coauthors( $this->author1_post1 );
            $this->assertEquals( array( 1 ) , wp_list_pluck( $coauthors, 'ID' ) );
        }

        public function testPostGet()
        {
            global $coauthors_plus;

            wp_set_current_user( 1 );
            $coauthors_plus->add_coauthors( $this->author1_post1, array('author1', 'subscriber1'));
            $response = $this->get_request_response('GET', 'post/' . $this->author1_post1 );
            $data = $response->get_data();
            $this->assertEquals( 2 , count($data['coauthors']) );

            $coauthors_plus->add_coauthors( $this->author1_post1, array('author1', 'subscriber1', 'contributor1'));
            $response = $this->get_request_response('GET', 'post/' . $this->author1_post1 );
            $data = $response->get_data();
            $this->assertEquals( 3 , count($data['coauthors']) );
        }

        public function testPostGetNoPost()
        {
            global $coauthors_plus;

            wp_set_current_user( 1 );
            $response = $this->get_request_response('GET', 'post/' . 9999 );
            $this->assertEquals( 404, $response->get_status() );
        }

        /**
         * @param String $code
         * @param WP_REST_Response $response
         * @param null $status
         */
        protected function assertErrorResponse( $code, $response, $status = null ) {
            if ( is_a( $response, 'WP_REST_Response' ) ) {
                $response = $response->as_error();
            }
            $this->assertInstanceOf( 'WP_Error', $response );
            $this->assertEquals( $code, $response->get_error_code() );
            if ( null !== $status ) {
                $data = $response->get_error_data();
                $this->assertArrayHasKey( 'status', $data );
                $this->assertEquals( $status, $data['status'] );
            }
        }

        /**
         * @param string $method
         * @param string $path
         * @param array $params
         *
         * @return mixed
         */
        protected function get_request_response($method, $path, array $params = array())
        {
            $request = new WP_REST_Request( $method, '/coauthors/v1/' . $path );
            $request->set_header('Content-Type', 'application/json');

            foreach ( $params as $key => $value ) {
                $request->set_param($key, $value);
            }
            return $this->server->dispatch( $request );
        }

        /**
         * Clears any persisted authentication
         */
        protected function logout() {
            wp_set_current_user( -1 );
        }
    }


    /**
     * "Stolen" From https://github.com/WP-API/WP-API/blob/develop/tests/class-wp-test-spy-rest-server.php
     */
    class WP_Test_Spy_REST_Server extends WP_REST_Server {
        /**
         * Get the raw $endpoints data from the server
         *
         * @return array
         */
        public function get_raw_endpoint_data() {
            return $this->endpoints;
        }

        /**
         * Allow calling protected methods from tests
         *
         * @param string $method Method to call
         * @param array $args Arguments to pass to the method
         *
         * @return mixed
         */
        public function __call( $method, $args ) {
            return call_user_func_array( array( $this, $method ), $args );
        }

        /**
         * Call dispatch() with the rest_post_dispatch filter
         */
        public function dispatch( $request ) {
            $result = parent::dispatch( $request );
            $result = rest_ensure_response( $result );
            if ( is_wp_error( $result ) ) {
                $result = $this->error_to_response( $result );
            }

            return apply_filters( 'rest_post_dispatch', rest_ensure_response( $result ), $this, $request );
        }
    }

}