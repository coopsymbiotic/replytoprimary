<?php

use CRM_Replytoprimary_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Replytoprimary_ScheduledReminderTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  // for callAPISuccess
  use \Civi\Test\Api3TestTrait;

  /**
   * @var CiviMailUtils
   */
  public $mut;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();

    $this->mut = new CiviMailUtils($this, TRUE);

    $this->fixtures['sched_contact_bday_anniv'] = array(
      'name' => 'sched_contact_bday_anniv',
      'title' => 'sched_contact_bday_anniv',
      'absolute_date' => '',
      'body_html' => '<p>happy birthday!</p>',
      'body_text' => 'happy birthday!',
      'end_action' => '',
      'end_date' => '',
      'end_frequency_interval' => '',
      'end_frequency_unit' => '',
      'entity_status' => 2,
      'entity_value' => 'birth_date',
      'group_id' => '',
      'is_active' => 1,
      'is_repeat' => '0',
      'mapping_id' => 6,
      'msg_template_id' => '',
      'recipient' => '',
      'recipient_listing' => '',
      'recipient_manual' => '',
      'record_activity' => 1,
      'repetition_frequency_interval' => '',
      'repetition_frequency_unit' => '',
      'start_action_condition' => 'before',
      'start_action_date' => 'date_field',
      'start_action_offset' => '1',
      'start_action_unit' => 'day',
      'subject' => 'subject sched_contact_bday_anniv',
    );
    $this->fixtures['phonecall'] = array(
      'status_id' => 1,
      'activity_type_id' => 2,
      'activity_date_time' => '20120615100000',
      'is_current_revision' => 1,
      'is_deleted' => 0,
    );
    $this->fixtures['contact'] = array(
      'is_deceased' => 0,
      'contact_type' => 'Individual',
      'email' => 'test-member@example.com',
      'gender_id' => 'Female',
      'first_name' => 'Churmondleia',
      'last_name' => 'Ōtākou',
    );
    $this->fixtures['org_email'] = array(
      'contact_id' => 1,
      'email' => 'org-email-primary@example.org',
      'location_type_id' => 1,
      'is_primary' => 1,
    );

    $this->fixtures['sched_activity_1day'] = array(
      'name' => 'One_Day_Phone_Call_Notice',
      'title' => 'One Day Phone Call Notice',
      'limit_to' => '1',
      'absolute_date' => NULL,
      'body_html' => '<p>1-Day (non-repeating) (for {activity.subject})</p>',
      'body_text' => '1-Day (non-repeating) (for {activity.subject})',
      'end_action' => NULL,
      'end_date' => NULL,
      'end_frequency_interval' => NULL,
      'end_frequency_unit' => NULL,
      'entity_status' => '1',
      'entity_value' => '2',
      'group_id' => NULL,
      'is_active' => '1',
      'is_repeat' => '0',
      'mapping_id' => '1',
      'msg_template_id' => NULL,
      'recipient' => '2',
      'recipient_listing' => NULL,
      'recipient_manual' => NULL,
      'record_activity' => 1,
      'repetition_frequency_interval' => NULL,
      'repetition_frequency_unit' => NULL,
      'start_action_condition' => 'before',
      'start_action_date' => 'activity_date_time',
      'start_action_offset' => '1',
      'start_action_unit' => 'day',
      'subject' => '1-Day (non-repeating) (about {activity.activity_type})',
    );

  }

  public function tearDown() {
    parent::tearDown();

    $this->mut->clearMessages();
    $this->mut->stop();
    unset($this->mut);
    $this->quickCleanup(array(
      'civicrm_action_schedule',
      'civicrm_action_log',
      'civicrm_membership',
      'civicrm_participant',
      'civicrm_event',
      'civicrm_email',
      'civicrm_campaign',
    ));
  }

  /**
   * Based on:
   * tests/phpunit/CRM/Core/BAO/ActionScheduleTest.php
   *
   * This generates a single mailing through the scheduled-reminder
   * system (using an activity-reminder as a baseline) and
   * checks that the resulting message satisfies various
   * regular expressions.
   *
   * @param array $schedule
   *   Values to set/override in the schedule.
   *   Ex: array('subject' => 'Hello, {contact.first_name}!').
   * @param array $patterns
   *   A list of regexes to compare with the actual email.
   *   Ex: array('subject' => '/^Hello, Alice!/').
   *   Keys: subject, body_text, body_html, from_name, from_email.
   * @dataProvider mailerExamples
   */
  public function testMailer($schedule, $patterns) {
    $actionSchedule = array_merge($this->fixtures['sched_activity_1day'], $schedule);
    $actionScheduleDao = CRM_Core_BAO_ActionSchedule::add($actionSchedule);
    $this->assertTrue(is_numeric($actionScheduleDao->id));

    $this->callAPISuccess('email', 'create', $this->fixtures['org_email']);

    $activity = $this->createTestObject('CRM_Activity_DAO_Activity', $this->fixtures['phonecall']);
    $this->assertTrue(is_numeric($activity->id));
    $contact = $this->callAPISuccess('contact', 'create', $this->fixtures['contact']);
    $activity->save();

    $source['contact_id'] = $contact['id'];
    $source['activity_id'] = $activity->id;
    $source['record_type_id'] = 2;
    $activityContact = $this->createTestObject('CRM_Activity_DAO_ActivityContact', $source);
    $activityContact->save();

    CRM_Utils_Time::setTime('2012-06-14 15:00:00');
    $this->callAPISuccess('job', 'send_reminder', []);
    $this->mut->assertRecipients(array(array('test-member@example.com')));
    foreach ($this->mut->getAllMessages('ezc') as $message) {
      /** @var ezcMail $message */
      $reply_to = $message->getHeader('Reply-To');
      $this->assertEquals('org-email-primary@example.org', $reply_to);
    }
    $this->mut->clearMessages();
  }

  /**
   * Based on:
   * phpunit/CRM/Core/BAO/ActionScheduleTest.php
   *
   * TODO: cleanup, we are not using this?
   */
  public function mailerExamples() {
    $cases = array();

    // Some tokens - short as subject has 128char limit in DB.
    $someTokensTmpl = implode(';;', array(
      '{contact.display_name}', // basic contact token
      '{contact.gender}', // funny legacy contact token
      '{contact.gender_id}', // funny legacy contact token
      '{domain.name}', // domain token
      '{activity.activity_type}', // action-scheduler token
    ));

    // In this example, we customize the from address.
    $cases[1] = array(
      // Schedule definition.
      array(
        'from_name' => 'Bob',
        'from_email' => 'bob@example.org',
      ),
      // Assertions (regex).
      array(
        'from_name' => "/^Bob\$/",
        'from_email' => "/^bob@example.org\$/",
      ),                                                                                                                                                                                         [179/591]
    );

    // In this example, we autoconvert HTML to text
    $cases[2] = array(
      // Schedule definition.
      array(
        'body_html' => '<p>Hello &amp; stuff.</p>',
        'body_text' => '',
      ),
      // Assertions (regex).
      array(
        'body_html' => '/^' . preg_quote('<p>Hello &amp; stuff.</p>', '/') . '/',
        'body_text' => '/^' . preg_quote('Hello & stuff.', '/') . '/',
      ),
    );

    // In this example, we autoconvert HTML to text
    $cases[3] = array(
      // Schedule definition.
      array(
        'body_html' => '',
        'body_text' => 'Hello world',
      ),
      // Assertions (regex).
      array(
        'body_html' => '/^--UNDEFINED--$/',
        'body_text' => '/^Hello world$/',
      ),
    );

    return $cases;
  }

  /**
   * Copied from:
   * tests/phpunit/CRM/Core/BAO/ActionScheduleTest.php
   *
   * This is a wrapper for CRM_Core_DAO::createTestObject which tracks
   * created entities and provides for brainless cleanup.
   *
   * @see CRM_Core_DAO::createTestObject
   *
   * @param $daoName
   * @param array $params
   * @param int $numObjects
   * @param bool $createOnly
   *
   * @return array|NULL|object
   */
  public function createTestObject($daoName, $params = array(), $numObjects = 1, $createOnly = FALSE) {
    $objects = CRM_Core_DAO::createTestObject($daoName, $params, $numObjects, $createOnly);
    if (is_array($objects)) {
      $this->registerTestObjects($objects);
    }
    else {
      $this->registerTestObjects(array($objects));
    }
    return $objects;
  }

  /**
   * @param array $objects
   *   DAO or BAO objects.
   */
  public function registerTestObjects($objects) {
    //if (is_object($objects)) {
    //  $objects = array($objects);
    //}
    foreach ($objects as $object) {
      $daoName = preg_replace('/_BAO_/', '_DAO_', get_class($object));
      $this->_testObjects[$daoName][] = $object->id;
    }
  }
  /**
   * Copied from:
   * tests/phpunit/CiviTest/CiviUnitTestCase.php
   * FIXME: how can we extend?
   *
   * Quick clean by emptying tables created for the test.
   *
   * @param array $tablesToTruncate
   * @param bool $dropCustomValueTables
   * @throws \Exception
   */
  public function quickCleanup($tablesToTruncate, $dropCustomValueTables = FALSE) {
    if ($dropCustomValueTables) {
      $optionGroupResult = CRM_Core_DAO::executeQuery('SELECT option_group_id FROM civicrm_custom_field');
      while ($optionGroupResult->fetch()) {
        if (!empty($optionGroupResult->option_group_id)) {
          CRM_Core_DAO::executeQuery('DELETE FROM civicrm_option_group WHERE id = ' . $optionGroupResult->option_group_id);
        }
      }
      $tablesToTruncate[] = 'civicrm_custom_group';
      $tablesToTruncate[] = 'civicrm_custom_field';
    }

    CRM_Core_DAO::executeQuery("SET FOREIGN_KEY_CHECKS = 0;");
    foreach ($tablesToTruncate as $table) {
      $sql = "TRUNCATE TABLE $table";
      CRM_Core_DAO::executeQuery($sql);
    }
    CRM_Core_DAO::executeQuery("SET FOREIGN_KEY_CHECKS = 1;");

    if ($dropCustomValueTables) {
      $dbName = self::getDBName();
      $query = "
SELECT TABLE_NAME as tableName
FROM   INFORMATION_SCHEMA.TABLES
WHERE  TABLE_SCHEMA = '{$dbName}'
AND    ( TABLE_NAME LIKE 'civicrm_value_%' )
";

      $tableDAO = CRM_Core_DAO::executeQuery($query);
      while ($tableDAO->fetch()) {
        $sql = "DROP TABLE {$tableDAO->tableName}";
        CRM_Core_DAO::executeQuery($sql);
      }
    }
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testWellFormedVersion() {
    $this->assertRegExp('/^([0-9\.]|alpha|beta)*$/', \CRM_Utils_System::version());
  }

  /**
   * Example: Test that we're using a fake CMS.
   */
  public function testWellFormedUF() {
    $this->assertEquals('UnitTests', CIVICRM_UF);
  }

}
