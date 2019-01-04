<?php

use CRM_Replytoprimary_ExtensionUtil as E;

class CRM_Replytoprimary_Utils {

  /**
   * @param $email Can be a single email, or formatted From
   */
  public static function isValidFrom($email) {
    if ($from_email_only = CRM_Utils_Mail::pluckEmailFromHeader($email)) {
      $email = $from_email_only;
    }

    $domain_emails = CRM_Core_BAO_Email::domainEmails();

    foreach ($domain_emails as $e) {
      $t = CRM_Utils_Mail::pluckEmailFromHeader($e);
      if ($t == $email) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns the default email for the current domain.
   */
  public static function getDefaultDomainEmail() {
    // This function oddly returns an array of one element.
    $t = CRM_Core_BAO_Domain::getNameAndEmail(FALSE, TRUE);
    return array_pop($t);
  }

}
