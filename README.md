# Reply To Primary

This extension addresses the rather obscure but common use-case where:

* We want CiviCRM to send out emails
* but we are not allowed to send email using the organisation's or the person's email (ex: gmail.com address, or some other domain for which we do not have permission through SPF/DKIM to send email)
* and so we want to use a generic from (`no-reply@example.org`) and set a reply-to to the address of the person who should receive responses (ex: `example@gmail.com`).

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM Latest

## Installation

Install as a regular extension.

## Usage

* As a logged-in user: make sure the contact record has (preferably) a primary email address. Otherwise, the first email address will be used.
* For system notifications: make sure that the organisation record linked to the domain (usually Contact ID 1 or 2) has an email address. The "System Status" will warn otherwise.
