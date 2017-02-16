<?php

namespace Carbon_Fields\Container;

use Carbon_Fields\Datastore\Meta_Datastore;
use Carbon_Fields\Datastore\Datastore;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Comment meta container class.
 */
class Comment_Meta_Container extends Container {
	protected $comment_id;

	/**
	 * Create a new container
	 *
	 * @param string $unique_id Unique id of the container
	 * @param string $title title of the container
	 * @param string $type Type of the container
	 **/
	public function __construct( $unique_id, $title, $type ) {
		parent::__construct( $unique_id, $title, $type );

		if ( ! $this->get_datastore() ) {
			$this->set_datastore( Datastore::make( 'comment_meta' ), $this->has_default_datastore() );
		}
	}

	/**
	 * Perform instance initialization
	 **/
	public function init() {
		if ( isset( $_GET['c'] ) && $comment_id = absint( $_GET['c'] ) ) { // Input var okay.
			$this->set_comment_id( $comment_id );
		}

		add_action( 'admin_init', array( $this, '_attach' ) );
		add_action( 'edit_comment', array( $this, '_save' ) );
	}

	/**
	 * Checks whether the current request is valid
	 *
	 * @return bool
	 **/
	public function is_valid_save() {
		return $this->verified_nonce_in_request();
	}

	/**
	 * Perform save operation after successful is_valid_save() check.
	 * The call is propagated to all fields in the container.
	 *
	 * @param int $comment_id ID of the comment against which save() is ran
	 **/
	public function save( $comment_id ) {

		// Unhook action to guarantee single save
		remove_action( 'edit_comment', array( $this, '_save' ) );

		$this->set_comment_id( $comment_id );

		foreach ( $this->fields as $field ) {
			$field->set_value_from_input( stripslashes_deep( $_POST ) );
			$field->save();
		}
	}

	/**
	 * Check container attachment rules against current page request (in admin)
	 *
	 * @return bool
	 **/
	public function is_valid_attach_for_request() {
		global $pagenow;

		return ( $pagenow === 'comment.php' );
	}

	/**
	 * Check container attachment rules against object id
	 *
	 * @param int $object_id
	 * @return bool
	 **/
	public function is_valid_attach_for_object( $object_id = null ) {
		return true;
	}

	/**
	 * Add meta box to the comment
	 **/
	public function attach() {
		add_meta_box(
			$this->id,
			$this->title,
			array( $this, 'render' ),
			'comment',
			'normal',
			'high'
		);
	}

	/**
	 * Output the container markup
	 **/
	public function render() {
		include \Carbon_Fields\DIR . '/templates/Container/comment_meta.php';
	}

	/**
	 * Set the comment ID the container will operate with.
	 *
	 * @param int $comment_id
	 **/
	public function set_comment_id( $comment_id ) {
		$this->comment_id = $comment_id;
		$this->get_datastore()->set_id( $comment_id );
	}
}
