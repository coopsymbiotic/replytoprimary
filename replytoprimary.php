<?php

require_once 'replytoprimary.civix.php';
use CRM_Replytoprimary_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function replytoprimary_civicrm_config(&$config) {
  _replytoprimary_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function replytoprimary_civicrm_xmlMenu(&$files) {
  _replytoprimary_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function replytoprimary_civicrm_install() {
  _replytoprimary_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function replytoprimary_civicrm_postInstall() {
  _replytoprimary_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function replytoprimary_civicrm_uninstall() {
  _replytoprimary_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function replytoprimary_civicrm_enable() {
  _replytoprimary_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function replytoprimary_civicrm_disable() {
  _replytoprimary_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function replytoprimary_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _replytoprimary_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function replytoprimary_civicrm_managed(&$entities) {
  _replytoprimary_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function replytoprimary_civicrm_caseTypes(&$caseTypes) {
  _replytoprimary_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function replytoprimary_civicrm_angularModules(&$angularModules) {
  _replytoprimary_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function replytoprimary_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _replytoprimary_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function replytoprimary_civicrm_entityTypes(&$entityTypes) {
  _replytoprimary_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_buildForm().
 */
function replytoprimary_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Contact_Form_Task_Email') {
    $session = CRM_Core_Session::singleton();
    $contact_id = $session->get('userID');
    $primary_email = _replytoprimary_get_primary($contact_id);

    // It's unlikely a contact sending emails would not have an email,
    // since Drupal/WP users must have an email, and it gets synched to their contact.
    if ($primary_email) {
      CRM_Core_Session::setStatus(ts("The email's Reply-To will be set to %1", [1 => $primary_email]));
    }
  }
}

/**
 * Implements hook_civicrm_alterMailParams().
 *
 */
function replytoprimary_civicrm_alterMailParams(&$params, $context) {

  // If the contact has a primary email, and this is a Send Email activity,
  // use their Primary Email as the Reply To header.

  // NB: if the contact has multiple addresses, warn about which email is going to be used?
  // ex: if a personal email address become their primary email.

  if (! in_array($context, ['messageTemplate', 'singleEmail'])) {
    return;
  }

  $session = CRM_Core_Session::singleton();
  $contact_id = $session->get('userID');

  // Scheduled Reminders are usually sent by cron
  // so always ignore the logged-in user.
  if (CRM_Utils_Array::value('groupName', $params) == 'Scheduled Reminder Sender') {
    $contact_id = NULL;
  }

  // Most probably a system email
  // ex: password reset sent through CiviCRM (civicrmmailer).
  if (empty($contact_id)) {
    $params['from'] = CRM_Replytoprimary_Utils::getDefaultDomainEmail();

    // Set the replyTo to the default org contact
    $domain_id = CRM_Core_Config::domainID();

    $contact_id = CRM_Core_DAO::singleValueQuery('SELECT contact_id FROM civicrm_domain WHERE id = %1', [
      1 => [$domain_id, 'Positive'],
    ]);
  }

  // Fetch emails for the currently logged in contact
  $primary_email = _replytoprimary_get_primary($contact_id);

  if ($primary_email) {
    $params['replyTo'] = $primary_email;
  }

  // Validate if the 'from' email is valid.
  if (!CRM_Replytoprimary_Utils::isValidFrom($params['from'])) {
    $params['from'] = CRM_Replytoprimary_Utils::getDefaultDomainEmail();
  }
}

/**
 * Returns the primary email address, if available. Otherwise returns the
 * first email found.
 */
function _replytoprimary_get_primary($contact_id) {
  // Fetch emails for the currently logged in contact
  $result = civicrm_api3('Email', 'get', [
    'contact_id' => $contact_id,
    'is_primary' => 1,
    'sequential' => 1,
  ]);

  if (!empty($result['values'])) {
    // There should always be only one primary email
    return $result['values'][0]['email'];
  }

  // Return the first email found
  $result = civicrm_api3('Email', 'get', [
    'contact_id' => $contact_id,
    'sequential' => 1,
  ]);

  if (!empty($result['values'])) {
    return $result['values'][0]['email'];
  }

  return NULL;
}

/**
 * Implements hook_civicrm_check().
 */
function replytoprimary_civicrm_check(&$messages) {
  CRM_Replytoprimary_Utils_Check_DefaultOrgEmail::check($messages);
}
