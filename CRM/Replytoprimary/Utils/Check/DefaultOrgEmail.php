<?php

use CRM_Replytoprimary_ExtensionUtil as E;

class CRM_Replytoprimary_Utils_Check_DefaultOrgEmail {

  /**
   * Check that the default domain contact has an email address.
   *
   * @see replytoprimary_civicrm_check()
   */
  static public function check(&$messages) {
    $default_from = CRM_Replytoprimary_Utils::getDefaultDomainEmail();

    // Get the email for the default org contact
    // This is what is used for system notifications.
    $domain_id = CRM_Core_Config::domainID();

    $contact_id = CRM_Core_DAO::singleValueQuery('SELECT contact_id FROM civicrm_domain WHERE id = %1', [
      1 => [$domain_id, 'Positive'],
    ]);

    $primary_email = _replytoprimary_get_primary($contact_id);

    if ($primary_email) {
      $messages[] = new CRM_Utils_Check_Message(
        'replytoprimary_defaultorg',
        E::ts('The default "reply-to" for system emails (ex: Scheduled Reminders) will be: %1; the default "from" will be: %2', [
          1 => $primary_email,
          2 => htmlspecialchars($default_from),
        ]),
        E::ts('Reply To Primary - Default Email Address'),
        \Psr\Log\LogLevel::INFO,
        'fa-check'
      );
    }
    else {
      $messages[] = new CRM_Utils_Check_Message(
        'replytoprimary_defaultorg',
        E::ts('The default organisation contact record does not have an email address. Please set one, and set it as the primary email.'),
        E::ts('Reply To Primary - Default Email Address'),
        \Psr\Log\LogLevel::WARNING,
        'fa-check'
      );
    }
  }

}
