<?php
if (!defined('EVENT_ESPRESSO_VERSION') )
	exit('NO direct script access allowed');

/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for Wordpress
 *
 * @package		Event Espresso
 * @author		Seth Shoultes
 * @copyright	(c)2009-2012 Event Espresso All Rights Reserved.
 * @license		http://eventespresso.com/support/terms-conditions/  ** see Plugin Licensing **
 * @link		http://www.eventespresso.com
 * @version		4.0
 *
 * ------------------------------------------------------------------------
 *
 * Extend_Events_Admin_Page
 *
 * This is the Events Caffeinated admin page.
 *
 *
 * @package		Extend_Events_Admin_Page
 * @subpackage	includes/core/admin/Extend_Events_Admin_Page.core.php
 * @author		Darren Ethier
 *
 * ------------------------------------------------------------------------
 */
class Extend_Events_Admin_Page extends Events_Admin_Page {


	public function __construct( $routing = TRUE ) {
		parent::__construct( $routing );
		define( 'EVENTS_CAF_TEMPLATE_PATH', EE_CORE_CAF_ADMIN_EXTEND . 'events/templates/');
		define( 'EVENTS_CAF_ASSETS', EE_CORE_CAF_ADMIN_EXTEND . 'events/assets/');
		define( 'EVENTS_CAF_ASSETS_URL', EE_CORE_CAF_ADMIN_EXTEND_URL . 'events/assets/');
	}


	protected function _extend_page_config() {

		$this->_admin_base_path = EE_CORE_CAF_ADMIN_EXTEND . 'events';
		$default_espresso_boxes = $this->_default_espresso_metaboxes;

		//is there a evt_id in the request?
		$evt_id = ! empty( $this->_req_data['EVT_ID'] ) && ! is_array( $this->_req_data['EVT_ID'] ) ? $this->_req_data['EVT_ID'] : 0;
		$evt_id = ! empty( $this->_req_data['post'] ) ? $this->_req_data['post'] : $evt_id;

		//tkt_id?
		$tkt_id = !empty( $this->_req_data['TKT_ID'] ) && ! is_array( $this->_req_data['TKT_ID'] ) ? $this->_req_data['TKT_ID'] : 0;

		$new_page_routes = array(
			'duplicate_event' => array(
				'func' => '_duplicate_event',
				'capability' => 'ee_edit_event',
				'obj_id' => $evt_id,
				'noheader' => TRUE
				),
			'ticket_list_table' => array(
				'func' => '_tickets_overview_list_table',
				'capability' => 'ee_read_default_tickets'
				),
			'trash_ticket' => array(
				'func' => '_trash_or_restore_ticket',
				'capability' => 'ee_delete_default_ticket',
				'obj_id' => $tkt_id,
				'noheader' => TRUE,
				'args' => array( 'trash' => TRUE )
				),
			'trash_tickets' => array(
				'func' => '_trash_or_restore_ticket',
				'capability' => 'ee_delete_default_tickets',
				'noheader' => TRUE,
				'args' => array( 'trash' => TRUE )
				),
			'restore_ticket' => array(
				'func' => '_trash_or_restore_ticket',
				'capability' => 'ee_delete_default_ticket',
				'obj_id' => $tkt_id,
				'noheader' => TRUE
				),
			'restore_tickets' => array(
				'func' => '_trash_or_restore_ticket',
				'capability' => 'ee_delete_default_tickets',
				'noheader' => TRUE
				),
			'delete_ticket' => array(
				'func' => '_delete_ticket',
				'capability' => 'ee_delete_default_ticket',
				'obj_id' => $tkt_id,
				'noheader' => TRUE
				),
			'delete_tickets' => array(
				'func' => '_delete_ticket',
				'capability' => 'ee_delete_default_tickets',
				'noheader' => TRUE
				),
			'import_page'=> array(
				'func' => '_import_page',
				'capability' => 'import'
				),
			'import' => array(
				'func'=>'_import_events',
				'capability' => 'import',
				'noheader'=>TRUE,
				),
			'import_events' => array(
				'func'=>'_import_events',
				'capability' => 'import',
				'noheader'=>TRUE,
				),
			'export_events' => array(
				'func' => '_events_export',
				'capability' => 'export',
				'noheader' => true
			),
			'export_categories' => array(
				'func' => '_categories_export',
				'capability' => 'export',
				'noheader' => TRUE
				),
			'sample_export_file'=>array(
				'func'=>'_sample_export_file',
				'capability' => 'export',
				'noheader'=>TRUE
				),
			'update_template_settings' => array(
				'func' => '_update_template_settings',
				'capability' => 'manage_options',
				'noheader' => true
				)
			);

		$this->_page_routes = array_merge( $this->_page_routes, $new_page_routes );


		//partial route/config override
		$this->_page_config['import_events']['metaboxes'] = $this->_default_espresso_metaboxes;
		$this->_page_config['create_new']['metaboxes'][] = '_premium_event_editor_meta_boxes';
		$this->_page_config['create_new']['qtips'][] = 'EE_Event_Editor_Tips';
		$this->_page_config['edit']['qtips'][] = 'EE_Event_Editor_Tips';
		$this->_page_config['edit']['metaboxes'][] = '_premium_event_editor_meta_boxes';
		$this->_page_config['default']['list_table'] = 'Extend_Events_Admin_List_Table';

		//add tickets tab but only if there are more than one default ticket!
		$tkt_count = EEM_Ticket::instance()->count_deleted_and_undeleted(array( array('TKT_is_default' => 1 ) ), 'TKT_ID', TRUE );
		if ( $tkt_count > 1 ) {
			$new_page_config = array(
				'ticket_list_table' => array(
					'nav' => array(
						'label' => __('Default Tickets', 'event_espresso'),
						'order' => 60
						),
					'list_table' => 'Tickets_List_Table',
					'require_nonce' => FALSE
					)
				);
		}

		//template settings
		$new_page_config['template_settings'] = array(
			'nav' => array(
				'label' => __('Templates'),
				'order' => 30
			),
			'metaboxes' => array_merge( $this->_default_espresso_metaboxes, array( '_publish_post_box' ) ),
			'help_tabs' => array(
				'general_settings_templates_help_tab' => array(
					'title' => __('Templates', 'event_espresso'),
					'filename' => 'general_settings_templates'
				)
			),
			'help_tour' => array( 'Templates_Help_Tour' ),
			'require_nonce' => FALSE
		);

//		$new_page_config['import_page'] = array(
//				'nav' => array(
//					'label' => __('Import', 'event_espresso'),
//					'order' => 30
//				),
//				'help_tabs' => array(
//					'import_help_tab' => array(
//						'title' => __('Event Espresso Import', 'event_espresso'),
//						'filename' => 'import_page'
//						)
//					),
//				'help_tour' => array('Event_Import_Help_Tour'),
//				'metaboxes' => $default_espresso_boxes,
//				'require_nonce' => FALSE
//		);
		$this->_page_config = array_merge( $this->_page_config, $new_page_config );

		//add filters and actions
		//modifying _views
		add_filter('FHEE_event_datetime_metabox_add_additional_date_time_template', array( $this, 'add_additional_datetime_button' ), 10, 2 );
		add_filter('FHEE_event_datetime_metabox_clone_button_template', array( $this, 'add_datetime_clone_button' ), 10, 2 );
		add_filter('FHEE_event_datetime_metabox_timezones_template', array( $this, 'datetime_timezones_template'), 10, 2 );


		//filters for event list table
		add_filter('FHEE__Extend_Events_Admin_List_Table__filters', array( $this, 'list_table_filters'), 10, 2);
		add_filter('FHEE__Events_Admin_List_Table__column_actions__action_links', array( $this, 'extra_list_table_actions'), 10, 2 );

		//legend item
		add_filter('FHEE__Events_Admin_Page___event_legend_items__items', array( $this, 'additional_legend_items') );

		add_action('admin_init', array( $this, 'admin_init') );

		//heartbeat stuff
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_response' ), 10, 2 );

	}



	/**
	 * admin_init
	 */
	public function admin_init() {
		EE_Registry::$i18n_js_strings = array_merge(
			EE_Registry::$i18n_js_strings,
			array(
				'image_confirm'          => __( 'Do you really want to delete this image? Please remember to update your event to complete the removal.', 'event_espresso' ),
				'event_starts_on'        => __( 'Event Starts on', 'event_espresso' ),
				'event_ends_on'          => __( 'Event Ends on', 'event_espresso' ),
				'event_datetime_actions' => __( 'Actions', 'event_espresso' ),
				'event_clone_dt_msg'     => __( 'Clone this Event Date and Time', 'event_espresso' ),
				'remove_event_dt_msg'    => __( 'Remove this Event Time', 'event_espresso' )
			)
		);
	}



	/**
	 * This will be used to listen for any heartbeat data packages coming via the WordPress heartbeat API and handle accordingly.
	 *
	 * @param array  $response The existing heartbeat response array.
	 * @param array  $data        The incoming data package.
	 *
	 * @return array  possibly appended response.
	 */
	public function heartbeat_response( $response, $data ) {
		/**
		 * check whether count of tickets is approaching the potential
		 * limits for the server.
		 */
		if ( ! empty( $data['input_count'] ) ) {
			$response['max_input_vars_check'] = EE_Registry::instance()->CFG->environment->max_input_vars_limit_check($data['input_count']);
		}

		return $response;
	}



	protected function _add_screen_options_ticket_list_table() {
		$this->_per_page_screen_option();
	}



	public function extra_permalink_field_buttons( $return, $id, $new_title, $new_slug ) {
		$return = parent::extra_permalink_field_buttons( $return, $id, $new_title, $new_slug );
		//make sure this is only when editing
		if ( !empty( $id ) ) {
			$href = EE_Admin_Page::add_query_args_and_nonce( array('action' => 'duplicate_event', 'EVT_ID' => $id), $this->_admin_base_url );
			$title = esc_attr__('Duplicate Event', 'event_espresso');
			$return .= '<a href="' . $href . '" title="' . $title . '" id="ee-duplicate-event-button" class="button button-small"  value="duplicate_event">' . $title  . '</button>';
		}
		return $return;
	}




	public function _set_list_table_views_ticket_list_table() {
		$this->_views = array(
			'all' => array(
				'slug' => 'all',
				'label' => __('All', 'event_espresso'),
				'count' => 0,
				'bulk_action' => array(
					'trash_tickets' => __('Move to Trash', 'event_espresso')
					)
				),
			'trashed' => array(
				'slug' => 'trashed',
				'label' => __('Trash', 'event_espresso'),
				'count' => 0,
				'bulk_action' => array(
					'restore_tickets' => __('Restore from Trash' , 'event_espresso'),
					'delete_tickets' => __('Delete Permanently', 'event_espresso')
					)
				)
			);
	}



	public function load_scripts_styles_edit() {
		wp_register_script( 'ee-event-editor-heartbeat', EVENTS_CAF_ASSETS_URL . 'event-editor-heartbeat.js', array( 'ee_admin_js', 'heartbeat' ), EVENT_ESPRESSO_VERSION, TRUE );

		/**
		 * load accounting js.
		 */
		add_filter('FHEE_load_accounting_js', '__return_true');

		//styles
		wp_enqueue_style('espresso-ui-theme');
		wp_enqueue_script('event_editor_js');
		wp_enqueue_script('ee-event-editor-heartbeat');

	}






	public function add_additional_datetime_button( $template, $template_args ) {
		return EEH_Template::display_template( EVENTS_CAF_TEMPLATE_PATH . 'event_datetime_add_additional_time.template.php', $template_args, TRUE);
	}



	public function add_datetime_clone_button( $template, $template_args ) {
		return EEH_Template::display_template( EVENTS_CAF_TEMPLATE_PATH . 'event_datetime_metabox_clone_button.template.php', $template_args, TRUE );
	}



	public function datetime_timezones_template( $template, $template_args ) {
		return EEH_Template::display_template( EVENTS_CAF_TEMPLATE_PATH . 'event_datetime_timezones.template.php', $template_args, TRUE );
	}




	protected function _set_list_table_views_default() {
		parent::_set_list_table_views_default();
		$export_label = __('Export Events', 'event_espresso');
		if ( EE_Registry::instance()->CAP->current_user_can( 'export', 'espresso_events_export' ) ) {
//			$this->_views['all']['bulk_action']['export_events'] = $export_label;
//			$this->_views['draft']['bulk_action']['export_events'] = $export_label;

			if ( EE_Registry::instance()->CAP->current_user_can( 'ee_delete_events', 'espresso_events_trash_events' ) ) {
//				$this->_views['trash']['bulk_action']['export_events'] = $export_label;
			}
		}

		$new_views = array(
			'today' => array(
				'slug' => 'today',
				'label' => __('Today', 'event_espresso'),
				'count' => $this->total_events_today(),
				'bulk_action' => array(
//					'export_events' => __('Export Events', 'event_espresso'),
					'trash_events' => __('Move to Trash', 'event_espresso')
				)
			),
			'month' => array(
				'slug' => 'month',
				'label' => __('This Month', 'event_espresso'),
				'count' => $this->total_events_this_month(),
				'bulk_action' => array(
//					'export_events' => __('Export Events', 'event_espresso'),
					'trash_events' => __('Move to Trash', 'event_espresso')
				)
			)
		);

		$this->_views = array_merge( $this->_views, $new_views);
	}




	protected function _set_list_table_views_category_list() {
		parent::_set_list_table_views_category_list();
//		$this->_views['all']['bulk_action']['export_categories'] = __('Export Categories', 'event_espresso');
	}




	public function extra_list_table_actions( $actionlinks, $event ) {
		if ( EE_Registry::instance()->CAP->current_user_can( 'ee_read_registrations', 'espresso_registrations_reports', $event->ID() ) ) {
			$reports_query_args = array(
					'action' => 'reports',
					'EVT_ID' => $event->ID()
				);
			$reports_link = EE_Admin_Page::add_query_args_and_nonce( $reports_query_args, REG_ADMIN_URL );
			$actionlinks[] = '<a href="' . $reports_link . '" title="' .  esc_attr__('View Report', 'event_espresso') . '"><div class="dashicons dashicons-chart-bar"></div></a>' . "\n\t";
		}
		if ( EE_Registry::instance()->CAP->current_user_can( 'ee_read_global_messages', 'view_filtered_messages' ) ) {
			EE_Registry::instance()->load_helper( 'MSG_Template' );
			$actionlinks[] = EEH_MSG_Template::get_message_action_link(
				'see_notifications_for',
				null,
				array(
					'EVT_ID' => $event->ID()
				)
			);
		}
		return $actionlinks;
	}



	public function additional_legend_items($items) {
		if ( EE_Registry::instance()->CAP->current_user_can( 'ee_read_registrations', 'espresso_registrations_reports' ) ) {
			$items['reports'] = array(
					'class' => 'dashicons dashicons-chart-bar',
					'desc' => __('Event Reports', 'event_espresso')
				);
		}
		if ( EE_Registry::instance()->CAP->current_user_can( 'ee_read_global_messages', 'view_filtered_messages' ) ) {
			$related_for_icon = EEH_MSG_Template::get_message_action_icon( 'see_notifications_for' );
			if ( isset( $related_for_icon['css_class']) && isset( $related_for_icon['label'] ) ) {
				$items['view_related_messages'] = array(
					'class' => $related_for_icon['css_class'],
					'desc' => $related_for_icon['label'],
				);
			}
		}
		return $items;
	}



	/**
	 * This is the callback method for the duplicate event route
	 *
	 * Method looks for 'EVT_ID' in the request and retrieves that event and its details and duplicates them
	 * into a new event.  We add a hook so that any plugins that add extra event details can hook into this
	 * action.  Note that the dupe will have **DUPLICATE** as its title and slug.
	 * After duplication the redirect is to the new event edit page.
	 * @return void
	 * @access protected
	 * @throws EE_Error If EE_Event is not available with given ID
	 */
	protected function _duplicate_event() {
		//first make sure the ID for the event is in the request.  If it isnt' then we need to bail and redirect back to overview list table (cause how did we get here?)
		if ( !isset( $this->_req_data['EVT_ID'] ) ) {
			EE_Error::add_error( __('In order to duplicate an event an Event ID is required.  None was given.', 'event_espresso'), __FILE__, __FUNCTION__, __LINE__ );
			$this->_redirect_after_action( FALSE, '', '', array(), TRUE );
			return;
		}

		//k we've got EVT_ID so let's use that to get the event we'll duplicate
		$orig_event = EEM_Event::instance()->get_one_by_ID( $this->_req_data['EVT_ID'] );

		if ( ! $orig_event instanceof EE_Event )
			throw new EE_Error( sprintf( __('An EE_Event object could not be retrieved for the given ID (%s)', 'event_espresso '), $this->_req_data['EVT_ID'] ) );

		//k now let's clone the $orig_event before getting relations
		$new_event = clone $orig_event;

		//original datetimes
		$orig_datetimes = $orig_event->get_many_related('Datetime');

		//other original relations
		$orig_ven = $orig_event->get_many_related('Venue');


		//reset the ID and modify other details to make it clear this is a dupe
		$new_event->set( 'EVT_ID', 0 );
		$new_name = $new_event->name() . ' ' . __('**DUPLICATE**', 'event_espresso');
		$new_event->set( 'EVT_name',  $new_name );
		$new_event->set( 'EVT_slug',  wp_unique_post_slug( sanitize_title( $orig_event->name() ), 0, 'publish', 'espresso_events', 0 ) );
		$new_event->set( 'status', 'draft' );

		//duplicate discussion settings
		$new_event->set( 'comment_status', $orig_event->get('comment_status') );
		$new_event->set( 'ping_status', $orig_event->get( 'ping_status' ) );

		//save the new event
		$new_event->save();

		//venues
		foreach( $orig_ven as $ven ) {
			$new_event->_add_relation_to( $ven, 'Venue' );
		}
		$new_event->save();


		//now we need to get the question group relations and handle that
		//first primary question groups
		$orig_primary_qgs = $orig_event->get_many_related('Question_Group', array( array('Event_Question_Group.EQG_primary' => 1 ) ) );
		if ( !empty( $orig_primary_qgs ) ) {
			foreach ( $orig_primary_qgs as $id => $obj ) {
				if ( $obj instanceof EE_Question_Group ) {
					$new_event->_add_relation_to( $obj, 'Question_Group', array( 'EQG_primary' => 1 ) );
				}
			}
		}

		//next additional attendee question groups
		$orig_additional_qgs = $orig_event->get_many_related('Question_Group', array( array('Event_Question_Group.EQG_primary' => 0 ) ) );
		if ( !empty( $orig_additional_qgs ) ) {
			foreach ( $orig_additional_qgs as $id => $obj ) {
				if ( $obj instanceof EE_Question_Group ) {
					$new_event->_add_relation_to( $obj, 'Question_Group', array( 'EQG_primary' => 0 ) );
				}
			}
		}

		//now save
		$new_event->save();


		//k now that we have the new event saved we can loop through the datetimes and start adding relations.
		$cloned_tickets = array();
		foreach ( $orig_datetimes as $orig_dtt ) {
			$new_dtt = clone $orig_dtt;
			$orig_tkts = $orig_dtt->tickets();

			//save new dtt then add to event
			$new_dtt->set('DTT_ID', 0);
			$new_dtt->set('DTT_sold', 0);
			$new_dtt->save();
			$new_event->_add_relation_to( $new_dtt, 'Datetime');
			$new_event->save();

			//now let's get the ticket relations setup.
			foreach ( (array) $orig_tkts as $orig_tkt ) {
				//it's possible a datetime will have no tickets so let's verify we HAVE a ticket first.
				if ( ! $orig_tkt instanceof EE_Ticket )
					continue;

				//is this ticket archived?  If it is then let's skip
				if ( $orig_tkt->get( 'TKT_deleted' ) ) {
					continue;
				}

				//does this original ticket already exist in the clone_tickets cache?  If so we'll just use the new ticket from it.
				if ( isset( $cloned_tickets[$orig_tkt->ID()] ) ) {
					$new_tkt = $cloned_tickets[$orig_tkt->ID()];
				} else {
					$new_tkt = clone $orig_tkt;
					//get relations on the $orig_tkt that we need to setup.
					$orig_prices = $orig_tkt->prices();
					$new_tkt->set('TKT_ID', 0);
					$new_tkt->set('TKT_sold', 0);

					$new_tkt->save(); //make sure new ticket has ID.

					//price relations on new ticket need to be setup.
					foreach ( $orig_prices as $orig_price ) {
						$new_price = clone $orig_price;
						$new_price->set('PRC_ID', 0);
						$new_price->save();
						$new_tkt->_add_relation_to($new_price, 'Price');
						$new_tkt->save();
					}
				}

				//k now we can add the new ticket as a relation to the new datetime and make sure its added to our cached $cloned_tickets array for use with later datetimes that have the same ticket.
				$new_dtt->_add_relation_to($new_tkt, 'Ticket');
				$new_dtt->save();
				$cloned_tickets[$orig_tkt->ID()] = $new_tkt;
			}
		}

		//clone taxonomy information
		$taxonomies_to_clone_with = apply_filters( 'FHEE__Extend_Events_Admin_Page___duplicate_event__taxonomies_to_clone', array( 'espresso_event_categories', 'espresso_event_type', 'post_tag' ) );

		//get terms for original event (notice)
		$orig_terms = wp_get_object_terms( $orig_event->ID(), $taxonomies_to_clone_with );

		//loop through terms and add them to new event.
		foreach ( $orig_terms as $term ) {
			wp_set_object_terms( $new_event->ID(), $term->term_id, $term->taxonomy, true );
		}


		do_action( 'AHEE__Extend_Events_Admin_Page___duplicate_event__after', $new_event, $orig_event );

		//now let's redirect to the edit page for this duplicated event if we have a new event id.
		if ( $new_event->ID() ) {
			$redirect_args = array(
				'post' => $new_event->ID(),
				'action' => 'edit'
			);
			EE_Error::add_success( __('Event successfully duplicated.  Please review the details below and make any necessary edits', 'event_espresso') );
		} else {
			$redirect_args = array(
				'action' => 'default'
				);
			EE_Error::add_error( __('Not able to duplicate event.  Something went wrong.', 'event_espresso'), __FILE__, __FUNCTION__, __LINE__ );
		}

		$this->_redirect_after_action(FALSE, '', '', $redirect_args, TRUE );
	}



	protected function _import_page(){

		$title = __('Import', 'event_espresso');
		$intro = __('If you have a previously exported Event Espresso 4 information in a Comma Separated Value (CSV) file format, you can upload the file here: ', 'event_espresso');
		$form_url = EVENTS_ADMIN_URL;
		$action = 'import_events';
		$type = 'csv';
		$this->_template_args['form'] = EE_Import::instance()->upload_form($title, $intro, $form_url, $action, $type);
		$this->_template_args['sample_file_link'] = EE_Admin_Page::add_query_args_and_nonce(array('action'=>'sample_export_file'),$this->_admin_base_url);
		$content = EEH_Template::display_template(EVENTS_CAF_TEMPLATE_PATH . 'import_page.template.php',$this->_template_args,true);


		$this->_template_args['admin_page_content'] = $content;
		$this->display_admin_page_with_sidebar();
	}
	/**
	 * _import_events
	 * This handles displaying the screen and running imports for importing events.
	 *
	 * @return string html
	 */
	protected function _import_events() {
		require_once(EE_CLASSES . 'EE_Import.class.php');
		$success = EE_Import::instance()->import();
		$this->_redirect_after_action($success, 'Import File', 'ran', array('action' => 'import_page'),true);

	}



	/**
	 * _events_export
	 * Will export all (or just the given event) to a Excel compatible file.
	 *
	 * @access protected
	 * @return file
	 */
	protected function _events_export() {
		if(isset($this->_req_data['EVT_ID'])){
			$event_ids = $this->_req_data['EVT_ID'];
		}elseif(isset($this->_req_data['EVT_IDs'])){
			$event_ids = $this->_req_data['EVT_IDs'];
		}else{
			$event_ids = NULL;
		}
		//todo: I don't like doing this but it'll do until we modify EE_Export Class.
		$new_request_args = array(
			'export' => 'report',
			'action' => 'all_event_data',
			'EVT_ID' => $event_ids ,
		);
		$this->_req_data = array_merge($this->_req_data, $new_request_args);

		if ( is_readable(EE_CLASSES . 'EE_Export.class.php')) {
			require_once(EE_CLASSES . 'EE_Export.class.php');
			$EE_Export = EE_Export::instance($this->_req_data);
			$EE_Export->export();
		}
	}




	/**
	 * handle category exports()
	 * @return file export
	 */
	protected function _categories_export() {

		//todo: I don't like doing this but it'll do until we modify EE_Export Class.
		$new_request_args = array(
			'export' => 'report',
			'action' => 'categories',
			'category_ids' => $this->_req_data['EVT_CAT_ID']
			);

		$this->_req_data = array_merge( $this->_req_data, $new_request_args );

		if ( is_readable( EE_CLASSES . 'EE_Export.class.php') ) {
			require_once( EE_CLASSES . 'EE_Export.class.php');
			$EE_Export = EE_Export::instance( $this->_req_data );
			$EE_Export->export();
		}

	}



	/**
	 * Creates a sample CSV file for importing
	 */
	protected function _sample_export_file(){
//		require_once(EE_CLASSES . 'EE_Export.class.php');
		EE_Export::instance()->export_sample();
	}



	/*************		Template Settings 		*************/



	protected function _template_settings() {
		$this->_template_args['values'] = $this->_yes_no_values;
		/**
		 * Note leaving this filter in for backward compatibility this was moved in 4.6.x
		 * from General_Settings_Admin_Page to here.
		 */
		$this->_template_args = apply_filters( 'FHEE__General_Settings_Admin_Page__template_settings__template_args', $this->_template_args );
		$this->_set_add_edit_form_tags( 'update_template_settings' );
		$this->_set_publish_post_box_vars( NULL, FALSE, FALSE, NULL, FALSE );
		$this->_template_args['admin_page_content'] = EEH_Template::display_template( EVENTS_CAF_TEMPLATE_PATH . 'template_settings.template.php', $this->_template_args, TRUE );
		$this->display_admin_page_with_sidebar();
	}



	protected function _update_template_settings() {

		/**
		 * Note leaving this filter in for backward compatibility this was moved in 4.6.x
		 * from General_Settings_Admin_Page to here.
		 */
		EE_Registry::instance()->CFG->template_settings = apply_filters( 'FHEE__General_Settings_Admin_Page__update_template_settings__data', EE_Registry::instance()->CFG->template_settings, $this->_req_data );


		//update custom post type slugs and detect if we need to flush rewrite rules
		$old_slug = EE_Registry::instance()->CFG->core->event_cpt_slug;
		EE_Registry::instance()->CFG->core->event_cpt_slug = empty( $this->_req_data['event_cpt_slug'] ) ? EE_Registry::instance()->CFG->core->event_cpt_slug : sanitize_title_with_dashes( $this->_req_data['event_cpt_slug'] );


		$what = 'Template Settings';
		$success = $this->_update_espresso_configuration( $what, EE_Registry::instance()->CFG->template_settings, __FILE__, __FUNCTION__, __LINE__ );


		if ( EE_Registry::instance()->CFG->core->event_cpt_slug != $old_slug ) {
			update_option( 'ee_flush_rewrite_rules', true );
		}


		$this->_redirect_after_action( $success, $what, 'updated', array( 'action' => 'template_settings' ) );

	}




	/**
	 * _premium_event_editor_meta_boxes
	 * add all metaboxes related to the event_editor
	 *
	 * @access protected
	 * @return void
	 */
	protected function _premium_event_editor_meta_boxes() {

		$this->verify_cpt_object();

		add_meta_box('espresso_event_editor_event_options', __('Event Registration Options', 'event_espresso'), array( $this, 'registration_options_meta_box' ), $this->page_slug, 'side', 'core');
		//add_meta_box('espresso_event_types', __('Event Type', 'event_espresso'), array( $this, 'event_type_meta_box' ), $this->page_slug, 'side', 'default' ); //add this back in when the feature is ready.

		//todo feature in progress
		//add_meta_box('espresso_event_editor_promo_box', __('Event Promotions', 'event_espresso'), array( $this, 'promotions_meta_box' ), $this->_current_screen->id, 'side', 'core');

		//todo, this will morph into the "Person" metabox once events are converted to cpts and we have the persons cpt in place.
		/*if ( EE_Registry::instance()->CFG->admin->use_personnel_manager ) {
			add_meta_box('espresso_event_editor_personnel_box', __('Event Staff / Speakers', 'event_espresso'), array( $this, 'personnel_metabox' ), $this->page_slug, 'side', 'default');
		}/**/
	}



	/**
	 * override caf metabox
	 * @return string html contents
	 */
	public function registration_options_meta_box() {

		$yes_no_values = array(
			array('id' => true, 'text' => __('Yes', 'event_espresso')),
			array('id' => false, 'text' => __('No', 'event_espresso'))
		);
		$default_reg_status_values = EEM_Registration::reg_status_array(array(EEM_Registration::status_id_cancelled, EEM_Registration::status_id_declined, EEM_Registration::status_id_incomplete ), TRUE);
		$template_args['active_status'] = $this->_cpt_model_obj->pretty_active_status(FALSE);
		$template_args['_event'] = $this->_cpt_model_obj;
		$template_args['additional_limit'] = $this->_cpt_model_obj->additional_limit();
		$template_args['default_registration_status'] = EEH_Form_Fields::select_input('default_reg_status', $default_reg_status_values, $this->_cpt_model_obj->default_registration_status());
		$template_args['display_description'] = EEH_Form_Fields::select_input('display_desc', $yes_no_values, $this->_cpt_model_obj->display_description());
		$template_args['display_ticket_selector'] = EEH_Form_Fields::select_input('display_ticket_selector', $yes_no_values, $this->_cpt_model_obj->display_ticket_selector(), '', '', false);
		$template_args['EVT_default_registration_status'] = EEH_Form_Fields::select_input('EVT_default_registration_status', $default_reg_status_values, $this->_cpt_model_obj->default_registration_status() );
		$template_args['additional_registration_options'] = apply_filters( 'FHEE__Events_Admin_Page__registration_options_meta_box__additional_registration_options', '', $template_args, $yes_no_values, $default_reg_status_values );
		$templatepath = EVENTS_CAF_TEMPLATE_PATH . 'event_registration_options.template.php';
		EEH_Template::display_template($templatepath, $template_args);
	}




	/**
	 * event type metabox for events
	 * @param  object $post current post object
	 * @param  array  $box  metabox args
	 * @return string       metabox contents
	 */
	public function event_type_meta_box( $post, $box ) {
		$template_args['radio_list'] = $this->wp_terms_radio($post->ID, array( 'taxonomy' => 'espresso_event_type' ) );
		$template = EVENTS_CAF_TEMPLATE_PATH . 'event_type_metabox_contents.template.php';
		EEH_Template::display_template($template, $template_args);
	}







	public function wp_terms_radio( $post_id = 0, $args = array() ) {
		$defaults = array(
			'descendants_and_self' => 0,
			'selected_cats' => false,
			'popular_cats' => false,
			'walker' => null,
			'taxonomy' => 'category',
			'checked_ontop' => true
		);
		$args = apply_filters( 'wp_terms_checklist_args', $args, $post_id );

		extract( wp_parse_args($args, $defaults), EXTR_SKIP );

		if ( empty($walker) || !is_a($walker, 'Walker') )
			$walker = new Walker_Radio_Checklist;

		$descendants_and_self = (int) $descendants_and_self;

		$args = array('taxonomy' => $taxonomy);

		$tax = get_taxonomy($taxonomy);
		$args['disabled'] = !current_user_can($tax->cap->assign_terms);

		if ( is_array( $selected_cats ) )
			$args['selected_cats'] = $selected_cats;
		elseif ( $post_id )
			$args['selected_cats'] = wp_get_object_terms($post_id, $taxonomy, array_merge($args, array('fields' => 'ids')));
		else
			$args['selected_cats'] = array();

		if ( is_array( $popular_cats ) )
			$args['popular_cats'] = $popular_cats;
		else
			$args['popular_cats'] = get_terms( $taxonomy, array( 'fields' => 'ids', 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );

		if ( $descendants_and_self ) {
			$categories = (array) get_terms($taxonomy, array( 'child_of' => $descendants_and_self, 'hierarchical' => 0, 'hide_empty' => 0 ) );
			$self = get_term( $descendants_and_self, $taxonomy );
			array_unshift( $categories, $self );
		} else {
			$categories = (array) get_terms($taxonomy, array('get' => 'all'));
		}

		if ( $checked_ontop ) {
			// Post process $categories rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache)
			$checked_categories = array();
			$keys = array_keys( $categories );

			foreach( $keys as $k ) {
				if ( in_array( $categories[$k]->term_id, $args['selected_cats'] ) ) {
					$checked_categories[] = $categories[$k];
					unset( $categories[$k] );
				}
			}

			// Put checked cats on top
			$list = call_user_func_array(array(&$walker, 'walk'), array($checked_categories, 0, $args));
		}
		// Then the rest of them
		$list .= call_user_func_array(array(&$walker, 'walk'), array($categories, 0, $args));
		return $list;
	}


	/**
	 * wp_list_table_mods for caf
	 * ============================
	 */


	/**
	 * hook into list table filters and provide filters for caffeinated list table
	 * @param  array  $oldfilters     any existing filters present
	 * @param  array  $list_table_obj the list table object
	 * @return array                  new filters
	 */
	public function list_table_filters( $oldfilters, $list_table_obj ) {
		$filters = array();

		//first month/year filters
		$filters[] = $this->espresso_event_months_dropdown();


		$status = isset( $this->_req_data['status'] ) ? $this->_req_data['status'] : NULL;

		//active status dropdown
		if ( $status !== 'draft' )
			$filter[] = $this->active_status_dropdown( isset( $this->_req_data['active_status'] ) ? $this->_req_data['active_status'] : '' );

		//category filter
		$filters[] = $this->category_dropdown();


		return array_merge($oldfilters, $filters);
	}






	/**
	 * espresso_event_months_dropdown
	 *
	 * @access public
	 * @return string                dropdown listing month/year selections for events.
	 */
	public function espresso_event_months_dropdown() {
		//what we need to do is get all PRIMARY datetimes for all events to filter on. Note we need to include any other filters that are set!
		$status = isset( $this->_req_data['status'] ) ? $this->_req_data['status'] : NULL;

		//categories?
		$category = isset( $this->_req_data['EVT_CAT'] ) && $this->_req_data['EVT_CAT'] > 0 ? $this->_req_data['EVT_CAT'] : NULL;

		//active status?
		$active_status = isset( $this->_req_data['active_status'] ) ? $this->_req_data['active_status'] : NULL;

		$cur_date = isset($this->_req_data['month_range']) ? $this->_req_data['month_range'] : '';

		return EEH_Form_Fields::generate_event_months_dropdown( $cur_date, $status, $category, $active_status );
	}




	/**
	 * returns a list of "active" statuses on the event
	 * @param  string $current_value whatever the ucrrent active status is
	 * @return string                html dropdown.
	 */
	public function  active_status_dropdown( $current_value = '' ) {
		$select_name = 'active_status';
		$values = array('none' => __('Show Active/Inactive', 'event_espresso'), 'active' => __('Active', 'event_espresso'), 'upcoming' => __('Upcoming', 'event_espresso'), 'expired' => __('Expired', 'event_espresso'), 'inactive' => __('Inactive', 'event_espresso') );
		$id = 'id="espresso-active-status-dropdown-filter"';
		$class = 'wide';
		echo EEH_Form_Fields::select_input( $select_name, $values, $current_value, $id, $class );
	}



	/**
	 * output a dropdown of the categories for the category filter on the event admin list table
	 *
	 * @access  public
	 * @return string html
	 */
	public function category_dropdown() {
		$cur_cat = isset( $this->_req_data['EVT_CAT'] ) ? $this->_req_data['EVT_CAT'] : -1;

		return EEH_Form_Fields::generate_event_category_dropdown( $cur_cat );
	}



	/**
	 * get total number of events today
	 *
	 * @access public
	 * @return int
	 */
	public function total_events_today() {
		$start = EEM_Datetime::instance()->convert_datetime_for_query( 'DTT_EVT_start', date('Y-m-d' ) . ' 00:00:00', 'Y-m-d H:i:s', 'UTC' );
		$end = EEM_Datetime::instance()->convert_datetime_for_query( 'DTT_EVT_start', date('Y-m-d' ) . ' 23:59:59', 'Y-m-d H:i:s', 'UTC' );

		$where = array(
			'Datetime.DTT_EVT_start' => array( 'BETWEEN', array($start, $end ) )
			);

		$count = EEM_Event::instance()->count( array( $where, 'caps' => 'read_admin' ), 'EVT_ID', true );
		return $count;
	}

	/**
	 * get total number of events this month
	 *
	 * @access public
	 * @return int
	 */
	public function total_events_this_month() {
		//Dates
		$this_year_r = date('Y');
		$this_month_r = date('m');
		$days_this_month = date('t');
		$start = EEM_Datetime::instance()->convert_datetime_for_query( 'DTT_EVT_start', $this_year_r . '-' . $this_month_r . '-01 00:00:00', 'Y-m-d H:i:s', 'UTC' );
		$end = EEM_Datetime::instance()->convert_datetime_for_query( 'DTT_EVT_start', $this_year_r . '-' . $this_month_r . '-' . $days_this_month . ' 23:59:59', 'Y-m-d H:i:s', 'UTC' );

		$where = array(
			'Datetime.DTT_EVT_start' => array( 'BETWEEN', array($start, $end ) )
			);

		$count = EEM_Event::instance()->count( array( $where, 'caps' => 'read_admin' ), 'EVT_ID', true );
		return $count;
	}




	/** DEFAULT TICKETS STUFF **/

	public function _tickets_overview_list_table() {
		$this->_search_btn_label = __('Tickets', 'event_espresso');
		$this->display_admin_list_table_page_with_no_sidebar();
	}




	public function get_default_tickets( $per_page = 10, $count = FALSE, $trashed = FALSE ) {

		$orderby= empty( $this->_req_data['orderby'] ) ? 'TKT_name' : $this->_req_data['orderby'];
		$order = empty( $this->_req_data['order'] ) ? 'ASC' : $this->_req_data['order'];

		switch ( $orderby ) {
			case 'TKT_name' :
				$orderby = array( 'TKT_name' => $order );
				break;

			case 'TKT_price' :
				$orderby = array( 'TKT_price' => $order );
				break;

			case 'TKT_uses' :
				$orderby = array( 'TKT_uses' => $order );
				break;

			case 'TKT_min' :
				$orderby = array( 'TKT_min' => $order );
				break;

			case 'TKT_max' :
				$orderby = array( 'TKT_max' => $order );
				break;

			case 'TKT_qty' :
				$orderby = array( 'TKT_qty' => $order );
				break;
		}

		$current_page = isset( $this->_req_data['paged'] ) && !empty( $this->_req_data['paged'] ) ? $this->_req_data['paged'] : 1;
		$per_page = isset( $this->_req_data['perpage'] ) && !empty( $this->_req_data['perpage'] ) ? $this->_req_data['perpage'] : $per_page;

		$_where = array(
			'TKT_is_default' => 1,
			'TKT_deleted' => $trashed
			);

		$offset = ($current_page-1)*$per_page;
		$limit = array( $offset, $per_page );

		if ( isset( $this->_req_data['s'] ) ) {
			$sstr = '%' . $this->_req_data['s'] . '%';
			$_where['OR'] = array(
				'TKT_name' => array('LIKE',$sstr ),
				'TKT_description' => array('LIKE',$sstr )
				);
		}

		$query_params = array(
			$_where,
			'order_by'=>$orderby,
			'limit'=>$limit,
			'group_by'=>'TKT_ID'
			);

		if($count){
			return EEM_Ticket::instance()->count_deleted_and_undeleted(array($_where));
		}else{
			return EEM_Ticket::instance()->get_all_deleted_and_undeleted($query_params);
		}

	}





	protected function _trash_or_restore_ticket(  $trash = FALSE ) {
		$success = 1;

		$TKT = EEM_Ticket::instance();

		//checkboxes?
		if ( !empty( $this->_req_data['checkbox'] ) && is_array( $this->_req_data['checkbox'] ) ) {
			//if array has more than one element then success message should be plural
			$success = count( $this->_req_data['checkbox'] ) > 1 ? 2 : 1;

			//cycle thru the boxes
			while ( list( $TKT_ID, $value ) = each( $this->_req_data['checkbox'] ) ) {
				if ( $trash ) {
					if ( ! $TKT->delete_by_ID( $TKT_ID ) )
						$success = 0;
				} else {
					if ( ! $TKT->restore_by_ID( $TKT_ID ) )
						$success = 0;
				}
			}
		} else {
			//grab single id and trash
			$TKT_ID = absint( $this->_req_data['TKT_ID'] );

			if ( $trash ) {
				if ( ! $TKT->delete_by_ID( $TKT_ID ) )
					$success = 0;
			} else {
				if ( ! $TKT->restore_by_ID( $TKT_ID ) )
					$success = 0;
			}
		}

		$action_desc = $trash ? 'moved to the trash' : 'restored';
		$query_args = array(
			'action' => 'ticket_list_table',
			'status' => $trash ? '' : 'trashed'
			);
		$this->_redirect_after_action( $success, 'Tickets', $action_desc, $query_args );
	}





	protected function _delete_ticket() {
		$success = 1;

		$TKT = EEM_Ticket::instance();

		//checkboxes?
		if ( !empty( $this->_req_data['checkbox'] ) && is_array( $this->_req_data['checkbox'] ) ) {
			//if array has more than one element then success message should be plural
			$success = count( $this->_req_data['checkbox'] ) > 1 ? 2 : 1;

			//cycle thru the boxes
			while ( list( $TKT_ID, $value ) = each( $this->_req_data['checkbox'] ) ) {
				//delete
				if ( ! $this->_delete_the_ticket( $TKT_ID ) ) {
					$success = 0;
				}
			}
		} else {
			//grab single id and trash
			$TKT_ID = absint( $this->_req_data['TKT_ID'] );
			if ( ! $this->_delete_the_ticket( $TKT_ID ) ) {
					$success = 0;
				}
		}

		$action_desc = 'deleted';
		$query_args = array(
			'action' => 'ticket_list_table',
			'status' => 'trashed'
			);

		//failsafe.  If the default ticket count === 1 then we need to redirect to event overview.
		if ( EEM_Ticket::instance()->count_deleted_and_undeleted( array( array( 'TKT_is_default' => 1 ) ), 'TKT_ID', TRUE ) )
			$query_args = array();
		$this->_redirect_after_action( $success, 'Tickets', $action_desc, $query_args );
	}




	protected function _delete_the_ticket( $TKT_ID ) {
		$tkt = EEM_Ticket::instance()->get_one_by_ID( $TKT_ID );
		$tkt->_remove_relations('Datetime');
		//delete all related prices first
		$tkt->delete_related_permanently('Price');
		return $tkt->delete_permanently();
	}



} //end class Events_Admin_Page

/*
// Walker_Radio_Checklist isn't used anywhere in EE4 core currently, commenting out for now
// The version check was added to make sure Walker_Category_Checklist class is available
global $wp_version;
if ( $wp_version >= 4.4 ){
	require_once ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php';
} else {
	require_once ABSPATH . 'wp-admin/includes/template.php';
}

class Walker_Radio_Checklist extends Walker_Category_Checklist {

	function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		extract($args);
		if ( empty($taxonomy) )
			$taxonomy = 'category';

		if ( $taxonomy == 'category' )
			$name = 'post_category';
		else
			$name = 'tax_input['.$taxonomy.']';

		$class = '';
		$output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="radio" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
	}
}
*/
