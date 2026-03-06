<?php

namespace Civi\Loctype;

use \Civi\Test\CiviUnitTestCase;

/**
 * Uitgebreide test class voor de Loctype FlexListener.
 * * Dekt de volgende scenario's:
 * 1. location_only (Match / Geen Match)
 * 2. location_prefer (Fallback naar Primary)
 * 3. location_exclude (Blokkade op match)
 * 4. Dubbele adressen (Pakt de juiste)
 * 5. Billing filter (Negeert factuuradressen)
 * 6. Automatic methode (Doet niets)
 */
class FlexListenerTest extends CiviUnitTestCase {

  protected $contactId;
  protected $targetLocTypeId;
  protected $reminderId;

  /**
   * Setup: Maak een schone testomgeving aan.
   */
  public function setUp(): void {
    parent::setUp();

    // 1. Maak een test contact aan
    $this->contactId = $this->callAPISuccess('Contact', 'create', [
      'first_name'   => 'Test',
      'last_name'    => 'LoctypeUser',
      'contact_type' => 'Individual',
    ])['id'];

    // 2. We testen standaard met ID 2 (Home) als doel
    $this->targetLocTypeId = 2;

    // 3. Voeg een Primary Work adres toe als basis-fallback
    $this->callAPISuccess('Email', 'create', [
      'contact_id'       => $this->contactId,
      'email'            => 'fallback-work@example.com',
      'location_type_id' => 1, // Work
      'is_primary'       => 1,
    ]);

    // 4. Maak een mock record in civicrm_action_schedule
    $this->reminderId = 9999;
    $sql = "INSERT INTO civicrm_action_schedule
            (id, title, name, mapping_id, email_location_type_id, email_selection_method)
            VALUES (%1, 'Test Reminder', 'test_rem', '1', %2, 'automatic')";

    \CRM_Core_DAO::executeQuery($sql, [
      1 => [$this->reminderId, 'Integer'],
      2 => [$this->targetLocTypeId, 'Integer']
    ]);
  }

  /**
   * TearDown: Schoon de database op.
   */
  public function tearDown(): void {
    \CRM_Core_DAO::executeQuery("DELETE FROM civicrm_action_schedule WHERE id = %1", [1 => [$this->reminderId, 'Integer']]);
    $this->callAPISuccess('Contact', 'delete', ['id' => $this->contactId]);
    parent::tearDown();
  }

  // ########################################################################
  // ### SCENARIO 1: BASIC LOGIC (ONLY / PREFER / EXCLUDE)
  // ########################################################################

  public function testLocationOnlyMatch() {
    $this->updateMethod('location_only');
    $this->addEmail('target@example.com', $this->targetLocTypeId);

    $params = $this->getMockParams();
    (new FlexListener())->onAlterMailParams($params, 'actionSchedule');

    $this->assertEquals('target@example.com', $params['toEmail'], "toEmail moet overschreven zijn.");
  }

  public function testLocationOnlyNoMatchAbort() {
    $this->updateMethod('location_only');
    // Geen target email toegevoegd

    $params = $this->getMockParams();
    (new FlexListener())->onAlterMailParams($params, 'actionSchedule');

    $this->assertTrue(($params['abortEmailSend'] ?? FALSE), "Verzending moet afgebroken worden zonder match.");
  }

  public function testLocationPreferFallback() {
    $this->updateMethod('location_prefer');
    // Geen target email toegevoegd

    $params = $this->getMockParams();
    (new FlexListener())->onAlterMailParams($params, 'actionSchedule');

    $this->assertEquals('fallback-work@example.com', $params['toEmail'], "Moet terugvallen op primary bij prefer.");
    $this->assertFalse(($params['abortEmailSend'] ?? FALSE));
  }

  public function testLocationExclude() {
    $this->updateMethod('location_exclude');
    
    // De mail-engine wil naar 'target@example.com' sturen, maar dat type is verboden
    $params = $this->getMockParams('target@example.com');

    (new FlexListener())->onAlterMailParams($params, 'actionSchedule');

    $this->assertTrue(($params['abortEmailSend'] ?? FALSE), "Mail naar verboden type moet geblokkeerd worden.");
  }

  // ########################################################################
  // ### SCENARIO 2: EDGE CASES (DUBBEL / BILLING / AUTOMATIC)
  // ########################################################################

  /**
   * Test: Wat als er twee adressen van hetzelfde type zijn?
   * De SQL moet de meest logische pakken (meestal op ID of primary).
   */
  public function testMultipleEmailsSameType() {
    $this->updateMethod('location_only');
    $this->addEmail('old@example.com', $this->targetLocTypeId, 0);
    $this->addEmail('new@example.com', $this->targetLocTypeId, 1); // Deze is primary binnen het type

    $params = $this->getMockParams();
    (new FlexListener())->onAlterMailParams($params, 'actionSchedule');

    $this->assertEquals('new@example.com', $params['toEmail'], "Moet het primaire adres binnen het type kiezen.");
  }

  /**
   * Test: Factuuradressen (is_billing) moeten worden overgeslagen.
   */
  public function testIgnoreBillingAddress() {
    $this->updateMethod('location_only');
    
    // Voeg een adres toe met is_billing = 1
    $this->callAPISuccess('Email', 'create', [
      'contact_id'       => $this->contactId,
      'email'            => 'invoice@example.com',
      'location_type_id' => $this->targetLocTypeId,
      'is_billing'       => 1,
    ]);

    $params = $this->getMockParams();
    (new FlexListener())->onAlterMailParams($params, 'actionSchedule');

    // Omdat het enige adres van dit type een billing-adres is, mag het niet gepakt worden.
    $this->assertTrue(($params['abortEmailSend'] ?? FALSE), "Billing adres moet genegeerd worden bij location_only.");
  }

  /**
   * Test: Automatic methode mag niets wijzigen.
   */
  public function testAutomaticMethodDoesNothing() {
    $this->updateMethod('automatic');
    $this->addEmail('target@example.com', $this->targetLocTypeId);

    $params = $this->getMockParams('original@example.com');
    (new FlexListener())->onAlterMailParams($params, 'actionSchedule');

    $this->assertEquals('original@example.com', $params['toEmail'], "Bij Automatic mag toEmail niet wijzigen.");
  }

  // ########################################################################
  // ### HELPERS
  // ########################################################################

  private function updateMethod($method) {
    \CRM_Core_DAO::executeQuery("UPDATE civicrm_action_schedule SET email_selection_method = %1 WHERE id = %2", [
      1 => [$method, 'String'],
      2 => [$this->reminderId, 'Integer']
    ]);
  }

  private function addEmail($email, $locTypeId, $isPrimary = 0) {
    $this->callAPISuccess('Email', 'create', [
      'contact_id'       => $this->contactId,
      'email'            => $email,
      'location_type_id' => $locTypeId,
      'is_primary'       => $isPrimary,
      'is_billing'       => 0,
    ]);
  }

  private function getMockParams($toEmail = 'fallback-work@example.com') {
    return [
      'contactId' => $this->contactId,
      'entity'    => 'action_schedule',
      'entity_id' => $this->reminderId,
      'toEmail'   => $toEmail,
      'to'        => "Test User <$toEmail>",
    ];
  }
}
