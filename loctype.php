<?php

/**
 * Implements hook_civicrm_install().
 */
function loctype_civicrm_install() {
    $extdebug = 1;
    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### LOCTYPE [INSTALL] 3.0 START DATABASE CHECK", "[CORE]");
    wachthond($extdebug, 2, "########################################################################");

    $sql = "SELECT count(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'civicrm_action_schedule' 
            AND COLUMN_NAME  = 'email_location_type_id' 
            AND TABLE_SCHEMA = DATABASE()";

    $exists = CRM_Core_DAO::singleValueQuery($sql);

    if (!$exists) {
        wachthond($extdebug, 1, "### LOCTYPE [INSTALL] Kolommen niet gevonden. Uitvoeren ALTER TABLE...");
        $alterSql = "ALTER TABLE civicrm_action_schedule 
                     ADD COLUMN `email_location_type_id` int(10) unsigned DEFAULT NULL, 
                     ADD COLUMN `email_selection_method` varchar(32)      DEFAULT 'primary'";
        CRM_Core_DAO::executeQuery($alterSql);
        wachthond($extdebug, 1, "### LOCTYPE [INSTALL] Database succesvol uitgebreid.");
    } else {
        wachthond($extdebug, 1, "### LOCTYPE [INSTALL] SKIP: Velden bestaan al in civicrm_action_schedule.");
    }
}

/**
 * Implements hook_civicrm_uninstall().
 */
function loctype_civicrm_uninstall() {
    $extdebug = 1;
    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### LOCTYPE [UNINSTALL] VERWIJDEREN DATABASE VELDEN", "[CORE]");
    wachthond($extdebug, 2, "########################################################################");
    
    $sql = "ALTER TABLE civicrm_action_schedule 
            DROP COLUMN `email_location_type_id`, 
            DROP COLUMN `email_selection_method`";
    
    try {
        CRM_Core_DAO::executeQuery($sql);
        wachthond($extdebug, 1, "### LOCTYPE [UNINSTALL] Kolommen succesvol verwijderd.");
    } catch (\Exception $e) {
        wachthond($extdebug, 1, "### LOCTYPE [UNINSTALL] ERROR: " . $e->getMessage());
    }
}

/**
 * Implements hook_civicrm_alterMailParams().
 */
function loctype_civicrm_alterMailParams(&$params, $context) {
    $extdebug = 1;
    
    // We loggen ALTIJD de binnenkomst om te zien of de hook überhaupt vuurt
    wachthond($extdebug, 1, "### LOCTYPE [HOOK] Binnenkomst alterMailParams. Context: " . $context);

    // Bepaal of we in de ActionSchedule flow zitten
    $isActionSchedule = ($context === 'actionSchedule' || (isset($params['entity']) && $params['entity'] === 'action_schedule'));

    if ($isActionSchedule || $context === 'singleEmail') {
        wachthond($extdebug, 1, "### LOCTYPE [HOOK] MATCH: Context valide (" . $context . "). Controleren op FlexListener...");
        
        if (class_exists('Civi\Loctype\FlexListener')) {
            wachthond($extdebug, 1, "### LOCTYPE [HOOK] Listener gevonden. Doorsturen naar FlexListener->onAlterMailParams.");
            $listener = new \Civi\Loctype\FlexListener();
            $listener->onAlterMailParams($params, $context);
        } else {
            wachthond($extdebug, 1, "### LOCTYPE [HOOK] CRITICAL: Class Civi\Loctype\FlexListener niet gevonden!");
        }
    } else {
        wachthond($extdebug, 1, "### LOCTYPE [HOOK] SKIP: Geen actie nodig voor deze context/entity.");
    }
}

/**
 * Implements hook_civicrm_buildForm().
 */
function loctype_civicrm_buildForm($formName, &$form) {
  if ($formName === 'CRM_Admin_Form_ScheduleReminders') {
    $extdebug = 1;
    wachthond($extdebug, 1, "### LOCTYPE [FORM] buildForm gestart voor ScheduleReminders");

    $locationTypes = \CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'location_type_id');
    
    // Voeg de velden toe aan het formulier (worden door FormFields.tpl in de modal geplaatst)
    $form->add('select', 'email_location_type_id', ts('Email Location'), 
      ['' => ts('- Default (Primary) -')] + $locationTypes, 
      false, 
      ['class' => 'crm-select2']
    );

    $form->add('select', 'email_selection_method', ts('Selection Method'), [
      'automatic'        => ts('Automatic'),
      'location_only'    => ts('Only send to specified location'),
      'location_prefer'  => ts('Prefer specified location'),
      'location_exclude' => ts('Exclude specified location'),
    ]);

    $id = $form->getVar('_id');
    if ($id) {
      $sql = "SELECT email_location_type_id, email_selection_method FROM civicrm_action_schedule WHERE id = %1";
      $defaults = CRM_Core_DAO::executeQuery($sql, [1 => [$id, 'Integer']])->fetch();
      
      if ($defaults) {
        $form->setDefaults([
          'email_location_type_id' => $defaults->email_location_type_id,
          'email_selection_method' => $defaults->email_selection_method ?? 'automatic',
        ]);
        wachthond($extdebug, 1, "### LOCTYPE [FORM] Defaults geladen voor SID: " . $id . " (Method: " . $defaults->email_selection_method . ")");
      }
    }

    // Injecteer de template die de Wrench en de Modal Dialog bevat
    CRM_Core_Region::instance('page-body')->add([
      'template' => 'CRM/Loctype/FormFields.tpl',
    ]);
  }
}

/**
 * Implements hook_civicrm_postProcess().
 */
function loctype_civicrm_postProcess($formName, &$form) {
  if ($formName === 'CRM_Admin_Form_ScheduleReminders') {
    $extdebug = 1;
    $id = $form->getVar('_id');
    $values = $form->exportValues();
    
    wachthond($extdebug, 1, "### LOCTYPE [POST] Opslaan gestart voor SID: " . $id);

    if ($id && isset($values['email_selection_method'])) {
      $dbParams = [
        1 => [$values['email_location_type_id'], 'Integer'],
        2 => [$values['email_selection_method'], 'String'],
        3 => [$id, 'Integer']
      ];
      
      $sql = "UPDATE civicrm_action_schedule 
              SET email_location_type_id = %1, email_selection_method = %2 
              WHERE id = %3";
      
      CRM_Core_DAO::executeQuery($sql, $dbParams);
      wachthond($extdebug, 1, "### LOCTYPE [POST] Database bijgewerkt. Method: " . $values['email_selection_method']);
    } else {
      wachthond($extdebug, 1, "### LOCTYPE [POST] SKIP: Onvoldoende data voor update.");
    }
  }
}
