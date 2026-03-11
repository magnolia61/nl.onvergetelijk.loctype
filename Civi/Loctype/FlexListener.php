<?php

namespace Civi\Loctype;

// DEZE REGELS ONTBREKEN WAARSCHIJNLIJK:
require_once __DIR__ . '/../../loctype.civix.php';
use CRM_Loctype_ExtensionUtil as E;

class FlexListener {

    /**
     * Onderschepping via hook_civicrm_alterMailParams.
     */
    public function onAlterMailParams(&$params, $context) {
        $extdebug = 1;

        // 1. Identificatie fase
        $contactId = $params['contactId'] ?? $params['contact_id'] ?? $params['contactID'] ?? NULL;
        $actionScheduleId = NULL;

        if (isset($params['entity']) && $params['entity'] === 'action_schedule') {
            $actionScheduleId = $params['entity_id'] ?? NULL;
        }

        if (!$actionScheduleId) {
            $actionScheduleId = $params['action_schedule_id'] ?? $params['schedule_id'] ?? NULL;
        }

        wachthond($extdebug, 2, "########################################################################");
        wachthond($extdebug, 1, "### LOCTYPE [LISTENER] 3.0 ANALYSE - CID: " . ($contactId ?: 'NULL') . " | SID: " . ($actionScheduleId ?: 'NULL'), "[MAIL]");
        wachthond($extdebug, 2, "########################################################################");

        // 2. Conditie check
        if (!$contactId || !$actionScheduleId || !is_numeric($actionScheduleId)) {
            wachthond($extdebug, 1, "### LOCTYPE [LISTENER] ABORT: Onvoldoende numerieke IDs gevonden in params.");
            return;
        }

        try {
            // 3. Database lookup instellingen
            $dbParams = [1 => [$actionScheduleId, 'Integer']];
            $sql      = "SELECT email_location_type_id, email_selection_method 
                         FROM civicrm_action_schedule WHERE id = %1";
            
            $dao = \CRM_Core_DAO::executeQuery($sql, $dbParams);

            if ($dao->fetch()) {
                $targetLocType = $dao->email_location_type_id;
                $method        = $dao->email_selection_method;

                wachthond($extdebug, 1, "### LOCTYPE [LISTENER] DB Result -> Type: " . ($targetLocType ?: 'NULL') . " | Method: " . ($method ?: 'NULL'));

                if (empty($targetLocType)) {
                    wachthond($extdebug, 1, "### LOCTYPE [LISTENER] EXIT: Geen specifiek locatietype geforceerd voor deze SID.");
                    return;
                }

                // 4. Zoeken naar specifiek e-mailadres bij dit contact
                $emailParams = [
                    1 => [$contactId, 'Integer'],
                    2 => [$targetLocType, 'Integer'],
                ];
                
                $sqlEmail = "SELECT email FROM civicrm_email 
                             WHERE contact_id = %1 AND location_type_id = %2 AND is_billing = 0 
                             ORDER BY id DESC LIMIT 1";

                $targetEmail = \CRM_Core_DAO::singleValueQuery($sqlEmail, $emailParams);

                // 5. Verwerking resultaat
                if ($targetEmail && !empty($targetEmail)) {
                    wachthond($extdebug, 1, "### LOCTYPE [LISTENER] MATCH! Nieuw adres gevonden: " . $targetEmail);
                    
                    // We overschrijven beide sleutels voor maximale compatibiliteit
                    $params['toEmail'] = $targetEmail;
                    $params['to']      = $targetEmail; 
                    
                    wachthond($extdebug, 1, "### LOCTYPE [LISTENER] SUCCESS: toEmail aangepast.");
                } else {
                    wachthond($extdebug, 1, "### LOCTYPE [LISTENER] GEEN MATCH: Locatietype " . $targetLocType . " niet aanwezig bij CID " . $contactId);
                    
                    if ($method === 'skip') {
                        $params['abortEmailSend'] = TRUE;
                        wachthond($extdebug, 1, "### LOCTYPE [LISTENER] ACTION: Verzending geblokkeerd (Method: skip).");
                    } else {
                        wachthond($extdebug, 1, "### LOCTYPE [LISTENER] ACTION: Geen actie, CiviCRM gebruikt standaard adres.");
                    }
                }
            } else {
                wachthond($extdebug, 1, "### LOCTYPE [LISTENER] DB ERROR: SID " . $actionScheduleId . " niet gevonden in database.");
            }
        } catch (\Exception $e) {
            wachthond($extdebug, 1, "### LOCTYPE [LISTENER] CRITICAL ERROR: " . $e->getMessage());
        }
    }
}
