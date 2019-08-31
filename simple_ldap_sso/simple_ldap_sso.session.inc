<?php

/**
 * @file
 * Session include for loading session ids from LDAP.
 */

include_once BACKDROP_ROOT . '/core/includes/common.inc';
require_once BACKDROP_ROOT . '/core/includes/session.inc';
require_once 'simple_ldap_sso.inc';

simple_ldap_sso_detect_sid();
