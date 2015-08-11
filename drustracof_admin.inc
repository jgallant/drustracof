<?php
function drustracof_admin() {
  $form = array();

  $form['drustracof_client_id'] = array(
    '#type' => 'textfield',
    '#title' => 'Strava Client ID',
    '#default_value' => variable_get('drustracof_client_id', ''),
    '#required' => TRUE,
  );

  return system_settings_form($form);
}