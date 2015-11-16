<?php namespace EventEspressoBatchRequest\JobHandlers;
use EventEspressoBatchRequest\JobHandlerBaseClasses\JobHandlerFile;
use EventEspressoBatchRequest\Helpers\BatchRequestException;
use EventEspressoBatchRequest\Helpers\JobParameters;
use EventEspressoBatchRequest\Helpers\JobStepResponse;
/* 
 * Generates the registrations report for the specified event,
 * or for all events
 */

class RegistrationsReport extends JobHandlerFile {
	/**
	 * Performs any necessary setup for starting the job. This is also a good
	 * place to setup the $job_arguments which will be used for subsequent HTTP requests
	 * when continue_job will be called
	 * @param JobParameters $job_parameters
	 * @throws BatchRequestException
	 * @return JobStepResponse
	 */
	public function create_job( JobParameters $job_parameters ) {
		$event_id = intval( $job_parameters->request_datum( 'EVT_ID', '0' ) );
		if( ! \EE_Capabilities::instance()->current_user_can( 'ee_read_registrations', 'generating_report' ) ) {
			throw new BatchRequestException( __( 'You do not have permission to view registrations', 'event_espresso') );
		}
		$filepath = $this->create_file_from_job_with_name($job_parameters->job_id(), $this->get_filename_from_event( $event_id ));
		$job_parameters->add_extra_data( 'filepath', $filepath );
		$job_parameters->set_job_size( $this->count_units_to_process( $event_id ) );
		//we should also set the header columns
		$csv_data_for_row = $this->get_csv_data_for( $event_id, 0, 1 );
		$success = \EEH_Export::write_data_array_to_csv( $filepath, $csv_data_for_row, true );
		//if we actually processed a row there, record it
		if( $job_parameters->job_size() ) {
			$job_parameters->mark_processed( 1 );
		}
		return new JobStepResponse( $job_parameters, __( 'Registrations report started successfully...', 'event_espresso' ) );
	}
	
	/**
	 * Creates teh filename form the event id (or lack thereof)
	 * @param int $event_id
	 * @return string
	 */
	protected function get_filename_from_event( $event_id ) {
		if( $event_id ){
			$event_slug =  \EEM_Event::instance()->get_var( array( array( 'EVT_ID' => $event_id ) ), 'EVT_slug' );
			if( ! $event_slug ) {
				$event_slug = __( 'unknown', 'event_espresso' );
			}
		}else{
			$event_slug = __( 'all', 'event_espresso' );
		}
		return sprintf( "registrations-for-%s.csv", $event_slug );
	}
	
	/**
	 * Performs another step of the job
	 * @param JobParameters $job_parameters
	 * @return array{
	 *	@type string $status
	 *	@type int $records_processed
	 *	@type int $records_to_process
	 *	@type string message
	 * } and anything more we want to add
	 * @throws 
	 */
	public function continue_job( JobParameters $job_parameters, $batch_size = 50 ) {
		$csv_data = $this->get_csv_data_for( $job_parameters->request_datum( 'EVT_ID', '0'), $job_parameters->units_processed(), $batch_size );
		$success = \EEH_Export::write_data_array_to_csv( $job_parameters->extra_datum( 'filepath' ), $csv_data, false );
		$units_processed = count( $csv_data );
		$job_parameters->mark_processed( $units_processed );
		$extra_response_data = array(
			'file_url' => ''
		);
		if( $units_processed < $batch_size ) {
			$job_parameters->set_status( JobParameters::status_complete );
			$extra_response_data[ 'file_url' ] = $this->convert_filepath_to_url( $job_parameters->extra_datum( 'filepath' ) );
		}
		return new JobStepResponse( 
				$job_parameters, 
				sprintf( 
					__( 'Wrote %1$s rows to report CSV file...', 'event_espresso' ),
					count( $csv_data ) ),
				$extra_response_data );
	}
	
	function get_csv_data_for( $event_id, $offset, $limit ) {
		\EE_Registry::instance()->load_helper( 'Export' );
		$reg_fields_to_include = array(
				'TXN_ID',
				'ATT_ID',
				'REG_ID',
				'REG_date',
				'REG_code',
				'REG_count',
				'REG_final_price'

		);
		$att_fields_to_include = array(
			'ATT_fname',
			'ATT_lname',
			'ATT_email',
			'ATT_address',
			'ATT_address2',
			'ATT_city',
			'STA_ID',
			'CNT_ISO',
			'ATT_zip',
			'ATT_phone',
		);

		$registrations_csv_ready_array = array();
		$reg_model = \EE_Registry::instance()->load_model('Registration');
		$query_params = apply_filters(
			'FHEE__EE_Export__report_registration_for_event',
			array(
				array(
					'OR' => array(
						//don't include registrations from failed or abandoned transactions...
						'Transaction.STS_ID' => array( 'NOT IN', array( \EEM_Transaction::failed_status_code, \EEM_Transaction::abandoned_status_code ) ),
						//unless the registration is approved, in which case include it regardless of transaction status
						'STS_ID' => \EEM_Registration::status_id_approved
						),
					'Ticket.TKT_deleted' => array( 'IN', array( true, false ) )
					),
				'order_by' => array('Transaction.TXN_ID'=>'asc','REG_count'=>'asc'),
				'force_join' => array( 'Transaction', 'Ticket', 'Attendee' ),
				'limit' => array( $offset, $limit ),
			),
			$event_id
		);
		if( $event_id ){
			$query_params[0]['EVT_ID'] =  $event_id;
		}else{
			$query_params[ 'force_join' ][] = 'Event';
		}
		$registration_rows = $reg_model->get_all_wpdb_results( $query_params );
		//get all questions which relate to someone in this group
		$registration_ids = array();
		foreach( $registration_rows as $reg_row ) {
			$registration_ids[] = intval( $reg_row[ 'Registration.REG_ID'] );
		}
//		EEM_Question::instance()->show_next_x_db_queries();
		if( $event_id ) {
			$questions_for_these_regs_rows = \EEM_Question::instance()->get_all_wpdb_results(array(array('Answer.Registration.EVT_ID'=> $event_id ) ) );
		} else {
			$questions_for_these_regs_rows = \EEM_Question::instance()->get_all_wpdb_results(array(array('Answer.ANS_ID'=> array( 'IS_NOT_NULL' ) ) ) );
		}
		
		foreach($registration_rows as $reg_row){
			if ( is_array( $reg_row ) ) {
				$reg_csv_array = array();
				if( ! $event_id ){
					//get the event's name and Id
					$reg_csv_array[ __( 'Event', 'event_espresso' ) ] = sprintf( __( '%1$s (%2$s)', 'event_espresso' ), \EEH_Export::prepare_value_from_db_for_display( \EEM_Event::instance(), 'EVT_name', $reg_row[ 'Event_CPT.post_title'] ), $reg_row[ 'Event_CPT.ID' ] );
				}
				$is_primary_reg = $reg_row[ 'Registration.REG_count' ] == '1' ? true : false;
				/*@var $reg_row EE_Registration */
				foreach($reg_fields_to_include as $field_name){
					$field = $reg_model->field_settings_for($field_name);
					if($field_name == 'REG_final_price'){
						$value = \EEH_Export::prepare_value_from_db_for_display( $reg_model, $field_name, $reg_row[ 'Registration.REG_final_price'], 'localized_float' );
					}elseif( $field_name == 'REG_count' ){
						$value = sprintf( __( '%s of %s', 'event_espresso' ), \EEH_Export::prepare_value_from_db_for_display( $reg_model, 'REG_count', $reg_row['Registration.REG_count'] ), \EEH_Export::prepare_value_from_db_for_display( $reg_model, 'REG_group_size', $reg_row['Registration.REG_group_size' ] ) );
					}elseif( $field_name == 'REG_date' ) {
						$value = \EEH_Export::prepare_value_from_db_for_display( $reg_model, $field_name, $reg_row[ 'Registration.REG_date'], 'no_html' );
					}else{
						$value = \EEH_Export::prepare_value_from_db_for_display( $reg_model, $field_name, $reg_row[ $field->get_qualified_column() ] );
					}
					$reg_csv_array[\EEH_Export::get_column_name_for_field($field)] = $value;
					if($field_name == 'REG_final_price'){
						//add a column named Currency after the final price
						$reg_csv_array[__("Currency", "event_espresso")] = \EE_Config::instance()->currency->code;
					}
				}
				//get pretty status
				$stati = \EEM_Status::instance()->localized_status( array(
					$reg_row[ 'Registration.STS_ID' ] => __( 'unknown', 'event_espresso' ),
					$reg_row[ 'Transaction.STS_ID' ] => __( 'unknown', 'event_espresso' ) ),
						FALSE,
						'sentence' );
				$reg_csv_array[__("Registration Status", 'event_espresso')] = $stati[ $reg_row[ 'Registration.STS_ID' ] ];
				//get pretty trnasaction status
				$reg_csv_array[__("Transaction Status", 'event_espresso')] = $stati[ $reg_row[ 'Transaction.STS_ID' ] ];
				$reg_csv_array[ __( 'Transaction Amount Due', 'event_espresso' ) ] = $is_primary_reg ? \EEH_Export::prepare_value_from_db_for_display( \EEM_Transaction::instance(), 'TXN_total', $reg_row[ 'Transaction.TXN_total' ], 'localized_float' ) : '0.00';
				$reg_csv_array[ __( 'Amount Paid', 'event_espresso' )] = $is_primary_reg ? \EEH_Export::prepare_value_from_db_for_display( \EEM_Transaction::instance(), 'TXN_paid', $reg_row[ 'Transaction.TXN_paid' ], 'localized_float' ) : '0.00';
				$payment_methods = array();
				$gateway_txn_ids_etc = array();
				$payment_times = array();
				if( $is_primary_reg && $reg_row[ 'Transaction.TXN_ID' ] ){
					$payments_info = \EEM_Payment::instance()->get_all_wpdb_results(
							array(
								array(
									'TXN_ID' => $reg_row[ 'Transaction.TXN_ID' ],
									'STS_ID' => \EEM_Payment::status_id_approved
								),
								'force_join' => array( 'Payment_Method' ),

							),
							ARRAY_A,
							'Payment_Method.PMD_admin_name as name, Payment.PAY_txn_id_chq_nmbr as gateway_txn_id, Payment.PAY_timestamp as payment_time' );

					foreach( $payments_info as $payment_method_and_gateway_txn_id ){
						$payment_methods[] = isset( $payment_method_and_gateway_txn_id[ 'name' ] ) ? $payment_method_and_gateway_txn_id[ 'name' ] : __( 'Unknown', 'event_espresso' );
						$gateway_txn_ids_etc[] = isset( $payment_method_and_gateway_txn_id[ 'gateway_txn_id' ] ) ? $payment_method_and_gateway_txn_id[ 'gateway_txn_id' ] : '';
						$payment_times[] = isset( $payment_method_and_gateway_txn_id[ 'payment_time' ] ) ? $payment_method_and_gateway_txn_id[ 'payment_time' ] : '';
					}

				}
				$reg_csv_array[ __( 'Payment Date(s)', 'event_espresso' ) ] = implode( ',', $payment_times );
				$reg_csv_array[ __( 'Payment Method(s)', 'event_espresso' ) ] = implode( ",", $payment_methods );
				$reg_csv_array[ __( 'Gateway Transaction ID(s)', 'event_espresso' )] = implode( ',', $gateway_txn_ids_etc );

				//get whether or not the user has checked in
				$reg_csv_array[__("Check-Ins", "event_espresso")] = $reg_model->count_related( $reg_row[ 'Registration.REG_ID'] , 'Checkin' );
				//get ticket of registration and its price
				$ticket_model = \EE_Registry::instance()->load_model('Ticket');
				if( $reg_row[ 'Ticket.TKT_ID'] ) {
					$ticket_name = \EEH_Export::prepare_value_from_db_for_display( $ticket_model, 'TKT_name', $reg_row[ 'Ticket.TKT_name' ] );
					$datetimes_strings = array();
					foreach( \EEM_Datetime::instance()->get_all_wpdb_results( array( array( 'Ticket.TKT_ID' => $reg_row[ 'Ticket.TKT_ID' ] ), 'order_by' => array( 'DTT_EVT_start' => 'ASC' ), 'default_where_conditions' => 'none' ) ) as $datetime){
						$datetimes_strings[] = \EEH_Export::prepare_value_from_db_for_display( \EEM_Datetime::instance(), 'DTT_EVT_start', $datetime[ 'Datetime.DTT_EVT_start'] );
					}

				} else {
					$ticket_name = __( 'Unknown', 'event_espresso' );
					$datetimes_strings = array( __( 'Unknown', 'event_espresso' ) );
				}
				$reg_csv_array[$ticket_model->field_settings_for('TKT_name')->get_nicename()] = $ticket_name;
				$reg_csv_array[__("Datetimes of Ticket", "event_espresso")] = implode(", ", $datetimes_strings);
				//get datetime(s) of registration

				//add attendee columns
				foreach($att_fields_to_include as $att_field_name){
					$field_obj = \EEM_Attendee::instance()->field_settings_for($att_field_name);
					if( $reg_row[ 'Attendee_CPT.ID' ]){
						if($att_field_name == 'STA_ID'){
							$value = \EEM_State::instance()->get_var( array( array( 'STA_ID' => $reg_row[ 'Attendee_Meta.STA_ID' ] ) ), 'STA_name' );
						}elseif($att_field_name == 'CNT_ISO'){
							$value = \EEM_Country::instance()->get_var( array( array( 'CNT_ISO' => $reg_row[ 'Attendee_Meta.CNT_ISO' ] ) ), 'CNT_name' );
						}else{
							$value = \EEH_Export::prepare_value_from_db_for_display( \EEM_Attendee::instance(), $att_field_name, $reg_row[ $field_obj->get_qualified_column() ] );
						}
					}else{
						$value = '';
					}

					$reg_csv_array[ \EEH_Export::get_column_name_for_field($field_obj) ] = $value;
				}

				//make sure each registration has the same questions in the same order
				foreach($questions_for_these_regs_rows as $question_row){
					if( ! isset($reg_csv_array[$question_row[ 'Question.QST_admin_label']])){
						$reg_csv_array[$question_row[ 'Question.QST_admin_label' ] ] = null;
					}
				}
				//now fill out the questions THEY answered
				foreach( \EEM_Answer::instance()->get_all_wpdb_results( array( array( 'REG_ID' => $reg_row[ 'Registration.REG_ID' ] ), 'force_join' => array( 'Question' ) ) ) as $answer_row){
					/* @var $answer EE_Answer */
					if( $answer_row[ 'Question.QST_ID' ] ){
						$question_label = \EEH_Export::prepare_value_from_db_for_display( \EEM_Question::instance(), 'QST_admin_label', $answer_row[ 'Question.QST_admin_label' ] );
					}else{
						$question_label = sprintf( __( 'Question $s', 'event_espresso' ), $answer_row[ 'Answer.QST_ID' ] );
					}
                                        if( isset( $answer_row[ 'Question.QST_type'] ) && $answer_row[ 'Question.QST_type' ] == \EEM_Question::QST_type_state ) {
                                            $reg_csv_array[ $question_label ] = \EEM_State::instance()->get_state_name_by_ID( $answer_row[ 'Answer.ANS_value' ] );
                                        } else {
                                            $reg_csv_array[ $question_label ] = \EEH_Export::prepare_value_from_db_for_display( \EEM_Answer::instance(), 'ANS_value', $answer_row[ 'Answer.ANS_value' ] );
                                        }
				}
				$registrations_csv_ready_array[] = apply_filters( 'FHEE__EE_Export__report_registrations__reg_csv_array', $reg_csv_array, $reg_row );
			}
		}

		//if we couldn't export anything, we want to at least show the column headers
		if(empty($registrations_csv_ready_array)){
			$reg_csv_array = array();
			$model_and_fields_to_include = array(
				'Registration' => $reg_fields_to_include,
				'Attendee' => $att_fields_to_include
			);
			foreach($model_and_fields_to_include as $model_name => $field_list){
				$model = \EE_Registry::instance()->load_model($model_name);
				foreach($field_list as $field_name){
					$field = $model->field_settings_for($field_name);
					$reg_csv_array[ \EEH_Export::get_column_name_for_field($field)] = null;
				}
			}
			$registrations_csv_ready_array [] = $reg_csv_array;
		}
		return $registrations_csv_ready_array;
	}
	
	/**
	 * Counts total unit to process
	 * @return int
	 */
	public function count_units_to_process( $event_id ) {
		//use the legacy filter
		$query_params = apply_filters(
			'FHEE__EE_Export__report_registration_for_event',
			array(
				array(
					'OR' => array(
						//don't include registrations from failed or abandoned transactions...
						'Transaction.STS_ID' => array( 'NOT IN', array( \EEM_Transaction::failed_status_code, \EEM_Transaction::abandoned_status_code ) ),
						//unless the registration is approved, in which case include it regardless of transaction status
						'STS_ID' => \EEM_Registration::status_id_approved
						),
					'Ticket.TKT_deleted' => array( 'IN', array( true, false ) )
					),
				'order_by' => array('Transaction.TXN_ID'=>'asc','REG_count'=>'asc'),
				'force_join' => array( 'Transaction', 'Ticket', 'Attendee' )
			),
			$event_id
		);
		if( $event_id ){
			$query_params[0]['EVT_ID'] =  $event_id;
		}else{
			$query_params[ 'force_join' ][] = 'Event';
		}
		return \EEM_Registration::instance()->count( $query_params );
	}
	
	/**
	 * Performs any clean-up logic when we know the job is completed.
	 * In this case, we delete the temporary file
	 * @param JobParameters $job_parameters
	 * @return boolean
	 */
	public function cleanup_job( JobParameters $job_parameters ){
		$success = $this->_file_helper->delete( 
				\EEH_File::remove_filename_from_filepath( $job_parameters->extra_datum( 'filepath' ) ), 
				true, 
				'd' );
		return new JobStepResponse( $job_parameters, __( 'Cleaned up temporary file', 'event_espresso' ) );
	}
	
	/**
	 * Gets the URL to download teh file
	 * @param type $filepath
	 * @return string
	 */
	public function convert_filepath_to_url( $filepath ) {
		return str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $filepath );
	}
}

