<?php

/**
 * @file
 * Class defining the LDAP <-> Backdrop user field mappings.
 */

class SimpleLdapUserMap {
  // Singleton implementation.
  protected static $instance;
  public static function singleton($reset = FALSE) {
    if ($reset || !isset(self::$instance)) {
      self::$instance = new SimpleLdapUserMap();
    }
    return self::$instance;
  }

  // The attribute map, as processed from the Backdrop settings below.
  protected $map = array();
  // The unprocessed Backdrop settings.
  protected $settings = array();
  // The attribute schema.
  protected $schema = array();

  public function __construct() {
    $this->server = SimpleLdapServer::singleton();
    $this->processSettings();
  }

  public function __get($name) {
    if (!isset($this->{$name})) {
      return NULL;
    }
    return $this->{$name};
  }

  /**
   * Process the settings from variable_get(), looking up LDAP mappings in the
   * schema to be sure the mappings are correct.
   */
  protected function processSettings() {
    $this->settings = simple_ldap_user_variable_get('simple_ldap_user_attribute_map', array());
    $this->map = array();
    foreach ($this->settings as $key => $value) {
      // Look up the ldap attribute in the schema, to be sure we have the
      // correct name of the attribute, and not an alias. Convert to lowercase
      // before doing this, just in case.
      $schema = $this->getAttributeSchema(strtolower($value['ldap']));
      // If we don't have a name, log a warning and skip this mapping.
      if (!isset($schema['name'])) {
        $message = 'Unable to lookup schema for ldap attribute @attribute.';
        $t_args = array('@attribute' => $value['ldap']);
        watchdog('SimpleLdapUserMap::processSettings()', $message, $t_args, WATCHDOG_WARNING);
        continue;
      }
      // Convert to all lowercase.
      $this->map[$key]['ldap'] = strtolower($schema['name']);
      // Make sure the Backdrop attribute is an array.
      $this->map[$key]['backdrop'] = (array) $value['backdrop'];
    }
  }

  /**
   * Get the schema for a LDAP attribute.
   */
  protected function getAttributeSchema($attribute) {
    if (!isset($this->schema[$attribute])) {
      $this->schema[$attribute] = $this->server->schema->get('attributeTypes', $attribute);
    }
    return $this->schema[$attribute];
  }

  /**
   * Generate a list of attribute names from the attribute map for use in forms.
   * This pulls from the schema to make the human-readable version have the
   * right case.
   */
  public function getFormOptions() {
    $options = array();
    foreach ($this->map as $attribute) {
      $key = $attribute['ldap'];
      $schema = $this->getAttributeSchema($key);
      $options[$key] = $schema['name'];
    }
    asort($options);
    return $options;
  }

  /**
   * Disable mapped Backdrop fields in a form. Used in user profile forms when the
   * server is readonly.
   */
  public function disableMappedFormFields(&$form) {
    // Disable default properties if LDAP server is read-only.
    $form['account']['name']['#disabled'] =
      $form['account']['mail']['#disabled'] =
      $form['account']['pass']['#disabled'] = TRUE;

    // Disable mapped fields if LDAP Server is read-only.
    foreach ($this->map as $attribute) {
      // Handle Backdrop many-to-one mappings.
      foreach ($attribute['backdrop'] as $backdrop_attribute) {
        if (!($parsed = $this->parseBackdropAttribute($backdrop_attribute))) {
          // If we couldn't parse the field name, just continue.
          continue;
        }
        list($is_field, $field_name) = $parsed;

        $parents = array($field_name, '#disabled');
        // If this is not a field, it is a property, and therefore lives in
        // $form['account'], so add that to the parents.
        if (!$is_field) {
          array_unshift($parents, 'account');
        }
        // Now set #disabled to TRUE.
        backdrop_array_set_nested_value($form, $parents, TRUE);
      }
    }
  }

  /**
   * Map from LDAP to Backdrop.
   *
   * @param SimpleLdapUser $ldap_user
   *   The LDAP user.
   * @param Array $edit
   *   The edit array, as passed to user_save().
   * @param stdClass $backdrop_user
   *   The Backdrop user account object.
   */
  public function mapFromLdapToBackdrop(SimpleLdapUser $ldap_user, array &$edit, stdClass $backdrop_user) {
    // Mail is a special attribute.
    $attribute_mail = simple_ldap_user_variable_get('simple_ldap_user_attribute_mail');
    if (isset($ldap_user->{$attribute_mail}[0]) && (!empty($backdrop_user->is_new) || $backdrop_user->mail != $ldap_user->{$attribute_mail}[0])) {
      $edit['mail'] = $ldap_user->{$attribute_mail}[0];
    }

    // Synchronize the fields in the attribute map.
    foreach ($this->map as $attribute) {

      // Skip Backdrop-to-ldap many-to-one mappings.
      if (count($attribute['backdrop']) > 1) {
        continue;
      }

      // Get the Backdrop field name and type.
      $backdrop_attribute = reset($attribute['backdrop']);
      $ldap_attribute = $ldap_user->{$attribute['ldap']};

      // If no records were found in LDAP, continue.
      if (!isset($ldap_attribute['count']) || $ldap_attribute['count'] == 0) {
        continue;
      }

      // Skip this field if it couldn't be parsed.
      if (!($parsed = $this->parseBackdropAttribute($backdrop_attribute))) {
        continue;
      }
      list($is_field, $field_name) = $parsed;

      // To avoid notices, set the value to NULL. This would most often be the
      // case on inserts.
      if (!isset($backdrop_user->$field_name)) {
        $backdrop_user->$field_name = NULL;
      }

      // If this is a Field API field, store in appropriate array structure.
      if ($is_field) {
        $field_info = field_info_field($field_name);
        $check_cardinality = $field_info['cardinality'] != FIELD_CARDINALITY_UNLIMITED;
        $language = field_language('user', $backdrop_user, $field_name);

        // Determine the columns for this field.
        $columns = $this->backdropAttributeColumns($backdrop_attribute);
        // Iterate over each LDAP value.
        for ($i = 0; $i < $ldap_attribute['count']; $i++) {
          // If cardinality has been reached, break.
          if ($check_cardinality && $i >= $field_info['cardinality']) {
            break;
          }
          // Don't mess with $columns, so it stays consistent for each value.
          $parents = $columns;
          array_unshift($parents, $field_name, $language, $i);
          backdrop_array_set_nested_value($edit, $parents, $ldap_attribute[$i]);
        }
      }
      // Otherwise, this is just a property on the user, and we just grab the
      // first record from ldap.
      else {
        $edit[$field_name] = $ldap_attribute[0];
      }
    }
  }

  /**
   * Map from Backdrop to LDAP.
   *
   * @param stdClass $backdrop_user
   *   The Backdrop user account object.
   * @param SimpleLdapUser $ldap_user
   *   The LDAP user object.
   */
  public function mapFromBackdropToLdap(stdClass $backdrop_user, SimpleLdapUser $ldap_user) {
    // Synchronize the fields in the attribute map.
    foreach ($this->map as $attribute) {
      // Initialize the Backdrop value array.
      $backdrop_values = array();

      // Parse the Backdrop attribute name.
      foreach ($attribute['backdrop'] as $backdrop_attribute) {
        // Skip this field if it couldn't be parsed.
        if (!($parsed = $this->parseBackdropAttribute($backdrop_attribute))) {
          continue;
        }
        list($is_field, $field_name) = $parsed;

        // If this is a Field API field, use Field API to get the values.
        if ($is_field) {
          // If we have no items, just skip this field.
          if (!($items = field_get_items('user', $backdrop_user, $field_name))) {
            continue;
          }
          // Otherwise, set up an array of values.
          $values = array();
          // Parse the columns from the attribute name.
          $columns = $this->backdropAttributeColumns($backdrop_attribute);
          foreach ($items as $key => $item) {
            // If we have a value, add it to the array.
            if ($value = backdrop_array_get_nested_value($item, $columns)) {
              $values[] = $value;
            }
          }
          $backdrop_values[] = $values;
        }
        else {
          // Get the value directly from the user object.
          $backdrop_values[] = array($backdrop_user->$field_name);
        }
      }

      // Merge the $backdrop_values array into uniform values for the LDAP server.
      // This is needed to account for Backdrop attributes of mixed types.
      // First, find the largest value array.
      $size = 0;
      foreach ($backdrop_values as $backdrop_value) {
        $count = count($backdrop_value);
        if ($count > $size) {
          $size = $count;
        }
      }

      // Then, generate the ldap array.
      $ldap_values = array();
      for ($i = 0; $i < $size; $i++) {
        $ldap_values[$i] = '';
        foreach ($backdrop_values as $values) {
          if (isset($values[$i])) {
            $ldap_values[$i] .= ' ' . $values[$i];
          }
        }
        $ldap_values[$i] = trim($ldap_values[$i]);
      }

      // Finally, add the values to the LDAP user.
      $ldap_user->{$attribute['ldap']} = $ldap_values;
    }
  }

  /**
   * Helper function to parse a Backdrop field name, as mapped to in the variable
   * called simple_ldap_user_attribute_map.
   *
   * @param string $backdrop_attribute
   *   The string name of the field.
   * @return array
   *   An array containing a boolean value to signify whether the field is a Field
   *   API field, and the actual field name that was matched.
   */
  function parseBackdropAttribute($backdrop_attribute) {
    // Use a regex to capture the name only, not the leading hash or any
    // trailing field columns inside of brackets.
    preg_match('/^(#?)([a-zA-Z0-9_]+)/', $backdrop_attribute, $matches);
    // Parse the matches we want to some variables.
    list( , $is_field, $field_name) = $matches;
    // If field name is empty, the match failed.
    if (empty($field_name)) {
      return FALSE;
    }
    return array((bool) $is_field, $field_name);
  }

  /**
   * Helper function to retrieve column names from a Backdrop attribute mapping for
   * a Field API field.
   *
   * @param string $backdrop_attribute
   *   The name of the field as declared in the attribute mapping.
   * @return array
   *   An array of columns for the field. Defaults to a single element of 'value'.
   */
  function backdropAttributeColumns($backdrop_attribute) {
    // Look for nested columns in the attribute name, specified by
    // brackets, ie field_foo[bar][baz].
    preg_match_all('/\[([a-zA-Z0-9_]+)\]/', $backdrop_attribute, $matches);

    // If there are nested columns, use them, otherwise default to value.
    return empty($matches[1]) ? array('value') : $matches[1];
  }
}
