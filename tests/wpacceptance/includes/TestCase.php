<?php
/**
 * Test case class that provides us with some baseline shortcut functionality
 *
 * @package distributor
 */
use WPAcceptance\Log;

/**
 * Class extends \WPAcceptance\PHPUnit\TestCase
 */
class TestCase extends \WPAcceptance\PHPUnit\TestCase {

	/**
	 * Push a post
	 *
	 * @param  \WPAcceptance\PHPUnit\Actor $actor            WP Acceptance actor
	 * @param  int                     $post_id          Post ID to distributor
	 * @param  int                     $to_connection_id Connection ID to distribute from
	 * @param  string                  $from_blog_slug   Blog where original post lives. Empty string is main blog.
	 * @param  string                  $post_status      New post status.
	 * @param  boolean                 $external         Is this an external connection push?
	 * @return array
	 */
	protected function pushPost( \WPAcceptance\PHPUnit\Actor $I, $post_id, $to_connection_id, $from_blog_slug = '', $post_status = 'publish', $external = false ) {
		$info = [
			'original_edit_url' => $from_blog_slug . '/wp-admin/post.php?post=' . $post_id . '&action=edit',
		];

		// Now distribute a published post
		$I->moveTo( $info['original_edit_url'] );

		try {
			$info['original_front_url'] = $I->getElementAttribute( '#wp-admin-bar-view a', 'href' );
		} catch ( \Exception $e ) {
			$info['original_front_url'] = $I->getElementAttribute( '#wp-admin-bar-preview a', 'href' );
		}

		$this->disableFullscreenEditor( $I );

		$I->waitUntilElementVisible( '#wp-admin-bar-distributor a' );

		$this->dismissNUXTip( $I );

		$I->moveMouse( '#wp-admin-bar-distributor a' );

		$I->click( '#wp-admin-bar-distributor a' );
		$I->click( '#wp-admin-bar-distributor a' );

		$I->waitUntilElementVisible( '#distributor-push-wrapper .new-connections-list' );

		// Distribute post

		$I->click( '#distributor-push-wrapper .new-connections-list .add-connection[data-connection-id="' . $to_connection_id . '"]' );

		usleep( 500 );

		if ( 'publish' === $post_status ) {
			$I->click( '#dt-as-draft' ); // Uncheck for publish, draft is checked by default
		}

		$I->waitUntilElementEnabled( '#distributor-push-wrapper .syndicate-button' );

		$I->click( '#distributor-push-wrapper .syndicate-button' );

		$I->waitUntilElementVisible( '#distributor-push-wrapper .dt-success' );

		// Now let's navigate to the new post - only works for network connections.
		if ( ! $external ) {

			$I->click( '#distributor-push-wrapper .new-connections-list .add-connection[data-connection-id="' . $to_connection_id . '"] a' );

			$I->waitUntilNavigation();

			$info['distributed_front_url'] = $I->getCurrentUrl();

			try {
				$link = $I->getElementAttribute( '#wp-admin-bar-edit a', 'href' );
				$info['distributed_edit_url'] = $link;
				preg_match( '/post=(\d+)/', $link, $matches );
				if ( $matches ) {
					$info['distributed_post_id'] = (int) $matches[1];
				}
			} catch ( \Exception $e ) {}
		}

		return $info;
	}

	/**
	 * Pull a post
	 *
	 * @param  \WPAcceptance\PHPUnit\Actor $actor            WP Acceptance actor
	 * @param  int                     $original_post_id Original post id
	 * @param  int                     $to_blog_slug     Blog slug where post is being pulled in
	 * @param  string                  $from_blog_slug   Blog we are pulling from. Empty string is main blog
	 * @param  string                  $use_connection   The full connection name to use on the pull screen.
	 *
	 * @return array
	 */
	protected function pullPost( \WPAcceptance\PHPUnit\Actor $I, $original_post_id, $to_blog_slug, $from_blog_slug = '', $use_connection = false ) {
		if ( ! empty( $to_blog_slug ) ) {
			$to_blog_slug .= '/';
		}

		if ( ! empty( $from_blog_slug ) ) {
			$from_blog_slug .= '/';
		}

		$info = [
			'original_edit_url' => $from_blog_slug . '/wp-admin/post.php?post=' . $original_post_id . '&action=edit',
		];

		$I->moveTo( $to_blog_slug . 'wp-admin/admin.php?page=pull' );

		if ( $use_connection ) {
			$I->checkOptions( '#pull_connections', $use_connection );
			$I->waitUntilElementVisible( '.wp-list-table #cb-select-' . $original_post_id );
		}

		$I->checkOptions( '.wp-list-table #cb-select-' . $original_post_id );

		$I->click( '#doaction' );

		$I->waitUntilNavigation();

		$I->click( '.pulled > a' );
		$I->waitUntilNavigation();

		$I->moveMouse( '.wp-list-table tbody tr:nth-child(1) .page-title' );
		$I->click( '.wp-list-table tbody tr:nth-child(1) .page-title .view a' );

		$I->waitUntilNavigation();

		$info['distributed_view_url'] = $I->getCurrentUrl();

		$I->click( '#wp-admin-bar-edit a' );

		$I->waitUntilNavigation();

		$info['distributed_edit_url'] = $I->getCurrentUrl();

		return $info;
	}

	/**
	 * Check if the editor is the block editor.
	 *
	 * Must be called from the edit page.
	 *
	 * @param \WPAcceptance\PHPUnit\Actor $actor The actor.
	 */
	protected function editorHasBlocks ( $actor ) {
		$body = $actor->getElement( 'body' );
		$msg = $actor->elementToString( $body );
		return ( strpos( $msg, 'block-editor-page' ) );
	}

	/**
	 * Dismiss the Gutenberg NUX tooltip.
	 *
	 * @param \WPAcceptance\PHPUnit\Actor $actor The actor.
	 */
	protected function dismissNUXTip( $actor ) {
		try {
			if ( $actor->getElement( '.nux-dot-tip__disable' ) ) {
				$actor->click( '.nux-dot-tip__disable' );
			}
		} catch ( \Exception $e ) {}
	}

	/**
	 * Disable new default fullscreen mode in WP 5.4.
	 *
	 * @param \WPAcceptance\PHPUnit\Actor $actor The actor.
	 */
	protected function disableFullscreenEditor( $actor ) {
		$script = "if ( !! wp.data && wp.data.select( 'core/edit-post' ).isFeatureActive( 'fullscreenMode' ) ) { wp.data.dispatch( 'core/edit-post' ).toggleFeature( 'fullscreenMode' ); }";

		$actor->executeJavascript( $script );
	}

	/**
	 * Dismiss the Welcome Modal.
	 *
	 * @param \WPAcceptance\PHPUnit\Actor $actor The actor.
	 */
	protected function dismissWelcomeModal( $actor ) {
		$script = 'if ( !! wp.data && wp.data.select( "core/edit-post" ).isFeatureActive( "welcomeGuide" ) ) { wp.data.dispatch( "core/edit-post" ).toggleFeature( "welcomeGuide" ); }';

		$actor->executeJavascript( $script );
	}
}
