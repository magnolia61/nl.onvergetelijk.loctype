<?php

namespace Civi\Loctype;

use \Civi\Test\CiviUnitTestCase;

/**
 * Test class voor de Loctype UI integratie.
 * * Controleert of de hook_civicrm_buildForm de velden correct injecteert
 * en of de hook_civicrm_postProcess de data opslaat in de database.
 */
class ScheduleRemindersFormTest extends CiviUnitTestCase {

  protected $reminderId;

  /**
   * Setup: Maak een herinnering aan om te kunnen bewerken.
   */
  public function setUp(): void {
    parent::setUp();
    
    // Maak een basis reminder aan via de API
    $result = $this->callAPISuccess('ActionSchedule', 'create', [
      'title'     => 'UI Test Reminder',
      'name'      => 'ui_test_reminder',
      'mapping_id'=> 'mailing',
      'entity'    => 'action_schedule',
      'mode'      => 'Email',
    ]);
    
    $this->reminderId = $result['id'];
  }

  public function tearDown(): void {
    $this->callAPISuccess('ActionSchedule', 'delete', ['id' => $this->reminderId]);
    parent::tearDown();
  }

  /**
   * Test 1: Controleer of de velden aanwezig zijn in het formulier object.
   */
  public function testFieldsExistInForm() {
    // We simuleren de opbouw van het formulier
    $form = new \CRM_Admin_Form_ScheduleReminders();
    
    // Simuleer CiviCRM variabelen die de hook nodig heeft
    $form->setVar('_id', $this->reminderId);
    
    // Roep de buildForm hook handmatig aan (CiviCRM doet dit normaal automatisch)
    loctype_civicrm_buildForm('CRM_Admin_Form_ScheduleReminders', $form);

    // Check of onze select velden zijn toegevoegd aan de QuickForm elementen
    $this->assertTrue($form->elementExists('email_location_type_id'), "Veld 'email_location_type_id' moet bestaan in het formulier.");
    $this->assertTrue($form->elementExists('email_selection_method'), "Veld 'email_selection_method' moet bestaan in het formulier.");
    
    // Check of de opties in de method dropdown de juiste keys hebben
    $methodElement = $form->getElement('email_selection_method');
    $options = $methodElement->_options;
    
    $foundPrefer = false;
    foreach ($options as $opt) {
      if ($opt['attr']['value'] === 'location_prefer') {
        $foundPrefer = true;
        break;
      }
    }
    $this->assertTrue($foundPrefer, "De optie 'location_prefer' moet aanwezig zijn in de dropdown.");
  }

  /**
   * Test 2: Controleer of postProcess de data daadwerkelijk wegschrijft.
   */
  public function testDataIsSavedInDatabase() {
    $form = new \CRM_Admin_Form_ScheduleReminders();
    $form->setVar('_id', $this->reminderId);
    
    // Simuleer de ingevulde waarden (wat normaal uit de modal/hidden fields komt)
    $testLocTypeId = 3; // Bijv. 'Billing'
    $testMethod    = 'location_only';
    
    $form->_exportValues = [
      'email_location_type_id' => $testLocTypeId,
      'email_selection_method' => $testMethod,
    ];

    // Roep de postProcess hook aan
    loctype_civicrm_postProcess('CRM_Admin_Form_ScheduleReminders', $form);

    // Controleer de database direct
    $dbData = \CRM_Core_DAO::executeQuery("
      SELECT email_location_type_id, email_selection_method 
      FROM civicrm_action_schedule 
      WHERE id = %1", 
      [1 => [$this->reminderId, 'Integer']]
    )->fetch();

    $this->assertEquals($testLocTypeId, $dbData->email_location_type_id, "Locatie ID moet correct zijn opgeslagen.");
    $this->assertEquals($testMethod, $dbData->email_selection_method, "Selectie methode moet correct zijn opgeslagen.");
  }
}
