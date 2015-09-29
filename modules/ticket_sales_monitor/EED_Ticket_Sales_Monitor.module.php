<?php
if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) {
	exit( 'No direct script access allowed' );
}
/**
 * Class EED_Ticket_Sales_Monitor
 *
 * ensures that tickets can not be added to the cart if they have sold out
 * in the time since the page with the ticket selector was first viewed.
 * also considers tickets in the cart that are in the process of being registered for
 *
 * @package               Event Espresso
 * @subpackage 		modules
 * @author                Brent Christensen
 * @since                 4.8.6
 *
 */
class EED_Ticket_Sales_Monitor extends EED_Module {

	const debug = false; 	//	true false

	/**
	 * an array of raw ticket data from EED_Ticket_Selector
	 *
	 * @var array $ticket_selections
	 */
	protected $ticket_selections = array();

	/**
	 * the raw ticket data from EED_Ticket_Selector is organized in rows
	 * according to how they are displayed in the actual Ticket_Selector
	 * this tracks the current row being processed
	 *
	 * @var int $current_row
	 */
	protected $current_row = 0;

	/**
	 * an array for tracking names of tickets that have sold out
	 *
	 * @var array $sold_out_tickets
	 */
	protected $sold_out_tickets = array();

	/**
	 * an array for tracking names of tickets that have had their quantities reduced
	 *
	 * @var array $decremented_tickets
	 */
	protected $decremented_tickets = array();



	/**
	 *    set_hooks - for hooking into EE Core, other modules, etc
	 *
	 * @access    public
	 * @return    void
	 */
	public static function set_hooks() {
		// check ticket reserves AFTER MER does it's check (hence priority 20)
		add_filter( 'FHEE__EE_Ticket_Selector___add_ticket_to_cart__ticket_qty',
			array( 'EED_Ticket_Sales_Monitor', 'validate_ticket_sale' ),
			20, 3
		);
		// add notices for sold out tickets
		add_action( 'AHEE__EE_Ticket_Selector__process_ticket_selections__after_tickets_added_to_cart',
			array( 'EED_Ticket_Sales_Monitor', 'post_notices' ),
			10
		);
		// handle emptied carts
		add_action(
			'AHEE__EE_Session__reset_cart__before_reset',
			array( 'EED_Ticket_Sales_Monitor', 'session_cart_reset' ),
			10, 1
		);
		add_action(
			'AHEE__EED_Multi_Event_Registration__empty_event_cart__before_delete_cart',
			array( 'EED_Ticket_Sales_Monitor', 'session_cart_reset' ),
			10, 1
		);
		// handle cancelled registrations
		add_action(
			'AHEE__EE_Session__reset_checkout__before_reset',
			array( 'EED_Ticket_Sales_Monitor', 'session_checkout_reset' ),
			10, 1
		);
		// cron tasks
		add_action(
			'AHEE__EE_Cron_Tasks__finalize_abandoned_transactions__abandoned_transaction',
			array( 'EED_Ticket_Sales_Monitor', 'process_abandoned_transactions' ),
			10, 1
		);
		add_action(
			'AHEE__EE_Cron_Tasks__process_expired_transactions__incomplete_transaction',
			array( 'EED_Ticket_Sales_Monitor', 'process_abandoned_transactions' ),
			10, 1
		);
		add_action(
			'AHEE__EE_Cron_Tasks__process_expired_transactions__failed_transaction',
			array( 'EED_Ticket_Sales_Monitor', 'process_failed_transactions' ),
			10, 1
		);

	}



	/**
	 *    set_hooks_admin - for hooking into EE Admin Core, other modules, etc
	 *
	 * @access    public
	 * @return    void
	 */
	public static function set_hooks_admin() {
		EED_Ticket_Sales_Monitor::set_hooks();
	}



	/**
	 * @return EED_Ticket_Sales_Monitor
	 */
	public static function instance() {
		return parent::get_instance( __CLASS__ );
	}



	/**
	 *    run
	 *
	 * @access    public
	 * @param WP_Query $WP_Query
	 * @return    void
	 */
	public function run( $WP_Query ) {
	}



	/********************************** VALIDATE_TICKET_SALE  **********************************/



	/**
	 *    validate_ticket_sales
	 *    callback for 'FHEE__EED_Ticket_Selector__process_ticket_selections__valid_post_data'
	 *
	 * @access    public
	 * @param int        $qty
	 * @param \EE_Ticket $ticket
	 * @return bool
	 */
	public static function validate_ticket_sale( $qty = 1, EE_Ticket $ticket  ) {
		$qty = absint( $qty );
		if ( $qty > 0 ) {
			$qty = EED_Ticket_Sales_Monitor::instance()->_validate_ticket_sale( $ticket, $qty );
		}
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
			echo "\n qty: " . $qty;
		}
		return $qty;
	}



	/**
	 *    _validate_ticket_sale
	 * checks whether an individual ticket is available for purchase based on datetime, and ticket details
	 *
	 * @access    protected
	 * @param   \EE_Ticket $ticket
	 * @param int          $qty
	 * @return int
	 */
	protected function _validate_ticket_sale( EE_Ticket $ticket, $qty = 1 ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
		}
		if ( ! $ticket instanceof EE_Ticket ) {
			return 0;
		}
		if ( self::debug ) {
			echo "\n . ticket->ID: " . $ticket->ID() . '<br />';
			echo "\n . original ticket->reserved: " . $ticket->reserved() . '<br />';
		}
		$ticket->refresh_from_db();
		// first let's determine the ticket availability based on sales
		$available = $ticket->qty() - $ticket->sold() - $ticket->reserved();
		if ( self::debug ) {
			echo "\n . . . ticket->qty: " . $ticket->qty() . '<br />';
			echo "\n . . . ticket->sold: " . $ticket->sold() . '<br />';
			echo "\n . . . ticket->reserved: " . $ticket->reserved() . '<br />';
			echo "\n . . . available: " . $available . '<br />';
		}
		if ( $available < 1 ) {
			$this->_ticket_sold_out( $ticket );
			return 0;
		}
		$available = $this->_get_all_datetimes_availability( $ticket, $available );
		if ( $available < 1 ) {
			$this->_ticket_sold_out( $ticket );
			return 0;
		}
		if ( self::debug ) {
			echo "\n . . . qty: " . $qty . '<br />';
		}
		if ( $available < $qty ) {
			$qty = $available;
			if ( self::debug ) {
				echo "\n . . . QTY ADJUSTED: " . $qty . '<br />';
			}
			$this->_ticket_quantity_decremented( $ticket );
		}
		if ( self::debug ) {
			echo "\n\n . . . INCREASE RESERVED: " . $qty . '<br/><br/>';
		}
		$ticket->increase_reserved( $qty );
		$ticket->save();
		return $qty;
	}



	/**
	 *    _get_datetime_availability
	 * determines the number of available tickets for a particular datetime
	 *
	 * @access 	protected
	 * @param 	EE_Ticket $ticket
	 * @return 	int
	 */
	protected function _get_all_datetimes_availability( EE_Ticket $ticket, $available ) {
		$datetimes = $ticket->datetimes();
		if ( ! empty( $datetimes ) ) {
			foreach ( $datetimes as $datetime ) {
				if ( $datetime instanceof EE_Datetime ) {
					$datetime_availability = $this->_get_datetime_availability( $datetime );
					if ( self::debug ) {
						echo "\n . . . FINAL AVAILABLE: " . $datetime_availability . '<br />';
					}
					$available = min( $available, $datetime_availability );
					if ( $available < 1 ) {
						return $available;
					}
				}
			}
		}
		return $available;
	}



	/**
	 *    _get_datetime_availability
	 * determines the number of available tickets for a particular datetime
	 *
	 * @access 	protected
	 * @param 	\EE_Datetime $datetime
	 * @return 	int
	 */
	protected function _get_datetime_availability( EE_Datetime $datetime ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
			echo "\n . . datetime->ID: " . $datetime->ID() . '<br />';
		}
		// don't track datetimes with unlimited reg limits
		if ( $datetime->reg_limit() < 0 ) {
			return false;
		}
		// now let's determine ticket availability based on ALL sales for the datetime
		// because multiple tickets can apply to the same datetime,
		// so the tickets themselves may not be sold out, but the datetime may be
		// ex: 20 seats are available for a dinner where the choice of ticket is your meal preference...
		// you could get 20 people wanting chicken or 20 people wanting steak, you don't know...
		// so you have to set each ticket quantity to the maximum number of seats, which is 20,
		// but we don't want 40 people showing up, only 20, so we can't go by ticket quantity alone
		$available = $datetime->reg_limit() - $datetime->sold();
		if ( self::debug ) {
			echo "\n . . . available: " . $available . '<br />';
			echo "\n . . . datetime->reg_limit: " . $datetime->reg_limit() . '<br />';
			echo "\n . . . datetime->reg_limit: " . $datetime->reg_limit() . '<br />';
		}
		// we also need to factor reserved quantities for ALL tickets into this equation
		$all_tickets = $datetime->tickets();
		foreach ( $all_tickets as $one_of_many_tickets ) {
			if ( self::debug ) {
				echo "\n . . . ticket->reserved: " . $one_of_many_tickets->reserved() . '<br />';
			}
			$available = $available - $one_of_many_tickets->reserved();
		}
		return $available;
	}



	/**
	 *    _ticket_sold_out
	 * 	removes quantities within the ticket selector based on zero ticket availability
	 *
	 * @access 	protected
	 * @param 	\EE_Ticket   $ticket
	 * @return 	bool
	 * @throws \EE_Error
	 */
	protected function _ticket_sold_out( EE_Ticket $ticket ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
			echo "\n . . ticket->name: " . $ticket->name() . '<br />';
		}
		$this->sold_out_tickets[] = $ticket->name();
	}



	/**
	 *    _ticket_quantity_decremented
	 *    adjusts quantities within the ticket selector based on decreased ticket availability
	 *
	 * @access    protected
	 * @param    \EE_Ticket $ticket
	 * @return bool
	 */
	protected function _ticket_quantity_decremented( EE_Ticket $ticket ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
			echo "\n . . ticket->name: " . $ticket->name() . '<br />';
		}
		$this->decremented_tickets[] = $ticket->name();
	}



	/********************************** POST_NOTICES  **********************************/



	/**
	 *    post_notices
	 *
	 * @access    public
	 * @return    void
	 */
	public static function post_notices() {
		EED_Ticket_Sales_Monitor::instance()->_post_notices();
	}



	/**
	 *    _post_notices
	 *
	 * @access    protected
	 * @return    void
	 */
	protected function _post_notices() {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
		}
		if ( ! empty( $this->sold_out_tickets ) ) {
			EE_Error::add_attention(
				sprintf(
					apply_filters(
						'FHEE__EED_Ticket_Sales_Monitor___post_notices__sold_out_tickets_notice',
						__( 'We\'re sorry...%1$sThe following items have sold out since you first viewed this page, and can no longer be registered for:%1$s%1$s%2$s%1$s%1$sPlease note that availability can change at any time due to cancellations, so please check back again later if registration for this event(s) is important to you.', 'event_espresso' )
					),
					'<br />',
					implode( '<br />', $this->sold_out_tickets )
				)
			);
			// alter code flow in the Ticket Selector for better UX
			add_filter( 'FHEE__EED_Ticket_Selector__process_ticket_selections__tckts_slctd', '__return_true' );
			add_filter( 'FHEE__EED_Ticket_Selector__process_ticket_selections__success', '__return_false' );
			$this->sold_out_tickets = array();
		}
		if ( ! empty( $this->decremented_tickets ) ) {
			EE_Error::add_attention(
				sprintf(
					apply_filters(
						'FHEE__EED_Ticket_Sales_Monitor___ticket_quantity_decremented__notice',
						__( 'We\'re sorry...%1$sDue to sales that have occurred since you first viewed the last page, the following items have had their quantities adjusted to match the current available amount:%1$s%1$s%2$s%1$s%1$sPlease note that availability can change at any time due to cancellations, so please check back again later if registration for this event(s) is important to you.', 'event_espresso' )
					),
					'<br />',
					implode( '<br />', $this->decremented_tickets )
				)
			);
			$this->decremented_tickets = array();
		}
	}



	/********************************** RELEASE_ALL_RESERVED_TICKETS_FOR_TRANSACTION  **********************************/



	/**
	 *    _release_all_reserved_tickets_for_transaction
	 *    releases reserved tickets for all registrations of an EE_Transaction
	 *    by default, will NOT release tickets for finalized transactions
	 *
	 * @access 	protected
	 * @param 	EE_Transaction 	$transaction
	 * @return int
	 */
	protected function _release_all_reserved_tickets_for_transaction( EE_Transaction $transaction ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
			echo "\n . transaction->ID: " . $transaction->ID() . '<br />';
		}
		/** @type EE_Transaction_Processor $transaction_processor */
		$transaction_processor = EE_Registry::instance()->load_class( 'Transaction_Processor' );
		// check if 'finalize_registration' step has been completed...
		$finalized = $transaction_processor->reg_step_completed( $transaction, 'finalize_registration' );
		// DEBUG LOG
		EEH_Debug_Tools::log(
			__CLASS__, __FUNCTION__, __LINE__,
			array( 'finalized' => $finalized ),
			false, 'EE_Transaction: ' . $transaction->ID()
		);
		// how many tickets were released
		$count = 0;
		if ( self::debug ) {
			echo "\n . . . finalized: " . $finalized . '<br />';
		}
		$release_tickets_with_TXN_status = array(
			EEM_Transaction::failed_status_code,
			EEM_Transaction::abandoned_status_code,
			EEM_Transaction::incomplete_status_code,
		);
		// if the session is getting cleared BEFORE the TXN has been finalized
		if ( ! $finalized || in_array( $transaction->status_ID(), $release_tickets_with_TXN_status ) ) {
			// let's cancel any reserved tickets
			$registrations = $transaction->registrations();
			if ( ! empty( $registrations ) ) {
				foreach ( $registrations as $registration ) {
					if ( $registration instanceof EE_Registration ) {
						$count += $this->_release_reserved_ticket_for_registration( $registration, $transaction );
					}
				}
			}
		}
		return $count;
	}



	/**
	 *    _release_reserved_ticket_for_registration
	 *    releases reserved tickets for an EE_Registration
	 *    by default, will NOT release tickets for APPROVED registrations
	 *
	 * @access 	protected
	 * @param 	EE_Registration $registration
	 * @param 	EE_Transaction $transaction
	 * @return 	int
	 * @throws 	\EE_Error
	 */
	protected function _release_reserved_ticket_for_registration( EE_Registration $registration, EE_Transaction $transaction ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
			echo "\n . . registration->ID: " . $registration->ID() . '<br />';
			echo "\n . . registration->status_ID: " . $registration->status_ID() . '<br />';
			echo "\n . . transaction->status_ID(): " . $transaction->status_ID() . '<br />';
		}
		if (
			// release Tickets for Failed Transactions and Abandoned Transactions
			$transaction->status_ID() === EEM_Transaction::failed_status_code ||
			$transaction->status_ID() === EEM_Transaction::abandoned_status_code ||
			(
				// also release Tickets for Incomplete Transactions, but ONLY if the Registrations are NOT Approved
				$transaction->status_ID() === EEM_Transaction::incomplete_status_code &&
				$registration->status_ID() !== EEM_Registration::status_id_approved
			)
		) {
			$ticket = $registration->ticket();
			if ( $ticket instanceof EE_Ticket ) {
				if ( self::debug ) {
					echo "\n . . . ticket->ID: " . $ticket->ID() . '<br />';
					echo "\n . . . ticket->reserved: " . $ticket->reserved() . '<br />';
				}
				$ticket->decrease_reserved();
				if ( self::debug ) {
					echo "\n . . . ticket->reserved: " . $ticket->reserved() . '<br />';
				}
				return $ticket->save() ? 1 : 0;
			}
		}
		return 0;
	}



	/********************************** SESSION_CART_RESET  **********************************/



	/**
	 *    session_cart_reset
	 * callback hooked into 'AHEE__EE_Session__reset_cart__before_reset'
	 *
	 * @access    public
	 * @param    EE_Session $session
	 * @return    void
	 */
	public static function session_cart_reset( EE_Session $session ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
		}
		$cart = $session->cart();
		if ( $cart instanceof EE_Cart ) {
			if ( self::debug ) {
				echo "\n\n cart instanceof EE_Cart: " . "<br />";
			}
			EED_Ticket_Sales_Monitor::instance()->_session_cart_reset( $cart );
		}
	}



	/**
	 *    _session_cart_reset
	 * releases reserved tickets in the EE_Cart
	 *
	 * @access    protected
	 * @param    EE_Cart $cart
	 * @return    void
	 */
	protected function _session_cart_reset( EE_Cart $cart ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
		}
		EE_Registry::instance()->load_helper( 'Line_Item' );
		$ticket_line_items = $cart->get_tickets();
		if ( empty( $ticket_line_items ) ) {
			return;
		}
		foreach ( $ticket_line_items as $ticket_line_item ) {
			if ( self::debug ) {
				echo "\n . ticket_line_item->ID(): " . $ticket_line_item->ID() . "<br />";
			}
			if ( $ticket_line_item instanceof EE_Line_Item && $ticket_line_item->OBJ_type() == 'Ticket' ) {
				if ( self::debug ) {
					echo "\n . . ticket_line_item->OBJ_ID(): " . $ticket_line_item->OBJ_ID() . "<br />";
				}
				$ticket = EEM_Ticket::instance()->get_one_by_ID( $ticket_line_item->OBJ_ID() );
				if ( $ticket instanceof EE_Ticket ) {
					if ( self::debug ) {
						echo "\n . . ticket->ID(): " . $ticket->ID() . "<br />";
						echo "\n . . ticket_line_item->quantity(): " . $ticket_line_item->quantity() . "<br />";
					}
					$ticket->decrease_reserved( $ticket_line_item->quantity() );
					$ticket->save();
				}
			}
		}
	}



	/********************************** SESSION_CHECKOUT_RESET  **********************************/



	/**
	 *    session_checkout_reset
	 * callback hooked into 'AHEE__EE_Session__reset_checkout__before_reset'
	 *
	 * @access    public
	 * @param    EE_Session $session
	 * @return    void
	 */
	public static function session_checkout_reset( EE_Session $session ) {
		$checkout = $session->checkout();
		if ( $checkout instanceof EE_Checkout ) {
			EED_Ticket_Sales_Monitor::instance()->_session_checkout_reset( $checkout );
		}
	}



	/**
	 *    _session_checkout_reset
	 * releases reserved tickets for the EE_Checkout->transaction
	 *
	 * @access    protected
	 * @param    EE_Checkout $checkout
	 * @return    void
	 */
	protected function _session_checkout_reset( EE_Checkout $checkout ) {
		if ( self::debug ) {
			echo "\n\n " . __LINE__ . ") " . __METHOD__ . "() <br />";
		}
		// we want to release the each registration's reserved tickets if the session was cleared, but not if this is a revisit
		if ( $checkout->revisit || ! $checkout->transaction instanceof EE_Transaction ) {
			return;
		}
		$this->_release_all_reserved_tickets_for_transaction( $checkout->transaction );
	}



	/********************************** SESSION_EXPIRED_RESET  **********************************/



	/**
	 *    session_expired_reset
	 *
	 * @access    public
	 * @param    EE_Session $session
	 * @return    void
	 */
	public static function session_expired_reset( EE_Session $session ) {

	}



	/********************************** PROCESS_ABANDONED_TRANSACTIONS  **********************************/



	/**
	 *    process_abandoned_transactions
	 *    releases reserved tickets for all registrations of an ABANDONED EE_Transaction
	 *    by default, will NOT release tickets for free transactions, or any that have received a payment
	 *
	 * @access    public
	 * @param    EE_Transaction $transaction
	 * @return    void
	 */
	public static function process_abandoned_transactions( EE_Transaction $transaction ) {
		// is this TXN free or has any money been paid towards this TXN? If so, then leave it alone
		if ( $transaction->is_free() || $transaction->paid() > 0 ) {
			// DEBUG LOG
			EEH_Debug_Tools::log(
				__CLASS__, __FUNCTION__, __LINE__,
				array( $transaction ),
				false, 'EE_Transaction: ' . $transaction->ID()
			);
			return;
		}
		// have their been any successful payments made ?
		$payments = $transaction->payments();
		foreach ( $payments as $payment ) {
			if ( $payment instanceof EE_Payment ) {
				if ( $payment->status() === EEM_Payment::status_id_approved ) {
					// DEBUG LOG
					EEH_Debug_Tools::log(
						__CLASS__, __FUNCTION__, __LINE__,
						array( $payment ),
						false, 'EE_Transaction: ' . $transaction->ID()
					);
					return;
				}
			}
		}
		// since you haven't even attempted to pay for your ticket...
		EED_Ticket_Sales_Monitor::instance()->_release_all_reserved_tickets_for_transaction( $transaction );
	}



	/********************************** PROCESS_FAILED_TRANSACTIONS  **********************************/



	/**
	 *    process_abandoned_transactions
	 *    releases reserved tickets for absolutely ALL registrations of a FAILED EE_Transaction
	 *
	 * @access    public
	 * @param    EE_Transaction $transaction
	 * @return    void
	 */
	public static function process_failed_transactions( EE_Transaction $transaction ) {
		// since you haven't even attempted to pay for your ticket...
		EED_Ticket_Sales_Monitor::instance()->_release_all_reserved_tickets_for_transaction( $transaction );
	}





}
// End of file EED_Ticket_Sales_Monitor.module.php
// Location: /modules/ticket_sales_monitor/EED_Ticket_Sales_Monitor.module.php