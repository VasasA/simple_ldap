<?php

/**
 * @file
 * Simple LDAP SSO Admin pages.
 */

/**
 * Admin form for Single Sign On.
 */
function simple_ldap_sso_admin() {
  $config = config('simple_ldap.settings');
  $config_sso = config('simple_ldap_sso.settings');
  $form['#config'] = 'simple_ldap_sso.settings';
  $session_inc_path = backdrop_get_path('module', 'simple_ldap_sso') . '/simple_ldap_sso.session.inc';
  $message = t('You must set the session_inc variable to Simple LDAP SSO’s session include file.');
  $message .= t(' Insert the following line into the @backdrop_root/settings.php file!', array('@backdrop_root' => BACKDROP_ROOT));
  $message .= '<br>$settings["session_inc"] = "' . $session_inc_path . '";';
  if (settings_get('session_inc', 'core/includes/session.inc') != $session_inc_path) {
    backdrop_set_message($message, 'error');
  }

  $form['credentials'] = array(
    '#access' => $config->get('simple_ldap_readonly'),
    '#type' => 'fieldset',
    '#title' => t('Read/Write LDAP Credentials'),
    '#description' => t('Since the Simple LDAP module is currently in Read Only mode for this site, you must specify separate read/write credentials to connect to LDAP with.'),
  );
  $form['credentials']['simple_ldap_sso_binddn'] = array(
    '#type' => 'textfield',
    '#title' => t('Bind DN'),
    '#default_value' => $config_sso->get('simple_ldap_sso_binddn'),
    '#required' => $config->get('simple_ldap_readonly'),
  );
  $form['credentials']['simple_ldap_sso_bindpw'] = array(
    '#type' => 'password',
    '#title' => t('Bind password'),
    '#default_value' => $config_sso->get('simple_ldap_sso_bindpw'),
    '#required' => $config->get('simple_ldap_readonly'),
    // Set the value on the input so that it won't get wiped out. This will mean
    // that someone could view source and get the value, but if someone is
    // logged in as a user that can access this form, you've got worse problems.
    '#attributes' => array('value' => array($config_sso->get('simple_ldap_sso_bindpw', ''))),
  );

  $form['basic'] = array(
    '#type' => 'fieldset',
    '#title' => t('Basic Settings'),
  );
  $form['basic']['simple_ldap_sso_encryption_key'] = array(
    '#type' => 'password',
    '#title' => t('Shared Encryption Key'),
    '#description' => t('This is the encryption key used to encrypt and decrypt the cookie value used to assist the single-sign-on. It must be the same accross all sites.'),
    // Set the value on the input so that it won't get wiped out. This will mean
    // that someone could view source and get the value, but if someone is
    // logged in as a user that can access this form, you've got worse problems.
    '#attributes' => array('value' => array($config_sso->get('simple_ldap_sso_encryption_key', ''))),
  );

  $form['basic']['simple_ldap_sso_attribute_sid'] = array(
    '#type' => 'select',
    '#title' => t('LDAP Session ID Attribute'),
    '#options' => simple_ldap_sso_get_attribute_options(),
    '#default_value' => $config_sso->get('simple_ldap_sso_attribute_sid'),
    '#required' => TRUE,
    '#description' => t('Specify the LDAP attribute that will store the session ID.'),
  );

  $form['advanced'] = array(
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#title' => t('Advanced Settings'),
  );

  $options = SimpleLdap::hashes();
  unset($options['none']);
  $form['advanced']['simple_ldap_sso_hashing_algorithm'] = array(
    '#type' => 'select',
    '#title' => t('Session ID Hashing Algorithm'),
    '#description' => t('Choose the algorithm that will be used to hash the session ID stored on LDAP.'),
    '#options' => $options,
    '#default_value' => $config_sso->get('simple_ldap_sso_hashing_algorithm', 'sha'),
  );

  $options = range(0, 20);
  $options[0] = t('Off. Not Recommended.');
  $form['advanced']['simple_ldap_sso_flood_limit'] = array(
    '#type' => 'select',
    '#title' => t('Failed SSO Limit'),
    '#description' => t('The limit of failed SSO attempts a user can make from a single IP.'),
    '#options' => $options,
    '#default_value' => $config_sso->get('simple_ldap_sso_flood_limit', 3),
  );

  $form['advanced']['simple_ldap_sso_flood_window'] = array(
    '#type' => 'select',
    '#title' => t('Failed SSO Window'),
    '#description' => t('The window of time in which to enforce the above limit. Higher is safer. Lower is more tolerant.'),
    '#options' => array(
      60 => t('One minute'),
      120 => t('Two minutes'),
      300 => t('Five minutes'),
      600 => t('Ten minutes'),
      900 => t('Fifteen minutes'),
      1800 => t('Thirty minutes'),
      3600 => t('One hour'),
      7200 => t('Two hours'),
      1800 => t('Five hours'),
      86400 => t('One day'),
      604800 => t('One week'),
    ),
    '#default_value' => $config_sso->get('simple_ldap_sso_flood_window', 3600),
  );

  $form['advanced']['simple_ldap_sso_test_user_cn'] = array(
    '#type' => 'textfield',
    '#title' => t('CN of testing user'),
    '#default_value' => $config_sso->get('simple_ldap_sso_test_user_cn'),
    '#description' => t('This and the following password are used by the <a href="!url">self test system</a>.', array('!url' => url('admin/config/development/testing'))),
  );

  $form['advanced']['simple_ldap_sso_test_user_pass'] = array(
    '#type' => 'password',
    '#title' => t('Password of testing user'),
  );

  return system_settings_form($form);
}

/**
 * Returns an array of LDAP attributes.
 */
function simple_ldap_sso_get_attribute_options() {
  // Initialize a Simple LDAP Server object. Used for dropdown options.
  $server = SimpleLdapServer::singleton();

  $objectclass = config_get('simple_ldap_user.settings', 'simple_ldap_user_objectclass');

  $attributes = array();

  // Generate a list of attributes for the selected objectclass.
  foreach ($objectclass as $o) {
    $result = $server->schema->attributes($o, TRUE);
    foreach ($result as $attribute) {
      $attributes[strtolower($attribute)] = $attribute;
    }
  }
  asort($attributes);

  return $attributes;
}

/**
 * Element validate for the encryption salt variable.
 *
 * If the submitted value is empty and a previous value exists, it is preserved.
 */
function simple_ldap_sso_encryption_key_element_validate($element, &$form_state, $form) {
  if (empty($element['#value']) && $existing_salt = config_get('simple_ldap_sso.settings', 'simple_ldap_sso_encryption_key')) {
    form_set_value($element, $existing_salt, $form_state);
  }
}
