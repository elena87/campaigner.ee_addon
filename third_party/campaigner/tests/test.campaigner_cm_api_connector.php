<?php

/**
 * Campaigner Campaign Monitor API Connector tests.
 *
 * @package 	Campaigner
 * @author 		Stephen Lewis <addons@experienceinternet.co.uk>
 * @copyright	Experience Internet
 */

require_once PATH_THIRD .'campaigner/classes/campaigner_cm_api_connector.php';
require_once PATH_THIRD .'campaigner/tests/mocks/createsend-php/mock.csrest_clients.php';
require_once PATH_THIRD .'campaigner/tests/mocks/createsend-php/mock.csrest_general.php';
require_once PATH_THIRD .'campaigner/tests/mocks/createsend-php/mock.csrest_lists.php';
require_once PATH_THIRD .'campaigner/tests/mocks/createsend-php/mock.csrest_subscribers.php';
require_once PATH_THIRD .'campaigner/tests/mocks/mock.campaigner_model.php';

class Test_campaigner_api_connector extends Testee_unit_test_case {
	
	/* --------------------------------------------------------------
	 * PRIVATE PROPERTIES
	 * ------------------------------------------------------------ */
	
	/**
	 * Dummy API key.
	 *
	 * @access	private
	 * @var		string
	 */
	private $_api_key;

	/**
	 * Dummy CM API connector classes.
	 *
	 * @access	private
	 * @var		object
	 */
	private $_cm_api_clients;
	private $_cm_api_general;
	private $_cm_api_lists;
	private $_cm_api_subscribers;

	/**
	 * Model.
	 *
	 * @access	private
	 * @var		object
	 */
	private $_model;
	
	/**
	 * The test subject.
	 *
	 * @access	private
	 * @var		Campaigner_api_connector
	 */
	private $_subject;
	
	
	
	/* --------------------------------------------------------------
	 * PRIVATE METHODS
	 * ------------------------------------------------------------ */
	
	/**
	 * Utility method, to convert an array to a StdClass object.
	 *
	 * @access	private
	 * @param	array		$subject		The array to convert.
	 * @return	StdClass
	 */
	private function _convert_array_to_object($subject)
	{
		if ( ! is_array($subject))
		{
			return $subject;
		}
		
		return (object) array_map(array($this, '_convert_array_to_object'), $subject);
	}
	
	
	
	/* --------------------------------------------------------------
	 * PUBLIC METHODS
	 * ------------------------------------------------------------ */
	
	/**
	 * Runs before each test.
	 *
	 * @access	public
	 * @return	void
	 */
	public function setUp()
	{
		parent::setUp();

		// Mock API connector classes.
		Mock::generate('Mock_CS_REST_Wrapper_Result', get_class($this) .'_mock_cm_api_result');

		Mock::generate('Mock_CS_REST_Clients', get_class($this) .'_mock_cm_api_clients');
		$this->_cm_api_clients = $this->_get_mock('cm_api_clients');

		Mock::generate('Mock_CS_REST_General', get_class($this) .'_mock_cm_api_general');
		$this->_cm_api_general = $this->_get_mock('cm_api_general');

		Mock::generate('Mock_CS_REST_Lists', get_class($this) .'_mock_cm_api_lists');
		$this->_cm_api_lists = $this->_get_mock('cm_api_lists');

		Mock::generate('Mock_CS_REST_Subscribers', get_class($this) .'_mock_cm_api_subscribers');
		$this->_cm_api_subscribers = $this->_get_mock('cm_api_subscribers');

		Mock::generate('Mock_campaigner_model', get_class($this) .'_mock_campaigner_model');
		$this->_model = $this->_get_mock('campaigner_model');

		// Initialise some test properties.
		$this->_api_key	= '04f82350a845ey7y87y87y82091015a00';

		// Create the test subject.
		$this->_subject = new Campaigner_cm_api_connector($this->_api_key, $this->_model);
	}
	
	
	/* --------------------------------------------------------------
	 * TEST METHODS
	 * ------------------------------------------------------------ */
	
	public function test__get_clients__success()
	{
		// Dummy values.
		$http_status_code	= '200';
		$response			= array(
			$this->_convert_array_to_object(array('ClientID' => '4a397ccaaa55eb4e6aa1221e1e2d7122', 'Name' => 'Client A')),
			$this->_convert_array_to_object(array('ClientID' => 'a206def0582eec7dae47d937a4109cb2', 'Name' => 'Client B'))
		);

		$result = $this->_get_mock('cm_api_result');

		$return = array(
			new Campaigner_client(array('client_id' => $response[0]->ClientID, 'client_name' => $response[0]->Name)),
			new Campaigner_client(array('client_id' => $response[1]->ClientID, 'client_name' => $response[1]->Name))
		);

		// Expectations.
		$this->_model->expectOnce('get_api_class_general');
		$this->_cm_api_general->expectOnce('get_clients');
		$result->expectOnce('was_successful');
		
		// Return values.
		$this->_model->setReturnReference('get_api_class_general', $this->_cm_api_general);
		$this->_cm_api_general->setReturnReference('get_clients', $result);

		$result->setReturnValue('__get', $http_status_code, array('http_status_code'));
		$result->setReturnValue('__get', $response, array('response'));
		$result->setReturnValue('was_successful', TRUE);
		
		// Tests.
		$this->assertIdentical($return, $this->_subject->get_clients());
	}


	public function test__get_clients__api_error()
	{
		// Dummy values.
		$http_status_code	= '401';
		$response			= $this->_convert_array_to_object(array('Code' => '100', 'Message' => 'Invalid API Key'));
		$result				= $this->_get_mock('cm_api_result');

		// Return values.
		$this->_model->setReturnReference('get_api_class_general', $this->_cm_api_general);
		$this->_cm_api_general->setReturnReference('get_clients', $result);
		$result->setReturnValue('__get', $http_status_code, array('http_status_code'));
		$result->setReturnValue('__get', $response, array('response'));
		$result->setReturnValue('was_successful', FALSE);

		// Tests.
		$this->expectException(new Campaigner_api_exception($response->Message, $response->Code));
		$this->_subject->get_clients();
	}


	public function test__get_clients__no_connector()
	{
		// Dummy values.
		$message = 'No API connector.';

		// Expectations.
		$this->_model->expectOnce('get_api_class_general');
		$this->_cm_api_general->expectNever('get_clients');
		
		// Return values.
		$this->_model->setReturnValue('get_api_class_general', FALSE);
		$this->_ee->lang->setReturnValue('line', $message, array('error_no_api_connector'));
		
		// Tests.
		$this->expectException(new Campaigner_exception($message));
		$this->_subject->get_clients();
	}


	public function test__get_client_lists__do_not_include_fields_success()
	{
		// Dummy values.
		$client_id			= 'a58ee1d3039b8bec838e6d1482a8a966';
		$http_status_code	= '200';
		$response			= array(
			$this->_convert_array_to_object(array('ListID' => 'a58ee1d3039b8bec838e6d1482a8a965', 'Name' => 'List A')),
			$this->_convert_array_to_object(array('ListID' => '99bc35084a5739127a8ab81eae5bd305', 'Name' => 'List B'))
		);

		$result = $this->_get_mock('cm_api_result');

		$return = array(
			new Campaigner_mailing_list(array('list_id' => $response[0]->ListID, 'list_name' => $response[0]->Name)),
			new Campaigner_mailing_list(array('list_id' => $response[1]->ListID, 'list_name' => $response[1]->Name))
		);

		// Expectations.
		$this->_model->expectOnce('get_api_class_clients', array($client_id));
		$this->_cm_api_clients->expectOnce('get_lists');
		$result->expectOnce('was_successful');
		
		// Return values.
		$this->_model->setReturnReference('get_api_class_clients', $this->_cm_api_clients);
		$this->_cm_api_clients->setReturnReference('get_lists', $result);

		$result->setReturnValue('__get', $http_status_code, array('http_status_code'));
		$result->setReturnValue('__get', $response, array('response'));
		$result->setReturnValue('was_successful', TRUE);
		
		// Tests.
		$this->assertIdentical($return, $this->_subject->get_client_lists($client_id, FALSE));
	}


	public function test__get_client_lists__api_error()
	{
		// Dummy values.
		$client_id			= 'a58ee1d3039b8bec838e6d1482a8a966';
		$http_status_code	= '401';
		$response			= $this->_convert_array_to_object(array('Code' => '100', 'Message' => 'Invalid API Key'));
		$result				= $this->_get_mock('cm_api_result');

		// Return values.
		$this->_model->setReturnReference('get_api_class_clients', $this->_cm_api_clients);
		$this->_cm_api_clients->setReturnReference('get_lists', $result);
		$result->setReturnValue('__get', $http_status_code, array('http_status_code'));
		$result->setReturnValue('__get', $response, array('response'));
		$result->setReturnValue('was_successful', FALSE);

		// Tests.
		$this->expectException(new Campaigner_api_exception($response->Message, $response->Code));
		$this->_subject->get_client_lists($client_id);
	}


	public function test__get_client_lists__no_connector()
	{
		// Dummy values.
		$client_id	= 'abc123';
		$message	= 'No API connector.';

		// Expectations.
		$this->_model->expectOnce('get_api_class_clients');
		$this->_cm_api_clients->expectNever('get_lists');
		
		// Return values.
		$this->_model->setReturnValue('get_api_class_clients', FALSE);
		$this->_ee->lang->setReturnValue('line', $message, array('error_no_api_connector'));
		
		// Tests.
		$this->expectException(new Campaigner_exception($message));
		$this->_subject->get_client_lists($client_id);
	}


	public function test__get_list_fields__success()
	{
		// Dummy values.
		$list_id = 'a58ee1d3039b8bec838e6d1482a8a966';

		$response = array(
			$this->_convert_array_to_object(array('FieldName' => 'website', 'Key' => '[website]', 'DataType' => 'Text', 'FieldOptions' => array())),
			$this->_convert_array_to_object(array('FieldName' => 'age', 'Key' => '[age]', 'DataType' => 'Number', 'FieldOptions' => array())),
			$this->_convert_array_to_object(array('FieldName' => 'subscription_date', 'Key' => '[subscriptiondate]', 'DataType' => 'Date', 'FieldOptions' => array())),
			$this->_convert_array_to_object(array('FieldName' => 'newsletterformat', 'Key' => '[newsletterformat]', 'DataType' => 'MultiSelectOne', 'FieldOptions' => array('HTML', 'Text'))),
		);

		$result = $this->_get_mock('cm_api_result');

		// Expectations.
		$this->_model->expectOnce('get_api_class_lists', array($list_id));
		$this->_cm_api_lists->expectOnce('get_custom_fields');
		$result->expectOnce('was_successful');

		// Return values.
		$this->_model->setReturnReference('get_api_class_lists', $this->_cm_api_lists);
		$this->_cm_api_lists->setReturnValue('get_custom_fields', $result);
		$result->setReturnValue('__get', $response, array('response'));
		$result->setReturnValue('was_successful', TRUE);

		// Tests.
		$return = array(
			new Campaigner_custom_field(array('cm_key' => $response[0]->Key, 'label' => $response[0]->FieldName)),
			new Campaigner_custom_field(array('cm_key' => $response[1]->Key, 'label' => $response[1]->FieldName)),
			new Campaigner_custom_field(array('cm_key' => $response[2]->Key, 'label' => $response[2]->FieldName)),
			new Campaigner_custom_field(array('cm_key' => $response[3]->Key, 'label' => $response[3]->FieldName))
		);

		$this->assertIdentical($return, $this->_subject->get_list_fields($list_id));
	}


	public function test__get_list_fields__failure()
	{
		// Dummy values.
		$error_code		= 911;
		$error_message	= 'Unable to retrieve list fields.';
		$list_id		= 'a58ee1d3039b8bec838e6d1482a8a966';

		$response = $this->_convert_array_to_object(array(
			'Code'		=> $error_code,
			'Message'	=> $error_message
		));

		$result = $this->_get_mock('cm_api_result');

		// Expectations.
		$this->_model->expectOnce('get_api_class_lists', array($list_id));
		$this->_cm_api_lists->expectOnce('get_custom_fields');
		$result->expectOnce('was_successful');

		// Return values.
		$this->_model->setReturnReference('get_api_class_lists', $this->_cm_api_lists);
		$this->_cm_api_lists->setReturnValue('get_custom_fields', $result);
		$result->setReturnValue('__get', $response, array('response'));
		$result->setReturnValue('was_successful', FALSE);

		// Tests.
		$this->expectException(new Campaigner_api_exception($error_message, $error_code));
		$this->_subject->get_list_fields($list_id);
	}


	public function test__get_list_fields__invalid_list_id()
	{
		// Dummy values.
		$list_id = '';
		$message = 'Missing or invalid list ID.';

		// Expectations.
		$this->_model->expectNever('get_api_class_lists');
		$this->_cm_api_lists->expectNever('get_custom_fields');

		// Return values.
		$this->_ee->lang->setReturnValue('line', $message, array('error_missing_or_invalid_list_id'));

		// Run the tests..
		$this->expectException(new Campaigner_exception($message));
		$this->_subject->get_list_fields($list_id);
	}


	public function test__get_list_fields__no_connector()
	{
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';
		$message	= 'No API connector.';
		
		// Return values.
		$this->_ee->lang->setReturnValue('line', $message);
		$this->_model->setReturnValue('get_api_class_lists', FALSE);

		// Expectations.
		$this->_ee->lang->expectOnce('line', array('error_no_api_connector'));

		// Tests.
		$this->expectException(new Campaigner_exception($message));
		$this->_subject->get_list_fields($list_id);
	}


	public function test__add_list_subscriber__success()
	{
		$list_id			= 'a58ee1d3039b8bec838e6d1482a8a966';
		$http_status_code	= '201';
		$resubscribe		= TRUE;
		$result				= $this->_get_mock('cm_api_result');

		$subscriber = new Campaigner_subscriber(array(
			'email'		=> 'me@here.com',
			'name'		=> 'Adam Adamson',
			'custom_data'	=> array(
				new Campaigner_subscriber_custom_data(array(
					'key'	=> '[location]',
					'value'	=> 'Caerphilly'
				)),
				new Campaigner_subscriber_custom_data(array(
					'key'	=> '[religion]',
					'value'	=> 'Can see church spire from bedroom'
				))
			)
		));

		$subscriber_data = array(
			'EmailAddress'		=> $subscriber->get_email(),
			'Name'				=> $subscriber->get_name(),
			'Resubscribe'		=> $resubscribe
		);

		$subscriber_data['CustomFields'] = array();

		foreach ($subscriber->get_custom_data() AS $custom_data)
		{
			$subscriber_data['CustomFields'][] = array(
				'Key'	=> $custom_data->get_key(),
				'Value'	=> $custom_data->get_value()
			);
		}

		$this->_model->expectOnce('get_api_class_subscribers', array($list_id));
		$this->_cm_api_subscribers->expectOnce('add', array($subscriber_data));

		$result->expectOnce('was_successful');

		$this->_model->setReturnReference('get_api_class_subscribers', $this->_cm_api_subscribers);
		$this->_cm_api_subscribers->setReturnReference('add', $result);
		$result->setReturnValue('__get', $http_status_code, array('http_status_code'));
		$result->setReturnValue('was_successful', TRUE);

		// Tests.
		$this->_subject->add_list_subscriber($list_id, $subscriber, $resubscribe);

	}


	public function test__add_list_subscriber__api_error()
	{
		// Dummy values.
		$list_id			= 'a58ee1d3039b8bec838e6d1482a8a966';
		$http_status_code	= '401';
		$response			= $this->_convert_array_to_object(array('Code' => '100', 'Message' => 'Invalid API Key'));
		$result				= $this->_get_mock('cm_api_result');
		$subscriber			= new Campaigner_subscriber();

		// Return values.
		$this->_model->setReturnReference('get_api_class_subscribers', $this->_cm_api_subscribers);
		$this->_cm_api_subscribers->setReturnReference('add', $result);
		$result->setReturnValue('__get', $http_status_code, array('http_status_code'));
		$result->setReturnValue('__get', $response, array('response'));
		$result->setReturnValue('was_successful', FALSE);

		// Tests.
		$this->expectException(new Campaigner_api_exception($response->Message, $response->Code));
		$this->_subject->add_list_subscriber($list_id, $subscriber, FALSE);
	}


	public function test__add_list_subscriber__no_connector()
	{
		$list_id		= 'a58ee1d3039b8bec838e6d1482a8a966';
		$subscriber		= new Campaigner_subscriber();
		$message		= 'ERROR_MESSAGE';
		
		// Return values.
		$this->_ee->lang->setReturnValue('line', $message);
		$this->_model->setReturnValue('get_api_class_subscribers', FALSE);

		// Expectations.
		$this->_ee->lang->expectOnce('line', array('error_no_api_connector'));

		// Tests.
		$this->expectException(new Campaigner_exception($message));
		$this->_subject->add_list_subscriber($list_id, $subscriber, FALSE);
	}


	public function test__get_is_subscribed__subscribed()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values
		$email		= 'me@here.com';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';

		// Retrieve the API class.
		$model->expectOnce('get_api_class_subscribers', array($list_id));
		$model->setReturnReference('get_api_class_subscribers', $api_class);

		// Attempt to retrieve the subscriber from Campaign Monitor.
		$result = $this->_get_mock('cm_api_result');
		$result->expectOnce('was_successful');
		$result->setReturnValue('was_successful', TRUE);

		$response = $this->_convert_array_to_object(array(
			'EmailAddress'	=> $email,
			'Name'			=> 'John Doe',
			'Date'			=> '2011-02-19 09:00:00',
			'State'			=> 'Active',
		));

		$result->setReturnValue('__get', $response, array('response'));

		$api_class->expectOnce('get', array($email));
		$api_class->setReturnReference('get', $result);

		// Run the tests.
		$this->assertIdentical(TRUE, $this->_subject->get_is_subscribed($list_id, $email));
	}


	public function test__get_is_subscribed__not_subscribed()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values
		$email		= 'me@here.com';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';

		// Retrieve the API class.
		$model->setReturnReference('get_api_class_subscribers', $api_class);

		// Attempt to retrieve the subscriber from Campaign Monitor.
		$result = $this->_get_mock('cm_api_result');
		$result->setReturnValue('was_successful', FALSE);	// This means the member is not subscribed.
		$api_class->setReturnReference('get', $result);

		// Run the tests.
		$this->assertIdentical(FALSE, $this->_subject->get_is_subscribed($list_id, $email));
	}


	public function test__get_is_subscribed__not_active()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values
		$email		= 'me@here.com';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';

		// Retrieve the API class.
		$model->expectOnce('get_api_class_subscribers', array($list_id));
		$model->setReturnReference('get_api_class_subscribers', $api_class);

		// Attempt to retrieve the subscriber from Campaign Monitor.
		$result = $this->_get_mock('cm_api_result');
		$result->expectOnce('was_successful');
		$result->setReturnValue('was_successful', TRUE);

		$response = $this->_convert_array_to_object(array(
			'EmailAddress'	=> $email,
			'Name'			=> 'John Doe',
			'Date'			=> '2011-02-19 09:00:00',
			'State'			=> 'Pending',
		));

		$result->setReturnValue('__get', $response, array('response'));

		$api_class->expectOnce('get', array($email));
		$api_class->setReturnReference('get', $result);

		// Run the tests.
		$this->assertIdentical(FALSE, $this->_subject->get_is_subscribed($list_id, $email));
	}


	public function test__get_is_subscribed__invalid_list_id()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values
		$email		= 'me@here.com';
		$list_id	= '';

		// Expectations.
		$model->expectNever('get_api_class_subscribers');
		$api_class->expectNever('get');

		// Run the tests.
		$this->assertIdentical(FALSE, $this->_subject->get_is_subscribed($list_id, $email));
	}


	public function test__get_is_subscribed__invalid_email()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values
		$email		= '';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';

		// Expectations.
		$model->expectNever('get_api_class_subscribers');
		$api_class->expectNever('get');

		// Run the tests.
		$this->assertIdentical(FALSE, $this->_subject->get_is_subscribed($list_id, $email));
	}


	public function test__get_is_subscribed__no_connector()
	{
		// Dummy valiues.
		$email		= 'me@here.com';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';
		$message	= 'Missing API connector.';
		
		// Return values.
		$this->_ee->lang->setReturnValue('line', $message);
		$this->_model->setReturnValue('get_api_class_subscribers', FALSE);

		// Expectations.
		$this->_ee->lang->expectOnce('line', array('error_no_api_connector'));

		// Tests.
		$this->expectException(new Campaigner_exception($message));
		$this->_subject->get_is_subscribed($list_id, $email);
	}


	public function test__remove_list_subscriber__success()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values.
		$email		= 'me@here.com';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';

		// Retrieve the API class.
		$model->expectOnce('get_api_class_subscribers', array($list_id));
		$model->setReturnReference('get_api_class_subscribers', $api_class);

		// Unsubscribe the member.
		$result = $this->_get_mock('cm_api_result');
		$result->expectOnce('was_successful');
		$result->setReturnValue('was_successful', TRUE);

		$api_class->expectOnce('unsubscribe', array($email));
		$api_class->setReturnReference('unsubscribe', $result);

		// Run the tests.
		$this->_subject->remove_list_subscriber($list_id, $email);
	}
		

	public function test__remove_list_subscriber__failure()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values.
		$email			= 'me@here.com';
		$error_message	= 'Unable to remove list subscriber.';
		$error_code		= 911;
		$list_id		= 'a58ee1d3039b8bec838e6d1482a8a966';

		// Expectations and return values.
		$model->setReturnReference('get_api_class_subscribers', $api_class);

		$response = $this->_convert_array_to_object(array(
			'Code'		=> $error_code,
			'Message'	=> $error_message
		));

		$result = $this->_get_mock('cm_api_result');
		$result->setReturnValue('was_successful', FALSE);
		$result->setReturnValue('__get', $response, array('response'));

		$api_class->setReturnReference('unsubscribe', $result);

		// Run the tests.
		$this->expectException(new Campaigner_api_exception($error_message, $error_code));
		$this->assertIdentical(FALSE, $this->_subject->remove_list_subscriber($list_id, $email));
	}


	public function test__remove_list_subscriber__invalid_list_id()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values.
		$email		= 'me@here.com';
		$list_id	= '';

		// Expectations.
		$model->expectNever('get_api_class_subscribers');
		$api_class->expectNever('unsubscribe');

		// Run the tests.
		$this->assertIdentical(FALSE, $this->_subject->remove_list_subscriber($list_id, $email));
	}


	public function test__remove_list_subscriber__invalid_email()
	{
		// Shortcuts.
		$api_class	= $this->_cm_api_subscribers;
		$model		= $this->_model;

		// Dummy values.
		$email		= '';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';

		// Expectations.
		$model->expectNever('get_api_class_subscribers');
		$api_class->expectNever('unsubscribe');

		// Run the tests.
		$this->assertIdentical(FALSE, $this->_subject->remove_list_subscriber($list_id, $email));
	}


	public function test__remove_list_subscriber__no_connector()
	{
		$email		= 'me@here.com';
		$list_id	= 'a58ee1d3039b8bec838e6d1482a8a966';
		$message	= 'ERROR_MESSAGE';
		
		// Return values.
		$this->_ee->lang->setReturnValue('line', $message);
		$this->_model->setReturnValue('get_api_class_subscribers', FALSE);

		// Expectations.
		$this->_ee->lang->expectOnce('line', array('error_no_api_connector'));

		// Tests.
		$this->expectException(new Campaigner_exception($message));
		$this->_subject->remove_list_subscriber($list_id, $email);
	}


}


/* End of file		: test_campaigner_api_connector.php */
/* File location	: third_party/campaigner/tests/test_campaigner_api_connector.php */